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

            // 5. Notify customer & admin via Telegram
            try {
                \App\Services\TelegramService::notifyCustomerOrderCancelled($order, $reason);
                \App\Services\TelegramService::updateAdminOrderMessage($order);
            } catch (\Exception $e) {
                \Log::error("Gagal mengirim notifikasi pembatalan ke pelanggan/admin: " . $e->getMessage());
            }

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
    public function confirmPayment(Order $order, $adminId = null): bool
    {
        if ($order->status !== 'pending_payment' && $order->status !== 'paid') {
            throw new Exception("Hanya pesanan pending atau paid yang dapat dikonfirmasi/diselesaikan.");
        }

        try {
            DB::beginTransaction();

            // 1. Update Order
            $order->status = 'delivered';
            $order->paid_at = $order->paid_at ?? now();
            $order->delivered_at = now();
            $order->save();

            // 2. Update Payment
            $sourceAppStr = is_string($adminId) ? $adminId : ($adminId ? 'WEB_ADMIN:' . $adminId : 'AUTO_PAYMENT');
            $payment = $order->payment;
            if ($payment) {
                $payment->status = 'paid';
                $payment->matched_at = $payment->matched_at ?? now();
                $payment->source_app = $payment->source_app ?: $sourceAppStr;
                $payment->received_amount = $payment->received_amount ?: $payment->expected_amount;
                $payment->save();
            }

            // 3. Consume Stock
            $reservedStock = StockUnit::where('sold_order_id', $order->id)->get();

            foreach ($reservedStock as $unit) {
                $unit->stock_status = 'ready';
                $unit->is_sold = true;
                $unit->save();
            }

            // 4. Process Seller Commissions
            $order->loadMissing('items.product');

            // Build map of discount per unit for each product
            $unitDiscounts = [];
            foreach ($order->items as $orderItem) {
                $itemDiscount = (int) ($orderItem->discount_amount ?? 0);
                if ($itemDiscount <= 0 && ($order->discount_amount ?? 0) > 0 && ($order->subtotal ?? 0) > 0) {
                    // Fallback proportional calculation for orders without item-level discount recorded
                    $itemTotal = $orderItem->unit_price * $orderItem->quantity;
                    $itemDiscount = (int) round(($itemTotal / $order->subtotal) * $order->discount_amount);
                }

                $qty = max(1, $orderItem->quantity);
                $baseUnitDiscount = (int) floor($itemDiscount / $qty);
                $remainder = $itemDiscount - ($baseUnitDiscount * $qty);

                $discountsArray = array_fill(0, $qty, $baseUnitDiscount);
                for ($i = 0; $i < $remainder; $i++) {
                    $discountsArray[$i] += 1;
                }
                $unitDiscounts[$orderItem->product_id] = $discountsArray;
            }

            $sellerSales = [];
            foreach ($reservedStock as $unit) {
                if ($unit->seller_id) {
                    $sellerSales[$unit->seller_id][] = $unit;
                }
            }

            // Also check VPN items if created by seller
            $sellerVpnItems = [];
            foreach ($order->items as $item) {
                if ($item->product && $item->product->is_vpn && $item->product->creator_id) {
                    $sellerVpnItems[$item->product->creator_id][] = $item;
                }
            }

            foreach ($sellerSales as $sellerId => $units) {
                $seller = User::find($sellerId);
                if ($seller) {
                    $totalWalletAdded = 0;

                    foreach ($units as $unit) {
                        $product = $unit->product;
                        if (!$product) continue;

                        $rawPrice = $product->price;
                        $unitDiscount = 0;
                        if (isset($unitDiscounts[$product->id]) && count($unitDiscounts[$product->id]) > 0) {
                            $unitDiscount = array_shift($unitDiscounts[$product->id]);
                        }

                        // Apply coupon discount to product price first
                        $priceAfterCoupon = max(0, $rawPrice - $unitDiscount);

                        // Determine platform fee percent: per-product or per-seller
                        if ($seller->commission_per_product) {
                            $feePercent = $product->platform_fee_percent ?? $seller->platform_fee_percent ?? 10;
                        } else {
                            $feePercent = $seller->platform_fee_percent ?? 10;
                        }

                        $feeAmount = (int) round(($priceAfterCoupon * $feePercent) / 100);
                        $netEarnings = max(0, $priceAfterCoupon - $feeAmount);

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

            // Process seller VPN commissions (if any)
            foreach ($sellerVpnItems as $sellerId => $items) {
                $seller = User::find($sellerId);
                if ($seller) {
                    $totalWalletAdded = 0;
                    foreach ($items as $item) {
                        $product = $item->product;
                        for ($i = 0; $i < $item->quantity; $i++) {
                            $rawPrice = $product->price;
                            $unitDiscount = 0;
                            if (isset($unitDiscounts[$product->id]) && count($unitDiscounts[$product->id]) > 0) {
                                $unitDiscount = array_shift($unitDiscounts[$product->id]);
                            }

                            $priceAfterCoupon = max(0, $rawPrice - $unitDiscount);
                            if ($seller->commission_per_product) {
                                $feePercent = $product->platform_fee_percent ?? $seller->platform_fee_percent ?? 10;
                            } else {
                                $feePercent = $seller->platform_fee_percent ?? 10;
                            }

                            $feeAmount = (int) round(($priceAfterCoupon * $feePercent) / 100);
                            $netEarnings = max(0, $priceAfterCoupon - $feeAmount);

                            $warrantyDays = $product->warranty_days ?? 0;
                            if ($warrantyDays > 0) {
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
                    }

                    if ($totalWalletAdded > 0) {
                        $seller->wallet_balance = ($seller->wallet_balance ?? 0) + $totalWalletAdded;
                        $seller->save();
                    }
                }
            }

            // 5. Create VPN Accounts (if any)
            $order->load('items.product');
            $vpnDetails = [];
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
                        
                        $vpn = \App\Models\VpnAccount::create([
                            'user_id' => $order->customer_id,
                            'order_id' => $order->id,
                            'protocol' => $item->product->vpn_protocol,
                            'username' => $username,
                            'password' => $item->vpn_password,
                            'config_link' => $res['output'] ?? 'Failed to generate',
                            'expired_at' => now()->addDays($item->product->vpn_duration_days),
                            'status' => $res['success'] ? 'active' : 'failed'
                        ]);
                        $vpnDetails[] = $vpn->config_link;
                    }
                }
            }

            \App\Services\TelegramService::notifyCustomerOrderDelivered($order, $reservedStock, $vpnDetails);
            \App\Services\TelegramService::updateAdminOrderMessage($order);

            // 6. Audit Log
            $actorId = is_numeric($adminId) ? (int)$adminId : null;
            DB::table('audit_logs')->insert([
                'action' => 'payment_confirmed',
                'actor_id' => $actorId,
                'entity_type' => 'payment',
                'entity_id' => $payment->id ?? 0,
                'detail' => \App\Models\AuditLog::maskSensitiveData("order_ref={$order->order_ref}; amount={$order->total_amount}; source_app={$sourceAppStr}"),
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
