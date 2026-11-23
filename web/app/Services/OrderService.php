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
        try {
            DB::beginTransaction();

            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            if (!$lockedOrder || $lockedOrder->status !== 'pending_payment') {
                DB::rollBack();
                throw new Exception("Hanya pesanan pending yang dapat dibatalkan.");
            }

            // 1. Update Order
            $lockedOrder->status = 'cancelled';
            $lockedOrder->cancelled_at = now();
            $lockedOrder->cancel_reason = $reason;
            $lockedOrder->save();

            // 2. Update Payment
            $payment = $lockedOrder->payment()->lockForUpdate()->first();
            if ($payment && $payment->status === 'pending') {
                $payment->status = 'cancelled';
                $payment->save();
            }

            // 3. Release Reserved Stock
            StockUnit::where('sold_order_id', $lockedOrder->id)
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
                'entity_id' => $lockedOrder->id,
                'detail' => \App\Models\AuditLog::maskSensitiveData("order_ref={$lockedOrder->order_ref}; reason={$reason}"),
                'created_at' => now(),
            ]);

            DB::commit();

            $order->refresh();

            // 5. Notify customer & admin via Telegram (after commit)
            try {
                \App\Services\TelegramService::notifyCustomerOrderCancelled($lockedOrder, $reason);
                \App\Services\TelegramService::updateAdminOrderMessage($lockedOrder);
            } catch (\Exception $e) {
                \Log::error("Gagal mengirim notifikasi pembatalan ke pelanggan/admin: " . $e->getMessage());
            }

            return true;
        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    /**
     * Confirm payment for an order and assign stock.
     */
    public function confirmPayment(Order $order, $adminId = null): bool
    {
        try {
            DB::beginTransaction();

            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            if (!$lockedOrder || ($lockedOrder->status !== 'pending_payment' && $lockedOrder->status !== 'paid')) {
                DB::rollBack();
                throw new Exception("Hanya pesanan pending atau paid yang dapat dikonfirmasi/diselesaikan.");
            }

            $order = $lockedOrder;

            // 1. Update Order
            $order->status = 'delivered';
            $order->paid_at = $order->paid_at ?? now();
            $order->delivered_at = now();
            $order->save();

            // 2. Update Payment
            $sourceAppStr = is_string($adminId) ? $adminId : ($adminId ? 'WEB_ADMIN:' . $adminId : 'AUTO_PAYMENT');
            $payment = $order->payment()->lockForUpdate()->first();
            if ($payment) {
                $payment->status = 'paid';
                $payment->matched_at = $payment->matched_at ?? now();
                $payment->source_app = $payment->source_app ?: $sourceAppStr;
                $payment->received_amount = $payment->received_amount ?: $payment->expected_amount;
                $payment->save();
            }

            // 3. Consume Stock
            $reservedStock = StockUnit::where('sold_order_id', $order->id)->lockForUpdate()->get();

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
                $seller = User::where('id', $sellerId)->lockForUpdate()->first();
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
                        $seller->increment('wallet_balance', $totalWalletAdded);
                    }
                }
            }

            // Process seller VPN commissions (if any)
            foreach ($sellerVpnItems as $sellerId => $items) {
                $seller = User::where('id', $sellerId)->lockForUpdate()->first();
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
                        $seller->increment('wallet_balance', $totalWalletAdded);
                    }
                }
            }

            // 5. Stage VPN Account rows inside transaction (external HTTP/SSH provisioning runs POST-COMMIT!)
            $order->load('items.product.vpnServer');
            $vpnService = app(\App\Services\VpnService::class);
            $stagedVpnProvisions = [];
            foreach ($order->items as $item) {
                $meta = is_array($item->vpn_meta) ? $item->vpn_meta : [];
                $isVpnItem = ($item->product && $item->product->is_vpn) || !empty($meta['protocol']);
                if ($isVpnItem) {
                    $metaServer = !empty($meta['server_id']) ? \App\Models\VpnServer::find((int) $meta['server_id']) : null;
                    $resolvedServer = $vpnService->resolveServer($metaServer, $item->product);
                    $protocol = strtolower((string) ($meta['protocol'] ?? $item->product?->vpn_protocol ?? 'vmess'));
                    $durationDays = max(1, (int) ($meta['days'] ?? $item->product?->vpn_duration_days ?: 30));
                    $quotaGb = max(0, (int) ($meta['quota_gb'] ?? $item->product?->vpn_quota_gb ?? 0));
                    $ipLimit = max(1, (int) ($meta['ip_limit'] ?? $item->product?->vpn_ip_limit ?? 1));
                    $sniMode = (string) ($meta['sni_mode'] ?? $item->product?->vpn_sni_mode ?? '3');

                    for ($i = 0; $i < $item->quantity; $i++) {
                        $username = $item->vpn_username;
                        if ($item->quantity > 1) {
                            $username .= '_' . ($i + 1);
                        }

                        $unitPurchasePrice = max(0, (int) ($item->unit_price ?: ($item->product?->price ?? 0)));

                        $vpn = \App\Models\VpnAccount::create([
                            'user_id' => $order->customer_id,
                            'order_id' => $order->id,
                            'vpn_server_id' => ($resolvedServer && $resolvedServer->exists) ? $resolvedServer->id : null,
                            'server_ip' => $resolvedServer?->ssh_host ?: ($resolvedServer ? parse_url((string) $resolvedServer->base_url, PHP_URL_HOST) : null),
                            'protocol' => $protocol,
                            'username' => $username,
                            'password' => $item->vpn_password,
                            'quota_gb' => $quotaGb,
                            'ip_limit' => $ipLimit,
                            'purchase_price' => $unitPurchasePrice > 0 ? $unitPurchasePrice : null,
                            'purchase_days' => $durationDays,
                            'external_ref' => $order->order_ref,
                            'expired_at' => now()->addDays($durationDays),
                            'status' => 'provisioning',
                        ]);

                        $stagedVpnProvisions[] = [
                            'account' => $vpn,
                            'days' => $durationDays,
                            'sni_mode' => $sniMode,
                        ];
                    }
                }
            }

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

            // 7. Post-Commit VPN Provisioning (never holds DB transaction locks & uses Idempotency-Key)
            $vpnDetails = [];
            foreach ($stagedVpnProvisions as $entry) {
                try {
                    /** @var \App\Models\VpnAccount $vpnAcc */
                    $vpnAcc = $entry['account'];
                    $provisioned = $vpnService->provisionAccount(
                        $vpnAcc,
                        $entry['days'],
                        "order-{$order->order_ref}-acct-{$vpnAcc->id}",
                        $entry['sni_mode']
                    );
                    if (!empty($provisioned->config_link)) {
                        $vpnDetails[] = $provisioned->config_link;
                    }
                } catch (\Throwable $vpnErr) {
                    \Log::error('Post-commit VPN provisioning error: ' . $vpnErr->getMessage(), [
                        'order_ref' => $order->order_ref,
                        'vpn_account_id' => $entry['account']->id ?? null,
                    ]);
                }
            }

            try {
                \App\Services\TelegramService::notifyCustomerOrderDelivered($order, $reservedStock, $vpnDetails);
                \App\Services\TelegramService::updateAdminOrderMessage($order);
            } catch (\Exception $e) {
                \Log::error("Gagal mengirim notifikasi pengiriman pesanan ke pelanggan/admin: " . $e->getMessage());
            }
            
            return true;
        } catch (Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $e;
        }
    }
}
