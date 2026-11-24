<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStockReplacedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public int $replacedCount = 1,
        public array $productNames = []
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $prodLabel = !empty($this->productNames)
            ? implode(', ', array_unique(array_filter($this->productNames)))
            : __('Produk Pesanan');

        return [
            'type' => 'order_stock_replaced',
            'order_id' => $this->order->id,
            'order_ref' => $this->order->order_ref,
            'replaced_count' => $this->replacedCount,
            'title' => __('Akun pada Pesanan #:ref Telah Diganti Admin', ['ref' => $this->order->order_ref]),
            'message' => __('Admin telah mengganti :count detail akun (:product) pada pesanan #:ref. Klik untuk melihat detail akun pengganti yang baru.', [
                'count' => $this->replacedCount,
                'product' => $prodLabel,
                'ref' => $this->order->order_ref,
            ]),
            'url' => route('orders.show', $this->order->id),
            'icon' => 'fas fa-sync-alt text-info',
        ];
    }
}
