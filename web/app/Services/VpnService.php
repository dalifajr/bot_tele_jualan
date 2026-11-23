<?php

namespace App\Services;

use App\Models\BotSetting;
use App\Models\Product;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\Vpn\LegacySshVpnDriver;
use App\Services\Vpn\VpnProviderInterface;
use App\Services\Vpn\VpnXrayApiDriver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VpnService
{
    public function resolveServer(?VpnServer $server = null, ?Product $product = null): ?VpnServer
    {
        if ($server && $server->isConfigured()) {
            return $server;
        }

        if ($product && $product->vpn_server_id) {
            $prodServer = $product->relationLoaded('vpnServer')
                ? $product->vpnServer
                : VpnServer::find($product->vpn_server_id);
            if ($prodServer && $prodServer->is_active && $prodServer->isConfigured()) {
                return $prodServer;
            }
        }

        if (Schema::hasTable('vpn_servers')) {
            $defaultServer = VpnServer::where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get()
                ->first(fn (VpnServer $s) => $s->isConfigured());

            if ($defaultServer) {
                return $defaultServer;
            }
        }

        return $this->buildLegacyBotSettingServer();
    }

    public function buildLegacyBotSettingServer(): ?VpnServer
    {
        if (!Schema::hasTable('bot_settings')) {
            return null;
        }

        $settings = BotSetting::whereIn('key', [
            'vpn_server_ip',
            'vpn_server_port',
            'vpn_server_username',
            'vpn_server_ssh_key_raw',
        ])->pluck('value', 'key');

        $ip = trim((string) ($settings['vpn_server_ip'] ?? ''));
        $rawKey = (string) ($settings['vpn_server_ssh_key_raw'] ?? '');

        if ($ip === '' || trim($rawKey) === '') {
            return null;
        }

        $virtual = new VpnServer();
        $virtual->name = 'Legacy SSH (Bot Settings)';
        $virtual->code = 'legacy-default';
        $virtual->driver = VpnServer::DRIVER_LEGACY_SSH;
        $virtual->ssh_host = $ip;
        $virtual->ssh_port = (int) ($settings['vpn_server_port'] ?? 22);
        $virtual->ssh_username = (string) ($settings['vpn_server_username'] ?? 'root');
        $virtual->ssh_private_key = $rawKey;
        $virtual->timeout_seconds = 15;
        $virtual->is_active = true;
        $virtual->is_default = true;

        return $virtual;
    }

    public function driverFor(VpnServer $server): VpnProviderInterface
    {
        return $server->isApiDriver()
            ? new VpnXrayApiDriver($server)
            : new LegacySshVpnDriver($server);
    }

    public function isConfigured(?VpnServer $server = null, ?Product $product = null): bool
    {
        return $this->resolveServer($server, $product) !== null;
    }

    /**
     * Backward-compatible credential validator.
     */
    public function validateCredentials(string $protocol, string $username, ?string $password = null): void
    {
        LegacySshVpnDriver::validateCredentials($protocol, $username, $password);
    }

    /**
     * Backward-compatible shell command builder (tested by existing unit/feature tests).
     */
    public function buildProvisionCommand(string $protocol, string $username, ?string $password, int $durationDays, int $quota = 0, int $ipLimit = 0): string
    {
        return LegacySshVpnDriver::buildProvisionCommand($protocol, $username, $password, $durationDays, $quota, $ipLimit);
    }

    /**
     * Health check a specific server (or resolved default server) and persist telemetry if stored in DB.
     */
    public function healthCheck(?VpnServer $server = null): array
    {
        $resolved = $server ?? $this->resolveServer();
        if (!$resolved) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => __('Belum ada Server VPN aktif yang terkonfigurasi.'),
            ];
        }

        $result = $this->driverFor($resolved)->healthCheck();

        if ($resolved->exists) {
            $resolved->forceFill([
                'last_ping_at' => now(),
                'last_ping_ms' => $result['latency_ms'] ?? null,
                'last_status' => !empty($result['ok']) ? 'online' : 'offline',
                'last_error' => !empty($result['ok']) ? null : ($result['message'] ?? 'Ping failed'),
            ])->save();
        }

        return $result;
    }

    /**
     * Run deep multi-stage diagnostic on a VPN server and persist latest status/error.
     */
    public function diagnoseServer(VpnServer $server): array
    {
        if ($server->isApiDriver()) {
            $report = (new VpnXrayApiDriver($server))->diagnose();
        } else {
            $health = $this->driverFor($server)->healthCheck();
            $report = [
                'ok' => !empty($health['ok']),
                'server_name' => $server->name,
                'server_code' => $server->code,
                'driver' => $server->driver,
                'endpoint' => $server->display_endpoint,
                'latency_ms' => $health['latency_ms'] ?? null,
                'headline' => $health['message'] ?? '',
                'checked_at' => now()->format('Y-m-d H:i:s'),
                'steps' => [
                    [
                        'key' => 'legacy_ssh',
                        'title' => __('1. Koneksi Legacy SSH'),
                        'status' => !empty($health['ok']) ? 'ok' : 'fail',
                        'latency_ms' => $health['latency_ms'] ?? null,
                        'detail' => $health['message'] ?? __('Gagal menguji SSH.'),
                        'hint' => !empty($health['ok']) ? null : __('Periksa IP, Port SSH, Username, dan Private Key VPS.'),
                    ],
                ],
                'recommendations' => !empty($health['ok']) ? [] : [__('Pertimbangkan beralih ke driver REST API vpnxray-main agar lebih aman dan mendukung CRUD penuh.')],
            ];
        }

        if ($server->exists) {
            $server->forceFill([
                'last_ping_at' => now(),
                'last_ping_ms' => $report['latency_ms'] ?? null,
                'last_status' => !empty($report['ok']) ? 'online' : 'offline',
                'last_error' => !empty($report['ok']) ? null : ($report['headline'] ?? 'Diagnostic failed'),
            ])->save();
        }

        return $report;
    }

    public function checkUsername(string $username, ?string $protocol = null, ?VpnServer $server = null, ?Product $product = null): array
    {
        $resolved = $this->resolveServer($server, $product);
        if (!$resolved) {
            return [
                'success' => false,
                'available' => false,
                'exists' => false,
                'message' => __('Server VPN belum dikonfigurasi.'),
            ];
        }

        return $this->driverFor($resolved)->checkUsername($username, $protocol);
    }

    /**
     * Backward-compatible create method used by CLI commands and legacy callers.
     */
    public function createVpnAccount(
        $protocol,
        $username,
        $password,
        $durationDays,
        $quota = 0,
        $ipLimit = 1,
        ?VpnServer $server = null,
        ?string $idempotencyKey = null,
        string $sniMode = '3',
        ?string $externalRef = null
    ): array {
        $resolved = $this->resolveServer($server);
        if (!$resolved) {
            return [
                'success' => false,
                'output' => 'Server VPN belum dikonfigurasi dengan benar di admin settings.',
                'message' => 'Server VPN belum dikonfigurasi dengan benar di admin settings.',
            ];
        }

        $payload = [
            'protocol' => strtolower((string) $protocol),
            'username' => (string) $username,
            'password' => $password !== null ? (string) $password : null,
            'days' => max(1, (int) $durationDays),
            'quota_gb' => max(0, (int) $quota),
            'ip_limit' => max(1, (int) $ipLimit),
            'sni_mode' => in_array($sniMode, ['1', '2', '3'], true) ? $sniMode : '3',
            'external_ref' => $externalRef ?: 'store',
        ];

        $res = $this->driverFor($resolved)->createAccount($payload, $idempotencyKey);
        $res['server'] = $resolved;

        return $res;
    }

    /**
     * Execute provisioning for a persisted VpnAccount (safe for post-DB::commit execution & admin retries).
     */
    public function provisionAccount(
        VpnAccount $account,
        int $days,
        ?string $idempotencyKey = null,
        string $sniMode = '3'
    ): VpnAccount {
        $server = $this->resolveServer($account->vpnServer);
        if (!$server) {
            $account->forceFill([
                'status' => 'failed',
                'last_error' => __('Tidak ada server VPN aktif yang dikonfigurasi.'),
                'config_link' => __('Server VPN belum dikonfigurasi.'),
            ])->save();

            return $account->fresh();
        }

        $key = $idempotencyKey ?: ("vpn-acct-{$account->id}-create");
        $res = $this->createVpnAccount(
            $account->protocol,
            $account->username,
            $account->password,
            $days,
            $account->quota_gb ?? 0,
            max(1, (int) ($account->ip_limit ?? 1)),
            $server,
            $key,
            $sniMode,
            $account->external_ref ?: ('acct_' . $account->id)
        );

        if (!empty($res['success'])) {
            $data = is_array($res['data'] ?? null) ? $res['data'] : [];
            $resolvedSni = in_array($sniMode, ['1', '2', '3'], true) ? $sniMode : '3';
            if ($account->protocol !== 'ssh' && empty($data['sni_mode'])) {
                $data['sni_mode'] = $resolvedSni;
            }
            $expiredAt = !empty($data['expired_at'])
                ? Carbon::parse($data['expired_at'])->endOfDay()
                : now()->addDays(max(1, $days));

            $account->forceFill([
                'vpn_server_id' => $server->exists ? $server->id : $account->vpn_server_id,
                'server_ip' => $data['domain'] ?? $server->ssh_host ?? parse_url((string) $server->base_url, PHP_URL_HOST) ?? $account->server_ip,
                'uuid' => $data['uuid'] ?? $account->uuid,
                'quota_gb' => (int) ($data['quota_gb'] ?? $account->quota_gb ?? 0),
                'ip_limit' => max(1, (int) ($data['ip_limit'] ?? $account->ip_limit ?? 1)),
                'config_payload' => !empty($data) ? $data : $account->config_payload,
                'config_link' => (string) ($data['formatted_text'] ?? $res['output'] ?? $account->config_link),
                'expired_at' => $expiredAt,
                'status' => 'active',
                'suspended_by_role' => null,
                'suspended_by_user_id' => null,
                'suspended_reason' => null,
                'suspended_at' => null,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } else {
            $err = (string) ($res['message'] ?? $res['output'] ?? 'Provisioning failed');
            $account->forceFill([
                'vpn_server_id' => $server->exists ? $server->id : $account->vpn_server_id,
                'status' => 'failed',
                'last_error' => Str::limit($err, 1000),
                'config_link' => $account->config_link ?: ('Failed: ' . $err),
            ])->save();
        }

        return $account->fresh();
    }

    public function renewAccount(
        VpnAccount $account,
        int $days,
        ?int $quotaGb = null,
        ?int $ipLimit = null,
        ?string $idempotencyKey = null
    ): array {
        $server = $this->resolveServer($account->vpnServer);
        if (!$server) {
            return ['success' => false, 'message' => __('Server VPN untuk akun ini tidak ditemukan.')];
        }

        $res = $this->driverFor($server)->renewAccount(
            $account->protocol,
            $account->username,
            $days,
            $quotaGb,
            $ipLimit,
            $idempotencyKey ?: ('vpn-renew-' . $account->id . '-' . Str::uuid())
        );

        if (!empty($res['success'])) {
            $data = is_array($res['data'] ?? null) ? $res['data'] : [];
            $detail = is_array($data['detail'] ?? null) ? $data['detail'] : null;
            $newExp = !empty($data['expired_at'])
                ? Carbon::parse($data['expired_at'])->endOfDay()
                : ($account->expired_at && $account->expired_at->isFuture()
                    ? $account->expired_at->copy()->addDays($days)
                    : now()->addDays($days));

            $update = [
                'expired_at' => $newExp,
                'status' => 'active',
                'quota_gb' => $quotaGb ?? (int) ($data['quota_gb'] ?? $account->quota_gb),
                'ip_limit' => $ipLimit ?? max(1, (int) ($data['ip_limit'] ?? $account->ip_limit)),
                'suspended_by_role' => null,
                'suspended_by_user_id' => null,
                'suspended_reason' => null,
                'suspended_at' => null,
                'last_synced_at' => now(),
                'last_error' => null,
            ];

            if ($detail) {
                $update['config_payload'] = $detail;
                if (!empty($detail['formatted_text'])) {
                    $update['config_link'] = $detail['formatted_text'];
                }
                if (!empty($detail['uuid'])) {
                    $update['uuid'] = $detail['uuid'];
                }
            }

            $account->forceFill($update)->save();
        } else {
            $account->forceFill(['last_error' => Str::limit((string) ($res['message'] ?? 'Renew failed'), 1000)])->save();
        }

        return $res;
    }

    public function suspendAccount(VpnAccount $account, string $actorRole, ?int $actorUserId = null, ?string $reason = null): array
    {
        $server = $this->resolveServer($account->vpnServer);
        if (!$server) {
            return ['success' => false, 'message' => __('Server VPN untuk akun ini tidak ditemukan.')];
        }

        $isAdmin = $actorRole === 'admin';
        $res = $this->driverFor($server)->suspendAccount($account->protocol, $account->username, $reason, $isAdmin);

        if (!empty($res['success'])) {
            $account->forceFill([
                'status' => 'suspended',
                'suspended_by_role' => $isAdmin ? 'admin' : 'customer',
                'suspended_by_user_id' => $actorUserId,
                'suspended_reason' => $reason ?: ($isAdmin ? __('Ditangguhkan oleh Admin') : __('Ditangguhkan mandiri oleh pemilik')),
                'suspended_at' => now(),
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } else {
            $account->forceFill(['last_error' => Str::limit((string) ($res['message'] ?? 'Suspend failed'), 1000)])->save();
        }

        return $res;
    }

    public function unsuspendAccount(VpnAccount $account): array
    {
        $server = $this->resolveServer($account->vpnServer);
        if (!$server) {
            return ['success' => false, 'message' => __('Server VPN untuk akun ini tidak ditemukan.')];
        }

        $res = $this->driverFor($server)->unsuspendAccount($account->protocol, $account->username);

        if (!empty($res['success'])) {
            $account->forceFill([
                'status' => 'active',
                'suspended_by_role' => null,
                'suspended_by_user_id' => null,
                'suspended_reason' => null,
                'suspended_at' => null,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } else {
            $account->forceFill(['last_error' => Str::limit((string) ($res['message'] ?? 'Unsuspend failed'), 1000)])->save();
        }

        return $res;
    }

    public function deleteAccount(VpnAccount $account, bool $deleteLocalOnSuccess = true): array
    {
        $server = $this->resolveServer($account->vpnServer);
        if (!$server) {
            if ($deleteLocalOnSuccess) {
                $account->delete();
                return ['success' => true, 'message' => __('Akun lokal berhasil dihapus (server tidak terhubung).')];
            }
            return ['success' => false, 'message' => __('Server VPN untuk akun ini tidak ditemukan.')];
        }

        $res = $this->driverFor($server)->deleteAccount($account->protocol, $account->username);

        if (!empty($res['success']) || ($res['status'] ?? null) === 404) {
            if ($deleteLocalOnSuccess) {
                $account->delete();
            }
            return ['success' => true, 'message' => __('Akun VPN berhasil dihapus permanen.')];
        }

        $account->forceFill(['last_error' => Str::limit((string) ($res['message'] ?? 'Delete failed'), 1000)])->save();
        return $res;
    }

    public function syncAccountFromPanel(VpnAccount $account): array
    {
        $server = $this->resolveServer($account->vpnServer);
        if (!$server) {
            return ['success' => false, 'message' => __('Server VPN tidak ditemukan.')];
        }

        $res = $this->driverFor($server)->getAccountDetail($account->protocol, $account->username);
        if (empty($res['success'])) {
            return $res;
        }

        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $update = [
            'config_payload' => $data,
            'last_synced_at' => now(),
            'last_error' => null,
        ];
        if (!empty($data['domain'])) {
            $update['server_ip'] = $data['domain'];
        }
        if (!empty($data['uuid'])) {
            $update['uuid'] = $data['uuid'];
        }
        if (!empty($data['formatted_text'])) {
            $update['config_link'] = $data['formatted_text'];
        }
        if (isset($data['quota_gb'])) {
            $update['quota_gb'] = (int) $data['quota_gb'];
        }
        if (isset($data['ip_limit'])) {
            $update['ip_limit'] = max(1, (int) $data['ip_limit']);
        }
        if (!empty($data['expired_at'])) {
            $update['expired_at'] = Carbon::parse($data['expired_at'])->endOfDay();
        }
        if (!empty($data['status']) && in_array($data['status'], ['active', 'suspended', 'expired'], true)) {
            $update['status'] = $data['status'];
        }

        $account->forceFill($update)->save();

        return ['success' => true, 'message' => __('Konfigurasi akun berhasil disinkronkan dari panel VPN.'), 'data' => $data];
    }

    public function listRemoteAccounts(VpnServer $server, ?string $protocol = null, ?string $status = null, ?string $search = null, bool $includeConfig = false): array
    {
        return $this->driverFor($server)->listAccounts($protocol, $status, $search, $includeConfig);
    }
}
