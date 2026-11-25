<?php

namespace App\Console\Commands;

use App\Services\HeldFundService;
use Illuminate\Console\Command;

class ReleaseHeldFunds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'funds:release-held';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically release held seller funds that have passed their warranty period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting automatic held funds release...');

        $logger = function ($msg) {
            $this->line($msg);
        };

        $result = HeldFundService::releaseEligibleFunds(true, $logger);

        if ($result['locked']) {
            $this->warn('Proses pelepasan dana sedang berjalan di proses lain (locked).');
            return 0;
        }

        $this->info("Completed. Released: {$result['released_count']} funds (Rp " . number_format($result['released_amount'], 0, ',', '.') . "), Skipped due to active complaints: {$result['skipped_complaints']}.");
        return 0;
    }
}
