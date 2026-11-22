<?php

namespace App\Console\Commands;

use App\Services\BroadcastDeliveryService;
use Illuminate\Console\Command;

class RunBroadcastCommand extends Command
{
    protected $signature = 'broadcast:run {jobId}';

    protected $description = 'Run a broadcast job in the background';

    public function handle(BroadcastDeliveryService $delivery)
    {
        $jobId = (int) $this->argument('jobId');
        $result = $delivery->deliver($jobId);

        if ($result['status'] === 'not_found') {
            $this->error('Job not found.');
            return 1;
        }

        if ($result['status'] === 'already_started') {
            $this->info('Job already running or completed by another worker.');
            return 0;
        }

        if ($result['status'] === 'failed') {
            $this->error('Telegram token not configured.');
            return 1;
        }

        $this->info('Broadcast completed successfully.');
        return 0;
    }
}
