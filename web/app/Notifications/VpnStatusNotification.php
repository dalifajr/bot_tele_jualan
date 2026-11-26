<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\VpnAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VpnStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public VpnAccount $account,
        public string $action, // 'suspended' | 'unsuspended'
        public ?string $reason = null,
        public string $actorRole = 'admin' // 'admin' | 'system'
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $proto = strtoupper((string) $this->account->protocol);
        $user = (string) $this->account->username;

        if ($this->action === 'suspended') {
            $reasonText = trim((string) ($this->reason ?? ''));
            $isSystem = $this->actorRole === 'system';
            $adminId = $this->account->suspended_by_user_id
                ?: User::where('role', 'admin')->orderBy('id')->value('id');
            $url = $adminId
                ? route('chat.index', ['contact_id' => $adminId])
                : route('vpn.my-accounts');

            if ($isSystem) {
                return [
                    'type' => 'vpn_system_suspended',
                    'vpn_account_id' => $this->account->id,
                    'action' => 'suspended',
                    'actor_role' => 'system',
                    'title' => __('Akun VPN :user Disuspend Otomatis oleh Sistem', ['user' => $user]),
                    'message' => $reasonText !== ''
                        ? __('Akun :proto (:user) telah disuspend otomatis oleh sistem. Alasan: :reason. Hubungi Admin melalui Chat untuk membuka suspend.', [
                            'proto' => $proto,
                            'user' => $user,
                            'reason' => $reasonText,
                        ])
                        : __('Akun :proto (:user) telah disuspend otomatis oleh sistem karena pelanggaran batas penggunaan. Hubungi Admin melalui Chat untuk membuka suspend.', [
                            'proto' => $proto,
                            'user' => $user,
                        ]),
                    'url' => $url,
                    'icon' => 'fas fa-shield-exclamation text-danger',
                ];
            }

            return [
                'type' => 'vpn_admin_suspended',
                'vpn_account_id' => $this->account->id,
                'action' => 'suspended',
                'actor_role' => 'admin',
                'title' => __('Akun VPN :user Disuspend oleh Admin', ['user' => $user]),
                'message' => $reasonText !== ''
                    ? __('Akun :proto (:user) telah disuspend oleh Admin. Alasan: :reason. Hubungi Admin melalui Chat untuk membuka suspend.', [
                        'proto' => $proto,
                        'user' => $user,
                        'reason' => $reasonText,
                    ])
                    : __('Akun :proto (:user) telah disuspend oleh Admin. Hubungi Admin melalui Chat untuk membuka suspend.', [
                        'proto' => $proto,
                        'user' => $user,
                    ]),
                'url' => $url,
                'icon' => 'fas fa-ban text-danger',
            ];
        }

        return [
            'type' => 'vpn_admin_unsuspended',
            'vpn_account_id' => $this->account->id,
            'action' => 'unsuspended',
            'title' => __('Suspend Akun VPN :user Telah Dibuka', ['user' => $user]),
            'message' => __('Akun :proto (:user) telah diaktifkan kembali oleh Admin dan kini siap digunakan kembali.', [
                'proto' => $proto,
                'user' => $user,
            ]),
            'url' => route('vpn.my-accounts'),
            'icon' => 'fas fa-check-circle text-success',
        ];
    }
}
