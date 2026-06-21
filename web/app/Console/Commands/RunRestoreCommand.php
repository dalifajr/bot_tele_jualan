<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RunRestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'restore:run {file} {mode} {filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs the database restore in the background';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');
        $mode = $this->argument('mode');
        $filename = $this->argument('filename');

        $statusFile = storage_path('app/restore_status.json');
        
        $relativePath = 'temp/' . $file;
        $fullPath = \Illuminate\Support\Facades\Storage::path($relativePath);

        if (!File::exists($fullPath)) {
            $this->updateStatus($statusFile, -1, 'File backup tidak ditemukan atau tidak valid.', 'failed');
            return 1;
        }

        $this->updateStatus($statusFile, 10, 'Memulai proses restore...', 'processing');

        try {
            BackupService::restore($fullPath, $mode, function ($percent, $message) use ($statusFile) {
                $this->updateStatus($statusFile, $percent, $message, 'processing');
                usleep(100000); // Small sleep for frontend log readability
            });

            // Run database migrations to align schema with the codebase
            $this->updateStatus($statusFile, 92, 'Menjalankan migrasi database...', 'processing');
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

            // Re-insert preserved admin user & session
            $this->restorePreservedData($statusFile);

            // Audit log
            $actorId = null;
            $preservePath = storage_path('app/restore_preserve.json');
            if (File::exists($preservePath)) {
                $preserved = json_decode(File::get($preservePath), true);
                $actorId = $preserved['admin']['id'] ?? null;
            }

            \App\Models\AuditLog::create([
                'actor_id' => $actorId,
                'action' => 'backup_restore',
                'entity_type' => 'backup',
                'entity_id' => 0,
                'detail' => "Database restored in background from file: {$filename} (Mode: {$mode})",
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Console Command',
            ]);

            // Clean temp file
            if (\Illuminate\Support\Facades\Storage::exists($relativePath)) {
                \Illuminate\Support\Facades\Storage::delete($relativePath);
            }

            $this->updateStatus($statusFile, 100, 'Proses restore selesai dengan sukses!', 'completed');
            $this->info("Restore completed successfully.");
        } catch (\Exception $e) {
            $this->updateStatus($statusFile, -1, 'ERROR: ' . $e->getMessage(), 'failed');
            Log::error("Restore failed: " . $e->getMessage());
            
            // Clean temp file
            if (\Illuminate\Support\Facades\Storage::exists($relativePath)) {
                \Illuminate\Support\Facades\Storage::delete($relativePath);
            }
            return 1;
        }

        return 0;
    }

    protected function updateStatus($statusFile, $percent, $message, $status)
    {
        $data = [];
        if (File::exists($statusFile)) {
            $data = json_decode(File::get($statusFile), true) ?: [];
        }
        
        $data['status'] = $status;
        $data['percent'] = $percent;
        $data['message'] = $message;
        if (!isset($data['logs'])) {
            $data['logs'] = [];
        }
        $data['logs'][] = "[" . date('H:i:s') . "] " . $message;
        
        File::put($statusFile, json_encode($data));
    }

    protected function restorePreservedData($statusFile)
    {
        $preservePath = storage_path('app/restore_preserve.json');
        if (!File::exists($preservePath)) {
            return;
        }

        $this->updateStatus($statusFile, 95, 'Memulihkan sesi login admin...', 'processing');

        $preserved = json_decode(File::get($preservePath), true);
        
        if (!empty($preserved['admin'])) {
            $admin = $preserved['admin'];
            if (!DB::table('users')->where('id', $admin['id'])->exists()) {
                DB::table('users')->insert($admin);
            } else {
                DB::table('users')->where('id', $admin['id'])->update($admin);
            }
        }

        if (!empty($preserved['session'])) {
            $sess = $preserved['session'];
            if (!DB::table('sessions')->where('id', $sess['id'])->exists()) {
                DB::table('sessions')->insert($sess);
            } else {
                DB::table('sessions')->where('id', $sess['id'])->update($sess);
            }
        }

        @unlink($preservePath);
    }
}
