<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Kirim notifikasi pesanan baru ke semua admin yang memiliki telegram_id.
     */
    public static function notifyAdminNewOrder(Order $order)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        
        if (empty($botToken)) {
            Log::warning("TELEGRAM_BOT_TOKEN kosong, notifikasi ke admin dilewati.");
            return;
        }

        $admins = User::where('role', 'admin')->whereNotNull('telegram_id')->get();
        if ($admins->isEmpty()) {
            return;
        }

        $customerName = $order->customer->full_name ?? $order->customer->username ?? '-';
        $itemCount = $order->items->count();
        $firstItem = $order->items->first();
        $productName = $firstItem ? $firstItem->product->name : 'Produk';
        
        if ($itemCount > 1) {
            $productName .= " (+" . ($itemCount - 1) . " item)";
        }

        $totalQty = $order->items->sum('quantity');

        $text = "🆕 <b>Pesanan Baru (Via Web)</b>\n"
              . "Order Ref: <code>{$order->order_ref}</code>\n"
              . "Customer: " . htmlspecialchars($customerName) . " ({$order->customer->telegram_id})\n"
              . "Item: " . htmlspecialchars($productName) . "\n"
              . "Qty: {$totalQty}\n"
              . "Total Bayar: <b>Rp " . number_format($order->total_amount, 0, ',', '.') . "</b>\n\n"
              . "Status: <b>🟡 Menunggu Pembayaran</b>\n"
              . "🕒 Dibuat dari Website.";

        // Samakan callback_data dengan bot Python
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Pembayaran Diterima', 'callback_data' => "adm:ord:paid:{$order->order_ref}"]
                ],
                [
                    ['text' => '❌ Batalkan Pesanan', 'callback_data' => "adm:ord:cancel:{$order->order_ref}"]
                ]
            ]
        ];

        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

        foreach ($admins as $admin) {
            try {
                Http::post($apiUrl, [
                    'chat_id' => $admin->telegram_id,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ]);
            } catch (\Exception $e) {
                Log::error("Gagal mengirim notifikasi admin ke {$admin->telegram_id}: " . $e->getMessage());
            }
        }
    }
}
