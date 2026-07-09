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
            file_put_contents($keyPath, $rawKey);
            // Pada Windows chmod mungkin diabaikan, tapi penting untuk VPS Linux
            @chmod($keyPath, 0600);

            $this->ssh = Ssh::create($username, $ip, $port)
                ->usePrivateKey($keyPath)
                ->disableStrictHostKeyChecking();
        }
    }

    public function isConfigured(): bool
    {
        return $this->ssh !== null;
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
            $sni = "bug.com"; // Placeholder SNI
            $command = "";

            if ($protocol === 'ssh') {
                $expiryDate = date('Y-m-d', strtotime("+$durationDays days"));
                $usernameEsc = escapeshellarg($username);
                $passwordEsc = escapeshellarg($password);
                $command = "useradd -e $expiryDate -s /bin/false -M $usernameEsc && echo \"$username:$password\" | chpasswd";
                if ($ipLimit > 0) {
                    $command .= " && mkdir -p /etc/kyt/limit/ssh/ip && echo $ipLimit > /etc/kyt/limit/ssh/ip/$usernameEsc";
                }
            } else {
                // Xray protocols (addws, addvless, addtr, addss)
                $cmdMap = [
                    'vmess' => 'addws',
                    'vless' => 'addvless',
                    'trojan' => 'addtr',
                    'shadowsocks' => 'addss'
                ];

                if (!array_key_exists($protocol, $cmdMap)) {
                    return ['success' => false, 'output' => "Protokol $protocol tidak valid."];
                }

                $bashCmd = $cmdMap[$protocol];
                // As per documentation: piping args through stdin: SNI, username, hari, kuota, limit IP
                $args = "$sni\n$username\n$durationDays\n$quota\n$ipLimit\n";
                $command = "echo -e " . escapeshellarg($args) . " | $bashCmd";
            }

            Log::info("Executing VPN SSH Command: " . $command);
            
            $process = $this->ssh->execute($command);

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();

            if ($process->isSuccessful()) {
                return ['success' => true, 'output' => $output];
            } else {
                Log::error("VPN SSH Failed: " . escapeshellarg($errorOutput));
                return ['success' => false, 'output' => $errorOutput];
            }

        } catch (\Exception $e) {
            Log::error("VPN SSH Exception: " . $e->getMessage());
            return ['success' => false, 'output' => $e->getMessage()];
        }
    }
}
