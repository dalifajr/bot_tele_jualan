<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Atomically credit a user's customer wallet balance.
     */
    public function credit(
        int $userId,
        int $amount,
        string $category,
        ?string $reference = null,
        ?string $description = null,
        array $meta = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal kredit dompet harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($userId, $amount, $category, $reference, $description, $meta) {
            $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();
            $before = (int) ($user->customer_wallet_balance ?? 0);
            $after = $before + $amount;

            $user->customer_wallet_balance = $after;
            $user->save();

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'credit',
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => $reference,
                'description' => $description,
                'meta' => $meta ?: null,
            ]);
        });
    }

    /**
     * Atomically debit a user's customer wallet balance (throws if insufficient).
     */
    public function debit(
        int $userId,
        int $amount,
        string $category,
        ?string $reference = null,
        ?string $description = null,
        array $meta = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal debit dompet harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($userId, $amount, $category, $reference, $description, $meta) {
            $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();
            $before = (int) ($user->customer_wallet_balance ?? 0);

            if ($before < $amount) {
                throw new \RuntimeException(__('Saldo dompet Anda tidak mencukupi.'));
            }

            $after = $before - $amount;
            $user->customer_wallet_balance = $after;
            $user->save();

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference' => $reference,
                'description' => $description,
                'meta' => $meta ?: null,
            ]);
        });
    }

    /**
     * Atomically confirm a pending wallet top-up and credit the user's balance once.
     */
    public function confirmTopup(int $topupId, ?int $actorId = null): WalletTopup
    {
        return DB::transaction(function () use ($topupId, $actorId) {
            $topup = WalletTopup::where('id', $topupId)->lockForUpdate()->firstOrFail();

            if ($topup->status !== 'pending') {
                throw new \RuntimeException(__('Permintaan top-up ini sudah diproses sebelumnya.'));
            }

            $topup->status = 'paid';
            $topup->paid_at = now();
            $topup->save();

            $this->credit(
                (int) $topup->user_id,
                (int) $topup->amount,
                'topup',
                $topup->topup_ref,
                __('Top-up saldo dompet (:ref)', ['ref' => $topup->topup_ref]),
                ['topup_id' => $topup->id, 'actor_id' => $actorId]
            );

            return $topup;
        });
    }
}
