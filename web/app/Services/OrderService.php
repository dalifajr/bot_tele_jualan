<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StockUnit;
use App\Models\Payment;
use App\Models\User;
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
                'detail' => \App\Models\AuditLog::maskSensitiveData("order_ref={$order->order_ref}; reason={$reason}"),
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
            $reservedStock = StockUnit::where('sold_order_id', $order->id)
                ->where('stock_status', 'reserved_checkout')
                ->where('is_sold', false)
                ->get();

            foreach ($reservedStock as $unit) {
                $unit->stock_status = 'ready';
                $unit->is_sold = true;
                $unit->save();
            }

            // 4. Process Seller Commissions
            $sellerSales = [];
            foreach ($reservedStock as $unit) {
                if ($unit->seller_id) {
                    $sellerSales[$unit->seller_id][] = $unit;
                }
            }

            foreach ($sellerSales as $sellerId => $units) {
                $seller = User::find($sellerId);
                if ($seller) {
                    $feePercent = $seller->platform_fee_percent ?? 10;
                    $totalWalletAdded = 0;

                    foreach ($units as $unit) {
                        $product = $unit->product;
                        if (!$product) continue;
                        $price = $product->price;
                        $feeAmount = (int)($price * $feePercent / 100);
                        $netEarnings = $price - $feeAmount;

                        $warrantyDays = $product->warranty_days ?? 0;
                        if ($warrantyDays > 0) {
                            // Insert into held_funds
                            DB::table('held_funds')->insert([
                                'seller_id' => $sellerId,
                                'order_id' => $order->id,
                                'product_id' => $product->id,
                                'amount' => $netEarnings,
                                'status' => 'held',
                                'release_at' => now()->addDays($warrantyDays),
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        } else {
                            $totalWalletAdded += $netEarnings;
                        }
                    }

                    if ($totalWalletAdded > 0) {
                        $seller->wallet_balance = ($seller->wallet_balance ?? 0) + $totalWalletAdded;
                        $seller->save();
                    }
                }
            }

            // 5. Create VPN Accounts (if any)
            $order->load('items.product');
            foreach ($order->items as $item) {
                if ($item->product && $item->product->is_vpn) {
                    $vpnService = app(\App\Services\VpnService::class);
                    
                    for ($i = 0; $i < $item->quantity; $i++) {
                        $username = $item->vpn_username;
                        if ($item->quantity > 1) {
                            $username .= '_' . ($i + 1);
                        }
                        
                        $res = $vpnService->createVpnAccount(
                            $item->product->vpn_protocol,
                            $username,
                            $item->vpn_password,
                            $item->product->vpn_duration_days
                        );
                        
                        \App\Models\VpnAccount::create([
                            'user_id' => $order->customer_id,
                            'order_id' => $order->id,
                            'protocol' => $item->product->vpn_protocol,
                            'username' => $username,
                            'password' => $item->vpn_password,
                            'config_link' => $res['output'] ?? 'Failed to generate',
                            'expired_at' => now()->addDays($item->product->vpn_duration_days),
                            'status' => $res['success'] ? 'active' : 'failed'
                        ]);
                    }
                }
            }

            // 6. Audit Log
            DB::table('audit_logs')->insert([
                'action' => 'payment_confirmed',
                'actor_id' => $adminId,
                'entity_type' => 'payment',
                'entity_id' => $payment->id ?? 0,
                'detail' => \App\Models\AuditLog::maskSensitiveData("amount={$payment->expected_amount}; source_app=WEB_ADMIN:{$adminId}"),
                'created_at' => now(),
            ]);

            DB::commit();
            
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
