<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class OrderCreatedNotification extends Notification {
    use Queueable;
    public $order;
    public function __construct($order) { $this->order = $order; }
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable) {
        return [
            'type' => 'pesanan_baru',
            'title' => 'Pesanan Baru #' . $this->order->id,
            'message' => 'Pesanan baru senilai Rp' . number_format($this->order->total_price ?? 0, 0, ',', '.'),
            'url' => route('admin.orders.show', $this->order->id),
            'icon' => 'fas fa-shopping-cart text-primary'
        ];
    }
}