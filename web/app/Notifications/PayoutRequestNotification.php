<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class PayoutRequestNotification extends Notification {
    use Queueable;
    public $sellerName;
    public function __construct($sellerName) { $this->sellerName = $sellerName; }
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable) {
        return [
            'type' => 'permintaan_payout',
            'title' => 'Permintaan Payout',
            'message' => 'Seller ' . $this->sellerName . ' meminta penarikan dana.',
            'url' => url('/admin/withdrawals'),
            'icon' => 'fas fa-money-bill-wave text-success'
        ];
    }
}