<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RemoteControlController extends Controller
{
    /**
     * Tampilan Utama Remote Control Android Root di Dashboard Admin Web Jualan
     */
    public function index(Request $request)
    {
        $deviceAuthToken = BotSetting::getSetting('remote_device_auth_token', env('DEVICE_AUTH_TOKEN', 'SECRET_REMOTE_TOKEN_2026'));
        $adminSecretKey = BotSetting::getSetting('remote_admin_secret_key', env('ADMIN_SECRET_KEY', 'ADMIN_SECRET_PASS_2026'));
        $relayPort = (int) BotSetting::getSetting('remote_relay_port', 8099);

        // URL Gateway Relay
        $relayHttpUrl = "http://127.0.0.1:{$relayPort}";
        $isHttps = $request->isSecure() || $request->header('X-Forwarded-Proto') === 'https';
        $defaultWsScheme = $isHttps ? 'wss://' : 'ws://';
        // Pada HTTPS lewat Nginx WSS tidak memerlukan port (port 443 standar), pada HTTP lokal gunakan port
        $defaultWsHost = $isHttps ? $request->getHost() : $request->getHost() . ":{$relayPort}";
        $relayWsUrl = config('services.remote_control.ws_url', env('REMOTE_RELAY_WS_URL', $defaultWsScheme . $defaultWsHost));

        // Periksa daftar perangkat online
        $devices = [];
        try {
            $response = Http::timeout(2)->get("{$relayHttpUrl}/api/devices");
            if ($response->successful()) {
                $devices = $response->json('devices', []);
            }
        } catch (\Exception $e) {
            Log::info("Remote Relay Gateway di {$relayHttpUrl} belum merespon atau offline.");
        }

        // Deteksi status instalasi & daemon service pada VPS
        $serviceStatus = $this->checkServiceStatus($relayPort, $request->getHost());

        return view('admin.remote_control.index', compact(
            'devices',
            'relayHttpUrl',
            'relayWsUrl',
            'adminSecretKey',
            'deviceAuthToken',
            'relayPort',
            'serviceStatus'
        ));
    }

    /**
     * Proxy API untuk memeriksa daftar perangkat online secara asinkron (Polling)
     */
    public function getDevices()
    {
        $relayPort = (int) BotSetting::getSetting('remote_relay_port', 8099);
        $relayHttpUrl = "http://127.0.0.1:{$relayPort}";

        try {
            $response = Http::timeout(2)->get("{$relayHttpUrl}/api/devices");
            if ($response->successful()) {
                return response()->json($response->json());
            }
        } catch (\Exception $e) {
            // Relay offline
        }

        return response()->json(['devices' => []]);
    }

    /**
     * Endpoint API untuk memeriksa status kesehatan service relay dan Nginx reverse proxy
     */
    public function serviceStatus(Request $request)
    {
        $relayPort = (int) BotSetting::getSetting('remote_relay_port', 8099);
        $status = $this->checkServiceStatus($relayPort, $request->getHost());

        return response()->json([
            'success' => true,
            'status' => $status
        ]);
    }

    /**
     * Memperbarui konfigurasi password / auth token Remote Control
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'device_auth_token' => 'required|string|min:4|max:100',
            'admin_secret_key' => 'required|string|min:4|max:100',
            'relay_port' => 'required|integer|between:1024,65535',
        ]);

        BotSetting::setSetting('remote_device_auth_token', $request->device_auth_token);
        BotSetting::setSetting('remote_admin_secret_key', $request->admin_secret_key);
        BotSetting::setSetting('remote_relay_port', $request->relay_port);

        // Sinkronkan ke file config relay di VPS jika ada
        $this->syncVpsConfigFile($request->device_auth_token, $request->admin_secret_key, (int)$request->relay_port);

        return redirect()->back()->with('success', __('Konfigurasi autentikasi Remote Control berhasil diperbarui!'));
    }

    /**
     * Menjalankan instalasi service remote control pada VPS melalui Panel Control
     */
    public function installService(Request $request)
    {
        $relayPort = (int) BotSetting::getSetting('remote_relay_port', 8099);
        $deviceAuthToken = BotSetting::getSetting('remote_device_auth_token', 'SECRET_REMOTE_TOKEN_2026');
        $adminSecretKey = BotSetting::getSetting('remote_admin_secret_key', 'ADMIN_SECRET_PASS_2026');

        $scriptPath = base_path('../remote_control/scripts/install_vps_service.sh');
        $output = '';
        $success = false;

        if (file_exists($scriptPath) && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            try {
                // Eksekusi installer bash dengan environment variable
                $command = "sudo bash " . escapeshellarg($scriptPath);
                $envVars = [
                    'RELAY_PORT' => $relayPort,
                    'DEVICE_AUTH_TOKEN' => $deviceAuthToken,
                    'ADMIN_SECRET_KEY' => $adminSecretKey,
                ];

                if (class_exists(Process::class)) {
                    $process = Process::env($envVars)->timeout(120)->run($command);
                    $output = $process->output() ?: $process->errorOutput();
                    $success = $process->successful();
                } else {
                    $output = shell_exec($command . " 2>&1");
                    $success = str_contains($output, 'INSTALASI SERVICE REMOTE CONTROL SELESAI');
                }
            } catch (\Exception $e) {
                $output = "Gagal menjalankan installer otomatis: " . $e->getMessage();
            }
        } else {
            // Simulasi / Info jika dijalankan di sistem non-Linux atau script dipanggil manual
            $output = "Skrip installer siap di: {$scriptPath}\n"
                    . "Jalankan via terminal VPS jika fungsi exec PHP dibatasi:\n"
                    . "sudo bash " . $scriptPath;
            $success = true;
        }

        $latestStatus = $this->checkServiceStatus($relayPort, $request->getHost());

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Proses instalasi service dijalankan.' : 'Instalasi menghadapi kendala.',
            'output' => $output,
            'status' => $latestStatus,
        ]);
    }

    /**
     * Restart Daemon Service Remote Control di VPS
     */
    public function restartService(Request $request)
    {
        $relayPort = (int) BotSetting::getSetting('remote_relay_port', 8099);
        $output = '';

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            try {
                $output = shell_exec('sudo systemctl restart remote_control 2>&1');
            } catch (\Exception $e) {
                $output = $e->getMessage();
            }
        }

        $status = $this->checkServiceStatus($relayPort, $request->getHost());

        return response()->json([
            'success' => true,
            'message' => 'Perintah restart service dikirim.',
            'output' => $output,
            'status' => $status
        ]);
    }

    /**
     * Helper deteksi status service, port, dan konfigurasi Nginx pada VPS
     */
    protected function checkServiceStatus(int $port, string $host): array
    {
        $isInstalled = false;
        $isRunning = false;
        $isNginxConfigured = false;
        $serviceDetail = 'Tidak terdeteksi';

        // 1. Cek apakah Uvicorn Relay Gateway aktif merespon HTTP Health
        try {
            $res = Http::timeout(1)->get("http://127.0.0.1:{$port}/api/health");
            if ($res->successful()) {
                $isRunning = true;
                $isInstalled = true;
                $serviceDetail = 'Daemon Uvicorn Relay Aktif (Port ' . $port . ')';
            }
        } catch (\Exception $e) {
            // Service belum aktif di port tersebut
        }

        // 2. Cek keberadaan file service systemd di Linux VPS
        $serviceFile = '/etc/systemd/system/remote_control.service';
        $vpsDir = '/var/www/remote_control';

        if (file_exists($serviceFile) || is_dir($vpsDir)) {
            $isInstalled = true;
        }

        // Cek status systemctl jika di Linux
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && function_exists('shell_exec')) {
            $systemdActive = trim((string) @shell_exec('systemctl is-active remote_control 2>/dev/null'));
            if ($systemdActive === 'active') {
                $isRunning = true;
                $isInstalled = true;
                $serviceDetail = 'Systemd Service: active (running)';
            } elseif ($systemdActive === 'inactive' || $systemdActive === 'failed') {
                $isInstalled = true;
                $serviceDetail = "Systemd Service: {$systemdActive} (stopped)";
            }

            // 3. Cek apakah blok /ws/ sudah ada di konfigurasi Nginx
            $nginxGrep = @shell_exec('grep -rn "location /ws/" /etc/nginx/ 2>/dev/null');
            if (!empty($nginxGrep)) {
                $isNginxConfigured = true;
            }
        }

        // Jika di Windows / Lokal dev, cek folder repo
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $localRelayDir = base_path('../remote_control/server_relay');
            if (is_dir($localRelayDir)) {
                $isInstalled = true;
                $serviceDetail = $isRunning ? 'Local Dev Gateway Online' : 'Workspace Siap (Jalankan run_server.bat)';
            }
        }

        return [
            'is_installed' => $isInstalled,
            'is_running' => $isRunning,
            'is_nginx_configured' => $isNginxConfigured,
            'port' => $port,
            'detail' => $serviceDetail,
            'ssh_command' => 'sudo bash /var/www/bot_tele_jualan/remote_control/scripts/install_vps_service.sh',
        ];
    }

    /**
     * Memperbarui file config di /var/www/remote_control/config.py jika direktori ada
     */
    protected function syncVpsConfigFile(string $deviceToken, string $adminKey, int $port): void
    {
        $configPath = '/var/www/remote_control/config.py';
        if (file_exists($configPath) && is_writable($configPath)) {
            $content = "class ServerConfig:\n"
                     . "    HOST = '127.0.0.1'\n"
                     . "    PORT = {$port}\n"
                     . "    DEVICE_AUTH_TOKEN = '{$deviceToken}'\n"
                     . "    ADMIN_SECRET_KEY = '{$adminKey}'\n"
                     . "    HEARTBEAT_INTERVAL = 15\n"
                     . "    CLIENT_TIMEOUT = 30\n";
            @file_put_contents($configPath, $content);
        }
    }
}
