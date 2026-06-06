<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Services\BackupService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:auto-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically backup SQLite database and media files, and send to Telegram Admin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $enabled = BotSetting::where('key', 'auto_backup_enabled')->value('value');
        if ($enabled !== '1') {
            $this->info("Auto backup is disabled.");
            return 0;
        }

        $schedule = BotSetting::where('key', 'auto_backup_schedule')->value('value') ?: 'daily';
        $lastRun = BotSetting::where('key', 'auto_backup_last_run')->value('value');

        $shouldRun = false;
        $now = Carbon::now();

        if (!$lastRun) {
            $shouldRun = true;
        } else {
            $lastRunTime = Carbon::parse($lastRun);
            if ($schedule === 'daily' && $now->diffInHours($lastRunTime) >= 23) {
                $shouldRun = true;
            } elseif ($schedule === 'weekly' && $now->diffInDays($lastRunTime) >= 7) {
                $shouldRun = true;
            } elseif ($schedule === 'monthly' && $now->diffInDays($lastRunTime) >= 30) {
                $shouldRun = true;
            }
        }

        if (!$shouldRun) {
            $this->info("Backup schedule ({$schedule}) condition is not met yet. Last run: {$lastRun}");
            return 0;
        }

        $this->info("Starting auto backup...");

        try {
            $zipPath = BackupService::createSnapshot();
            $filename = basename($zipPath);

            $caption = "<b>💾 Auto Backup Terjadwal Selesai</b>\n\n"
                     . "📅 Tanggal: " . $now->format('Y-m-d H:i:s') . " WIB\n"
                     . "📦 Tipe: Snapshot (DB + Media)\n"
                     . "⏱️ Jadwal: " . ucfirst($schedule) . "\n\n"
                     . "<i>File backup ini dikirimkan secara otomatis dari sistem website.</i>";

            $sent = TelegramService::sendBackupFile($zipPath, $caption);

            // Save last run timestamp
            BotSetting::updateOrCreate(
                ['key' => 'auto_backup_last_run'],
                ['value' => $now->toIso8601String(), 'updated_at' => $now->format('Y-m-d H:i:s')]
            );

            // Log Audit
            AuditLog::create([
                'actor_id' => null, // null represents system/cron
                'action' => 'system_auto_backup',
                'entity_type' => 'backup',
                'entity_id' => 0,
                'detail' => "Auto backup successful. schedule={$schedule}; file={$filename}; telegram_sent=" . ($sent ? 'YES' : 'NO'),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Symfony/Console',
            ]);

            $this->info("Auto backup completed successfully. Sent: " . ($sent ? 'YES' : 'NO'));
            return 0;
        } catch (\Exception $e) {
            Log::error("Auto backup command failed: " . $e->getMessage());
            $this->error("Auto backup failed: " . $e->getMessage());
            return 1;
        }
    }
}
