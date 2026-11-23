<?php

namespace App\Notifications;

use App\Models\VpnAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VpnOwnerTransferredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public VpnAccount $account
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $expFormatted = $this->account->expired_at
            ? $this->account->expired_at->format('d M Y, H:i')
            : '-';

        return [
            'type' => 'vpn_owner_transferred',
            'vpn_account_id' => $this->account->id,
            'title' => __('Akun VPN :user Kini Milik Anda', ['user' => $this->account->username]),
            'message' => __(
                'Admin telah memindahkan kepemilikan akun :proto (:user) ke akun Anda (aktif hingga :exp). Kelola konfigurasi & perpanjangan di menu Akun VPN Saya.',
                [
                    'proto' => strtoupper($this->account->protocol),
                    'user' => $this->account->username,
                    'exp' => $expFormatted,
                ]
            ),
            'url' => route('vpn.my-accounts'),
            'icon' => 'fas fa-user-check text-primary',
        ];
    }
}
