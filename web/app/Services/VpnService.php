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

        // Global cross-protocol uniqueness check in local DB first (prevents duplicate username across VMESS/VLESS/Trojan/SS/SSH)
        $localExists = VpnAccount::query()
            ->whereRaw('LOWER(username) = ?', [strtolower($username)])
            ->where('status', '!=', 'failed')
            ->when($resolved->exists, fn ($q) => $q->where('vpn_server_id', $resolved->id))
            ->exists();

        if ($localExists) {
            return [
                'success' => true,
                'available' => false,
                'exists' => true,
                'message' => __('Username sudah terpakai.'),
            ];
        }

        // Always query panel globally (protocol = null) so cross-protocol collisions on the VPS are caught too
        $res = $this->driverFor($resolved)->checkUsername($username, null);
        if (!empty($res['success']) && empty($res['available'])) {
            $res['message'] = __('Username sudah terpakai.');
        }

        return $res;
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
                'expiry_notified_stage' => null,
                'expiry_notified_at' => null,
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

    public function reduceAccount(
        VpnAccount $account,
        int $days,
        ?int $quotaGb = null,
        ?int $ipLimit = null
    ): array {
        $server = $this->resolveServer($account->vpnServer);
        if (!$server) {
            return ['success' => false, 'message' => __('Server VPN untuk akun ini tidak ditemukan.')];
        }

        $res = $this->driverFor($server)->reduceAccount(
            $account->protocol,
            $account->username,
            $days,
            $quotaGb,
            $ipLimit
        );

        if (!empty($res['success'])) {
            $data = is_array($res['data'] ?? null) ? $res['data'] : [];
            $detail = is_array($data['detail'] ?? null) ? $data['detail'] : null;
            $newExp = !empty($data['expired_at'])
                ? Carbon::parse($data['expired_at'])->endOfDay()
                : ($account->expired_at
                    ? $account->expired_at->copy()->subDays($days)
                    : now());

            $newStatus = $newExp->isPast() ? 'expired' : $account->status;

            $update = [
                'expired_at' => $newExp,
                'status' => $newStatus,
                'quota_gb' => $quotaGb ?? (int) ($data['quota_gb'] ?? $account->quota_gb),
                'ip_limit' => $ipLimit ?? max(1, (int) ($data['ip_limit'] ?? $account->ip_limit)),
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

            return [
                'success' => true,
                'message' => __('Masa aktif akun :user berhasil dikurangi :days hari.', ['user' => $account->username, 'days' => $days]),
                'data' => array_merge($data, ['expired_at' => $newExp->toDateTimeString()]),
            ];
        } else {
            $account->forceFill(['last_error' => Str::limit((string) ($res['message'] ?? 'Reduce duration failed'), 1000)])->save();
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

        $this->applyRemoteStatusUpdate($account, $data, $update);

        return ['success' => true, 'message' => __('Konfigurasi akun berhasil disinkronkan dari panel VPN.'), 'data' => $data];
    }

    /**
     * Format raw VPS suspend reason (e.g. "auto-limit:vless:ip=3/1:strike=2") into clear Indonesian copy.
     */
    public function formatSuspendReason(?string $rawReason, bool $isAuto = false): string
    {
        $raw = trim((string) ($rawReason ?? ''));
        $lower = strtolower($raw);

        if (preg_match('/^auto-limit:[^:]+:ip=(\d+)\/(\d+)(?::strike=(\d+))?/i', $raw, $m)) {
            $detected = (int) $m[1];
            $limit = max(1, (int) $m[2]);
            return __('Pelanggaran batas multi-login (:detected IP terdeteksi, batas maksimal :limit IP)', [
                'detected' => $detected,
                'limit' => $limit,
            ]);
        }

        if (str_starts_with($lower, 'auto-quota')) {
            return __('Kuota data akun VPN telah habis');
        }

        if ($isAuto || str_starts_with($lower, 'auto-')) {
            return $raw !== '' && !in_array($lower, ['auto', 'auto-limit'], true)
                ? __('Pelanggaran batas penggunaan otomatis (:raw)', ['raw' => Str::limit($raw, 120)])
                : __('Pelanggaran batas penggunaan otomatis oleh sistem');
        }

        if ($raw === '' || in_array($lower, ['manual', 'admin_suspend'], true)) {
            return __('Ditangguhkan oleh Admin');
        }

        if ($lower === 'customer_suspend') {
            return __('Ditangguhkan mandiri oleh pemilik');
        }

        return Str::limit($raw, 240);
    }

    /**
     * Atomically apply remote panel status & metadata updates to a local VpnAccount.
     * Detects active -> suspended (auto-suspend or remote admin suspend) and suspended -> active transitions,
     * updating lock metadata and sending anti-spam Telegram + Website notifications once per transition.
     *
     * @return array{status_changed: bool, action: string|null, actor_role: string|null}
     */
    public function applyRemoteStatusUpdate(VpnAccount $account, array $remote, array $extraUpdate = []): array
    {
        $remoteStatus = isset($remote['status']) && in_array($remote['status'], ['active', 'suspended', 'expired'], true)
            ? (string) $remote['status']
            : null;
        $rawReason = trim((string) ($remote['suspend_reason'] ?? ''));
        $isAuto = !empty($remote['auto_suspended']) || str_starts_with(strtolower($rawReason), 'auto-');
        $isRemoteAdminSusp = !empty($remote['admin_suspended']);

        $notifyAction = null;
        $notifyRole = 'admin';
        $notifyReason = null;
        $lockedAccount = null;

        \Illuminate\Support\Facades\DB::transaction(function () use (
            $account,
            $extraUpdate,
            $remoteStatus,
            $rawReason,
            $isAuto,
            $isRemoteAdminSusp,
            &$notifyAction,
            &$notifyRole,
            &$notifyReason,
            &$lockedAccount
        ) {
            $locked = VpnAccount::with(['user', 'vpnServer'])
                ->where('id', $account->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return;
            }

            $previousStatus = (string) $locked->status;
            $effectiveStatus = $remoteStatus ?: $previousStatus;

            $update = array_merge([
                'last_synced_at' => now(),
                'last_error' => null,
            ], $extraUpdate);

            if ($remoteStatus !== null) {
                $update['status'] = $remoteStatus;
            }

            if ($effectiveStatus === 'suspended') {
                if ($previousStatus !== 'suspended') {
                    // Fresh remote suspension detected (e.g. VPS limit-ip auto-suspend or panel admin suspend)
                    $actorRole = $isAuto ? 'system' : ($isRemoteAdminSusp || strtolower($rawReason) !== 'customer_suspend' ? 'admin' : 'customer');
                    $humanReason = $this->formatSuspendReason($rawReason, $isAuto);

                    $update['suspended_by_role'] = $actorRole;
                    if ($actorRole === 'system') {
                        $update['suspended_by_user_id'] = null;
                    }
                    $update['suspended_reason'] = $humanReason;
                    $update['suspended_at'] = $locked->suspended_at ?: now();

                    if (in_array($actorRole, ['system', 'admin'], true)) {
                        $notifyAction = 'suspended';
                        $notifyRole = $actorRole;
                        $notifyReason = $humanReason;
                    }
                } elseif ($isAuto && $locked->suspended_by_role !== 'system') {
                    // Backfill lock metadata if previously synced without system role (no duplicate notification)
                    $update['suspended_by_role'] = 'system';
                    $update['suspended_by_user_id'] = null;
                    if (empty($locked->suspended_reason)) {
                        $update['suspended_reason'] = $this->formatSuspendReason($rawReason, true);
                    }
                    if (empty($locked->suspended_at)) {
                        $update['suspended_at'] = now();
                    }
                } elseif ($isRemoteAdminSusp && empty($locked->suspended_by_role)) {
                    $update['suspended_by_role'] = 'admin';
                }
            } elseif ($previousStatus === 'suspended' && $effectiveStatus === 'active') {
                $wasLockedByAdminOrSystem = in_array($locked->suspended_by_role, ['admin', 'system'], true);
                $update['suspended_by_role'] = null;
                $update['suspended_by_user_id'] = null;
                $update['suspended_reason'] = null;
                $update['suspended_at'] = null;

                if ($wasLockedByAdminOrSystem) {
                    $notifyAction = 'unsuspended';
                    $notifyRole = 'admin';
                }
            }

            $locked->forceFill($update)->save();
            $lockedAccount = $locked;
        });

        $account->refresh();

        if ($lockedAccount && $notifyAction !== null) {
            try {
                if ($notifyAction === 'suspended') {
                    if ($lockedAccount->user) {
                        $lockedAccount->user->notify(new \App\Notifications\VpnStatusNotification($lockedAccount, 'suspended', $notifyReason, $notifyRole));
                    }
                    \App\Services\TelegramService::notifyVpnStatusChanged($lockedAccount, 'suspended', $notifyReason, $notifyRole);

                    if ($notifyRole === 'system') {
                        \App\Services\TelegramService::notifyAdminVpnAutoSuspended($lockedAccount, $notifyReason);
                    }

                    \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                        'action' => $notifyRole === 'system' ? 'vpn_auto_suspend_detected' : 'vpn_remote_suspend_detected',
                        'actor_id' => null,
                        'entity_type' => 'vpn_account',
                        'entity_id' => $lockedAccount->id,
                        'detail' => \App\Models\AuditLog::maskSensitiveData("username={$lockedAccount->username}; protocol={$lockedAccount->protocol}; role={$notifyRole}; reason={$notifyReason}"),
                        'created_at' => now(),
                    ]);
                } elseif ($notifyAction === 'unsuspended') {
                    if ($lockedAccount->user) {
                        $lockedAccount->user->notify(new \App\Notifications\VpnStatusNotification($lockedAccount, 'unsuspended'));
                    }
                    \App\Services\TelegramService::notifyVpnStatusChanged($lockedAccount, 'unsuspended');
                }
            } catch (\Throwable $e) {
                Log::warning('VPN remote status transition notification failed: ' . $e->getMessage(), [
                    'vpn_account_id' => $lockedAccount->id,
                    'action' => $notifyAction,
                    'actor_role' => $notifyRole,
                ]);
            }
        }

        return [
            'status_changed' => $notifyAction !== null,
            'action' => $notifyAction,
            'actor_role' => $notifyAction !== null ? $notifyRole : null,
        ];
    }

    /**
     * Bulk-sync account statuses (active / suspended / expired) for all local VPN accounts on a server.
     * Single lightweight API call (include_config = false) — ideal for 1-minute cron & page auto-sync.
     */
    public function syncServerStatuses(VpnServer $server): array
    {
        if (!$server->isApiDriver()) {
            return [
                'success' => false,
                'message' => __('Sinkronisasi status otomatis memerlukan driver REST API vpnxray-main.'),
            ];
        }

        $res = $this->driverFor($server)->listAccounts(null, 'all', null, false);
        if (empty($res['success'])) {
            return $res;
        }

        $remoteAccounts = is_array($res['accounts'] ?? null) ? $res['accounts'] : [];
        if (empty($remoteAccounts)) {
            return [
                'success' => true,
                'checked' => 0,
                'suspended_notified' => 0,
                'unsuspended_notified' => 0,
            ];
        }

        $localAccounts = VpnAccount::where('vpn_server_id', $server->id)
            ->whereNotIn('status', ['failed', 'provisioning'])
            ->get()
            ->keyBy(fn (VpnAccount $a) => strtolower($a->protocol . ':' . $a->username));

        $checked = 0;
        $suspendedNotified = 0;
        $unsuspendedNotified = 0;

        foreach ($remoteAccounts as $remote) {
            $proto = strtolower((string) ($remote['protocol'] ?? ''));
            $uname = (string) ($remote['username'] ?? '');
            if ($proto === '' || $uname === '') {
                continue;
            }

            $key = strtolower($proto . ':' . $uname);
            $local = $localAccounts->get($key);
            if (!$local) {
                continue;
            }

            $extra = [];
            if (!empty($remote['expired_at'])) {
                try {
                    $extra['expired_at'] = Carbon::parse($remote['expired_at'])->endOfDay();
                } catch (\Throwable $e) {
                    // Ignore malformed date
                }
            }
            if (isset($remote['quota_gb'])) {
                $extra['quota_gb'] = (int) $remote['quota_gb'];
            }
            if (isset($remote['ip_limit'])) {
                $extra['ip_limit'] = max(1, (int) $remote['ip_limit']);
            }

            $outcome = $this->applyRemoteStatusUpdate($local, $remote, $extra);
            $checked++;

            if ($outcome['action'] === 'suspended') {
                $suspendedNotified++;
            } elseif ($outcome['action'] === 'unsuspended') {
                $unsuspendedNotified++;
            }
        }

        return [
            'success' => true,
            'checked' => $checked,
            'suspended_notified' => $suspendedNotified,
            'unsuspended_notified' => $unsuspendedNotified,
        ];
    }

    public function listRemoteAccounts(VpnServer $server, ?string $protocol = null, ?string $status = null, ?string $search = null, bool $includeConfig = false): array
    {
        return $this->driverFor($server)->listAccounts($protocol, $status, $search, $includeConfig);
    }

    /**
     * Fetch active/recent device log & IP limit violation history from panel VPS (Admin monitoring).
     */
    public function getAccountDevices(VpnAccount $account, int $windowMinutes = 60): array
    {
        $server = $this->resolveServer($account->vpnServer);
        if (!$server) {
            return ['success' => false, 'message' => __('Server VPN untuk akun ini tidak ditemukan.')];
        }

        return $this->driverFor($server)->getAccountDevices($account->protocol, $account->username, $windowMinutes);
    }

    /**
     * Sync live traffic (Upload/Download) for all accounts on a server using anti-reset high-water-mark accumulation.
     * Persists both lifetime totals on `vpn_accounts` and daily buckets on `vpn_traffic_logs`.
     */
    public function syncServerTraffic(VpnServer $server): array
    {
        $res = $this->driverFor($server)->getTrafficStats();
        if (empty($res['success'])) {
            return $res;
        }

        $accountsMap = is_array($res['data']['accounts'] ?? null) ? $res['data']['accounts'] : [];
        if (empty($accountsMap)) {
            return ['success' => true, 'updated' => 0, 'message' => __('Tidak ada data traffic baru dari panel.')];
        }

        // Case-insensitive lookup map by username
        $normalized = [];
        foreach ($accountsMap as $uname => $stats) {
            if (!is_array($stats)) {
                continue;
            }
            $normalized[strtolower((string) $uname)] = [
                'uplink' => max(0, (int) ($stats['uplink_bytes'] ?? 0)),
                'downlink' => max(0, (int) ($stats['downlink_bytes'] ?? 0)),
            ];
        }

        $localAccounts = VpnAccount::where('vpn_server_id', $server->id)->get();
        $today = now()->toDateString();
        $updated = 0;

        foreach ($localAccounts as $acc) {
            $key = strtolower((string) $acc->username);
            if (!isset($normalized[$key])) {
                continue;
            }

            $currUp = $normalized[$key]['uplink'];
            $currDown = $normalized[$key]['downlink'];

            $this->applyTrafficSnapshot($acc, $currUp, $currDown, $today);
            $updated++;
        }

        return [
            'success' => true,
            'updated' => $updated,
            'message' => __('Traffic :count akun berhasil disinkronkan.', ['count' => $updated]),
        ];
    }

    /**
     * Apply a panel traffic counter snapshot safely using high-water-mark delta so restarts/reboots NEVER reset totals.
     */
    public function applyTrafficSnapshot(VpnAccount $account, int $currentPanelUp, int $currentPanelDown, ?string $date = null): void
    {
        $currentPanelUp = max(0, $currentPanelUp);
        $currentPanelDown = max(0, $currentPanelDown);
        $lastUp = max(0, (int) ($account->last_panel_uplink_bytes ?? 0));
        $lastDown = max(0, (int) ($account->last_panel_downlink_bytes ?? 0));

        // If panel counter dropped (Xray restart or VPS reboot), treat full current reading as new delta
        $deltaUp = $currentPanelUp >= $lastUp ? ($currentPanelUp - $lastUp) : $currentPanelUp;
        $deltaDown = $currentPanelDown >= $lastDown ? ($currentPanelDown - $lastDown) : $currentPanelDown;

        $account->forceFill([
            'uplink_bytes' => max(0, (int) ($account->uplink_bytes ?? 0)) + $deltaUp,
            'downlink_bytes' => max(0, (int) ($account->downlink_bytes ?? 0)) + $deltaDown,
            'last_panel_uplink_bytes' => $currentPanelUp,
            'last_panel_downlink_bytes' => $currentPanelDown,
            'traffic_synced_at' => now(),
        ])->save();

        if ($deltaUp > 0 || $deltaDown > 0) {
            $logDate = $date ?: now()->toDateString();
            $existing = \App\Models\VpnTrafficLog::where('vpn_account_id', $account->id)
                ->where('date', $logDate)
                ->first();
            if ($existing) {
                $existing->increment('uplink_bytes', $deltaUp);
                $existing->increment('downlink_bytes', $deltaDown);
            } else {
                \App\Models\VpnTrafficLog::create([
                    'vpn_account_id' => $account->id,
                    'date' => $logDate,
                    'uplink_bytes' => $deltaUp,
                    'downlink_bytes' => $deltaDown,
                ]);
            }
        }
    }

    /**
     * Resolve traffic totals (per-account map & aggregate sum) for a set of account IDs across a selected time range.
     *
     * @param  array<int, int>  $accountIds
     * @return array{per_account: array<int, array{uplink: int, downlink: int, total: int}>, total_uplink: int, total_downlink: int, total_bytes: int}
     */
    public function resolveTrafficForRange(string $range, ?int $userId = null, array $pageAccountIds = []): array
    {
        $range = in_array($range, ['all', 'today', '7d', '30d', 'month'], true) ? $range : 'all';

        if ($range === 'all') {
            $aggQuery = VpnAccount::query();
            if ($userId !== null) {
                $aggQuery->where('user_id', $userId);
            }
            $agg = $aggQuery->selectRaw('COALESCE(SUM(uplink_bytes), 0) as up_sum, COALESCE(SUM(downlink_bytes), 0) as down_sum')->first();
            $totalUp = (int) ($agg->up_sum ?? 0);
            $totalDown = (int) ($agg->down_sum ?? 0);

            $perAccount = [];
            if (!empty($pageAccountIds)) {
                $rows = VpnAccount::whereIn('id', $pageAccountIds)->get(['id', 'uplink_bytes', 'downlink_bytes']);
                foreach ($rows as $r) {
                    $u = max(0, (int) $r->uplink_bytes);
                    $d = max(0, (int) $r->downlink_bytes);
                    $perAccount[$r->id] = ['uplink' => $u, 'downlink' => $d, 'total' => $u + $d];
                }
            }

            return [
                'per_account' => $perAccount,
                'total_uplink' => $totalUp,
                'total_downlink' => $totalDown,
                'total_bytes' => $totalUp + $totalDown,
            ];
        }

        $startDate = match ($range) {
            'today' => now()->toDateString(),
            '7d' => now()->subDays(6)->toDateString(),
            '30d' => now()->subDays(29)->toDateString(),
            'month' => now()->startOfMonth()->toDateString(),
            default => now()->toDateString(),
        };

        $aggQuery = \App\Models\VpnTrafficLog::query()
            ->where('vpn_traffic_logs.date', '>=', $startDate);
        if ($userId !== null) {
            $aggQuery->join('vpn_accounts', 'vpn_accounts.id', '=', 'vpn_traffic_logs.vpn_account_id')
                ->where('vpn_accounts.user_id', $userId);
        }
        $agg = $aggQuery->selectRaw('COALESCE(SUM(vpn_traffic_logs.uplink_bytes), 0) as up_sum, COALESCE(SUM(vpn_traffic_logs.downlink_bytes), 0) as down_sum')->first();
        $totalUp = (int) ($agg->up_sum ?? 0);
        $totalDown = (int) ($agg->downlink_bytes_sum ?? $agg->down_sum ?? 0);

        $perAccount = [];
        if (!empty($pageAccountIds)) {
            $rows = \App\Models\VpnTrafficLog::whereIn('vpn_account_id', $pageAccountIds)
                ->where('date', '>=', $startDate)
                ->groupBy('vpn_account_id')
                ->selectRaw('vpn_account_id, COALESCE(SUM(uplink_bytes), 0) as up_sum, COALESCE(SUM(downlink_bytes), 0) as down_sum')
                ->get();
            foreach ($rows as $r) {
                $u = max(0, (int) $r->up_sum);
                $d = max(0, (int) $r->down_sum);
                $perAccount[(int) $r->vpn_account_id] = ['uplink' => $u, 'downlink' => $d, 'total' => $u + $d];
            }
        }

        return [
            'per_account' => $perAccount,
            'total_uplink' => $totalUp,
            'total_downlink' => $totalDown,
            'total_bytes' => $totalUp + $totalDown,
        ];
    }
}
