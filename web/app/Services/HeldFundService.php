<?php

namespace App\Services;

use App\Models\ComplaintCase;
use App\Models\HeldFund;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HeldFundService
{
    /**
     * Release all eligible held funds whose warranty period has passed and have no active complaints.
     *
     * @param bool $verbose
     * @param \Closure|null $logger
     * @return array
     */
    public static function releaseEligibleFunds(bool $verbose = false, ?\Closure $logger = null): array
    {
        $lock = Cache::lock('held_funds_release_lock', 25);
        if (!$lock->get()) {
            return [
                'released_count' => 0,
                'released_amount' => 0,
                'skipped_complaints' => 0,
                'locked' => true,
            ];
        }

        $releasedCount = 0;
        $releasedAmount = 0;
        $skippedComplaints = 0;

        try {
            $now = now();
            $expiredFunds = HeldFund::where('status', 'held')
                ->where('release_at', '<=', $now)
                ->with(['seller', 'order', 'product'])
                ->get();

            if ($expiredFunds->isEmpty()) {
                if ($logger) {
                    $logger('Tidak ada dana tertahan yang memenuhi syarat untuk dilepaskan.');
                }
                return [
                    'released_count' => 0,
                    'released_amount' => 0,
                    'skipped_complaints' => 0,
                    'locked' => false,
                ];
            }

            foreach ($expiredFunds as $fund) {
                // If the order has an active/unresolved complaint, hold the funds until resolved
                $hasActiveComplaint = ComplaintCase::where('order_id', $fund->order_id)
                    ->whereNotIn('status', ['done', 'rejected', 'refund_transferred', 'refunded_to_wallet'])
                    ->exists();

                if ($hasActiveComplaint) {
                    $skippedComplaints++;
                    if ($logger) {
                        $logger("Dana ID {$fund->id} (Order #{$fund->order_id}) ditahan karena ada komplain yang sedang aktif.");
                    }
                    continue;
                }

                DB::beginTransaction();
                try {
                    $seller = User::where('id', $fund->seller_id)->lockForUpdate()->first();
                    if (!$seller) {
                        throw new \Exception("Seller (ID: {$fund->seller_id}) not found for held fund ID {$fund->id}.");
                    }

                    // 1. Mark fund as released
                    $fund->status = 'released';
                    $fund->save();

                    // 2. Increment seller wallet balance
                    $seller->increment('wallet_balance', $fund->amount);

                    // 3. Insert Audit Log
                    DB::table('audit_logs')->insert([
                        'action' => 'held_fund_released',
                        'actor_id' => null, // System / Auto Action
                        'entity_type' => 'held_funds',
                        'entity_id' => $fund->id,
                        'detail' => "seller_id={$seller->id}; amount={$fund->amount}; order_id={$fund->order_id}; auto=true",
                        'created_at' => now(),
                    ]);

                    DB::commit();

                    $releasedCount++;
                    $releasedAmount += (int) $fund->amount;

                    if ($logger) {
                        $logger("Berhasil melepaskan dana ID {$fund->id} sejumlah Rp " . number_format($fund->amount, 0, ',', '.') . " ke seller {$seller->username}.");
                    }

                    // 4. Send Telegram notification to seller
                    try {
                        $orderRef = $fund->order->order_ref ?? 'N/A';
                        $productName = $fund->product->name ?? 'Produk Garansi';
                        TelegramService::notifySellerFundsReleased($seller, $fund->amount, $orderRef, $productName);
                    } catch (\Throwable $te) {
                        Log::warning("Gagal mengirim notifikasi pelepasan dana ke Telegram seller: " . $te->getMessage());
                    }
                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::error("Failed to release held fund ID {$fund->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    if ($logger) {
                        $logger("Gagal melepaskan dana ID {$fund->id}: " . $e->getMessage());
                    }
                }
            }
        } finally {
            $lock->release();
        }

        return [
            'released_count' => $releasedCount,
            'released_amount' => $releasedAmount,
            'skipped_complaints' => $skippedComplaints,
            'locked' => false,
        ];
    }

    /**
     * Opportunistic release trigger during user/seller activity.
     * Throttled via cache cooldown so it runs at most once every 60 seconds across all web requests.
     */
    public static function runOpportunisticRelease(): void
    {
        $cooldownKey = 'held_funds_opportunistic_cooldown';
        if (Cache::has($cooldownKey)) {
            return;
        }

        Cache::put($cooldownKey, true, 60);

        try {
            self::releaseEligibleFunds(false);
        } catch (\Throwable $e) {
            Log::warning("Opportunistic held funds release encountered an issue: " . $e->getMessage());
        }
    }
}
