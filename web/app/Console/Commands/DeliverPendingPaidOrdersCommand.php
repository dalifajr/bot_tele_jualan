<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeliverPendingPaidOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:deliver-paid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pulihkan dan selesaikan pesanan yang berstatus paid tetapi belum ter-deliver (menghapus QRIS dan mengirim akun ke Telegram)';

    /**
     * Execute the console command.
     */
    public function handle(OrderService $orderService): int
    {
        $this->info("Memeriksa pesanan yang berstatus 'paid' namun belum 'delivered'...");

        $stuckOrders = Order::where(function ($query) {
            $query->where('status', 'paid')
                  ->orWhere(function ($sub) {
                      $sub->where('status', 'pending_payment')
                          ->whereHas('payment', fn($pq) => $pq->where('status', 'paid'));
                  });
        })->get();

        if ($stuckOrders->isEmpty()) {
            $this->info("Tidak ada pesanan tertahan. Semua pesanan yang lunas telah berstatus 'delivered' (Selesai).");
            return Command::SUCCESS;
        }

        $this->info("Ditemukan {$stuckOrders->count()} pesanan yang perlu diselesaikan:");

        $successCount = 0;
        foreach ($stuckOrders as $order) {
            $this->line("-> Memproses Order #{$order->order_ref} (Customer ID: {$order->customer_id})...");
            try {
                $orderService->confirmPayment($order, 'AUTO_RECOVERY_CLI');
                $this->info("   [OK] Order #{$order->order_ref} berhasil diselesaikan, QRIS dihapus, dan data akun dikirim!");
                $successCount++;
            } catch (\Throwable $e) {
                $this->error("   [ERROR] Gagal memproses Order #{$order->order_ref}: " . $e->getMessage());
                Log::error("[DeliverPendingPaidOrdersCommand] Error order {$order->order_ref}: " . $e->getMessage());
            }
        }

        $this->info("Selesai! {$successCount} dari {$stuckOrders->count()} pesanan berhasil dipulihkan.");
        return Command::SUCCESS;
    }
}
