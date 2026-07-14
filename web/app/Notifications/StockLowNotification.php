<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class StockLowNotification extends Notification {
    use Queueable;
    public $product;
    public $count;
    public function __construct($product, $count) { $this->product = $product; $this->count = $count; }
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable) {
        return [
            'type' => 'stok_menipis',
            'title' => 'Stok Menipis',
            'message' => 'Stok "' . $this->product->name . '" tersisa ' . $this->count . '!',
            'url' => route('admin.products.index'),
            'icon' => 'fas fa-exclamation-triangle text-warning'
        ];
    }
}