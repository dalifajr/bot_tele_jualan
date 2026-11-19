<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotSetting;
use App\Models\FirewallRule;
use App\Models\LoginLog;
use App\Models\User;
use App\Models\UserSession;
use App\Services\SecurityHubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FirewallController extends Controller
{
    protected function redirectBackToTab(Request $request)
    {
        $tab = trim((string) $request->input('active_tab', ''));
        if ($tab !== '' && in_array($tab, SecurityHubService::VALID_TABS, true)) {
            return redirect()->route('admin.security.firewall', ['tab' => $tab]);
        }

        return redirect()->route('admin.security.firewall');
    }

    public function index(Request $request, SecurityHubService $hubService)
    {
        $data = $hubService->buildHubData($request, 'firewall');
        return view('admin.security.hub', $data);
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

        return $this->redirectBackToTab($request)
            ->with('success', __('Aturan firewall (:value) berhasil diterapkan.', ['value' => $value]));
    }

    public function toggle(Request $request, $id)
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

        return $this->redirectBackToTab($request)
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

        return $this->redirectBackToTab($request)
            ->with('success', $blockedCount > 0
                ? __('Auto-blokir selesai: :count IP agresif berhasil ditambahkan ke firewall.', ['count' => $blockedCount])
                : __('Tidak ditemukan IP agresif baru di atas ambang :threshold kali gagal login dalam 24 jam.', ['threshold' => $threshold]));
    }

    public function revokeUserSession(Request $request, $id)
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

        return $this->redirectBackToTab($request)
            ->with('success', __('Sesi perangkat pengguna berhasil dicabut paksa.'));
    }

    public function unsuspendUser(Request $request, $id)
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

        return $this->redirectBackToTab($request)
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

        return $this->redirectBackToTab($request)
            ->with('success', __('Kebijakan batas deteksi spam registrasi berhasil diperbarui.'));
    }

    public function destroy(Request $request, $id)
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

        return $this->redirectBackToTab($request)
            ->with('success', __('Aturan firewall (:value) telah dihapus.', ['value' => $value]));
    }
}
