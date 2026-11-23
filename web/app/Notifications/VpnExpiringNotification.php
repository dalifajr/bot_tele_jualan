<?php

namespace App\Notifications;

use App\Models\VpnAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VpnExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        public VpnAccount $account,
        public string $stage = '3d'
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $isCritical = $this->stage === '1d';
        $expFormatted = $this->account->expired_at
            ? $this->account->expired_at->format('d M Y, H:i')
            : '-';
        $remaining = $this->account->expired_at
            ? $this->account->expired_at->diffForHumans()
            : '';

        return [
            'type' => 'vpn_expiring',
            'vpn_account_id' => $this->account->id,
            'stage' => $this->stage,
            'title' => $isCritical
                ? __('Akun VPN :user Segera Expired!', ['user' => $this->account->username])
                : __('Masa Aktif VPN :user Hampir Habis', ['user' => $this->account->username]),
            'message' => __(
                'Akun :proto (:user) akan berakhir pada :exp (:rem). Klik untuk perpanjang sekarang.',
                [
                    'proto' => strtoupper($this->account->protocol),
                    'user' => $this->account->username,
                    'exp' => $expFormatted,
                    'rem' => $remaining,
                ]
            ),
            'url' => route('vpn.my-accounts', ['renew' => $this->account->id]),
            'icon' => $isCritical
                ? 'fas fa-exclamation-triangle text-danger'
                : 'fas fa-clock text-warning',
        ];
    }
}
