<?php

namespace App\Console\Commands;

use App\Models\BroadcastJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

            $retry = 0;
            $maxRetries = 3;
            $sentSuccessfully = false;

            while ($retry < $maxRetries) {
                try {
                    if ($job->media_path && Storage::disk('public')->exists($job->media_path)) {
                        $mediaFullPath = storage_path('app/public/' . $job->media_path);
                        $mediaData = fopen($mediaFullPath, 'r');
                        $filename = basename($mediaFullPath);

                        if ($job->media_type === 'photo') {
                            $response = Http::timeout(15)
                                ->attach('photo', $mediaData, $filename)
                                ->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                                    'chat_id' => $targetId,
                                    'caption' => $job->message,
                                    'parse_mode' => 'HTML'
                                ]);
                        } elseif ($job->media_type === 'video') {
                            $response = Http::timeout(15)
                                ->attach('video', $mediaData, $filename)
                                ->post("https://api.telegram.org/bot{$token}/sendVideo", [
                                    'chat_id' => $targetId,
                                    'caption' => $job->message,
                                    'parse_mode' => 'HTML'
                                ]);
                        } else {
                            $response = Http::timeout(15)
                                ->attach('document', $mediaData, $filename)
                                ->post("https://api.telegram.org/bot{$token}/sendDocument", [
                                    'chat_id' => $targetId,
                                    'caption' => $job->message,
                                    'parse_mode' => 'HTML'
                                ]);
                        }
                    } else {
                        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                            'chat_id' => $targetId,
                            'text' => $job->message,
                            'parse_mode' => 'HTML'
                        ]);
                    }

                    if ($response->status() === 429) {
                        $retryAfter = $response->json('parameters.retry_after') ?? 2;
                        sleep($retryAfter);
                        $retry++;
                        continue;
                    }

                    if ($response->successful()) {
                        $success++;
                        $sentSuccessfully = true;
                    } else {
                        $failed++;
                    }
                    break;
                } catch (\Exception $e) {
                    sleep(1);
                    $retry++;
                }
            }

            if (!$sentSuccessfully && $retry === $maxRetries) {
                $failed++;
            }

            // Batch update database every 5 messages
            if (($success + $failed) % 5 === 0) {
                $job->update([
                    'sent_count' => $success,
                    'failed_count' => $failed,
                ]);
            }

            // Small delay to prevent hitting limits aggressively
            usleep(25000); // 25ms
        }

        $job->update([
            'sent_count' => $success,
            'failed_count' => $failed,
            'status' => 'completed',
        ]);
        $this->info("Broadcast completed successfully.");
        return 0;
    }
}
