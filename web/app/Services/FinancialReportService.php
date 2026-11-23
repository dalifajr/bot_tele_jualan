<?php

namespace App\Services;

use App\Models\EscrowTransaction;
use App\Models\HeldFund;
use App\Models\Order;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Resolve start & end Carbon range from preset or custom query inputs.
     */
    public function resolvePeriod(?string $preset, ?string $startInput, ?string $endInput): array
    {
        $preset = $preset ?: '30d';
        $now = now();

        if ($preset === 'custom' && $startInput && $endInput) {
            try {
                $start = Carbon::parse($startInput)->startOfDay();
                $end = Carbon::parse($endInput)->endOfDay();
                if ($start->greaterThan($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }
                return [
                    'preset' => 'custom',
                    'start' => $start,
                    'end' => $end,
                    'label' => $start->format('d M Y') . ' – ' . $end->format('d M Y'),
                ];
            } catch (\Throwable $e) {
                $preset = '30d';
            }
        }

        [$start, $end, $label] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), __('Hari Ini')],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), __('7 Hari Terakhir')],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), __('Bulan Ini')],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
                __('Bulan Lalu'),
            ],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay(), __('Tahun Ini')],
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), __('30 Hari Terakhir')],
        };

        return [
            'preset' => in_array($preset, ['today', '7d', '30d', 'this_month', 'last_month', 'this_year'], true) ? $preset : '30d',
            'start' => $start,
            'end' => $end,
            'label' => $label,
        ];
    }

    /**
     * Build complete P&L, Cash Flow, Liabilities, Trends, Product/Seller breakdown & Unified Ledger.
     */
    public function buildReport(Carbon $start, Carbon $end): array
    {
        // 1. Delivered Orders within period (use delivered_at fallback created_at)
        $deliveredOrders = Order::with(['customer', 'items.product.creator', 'stockUnits.seller', 'payment'])
            ->where('status', 'delivered')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('delivered_at', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->whereNull('delivered_at')->whereBetween('created_at', [$start, $end]);
                  });
            })
            ->orderByDesc('id')
            ->get();

        $cancelledCount = Order::whereIn('status', ['cancelled', 'expired'])
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $grossSubtotal = 0;
        $couponDiscounts = 0;
        $uniqueCodeRevenue = 0;
        $grossGmv = 0;
        $adminProductRevenue = 0;
        $sellerGrossSales = 0;
        $platformSellerCommission = 0;
        $sellerNetEarnings = 0;

        $productAgg = [];
        $sellerAgg = [];
        $dailyMap = [];

        // Initialize daily buckets (cap at 366 days for chart safety)
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        $dayGuard = 0;
        while ($cursor->lessThanOrEqualTo($endDay) && $dayGuard < 366) {
            $key = $cursor->format('Y-m-d');
            $dailyMap[$key] = [
                'date' => $key,
                'label' => $cursor->format('d M'),
                'gmv' => 0,
                'platform_revenue' => 0,
                'topup_in' => 0,
                'payout_out' => 0,
                'orders_count' => 0,
            ];
            $cursor->addDay();
            $dayGuard++;
        }

        foreach ($deliveredOrders as $order) {
            $orderSubtotal = (int) ($order->subtotal ?? 0);
            $orderDiscount = (int) ($order->discount_amount ?? 0);
            $orderUnique = (int) ($order->unique_code ?? 0);
            $netOrderSales = max(0, $orderSubtotal - $orderDiscount);

            $grossSubtotal += $orderSubtotal;
            $couponDiscounts += $orderDiscount;
            $uniqueCodeRevenue += $orderUnique;
            $grossGmv += $netOrderSales;

            $orderDateKey = ($order->delivered_at ?? $order->created_at ?? now())->format('Y-m-d');
            $orderPlatformContrib = $orderUnique;

            // Compute proportional item discounts mirroring OrderService
            $orderItems = $order->items;
            $stockByProduct = $order->stockUnits->groupBy('product_id');

            foreach ($orderItems as $item) {
                $product = $item->product;
                $qty = max(1, (int) $item->quantity);
                $itemGross = (int) $item->unit_price * $qty;
                $itemDiscount = (int) ($item->discount_amount ?? 0);
                if ($itemDiscount <= 0 && $orderDiscount > 0 && $orderSubtotal > 0) {
                    $itemDiscount = (int) round(($itemGross / $orderSubtotal) * $orderDiscount);
                }
                $itemNet = max(0, $itemGross - $itemDiscount);

                // Split unit-level between admin and seller stock
                $units = $stockByProduct->get($item->product_id, collect());
                $itemAdminRev = 0;
                $itemSellerGross = 0;
                $itemPlatformFee = 0;
                $itemSellerNet = 0;

                if ($units->isNotEmpty()) {
                    $unitCount = $units->count();
                    $baseDisc = (int) floor($itemDiscount / max(1, $unitCount));
                    $remDisc = $itemDiscount - ($baseDisc * max(1, $unitCount));

                    foreach ($units as $idx => $unit) {
                        $uDisc = $baseDisc + ($idx < $remDisc ? 1 : 0);
                        $uNet = max(0, (int) $item->unit_price - $uDisc);
                        $seller = $unit->seller;

                        if ($seller && $seller->role === 'seller') {
                            $feePct = $seller->commission_per_product
                                ? ($product->platform_fee_percent ?? $seller->platform_fee_percent ?? 10)
                                : ($seller->platform_fee_percent ?? 10);
                            $fee = (int) round(($uNet * $feePct) / 100);
                            $netToSeller = max(0, $uNet - $fee);

                            $itemSellerGross += $uNet;
                            $itemPlatformFee += $fee;
                            $itemSellerNet += $netToSeller;

                            $this->addSellerAgg($sellerAgg, $seller, 1, $uNet, $fee, $netToSeller);
                        } else {
                            $itemAdminRev += $uNet;
                        }
                    }
                } else {
                    // VPN or virtual item without StockUnit rows
                    $creator = $product?->creator;
                    if ($creator && $creator->role === 'seller') {
                        $feePct = $creator->commission_per_product
                            ? ($product->platform_fee_percent ?? $creator->platform_fee_percent ?? 10)
                            : ($creator->platform_fee_percent ?? 10);
                        $fee = (int) round(($itemNet * $feePct) / 100);
                        $netToSeller = max(0, $itemNet - $fee);

                        $itemSellerGross += $itemNet;
                        $itemPlatformFee += $fee;
                        $itemSellerNet += $netToSeller;

                        $this->addSellerAgg($sellerAgg, $creator, $qty, $itemNet, $fee, $netToSeller);
                    } else {
                        $itemAdminRev += $itemNet;
                    }
                }

                $adminProductRevenue += $itemAdminRev;
                $sellerGrossSales += $itemSellerGross;
                $platformSellerCommission += $itemPlatformFee;
                $sellerNetEarnings += $itemSellerNet;
                $orderPlatformContrib += ($itemAdminRev + $itemPlatformFee);

                // Product aggregation
                $pid = (int) ($item->product_id ?? 0);
                if (!isset($productAgg[$pid])) {
                    $productAgg[$pid] = [
                        'product_id' => $pid,
                        'name' => $product?->name ?? ('Produk #' . $pid),
                        'owner_type' => ($product?->creator && $product->creator->role === 'seller') ? 'seller' : 'admin',
                        'owner_name' => $product?->creator?->display_name ?? __('Toko Utama (Admin)'),
                        'units_sold' => 0,
                        'gmv' => 0,
                        'platform_revenue' => 0,
                    ];
                }
                $productAgg[$pid]['units_sold'] += $qty;
                $productAgg[$pid]['gmv'] += $itemNet;
                $productAgg[$pid]['platform_revenue'] += ($itemAdminRev + $itemPlatformFee);
            }

            if (isset($dailyMap[$orderDateKey])) {
                $dailyMap[$orderDateKey]['gmv'] += $netOrderSales;
                $dailyMap[$orderDateKey]['platform_revenue'] += $orderPlatformContrib;
                $dailyMap[$orderDateKey]['orders_count'] += 1;
            }
        }

        // 2. Escrow (Rekber) completed fees within period
        $completedEscrows = EscrowTransaction::where('status', EscrowTransaction::STATUS_COMPLETED)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('payout_at', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->whereNull('payout_at')->whereBetween('updated_at', [$start, $end]);
                  });
            })
            ->orderByDesc('id')
            ->get();

        $escrowFeeRevenue = (int) round($completedEscrows->sum(fn ($e) => (float) ($e->fee_amount ?? 0)));
        $escrowVolumeCompleted = (int) round($completedEscrows->sum(fn ($e) => (float) ($e->amount ?? 0)));

        foreach ($completedEscrows as $escrow) {
            $dKey = ($escrow->payout_at ?? $escrow->updated_at ?? now())->format('Y-m-d');
            if (isset($dailyMap[$dKey])) {
                $dailyMap[$dKey]['platform_revenue'] += (int) round((float) ($escrow->fee_amount ?? 0));
            }
        }

        // 3. Wallet Top-Ups, Spends, Manual Credits & Refunds within period
        $paidTopups = WalletTopup::with('user')
            ->where('status', 'paid')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('paid_at', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->whereNull('paid_at')->whereBetween('created_at', [$start, $end]);
                  });
            })
            ->orderByDesc('id')
            ->get();

        $topupConfirmedTotal = (int) $paidTopups->sum('amount');
        foreach ($paidTopups as $topup) {
            $dKey = ($topup->paid_at ?? $topup->created_at ?? now())->format('Y-m-d');
            if (isset($dailyMap[$dKey])) {
                $dailyMap[$dKey]['topup_in'] += (int) $topup->amount;
            }
        }

        $walletManualCredits = (int) WalletTransaction::where('type', 'credit')
            ->where('category', 'admin_credit')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $walletManualDebits = (int) WalletTransaction::where('type', 'debit')
            ->where('category', 'admin_debit')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $walletSpendTotal = (int) WalletTransaction::where('type', 'debit')
            ->whereIn('category', ['checkout', 'reseller_api_order'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $resellerApiRevenue = (int) WalletTransaction::where('type', 'debit')
            ->where('category', 'reseller_api_order')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $walletRefundTransactions = WalletTransaction::with('user')
            ->where('type', 'credit')
            ->whereIn('category', ['refund', 'warranty_refund', 'order_refund'])
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('id')
            ->get();
        $walletRefundTotal = (int) $walletRefundTransactions->sum('amount');

        // 4. Seller Payouts / Withdrawals within period
        $approvedWithdrawals = WithdrawalRequest::with('seller')
            ->where('status', 'approved')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('processed_at', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->whereNull('processed_at')->whereBetween('created_at', [$start, $end]);
                  });
            })
            ->orderByDesc('id')
            ->get();

        $payoutApprovedTotal = (int) $approvedWithdrawals->sum('amount');
        foreach ($approvedWithdrawals as $wd) {
            $dKey = ($wd->processed_at ?? $wd->created_at ?? now())->format('Y-m-d');
            if (isset($dailyMap[$dKey])) {
                $dailyMap[$dKey]['payout_out'] += (int) $wd->amount;
            }
        }

        $payoutPendingTotal = (int) WithdrawalRequest::where('status', 'pending')->sum('amount');
        $payoutPendingCount = WithdrawalRequest::where('status', 'pending')->count();

        // 5. Net Platform Revenue (Operating Profit before off-platform VPS costs)
        $grossPlatformIncome = $adminProductRevenue + $platformSellerCommission + $escrowFeeRevenue + $uniqueCodeRevenue;
        $netPlatformRevenue = $grossPlatformIncome - $walletRefundTotal;
        $ordersDeliveredCount = $deliveredOrders->count();
        $avgOrderValue = $ordersDeliveredCount > 0 ? (int) round($grossGmv / $ordersDeliveredCount) : 0;

        // 6. Current Real-Time Balance Sheet Liabilities (Snapshot NOW)
        $customerWalletLiability = (int) User::sum('customer_wallet_balance');
        $sellerAvailablePayable = (int) User::where('role', 'seller')->sum('wallet_balance');
        $heldFundsLiability = (int) HeldFund::where('status', 'held')->sum('amount');
        $activeEscrowLiability = (int) round((float) EscrowTransaction::whereIn('status', [
            EscrowTransaction::STATUS_FUNDS_HELD,
            EscrowTransaction::STATUS_DELIVERED,
            EscrowTransaction::STATUS_WAITING_PAYOUT,
            EscrowTransaction::STATUS_DISPUTED,
        ])->sum('total_buyer_pay'));

        $totalLiabilities = $customerWalletLiability + $sellerAvailablePayable + $heldFundsLiability + $activeEscrowLiability;

        $topCustomerWallets = User::where('customer_wallet_balance', '>', 0)
            ->orderByDesc('customer_wallet_balance')
            ->limit(10)
            ->get(['id', 'username', 'full_name', 'role', 'customer_wallet_balance']);

        $heldBySeller = HeldFund::select('seller_id', DB::raw('SUM(amount) as held_total'))
            ->where('status', 'held')
            ->groupBy('seller_id')
            ->pluck('held_total', 'seller_id');

        $topSellerPayables = User::where('role', 'seller')
            ->get(['id', 'username', 'full_name', 'wallet_balance', 'platform_fee_percent'])
            ->map(function ($seller) use ($heldBySeller) {
                $avail = (int) ($seller->wallet_balance ?? 0);
                $held = (int) ($heldBySeller[$seller->id] ?? 0);
                return [
                    'id' => $seller->id,
                    'name' => $seller->display_name,
                    'username' => $seller->username,
                    'available' => $avail,
                    'held' => $held,
                    'total' => $avail + $held,
                    'fee_percent' => (int) ($seller->platform_fee_percent ?? 10),
                ];
            })
            ->filter(fn ($row) => $row['total'] > 0)
            ->sortByDesc('total')
            ->take(10)
            ->values();

        // 7. Sort top products & sellers
        $topProducts = collect($productAgg)->sortByDesc('platform_revenue')->take(12)->values();
        $topSellers = collect($sellerAgg)->sortByDesc('gmv')->take(12)->values();

        // 8. Unified Financial Ledger
        $ledger = collect();

        foreach ($deliveredOrders->take(40) as $order) {
            $ledger->push([
                'date' => $order->delivered_at ?? $order->created_at,
                'type' => 'order',
                'type_label' => __('Pesanan Selesai'),
                'badge' => 'success',
                'direction' => 'in',
                'reference' => $order->order_ref,
                'party' => $order->customer?->display_name ?? __('Pelanggan'),
                'detail' => $order->items->map(fn ($i) => ($i->vpn_meta['label'] ?? $i->product?->name ?? 'Produk') . ' x' . $i->quantity)->implode(', '),
                'amount' => (int) $order->total_amount,
                'source' => $order->payment?->source_app ?? 'DIRECT',
            ]);
        }

        foreach ($paidTopups->take(25) as $topup) {
            $ledger->push([
                'date' => $topup->paid_at ?? $topup->created_at,
                'type' => 'topup',
                'type_label' => __('Top-Up Dompet'),
                'badge' => 'primary',
                'direction' => 'in',
                'reference' => $topup->topup_ref,
                'party' => $topup->user?->display_name ?? ('User #' . $topup->user_id),
                'detail' => __('Deposit Dompet Saldo') . ' (' . ($topup->verified_source ?: 'QRIS') . ')',
                'amount' => (int) $topup->amount,
                'source' => $topup->verified_source ?: 'QRIS',
            ]);
        }

        foreach ($approvedWithdrawals->take(25) as $wd) {
            $ledger->push([
                'date' => $wd->processed_at ?? $wd->created_at,
                'type' => 'payout',
                'type_label' => __('Payout Seller'),
                'badge' => 'danger',
                'direction' => 'out',
                'reference' => 'WD-' . $wd->id,
                'party' => $wd->seller?->display_name ?? ('Seller #' . $wd->seller_id),
                'detail' => trim(($wd->bank_name ?? '') . ' - ' . ($wd->account_number ?? '')),
                'amount' => (int) $wd->amount,
                'source' => 'BANK_TRANSFER',
            ]);
        }

        foreach ($completedEscrows->take(20) as $escrow) {
            $ledger->push([
                'date' => $escrow->payout_at ?? $escrow->updated_at,
                'type' => 'escrow_fee',
                'type_label' => __('Fee Rekber'),
                'badge' => 'info',
                'direction' => 'in',
                'reference' => $escrow->transaction_code,
                'party' => $escrow->buyer_name ?: __('Rekber'),
                'detail' => __('Fee transaksi rekber:') . ' ' . $escrow->title,
                'amount' => (int) round((float) ($escrow->fee_amount ?? 0)),
                'source' => 'ESCROW',
            ]);
        }

        foreach ($walletRefundTransactions->take(20) as $refTx) {
            $ledger->push([
                'date' => $refTx->created_at,
                'type' => 'refund',
                'type_label' => __('Refund Dompet'),
                'badge' => 'warning',
                'direction' => 'out',
                'reference' => $refTx->reference ?: ('REF-' . $refTx->id),
                'party' => $refTx->user?->display_name ?? ('User #' . $refTx->user_id),
                'detail' => $refTx->description ?: __('Pengembalian dana garansi / refund'),
                'amount' => (int) $refTx->amount,
                'source' => 'WALLET_REFUND',
            ]);
        }

        $ledger = $ledger->sortByDesc(fn ($row) => optional($row['date'])->timestamp ?? 0)->values();

        return [
            'pnl' => [
                'gross_subtotal' => $grossSubtotal,
                'coupon_discounts' => $couponDiscounts,
                'gross_gmv' => $grossGmv,
                'admin_product_revenue' => $adminProductRevenue,
                'seller_gross_sales' => $sellerGrossSales,
                'platform_seller_commission' => $platformSellerCommission,
                'seller_net_earnings' => $sellerNetEarnings,
                'escrow_fee_revenue' => $escrowFeeRevenue,
                'unique_code_revenue' => $uniqueCodeRevenue,
                'gross_platform_income' => $grossPlatformIncome,
                'wallet_refund_total' => $walletRefundTotal,
                'net_platform_revenue' => $netPlatformRevenue,
                'orders_delivered_count' => $ordersDeliveredCount,
                'orders_cancelled_count' => $cancelledCount,
                'avg_order_value' => $avgOrderValue,
                'reseller_api_revenue' => $resellerApiRevenue,
            ],
            'cashflow' => [
                'topup_confirmed_total' => $topupConfirmedTotal,
                'topup_count' => $paidTopups->count(),
                'wallet_manual_credits' => $walletManualCredits,
                'wallet_manual_debits' => $walletManualDebits,
                'wallet_spend_total' => $walletSpendTotal,
                'payout_approved_total' => $payoutApprovedTotal,
                'payout_approved_count' => $approvedWithdrawals->count(),
                'payout_pending_total' => $payoutPendingTotal,
                'payout_pending_count' => $payoutPendingCount,
                'escrow_volume_completed' => $escrowVolumeCompleted,
                'escrow_completed_count' => $completedEscrows->count(),
            ],
            'liabilities' => [
                'customer_wallet_liability' => $customerWalletLiability,
                'seller_available_payable' => $sellerAvailablePayable,
                'held_funds_liability' => $heldFundsLiability,
                'active_escrow_liability' => $activeEscrowLiability,
                'total_liabilities' => $totalLiabilities,
                'top_customer_wallets' => $topCustomerWallets,
                'top_seller_payables' => $topSellerPayables,
            ],
            'daily_series' => array_values($dailyMap),
            'top_products' => $topProducts,
            'top_sellers' => $topSellers,
            'ledger' => $ledger,
        ];
    }

    protected function addSellerAgg(array &$sellerAgg, User $seller, int $units, int $gross, int $fee, int $net): void
    {
        $sid = (int) $seller->id;
        if (!isset($sellerAgg[$sid])) {
            $sellerAgg[$sid] = [
                'seller_id' => $sid,
                'name' => $seller->display_name,
                'username' => $seller->username,
                'units_sold' => 0,
                'gmv' => 0,
                'platform_fee' => 0,
                'seller_net' => 0,
            ];
        }

        $sellerAgg[$sid]['units_sold'] += $units;
        $sellerAgg[$sid]['gmv'] += $gross;
        $sellerAgg[$sid]['platform_fee'] += $fee;
        $sellerAgg[$sid]['seller_net'] += $net;
    }
}
