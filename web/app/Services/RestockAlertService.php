<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductWishlist;
use App\Notifications\RestockAlertNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RestockAlertService
{
    /**
     * Notify all users who subscribed to restock alerts for a given product.
     */
    public static function notifySubscribers(Product $product, int $addedQty = 1): int
    {
        if ($addedQty <= 0) {
            return 0;
        }

        $subscribers = ProductWishlist::with('user')
            ->where('product_id', $product->id)
            ->where('notify_restock', true)
            ->get();

        if ($subscribers->isEmpty()) {
            return 0;
        }

        $botToken = config('telegram.bot_token');
        $productUrl = route('catalog.show', $product->id);
        $notifiedCount = 0;

        foreach ($subscribers as $wish) {
            $user = $wish->user;
            if (!$user || $user->is_suspended) {
                continue;
            }

            try {
                $user->notify(new RestockAlertNotification($product, $addedQty));
            } catch (\Throwable $e) {
                Log::warning('Failed to create in-app restock alert notification: ' . $e->getMessage());
            }

            if ($botToken && !empty($user->telegram_id)) {
                try {
                    Http::timeout(4)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $user->telegram_id,
                        'text' => "🔔 *Stok Baru Tersedia!*\n\nProduk *{$product->name}* baru saja di-restok (+{$addedQty} unit).\n\nSegera amankan sebelum kehabisan:\n{$productUrl}",
                        'parse_mode' => 'Markdown',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to send Telegram restock alert: ' . $e->getMessage());
                }
            }

            $wish->update([
                'notify_restock' => false,
                'last_notified_at' => now(),
            ]);
            $notifiedCount++;
        }

        return $notifiedCount;
    }
}
