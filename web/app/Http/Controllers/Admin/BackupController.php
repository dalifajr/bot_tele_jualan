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
    public function index()
    {
        // 1. Database Stats
        $dbPath = BackupService::getDatabasePath();
        $dbSize = File::exists($dbPath) ? File::size($dbPath) : 0;
        $dbSizeFormatted = $this->formatBytes($dbSize);

        $tablesStats = [];
        $totalRecords = 0;

        // Count records in tables
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
                $totalRecords += $count;
            }
        }

        // 2. Settings
        $autoBackupEnabled = BotSetting::where('key', 'auto_backup_enabled')->value('value') ?: '0';
        $autoBackupSchedule = BotSetting::where('key', 'auto_backup_schedule')->value('value') ?: 'daily';
        $autoBackupLastRun = BotSetting::where('key', 'auto_backup_last_run')->value('value') ?: '-';

        // 3. History
        $history = BackupService::getBackupHistory();

        // 4. Audit Logs
        $logs = AuditLog::with('user')
            ->whereIn('action', ['backup_create', 'backup_restore', 'backup_delete', 'system_auto_backup'])
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        return view('admin.backup.index', compact(
            'dbSizeFormatted',
            'tablesStats',
            'totalRecords',
            'autoBackupEnabled',
            'autoBackupSchedule',
            'autoBackupLastRun',
            'history',
            'logs'
        ));
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
                    'user_id' => auth()->id(),
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
                    'user_id' => auth()->id(),
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
            return back()->with('error', 'File backup tidak ditemukan atau tidak valid.');
        }
    }

    /**
     * Restore database from uploaded ZIP backup.
     */
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
        $absoluteTempPath = storage_path('app/' . $tempPath);

        try {
            BackupService::restore($absoluteTempPath, $request->mode);

            // Audit log
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'backup_restore',
                'entity_type' => 'backup',
                'entity_id' => 0,
                'detail' => "Database restored from file: {$filename} (Mode: {$request->mode})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Clean temp file
            if (File::exists($absoluteTempPath)) {
                File::delete($absoluteTempPath);
            }

            return back()->with('success', 'Proses restore database berhasil diselesaikan. Seluruh data web & bot disinkronkan.');
        } catch (\Exception $e) {
            // Clean temp file
            if (File::exists($absoluteTempPath)) {
                File::delete($absoluteTempPath);
            }
            return back()->with('error', 'Restore gagal: ' . $e->getMessage());
        }
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
                    'user_id' => auth()->id(),
                    'action' => 'backup_delete',
                    'entity_type' => 'backup',
                    'entity_id' => 0,
                    'detail' => 'Backup file deleted: ' . $filename,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return back()->with('success', 'File backup berhasil dihapus.');
            }
        }

        return back()->with('error', 'Gagal menghapus file backup.');
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
            'user_id' => auth()->id(),
            'action' => 'backup_settings_update',
            'entity_type' => 'settings',
            'entity_id' => 0,
            'detail' => "Auto backup settings updated. enabled={$enabled}; schedule={$schedule}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Pengaturan auto-backup berhasil diperbarui.');
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
