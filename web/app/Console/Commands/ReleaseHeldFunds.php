<?php

namespace App\Console\Commands;

use App\Models\HeldFund;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $now = now();
        $expiredFunds = HeldFund::where('status', 'held')
            ->where('release_at', '<=', $now)
            ->with(['seller', 'order', 'product'])
            ->get();

        if ($expiredFunds->isEmpty()) {
            $this->info('No held funds to release.');
            return 0;
        }

        $this->info('Found ' . $expiredFunds->count() . ' held funds to release. Processing...');

        foreach ($expiredFunds as $fund) {
            DB::beginTransaction();
            try {
                $seller = $fund->seller;
                if (!$seller) {
                    throw new \Exception("Seller (ID: {$fund->seller_id}) not found for held fund ID {$fund->id}.");
                }

                // 1. Release held funds
                $fund->status = 'released';
                $fund->save();

                // 2. Increment seller wallet balance
                $seller->wallet_balance = ($seller->wallet_balance ?? 0) + $fund->amount;
                $seller->save();

                // 3. Insert Audit Log
                DB::table('audit_logs')->insert([
                    'action' => 'held_fund_released',
                    'actor_id' => null, // System Action
                    'entity_type' => 'held_funds',
                    'entity_id' => $fund->id,
                    'detail' => "seller_id={$seller->id}; amount={$fund->amount}; order_id={$fund->order_id}",
                    'created_at' => now(),
                ]);

                DB::commit();

                $this->info("Released fund ID {$fund->id} of Rp " . number_format($fund->amount, 0, ',', '.') . " to seller {$seller->username}.");

                // 4. Send Telegram notification
                $orderRef = $fund->order->order_ref ?? 'N/A';
                $productName = $fund->product->name ?? 'Produk Garansi';
                try {
                    TelegramService::notifySellerFundsReleased($seller, $fund->amount, $orderRef, $productName);
                } catch (\Exception $te) {
                    Log::warning("Gagal mengirim notifikasi pelepasan dana ke Telegram seller: " . $te->getMessage());
                }

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to release held fund ID {$fund->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                $this->error("Failed to release held fund ID {$fund->id}: " . $e->getMessage());
            }
        }

        $this->info('Completed releasing held funds.');
        return 0;
    }
}
