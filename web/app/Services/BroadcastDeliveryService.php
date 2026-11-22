<?php

namespace App\Services;

use App\Models\BroadcastFailure;
use App\Models\BroadcastJob;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
            ->get(['id', 'telegram_id', 'full_name', 'username']);

        $token = config('telegram.bot_token');
        if (!$token) {
            BroadcastJob::where('id', $jobId)->update(['status' => 'failed']);
            return ['status' => 'failed', 'reason' => 'no_token'];
        }

        $canLogFailures = Schema::hasTable('broadcast_failures');
        $success = 0;
        $failed = 0;

        foreach ($targets as $targetUser) {
            $currentJob = BroadcastJob::find($jobId);
            if (!$currentJob || in_array($currentJob->status, ['failed', 'completed'])) {
                break;
            }

            $replyMarkup = $this->buildReplyMarkup($job, (int) $targetUser->id);
            $sendResult = $this->sendToRecipient($job, $token, (string) $targetUser->telegram_id, $replyMarkup);

            if ($sendResult['ok']) {
                $success++;
            } else {
                $failed++;

                if ($canLogFailures) {
                    $this->recordFailure($jobId, $targetUser, $sendResult);
                }
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

            $btnText = trim((string) $job->cta_label) ?: '🔗 Buka Tautan';
            if ($this->shouldOpenAsMiniApp($job, $trackedUrl)) {
                $keyboard[] = [[
                    'text' => $btnText,
                    'web_app' => ['url' => $trackedUrl],
                ]];
            } else {
                $keyboard[] = [[
                    'text' => $btnText,
                    'url' => $trackedUrl,
                ]];
            }
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

    /**
     * Telegram Bot API requires `web_app` URLs to be HTTPS and forbids `t.me` links inside WebApp.
     */
    protected function shouldOpenAsMiniApp(BroadcastJob $job, string $trackedUrl): bool
    {
        $mode = $job->cta_open_mode ?: 'miniapp';
        if ($mode !== 'miniapp') {
            return false;
        }

        if (!str_starts_with(strtolower($trackedUrl), 'https://')) {
            return false;
        }

        $target = trim((string) $job->cta_url);
        if (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            return true;
        }

        $host = strtolower((string) parse_url($target, PHP_URL_HOST));
        if ($host === '' || in_array($host, ['t.me', 'telegram.me', 'telegram.dog'], true) || str_ends_with($host, '.t.me')) {
            return false;
        }

        return true;
    }

    /**
     * Send broadcast message to a single recipient and return detailed delivery status.
     *
     * @return array{ok: bool, error_code: ?int, error_category: ?string, error_message: ?string}
     */
    protected function sendToRecipient(BroadcastJob $job, string $token, string $chatId, ?string $replyMarkup): array
    {
        $retry = 0;
        $maxRetries = 3;
        $lastCode = null;
        $lastMessage = 'Unknown Telegram API error';
        $lastCategory = 'other';

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

                if ($response->successful()) {
                    return [
                        'ok' => true,
                        'error_code' => null,
                        'error_category' => null,
                        'error_message' => null,
                    ];
                }

                $statusCode = (int) ($response->json('error_code') ?? $response->status());
                $description = trim((string) ($response->json('description') ?? ''));
                if ($description === '') {
                    $description = trim(substr((string) $response->body(), 0, 500)) ?: "HTTP {$statusCode}";
                }

                $lastCode = $statusCode;
                $lastMessage = $description;
                $lastCategory = $this->classifyTelegramError($statusCode, $description);

                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->json('parameters.retry_after') ?? 2);
                    sleep(max(1, min(30, $retryAfter)));
                    $retry++;
                    continue;
                }

                return [
                    'ok' => false,
                    'error_code' => $lastCode,
                    'error_category' => $lastCategory,
                    'error_message' => $lastMessage,
                ];
            } catch (\Throwable $e) {
                $lastCode = null;
                $lastMessage = trim(substr($e->getMessage(), 0, 500)) ?: 'Network exception while contacting Telegram API';
                $lastCategory = 'network_error';
                sleep(1);
                $retry++;
            }
        }

        return [
            'ok' => false,
            'error_code' => $lastCode,
            'error_category' => $lastCategory,
            'error_message' => $lastMessage,
        ];
    }

    /**
     * Classify raw Telegram Bot API / transport errors into human-friendly categories.
     */
    public function classifyTelegramError(?int $statusCode, string $description): string
    {
        $msg = strtolower($description);

        if (
            str_contains($msg, 'bot was blocked')
            || str_contains($msg, 'blocked by the user')
            || str_contains($msg, 'bot was kicked')
            || str_contains($msg, 'user is kicked')
        ) {
            return 'bot_blocked';
        }

        if (
            str_contains($msg, 'user is deactivated')
            || str_contains($msg, 'deleted account')
        ) {
            return 'user_deactivated';
        }

        if (
            str_contains($msg, 'chat not found')
            || str_contains($msg, "can't initiate conversation")
            || str_contains($msg, 'peer_id_invalid')
            || str_contains($msg, "can't send messages to bots")
            || str_contains($msg, 'user not found')
        ) {
            return 'chat_not_found';
        }

        if (
            str_contains($msg, "can't parse entities")
            || str_contains($msg, 'unsupported start tag')
            || str_contains($msg, 'unexpected end tag')
            || str_contains($msg, 'message is too long')
            || str_contains($msg, 'caption is too long')
            || str_contains($msg, 'message text is empty')
        ) {
            return 'invalid_html';
        }

        if (
            $statusCode === 413
            || str_contains($msg, 'wrong file identifier')
            || str_contains($msg, 'file is too big')
            || str_contains($msg, 'failed to get http url')
            || str_contains($msg, 'wrong remote file')
            || str_contains($msg, 'photo_invalid_dimensions')
            || str_contains($msg, 'media_caption_too_long')
        ) {
            return 'media_error';
        }

        if ($statusCode === 429 || str_contains($msg, 'too many requests')) {
            return 'rate_limited';
        }

        if (
            str_contains($msg, 'curl error')
            || str_contains($msg, 'timed out')
            || str_contains($msg, 'timeout')
            || str_contains($msg, 'could not resolve host')
            || str_contains($msg, 'connection refused')
            || str_contains($msg, 'connection reset')
        ) {
            return 'network_error';
        }

        return 'other';
    }

    protected function recordFailure(int $jobId, User $targetUser, array $sendResult): void
    {
        try {
            $fullName = trim((string) ($targetUser->full_name ?? ''));
            $username = trim((string) ($targetUser->username ?? ''));
            $recipientName = $fullName !== ''
                ? ($username !== '' ? "{$fullName} (@{$username})" : $fullName)
                : ($username !== '' ? "@{$username}" : "User #{$targetUser->id}");

            BroadcastFailure::create([
                'broadcast_job_id' => $jobId,
                'user_id' => $targetUser->id,
                'telegram_id' => (string) $targetUser->telegram_id,
                'recipient_name' => mb_substr($recipientName, 0, 160),
                'error_code' => $sendResult['error_code'],
                'error_category' => $sendResult['error_category'] ?? 'other',
                'error_message' => mb_substr((string) ($sendResult['error_message'] ?? 'Unknown error'), 0, 1000),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never interrupt broadcast delivery loop if failure logging throws
        }
    }
}
