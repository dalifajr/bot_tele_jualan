<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RestockAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Product $product,
        protected int $addedQty = 1
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'restock_alert',
            'icon' => 'fas fa-bell-concierge',
            'title' => 'Stok Produk Favorit Tersedia!',
            'message' => "{$this->product->name} baru saja di-restok (+{$this->addedQty} unit). Segera checkout sebelum kehabisan.",
            'url' => route('catalog.show', $this->product->id),
            'product_id' => $this->product->id,
        ];
    }
}
