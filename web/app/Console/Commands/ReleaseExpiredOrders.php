<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\StockUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:release-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically cancel pending orders that have passed their expiration time and release locked stock units back to ready status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredOrders = Order::where('status', 'pending_payment')
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('No expired orders to process.');
            return 0;
        }

        $this->info('Found ' . $expiredOrders->count() . ' expired orders. Processing...');

        foreach ($expiredOrders as $order) {
            DB::beginTransaction();
            try {
                // Update Order status to expired
                $order->update([
                    'status' => 'expired',
                    'cancelled_at' => now(),
                    'cancel_reason' => 'Batas waktu pembayaran telah habis (Sistem)',
                ]);

                // Notify customer & admin via Telegram
                try {
                    \App\Services\TelegramService::notifyCustomerOrderCancelled($order, 'payment_timeout');
                    \App\Services\TelegramService::updateAdminOrderMessage($order);
                } catch (\Exception $e) {
                    Log::error("Gagal kirim notifikasi expired ke customer/admin: " . $e->getMessage());
                }

                // Release reserved stock units
                $affectedStockUnits = StockUnit::where('sold_order_id', $order->id)
                    ->where('stock_status', 'reserved_checkout')
                    ->get();

                $releasedIds = [];
                foreach ($affectedStockUnits as $unit) {
                    $unit->update([
                        'stock_status' => 'ready',
                        'sold_order_id' => null,
                    ]);
                    $releasedIds[] = $unit->id;
                }

                // Write Audit Log
                DB::table('audit_logs')->insert([
                    'action' => 'order_auto_expired',
                    'actor_id' => null, // System Action
                    'entity_type' => 'order',
                    'entity_id' => $order->id,
                    'detail' => "order_ref={$order->order_ref}; released_stock_ids=[" . implode(',', $releasedIds) . "]",
                    'created_at' => now(),
                ]);

                DB::commit();
                $this->info("Order {$order->order_ref} expired successfully. Released " . count($releasedIds) . " stock units.");
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed to release expired order #{$order->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                $this->error("Failed to release expired order #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info('Completed auto-release process.');
        return 0;
    }
}
