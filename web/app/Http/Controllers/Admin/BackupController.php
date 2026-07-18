<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class BackupController extends Controller
{
    /**
     * Display the backup and restore dashboard.
     */
    /**
     * Display the backup and restore dashboard.
     */
    public function index()
    {
        // 1. Database Stats
        $dbSize = 0;
        $dbConnection = config('database.default');

        if ($dbConnection === 'sqlite') {
            $dbPath = BackupService::getDatabasePath();
            $dbSize = File::exists($dbPath) ? File::size($dbPath) : 0;
        } elseif ($dbConnection === 'mysql') {
            try {
                $dbName = config('database.connections.mysql.database');
                $result = DB::select("SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = ?", [$dbName]);
                $dbSize = !empty($result) ? ($result[0]->size ?? 0) : 0;
            } catch (\Exception $e) {
                // Fallback
            }
        }
        $dbSizeFormatted = $this->formatBytes($dbSize);

        $totalRecords = 0;
        $tables = ['users', 'products', 'stock_units', 'orders', 'order_items', 'payments', 'complaint_cases', 'bot_settings', 'audit_logs'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $totalRecords += DB::table($table)->count();
            }
        }

        // 2. Settings Status
        $autoBackupEnabled = BotSetting::where('key', 'auto_backup_enabled')->value('value') ?: '0';

        // 3. Audit Logs
        $logs = AuditLog::with('actor')
            ->whereIn('action', ['backup_create', 'backup_restore', 'backup_delete', 'system_auto_backup'])
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        return view('admin.backup.index', compact(
            'dbSizeFormatted',
            'totalRecords',
            'autoBackupEnabled',
            'logs'
        ));
    }

    /**
     * Show restore database form.
     */
    public function showRestore()
    {
        return view('admin.backup.restore');
    }

    /**
     * Show backup settings.
     */
    public function showSettings()
    {
        $autoBackupEnabled = BotSetting::where('key', 'auto_backup_enabled')->value('value') ?: '0';
        $autoBackupSchedule = BotSetting::where('key', 'auto_backup_schedule')->value('value') ?: 'daily';
        $autoBackupLastRun = BotSetting::where('key', 'auto_backup_last_run')->value('value') ?: '-';

        $tablesStats = [];
        $tables = [
            'users' => 'Pengguna/Pelanggan',
            'products' => 'Produk',
            'stock_units' => 'Stok Akun',
            'orders' => 'Pesanan',
            'order_items' => 'Item Pesanan',
            'payments' => 'Pembayaran',
            'complaint_cases' => 'Kasus Komplain',
            'bot_settings' => 'Pengaturan Bot',
            'audit_logs' => 'Log Audit System',
        ];

        foreach ($tables as $table => $label) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $tablesStats[] = [
                    'table' => $table,
                    'label' => $label,
                    'count' => $count
                ];
            }
        }

        return view('admin.backup.settings', compact(
            'autoBackupEnabled',
            'autoBackupSchedule',
            'autoBackupLastRun',
            'tablesStats'
        ));
    }

    /**
     * Show backup history list.
     */
    public function history()
    {
        $history = BackupService::getBackupHistory();
        return view('admin.backup.history', compact('history'));
    }

    /**
     * Download or generate backup files.
     */
    public function download($param)
    {
        if ($param === 'snapshot') {
            try {
                $path = BackupService::createSnapshot();
                
                AuditLog::create([
                    'actor_id' => auth()->id(),
                    'action' => 'backup_create',
                    'entity_type' => 'backup',
                    'entity_id' => 0,
                    'detail' => 'Manual snapshot backup generated: ' . basename($path),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return response()->download($path);
            } catch (\Exception $e) {
                return back()->with('error', 'Gagal membuat snapshot backup: ' . $e->getMessage());
            }
        } elseif ($param === 'json') {
            try {
                $path = BackupService::createJsonBackup();

                AuditLog::create([
                    'actor_id' => auth()->id(),
                    'action' => 'backup_create',
                    'entity_type' => 'backup',
                    'entity_id' => 0,
                    'detail' => 'Manual JSON backup generated: ' . basename($path),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return response()->download($path);
            } catch (\Exception $e) {
                return back()->with('error', 'Gagal membuat JSON backup: ' . $e->getMessage());
            }
        } else {
            // Download file from history
            $filePath = storage_path('app/backups/' . $param);
            if (File::exists($filePath)) {
                // Secure path traversal check
                $realBase = realpath(storage_path('app/backups'));
                $realFile = realpath($filePath);
                
                if ($realBase && $realFile && strpos($realFile, $realBase) === 0) {
                    return response()->download($filePath);
                }
            }
            return back()->with('error', __('File backup tidak ditemukan atau tidak valid.'));
        }
    }

    public function restore(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:zip|max:51200', // max 50MB
            'mode' => 'required|in:overwrite,merge'
        ], [
            'backup_file.required' => 'File backup wajib diunggah.',
            'backup_file.mimes' => 'Format file harus berupa ZIP.',
            'backup_file.max' => 'Ukuran file backup maksimal 50 MB.',
        ]);

        $file = $request->file('backup_file');
        $filename = $file->getClientOriginalName();

        // Save file to temporary path
        $tempPath = $file->storeAs('temp', 'restore_' . time() . '.zip');
        $fileBase = basename($tempPath);

        // 1. Capture active user and session for preservation
        $adminData = null;
        $currentAdmin = auth()->user();
        if ($currentAdmin) {
            $adminRow = DB::table('users')->where('id', $currentAdmin->id)->first();
            if ($adminRow) {
                $adminData = (array)$adminRow;
            }
        }
        $sessionData = null;
        if (config('session.driver') === 'database') {
            $sessionData = (array)DB::table('sessions')->where('id', session()->getId())->first();
        }
        File::put(storage_path('app/restore_preserve.json'), json_encode([
            'admin' => $adminData,
            'session' => $sessionData
        ]));

        // 2. Initialize restore_status.json
        $statusFile = storage_path('app/restore_status.json');
        File::put($statusFile, json_encode([
            'status' => 'processing',
            'percent' => 5,
            'message' => 'Menghubungkan ke proses restore...',
            'error' => null,
            'logs' => [
                '['.date('H:i:s').'] Menghubungkan ke proses restore...'
            ]
        ]));

        // 3. Start restore in background (or synchronously in testing)
        if (app()->runningUnitTests()) {
            \Illuminate\Support\Facades\Artisan::call('restore:run', [
                'file' => $fileBase,
                'mode' => $request->mode,
                'filename' => $filename
            ]);
        } else {
            $artisan = base_path('artisan');
            $phpFinder = new \Symfony\Component\Process\PhpExecutableFinder();
            $php = $phpFinder->find(false) ?: 'php';

            if (PHP_OS_FAMILY === 'Windows') {
                pclose(popen("start /B " . escapeshellarg($php) . " " . escapeshellarg($artisan) . " restore:run " . escapeshellarg($fileBase) . " " . escapeshellarg($request->mode) . " " . escapeshellarg($filename) . " > NUL 2>&1", "r"));
            } else {
                exec(escapeshellarg($php) . " " . escapeshellarg($artisan) . " restore:run " . escapeshellarg($fileBase) . " " . escapeshellarg($request->mode) . " " . escapeshellarg($filename) . " > /dev/null 2>&1 &");
            }
        }

        return response()->json([
            'success' => true,
            'redirect_url' => route('admin.backup.restore.progress', [
                'file' => $fileBase,
                'filename' => $filename,
                'mode' => $request->mode
            ])
        ]);
    }

    /**
     * Display live restore progress page.
     */
    public function restoreProgress(Request $request)
    {
        $file = $request->query('file');
        $filename = $request->query('filename');
        $mode = $request->query('mode', 'overwrite');

        return view('admin.backup.restore_progress', compact('file', 'filename', 'mode'));
    }

    public function runRestore(Request $request)
    {
        return response()->json(['success' => true]);
    }

    public function restoreStatus()
    {
        $statusFile = storage_path('app/restore_status.json');
        if (File::exists($statusFile)) {
            $data = json_decode(File::get($statusFile), true);
            return response()->json($data);
        }
        return response()->json([
            'status' => 'pending',
            'percent' => 0,
            'message' => 'Menunggu proses restore...',
            'logs' => []
        ]);
    }

    public function wipeProgress()
    {
        return view('admin.backup.wipe_progress');
    }

    public function runWipe()
    {
        $currentUserId = auth()->id();
        $currentSessionId = session()->getId();

        $statusFile = storage_path('app/wipe_status.json');
        File::put($statusFile, json_encode([
            'status' => 'processing',
            'percent' => 5,
            'message' => 'Menghubungkan ke proses pembersihan data...',
            'error' => null,
            'logs' => [
                '['.date('H:i:s').'] Menghubungkan ke proses pembersihan data...'
            ]
        ]));

        if (app()->runningUnitTests()) {
            \Illuminate\Support\Facades\Artisan::call('app:wipe-data', [
                'current_user_id' => $currentUserId,
                'current_session_id' => $currentSessionId
            ]);
        } else {
            $artisan = base_path('artisan');
            $phpFinder = new \Symfony\Component\Process\PhpExecutableFinder();
            $php = $phpFinder->find(false) ?: 'php';

            if (PHP_OS_FAMILY === 'Windows') {
                pclose(popen("start /B " . escapeshellarg($php) . " " . escapeshellarg($artisan) . " app:wipe-data " . escapeshellarg($currentUserId) . " " . escapeshellarg($currentSessionId) . " > NUL 2>&1", "r"));
            } else {
                exec(escapeshellarg($php) . " " . escapeshellarg($artisan) . " app:wipe-data " . escapeshellarg($currentUserId) . " " . escapeshellarg($currentSessionId) . " > /dev/null 2>&1 &");
            }
        }

        return response()->json([
            'success' => true,
            'redirect_url' => route('admin.backup.wipe.progress')
        ]);
    }

    public function wipeStatus()
    {
        $statusFile = storage_path('app/wipe_status.json');
        if (File::exists($statusFile)) {
            $data = json_decode(File::get($statusFile), true);
            return response()->json($data);
        }
        return response()->json([
            'status' => 'pending',
            'percent' => 0,
            'message' => 'Menunggu proses pembersihan data...',
            'logs' => []
        ]);
    }

    /**
     * Delete backup file from history.
     */
    public function destroy($filename)
    {
        $filePath = storage_path('app/backups/' . $filename);
        if (File::exists($filePath)) {
            // Secure path traversal check
            $realBase = realpath(storage_path('app/backups'));
            $realFile = realpath($filePath);

            if ($realBase && $realFile && strpos($realFile, $realBase) === 0) {
                File::delete($filePath);

                AuditLog::create([
                    'actor_id' => auth()->id(),
                    'action' => 'backup_delete',
                    'entity_type' => 'backup',
                    'entity_id' => 0,
                    'detail' => 'Backup file deleted: ' . $filename,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return back()->with('success', __('File backup berhasil dihapus.'));
            }
        }

        return back()->with('error', __('Gagal menghapus file backup.'));
    }

    /**
     * Update auto-backup configuration.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'auto_backup_enabled' => 'nullable|in:1,0',
            'auto_backup_schedule' => 'required|in:daily,weekly,monthly'
        ]);

        $enabled = $request->input('auto_backup_enabled', '0');
        $schedule = $request->input('auto_backup_schedule');

        BotSetting::updateOrCreate(
            ['key' => 'auto_backup_enabled'],
            ['value' => $enabled, 'updated_at' => date('Y-m-d H:i:s')]
        );

        BotSetting::updateOrCreate(
            ['key' => 'auto_backup_schedule'],
            ['value' => $schedule, 'updated_at' => date('Y-m-d H:i:s')]
        );

        // Audit log
        AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => 'backup_settings_update',
            'entity_type' => 'settings',
            'entity_id' => 0,
            'detail' => "Auto backup settings updated. enabled={$enabled}; schedule={$schedule}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', __('Pengaturan auto-backup berhasil diperbarui.'));
    }

    /**
     * Helper to format file sizes.
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
