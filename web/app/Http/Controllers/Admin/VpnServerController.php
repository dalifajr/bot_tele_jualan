<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\VpnServer;
use App\Services\VpnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VpnServerController extends Controller
{
    public function index()
    {
        $servers = VpnServer::withCount(['vpnAccounts', 'products'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => $servers->count(),
            'active' => $servers->where('is_active', true)->count(),
            'api_driver' => $servers->where('driver', VpnServer::DRIVER_VPNXRAY_API)->count(),
            'online' => $servers->where('last_status', 'online')->count(),
        ];

        return view('admin.vpn.servers', compact('servers', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:40|regex:/^[a-zA-Z0-9_-]+$/|unique:vpn_servers,code',
            'region' => 'nullable|string|max:60',
            'driver' => 'required|in:vpnxray_api,legacy_ssh',
            'base_url' => 'required_if:driver,vpnxray_api|nullable|url|max:255',
            'api_token' => 'required_if:driver,vpnxray_api|nullable|string|max:255',
            'hmac_secret' => 'required_if:driver,vpnxray_api|nullable|string|max:255',
            'timeout_seconds' => 'nullable|integer|min:3|max:60',
            'verify_ssl' => 'nullable|boolean',
            'ssh_host' => 'required_if:driver,legacy_ssh|nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_username' => 'nullable|string|max:64',
            'ssh_private_key' => 'required_if:driver,legacy_ssh|nullable|string',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default') || VpnServer::count() === 0;

        DB::transaction(function () use ($validated, $request, $isDefault, &$server) {
            if ($isDefault) {
                VpnServer::query()->update(['is_default' => false]);
            }

            $server = VpnServer::create([
                'name' => trim($validated['name']),
                'code' => Str::lower(trim($validated['code'])),
                'region' => $validated['region'] ?? null,
                'driver' => $validated['driver'],
                'base_url' => $validated['base_url'] ? rtrim(trim($validated['base_url']), '/') : null,
                'api_token' => $validated['api_token'] ?? null,
                'hmac_secret' => $validated['hmac_secret'] ?? null,
                'timeout_seconds' => (int) ($validated['timeout_seconds'] ?? 15),
                'verify_ssl' => $request->has('verify_ssl') ? $request->boolean('verify_ssl') : true,
                'ssh_host' => $validated['ssh_host'] ?? null,
                'ssh_port' => (int) ($validated['ssh_port'] ?? 22),
                'ssh_username' => $validated['ssh_username'] ?: 'root',
                'ssh_private_key' => $validated['ssh_private_key'] ?? null,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
                'is_default' => $isDefault,
            ]);
        });

        DB::table('audit_logs')->insert([
            'action' => 'vpn_server_created',
            'actor_id' => Auth::id(),
            'entity_type' => 'vpn_server',
            'entity_id' => $server->id,
            'detail' => AuditLog::maskSensitiveData("code={$server->code}; driver={$server->driver}; endpoint={$server->display_endpoint}"),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.vpn-servers.index')
            ->with('success', __('Server VPN :name berhasil ditambahkan.', ['name' => $server->name]));
    }

    public function update(Request $request, $id)
    {
        $server = VpnServer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:40|regex:/^[a-zA-Z0-9_-]+$/|unique:vpn_servers,code,' . $server->id,
            'region' => 'nullable|string|max:60',
            'driver' => 'required|in:vpnxray_api,legacy_ssh',
            'base_url' => 'required_if:driver,vpnxray_api|nullable|url|max:255',
            'api_token' => 'nullable|string|max:255',
            'hmac_secret' => 'nullable|string|max:255',
            'timeout_seconds' => 'nullable|integer|min:3|max:60',
            'verify_ssl' => 'nullable|boolean',
            'ssh_host' => 'required_if:driver,legacy_ssh|nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_username' => 'nullable|string|max:64',
            'ssh_private_key' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($server, $validated, $request, $isDefault) {
            if ($isDefault) {
                VpnServer::where('id', '!=', $server->id)->update(['is_default' => false]);
            }

            $data = [
                'name' => trim($validated['name']),
                'code' => Str::lower(trim($validated['code'])),
                'region' => $validated['region'] ?? null,
                'driver' => $validated['driver'],
                'base_url' => $validated['base_url'] ? rtrim(trim($validated['base_url']), '/') : null,
                'timeout_seconds' => (int) ($validated['timeout_seconds'] ?? 15),
                'verify_ssl' => $request->boolean('verify_ssl'),
                'ssh_host' => $validated['ssh_host'] ?? null,
                'ssh_port' => (int) ($validated['ssh_port'] ?? 22),
                'ssh_username' => $validated['ssh_username'] ?: 'root',
                'is_active' => $request->boolean('is_active'),
                'is_default' => $isDefault,
            ];

            if (!empty($validated['api_token'])) {
                $data['api_token'] = trim($validated['api_token']);
            }
            if (!empty($validated['hmac_secret'])) {
                $data['hmac_secret'] = trim($validated['hmac_secret']);
            }
            if (!empty($validated['ssh_private_key'])) {
                $data['ssh_private_key'] = $validated['ssh_private_key'];
            }

            $server->update($data);
        });

        DB::table('audit_logs')->insert([
            'action' => 'vpn_server_updated',
            'actor_id' => Auth::id(),
            'entity_type' => 'vpn_server',
            'entity_id' => $server->id,
            'detail' => AuditLog::maskSensitiveData("code={$server->code}; driver={$server->driver}; active=" . ($server->is_active ? '1' : '0')),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.vpn-servers.index')
            ->with('success', __('Server VPN :name berhasil diperbarui.', ['name' => $server->name]));
    }

    public function setDefault($id)
    {
        $server = VpnServer::findOrFail($id);

        DB::transaction(function () use ($server) {
            VpnServer::query()->update(['is_default' => false]);
            $server->update(['is_default' => true, 'is_active' => true]);
        });

        return redirect()->route('admin.vpn-servers.index')
            ->with('success', __('Server :name ditetapkan sebagai server VPN utama.', ['name' => $server->name]));
    }

    public function ping(Request $request, $id, VpnService $vpnService)
    {
        $server = VpnServer::findOrFail($id);
        $result = $vpnService->healthCheck($server);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => !empty($result['ok']),
                'latency_ms' => $result['latency_ms'] ?? null,
                'message' => $result['message'] ?? '',
                'data' => $result['data'] ?? [],
            ], !empty($result['ok']) ? 200 : 502);
        }

        return redirect()->route('admin.vpn-servers.index')
            ->with(!empty($result['ok']) ? 'success' : 'error', $result['message'] ?? __('Pengujian koneksi selesai.'));
    }

    public function destroy($id)
    {
        $server = VpnServer::findOrFail($id);
        $name = $server->name;
        $code = $server->code;

        DB::transaction(function () use ($server) {
            $server->products()->update(['vpn_server_id' => null]);
            $server->vpnAccounts()->update(['vpn_server_id' => null]);
            $server->delete();
        });

        DB::table('audit_logs')->insert([
            'action' => 'vpn_server_deleted',
            'actor_id' => Auth::id(),
            'entity_type' => 'vpn_server',
            'entity_id' => (int) $id,
            'detail' => "code={$code}; name={$name}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.vpn-servers.index')
            ->with('success', __('Server VPN :name berhasil dihapus.', ['name' => $name]));
    }
}
