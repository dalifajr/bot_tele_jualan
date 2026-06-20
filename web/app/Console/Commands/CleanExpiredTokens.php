<?php

namespace App\Console\Commands;

use App\Models\TelegramLoginToken;
use App\Models\TelegramLinkToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CleanExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired Telegram login tokens, link tokens, and expired sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting expired tokens cleanup...');

        // 1. Clean Telegram Login Tokens
        $loginTokensDeleted = TelegramLoginToken::where(function ($query) {
            $query->where('expires_at', '<', now())
                  ->where('status', '!=', 'used');
        })->orWhere(function ($query) {
            $query->where('link_expires_at', '<', now())
                  ->whereNull('used_at');
        })->delete();

        $this->info("Deleted {$loginTokensDeleted} expired Telegram login tokens.");

        // 2. Clean Telegram Link Tokens
        $linkTokensDeleted = TelegramLinkToken::where('expires_at', '<', now())->delete();
        $this->info("Deleted {$linkTokensDeleted} expired Telegram link tokens.");

        // 3. Clean Sessions if database sessions are used
        $sessionsDeleted = 0;
        if (Schema::hasTable('sessions')) {
            // Laravel database sessions store last_activity as UNIX timestamp.
            // Let's delete sessions that haven't been active for more than 1 week.
            $oneWeekAgo = now()->subWeek()->timestamp;
            $sessionsDeleted = DB::table('sessions')->where('last_activity', '<', $oneWeekAgo)->delete();
            $this->info("Deleted {$sessionsDeleted} inactive database sessions.");
        }

        // Write to system logs
        Log::info("CleanExpiredTokens executed: Deleted {$loginTokensDeleted} login tokens, {$linkTokensDeleted} link tokens, and {$sessionsDeleted} sessions.");

        $this->info('Expired tokens cleanup completed.');
        return 0;
    }
}
