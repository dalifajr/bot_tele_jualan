<?php

namespace App\Services;

use App\Models\BroadcastJob;
use App\Models\BroadcastRead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BroadcastReadAnalyticsService
{
    /**
     * Staged calculation checkpoints in minutes after broadcast completion.
     */
    public const CHECKPOINTS = [
        '10m' => 10,
        '30m' => 30,
        '1h'  => 60,
        '24h' => 1440,
    ];

    /**
     * Idempotently record a confirmed read for a broadcast job across Web or Telegram.
     */
    public function recordRead(
        int $broadcastJobId,
        ?int $userId = null,
        ?string $telegramId = null,
        string $source = 'web_notif',
        ?Carbon $readAt = null
    ): ?BroadcastRead {
        if ($broadcastJobId <= 0 || ($userId === null && empty($telegramId))) {
            return null;
        }

        if (!Schema::hasTable('broadcast_reads')) {
            return null;
        }

        if ($userId === null && !empty($telegramId)) {
            $userId = User::where('telegram_id', $telegramId)->value('id');
        }

        $isWeb = str_starts_with($source, 'web');
        $isTelegram = str_starts_with($source, 'telegram');
        $timestamp = $readAt ?? now();

        $existing = null;
        if ($userId !== null) {
            $existing = BroadcastRead::where('broadcast_job_id', $broadcastJobId)
                ->where('user_id', $userId)
                ->first();
        } elseif (!empty($telegramId)) {
            $existing = BroadcastRead::where('broadcast_job_id', $broadcastJobId)
                ->where('telegram_id', (string) $telegramId)
                ->first();
        }

        if ($existing) {
            $dirty = false;
            if ($isWeb && !$existing->via_web) {
                $existing->via_web = true;
                $dirty = true;
            }
            if ($isTelegram && !$existing->via_telegram) {
                $existing->via_telegram = true;
                $dirty = true;
            }
            if ($userId !== null && $existing->user_id === null) {
                $existing->user_id = $userId;
                $dirty = true;
            }
            if (!empty($telegramId) && empty($existing->telegram_id)) {
                $existing->telegram_id = (string) $telegramId;
                $dirty = true;
            }
            if ($dirty) {
                $existing->save();
            }

            return $existing;
        }

        try {
            return BroadcastRead::create([
                'broadcast_job_id' => $broadcastJobId,
                'user_id' => $userId,
                'telegram_id' => $telegramId ? (string) $telegramId : null,
                'source' => $source,
                'via_web' => $isWeb,
                'via_telegram' => $isTelegram,
                'read_at' => $timestamp,
            ]);
        } catch (\Throwable $e) {
            // Handle race condition on unique index gracefully
            if ($userId !== null) {
                return BroadcastRead::where('broadcast_job_id', $broadcastJobId)
                    ->where('user_id', $userId)
                    ->first();
            }

            return null;
        }
    }

    /**
     * Sync read receipts from Laravel's `notifications` table into `broadcast_reads`.
     */
    public function syncWebNotificationReads(BroadcastJob $job): int
    {
        if (!Schema::hasTable('notifications') || !Schema::hasTable('broadcast_reads')) {
            return 0;
        }

        $synced = 0;
        $needle = '"broadcast_job_id":' . $job->id;
        $needleAlt = '"broadcast_job_id": ' . $job->id;

        $rows = DB::table('notifications')
            ->where('type', \App\Notifications\BroadcastAnnouncementNotification::class)
            ->whereNotNull('read_at')
            ->where(function ($q) use ($needle, $needleAlt) {
                $q->where('data', 'like', '%' . $needle . '%')
                  ->orWhere('data', 'like', '%' . $needleAlt . '%');
            })
            ->get(['notifiable_id', 'read_at', 'data']);

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->data, true);
            if (!is_array($payload) || (int) ($payload['broadcast_job_id'] ?? 0) !== (int) $job->id) {
                continue;
            }

            $readAt = $row->read_at ? Carbon::parse($row->read_at) : now();
            $this->recordRead(
                (int) $job->id,
                (int) $row->notifiable_id,
                null,
                'web_notif',
                $readAt
            );
            $synced++;
        }

        return $synced;
    }

    /**
     * Recalculate totals and capture any due staged checkpoints (10m, 30m, 1h, 24h).
     */
    public function recalculateJob(BroadcastJob $job): BroadcastJob
    {
        $this->syncWebNotificationReads($job);

        $readCount = BroadcastRead::where('broadcast_job_id', $job->id)->count();
        $webReadCount = BroadcastRead::where('broadcast_job_id', $job->id)->where('via_web', true)->count();
        $telegramReadCount = BroadcastRead::where('broadcast_job_id', $job->id)->where('via_telegram', true)->count();

        $reachBase = max((int) $job->sent_count, (int) $job->web_target_count, $readCount);
        $rate = $reachBase > 0 ? round(min(100, ($readCount / $reachBase) * 100), 1) : 0.0;

        $snapshots = is_array($job->read_snapshots) ? $job->read_snapshots : [];
        $anchor = $job->completed_at ?? $job->updated_at ?? $job->created_at;
        $now = now();

        if ($anchor && $job->status === 'completed') {
            foreach (self::CHECKPOINTS as $key => $minutes) {
                if (isset($snapshots[$key])) {
                    continue;
                }

                $dueAt = $anchor->copy()->addMinutes($minutes);
                if ($now->greaterThanOrEqualTo($dueAt)) {
                    // Count only reads recorded up to now (or exact checkpoint time if backfilling)
                    $snapUnique = BroadcastRead::where('broadcast_job_id', $job->id)
                        ->where('read_at', '<=', $now)
                        ->count();
                    $snapWeb = BroadcastRead::where('broadcast_job_id', $job->id)
                        ->where('via_web', true)
                        ->where('read_at', '<=', $now)
                        ->count();
                    $snapTg = BroadcastRead::where('broadcast_job_id', $job->id)
                        ->where('via_telegram', true)
                        ->where('read_at', '<=', $now)
                        ->count();
                    $snapBase = max((int) $job->sent_count, (int) $job->web_target_count, $snapUnique);
                    $snapRate = $snapBase > 0 ? round(min(100, ($snapUnique / $snapBase) * 100), 1) : 0.0;

                    $snapshots[$key] = [
                        'count' => $snapUnique,
                        'web' => $snapWeb,
                        'telegram' => $snapTg,
                        'rate' => $snapRate,
                        'captured_at' => $now->toIso8601String(),
                    ];
                }
            }
        }

        $job->forceFill([
            'read_count' => $readCount,
            'web_read_count' => $webReadCount,
            'telegram_read_count' => $telegramReadCount,
            'read_snapshots' => $snapshots,
            'last_read_calculated_at' => $now,
        ])->save();

        return $job->fresh();
    }

    /**
     * Process all completed broadcast jobs within the last 48h that still have pending checkpoints.
     */
    public function calculateDueJobs(): int
    {
        $cutoff = now()->subHours(48);
        $jobs = BroadcastJob::where('status', 'completed')
            ->where(function ($q) use ($cutoff) {
                $q->where('completed_at', '>=', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('completed_at')->where('updated_at', '>=', $cutoff);
                  });
            })
            ->orderByDesc('id')
            ->get();

        $processed = 0;
        foreach ($jobs as $job) {
            $snapshots = is_array($job->read_snapshots) ? $job->read_snapshots : [];
            if (count($snapshots) >= count(self::CHECKPOINTS)) {
                continue;
            }

            $anchor = $job->completed_at ?? $job->updated_at ?? $job->created_at;
            if (!$anchor) {
                continue;
            }

            $hasDueCheckpoint = false;
            foreach (self::CHECKPOINTS as $key => $minutes) {
                if (!isset($snapshots[$key]) && now()->greaterThanOrEqualTo($anchor->copy()->addMinutes($minutes))) {
                    $hasDueCheckpoint = true;
                    break;
                }
            }

            if ($hasDueCheckpoint) {
                $this->recalculateJob($job);
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Build a structured analytics payload for the Admin UI modal.
     */
    public function buildAnalyticsPayload(BroadcastJob $job): array
    {
        $anchor = $job->completed_at ?? $job->updated_at ?? $job->created_at;
        $snapshots = is_array($job->read_snapshots) ? $job->read_snapshots : [];

        $labels = [
            '10m' => __('10 Menit'),
            '30m' => __('30 Menit'),
            '1h'  => __('1 Jam'),
            '24h' => __('1 Hari (24 Jam)'),
        ];

        $timeline = [];
        foreach (self::CHECKPOINTS as $key => $minutes) {
            $dueAt = $anchor ? $anchor->copy()->addMinutes($minutes) : null;
            $snap = $snapshots[$key] ?? null;

            $timeline[] = [
                'key' => $key,
                'label' => $labels[$key],
                'minutes' => $minutes,
                'captured' => $snap !== null,
                'due_at' => $dueAt ? $dueAt->format('d M Y, H:i') : '-',
                'count' => $snap['count'] ?? null,
                'web' => $snap['web'] ?? null,
                'telegram' => $snap['telegram'] ?? null,
                'rate' => $snap['rate'] ?? null,
                'captured_at' => isset($snap['captured_at'])
                    ? Carbon::parse($snap['captured_at'])->format('d M Y, H:i')
                    : null,
            ];
        }

        return [
            'job_id' => $job->id,
            'status' => $job->status,
            'sent_count' => (int) $job->sent_count,
            'failed_count' => (int) $job->failed_count,
            'total_targets' => (int) $job->total_targets,
            'web_target_count' => (int) $job->web_target_count,
            'reach_base' => $job->reach_base,
            'read_count' => (int) $job->read_count,
            'web_read_count' => (int) $job->web_read_count,
            'telegram_read_count' => (int) $job->telegram_read_count,
            'read_rate' => $job->read_rate,
            'enable_read_button' => (bool) $job->enable_read_button,
            'cta_label' => $job->cta_label,
            'cta_url' => $job->cta_url,
            'completed_at' => $anchor ? $anchor->format('d M Y, H:i') : null,
            'last_calculated_at' => $job->last_read_calculated_at
                ? $job->last_read_calculated_at->format('d M Y, H:i:s')
                : null,
            'timeline' => $timeline,
        ];
    }

    /**
     * Generate deterministic HMAC signature for tracked Telegram CTA links.
     */
    public function generateTrackSignature(int $jobId, int $userId): string
    {
        return substr(
            hash_hmac('sha256', "bc_track:{$jobId}:{$userId}", (string) config('app.key')),
            0,
            24
        );
    }

    public function verifyTrackSignature(int $jobId, int $userId, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        return hash_equals($this->generateTrackSignature($jobId, $userId), $signature);
    }
}
