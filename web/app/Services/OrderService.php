<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StockUnit;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    /**
     * Cancel an order and release its reserved stock.
     */
    public function cancelOrder(Order $order, string $reason, $actorId): bool
    {
        if ($order->status !== 'pending_payment') {
            throw new Exception("Hanya pesanan pending yang dapat dibatalkan.");
        }

        try {
            DB::beginTransaction();

            // 1. Update Order
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancel_reason = $reason;
            $order->save();

            // 2. Update Payment
            $payment = $order->payment;
            if ($payment && $payment->status === 'pending') {
                $payment->status = 'cancelled';
                $payment->save();
            }

            // 3. Release Reserved Stock
            StockUnit::where('sold_order_id', $order->id)
                ->where('stock_status', 'reserved_checkout')
                ->where('is_sold', false)
                ->update([
                    'stock_status' => 'ready',
                    'sold_order_id' => null
                ]);

            // 4. Audit Log
            DB::table('audit_logs')->insert([
                'action' => 'order_cancelled',
                'actor_id' => $actorId,
                'entity_type' => 'order',
                'entity_id' => $order->id,
                'detail' => "order_ref={$order->order_ref}; reason={$reason}",
                'created_at' => now(),
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Confirm payment for an order and assign stock.
     */
    public function confirmPayment(Order $order, $adminId): bool
    {
        if ($order->status !== 'pending_payment') {
            throw new Exception("Hanya pesanan pending yang dapat dikonfirmasi.");
        }

        try {
            DB::beginTransaction();

            // 1. Update Order
            $order->status = 'delivered';
            $order->paid_at = now();
            $order->delivered_at = now();
            $order->save();

            // 2. Update Payment
            $payment = $order->payment;
            if ($payment && $payment->status === 'pending') {
                $payment->status = 'paid';
                $payment->matched_at = now();
                $payment->source_app = 'WEB_ADMIN:' . $adminId;
                $payment->received_amount = $payment->expected_amount;
                $payment->save();
            }

            // 3. Consume Stock
            StockUnit::where('sold_order_id', $order->id)
                ->where('stock_status', 'reserved_checkout')
                ->where('is_sold', false)
                ->update([
                    'stock_status' => 'ready', // Kembali ke 'ready' (atau bisa dibiarkan/null) tapi is_sold = true
                    'is_sold' => true
                ]);

            // 4. Audit Log
            DB::table('audit_logs')->insert([
                'action' => 'payment_confirmed',
                'actor_id' => $adminId,
                'entity_type' => 'payment',
                'entity_id' => $payment->id ?? 0,
                'detail' => "amount={$payment->expected_amount}; source_app=WEB_ADMIN:{$adminId}",
                'created_at' => now(),
            ]);

            DB::commit();
            
            // TODO: Seharusnya mengirim pesan delivery ke telegram customer.
            // Karena ini sistem hybrid, kita bisa mengandalkan bot untuk sweep/deteksi atau biarkan customer cek web.
            // Pada bot python, `reconcile_payment` mengirim message ke customer.
            // Di sini kita bisa biarkan saja atau buat notifikasi telegram jika memungkinkan.
            // Untuk sekarang, kita selesaikan state DB-nya saja.
            
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
