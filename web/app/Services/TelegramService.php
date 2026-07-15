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
        $botToken = config('services.telegram.bot_token');
        
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
        $expiresAt = $order->expires_at ? $order->expires_at->format('d M Y, H:i') : '-';

        $text = "🆕 <b>Pesanan Baru (Via Web)</b>\n"
              . "Order Ref: <code>{$order->order_ref}</code>\n"
              . "Customer: " . htmlspecialchars($customerName) . " ({$order->customer->telegram_id})\n"
              . "Item: " . htmlspecialchars($productName) . "\n"
              . "Qty: {$totalQty}\n"
              . "Total Bayar: <b>Rp " . number_format($order->total_amount, 0, ',', '.') . "</b>\n"
              . "Batas Bayar: {$expiresAt} WIB\n\n"
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
                $response = Http::post($apiUrl, [
                    'chat_id' => $admin->telegram_id,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ]);

                if ($response->successful() && !$order->admin_notify_message_id) {
                    $order->admin_notify_chat_id = $admin->telegram_id;
                    $order->admin_notify_message_id = $response->json('result.message_id');
                    $order->save();
                }
            } catch (\Exception $e) {
                Log::error("Gagal mengirim notifikasi admin ke {$admin->telegram_id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Update pesan notifikasi admin ketika status pesanan berubah.
     */
    public static function updateAdminOrderMessage(Order $order)
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) return;

        $chatId = $order->admin_notify_chat_id;
        $messageId = $order->admin_notify_message_id;

        if (!$chatId || !$messageId) {
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

        // Tentukan label status & emoji
        $statusText = '';
        if ($order->status === 'pending_payment') {
            $statusText = '🟡 Menunggu Pembayaran';
        } elseif (in_array($order->status, ['completed', 'paid', 'delivered'])) {
            $statusText = '🟢 Selesai';
        } elseif ($order->status === 'cancelled') {
            $statusText = '❌ Dibatalkan';
        } elseif ($order->status === 'expired') {
            $statusText = '⏰ Kadaluarsa / Waktu Habis';
        } else {
            $statusText = strtoupper($order->status);
        }

        $expiresAt = $order->expires_at ? $order->expires_at->format('d M Y, H:i') : '-';

        $text = "🆕 <b>Pesanan Baru (Via Web)</b>\n"
              . "Order Ref: <code>{$order->order_ref}</code>\n"
              . "Customer: " . htmlspecialchars($customerName) . " (" . ($order->customer->telegram_id ?? '-') . ")\n"
              . "Item: " . htmlspecialchars($productName) . "\n"
              . "Qty: {$totalQty}\n"
              . "Total Bayar: <b>Rp " . number_format($order->total_amount, 0, ',', '.') . "</b>\n"
              . "Batas Bayar: {$expiresAt} WIB\n\n"
              . "Status: <b>{$statusText}</b>\n"
              . "🕒 Dibuat dari Website.";

        // Jika statusnya bukan pending_payment, sembunyikan tombol aksi admin
        $keyboard = null;
        if ($order->status === 'pending_payment') {
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
        }

        $apiUrl = "https://api.telegram.org/bot{$botToken}/editMessageText";

        try {
            Http::post($apiUrl, [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard ? json_encode($keyboard) : json_encode(['inline_keyboard' => []])
            ]);
        } catch (\Exception $e) {
            Log::error("Gagal memperbarui notifikasi admin untuk Order {$order->order_ref}: " . $e->getMessage());
        }
    }


    /**
     * Kirim pesan tagihan + QRIS ke pelanggan yang melakukan order via Web.
     */
    public static function notifyCustomerNewOrder(Order $order, ?string $dynamicQris)
    {
        $botToken = config('services.telegram.bot_token');
        
        if (empty($botToken)) {
            Log::warning("TELEGRAM_BOT_TOKEN kosong, notifikasi ke customer dilewati.");
            return;
        }

        $customerTelegramId = $order->customer->telegram_id ?? null;
        if (!$customerTelegramId) {
            return;
        }

        $itemCount = $order->items->count();
        $firstItem = $order->items->first();
        $productName = $firstItem ? $firstItem->product->name : 'Produk';
        if ($itemCount > 1) {
            $productName .= " (+" . ($itemCount - 1) . " item)";
        }

        $totalQty = $order->items->sum('quantity');
        $expiresAt = $order->expires_at ? $order->expires_at->format('d M Y, H:i') : '-';

        $text = "🎉 <b>Pesanan Berhasil Dibuat (Via Web)</b>\n\n"
              . "Order Ref: <code>{$order->order_ref}</code>\n"
              . "Produk: {$productName}\n"
              . "Kuantitas: {$totalQty}\n"
              . "Total Tagihan: <b>Rp " . number_format($order->total_amount, 0, ',', '.') . "</b>\n"
              . "Batas Pembayaran: {$expiresAt} WIB\n\n";

        if ($dynamicQris) {
            $text .= "👇 <b>Silakan Scan QRIS berikut atau Salin Kode Payload:</b>\n"
                   . "<code>{$dynamicQris}</code>\n\n"
                   . "<i>Pesanan akan otomatis diproses segera setelah pembayaran diterima.</i>";
            
            // Kita bisa menggunakan sendPhoto dengan URL image QR Code dinamis dari API public.
            $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($dynamicQris);
            $apiUrl = "https://api.telegram.org/bot{$botToken}/sendPhoto";
            
            try {
                $response = Http::post($apiUrl, [
                    'chat_id' => $customerTelegramId,
                    'photo' => $qrImageUrl,
                    'caption' => $text,
                    'parse_mode' => 'HTML',
                ]);

                if ($response->successful()) {
                    $order->checkout_chat_id = $customerTelegramId;
                    $order->checkout_message_id = $response->json('result.message_id');
                    $order->save();
                }
            } catch (\Exception $e) {
                Log::error("Gagal mengirim QRIS photo ke {$customerTelegramId}: " . $e->getMessage());
            }

        } else {
            $text .= "⚠️ Payload QRIS belum diatur oleh admin.\n"
                   . "Silakan hubungi admin untuk melakukan pembayaran manual.";
                    
            $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
            try {
                $response = Http::post($apiUrl, [
                    'chat_id' => $customerTelegramId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ]);

                if ($response->successful()) {
                    $order->checkout_chat_id = $customerTelegramId;
                    $order->checkout_message_id = $response->json('result.message_id');
                    $order->save();
                }
            } catch (\Exception $e) {
                Log::error("Gagal mengirim text invoice ke {$customerTelegramId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Kirim notifikasi pembatalan/expired pesanan ke pelanggan dengan mengubah pesan lamanya.
     */
    public static function notifyCustomerOrderCancelled(Order $order, string $reasonText)
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) return;

        $chatId = $order->checkout_chat_id;
        $messageId = $order->checkout_message_id;

        if (!$chatId || !$messageId) {
            return;
        }

        // Terjemahkan/format alasan pembatalan
        $reason = 'Alasan: ';
        if ($reasonText === 'cancelled_by_customer') {
            $reason .= 'Dibatalkan oleh pelanggan.';
        } elseif ($reasonText === 'cancelled_by_admin') {
            $reason .= 'Dibatalkan oleh admin.';
        } elseif ($reasonText === 'payment_timeout' || str_contains($reasonText, 'waktu pembayaran telah habis') || str_contains($reasonText, 'Batas waktu')) {
            $reason .= 'Waktu pembayaran telah habis.';
        } else {
            $reason .= $reasonText;
        }

        $text = "❌ <b>Pesanan Dibatalkan</b>\n\n"
              . "Order Ref: <code>{$order->order_ref}</code>\n"
              . "{$reason}\n\n"
              . "🔎 Cek status: <code>/order_status {$order->order_ref}</code>\n"
              . "Jika Anda butuh bantuan, hubungi admin.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📦 Cek Pesanan', 'callback_data' => 'cus:ord'],
                    ['text' => '🏠 Menu Utama', 'callback_data' => 'back:main']
                ]
            ]
        ];

        $apiUrlCaption = "https://api.telegram.org/bot{$botToken}/editMessageCaption";
        $apiUrlText = "https://api.telegram.org/bot{$botToken}/editMessageText";

        try {
            // Coba editMessageText terlebih dahulu (jika pesan dikirim sebagai text biasa)
            $response = Http::post($apiUrlText, [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ]);

            // Jika gagal (karena tipe pesannya photo dengan caption), lakukan editMessageCaption
            if (!$response->successful()) {
                Http::post($apiUrlCaption, [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'caption' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Gagal memperbarui Telegram checkout message pelanggan: " . $e->getMessage());
        }
    }

    /**
     * Kirim pesan detail pesanan (Delivery Message) ke pelanggan.
     */
    public static function notifyCustomerOrderDelivered(Order $order, $reservedStock, $vpnConfigs = [])
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) return;

        $customerTelegramId = $order->customer->telegram_id ?? null;
        if (!$customerTelegramId) return;

        $text = "✅ <b>Pembayaran Berhasil Dikonfirmasi</b>\n"
              . "Order Ref: <code>{$order->order_ref}</code>\n\n"
              . "🔐 <b>Detail Akun Pesanan</b>\n";

        $idx = 1;
        foreach ($reservedStock as $unit) {
            $text .= "\n<b>Akun {$idx}</b>\n";
            $text .= "<pre>" . htmlspecialchars($unit->raw_text) . "</pre>\n";
            $idx++;
        }

        if (!empty($vpnConfigs)) {
            foreach ($vpnConfigs as $vpnIdx => $config) {
                $num = $vpnIdx + 1;
                $text .= "\n<b>Akun VPN {$num}</b>\n";
                $text .= "<pre>" . htmlspecialchars($config) . "</pre>\n";
            }
        }

        $text .= "\n📌 Simpan data akun ini dengan aman.\n"
               . "📲 Ketik /start kapan saja untuk kembali ke menu utama.";

        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        try {
            // Update original checkout message if possible
            if ($order->checkout_chat_id && $order->checkout_message_id) {
                $editUrl = "https://api.telegram.org/bot{$botToken}/editMessageText";
                $editTxt = "✅ <b>Pembayaran Dikonfirmasi</b>\n\nOrder Ref: <code>{$order->order_ref}</code>\nPesanan telah dikirim! Silakan cek pesan terbaru.";
                Http::post($editUrl, [
                    'chat_id' => $order->checkout_chat_id,
                    'message_id' => $order->checkout_message_id,
                    'text' => $editTxt,
                    'parse_mode' => 'HTML',
                ]);
            }

            Http::post($apiUrl, [
                'chat_id' => $customerTelegramId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::error("Gagal mengirim delivery message ke {$customerTelegramId}: " . $e->getMessage());
        }
    }

    /**
     * Kirim file backup ke semua admin Telegram.
     */
    public static function sendBackupFile(string $filePath, string $caption = '')
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) {
            Log::warning("TELEGRAM_BOT_TOKEN kosong, kirim backup dilewati.");
            return false;
        }

        $admins = User::where('role', 'admin')->whereNotNull('telegram_id')->get();
        if ($admins->isEmpty()) {
            Log::warning("Tidak ada admin dengan telegram_id untuk dikirimi backup.");
            return false;
        }

        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendDocument";
        $success = false;

        foreach ($admins as $admin) {
            try {
                $response = Http::attach(
                    'document',
                    file_get_contents($filePath),
                    basename($filePath)
                )->post($apiUrl, [
                    'chat_id' => $admin->telegram_id,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ]);

                if ($response->successful()) {
                    $success = true;
                } else {
                    Log::error("Gagal kirim file backup ke Telegram Admin {$admin->telegram_id}: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Gagal mengirim backup ke {$admin->telegram_id}: " . $e->getMessage());
            }
        }

        return $success;
    }

    /**
     * Kirim notifikasi ke seller ketika saldo tertahan dicairkan.
     */
    public static function notifySellerFundsReleased(User $seller, int $amount, string $orderRef, string $productName)
    {
        $botToken = config('services.telegram.bot_token');
        
        if (empty($botToken)) {
            Log::warning("TELEGRAM_BOT_TOKEN kosong, notifikasi pelepasan dana seller dilewati.");
            return;
        }

        if (!$seller->telegram_id) {
            return;
        }

        $formattedAmount = "Rp " . number_format($amount, 0, ',', '.');
        $text = "💰 <b>Saldo Tertahan Dicairkan!</b>\n\n"
              . "Halo " . htmlspecialchars($seller->full_name ?? $seller->username) . ",\n"
              . "Garansi produk telah berakhir dan saldo tertahan Anda sebesar <b>{$formattedAmount}</b> dari penjualan <b>" . htmlspecialchars($productName) . "</b> (Ref: <code>{$orderRef}</code>) telah berhasil dicairkan ke saldo wallet utama Anda.\n\n"
              . "Silakan periksa saldo wallet Anda di panel seller.";

        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        try {
            Http::post($apiUrl, [
                'chat_id' => $seller->telegram_id,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::error("Gagal mengirim notifikasi pelepasan dana seller ke {$seller->telegram_id}: " . $e->getMessage());
        }
    }
    /**
     * Kirim notifikasi ke Seller bahwa ada komplain baru.
     */
    public static function notifySellerNewComplaint(\App\Models\ComplaintCase $complaint)
    {
        $sellerId = $complaint->order->items->first()->product->creator_id ?? null;
        $seller = $sellerId ? User::find($sellerId) : null;

        $customerName = $complaint->customer->full_name ?? $complaint->customer->username ?? '-';
        $productName = $complaint->order->items->first()->product->name ?? 'Produk';
        
        $text = "⚠️ <b>Komplain Baru (Klaim Garansi)</b>\n"
              . "Ref Komplain: <code>{$complaint->complaint_ref}</code>\n"
              . "Order Ref: <code>{$complaint->order_ref_snapshot}</code>\n"
              . "Customer: " . htmlspecialchars($customerName) . " ({$complaint->customer_telegram_id})\n"
              . "Produk: " . htmlspecialchars($productName) . "\n\n"
              . "<b>Keluhan:</b>\n"
              . htmlspecialchars($complaint->complaint_text) . "\n\n"
              . "<i>Silakan periksa dan tindak lanjuti komplain di website</i>";

        // Notify seller
        if ($seller && $seller->telegram_id) {
            self::sendTelegramMessage($seller->telegram_id, $text);
        }

        // Notify all admins (if they are not already the seller)
        $admins = User::where('role', 'admin')->whereNotNull('telegram_id')->get();
        foreach ($admins as $admin) {
            if (!$seller || $admin->id !== $seller->id) {
                self::sendTelegramMessage($admin->telegram_id, $text);
            }
        }
    }

    /**
     * Kirim notifikasi ke Customer (dan opsional Seller) tentang update status komplain.
     */
    public static function notifyComplaintStatusUpdate(\App\Models\ComplaintCase $complaint)
    {
        $customerTelegramId = $complaint->customer_telegram_id ?: ($complaint->customer->telegram_id ?? null);
        if (!$customerTelegramId) return;

        $productName = $complaint->order->items->first()->product->name ?? 'Produk';
        
        $statusLabel = '';
        if ($complaint->status === 'done') {
            $statusLabel = '✅ Disetujui / Selesai';
        } elseif ($complaint->status === 'rejected') {
            $statusLabel = '❌ Ditolak';
        } elseif ($complaint->status === 'refund_requested') {
            $statusLabel = '💸 Sedang Diproses Refund';
        } else {
            $statusLabel = 'Diproses (' . strtoupper($complaint->status) . ')';
        }

        $text = "📋 <b>Update Status Klaim Garansi</b>\n"
              . "Ref Komplain: <code>{$complaint->complaint_ref}</code>\n"
              . "Order Ref: <code>{$complaint->order_ref_snapshot}</code>\n"
              . "Produk: " . htmlspecialchars($productName) . "\n\n"
              . "Status Saat Ini: <b>{$statusLabel}</b>\n";

        if ($complaint->status === 'rejected' && $complaint->rejected_reason) {
            $text .= "\n<b>Alasan Penolakan:</b>\n" . htmlspecialchars($complaint->rejected_reason) . "\n";
        }
        
        if ($complaint->refund_note) {
            $text .= "\n<b>Catatan:</b>\n" . htmlspecialchars($complaint->refund_note) . "\n";
        }

        $text .= "\n<i>Silakan periksa perkembangan komplain di website</i>";

        self::sendTelegramMessage($customerTelegramId, $text);
    }

    /**
     * Kirim notifikasi ke Seller bahwa komplain dibuka kembali (reopened)
     */
    public static function notifySellerComplaintReopened(\App\Models\ComplaintCase $complaint, User $seller)
    {
        $customerName = $complaint->customer->full_name ?? $complaint->customer->username ?? '-';
        $productName = $complaint->order->items->first()->product->name ?? 'Produk';
        
        $text = "⚠️ <b>Komplain Dibuka Kembali (Reopened)</b>\n"
              . "Ref Komplain: <code>{$complaint->complaint_ref}</code>\n"
              . "Order Ref: <code>{$complaint->order_ref_snapshot}</code>\n"
              . "Customer: " . htmlspecialchars($customerName) . "\n"
              . "Produk: " . htmlspecialchars($productName) . "\n\n"
              . "<i>Silakan periksa dan tindak lanjuti komplain di website</i>";

        // Notify seller
        if ($seller->telegram_id) {
            self::sendTelegramMessage($seller->telegram_id, $text);
        }

        // Notify all admins (if they are not already the seller)
        $admins = User::where('role', 'admin')->whereNotNull('telegram_id')->get();
        foreach ($admins as $admin) {
            if ($admin->id !== $seller->id) {
                self::sendTelegramMessage($admin->telegram_id, $text);
            }
        }
    }

    /**
     * Kirim notifikasi chat baru ke user tujuan via Telegram
     */
    public static function notifyUserNewChatMessage(User $receiver, User $sender, \App\Models\ChatMessage $msg)
    {
        if (!$receiver->telegram_id) return;

        $senderName = htmlspecialchars($sender->full_name ?? $sender->username ?? 'User');
        $messageText = htmlspecialchars($msg->message ?: '-');
        
        $text = "<b>Notifikasi Chat Baru</b>\n"
              . "Pengirim: {$senderName}\n"
              . "Pesan: {$messageText}\n";
              
        if ($msg->attachment_path) {
            $val = $msg->attachment_type === 'video' ? '1 Video' : '1 Foto';
            $text .= "Attachment: {$val}\n";
        }
        
        $text .= "\n<i>Silakan periksa dan balas pesan di website</i>";

        self::sendTelegramMessage($receiver->telegram_id, $text);
    }

    /**
     * Notify Admin when a user requests an IP unblock.
     */
    public static function notifyAdminUnblockRequest(User $admin, User $user, string $ip, string $location, string $device, string $browser)
    {
        if (!$admin->telegram_id) return;

        $text = "<b>Permintaan Buka Blokir IP</b>\n"
              . "Pengirim: " . htmlspecialchars($user->username) . " ({$user->role})\n"
              . "IP: " . htmlspecialchars($ip) . "\n"
              . "Lokasi: " . htmlspecialchars($location) . "\n"
              . "Perangkat: " . htmlspecialchars($device) . "\n"
              . "Browser: " . htmlspecialchars($browser) . "\n\n"
              . "<i>Silakan periksa dan balas pesan di website</i>";

        self::sendTelegramMessage($admin->telegram_id, $text);
    }

    /**
     * Helper to send Telegram message.
     */
    public static function sendTelegramMessage(string $chatId, string $text)
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) return;

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
            
            if (!$response->successful()) {
                Log::warning("Telegram API failed for chat {$chatId}: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Gagal mengirim pesan Telegram ke {$chatId}: " . $e->getMessage());
        }
    }
}

