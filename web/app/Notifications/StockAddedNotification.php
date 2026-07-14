<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class StockAddedNotification extends Notification {
    use Queueable;
    public $product;
    public $count;
    public function __construct($product, $count) { $this->product = $product; $this->count = $count; }
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable) {
        return [
            'type' => 'stok_ditambah',
            'title' => 'Stok Ditambah',
            'message' => $this->count . ' stok ditambahkan untuk "' . $this->product->name . '"',
            'url' => route('admin.products.index'),
            'icon' => 'fas fa-box-open text-success'
        ];
    }
}