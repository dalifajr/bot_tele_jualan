<?php

namespace App\Jobs;

use App\Models\BroadcastJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $broadcastJobId;
    protected $targets;
    protected $message;

    /**
     * Create a new job instance.
     */
    public function __construct($broadcastJobId, array $targets, $message)
    {
        $this->broadcastJobId = $broadcastJobId;
        $this->targets = $targets;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $job = BroadcastJob::find($this->broadcastJobId);
        if (!$job) return;

        $job->update(['status' => 'processing']);

        $token = config('telegram.bot_token');
        if (!$token) {
            $job->update(['status' => 'failed']);
            return;
        }

        $success = 0;
        $failed = 0;

        foreach ($this->targets as $targetId) {
            // Check if job was manually updated/cancelled
            $currentJob = BroadcastJob::find($this->broadcastJobId);
            if (!$currentJob || in_array($currentJob->status, ['failed', 'completed'])) {
                break;
            }

            try {
                $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $targetId,
                    'text' => $this->message,
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

            // Delay to avoid hitting rate limits (20-30 msgs per sec limit)
            usleep(50000); // 50ms
        }

        $job->update(['status' => 'completed']);
    }
}
