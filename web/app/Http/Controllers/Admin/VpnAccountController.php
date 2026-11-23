<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\VpnService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VpnAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = VpnAccount::with(['user', 'order', 'vpnServer', 'suspendedBy'])
            ->orderByDesc('created_at');

        if ($request->filled('server_id')) {
            $query->where('vpn_server_id', (int) $request->input('server_id'));
        }
        if ($request->filled('protocol')) {
            $query->where('protocol', strtolower((string) $request->input('protocol')));
        }
        if ($request->filled('status')) {
            $status = strtolower((string) $request->input('status'));
            if ($status === 'expired') {
                $query->where(function ($q) {
                    $q->where('status', 'expired')
                      ->orWhere(function ($q2) {
                          $q2->where('status', 'active')->whereNotNull('expired_at')->where('expired_at', '<', now());
                      });
                });
            } else {
                $query->where('status', $status);
            }
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('server_ip', 'like', "%{$search}%")
                  ->orWhere('external_ref', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $accounts = $query->paginate(20)->withQueryString();
        $servers = VpnServer::orderByDesc('is_default')->orderBy('name')->get();
        $users = User::orderBy('full_name')->orderBy('username')->limit(100)->get(['id', 'full_name', 'username', 'email', 'role']);

        $stats = [
            'total' => VpnAccount::count(),
            'active' => VpnAccount::where('status', 'active')->count(),
            'suspended' => VpnAccount::where('status', 'suspended')->count(),
            'failed' => VpnAccount::whereIn('status', ['failed', 'provisioning'])->count(),
        ];

        return view('admin.vpn.accounts', compact('accounts', 'servers', 'users', 'stats'));
    }

    public function store(Request $request, VpnService $vpnService)
    {
        $validated = $request->validate([
            'vpn_server_id' => 'nullable|exists:vpn_servers,id',
            'user_id' => 'required|exists:users,id',
            'protocol' => 'required|in:ssh,vmess,vless,trojan,shadowsocks',
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{3,24}$/'],
            'password' => 'required_if:protocol,ssh|nullable|string|min:4|max:64',
            'days' => 'required|integer|min:1|max:365',
            'quota_gb' => 'nullable|integer|min:0|max:10000',
            'ip_limit' => 'nullable|integer|min:1|max:50',
            'sni_mode' => 'nullable|in:1,2,3',
        ]);

        $server = !empty($validated['vpn_server_id'])
            ? VpnServer::find($validated['vpn_server_id'])
            : $vpnService->resolveServer();

        if (!$server) {
            return back()->with('error', __('Belum ada Server VPN yang aktif dan terkonfigurasi.'));
        }

        $account = VpnAccount::create([
            'user_id' => (int) $validated['user_id'],
            'vpn_server_id' => $server->exists ? $server->id : null,
            'protocol' => $validated['protocol'],
            'username' => $validated['username'],
            'password' => $validated['password'] ?? null,
            'quota_gb' => (int) ($validated['quota_gb'] ?? 0),
            'ip_limit' => (int) ($validated['ip_limit'] ?? 1),
            'external_ref' => 'admin_manual_' . Auth::id(),
            'expired_at' => now()->addDays((int) $validated['days']),
            'status' => 'provisioning',
        ]);

        $provisioned = $vpnService->provisionAccount(
            $account,
            (int) $validated['days'],
            'admin-create-' . $account->id,
            (string) ($validated['sni_mode'] ?? '3')
        );

        $this->logAudit('admin_vpn_create', $provisioned, "protocol={$provisioned->protocol}; username={$provisioned->username}; status={$provisioned->status}");

        if ($provisioned->status === 'active') {
            return redirect()->route('admin.vpn-accounts.index')
                ->with('success', __('Akun VPN :user (:proto) berhasil dibuat.', ['user' => $provisioned->username, 'proto' => strtoupper($provisioned->protocol)]));
        }

        return redirect()->route('admin.vpn-accounts.index')
            ->with('error', __('Akun disimpan dengan status gagal: :err', ['err' => $provisioned->last_error ?: 'Unknown error']));
    }

    public function renew(Request $request, $id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'quota_gb' => 'nullable|integer|min:0|max:10000',
            'ip_limit' => 'nullable|integer|min:1|max:50',
        ]);

        $res = $vpnService->renewAccount(
            $account,
            (int) $validated['days'],
            $request->filled('quota_gb') ? (int) $validated['quota_gb'] : null,
            $request->filled('ip_limit') ? (int) $validated['ip_limit'] : null
        );

        $this->logAudit('admin_vpn_renew', $account, "days={$validated['days']}; success=" . (!empty($res['success']) ? '1' : '0'));

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil diperpanjang :days hari.', ['user' => $account->username, 'days' => $validated['days']])
                : ($res['message'] ?? __('Gagal memperpanjang akun VPN.'))
        );
    }

    public function suspend(Request $request, $id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));

        $res = $vpnService->suspendAccount(
            $account,
            'admin',
            Auth::id(),
            $reason !== '' ? $reason : __('Ditangguhkan oleh Admin')
        );

        $this->logAudit('admin_vpn_suspend', $account, "reason={$reason}; success=" . (!empty($res['success']) ? '1' : '0'));

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil disuspend (terkunci dari unsuspend mandiri pelanggan).', ['user' => $account->username])
                : ($res['message'] ?? __('Gagal mensuspend akun VPN.'))
        );
    }

    public function unsuspend($id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);
        $res = $vpnService->unsuspendAccount($account);

        $this->logAudit('admin_vpn_unsuspend', $account, 'success=' . (!empty($res['success']) ? '1' : '0'));

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil diaktifkan kembali.', ['user' => $account->username])
                : ($res['message'] ?? __('Gagal mengaktifkan kembali akun VPN.'))
        );
    }

    public function retry($id, VpnService $vpnService)
    {
        $account = VpnAccount::with(['vpnServer', 'order.items.product'])->findOrFail($id);

        $days = 30;
        $sniMode = '3';
        if ($account->order) {
            $vpnItem = $account->order->items->first(fn ($i) => $i->product && $i->product->is_vpn);
            if ($vpnItem && $vpnItem->product) {
                $days = max(1, (int) ($vpnItem->product->vpn_duration_days ?: 30));
                $sniMode = (string) ($vpnItem->product->vpn_sni_mode ?? '3');
            }
        } elseif ($account->expired_at && $account->created_at) {
            $days = max(1, min(365, (int) $account->created_at->diffInDays($account->expired_at)));
        }

        $idempotencyKey = $account->external_ref
            ? "order-{$account->external_ref}-acct-{$account->id}"
            : "vpn-acct-{$account->id}-create";

        $provisioned = $vpnService->provisionAccount($account, $days, $idempotencyKey, $sniMode);
        $this->logAudit('admin_vpn_retry', $provisioned, "status={$provisioned->status}");

        return back()->with(
            $provisioned->status === 'active' ? 'success' : 'error',
            $provisioned->status === 'active'
                ? __('Provisioning ulang akun :user berhasil!', ['user' => $provisioned->username])
                : __('Provisioning ulang gagal: :err', ['err' => $provisioned->last_error ?: 'Unknown error'])
        );
    }

    public function sync($id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);
        $res = $vpnService->syncAccountFromPanel($account);

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            $res['message'] ?? __('Sinkronisasi selesai.')
        );
    }

    public function syncServer(Request $request, VpnService $vpnService)
    {
        $validated = $request->validate([
            'vpn_server_id' => 'required|exists:vpn_servers,id',
        ]);

        $server = VpnServer::findOrFail($validated['vpn_server_id']);
        $res = $vpnService->listRemoteAccounts($server);
        if (empty($res['success'])) {
            return back()->with('error', $res['message'] ?? __('Gagal menarik daftar akun dari panel VPN.'));
        }

        $synced = 0;
        $imported = 0;
        $adminId = Auth::id();

        foreach ($res['accounts'] as $remote) {
            $protocol = strtolower((string) ($remote['protocol'] ?? ''));
            $username = (string) ($remote['username'] ?? '');
            if ($protocol === '' || $username === '') {
                continue;
            }

            $existing = VpnAccount::where('vpn_server_id', $server->id)
                ->where('protocol', $protocol)
                ->where('username', $username)
                ->first();

            $expiredAt = !empty($remote['expired_at']) ? Carbon::parse($remote['expired_at'])->endOfDay() : null;
            $status = in_array($remote['status'] ?? 'active', ['active', 'suspended', 'expired'], true)
                ? $remote['status']
                : 'active';

            if ($existing) {
                $existing->forceFill([
                    'status' => $status,
                    'expired_at' => $expiredAt ?: $existing->expired_at,
                    'quota_gb' => (int) ($remote['quota_gb'] ?? $existing->quota_gb),
                    'ip_limit' => max(1, (int) ($remote['ip_limit'] ?? $existing->ip_limit)),
                    'last_synced_at' => now(),
                ])->save();
                $synced++;
            } else {
                VpnAccount::create([
                    'user_id' => $adminId,
                    'vpn_server_id' => $server->id,
                    'server_ip' => parse_url((string) $server->base_url, PHP_URL_HOST) ?: $server->ssh_host,
                    'protocol' => $protocol,
                    'username' => $username,
                    'quota_gb' => (int) ($remote['quota_gb'] ?? 0),
                    'ip_limit' => max(1, (int) ($remote['ip_limit'] ?? 1)),
                    'expired_at' => $expiredAt,
                    'status' => $status,
                    'suspended_by_role' => ($status === 'suspended' && !empty($remote['admin_suspended'])) ? 'admin' : null,
                    'external_ref' => 'panel_sync',
                    'last_synced_at' => now(),
                ]);
                $imported++;
            }
        }

        DB::table('audit_logs')->insert([
            'action' => 'admin_vpn_server_sync',
            'actor_id' => $adminId,
            'entity_type' => 'vpn_server',
            'entity_id' => $server->id,
            'detail' => "server={$server->code}; synced={$synced}; imported={$imported}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.vpn-accounts.index', ['server_id' => $server->id])
            ->with('success', __('Sinkronisasi server :name selesai: :synced diperbarui, :imported akun baru diimpor.', [
                'name' => $server->name,
                'synced' => $synced,
                'imported' => $imported,
            ]));
    }

    public function destroy($id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);
        $username = $account->username;
        $protocol = $account->protocol;

        $res = $vpnService->deleteAccount($account, true);

        DB::table('audit_logs')->insert([
            'action' => 'admin_vpn_delete',
            'actor_id' => Auth::id(),
            'entity_type' => 'vpn_account',
            'entity_id' => (int) $id,
            'detail' => "protocol={$protocol}; username={$username}; success=" . (!empty($res['success']) ? '1' : '0'),
            'created_at' => now(),
        ]);

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil dihapus permanen.', ['user' => $username])
                : ($res['message'] ?? __('Gagal menghapus akun VPN.'))
        );
    }

    private function logAudit(string $action, VpnAccount $account, string $detail): void
    {
        DB::table('audit_logs')->insert([
            'action' => $action,
            'actor_id' => Auth::id(),
            'entity_type' => 'vpn_account',
            'entity_id' => $account->id,
            'detail' => AuditLog::maskSensitiveData($detail),
            'created_at' => now(),
        ]);
    }
}
