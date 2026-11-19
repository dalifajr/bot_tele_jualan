<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Atomically confirm a wallet top-up and credit the user's balance exactly once.
     * When $allowManualOverride is true, admins can manually confirm expired/cancelled topups (never already-paid ones).
     */
    public function confirmTopup(
        int $topupId,
        ?int $actorId = null,
        ?string $verifiedSource = null,
        ?string $adminNote = null,
        bool $allowManualOverride = false
    ): WalletTopup {
        $topup = DB::transaction(function () use ($topupId, $actorId, $verifiedSource, $adminNote, $allowManualOverride) {
            $locked = WalletTopup::where('id', $topupId)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid') {
                throw new \RuntimeException(__('Permintaan top-up ini sudah dikonfirmasi sebelumnya.'));
            }

            if (!$allowManualOverride && $locked->status !== 'pending') {
                throw new \RuntimeException(__('Permintaan top-up ini sudah diproses sebelumnya.'));
            }

            $source = $verifiedSource ?: ($actorId ? 'ADMIN_MANUAL' : 'AUTO_WEBHOOK');
            $previousStatus = $locked->status;

            $locked->status = 'paid';
            $locked->verified_source = substr($source, 0, 80);
            $locked->verified_by = $actorId;
            if ($adminNote !== null && trim($adminNote) !== '') {
                $locked->admin_note = substr(trim($adminNote), 0, 500);
            }
            $locked->paid_at = now();
            $locked->save();

            $desc = __('Top-up saldo dompet (:ref)', ['ref' => $locked->topup_ref]);
            if ($allowManualOverride && $previousStatus !== 'pending') {
                $desc .= ' [' . __('Konfirmasi Manual Admin') . ']';
            }

            $this->credit(
                (int) $locked->user_id,
                (int) $locked->amount,
                'topup',
                $locked->topup_ref,
                $desc,
                [
                    'topup_id' => $locked->id,
                    'actor_id' => $actorId,
                    'verified_source' => $source,
                    'previous_status' => $previousStatus,
                ]
            );

            return $locked->fresh('user');
        });

        // Notify user via Telegram if linked
        try {
            $user = $topup->user;
            if ($user && !empty($user->telegram_id)) {
                $amountFmt = number_format((int) $topup->amount, 0, ',', '.');
                $balanceFmt = number_format((int) ($user->customer_wallet_balance ?? 0), 0, ',', '.');
                TelegramService::sendMessage(
                    $user->telegram_id,
                    "✅ <b>Top-Up Dompet Saldo Berhasil!</b>\n\nReferensi: <code>{$topup->topup_ref}</code>\nNominal Masuk: <b>Rp {$amountFmt}</b>\nSaldo Dompet Sekarang: <b>Rp {$balanceFmt}</b>\n\nSaldo siap digunakan untuk checkout instan 1-klik."
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify Telegram on wallet topup confirmation: ' . $e->getMessage());
        }

        return $topup;
    }
}
