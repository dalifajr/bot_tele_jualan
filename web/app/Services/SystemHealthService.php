<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Models\Order;
use App\Models\TelegramLoginToken;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spatie\Ssh\Ssh;

class SystemHealthService
{
    public const VALID_TABS = ['ringkasan', 'layanan', 'pemeliharaan', 'pembaruan', 'log'];

    public const SERVICE_CATALOG = [
        'jualan-bot' => [
            'label' => 'Bot Telegram (jualan-bot)',
            'unit' => 'jualan-bot.service',
            'icon' => 'fab fa-telegram',
            'group' => 'bot',
            'description' => 'Service utama bot Telegram Python',
        ],
        'jualan-api' => [
            'label' => 'API Internal (jualan-api)',
            'unit' => 'jualan-api.service',
            'icon' => 'fas fa-plug',
            'group' => 'bot',
            'description' => 'Service API pendukung bot',
        ],
        'nginx' => [
            'label' => 'Web Server (Nginx)',
            'unit' => 'nginx.service',
            'icon' => 'fas fa-globe',
            'group' => 'web',
            'description' => 'Reverse proxy & HTTP/HTTPS server',
        ],
        'php-fpm' => [
            'label' => 'PHP-FPM Runtime',
            'unit' => 'php-fpm.service',
            'icon' => 'fab fa-php',
            'group' => 'web',
            'description' => 'FastCGI Process Manager Laravel',
        ],
        'mysql' => [
            'label' => 'Database Server (MySQL/MariaDB)',
            'unit' => 'mysql.service',
            'icon' => 'fas fa-database',
            'group' => 'db',
            'description' => 'Server database utama aplikasi',
        ],
        'remote-control' => [
            'label' => 'Remote Control Relay',
            'unit' => 'remote_control.service',
            'icon' => 'fas fa-mobile-screen-button',
            'group' => 'extra',
            'description' => 'WebSocket relay untuk Remote Control HP',
        ],
        'jualan-backup-timer' => [
            'label' => 'Auto Backup Timer',
            'unit' => 'jualan-backup.timer',
            'icon' => 'fas fa-clock-rotate-left',
            'group' => 'extra',
            'description' => 'Penjadwal snapshot backup otomatis VPS',
        ],
    ];

    public const ALLOWED_SERVICE_ACTIONS = ['start', 'stop', 'restart'];

    public const ARTISAN_ACTIONS = [
        'release_expired' => [
            'command' => 'orders:release-expired',
            'label' => 'Release Order Expired',
            'description' => 'Batalkan pesanan kadaluarsa dan kembalikan stok ke etalase.',
        ],
        'release_held' => [
            'command' => 'funds:release-held',
            'label' => 'Rilis Held Funds Seller',
            'description' => 'Cairkan dana seller yang masa tunggunya sudah jatuh tempo.',
        ],
        'deliver_paid' => [
            'command' => 'orders:deliver-paid',
            'label' => 'Pulihkan Order Paid Tertahan',
            'description' => 'Kirim ulang produk & selesaikan pesanan lunas yang belum terkirim.',
        ],
        'clean_tokens' => [
            'command' => 'tokens:clean',
            'label' => 'Bersihkan Token & Sesi Lama',
            'description' => 'Hapus token login Telegram kedaluwarsa & sesi database > 7 hari.',
        ],
        'optimize_clear' => [
            'command' => 'optimize:clear',
            'label' => 'Clear Semua Cache Laravel',
            'description' => 'Bersihkan cache aplikasi, config, route, event, dan compiled views.',
        ],
        'migrate' => [
            'command' => 'migrate',
            'params' => ['--force' => true],
            'label' => 'Jalankan Migrasi Database',
            'description' => 'Terapkan seluruh file migrasi skema database yang tertunda.',
        ],
    ];

    public const LOG_SOURCES = ['laravel', 'bot', 'api', 'nginx', 'remote-control', 'ops-task'];

    public function projectRoot(): string
    {
        return (string) (config('system_health.project_root') ?: realpath(base_path('..')) ?: dirname(base_path()));
    }

    public function rootEnvPath(): string
    {
        return (string) (config('system_health.root_env_path') ?: ($this->projectRoot() . DIRECTORY_SEPARATOR . '.env'));
    }

    public function webEnvPath(): string
    {
        return (string) (config('system_health.web_env_path') ?: base_path('.env'));
    }

    public function opsBackupDir(): string
    {
        return (string) (config('system_health.ops_backup_dir') ?: ($this->projectRoot() . DIRECTORY_SEPARATOR . 'ops' . DIRECTORY_SEPARATOR . 'backups'));
    }

    public function bridgeScriptPath(): string
    {
        return $this->projectRoot() . '/ops/web_ops_bridge.sh';
    }

    public function taskLogPath(): string
    {
        return storage_path('logs/vps_ops_task.log');
    }

    public function isLinux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }

    public function resolveActiveTab(Request $request): string
    {
        $tab = (string) $request->query('tab', 'ringkasan');
        return in_array($tab, self::VALID_TABS, true) ? $tab : 'ringkasan';
    }

    public function buildDashboardData(Request $request): array
    {
        $activeTab = $this->resolveActiveTab($request);

        // 1. Database Telemetry & Top Tables
        $dbStart = microtime(true);
        $dbConnected = false;
        $dbLatencyMs = 0.0;
        $dbDriver = (string) config('database.default');
        $dbSizeBytes = 0;
        $dbSizeLabel = '-';
        $dbTableCount = 0;
        $topTables = [];

        try {
            DB::select('SELECT 1');
            $dbConnected = true;
            $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);

            if ($dbDriver === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if ($dbPath && $dbPath !== ':memory:' && file_exists($dbPath)) {
                    $dbSizeBytes = (int) filesize($dbPath);
                    $dbSizeLabel = $this->formatBytes($dbSizeBytes);
                }
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
                $dbTableCount = count($tables);
                foreach (array_slice($tables, 0, 12) as $tbl) {
                    $tName = $tbl->name;
                    try {
                        $cnt = (int) DB::table($tName)->count();
                        $topTables[] = [
                            'name' => $tName,
                            'rows' => $cnt,
                            'size_bytes' => 0,
                            'size_label' => '-',
                        ];
                    } catch (\Throwable $e) {
                    }
                }
                usort($topTables, fn ($a, $b) => $b['rows'] <=> $a['rows']);
                $topTables = array_slice($topTables, 0, 8);
            } elseif (in_array($dbDriver, ['mysql', 'mariadb'], true)) {
                $dbName = config("database.connections.{$dbDriver}.database");
                if ($dbName) {
                    $rows = DB::select(
                        'SELECT TABLE_NAME as name, TABLE_ROWS as rows_est, (DATA_LENGTH + INDEX_LENGTH) as size_bytes
                         FROM information_schema.TABLES
                         WHERE table_schema = ?
                         ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC',
                        [$dbName]
                    );
                    $dbTableCount = count($rows);
                    foreach ($rows as $row) {
                        $sz = (int) ($row->size_bytes ?? 0);
                        $dbSizeBytes += $sz;
                    }
                    $dbSizeLabel = $this->formatBytes($dbSizeBytes);
                    foreach (array_slice($rows, 0, 8) as $row) {
                        $sz = (int) ($row->size_bytes ?? 0);
                        $topTables[] = [
                            'name' => (string) $row->name,
                            'rows' => (int) ($row->rows_est ?? 0),
                            'size_bytes' => $sz,
                            'size_label' => $this->formatBytes($sz),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            $dbConnected = false;
        }

        // 2. Disk & Folder Breakdown
        $storagePath = storage_path();
        $diskFree = (int) (@disk_free_space($storagePath) ?: 0);
        $diskTotal = (int) (@disk_total_space($storagePath) ?: 0);
        $diskUsed = max(0, $diskTotal - $diskFree);
        $diskUsedPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0.0;

        $logPath = storage_path('logs/laravel.log');
        $logSizeBytes = file_exists($logPath) ? (int) filesize($logPath) : 0;

        $diskBreakdown = [
            [
                'label' => __('Log Laravel (storage/logs)'),
                'path' => 'web/storage/logs',
                'bytes' => $this->getDirectorySize(storage_path('logs')),
            ],
            [
                'label' => __('Backup VPS (ops/backups)'),
                'path' => 'ops/backups',
                'bytes' => $this->getDirectorySize($this->opsBackupDir()),
            ],
            [
                'label' => __('Backup Web (storage/app/backups)'),
                'path' => 'web/storage/app/backups',
                'bytes' => $this->getDirectorySize(storage_path('app/backups')),
            ],
            [
                'label' => __('Media Publik (storage/app/public)'),
                'path' => 'web/storage/app/public',
                'bytes' => $this->getDirectorySize(storage_path('app/public')),
            ],
            [
                'label' => __('Framework Cache & Views'),
                'path' => 'web/storage/framework',
                'bytes' => $this->getDirectorySize(storage_path('framework')),
            ],
        ];
        foreach ($diskBreakdown as &$item) {
            $item['size_label'] = $this->formatBytes($item['bytes']);
        }
        unset($item);
        usort($diskBreakdown, fn ($a, $b) => $b['bytes'] <=> $a['bytes']);

        // 3. Server OS, CPU, RAM, Uptime Telemetry
        $serverTelemetry = $this->collectServerTelemetry();

        // 4. Queue, Scheduler & Business Health Counters
        $pendingOrdersCount = Schema::hasTable('orders') ? Order::where('status', 'pending_payment')->count() : 0;
        $expiredStuckOrders = Schema::hasTable('orders')
            ? Order::where('status', 'pending_payment')->whereNotNull('expires_at')->where('expires_at', '<=', now())->count()
            : 0;
        $paidStuckOrders = Schema::hasTable('orders')
            ? Order::where('status', 'paid')->count()
            : 0;
        $readyHeldFundsCount = Schema::hasTable('held_funds')
            ? DB::table('held_funds')->where('status', 'held')->where('release_at', '<=', now())->count()
            : 0;
        $pendingTopupsCount = Schema::hasTable('wallet_topups')
            ? WalletTopup::where('status', 'pending')->count()
            : 0;
        $failedJobsCount = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->count()
            : 0;
        $pendingJobsCount = Schema::hasTable('jobs')
            ? DB::table('jobs')->count()
            : 0;
        $expiredTokensCount = Schema::hasTable('telegram_login_tokens')
            ? TelegramLoginToken::where('expires_at', '<', now())->where('status', '!=', 'used')->count()
            : 0;

        // 5. Integrations & Settings
        $vpnConfigured = !empty(BotSetting::where('key', 'vpn_server_ip')->value('value'))
            && !empty(BotSetting::where('key', 'vpn_server_ssh_key_raw')->value('value'));
        $telegramConfigured = !empty(config('telegram.bot_token'));
        $maintenanceActive = BotSetting::where('key', 'maintenance_mode')->value('value') === '1';
        $turnstileSiteKey = (string) ($this->readEnvValue($this->webEnvPath(), 'CLOUDFLARE_TURNSTILE_SITE_KEY') ?? config('services.turnstile.site_key', ''));
        $turnstileSecretKey = (string) ($this->readEnvValue($this->webEnvPath(), 'CLOUDFLARE_TURNSTILE_SECRET_KEY') ?? config('services.turnstile.secret_key', ''));
        $turnstileConfigured = $turnstileSiteKey !== ''
            && $turnstileSecretKey !== ''
            && $turnstileSiteKey !== '1x00000000000000000000AA';

        $viteBuilt = file_exists(public_path('build/manifest.json'));

        // 6. VPS Bridge & Systemd Services
        $bridgeStatus = $this->detectBridgeStatus();
        $services = $this->collectServicesStatus($bridgeStatus['available']);

        // 7. Bot Migration Prerequisites & Status (from ops/jualan)
        $botMigration = $this->checkBotMigrationPrerequisites($services);

        // 8. Git & Update Status
        $gitInfo = $this->collectGitInfo();

        // 9. Ops Backups Summary
        $opsBackups = $this->collectOpsBackupsSummary();

        // 10. Admin Accounts (for Reset Admin Password)
        $adminUsers = User::where('role', 'admin')
            ->orderBy('id')
            ->get(['id', 'username', 'full_name', 'email']);

        // 11. PHP Extensions & Writable Directories
        $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'zip', 'json', 'fileinfo', 'gd'];
        $phpExtensions = [];
        foreach ($requiredExtensions as $ext) {
            $phpExtensions[$ext] = extension_loaded($ext);
        }

        $writableChecks = [
            'storage/logs' => is_writable(storage_path('logs')),
            'storage/framework' => is_writable(storage_path('framework')),
            'bootstrap/cache' => is_writable(base_path('bootstrap/cache')),
            'web/.env' => file_exists($this->webEnvPath()) && is_writable($this->webEnvPath()),
            '.env (root)' => file_exists($this->rootEnvPath()) && is_writable($this->rootEnvPath()),
        ];

        $metrics = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => (string) config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'app_timezone' => (string) config('app.timezone'),
            'cache_driver' => (string) config('cache.default'),
            'session_driver' => (string) config('session.driver'),
            'queue_driver' => (string) config('queue.default'),
            'memory_limit' => (string) ini_get('memory_limit'),
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'max_execution_time' => (string) ini_get('max_execution_time'),
            'opcache_enabled' => function_exists('opcache_get_status') && @opcache_get_status(false) !== false,
            'db_driver' => $dbDriver,
            'db_connected' => $dbConnected,
            'db_latency_ms' => $dbLatencyMs,
            'db_size_label' => $dbSizeLabel,
            'db_table_count' => $dbTableCount,
            'disk_free_label' => $this->formatBytes($diskFree),
            'disk_used_label' => $this->formatBytes($diskUsed),
            'disk_total_label' => $this->formatBytes($diskTotal),
            'disk_used_percent' => $diskUsedPercent,
            'log_size_label' => $this->formatBytes($logSizeBytes),
            'log_size_bytes' => $logSizeBytes,
            'vite_bundle_built' => $viteBuilt,
            'telegram_configured' => $telegramConfigured,
            'vpn_configured' => $vpnConfigured,
            'turnstile_configured' => $turnstileConfigured,
            'maintenance_active' => $maintenanceActive,
            'pending_orders' => $pendingOrdersCount,
            'expired_stuck_orders' => $expiredStuckOrders,
            'paid_stuck_orders' => $paidStuckOrders,
            'ready_held_funds' => $readyHeldFundsCount,
            'pending_topups' => $pendingTopupsCount,
            'failed_jobs' => $failedJobsCount,
            'pending_jobs' => $pendingJobsCount,
            'expired_tokens' => $expiredTokensCount,
            'total_users' => User::count(),
        ];

        // 12. Health Score & Actionable Recommendations
        [$healthScore, $healthStatus, $healthBadgeColor, $healthChecks] = $this->evaluateHealth($metrics, $phpExtensions, $writableChecks);

        // 13. Background Task Status & Initial Log Preview
        $taskStatus = $this->readTaskStatus();
        $initialLog = $this->readLogSource('laravel', 80, 'all');

        $turnstileInfo = [
            'site_key' => $turnstileSiteKey,
            'secret_masked' => $this->maskSecret($turnstileSecretKey),
            'configured' => $turnstileConfigured,
            'web_env_writable' => file_exists($this->webEnvPath()) && is_writable($this->webEnvPath()),
        ];

        $artisanActions = self::ARTISAN_ACTIONS;

        return compact(
            'activeTab',
            'metrics',
            'serverTelemetry',
            'topTables',
            'diskBreakdown',
            'healthScore',
            'healthStatus',
            'healthBadgeColor',
            'healthChecks',
            'bridgeStatus',
            'services',
            'botMigration',
            'gitInfo',
            'opsBackups',
            'adminUsers',
            'phpExtensions',
            'writableChecks',
            'turnstileInfo',
            'taskStatus',
            'initialLog',
            'artisanActions'
        );
    }

    public function pingTelegram(): array
    {
        $botToken = config('telegram.bot_token');
        if (!$botToken) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => __('TELEGRAM_BOT_TOKEN belum dikonfigurasi.'),
            ];
        }

        $start = microtime(true);
        try {
            $res = Http::timeout(5)->get("https://api.telegram.org/bot{$botToken}/getMe");
            $latency = round((microtime(true) - $start) * 1000, 1);

            if ($res->successful() && $res->json('ok')) {
                $botUsername = $res->json('result.username') ?? 'unknown';
                return [
                    'ok' => true,
                    'status' => 200,
                    'latency_ms' => $latency,
                    'message' => __('Terhubung ke Bot @:bot (:ms ms).', ['bot' => $botUsername, 'ms' => $latency]),
                ];
            }

            return [
                'ok' => false,
                'status' => 502,
                'latency_ms' => $latency,
                'message' => __('API Telegram mengembalikan status gagal (HTTP :code).', ['code' => $res->status()]),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 502,
                'message' => __('Gagal menjangkau API Telegram: :err', ['err' => $e->getMessage()]),
            ];
        }
    }

    public function pingVpn(): array
    {
        $ip = (string) (BotSetting::where('key', 'vpn_server_ip')->value('value') ?? '');
        $port = (int) (BotSetting::where('key', 'vpn_server_port')->value('value') ?? 22);
        $username = (string) (BotSetting::where('key', 'vpn_server_username')->value('value') ?? 'root');
        $rawKey = (string) (BotSetting::where('key', 'vpn_server_ssh_key_raw')->value('value') ?? '');

        if ($ip === '' || $rawKey === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => __('Konfigurasi SSH Server VPN belum lengkap di Pengaturan Sistem.'),
            ];
        }

        $start = microtime(true);
        try {
            $keyPath = storage_path('app/vpn_ssh_key.pem');
            if (!is_dir(dirname($keyPath))) {
                mkdir(dirname($keyPath), 0755, true);
            }
            file_put_contents($keyPath, str_replace("\r\n", "\n", trim($rawKey)) . "\n");
            @chmod($keyPath, 0600);

            $ssh = Ssh::create($username, $ip, $port)
                ->usePrivateKey($keyPath)
                ->disableStrictHostKeyChecking()
                ->setTimeout(6);

            $process = $ssh->execute('echo VPN_SSH_OK');
            $latency = round((microtime(true) - $start) * 1000, 1);

            if ($process->isSuccessful() && str_contains($process->getOutput(), 'VPN_SSH_OK')) {
                return [
                    'ok' => true,
                    'status' => 200,
                    'latency_ms' => $latency,
                    'message' => __('Koneksi SSH VPN ke :ip::port berhasil (:ms ms).', [
                        'ip' => $ip,
                        'port' => $port,
                        'ms' => $latency,
                    ]),
                ];
            }

            return [
                'ok' => false,
                'status' => 502,
                'latency_ms' => $latency,
                'message' => __('Koneksi SSH VPN gagal: :err', ['err' => trim($process->getErrorOutput() ?: $process->getOutput() ?: 'Timeout/Auth error')]),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 502,
                'message' => __('Gagal menguji SSH VPN: :err', ['err' => $e->getMessage()]),
            ];
        }
    }

    public function runArtisanAction(string $actionKey): array
    {
        if (!isset(self::ARTISAN_ACTIONS[$actionKey])) {
            return ['ok' => false, 'message' => __('Aksi pemeliharaan tidak dikenal.')];
        }

        $spec = self::ARTISAN_ACTIONS[$actionKey];
        try {
            $exitCode = Artisan::call($spec['command'], $spec['params'] ?? []);
            $output = trim(Artisan::output());

            if ($actionKey === 'optimize_clear') {
                Cache::forget('nav:global_settings');
                Cache::forget('firewall:effective_rules');
            }

            $this->recordAudit('system_artisan_' . $actionKey, "Menjalankan {$spec['command']} (exit={$exitCode})");

            return [
                'ok' => $exitCode === 0,
                'message' => $exitCode === 0
                    ? __('Perintah :cmd berhasil dijalankan.', ['cmd' => $spec['command']])
                    : __('Perintah :cmd selesai dengan kode :code.', ['cmd' => $spec['command'], 'code' => $exitCode]),
                'output' => $output !== '' ? $output : __('(Selesai tanpa output tambahan)'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => __('Gagal menjalankan :cmd: :err', ['cmd' => $spec['command'], 'err' => $e->getMessage()]),
                'output' => $e->getMessage(),
            ];
        }
    }

    public function clearLaravelLogs(): array
    {
        $logFile = storage_path('logs/laravel.log');
        $freedBytes = 0;

        if (file_exists($logFile)) {
            $freedBytes += (int) filesize($logFile);
            @file_put_contents($logFile, '');
        }

        // Also clean old daily rotated laravel-*.log files older than 3 days
        $rotatedRemoved = 0;
        foreach (glob(storage_path('logs/laravel-*.log')) ?: [] as $oldLog) {
            if (@filemtime($oldLog) < now()->subDays(3)->timestamp) {
                $freedBytes += (int) (@filesize($oldLog) ?: 0);
                if (@unlink($oldLog)) {
                    $rotatedRemoved++;
                }
            }
        }

        $freedLabel = $this->formatBytes($freedBytes);
        $this->recordAudit('system_clear_logs', "Membersihkan laravel.log (+{$rotatedRemoved} file rotasi lama), menghemat {$freedLabel}.");

        return [
            'ok' => true,
            'message' => __('Log Laravel berhasil dikosongkan (menghemat :size).', ['size' => $freedLabel]),
        ];
    }

    public function pruneOpsBackups(int $keep): array
    {
        $keep = max(1, min(100, $keep));
        $dir = $this->opsBackupDir();

        if (!is_dir($dir)) {
            return [
                'ok' => true,
                'deleted' => 0,
                'message' => __('Direktori backup ops belum tersedia.'),
            ];
        }

        $patterns = [
            $dir . DIRECTORY_SEPARATOR . '*.sqlite3',
            $dir . DIRECTORY_SEPARATOR . '*.tar.gz',
            $dir . DIRECTORY_SEPARATOR . '*.patch',
        ];
        $files = [];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (is_file($file)) {
                    $files[$file] = (int) (@filemtime($file) ?: 0);
                }
            }
        }

        arsort($files);
        $orderedPaths = array_keys($files);

        if (count($orderedPaths) <= $keep) {
            return [
                'ok' => true,
                'deleted' => 0,
                'message' => __('Jumlah arsip backup (:total) masih dalam batas simpan (:keep).', [
                    'total' => count($orderedPaths),
                    'keep' => $keep,
                ]),
            ];
        }

        $toDelete = array_slice($orderedPaths, $keep);
        $deletedCount = 0;
        $freedBytes = 0;

        foreach ($toDelete as $path) {
            $sz = (int) (@filesize($path) ?: 0);
            if (@unlink($path)) {
                $deletedCount++;
                $freedBytes += $sz;
            }
        }

        $freedLabel = $this->formatBytes($freedBytes);
        $this->recordAudit('system_prune_backups', "Menghapus {$deletedCount} backup ops lama (simpan {$keep} terbaru), hemat {$freedLabel}.");

        return [
            'ok' => true,
            'deleted' => $deletedCount,
            'message' => __('Berhasil menghapus :count arsip backup lama (menghemat :size).', [
                'count' => $deletedCount,
                'size' => $freedLabel,
            ]),
        ];
    }

    public function toggleMaintenanceMode(): array
    {
        $current = BotSetting::where('key', 'maintenance_mode')->value('value') === '1';
        $next = !$current;

        BotSetting::updateOrCreate(
            ['key' => 'maintenance_mode'],
            ['value' => $next ? '1' : '0']
        );
        Cache::forget('nav:global_settings');

        $this->recordAudit('system_toggle_maintenance', $next ? 'Mengaktifkan Mode Maintenance website.' : 'Menonaktifkan Mode Maintenance website.');

        return [
            'ok' => true,
            'active' => $next,
            'message' => $next
                ? __('Mode Maintenance website telah DIAKTIFKAN.')
                : __('Mode Maintenance website telah DINONAKTIFKAN (Website kembali online).'),
        ];
    }

    public function runServiceAction(string $unitKey, string $action): array
    {
        if (!array_key_exists($unitKey, self::SERVICE_CATALOG) || !in_array($action, self::ALLOWED_SERVICE_ACTIONS, true)) {
            return ['ok' => false, 'message' => __('Layanan atau aksi tidak valid.')];
        }

        $bridge = $this->detectBridgeStatus();
        if (!$bridge['available']) {
            return [
                'ok' => false,
                'message' => __('VPS Ops Bridge belum aktif. Jalankan "sudo bash ops/install_web_sudoers.sh" di VPS untuk mengaktifkan kontrol systemd.'),
            ];
        }

        $res = $this->runBridge(['service', $unitKey, $action], 25);
        $this->recordAudit('vps_service_' . $action, "Aksi {$action} pada layanan {$unitKey}: " . ($res['ok'] ? 'OK' : 'FAIL'));

        if ($res['ok']) {
            return [
                'ok' => true,
                'message' => __('Perintah :action untuk :svc berhasil dikirim.', [
                    'action' => strtoupper($action),
                    'svc' => self::SERVICE_CATALOG[$unitKey]['label'],
                ]),
                'output' => $res['output'],
            ];
        }

        return [
            'ok' => false,
            'message' => __('Gagal menjalankan :action pada :svc: :err', [
                'action' => $action,
                'svc' => self::SERVICE_CATALOG[$unitKey]['label'],
                'err' => $res['output'] ?: 'Unknown error',
            ]),
        ];
    }

    public function toggleBotCommands(bool $disable): array
    {
        $rootEnv = $this->rootEnvPath();
        if (!file_exists($rootEnv)) {
            return [
                'ok' => false,
                'message' => __('File .env root bot tidak ditemukan di :path.', ['path' => $rootEnv]),
            ];
        }

        if (!is_writable($rootEnv)) {
            return [
                'ok' => false,
                'message' => __('File .env root tidak memiliki izin tulis untuk web server. Jalankan sudo bash ops/install_web_sudoers.sh di VPS.'),
            ];
        }

        if ($disable) {
            $prereq = $this->checkBotMigrationPrerequisites($this->collectServicesStatus($this->detectBridgeStatus()['available']));
            if (!$prereq['ready']) {
                return [
                    'ok' => false,
                    'message' => __('Persyaratan migrasi website belum terpenuhi (:failed item belum siap). Periksa checklist prasyarat terlebih dahulu.', [
                        'failed' => $prereq['failed_count'],
                    ]),
                ];
            }
        }

        $written = $this->updateEnvFile($rootEnv, [
            'DISABLE_BOT_COMMANDS' => $disable ? 'true' : 'false',
        ]);

        if (!$written) {
            return ['ok' => false, 'message' => __('Gagal menyimpan perubahan ke file .env root.')];
        }

        $restarted = false;
        if ($this->detectBridgeStatus()['available']) {
            $restartRes = $this->runBridge(['service', 'jualan-bot', 'restart'], 20);
            $restarted = $restartRes['ok'];
        }

        $this->recordAudit('vps_toggle_bot_commands', 'DISABLE_BOT_COMMANDS=' . ($disable ? 'true' : 'false') . ' (bot_restarted=' . ($restarted ? 'yes' : 'no') . ')');

        $statusMsg = $disable
            ? __('Mode Migrasi Web DIAKTIFKAN (DISABLE_BOT_COMMANDS=true).')
            : __('Command Bot Telegram DIAKTIFKAN KEMBALI (DISABLE_BOT_COMMANDS=false).');

        if ($restarted) {
            $statusMsg .= ' ' . __('Service jualan-bot otomatis di-restart.');
        } elseif ($this->isLinux()) {
            $statusMsg .= ' ' . __('Silakan restart jualan-bot.service agar perubahan aktif.');
        }

        return ['ok' => true, 'message' => $statusMsg];
    }

    public function checkGitUpdate(): array
    {
        $bridge = $this->detectBridgeStatus();
        if ($bridge['available']) {
            $res = $this->runBridge(['check-update'], 25);
            return [
                'ok' => $res['ok'],
                'output' => $res['output'],
                'git' => $this->collectGitInfo(),
            ];
        }

        $root = $this->projectRoot();
        try {
            $env = ['PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:' . (getenv('PATH') ?: '')];
            $branch = trim(Process::path($root)->env($env)->timeout(5)->run($this->gitCmd(['symbolic-ref', '--quiet', '--short', 'HEAD']))->output()) ?: 'main';
            $local = trim(Process::path($root)->env($env)->timeout(5)->run($this->gitCmd(['rev-parse', 'HEAD']))->output());

            // Use read-only ls-remote so www-data can check remote GitHub commit even without write access to .git/FETCH_HEAD
            $lsRemote = Process::path($root)->env($env)->timeout(15)->run($this->gitCmd(['ls-remote', 'origin', "refs/heads/{$branch}"]));
            if (!$lsRemote->successful()) {
                return [
                    'ok' => false,
                    'output' => trim($lsRemote->errorOutput() ?: $lsRemote->output() ?: __('Gagal menghubungi remote origin GitHub.')),
                    'git' => $this->collectGitInfo(),
                ];
            }

            $remoteLine = trim($lsRemote->output());
            $remote = $remoteLine !== '' ? preg_split('/\s+/', $remoteLine)[0] : '';
            $upToDate = ($local !== '' && $remote !== '' && $local === $remote);

            $summary = "Repo  : {$root}\n"
                . "Branch: {$branch}\n"
                . "Local : " . ($local ?: '-') . "\n"
                . "Remote: " . ($remote ?: '-') . "\n"
                . "Status: " . ($upToDate ? 'up-to-date' : 'update available');

            if (!$upToDate && $remote !== '') {
                $summary .= "\n\n" . __('Tersedia pembaruan baru di GitHub. Aktifkan VPS Ops Bridge (sudo bash ops/install_web_sudoers.sh) untuk menerapkan update 1-klik dari web, atau jalankan "sudo jualan update" di VPS.');
            }

            return [
                'ok' => true,
                'up_to_date' => $upToDate,
                'output' => $summary,
                'git' => $this->collectGitInfo(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'output' => $e->getMessage(),
                'git' => $this->collectGitInfo(),
            ];
        }
    }

    public function triggerVpsBackgroundTask(string $task): array
    {
        if (!in_array($task, ['update', 'rollback', 'repair', 'website-restart'], true)) {
            return ['ok' => false, 'message' => __('Tugas VPS tidak dikenal.')];
        }

        $bridge = $this->detectBridgeStatus();

        // Fallback repair mode in PHP/Artisan if bridge is not installed
        if (!$bridge['available'] && $task === 'repair') {
            $lines = [];
            $lines[] = '[TASK_STATUS:RUNNING]';
            $lines[] = '[TASK_NAME:repair-laravel]';
            $lines[] = '[TASK_STARTED:' . now()->format('Y-m-d H:i:s') . ']';
            foreach (['migrate', 'optimize_clear', 'deliver_paid', 'clean_tokens'] as $step) {
                $stepRes = $this->runArtisanAction($step);
                $lines[] = "[{$step}] " . ($stepRes['ok'] ? 'OK' : 'WARN') . ' — ' . $stepRes['message'];
            }
            $lines[] = '[TASK_FINISHED:' . now()->format('Y-m-d H:i:s') . ']';
            $lines[] = '[TASK_STATUS:DONE]';
            @file_put_contents($this->taskLogPath(), implode("\n", $lines) . "\n");

            $this->recordAudit('vps_task_repair_laravel', 'Menjalankan pemulihan otomatis mode Laravel (migrate + optimize:clear + deliver-paid + tokens:clean).');

            return [
                'ok' => true,
                'message' => __('Pemulihan mode Laravel selesai (migrasi, clear cache, pulihkan order paid, bersihkan token). Pasang VPS Bridge untuk mencakup perbaikan systemd & Nginx.'),
            ];
        }

        if (!$bridge['available']) {
            return [
                'ok' => false,
                'message' => __('VPS Ops Bridge belum aktif. Jalankan "sudo bash ops/install_web_sudoers.sh" di VPS terlebih dahulu.'),
            ];
        }

        $res = $this->runBridge([$task], 20);
        $this->recordAudit('vps_task_' . $task, "Memicu tugas background VPS: {$task} (" . ($res['ok'] ? 'STARTED' : 'FAIL') . ')');

        if ($res['ok']) {
            return [
                'ok' => true,
                'message' => __('Tugas ":task" telah dimulai di latar belakang. Pantau progres langsung di panel output di bawah.', ['task' => $task]),
            ];
        }

        return [
            'ok' => false,
            'message' => __('Gagal memulai tugas ":task": :err', ['task' => $task, 'err' => $res['output'] ?: 'Unknown error']),
        ];
    }

    public function readTaskStatus(): array
    {
        $path = $this->taskLogPath();
        if (!file_exists($path)) {
            return [
                'status' => 'idle',
                'task_name' => null,
                'updated_at' => null,
                'output' => '',
            ];
        }

        $content = $this->tailFile($path, 120);
        $status = 'idle';
        if (str_contains($content, '[TASK_STATUS:DONE]')) {
            $status = 'done';
        } elseif (str_contains($content, '[TASK_STATUS:FAILED]')) {
            $status = 'failed';
        } elseif (str_contains($content, '[TASK_STATUS:RUNNING]')) {
            $status = 'running';
        }

        $taskName = null;
        if (preg_match('/\[TASK_NAME:([^\]]+)\]/', $content, $m)) {
            $taskName = trim($m[1]);
        }

        return [
            'status' => $status,
            'task_name' => $taskName,
            'updated_at' => date('Y-m-d H:i:s', (int) (@filemtime($path) ?: time())),
            'output' => $content,
        ];
    }

    public function readLogSource(string $source, int $lines = 100, string $level = 'all'): array
    {
        if (!in_array($source, self::LOG_SOURCES, true)) {
            $source = 'laravel';
        }
        $lines = max(10, min(500, $lines));
        $level = in_array(strtolower($level), ['all', 'error', 'warning'], true) ? strtolower($level) : 'all';

        $content = '';
        $available = true;
        $note = '';

        if ($source === 'laravel') {
            $path = storage_path('logs/laravel.log');
            if (!file_exists($path)) {
                $content = __('(File storage/logs/laravel.log belum ada atau kosong)');
            } else {
                $content = $this->tailFile($path, $lines);
                if (trim($content) === '') {
                    $content = __('(Log Laravel saat ini kosong)');
                }
            }
        } elseif ($source === 'ops-task') {
            $path = $this->taskLogPath();
            $content = file_exists($path) ? $this->tailFile($path, $lines) : __('(Belum ada riwayat tugas VPS yang dijalankan dari web)');
        } else {
            $bridge = $this->detectBridgeStatus();
            if ($bridge['available']) {
                $res = $this->runBridge(['logs', $source, (string) $lines], 15);
                $content = $res['output'] !== '' ? $res['output'] : __('(Log kosong)');
            } else {
                $available = false;
                $note = __('Sumber log ini membutuhkan VPS Ops Bridge di server Linux.');
                $content = __('VPS Ops Bridge belum aktif. Jalankan: sudo bash ops/install_web_sudoers.sh');
            }
        }

        if ($level !== 'all' && $available && $content !== '') {
            $needle = $level === 'error'
                ? ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY', 'EXCEPTION', 'FATAL']
                : ['WARNING', 'WARN'];
            $filtered = array_filter(explode("\n", $content), function ($line) use ($needle) {
                $upper = strtoupper($line);
                foreach ($needle as $n) {
                    if (str_contains($upper, $n)) {
                        return true;
                    }
                }
                return false;
            });
            $content = count($filtered) > 0
                ? implode("\n", $filtered)
                : __('(Tidak ditemukan baris log dengan level :lvl pada :n baris terakhir)', ['lvl' => strtoupper($level), 'n' => $lines]);
        }

        return [
            'ok' => true,
            'source' => $source,
            'lines' => $lines,
            'level' => $level,
            'available' => $available,
            'note' => $note,
            'content' => $content,
            'fetched_at' => now()->format('H:i:s'),
        ];
    }

    public function updateTurnstileKeys(string $siteKey, string $secretKey): array
    {
        $webEnv = $this->webEnvPath();
        if (!file_exists($webEnv) || !is_writable($webEnv)) {
            return [
                'ok' => false,
                'message' => __('File web/.env tidak ditemukan atau tidak dapat ditulis oleh web server.'),
            ];
        }

        $ok = $this->updateEnvFile($webEnv, [
            'CLOUDFLARE_TURNSTILE_SITE_KEY' => trim($siteKey),
            'CLOUDFLARE_TURNSTILE_SECRET_KEY' => trim($secretKey),
        ]);

        if (!$ok) {
            return ['ok' => false, 'message' => __('Gagal memperbarui kunci Cloudflare Turnstile.')];
        }

        try {
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
        }

        $this->recordAudit('vps_turnstile_updated', 'Memperbarui konfigurasi Cloudflare Turnstile di web/.env.');

        return [
            'ok' => true,
            'message' => __('Kunci Cloudflare Turnstile berhasil disimpan ke web/.env dan cache konfigurasi telah dibersihkan.'),
        ];
    }

    public function resetAdminPassword(int $targetUserId, string $newPassword): array
    {
        $admin = User::where('id', $targetUserId)->where('role', 'admin')->first();
        if (!$admin) {
            return [
                'ok' => false,
                'message' => __('Akun admin target tidak ditemukan.'),
            ];
        }

        $admin->password = Hash::make($newPassword);
        $admin->has_custom_password = true;
        $admin->save();

        $this->recordAudit('vps_admin_password_reset', "Mereset password akun admin @{$admin->username} (ID #{$admin->id}) melalui Pusat Kesehatan Sistem.");

        return [
            'ok' => true,
            'message' => __('Password akun admin @:username berhasil diperbarui.', ['username' => $admin->username]),
        ];
    }

    public function updateEnvFile(string $path, array $pairs): bool
    {
        if (!file_exists($path) || !is_writable($path)) {
            return false;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return false;
        }

        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";

        foreach ($pairs as $key => $rawValue) {
            if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
                continue;
            }
            $value = str_replace(["\r", "\n"], '', (string) $rawValue);
            $formatted = preg_match('/\s/', $value) ? '"' . str_replace('"', '\"', $value) . '"' : $value;
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = (string) preg_replace($pattern, $key . '=' . $formatted, $content, 1);
            } else {
                $content = rtrim($content, "\r\n") . $eol . $key . '=' . $formatted . $eol;
            }
        }

        return file_put_contents($path, $content, LOCK_EX) !== false;
    }

    public function readEnvValue(string $path, string $key): ?string
    {
        if (!file_exists($path) || !is_readable($path)) {
            return null;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return null;
        }

        $prefix = $key . '=';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            if (str_starts_with($trimmed, $prefix)) {
                $val = trim(substr($trimmed, strlen($prefix)));
                return trim($val, "\"'");
            }
        }

        return null;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = (int) floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    protected function gitCmd(array $args): array
    {
        return array_merge(['git', '-c', 'safe.directory=' . $this->projectRoot()], $args);
    }

    protected function detectBridgeStatus(): array
    {
        $isLinux = $this->isLinux();
        $script = $this->bridgeScriptPath();
        $exists = file_exists($script);
        $available = false;
        $reason = '';

        if ($isLinux && $exists && !app()->runningUnitTests()) {
            $res = $this->runBridge(['ping'], 4);
            $available = $res['ok'] && str_contains($res['output'], 'BRIDGE_OK');
            if (!$available) {
                $reason = $res['output'] ?: __('Izin sudoers belum dipasang untuk user web server.');
            }
        } elseif ($isLinux && !$exists) {
            $reason = __('Skrip ops/web_ops_bridge.sh tidak ditemukan.');
        }

        return [
            'is_linux' => $isLinux,
            'script_exists' => $exists,
            'available' => $available,
            'reason' => $reason,
            'install_command' => 'sudo bash ' . $this->projectRoot() . '/ops/install_web_sudoers.sh',
        ];
    }

    protected function runBridge(array $args, int $timeout = 15): array
    {
        if (!$this->isLinux()) {
            return ['ok' => false, 'output' => 'Bridge hanya tersedia di Linux VPS.'];
        }

        $script = $this->bridgeScriptPath();
        if (!file_exists($script)) {
            return ['ok' => false, 'output' => 'File ops/web_ops_bridge.sh tidak ditemukan.'];
        }

        try {
            $sudoBin = is_executable('/usr/bin/sudo') ? '/usr/bin/sudo' : 'sudo';
            $env = ['PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:' . (getenv('PATH') ?: '')];
            $cmd = array_merge([$sudoBin, '-n', $script], $args);
            $proc = Process::env($env)->timeout($timeout)->run($cmd);
            return [
                'ok' => $proc->successful(),
                'output' => trim($proc->output() ?: $proc->errorOutput()),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }

    protected function collectServicesStatus(bool $bridgeAvailable): array
    {
        $result = [];
        foreach (self::SERVICE_CATALOG as $key => $meta) {
            $result[$key] = array_merge($meta, [
                'key' => $key,
                'state' => $this->isLinux() ? 'unknown' : 'local',
                'substate' => '',
                'enabled' => '-',
            ]);
        }

        if (!$this->isLinux() || app()->runningUnitTests()) {
            return $result;
        }

        if ($bridgeAvailable) {
            $res = $this->runBridge(['service-status'], 8);
            if ($res['ok'] && $res['output'] !== '') {
                foreach (explode("\n", $res['output']) as $line) {
                    $parts = explode('|', trim($line));
                    if (count($parts) >= 5 && isset($result[$parts[0]])) {
                        $result[$parts[0]]['unit'] = $parts[1];
                        $result[$parts[0]]['state'] = $parts[2];
                        $result[$parts[0]]['substate'] = $parts[3];
                        $result[$parts[0]]['enabled'] = $parts[4];
                    }
                }
                return $result;
            }
        }

        // Read-only fallback via systemctl without sudo
        foreach ($result as $key => &$svc) {
            try {
                $unit = $svc['unit'];
                if ($key === 'php-fpm') {
                    $unit = 'php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-fpm.service';
                    $svc['unit'] = $unit;
                }
                $proc = Process::timeout(2)->run(['systemctl', 'is-active', $unit]);
                $out = trim($proc->output());
                $svc['state'] = $out !== '' ? $out : 'unknown';
            } catch (\Throwable $e) {
            }
        }
        unset($svc);

        return $result;
    }

    protected function collectServerTelemetry(): array
    {
        $osName = php_uname('s') . ' ' . php_uname('r');
        $hostname = gethostname() ?: 'server';
        $cpuCores = 1;
        $loadAvg = null;
        $ramTotalBytes = 0;
        $ramUsedBytes = 0;
        $ramPercent = 0.0;
        $swapTotalBytes = 0;
        $swapUsedBytes = 0;
        $uptimeLabel = '-';

        if ($this->isLinux()) {
            if (function_exists('sys_getloadavg')) {
                $loads = @sys_getloadavg();
                if (is_array($loads) && count($loads) >= 3) {
                    $loadAvg = [
                        round((float) $loads[0], 2),
                        round((float) $loads[1], 2),
                        round((float) $loads[2], 2),
                    ];
                }
            }

            if (is_readable('/proc/cpuinfo')) {
                $cpuInfo = (string) @file_get_contents('/proc/cpuinfo');
                $matches = preg_match_all('/^processor\s*:/m', $cpuInfo);
                if ($matches > 0) {
                    $cpuCores = $matches;
                }
            }

            if (is_readable('/proc/meminfo')) {
                $memInfo = (string) @file_get_contents('/proc/meminfo');
                $mem = [];
                foreach (explode("\n", $memInfo) as $line) {
                    if (preg_match('/^(\w+):\s+(\d+)\s+kB/i', $line, $m)) {
                        $mem[$m[1]] = (int) $m[2] * 1024;
                    }
                }
                $ramTotalBytes = $mem['MemTotal'] ?? 0;
                $ramAvail = $mem['MemAvailable'] ?? (($mem['MemFree'] ?? 0) + ($mem['Buffers'] ?? 0) + ($mem['Cached'] ?? 0));
                $ramUsedBytes = max(0, $ramTotalBytes - $ramAvail);
                $ramPercent = $ramTotalBytes > 0 ? round(($ramUsedBytes / $ramTotalBytes) * 100, 1) : 0.0;

                $swapTotalBytes = $mem['SwapTotal'] ?? 0;
                $swapFree = $mem['SwapFree'] ?? 0;
                $swapUsedBytes = max(0, $swapTotalBytes - $swapFree);
            }

            if (is_readable('/proc/uptime')) {
                $uptimeRaw = trim((string) @file_get_contents('/proc/uptime'));
                $secs = (int) floor((float) explode(' ', $uptimeRaw)[0]);
                $uptimeLabel = $this->formatUptime($secs);
            }
        } else {
            // Safe lightweight Windows/local dev telemetry
            $ramUsedBytes = memory_get_usage(true);
            $uptimeLabel = __('Mode Pengembangan Lokal');
        }

        return [
            'os' => $osName,
            'hostname' => $hostname,
            'is_linux' => $this->isLinux(),
            'cpu_cores' => $cpuCores,
            'load_avg' => $loadAvg,
            'load_label' => $loadAvg ? implode(' / ', $loadAvg) : '-',
            'ram_total_label' => $ramTotalBytes > 0 ? $this->formatBytes($ramTotalBytes) : '-',
            'ram_used_label' => $this->formatBytes($ramUsedBytes),
            'ram_percent' => $ramPercent,
            'swap_total_label' => $swapTotalBytes > 0 ? $this->formatBytes($swapTotalBytes) : __('Tidak aktif'),
            'swap_used_label' => $this->formatBytes($swapUsedBytes),
            'uptime_label' => $uptimeLabel,
            'php_memory_usage' => $this->formatBytes(memory_get_usage(true)),
        ];
    }

    protected function checkBotMigrationPrerequisites(array $services): array
    {
        $rootEnv = $this->rootEnvPath();
        $webEnv = $this->webEnvPath();

        $disableCommands = strtolower((string) ($this->readEnvValue($rootEnv, 'DISABLE_BOT_COMMANDS') ?? 'false')) === 'true';
        $websiteEnabled = strtolower((string) ($this->readEnvValue($rootEnv, 'WEBSITE_ENABLED') ?? 'false')) === 'true';
        $websiteDomain = trim((string) ($this->readEnvValue($rootEnv, 'WEBSITE_DOMAIN') ?? ''));
        $cleanDomain = preg_replace('#^https?://#i', '', $websiteDomain);
        $cleanDomain = explode('/', (string) $cleanDomain)[0];

        $checks = [
            [
                'label' => __('File Laravel & web/.env tersedia'),
                'ok' => file_exists(base_path('artisan')) && file_exists($webEnv),
                'detail' => file_exists($webEnv) ? 'web/.env ditemukan' : 'web/.env tidak ditemukan',
            ],
            [
                'label' => __('WEBSITE_ENABLED=true di root .env'),
                'ok' => $websiteEnabled,
                'detail' => $websiteEnabled ? 'WEBSITE_ENABLED=true' : __('Belum aktif di root .env'),
            ],
            [
                'label' => __('WEBSITE_DOMAIN valid untuk Mini WebApp'),
                'ok' => $cleanDomain !== '',
                'detail' => $cleanDomain !== '' ? "https://{$cleanDomain}/dashboard" : __('WEBSITE_DOMAIN belum diatur'),
            ],
        ];

        if ($this->isLinux() && !app()->runningUnitTests()) {
            $nginxOk = ($services['nginx']['state'] ?? '') === 'active';
            $fpmOk = ($services['php-fpm']['state'] ?? '') === 'active';
            $checks[] = [
                'label' => __('Nginx & PHP-FPM berjalan normal'),
                'ok' => $nginxOk && $fpmOk,
                'detail' => 'Nginx: ' . ($services['nginx']['state'] ?? 'unknown') . ' · PHP-FPM: ' . ($services['php-fpm']['state'] ?? 'unknown'),
            ];
        }

        $failedCount = count(array_filter($checks, fn ($c) => !$c['ok']));

        return [
            'disabled' => $disableCommands,
            'website_enabled' => $websiteEnabled,
            'domain' => $cleanDomain,
            'root_env_writable' => file_exists($rootEnv) && is_writable($rootEnv),
            'checks' => $checks,
            'failed_count' => $failedCount,
            'ready' => $failedCount === 0,
        ];
    }

    protected function collectGitInfo(): array
    {
        $root = $this->projectRoot();
        $lastCommitFile = $this->opsBackupDir() . DIRECTORY_SEPARATOR . '.last_commit';
        $rollbackCommit = file_exists($lastCommitFile) ? trim((string) @file_get_contents($lastCommitFile)) : '';

        $info = [
            'available' => false,
            'branch' => '-',
            'commit_short' => '-',
            'commit_full' => '',
            'commit_subject' => '-',
            'commit_date' => '-',
            'is_dirty' => false,
            'rollback_commit' => $rollbackCommit,
            'rollback_short' => $rollbackCommit !== '' ? substr($rollbackCommit, 0, 7) : '',
        ];

        if (!is_dir($root . DIRECTORY_SEPARATOR . '.git') || app()->runningUnitTests()) {
            return $info;
        }

        try {
            $env = ['PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:' . (getenv('PATH') ?: '')];
            $branch = trim(Process::path($root)->env($env)->timeout(3)->run($this->gitCmd(['symbolic-ref', '--quiet', '--short', 'HEAD']))->output());
            $logLine = trim(Process::path($root)->env($env)->timeout(3)->run($this->gitCmd(['log', '-1', '--format=%H|%h|%s|%ci']))->output());
            $status = trim(Process::path($root)->env($env)->timeout(3)->run($this->gitCmd(['status', '--porcelain']))->output());

            if ($logLine !== '') {
                $parts = explode('|', $logLine, 4);
                $info['available'] = true;
                $info['branch'] = $branch ?: 'HEAD';
                $info['commit_full'] = $parts[0] ?? '';
                $info['commit_short'] = $parts[1] ?? '-';
                $info['commit_subject'] = $parts[2] ?? '-';
                $info['commit_date'] = $parts[3] ?? '-';
                $info['is_dirty'] = $status !== '';
            }
        } catch (\Throwable $e) {
        }

        return $info;
    }

    protected function collectOpsBackupsSummary(): array
    {
        $dir = $this->opsBackupDir();
        if (!is_dir($dir)) {
            return [
                'count' => 0,
                'total_size_label' => '0 B',
                'latest_name' => '-',
                'files' => [],
            ];
        }

        $items = [];
        $totalBytes = 0;
        foreach (['*.sqlite3', '*.tar.gz', '*.patch'] as $pattern) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . $pattern) ?: [] as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $size = (int) (@filesize($file) ?: 0);
                $mtime = (int) (@filemtime($file) ?: 0);
                $totalBytes += $size;
                $items[] = [
                    'name' => basename($file),
                    'size' => $size,
                    'size_label' => $this->formatBytes($size),
                    'modified_at' => date('d M Y H:i', $mtime),
                    'mtime' => $mtime,
                ];
            }
        }

        usort($items, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return [
            'count' => count($items),
            'total_size_label' => $this->formatBytes($totalBytes),
            'latest_name' => $items[0]['name'] ?? '-',
            'files' => array_slice($items, 0, 8),
        ];
    }

    protected function evaluateHealth(array $metrics, array $phpExtensions, array $writableChecks): array
    {
        $score = 100;
        $checks = [];

        if (!$metrics['db_connected']) {
            $score -= 40;
            $checks[] = ['status' => 'danger', 'title' => __('Koneksi Database Terputus'), 'desc' => __('Periksa service MySQL/MariaDB dan konfigurasi koneksi di web/.env.')];
        } else {
            $checks[] = ['status' => 'success', 'title' => __('Database Online & Responsif'), 'desc' => __('Latensi kueri :ms ms dengan :n tabel (:size).', ['ms' => $metrics['db_latency_ms'], 'n' => $metrics['db_table_count'], 'size' => $metrics['db_size_label']])];
        }

        if ($metrics['disk_used_percent'] >= 90) {
            $score -= 25;
            $checks[] = ['status' => 'danger', 'title' => __('Penyimpanan Disk Kritis (:pct%)', ['pct' => $metrics['disk_used_percent']]), 'desc' => __('Tersisa :free. Segera kosongkan log Laravel dan prune backup lama di tab Pemeliharaan.', ['free' => $metrics['disk_free_label']])];
        } elseif ($metrics['disk_used_percent'] >= 80) {
            $score -= 10;
            $checks[] = ['status' => 'warning', 'title' => __('Penyimpanan Disk Menipis (:pct%)', ['pct' => $metrics['disk_used_percent']]), 'desc' => __('Tersisa :free. Pertimbangkan membersihkan arsip backup lama.', ['free' => $metrics['disk_free_label']])];
        } else {
            $checks[] = ['status' => 'success', 'title' => __('Kapasitas Disk Sehat (:pct%)', ['pct' => $metrics['disk_used_percent']]), 'desc' => __('Ruang tersisa :free dari total :total.', ['free' => $metrics['disk_free_label'], 'total' => $metrics['disk_total_label']])];
        }

        if ($metrics['app_debug']) {
            $score -= 15;
            $checks[] = ['status' => 'warning', 'title' => __('Mode Debug (APP_DEBUG=ON) Aktif'), 'desc' => __('Di lingkungan produksi sebaiknya matikan APP_DEBUG=false agar stack trace tidak terekspos.')];
        } else {
            $checks[] = ['status' => 'success', 'title' => __('Mode Debug Nonaktif (Aman)'), 'desc' => __('Error internal tidak menampilkan detail kode ke pengunjung.')];
        }

        if (!$metrics['vite_bundle_built']) {
            $score -= 5;
            $checks[] = ['status' => 'warning', 'title' => __('Asset Bundle Vite Menggunakan Fallback CDN'), 'desc' => __('Jalankan "npm run build" di direktori web agar aset dimuat lokal lebih cepat.')];
        }

        if ($metrics['expired_stuck_orders'] > 0 || $metrics['paid_stuck_orders'] > 0 || $metrics['failed_jobs'] > 0) {
            $score -= 10;
            $checks[] = ['status' => 'warning', 'title' => __('Ada Antrean / Order Perlu Diproses'), 'desc' => __(':exp order expired, :paid order paid tertahan, :fail failed jobs. Gunakan tombol di tab Pemeliharaan.', [
                'exp' => $metrics['expired_stuck_orders'],
                'paid' => $metrics['paid_stuck_orders'],
                'fail' => $metrics['failed_jobs'],
            ])];
        } else {
            $checks[] = ['status' => 'success', 'title' => __('Scheduler & Antrean Bersih'), 'desc' => __('Tidak ada pesanan tertahan maupun failed jobs.')];
        }

        $missingExts = array_keys(array_filter($phpExtensions, fn ($loaded) => !$loaded));
        if (!empty($missingExts)) {
            $score -= 10;
            $checks[] = ['status' => 'danger', 'title' => __('Ekstensi PHP Belum Terpasang'), 'desc' => implode(', ', $missingExts)];
        }

        $unwritable = array_keys(array_filter($writableChecks, fn ($ok) => !$ok));
        if (!empty($unwritable)) {
            $score -= 5;
            $checks[] = ['status' => 'warning', 'title' => __('Izin Tulis Direktori/File Terbatas'), 'desc' => implode(', ', $unwritable)];
        }

        $score = max(10, min(100, $score));
        if ($score >= 80) {
            $status = __('Sistem Sehat');
            $color = 'success';
        } elseif ($score >= 55) {
            $status = __('Perlu Perhatian');
            $color = 'warning';
        } else {
            $status = __('Kondisi Kritis');
            $color = 'danger';
        }

        return [$score, $status, $color, $checks];
    }

    protected function getDirectorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }
        $total = 0;
        $counted = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total += (int) $file->getSize();
                    $counted++;
                    if ($counted > 5000) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
        }
        return $total;
    }

    protected function tailFile(string $filepath, int $lines = 100): string
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return '';
        }

        $size = (int) (@filesize($filepath) ?: 0);
        if ($size === 0) {
            return '';
        }

        // Read at most the last 256 KB to guarantee fast response even on huge log files
        $maxRead = 262144;
        $fp = @fopen($filepath, 'rb');
        if (!$fp) {
            return '';
        }

        if ($size > $maxRead) {
            fseek($fp, -$maxRead, SEEK_END);
        }
        $chunk = (string) fread($fp, min($size, $maxRead));
        fclose($fp);

        $chunk = str_replace(["\r\n", "\r"], "\n", $chunk);
        $allLines = explode("\n", trim($chunk, "\n"));
        $slice = array_slice($allLines, -$lines);

        return implode("\n", $slice);
    }

    protected function formatUptime(int $seconds): string
    {
        if ($seconds <= 0) {
            return '-';
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $mins = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . 'd';
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = $hours . 'h';
        }
        $parts[] = $mins . 'm';

        return implode(' ', $parts);
    }

    protected function maskSecret(string $value): string
    {
        $len = strlen($value);
        if ($len === 0) {
            return '';
        }
        if ($len <= 8) {
            return str_repeat('•', $len);
        }
        return substr($value, 0, 4) . str_repeat('•', min(12, $len - 8)) . substr($value, -4);
    }

    protected function recordAudit(string $action, string $detail): void
    {
        try {
            if (Schema::hasTable('audit_logs')) {
                AuditLog::create([
                    'actor_id' => Auth::id(),
                    'action' => $action,
                    'entity_type' => 'system',
                    'entity_id' => null,
                    'detail' => $detail,
                ]);
            }
        } catch (\Throwable $e) {
        }
    }
}
