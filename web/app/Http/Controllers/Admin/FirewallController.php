<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FirewallRule;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FirewallController extends Controller
{
    public function index()
    {
        $rules = FirewallRule::with('creator')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->paginate(20);

        $activeRulesCount = FirewallRule::effective()->count();
        $failedLogins24h = LoginLog::where('is_success', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $suspendedUsersCount = User::where('is_suspended', true)->count();

        $topSuspiciousIps = LoginLog::where('is_success', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('ip_address')
            ->selectRaw('ip_address, COUNT(*) as fail_count, MAX(created_at) as last_seen')
            ->groupBy('ip_address')
            ->orderByDesc('fail_count')
            ->limit(8)
            ->get();

        return view('admin.security.firewall', compact(
            'rules',
            'activeRulesCount',
            'failedLogins24h',
            'suspendedUsersCount',
            'topSuspiciousIps'
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

        $rule = FirewallRule::create([
            'type' => $type,
            'value' => $value,
            'reason' => $request->input('reason') ?: __('Diblokir melalui Firewall Center'),
            'expires_at' => $expiresAt,
            'created_by' => Auth::id(),
            'is_active' => true,
        ]);

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
