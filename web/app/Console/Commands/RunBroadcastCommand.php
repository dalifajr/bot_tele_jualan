<?php

namespace App\Console\Commands;

use App\Models\BroadcastJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RunBroadcastCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcast:run {jobId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a broadcast job in the background';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jobId = $this->argument('jobId');
        $job = BroadcastJob::find($jobId);
        if (!$job) {
            $this->error("Job not found.");
            return 1;
        }

        $updated = BroadcastJob::where('id', $jobId)
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if (!$updated) {
            $this->info("Job already running or completed by another worker.");
            return 0;
        }

        $targets = User::whereNotNull('telegram_id')
            ->where('role', 'customer')
            ->pluck('telegram_id')
            ->toArray();

        $token = config('telegram.bot_token');
        if (!$token) {
            $job->update(['status' => 'failed']);
            $this->error("Telegram token not configured.");
            return 1;
        }

        $success = 0;
        $failed = 0;

        foreach ($targets as $targetId) {
            // Check if job was manually cancelled/stopped
            $currentJob = BroadcastJob::find($jobId);
            if (!$currentJob || in_array($currentJob->status, ['failed', 'completed'])) {
                break;
            }

            try {
                $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $targetId,
                    'text' => $job->message,
                    'parse_mode' => 'HTML'
                ]);

                if ($response->successful()) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }

            $job->update([
                'sent_count' => $success,
                'failed_count' => $failed,
            ]);

            // Delay to avoid hitting rate limits
            usleep(50000); // 50ms
        }

        $job->update(['status' => 'completed']);
        $this->info("Broadcast completed successfully.");
        return 0;
    }
}
