<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class OrderStatusNotification extends Notification {
    use Queueable;
    public $orderId;
    public $statusMessage;
    public function __construct($orderId, $statusMessage) { $this->orderId = $orderId; $this->statusMessage = $statusMessage; }
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable) {
        return [
            'type' => 'status_pesanan',
            'title' => 'Pembaruan Pesanan #' . $this->orderId,
            'message' => $this->statusMessage,
            'url' => url('/orders/' . $this->orderId),
            'icon' => 'fas fa-info-circle text-primary'
        ];
    }
}