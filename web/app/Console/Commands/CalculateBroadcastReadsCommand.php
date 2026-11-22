<?php

namespace App\Console\Commands;

use App\Services\BroadcastReadAnalyticsService;
use Illuminate\Console\Command;

class CalculateBroadcastReadsCommand extends Command
{
    protected $signature = 'broadcast:calculate-reads {--job= : Recalculate a specific broadcast job ID immediately}';

    protected $description = 'Calculate staged read analytics checkpoints (10m, 30m, 1h, 24h) for completed broadcasts';

    public function handle(BroadcastReadAnalyticsService $analytics): int
    {
        $specificJobId = $this->option('job');
        if ($specificJobId) {
            $job = \App\Models\BroadcastJob::find($specificJobId);
            if (!$job) {
                $this->error("Broadcast job #{$specificJobId} not found.");
                return 1;
            }
            $updated = $analytics->recalculateJob($job);
            $this->info("Broadcast #{$updated->id}: {$updated->read_count} unique reads ({$updated->read_rate}%).");
            return 0;
        }

        $count = $analytics->calculateDueJobs();
        $this->info("Calculated read checkpoints for {$count} broadcast job(s).");
        return 0;
    }
}
