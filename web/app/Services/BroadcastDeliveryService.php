<?php

namespace App\Services;

use App\Models\BroadcastJob;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BroadcastDeliveryService
{
    public function __construct(
        protected BroadcastReadAnalyticsService $analytics
    ) {
    }

    /**
     * Execute Telegram delivery for a pending BroadcastJob (atomic lock).
     * Returns ['status' => 'success'|'already_started'|'not_found'|'failed', ...].
     */
    public function deliver(int $jobId): array
    {
        $job = BroadcastJob::find($jobId);
        if (!$job) {
            return ['status' => 'not_found'];
        }

        $updated = BroadcastJob::where('id', $jobId)
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if (!$updated) {
            return ['status' => 'already_started'];
        }

        $targets = User::whereNotNull('telegram_id')
            ->where('role', 'customer')
            ->get(['id', 'telegram_id']);

        $token = config('telegram.bot_token');
        if (!$token) {
            BroadcastJob::where('id', $jobId)->update(['status' => 'failed']);
            return ['status' => 'failed', 'reason' => 'no_token'];
        }

        $success = 0;
        $failed = 0;

        foreach ($targets as $targetUser) {
            $currentJob = BroadcastJob::find($jobId);
            if (!$currentJob || in_array($currentJob->status, ['failed', 'completed'])) {
                break;
            }

            $replyMarkup = $this->buildReplyMarkup($job, (int) $targetUser->id);
            $sentSuccessfully = $this->sendToRecipient($job, $token, (string) $targetUser->telegram_id, $replyMarkup);

            if ($sentSuccessfully) {
                $success++;
            } else {
                $failed++;
            }

            if (($success + $failed) % 5 === 0) {
                BroadcastJob::where('id', $jobId)->update([
                    'sent_count' => $success,
                    'failed_count' => $failed,
                ]);
            }

            usleep(25000);
        }

        $finalJob = BroadcastJob::find($jobId);
        if ($finalJob && $finalJob->status !== 'failed') {
            $finalJob->update([
                'sent_count' => $success,
                'failed_count' => $failed,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return [
            'status' => 'success',
            'sent' => $success,
            'failed' => $failed,
        ];
    }

    protected function buildReplyMarkup(BroadcastJob $job, int $userId): ?string
    {
        $keyboard = [];

        if (!empty($job->cta_url)) {
            $sig = $this->analytics->generateTrackSignature((int) $job->id, $userId);
            $trackedUrl = route('broadcast.track-click', [
                'jobId' => $job->id,
                'userId' => $userId,
                'sig' => $sig,
            ]);

            $keyboard[] = [[
                'text' => trim((string) $job->cta_label) ?: '🔗 Buka Tautan',
                'url' => $trackedUrl,
            ]];
        }

        if ($job->enable_read_button) {
            $keyboard[] = [[
                'text' => '✅ Tandai Dibaca',
                'callback_data' => 'bc_read:' . $job->id,
            ]];
        }

        if (empty($keyboard)) {
            return null;
        }

        return json_encode(['inline_keyboard' => $keyboard]);
    }

    protected function sendToRecipient(BroadcastJob $job, string $token, string $chatId, ?string $replyMarkup): bool
    {
        $retry = 0;
        $maxRetries = 3;

        while ($retry < $maxRetries) {
            try {
                if ($job->media_path && Storage::disk('public')->exists($job->media_path)) {
                    $mediaFullPath = storage_path('app/public/' . $job->media_path);
                    $mediaData = fopen($mediaFullPath, 'r');
                    $filename = basename($mediaFullPath);

                    $payload = [
                        'chat_id' => $chatId,
                        'caption' => $job->message,
                        'parse_mode' => 'HTML',
                    ];
                    if ($replyMarkup !== null) {
                        $payload['reply_markup'] = $replyMarkup;
                    }

                    $endpoint = match ($job->media_type) {
                        'photo' => ['field' => 'photo', 'method' => 'sendPhoto'],
                        'video' => ['field' => 'video', 'method' => 'sendVideo'],
                        default => ['field' => 'document', 'method' => 'sendDocument'],
                    };

                    $response = Http::timeout(15)
                        ->attach($endpoint['field'], $mediaData, $filename)
                        ->post("https://api.telegram.org/bot{$token}/{$endpoint['method']}", $payload);

                    if (is_resource($mediaData)) {
                        fclose($mediaData);
                    }
                } else {
                    $payload = [
                        'chat_id' => $chatId,
                        'text' => $job->message,
                        'parse_mode' => 'HTML',
                    ];
                    if ($replyMarkup !== null) {
                        $payload['reply_markup'] = $replyMarkup;
                    }

                    $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
                }

                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->json('parameters.retry_after') ?? 2);
                    sleep(max(1, min(30, $retryAfter)));
                    $retry++;
                    continue;
                }

                return $response->successful();
            } catch (\Exception $e) {
                sleep(1);
                $retry++;
            }
        }

        return false;
    }
}
