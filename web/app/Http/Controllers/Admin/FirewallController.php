<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotSetting;
use App\Models\FirewallRule;
use App\Models\LoginLog;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FirewallController extends Controller
{
    public function index(Request $request)
    {
        $typeFilter = $request->query('type', 'all');
        $search = trim((string) $request->query('search', ''));

        $rulesQuery = FirewallRule::with('creator')
            ->orderByDesc('is_active')
            ->orderByDesc('id');

        if (in_array($typeFilter, ['ip', 'subnet', 'telegram_id'], true)) {
            $rulesQuery->where('type', $typeFilter);
        }

        if ($search !== '') {
            $rulesQuery->where(function ($q) use ($search) {
                $q->where('value', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $rules = $rulesQuery->paginate(15)->withQueryString();

        // Core KPI metrics
        $activeRulesCount = FirewallRule::effective()->count();
        $totalBlockedHits = (int) FirewallRule::sum('hit_count');

        $failedLogins24h = LoginLog::where('is_successful', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $successLogins24h = LoginLog::where('is_successful', true)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $totalLogins24h = $failedLogins24h + $successLogins24h;
        $failRate24h = $totalLogins24h > 0 ? round(($failedLogins24h / $totalLogins24h) * 100, 1) : 0.0;

        $threatLevel = 'low';
        if ($failedLogins24h >= 50 || $failRate24h >= 40) {
            $threatLevel = 'high';
        } elseif ($failedLogins24h >= 15 || $failRate24h >= 20) {
            $threatLevel = 'medium';
        }

        $suspendedUsersCount = User::where('is_suspended', true)->count();
        $totalUsersCount = max(1, User::count());
        $twoFactorUsersCount = User::where(function ($q) {
            $q->where('two_factor_enabled', true)->orWhere('totp_enabled', true);
        })->count();
        $twoFactorAdoptionRate = round(($twoFactorUsersCount / $totalUsersCount) * 100, 1);

        $activeSessionsCount = Schema::hasTable('user_sessions')
            ? UserSession::whereNull('revoked_at')->where('last_active_at', '>=', now()->subHours(24))->count()
            : 0;

        // 7-day Attack vs Legitimate Login Trend
        $trendLabels = [];
        $trendFailed = [];
        $trendSuccess = [];
        $logs7d = LoginLog::where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->get(['is_successful', 'created_at']);

        $byDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $key = now()->subDays($i)->format('Y-m-d');
            $trendLabels[] = now()->subDays($i)->format('d M');
            $byDay[$key] = ['failed' => 0, 'success' => 0];
        }
        foreach ($logs7d as $log) {
            $d = $log->created_at ? $log->created_at->format('Y-m-d') : null;
            if ($d && isset($byDay[$d])) {
                if ($log->is_successful) {
                    $byDay[$d]['success']++;
                } else {
                    $byDay[$d]['failed']++;
                }
            }
        }
        foreach ($byDay as $vals) {
            $trendFailed[] = $vals['failed'];
            $trendSuccess[] = $vals['success'];
        }

        // Top aggressive / suspicious IPs (7 days) with unique targets & geo snapshot
        $existingBlockedIps = FirewallRule::effective()
            ->where('type', 'ip')
            ->pluck('value')
            ->flip();

        $topSuspiciousIps = LoginLog::where('is_successful', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('ip_address')
            ->selectRaw('ip_address, COUNT(*) as fail_count, COUNT(DISTINCT username_or_email) as unique_targets, MAX(location) as last_location, MAX(country) as last_country, MAX(created_at) as last_seen')
            ->groupBy('ip_address')
            ->orderByDesc('fail_count')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($existingBlockedIps) {
                $row->is_blocked = $existingBlockedIps->has($row->ip_address) || Cache::has('blocked_ip:' . $row->ip_address);
                $row->subnet_24 = filter_var($row->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    ? preg_replace('/\.\d+$/', '.0/24', $row->ip_address)
                    : null;
                return $row;
            });

        // Most targeted accounts (credential stuffing / brute-force targets)
        $targetedAccounts = LoginLog::where('is_successful', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('username_or_email')
            ->where('username_or_email', '!=', '')
            ->selectRaw('username_or_email, COUNT(*) as fail_count, COUNT(DISTINCT ip_address) as unique_ips, MAX(created_at) as last_attempt')
            ->groupBy('username_or_email')
            ->orderByDesc('fail_count')
            ->limit(8)
            ->get();

        // Multi-account registration IP anomaly detection (Anti-Spam)
        $multiAccountIps = User::whereNotNull('registration_ip')
            ->where('registration_ip', '!=', '')
            ->selectRaw('registration_ip, COUNT(*) as account_count, SUM(CASE WHEN is_suspended = 1 THEN 1 ELSE 0 END) as suspended_count, MAX(created_at) as latest_reg')
            ->groupBy('registration_ip')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('account_count')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($existingBlockedIps) {
                $row->is_blocked = $existingBlockedIps->has($row->registration_ip) || Cache::has('blocked_ip:' . $row->registration_ip);
                return $row;
            });

        // Recent active user sessions for live security supervision
        $recentSessions = Schema::hasTable('user_sessions')
            ? UserSession::with('user')
                ->whereNull('revoked_at')
                ->orderByDesc('last_active_at')
                ->limit(10)
                ->get()
            : collect();

        // Recently suspended users
        $suspendedUsers = User::where('is_suspended', true)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'username', 'full_name', 'registration_ip', 'suspension_reason', 'updated_at']);

        // Recent security audit trail
        $recentSecurityEvents = Schema::hasTable('audit_logs')
            ? DB::table('audit_logs')
                ->where(function ($q) {
                    $q->where('action', 'like', 'firewall_%')
                        ->orWhere('action', 'like', 'totp_%')
                        ->orWhere('action', 'like', 'sessions_%')
                        ->orWhere('action', 'user_role_change')
                        ->orWhere('action', 'like', '%block%');
                })
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
            : collect();

        // Current Security Posture Checklist
        $spamThreshold = (int) (BotSetting::where('key', 'spam_registration_threshold')->value('value') ?? 5);
        $securityPosture = [
            'turnstile_active' => !empty(config('services.turnstile.site_key'))
                && !empty(config('services.turnstile.secret_key'))
                && config('services.turnstile.site_key') !== '1x00000000000000000000AA',
            'admin_2fa_enabled' => (bool) (Auth::user()?->hasAnyTwoFactorEnabled()),
            'maintenance_active' => BotSetting::where('key', 'maintenance_mode')->value('value') === '1',
            'spam_threshold' => $spamThreshold,
            'app_debug_off' => !config('app.debug'),
        ];

        return view('admin.security.firewall', compact(
            'rules',
            'typeFilter',
            'search',
            'activeRulesCount',
            'totalBlockedHits',
            'failedLogins24h',
            'successLogins24h',
            'failRate24h',
            'threatLevel',
            'suspendedUsersCount',
            'twoFactorUsersCount',
            'twoFactorAdoptionRate',
            'activeSessionsCount',
            'trendLabels',
            'trendFailed',
            'trendSuccess',
            'topSuspiciousIps',
            'targetedAccounts',
            'multiAccountIps',
            'recentSessions',
            'suspendedUsers',
            'recentSecurityEvents',
            'securityPosture'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:ip,subnet,telegram_id',
            'value' => 'required|string|max:100',
            'reason' => 'nullable|string|max:255',
            'duration_days' => 'nullable|integer|in:0,1,7,30,365',
        ]);

        $type = $request->input('type');
        $value = trim((string) $request->input('value'));

        if ($type === 'ip' && !filter_var($value, FILTER_VALIDATE_IP)) {
            return redirect()->back()->withErrors(['value' => __('Format alamat IP tidak valid.')])->withInput();
        }

        if ($type === 'subnet') {
            if (!preg_match('/^(\d{1,3}\.){3}\d{1,3}\/([0-9]|[1-2][0-9]|3[0-2])$/', $value)) {
                return redirect()->back()->withErrors(['value' => __('Format subnet CIDR tidak valid (contoh: 103.10.20.0/24).')])->withInput();
            }
            [$ipPart] = explode('/', $value, 2);
            if (!filter_var($ipPart, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return redirect()->back()->withErrors(['value' => __('Alamat IPv4 pada subnet CIDR tidak valid.')])->withInput();
            }
        }

        if ($type === 'telegram_id' && !preg_match('/^\d{4,20}$/', $value)) {
            return redirect()->back()->withErrors(['value' => __('ID Telegram harus berupa angka 4-20 digit.')])->withInput();
        }

        $days = (int) $request->input('duration_days', 7);
        $expiresAt = $days > 0 ? now()->addDays($days) : null;

        $rule = FirewallRule::updateOrCreate(
            ['type' => $type, 'value' => $value],
            [
                'reason' => $request->input('reason') ?: __('Diblokir melalui Firewall Center'),
                'expires_at' => $expiresAt,
                'created_by' => Auth::id(),
                'is_active' => true,
            ]
        );

        if ($type === 'ip') {
            Cache::put('blocked_ip:' . $value, true, $expiresAt ?? now()->addYears(5));
        }

        Cache::forget('firewall:effective_rules');

        DB::table('audit_logs')->insert([
            'action' => 'firewall_rule_created',
            'actor_id' => Auth::id(),
            'entity_type' => 'firewall_rule',
            'entity_id' => $rule->id,
            'detail' => "type={$type}; value={$value}; days={$days}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.security.firewall')
            ->with('success', __('Aturan firewall (:value) berhasil diterapkan.', ['value' => $value]));
    }

    public function toggle($id)
    {
        $rule = FirewallRule::findOrFail($id);
        $newState = !$rule->is_active;
        $rule->update(['is_active' => $newState]);

        if ($rule->type === 'ip') {
            if ($newState) {
                Cache::put('blocked_ip:' . $rule->value, true, $rule->expires_at ?? now()->addYears(5));
            } else {
                Cache::forget('blocked_ip:' . $rule->value);
            }
        }
        Cache::forget('firewall:effective_rules');

        DB::table('audit_logs')->insert([
            'action' => $newState ? 'firewall_rule_enabled' : 'firewall_rule_disabled',
            'actor_id' => Auth::id(),
            'entity_type' => 'firewall_rule',
            'entity_id' => $rule->id,
            'detail' => "type={$rule->type}; value={$rule->value}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.security.firewall')
            ->with('success', $newState
                ? __('Aturan firewall (:value) diaktifkan kembali.', ['value' => $rule->value])
                : __('Aturan firewall (:value) dijeda sementara.', ['value' => $rule->value]));
    }

    public function autoBlockAggressive(Request $request)
    {
        $threshold = max(3, min(100, (int) $request->input('threshold', 5)));
        $days = in_array((int) $request->input('duration_days', 7), [1, 7, 30, 365], true)
            ? (int) $request->input('duration_days', 7)
            : 7;

        $aggressiveIps = LoginLog::where('is_successful', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->whereNotNull('ip_address')
            ->selectRaw('ip_address, COUNT(*) as fail_count')
            ->groupBy('ip_address')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get();

        $blockedCount = 0;
        $myIp = $request->ip();

        foreach ($aggressiveIps as $row) {
            if ($row->ip_address === $myIp || $row->ip_address === '127.0.0.1' || $row->ip_address === '::1') {
                continue;
            }
            if (!filter_var($row->ip_address, FILTER_VALIDATE_IP)) {
                continue;
            }

            $expiresAt = now()->addDays($days);
            FirewallRule::updateOrCreate(
                ['type' => 'ip', 'value' => $row->ip_address],
                [
                    'reason' => __('Auto-blokir: :count percobaan login gagal dalam 24 jam', ['count' => $row->fail_count]),
                    'expires_at' => $expiresAt,
                    'created_by' => Auth::id(),
                    'is_active' => true,
                ]
            );
            Cache::put('blocked_ip:' . $row->ip_address, true, $expiresAt);
            $blockedCount++;
        }

        Cache::forget('firewall:effective_rules');

        if ($blockedCount > 0) {
            DB::table('audit_logs')->insert([
                'action' => 'firewall_auto_block',
                'actor_id' => Auth::id(),
                'entity_type' => 'firewall_rule',
                'entity_id' => 0,
                'detail' => "blocked_count={$blockedCount}; threshold={$threshold}; days={$days}",
                'created_at' => now(),
            ]);
        }

        return redirect()->route('admin.security.firewall')
            ->with('success', $blockedCount > 0
                ? __('Auto-blokir selesai: :count IP agresif berhasil ditambahkan ke firewall.', ['count' => $blockedCount])
                : __('Tidak ditemukan IP agresif baru di atas ambang :threshold kali gagal login dalam 24 jam.', ['threshold' => $threshold]));
    }

    public function revokeUserSession($id)
    {
        $session = UserSession::findOrFail($id);
        $session->update(['revoked_at' => now()]);

        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('id', $session->session_id)->delete();
        }

        DB::table('audit_logs')->insert([
            'action' => 'sessions_revoked_by_admin',
            'actor_id' => Auth::id(),
            'entity_type' => 'user_session',
            'entity_id' => $session->id,
            'detail' => "target_user_id={$session->user_id}; ip={$session->ip_address}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.security.firewall')
            ->with('success', __('Sesi perangkat pengguna berhasil dicabut paksa.'));
    }

    public function unsuspendUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_suspended = false;
        $user->suspension_reason = null;
        $user->save();

        DB::table('audit_logs')->insert([
            'action' => 'user_unsuspended_from_firewall',
            'actor_id' => Auth::id(),
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'detail' => "username={$user->username}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.security.firewall')
            ->with('success', __('Akun :name berhasil dipulihkan dari penangguhan.', ['name' => $user->username ?: $user->full_name]));
    }

    public function updatePolicy(Request $request)
    {
        $request->validate([
            'spam_registration_threshold' => 'required|integer|min:2|max:50',
        ]);

        BotSetting::updateOrCreate(
            ['key' => 'spam_registration_threshold'],
            ['value' => (string) $request->input('spam_registration_threshold')]
        );

        return redirect()->route('admin.security.firewall')
            ->with('success', __('Kebijakan batas deteksi spam registrasi berhasil diperbarui.'));
    }

    public function destroy($id)
    {
        $rule = FirewallRule::findOrFail($id);
        $value = $rule->value;
        $type = $rule->type;

        $rule->delete();

        if ($type === 'ip') {
            Cache::forget('blocked_ip:' . $value);
        }
        Cache::forget('firewall:effective_rules');

        DB::table('audit_logs')->insert([
            'action' => 'firewall_rule_deleted',
            'actor_id' => Auth::id(),
            'entity_type' => 'firewall_rule',
            'entity_id' => (int) $id,
            'detail' => "type={$type}; value={$value}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.security.firewall')
            ->with('success', __('Aturan firewall (:value) telah dihapus.', ['value' => $value]));
    }
}
