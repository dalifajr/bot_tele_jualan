<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotSetting;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SystemHealthController extends Controller
{
    public function index()
    {
        $dbStart = microtime(true);
        $dbConnected = false;
        $dbLatencyMs = 0;
        $dbDriver = config('database.default');
        $dbSizeLabel = '-';

        try {
            DB::select('SELECT 1');
            $dbConnected = true;
            $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);

            if ($dbDriver === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if ($dbPath && file_exists($dbPath)) {
                    $dbSizeLabel = $this->formatBytes((int) filesize($dbPath));
                }
            } elseif (in_array($dbDriver, ['mysql', 'mariadb'], true)) {
                $dbName = config("database.connections.{$dbDriver}.database");
                if ($dbName) {
                    $sizeRow = DB::selectOne(
                        'SELECT SUM(data_length + index_length) AS size_bytes FROM information_schema.TABLES WHERE table_schema = ?',
                        [$dbName]
                    );
                    if ($sizeRow && !empty($sizeRow->size_bytes)) {
                        $dbSizeLabel = $this->formatBytes((int) $sizeRow->size_bytes);
                    }
                }
            }
        } catch (\Throwable $e) {
            $dbConnected = false;
        }

        $storagePath = storage_path();
        $diskFree = @disk_free_space($storagePath) ?: 0;
        $diskTotal = @disk_total_space($storagePath) ?: 0;
        $diskUsedPercent = $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0;

        $pendingOrdersCount = Order::where('status', 'pending_payment')->count();
        $expiredStuckOrders = Order::where('status', 'pending_payment')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();

        $readyHeldFundsCount = Schema::hasTable('held_funds')
            ? DB::table('held_funds')->where('status', 'held')->where('release_at', '<=', now())->count()
            : 0;

        $pendingTopupsCount = Schema::hasTable('wallet_topups')
            ? WalletTopup::where('status', 'pending')->count()
            : 0;

        $vpnConfigured = !empty(BotSetting::where('key', 'vpn_server_ip')->value('value'))
            && !empty(BotSetting::where('key', 'vpn_server_ssh_key_raw')->value('value'));

        $telegramConfigured = !empty(config('telegram.bot_token'));
        $maintenanceActive = BotSetting::where('key', 'maintenance_mode')->value('value') === '1';

        $logPath = storage_path('logs/laravel.log');
        $logSizeLabel = file_exists($logPath) ? $this->formatBytes((int) filesize($logPath)) : '0 B';

        $metrics = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default'),
            'db_driver' => $dbDriver,
            'db_connected' => $dbConnected,
            'db_latency_ms' => $dbLatencyMs,
            'db_size_label' => $dbSizeLabel,
            'disk_free_label' => $this->formatBytes((int) $diskFree),
            'disk_total_label' => $this->formatBytes((int) $diskTotal),
            'disk_used_percent' => $diskUsedPercent,
            'log_size_label' => $logSizeLabel,
            'vite_bundle_built' => file_exists(public_path('build/manifest.json')),
            'telegram_configured' => $telegramConfigured,
            'vpn_configured' => $vpnConfigured,
            'maintenance_active' => $maintenanceActive,
            'pending_orders' => $pendingOrdersCount,
            'expired_stuck_orders' => $expiredStuckOrders,
            'ready_held_funds' => $readyHeldFundsCount,
            'pending_topups' => $pendingTopupsCount,
            'total_users' => User::count(),
        ];

        return view('admin.system.health', compact('metrics'));
    }

    public function pingTelegram()
    {
        $botToken = config('telegram.bot_token');
        if (!$botToken) {
            return response()->json([
                'ok' => false,
                'message' => __('TELEGRAM_BOT_TOKEN belum dikonfigurasi.'),
            ], 422);
        }

        $start = microtime(true);
        try {
            $res = Http::timeout(4)->get("https://api.telegram.org/bot{$botToken}/getMe");
            $latency = round((microtime(true) - $start) * 1000, 1);

            if ($res->successful() && $res->json('ok')) {
                $botUsername = $res->json('result.username') ?? 'unknown';
                return response()->json([
                    'ok' => true,
                    'latency_ms' => $latency,
                    'message' => __('Terhubung ke Bot @:bot (:ms ms).', ['bot' => $botUsername, 'ms' => $latency]),
                ]);
            }

            return response()->json([
                'ok' => false,
                'latency_ms' => $latency,
                'message' => __('API Telegram mengembalikan status gagal (HTTP :code).', ['code' => $res->status()]),
            ], 502);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => __('Gagal menjangkau API Telegram: :err', ['err' => $e->getMessage()]),
            ], 502);
        }
    }

    public function flushCaches()
    {
        Cache::forget('nav:global_settings');
        Cache::forget('firewall:effective_rules');

        return redirect()->route('admin.system.health')
            ->with('success', __('Cache navigasi & aturan firewall berhasil disegarkan.'));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = (int) floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }
}
