<?php

namespace App\Services\Vpn;

use App\Models\VpnServer;
use Illuminate\Support\Facades\Log;
use Spatie\Ssh\Ssh;

class LegacySshVpnDriver implements VpnProviderInterface
{
    public function __construct(
        protected VpnServer $server
    ) {}

    public static function validateCredentials(string $protocol, string $username, ?string $password = null): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $username)) {
            throw new \InvalidArgumentException('Format username VPN tidak valid (hanya huruf, angka, _ atau -, 3-32 karakter).');
        }

        if ($protocol === 'ssh') {
            if ($password === null || !preg_match('/^[a-zA-Z0-9@#$%^&*_+=!-]{4,64}$/', $password)) {
                throw new \InvalidArgumentException('Format password VPN SSH tidak valid (4-64 karakter ASCII aman tanpa spasi atau metakarakter shell).');
            }
        }
    }

    public static function buildProvisionCommand(string $protocol, string $username, ?string $password, int $durationDays, int $quota = 0, int $ipLimit = 0): string
    {
        self::validateCredentials($protocol, $username, $password);

        $durationDays = max(1, $durationDays);
        $quota = max(0, $quota);
        $ipLimit = max(0, $ipLimit);
        $sni = 'bug.com';

        if ($protocol === 'ssh') {
            $expiryDate = escapeshellarg(date('Y-m-d', strtotime("+{$durationDays} days")));
            $usernameEsc = escapeshellarg($username);
            $credentialPairEsc = escapeshellarg($username . ':' . $password);

            $command = "useradd -e {$expiryDate} -s /bin/false -M {$usernameEsc} && printf '%s\\n' {$credentialPairEsc} | chpasswd";
            if ($ipLimit > 0) {
                $command .= " && mkdir -p /etc/kyt/limit/ssh/ip && printf '%d\\n' {$ipLimit} > /etc/kyt/limit/ssh/ip/{$usernameEsc}";
            }

            return $command;
        }

        $cmdMap = [
            'vmess' => 'addws',
            'vless' => 'addvless',
            'trojan' => 'addtr',
            'shadowsocks' => 'addss',
        ];

        if (!array_key_exists($protocol, $cmdMap)) {
            throw new \InvalidArgumentException("Protokol {$protocol} tidak valid.");
        }

        $bashCmd = $cmdMap[$protocol];
        $argsEsc = array_map('escapeshellarg', [
            $sni,
            $username,
            (string) $durationDays,
            (string) $quota,
            (string) $ipLimit,
        ]);

        return "printf '%s\\n' " . implode(' ', $argsEsc) . " | {$bashCmd}";
    }

    private function buildSshClient(): ?Ssh
    {
        $host = (string) ($this->server->ssh_host ?? '');
        $port = (int) ($this->server->ssh_port ?: 22);
        $username = (string) ($this->server->ssh_username ?: 'root');
        $rawKey = (string) ($this->server->ssh_private_key ?? '');

        if ($host === '' || $rawKey === '') {
            return null;
        }

        $keySuffix = $this->server->id ? ('_' . $this->server->id) : '_default';
        $keyPath = storage_path("app/vpn_ssh_key{$keySuffix}.pem");
        if (!is_dir(dirname($keyPath))) {
            mkdir(dirname($keyPath), 0755, true);
        }

        file_put_contents($keyPath, str_replace("\r\n", "\n", trim($rawKey)) . "\n");
        @chmod($keyPath, 0600);

        return Ssh::create($username, $host, $port)
            ->usePrivateKey($keyPath)
            ->disableStrictHostKeyChecking()
            ->setTimeout(max(5, (int) ($this->server->timeout_seconds ?: 10)));
    }

    public function healthCheck(): array
    {
        $ssh = $this->buildSshClient();
        if (!$ssh) {
            return [
                'ok' => false,
                'message' => __('Konfigurasi SSH Server VPN belum lengkap.'),
            ];
        }

        $start = microtime(true);
        try {
            $process = $ssh->execute('echo VPN_SSH_OK');
            $latency = round((microtime(true) - $start) * 1000, 1);

            if ($process->isSuccessful() && str_contains($process->getOutput(), 'VPN_SSH_OK')) {
                return [
                    'ok' => true,
                    'latency_ms' => $latency,
                    'message' => __('Koneksi SSH VPN ke :ip::port berhasil (:ms ms).', [
                        'ip' => $this->server->ssh_host,
                        'port' => $this->server->ssh_port ?: 22,
                        'ms' => $latency,
                    ]),
                    'data' => ['driver' => VpnServer::DRIVER_LEGACY_SSH],
                ];
            }

            return [
                'ok' => false,
                'latency_ms' => $latency,
                'message' => __('Koneksi SSH VPN gagal: :err', ['err' => trim($process->getErrorOutput() ?: $process->getOutput() ?: 'Timeout/Auth error')]),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => __('Gagal menguji SSH VPN: :err', ['err' => $e->getMessage()]),
            ];
        }
    }

    public function checkUsername(string $username, ?string $protocol = null): array
    {
        // Legacy SSH does not support fast preflight check without opening an SSH session; assume available.
        return [
            'success' => true,
            'available' => true,
            'exists' => false,
            'message' => __('Pengecekan username dilewati pada mode Legacy SSH.'),
        ];
    }

    public function listAccounts(?string $protocol = null, ?string $status = null, ?string $search = null, bool $includeConfig = false): array
    {
        return [
            'success' => false,
            'accounts' => [],
            'message' => __('Sinkronisasi daftar akun hanya didukung oleh driver REST API vpnxray-main.'),
        ];
    }

    public function getAccountDetail(string $protocol, string $username, string $sniMode = '3'): array
    {
        return [
            'success' => false,
            'message' => __('Pengambilan detail akun jarak jauh hanya didukung oleh driver REST API vpnxray-main.'),
        ];
    }

    public function createAccount(array $payload, ?string $idempotencyKey = null): array
    {
        $ssh = $this->buildSshClient();
        if (!$ssh) {
            return [
                'success' => false,
                'output' => 'Server VPN belum dikonfigurasi dengan benar.',
                'message' => __('Server VPN belum dikonfigurasi dengan benar.'),
            ];
        }

        try {
            $protocol = (string) ($payload['protocol'] ?? '');
            $username = (string) ($payload['username'] ?? '');
            $password = isset($payload['password']) ? (string) $payload['password'] : null;
            $days = (int) ($payload['days'] ?? 30);
            $quota = (int) ($payload['quota_gb'] ?? 0);
            $ipLimit = (int) ($payload['ip_limit'] ?? 1);

            $command = self::buildProvisionCommand($protocol, $username, $password, $days, $quota, $ipLimit);
            $process = $ssh->execute($command);

            if ($process->isSuccessful()) {
                $out = $process->getOutput();
                return [
                    'success' => true,
                    'output' => $out,
                    'message' => 'OK',
                    'data' => [
                        'protocol' => $protocol,
                        'username' => $username,
                        'domain' => $this->server->ssh_host,
                        'formatted_text' => $out,
                        'expired_at' => now()->addDays(max(1, $days))->format('Y-m-d'),
                    ],
                ];
            }

            $err = trim($process->getErrorOutput() ?: $process->getOutput() ?: 'SSH command failed');
            Log::error('LegacySshVpnDriver create failed', ['protocol' => $protocol, 'username' => $username, 'error' => $err]);
            return ['success' => false, 'output' => $err, 'message' => $err];
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => $e->getMessage(), 'message' => $e->getMessage()];
        }
    }

    public function renewAccount(string $protocol, string $username, int $days, ?int $quotaGb = null, ?int $ipLimit = null, ?string $idempotencyKey = null): array
    {
        return [
            'success' => false,
            'message' => __('Perpanjangan jarak jauh memerlukan driver REST API vpnxray-main.'),
        ];
    }

    public function suspendAccount(string $protocol, string $username, ?string $reason = null, bool $adminSuspended = true): array
    {
        return [
            'success' => false,
            'message' => __('Suspend jarak jauh memerlukan driver REST API vpnxray-main.'),
        ];
    }

    public function unsuspendAccount(string $protocol, string $username): array
    {
        return [
            'success' => false,
            'message' => __('Unsuspend jarak jauh memerlukan driver REST API vpnxray-main.'),
        ];
    }

    public function deleteAccount(string $protocol, string $username): array
    {
        return [
            'success' => false,
            'message' => __('Penghapusan jarak jauh memerlukan driver REST API vpnxray-main.'),
        ];
    }

    public function getTrafficStats(): array
    {
        return [
            'success' => false,
            'data' => ['accounts' => []],
            'message' => __('Monitoring lalu lintas data memerlukan driver REST API vpnxray-main.'),
        ];
    }

    public function getAccountDevices(string $protocol, string $username, int $windowMinutes = 60): array
    {
        return [
            'success' => false,
            'data' => [],
            'message' => __('Pantau log device memerlukan driver REST API vpnxray-main.'),
        ];
    }
}
