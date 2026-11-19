<?php

namespace App\Services;

use Spatie\Ssh\Ssh;
use App\Models\BotSetting;
use Illuminate\Support\Facades\Log;

class VpnService
{
    protected ?Ssh $ssh = null;

    public function __construct()
    {
        $settings = BotSetting::all()->pluck('value', 'key')->toArray();
        $ip = $settings['vpn_server_ip'] ?? null;
        $port = $settings['vpn_server_port'] ?? 22;
        $username = $settings['vpn_server_username'] ?? 'root';
        $rawKey = $settings['vpn_server_ssh_key_raw'] ?? null;

        if ($ip && $rawKey) {
            $keyPath = storage_path('app/vpn_ssh_key.pem');
            if (!file_exists(storage_path('app'))) {
                mkdir(storage_path('app'), 0755, true);
            }
            
            // Clean up the key: standardize line endings and ensure it ends with a single newline
            $rawKey = str_replace("\r\n", "\n", trim($rawKey)) . "\n";
            file_put_contents($keyPath, $rawKey);
            
            // Pada Windows chmod mungkin diabaikan, tapi penting untuk VPS Linux
            @chmod($keyPath, 0600);

            $this->ssh = Ssh::create($username, $ip, $port)
                ->usePrivateKey($keyPath)
                ->disableStrictHostKeyChecking();
                
            // Add BatchMode to prevent hanging on password prompt if key fails
            try {
                // Not all spatie/ssh versions have configureProcess, but we can try to pass it if possible,
                // or just rely on spatie/ssh's default timeout. 
            } catch (\Exception $e) {}
        }
    }

    public function isConfigured(): bool
    {
        return $this->ssh !== null;
    }

    /**
     * Validate VPN username and password against shell injection / control characters.
     */
    public function validateCredentials(string $protocol, string $username, ?string $password = null): void
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

    /**
     * Build a safely escaped shell command for VPN account provisioning.
     */
    public function buildProvisionCommand(string $protocol, string $username, ?string $password, int $durationDays, int $quota = 0, int $ipLimit = 0): string
    {
        $this->validateCredentials($protocol, $username, $password);

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

    /**
     * @return array ['success' => bool, 'output' => string]
     */
    public function createVpnAccount($protocol, $username, $password, $durationDays, $quota = 0, $ipLimit = 0)
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'output' => 'Server VPN belum dikonfigurasi dengan benar di admin settings.'];
        }

        try {
            $protocolStr = (string) $protocol;
            $usernameStr = (string) $username;
            $passwordStr = $password !== null ? (string) $password : null;

            $command = $this->buildProvisionCommand(
                $protocolStr,
                $usernameStr,
                $passwordStr,
                (int) $durationDays,
                (int) $quota,
                (int) $ipLimit
            );

            Log::info('Executing VPN SSH provisioning command', [
                'protocol' => $protocolStr,
                'username' => $usernameStr,
                'duration_days' => max(1, (int) $durationDays),
                'ip_limit' => max(0, (int) $ipLimit),
            ]);

            $process = $this->ssh->execute($command);

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();

            if ($process->isSuccessful()) {
                return ['success' => true, 'output' => $output];
            }

            Log::error('VPN SSH Failed', ['protocol' => $protocolStr, 'username' => $usernameStr, 'error' => $errorOutput]);
            return ['success' => false, 'output' => $errorOutput];

        } catch (\InvalidArgumentException $e) {
            Log::warning('VPN Provisioning rejected due to invalid input: ' . $e->getMessage());
            return ['success' => false, 'output' => $e->getMessage()];
        } catch (\Exception $e) {
            Log::error('VPN SSH Exception: ' . $e->getMessage());
            return ['success' => false, 'output' => $e->getMessage()];
        }
    }
}
