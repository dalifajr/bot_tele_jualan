<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WalletActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $event, // 'topup_paid' | 'topup_rejected' | 'admin_credit' | 'admin_debit'
        public int $amount,
        public int $balanceAfter,
        public string $reference = '',
        public ?string $note = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $amountFmt = number_format($this->amount, 0, ',', '.');
        $balanceFmt = number_format($this->balanceAfter, 0, ',', '.');
        $noteText = trim((string) ($this->note ?? ''));

        return match ($this->event) {
            'topup_paid' => [
                'type' => 'wallet_topup_paid',
                'reference' => $this->reference,
                'title' => __('Top-Up Saldo Rp :amount Berhasil', ['amount' => $amountFmt]),
                'message' => __('Top-up :ref sebesar Rp :amount telah masuk ke Dompet Anda. Saldo sekarang: Rp :balance.', [
                    'ref' => $this->reference ?: '-',
                    'amount' => $amountFmt,
                    'balance' => $balanceFmt,
                ]),
                'url' => route('wallet.index'),
                'icon' => 'fas fa-wallet text-success',
            ],
            'topup_rejected' => [
                'type' => 'wallet_topup_rejected',
                'reference' => $this->reference,
                'title' => __('Permintaan Top-Up :ref Dibatalkan', ['ref' => $this->reference ?: '-']),
                'message' => __('Permintaan top-up sebesar Rp :amount telah dibatalkan oleh Admin.', [
                    'amount' => $amountFmt,
                ]),
                'url' => route('wallet.index'),
                'icon' => 'fas fa-times-circle text-danger',
            ],
            'admin_debit' => [
                'type' => 'wallet_admin_debit',
                'reference' => $this->reference,
                'title' => __('Penyesuaian Saldo -Rp :amount oleh Admin', ['amount' => $amountFmt]),
                'message' => __('Saldo Dompet dikurangi Rp :amount oleh Admin (Ref: :ref).:note Saldo sekarang: Rp :balance.', [
                    'amount' => $amountFmt,
                    'ref' => $this->reference ?: '-',
                    'note' => $noteText !== '' ? ' ' . __('Catatan:') . ' ' . $noteText . '.' : '',
                    'balance' => $balanceFmt,
                ]),
                'url' => route('wallet.index'),
                'icon' => 'fas fa-minus-circle text-warning',
            ],
            default => [
                'type' => 'wallet_admin_credit',
                'reference' => $this->reference,
                'title' => __('Saldo +Rp :amount Ditambahkan oleh Admin', ['amount' => $amountFmt]),
                'message' => __('Admin menambahkan Rp :amount ke Dompet Anda (Ref: :ref).:note Saldo sekarang: Rp :balance.', [
                    'amount' => $amountFmt,
                    'ref' => $this->reference ?: '-',
                    'note' => $noteText !== '' ? ' ' . __('Catatan:') . ' ' . $noteText . '.' : '',
                    'balance' => $balanceFmt,
                ]),
                'url' => route('wallet.index'),
                'icon' => 'fas fa-plus-circle text-success',
            ],
        };
    }
}
