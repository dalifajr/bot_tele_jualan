<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WipeDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:wipe-data {current_user_id} {current_session_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wipe all database transactional data while preserving admin user and session';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentUserId = $this->argument('current_user_id');
        $currentSessionId = $this->argument('current_session_id');

        $statusFile = storage_path('app/wipe_status.json');

        $this->updateStatus($statusFile, 10, 'Mengambil daftar tabel database...', 'processing');

        $tables = [];
        try {
            if (method_exists(Schema::class, 'getTables')) {
                $tableInfos = Schema::getTables();
                foreach ($tableInfos as $info) {
                    $name = is_array($info) ? ($info['name'] ?? null) : ($info->name ?? null);
                    if ($name && !str_starts_with($name, 'sqlite_') && !str_starts_with($name, 'migrations')) {
                        $tables[] = $name;
                    }
                }
            }
        } catch (\Exception $e) {
            // fallback
        }

        if (empty($tables)) {
            $tables = ['users', 'products', 'stock_units', 'orders', 'order_items', 'payments', 'held_funds', 'restock_subscriptions', 'complaint_cases', 'complaint_attachments', 'bot_settings', 'audit_logs', 'sessions', 'cache', 'chat_messages', 'cart_items', 'visitors', 'coupons', 'withdrawal_requests', 'broadcast_jobs'];
        }

        $this->updateStatus($statusFile, 20, 'Menonaktifkan foreign key constraints...', 'processing');
        Schema::disableForeignKeyConstraints();

        try {
            $total = count($tables);
            foreach ($tables as $index => $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                $percent = 20 + (int)(($index / $total) * 70);
                
                if ($table === 'users') {
                    $this->updateStatus($statusFile, $percent, "Membersihkan tabel: {$table} (mempertahankan admin & seller)...", 'processing');
                    DB::table('users')->whereNotIn('role', ['admin', 'seller'])->where('id', '!=', $currentUserId)->delete();
                } elseif ($table === 'sessions') {
                    $this->updateStatus($statusFile, $percent, "Membersihkan tabel: {$table} (mempertahankan sesi aktif)...", 'processing');
                    DB::table('sessions')->where('id', '!=', $currentSessionId)->delete();
                } elseif ($table === 'bot_settings') {
                    $this->updateStatus($statusFile, $percent, "Mengabaikan tabel konfigurasi: {$table}...", 'processing');
                } else {
                    $this->updateStatus($statusFile, $percent, "Mengosongkan tabel: {$table}...", 'processing');
                    DB::table($table)->delete();
                }
                
                usleep(100000); // 100ms delay for smooth UI feedback
            }

            $this->updateStatus($statusFile, 95, 'Mengaktifkan kembali foreign key constraints...', 'processing');
            Schema::enableForeignKeyConstraints();

            // Create audit log
            \App\Models\AuditLog::create([
                'actor_id' => $currentUserId,
                'action' => 'backup_restore',
                'entity_type' => 'system',
                'entity_id' => 0,
                'detail' => 'Database successfully wiped/cleared from Admin Panel (except configuration and admins)',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Console Command',
            ]);

            $this->updateStatus($statusFile, 100, 'Pembersihan database selesai dengan sukses!', 'completed');
            $this->info("Database wiped successfully.");
        } catch (\Exception $e) {
            Schema::enableForeignKeyConstraints();
            $this->updateStatus($statusFile, -1, 'ERROR: ' . $e->getMessage(), 'failed');
            Log::error("Wipe failed: " . $e->getMessage());
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
}
