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
        $isAdmin = ($notifiable->role ?? null) === 'admin';
        $url = $isAdmin
            ? route('admin.orders.index', ['search' => $this->order->order_ref])
            : ($this->order->status === 'pending_payment'
                ? route('checkout.success', ['order_ref' => $this->order->order_ref])
                : route('orders.show', $this->order->id));

        return [
            'type' => 'pesanan_baru',
            'title' => ($isAdmin ? 'Pesanan Baru #' : 'Tagihan Pesanan #') . $this->order->id,
            'message' => ($isAdmin ? 'Pesanan baru senilai Rp' : 'Tagihan pembayaran senilai Rp') . number_format($this->order->total_amount ?? 0, 0, ',', '.'),
            'url' => $url,
            'icon' => 'fas fa-shopping-cart text-primary'
        ];
    }
}