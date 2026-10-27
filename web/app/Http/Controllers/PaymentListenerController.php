<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PaymentListenerController extends Controller
{
    /**
     * Bersihkan nilai secret dari karakter whitespace dan tanda kutip pembungkus.
     */
    protected function cleanSecret(?string $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $cleaned = trim($val, " \t\n\r\0\x0B\"'");
        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Dapatkan shared secret yang dikonfigurasi untuk Android Listener.
     * Prioritas: Database BotSetting > config() > web/.env > root .env > 'change-me'
     */
    protected function getSharedSecret(): string
    {
        // 1. Cek dari BotSetting (Pengaturan Admin Panel di Database)
        $dbSecret = $this->cleanSecret(BotSetting::where('key', 'listener_shared_secret')->value('value'));
        if (!empty($dbSecret)) {
            return $dbSecret;
        }

        // 2. Cek dari config services Laravel
        $configSecret = $this->cleanSecret(config('services.listener.secret'));
        if (!empty($configSecret)) {
            return $configSecret;
        }

        // 3. Cek dari env Laravel (web/.env)
        $envSecret = $this->cleanSecret(env('LISTENER_SHARED_SECRET'));
        if (!empty($envSecret)) {
            return $envSecret;
        }

        // 4. Failsafe: Baca langsung dari file root .env (../../.env) jika di web/.env tidak ada
        $rootEnvPath = base_path('../.env');
        if (file_exists($rootEnvPath)) {
            $rootEnv = @file_get_contents($rootEnvPath);
            if ($rootEnv && preg_match('/^LISTENER_SHARED_SECRET=(.*)$/m', $rootEnv, $matches)) {
                $parsed = $this->cleanSecret($matches[1]);
                if (!empty($parsed)) {
                    return $parsed;
                }
            }
        }

        return 'change-me';
    }

    /**
     * Verifikasi signature HMAC SHA-256 dari Android Listener.
     */
    protected function verifySignature(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $signature = trim((string)$request->header('X-Signature', ''));
        $timestamp = trim((string)$request->header('X-Timestamp', ''));
        $rawBody = $request->getContent();

        if (empty($signature) || empty($timestamp)) {
            return response()->json(['detail' => 'Header signature belum lengkap (X-Signature & X-Timestamp wajib)'], 401);
        }

        if (!ctype_digit((string)$timestamp)) {
            return response()->json(['detail' => 'X-Timestamp harus berupa integer unix timestamp'], 401);
        }

        $now = time();
        $ttl = (int)(env('LISTENER_SIGNATURE_TTL_SECONDS', 300));
        if (abs($now - (int)$timestamp) > max(1, $ttl)) {
            return response()->json(['detail' => 'X-Timestamp kedaluwarsa'], 401);
        }

        $secret = $this->getSharedSecret();
        $message = $timestamp . '.' . $rawBody;
        $expected = hash_hmac('sha256', $message, $secret);

        if (!hash_equals($expected, $signature)) {
            // Cek juga fallback jika secret di payload cocok (Legacy JSON payload secret)
            $payload = json_decode($rawBody, true);
            if (!empty($payload['secret']) && hash_equals($secret, (string)$payload['secret'])) {
                return null;
            }

            Log::warning("[PaymentListener] Signature mismatch", [
                'received_sig' => $signature,
                'expected_sig' => $expected,
                'timestamp' => $timestamp,
                'secret_len' => strlen($secret),
                'client_ip' => $request->ip()
            ]);

            return response()->json(['detail' => 'Signature tidak valid'], 401);
        }

        return null;
    }

    /**
     * Endpoint uji coba koneksi dari aplikasi Android Listener.
     */
    public function testConnection(Request $request)
    {
        if ($authError = $this->verifySignature($request)) {
            return $authError;
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Koneksi listener valid ke server web',
            'server_time' => time(),
        ]);
    }

    /**
     * Endpoint utama pemroses mutasi uang masuk dari aplikasi Android Listener.
     */
    public function handlePayment(Request $request)
    {
        if ($authError = $this->verifySignature($request)) {
            return $authError;
        }

        $idempotencyKey = trim($request->header('X-Idempotency-Key', ''));
        if (!empty($idempotencyKey)) {
            $cacheKey = 'listener_event_' . md5($idempotencyKey);
            if (Cache::has($cacheKey)) {
                $cached = Cache::get($cacheKey);
                $cached['idempotent_replay'] = true;
                return response()->json($cached);
            }
        }

        $amount = (int)$request->input('amount', 0);
        $sourceApp = (string)$request->input('source_app', 'unknown');
        $reference = $request->input('reference');
        $rawText = (string)$request->input('raw_text', '');

        if ($amount <= 0) {
            return response()->json(['detail' => 'Nominal amount harus lebih dari 0'], 422);
        }

        // =========================================================================
        // 1. CEK TRANSAKSI REKBER (ESCROW) YANG MENUNGGU PEMBAYARAN DEPOSIT
        // =========================================================================
        $escrowQuery = EscrowTransaction::whereIn('status', [
            EscrowTransaction::STATUS_WAITING_DEPOSIT,
            EscrowTransaction::STATUS_DEPOSIT_SUBMITTED,
        ]);

        $escrow = null;
        if (!empty($reference)) {
            $candidate = (clone $escrowQuery)->where('transaction_code', $reference)->first();
            if ($candidate && (int)$candidate->total_buyer_pay === $amount) {
                $escrow = $candidate;
            }
        }

        if (!$escrow) {
            // Pencocokan berdasarkan nominal unik total_buyer_pay
            $candidates = (clone $escrowQuery)->where('total_buyer_pay', $amount)->get();
            if ($candidates->count() === 1) {
                $escrow = $candidates->first();
            } elseif ($candidates->count() > 1) {
                return response()->json([
                    'status' => 'ambiguous',
                    'message' => 'Nominal rekber cocok ke beberapa transaksi aktif. Kirim reference kode transaksi.',
                ], 409);
            }
        }

        if ($escrow) {
            // Uang masuk deposit rekber berhasil diverifikasi otomatis
            $escrow->update([
                'status' => EscrowTransaction::STATUS_FUNDS_HELD,
                'deposit_verified_at' => now(),
                'deposit_verified_by' => null, // Diverifikasi otomatis oleh sistem
                'admin_notes' => "Diverifikasi otomatis oleh Android Listener ({$sourceApp}). Mutasi: " . substr($rawText, 0, 150),
            ]);

            $escrow->logTimeline(
                'system',
                null,
                'funds_verified_auto',
                __('Dana deposit sebesar Rp:amount telah berhasil dideteksi dan diverifikasi secara OTOMATIS oleh Android Listener (:source_app). Dana resmi diamankan dalam rekening perantara. Penjual diinstruksikan menyerahkan barang/akun.', [
                    'amount' => number_format($escrow->total_buyer_pay, 0, ',', '.'),
                    'source_app' => $sourceApp,
                ])
            );

            // Kirim notifikasi Telegram ke buyer & seller & admin jika ada
            try {
                if ($escrow->buyerUser && $escrow->buyerUser->telegram_id) {
                    TelegramService::sendMessage(
                        $escrow->buyerUser->telegram_id,
                        "✅ <b>Pembayaran Deposit Rekber Berhasil!</b>\n\nKode: <code>{$escrow->transaction_code}</code>\nNominal: Rp " . number_format($escrow->total_buyer_pay, 0, ',', '.') . "\n\nDana Anda telah aman di rekening perantara Dzulfikrialifajri Store. Penjual telah diberi instruksi untuk segera menyerahkan data akun/barang."
                    );
                }

                if ($escrow->sellerUser && $escrow->sellerUser->telegram_id) {
                    TelegramService::sendMessage(
                        $escrow->sellerUser->telegram_id,
                        "💰 <b>Dana Rekber Telah Diamankan!</b>\n\nKode: <code>{$escrow->transaction_code}</code>\nNominal Bersih yang Akan Anda Terima: <b>Rp " . number_format($escrow->total_seller_receive, 0, ',', '.') . "</b>\n\nPembeli telah membayar deposit dan diverifikasi otomatis oleh Android Listener. Silakan segera serahkan data akun/barang di ruang transaksi rekber."
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("Telegram notification error on escrow auto-reconcile: " . $e->getMessage());
            }

            $responsePayload = [
                'status' => 'paid',
                'matched_type' => 'escrow',
                'transaction_code' => $escrow->transaction_code,
                'amount' => $amount,
                'message' => 'Deposit rekber berhasil diverifikasi otomatis oleh Android Listener',
            ];

            if (!empty($idempotencyKey)) {
                Cache::put('listener_event_' . md5($idempotencyKey), $responsePayload, now()->addDays(7));
            }

            return response()->json($responsePayload);
        }

        // =========================================================================
        // 2. CEK ORDER KATALOG TOKO BIASA (ORDERS & PAYMENTS)
        // =========================================================================
        $paymentQuery = Payment::with(['order.customer', 'order.items.product'])
            ->whereIn('status', ['pending', 'paid'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['pending_payment', 'paid']);
            });

        $payment = null;
        if (!empty($reference)) {
            $payment = (clone $paymentQuery)
                ->where(function ($q) use ($reference) {
                    $q->where('payment_ref', $reference)
                      ->orWhereHas('order', fn($oq) => $oq->where('order_ref', $reference));
                })
                ->where('expected_amount', $amount)
                ->first();
        }

        if (!$payment) {
            $candidates = (clone $paymentQuery)->where('expected_amount', $amount)->get();
            if ($candidates->count() === 1) {
                $payment = $candidates->first();
            } elseif ($candidates->count() > 1) {
                return response()->json([
                    'status' => 'ambiguous',
                    'message' => 'Nominal pesanan cocok ke beberapa order aktif.',
                ], 409);
            }
        }

        if ($payment) {
            $order = $payment->order;
            if ($order) {
                // Update log pembayaran
                $payment->update([
                    'status' => 'paid',
                    'received_amount' => $amount,
                    'source_app' => 'ANDROID_LISTENER:' . $sourceApp,
                    'payload_json' => json_encode($request->all()),
                    'matched_at' => now(),
                ]);

                // Eksekusi siklus hidup pemenuhan pesanan lengkap via OrderService:
                // 1. Ubah status order -> 'delivered' (Selesai di panel admin & pelanggan)
                // 2. Tandai unit stok akun digital -> is_sold = true
                // 3. Masukkan komisi hasil penjualan ke wallet seller
                // 4. Generate akun VPN otomatis jika produk VPN
                // 5. HAPUS pesan foto QRIS pembayaran dari chat Telegram pelanggan
                // 6. KIRIM data akun & kredensial pesanan ke Telegram pelanggan
                // 7. Update pesan status pesanan di grup/chat admin
                try {
                    $orderService = app(\App\Services\OrderService::class);
                    $orderService->confirmPayment($order, 'ANDROID_LISTENER:' . $sourceApp);
                } catch (\Throwable $e) {
                    Log::error("[PaymentListener] Gagal memproses confirmPayment pada order {$order->order_ref}: " . $e->getMessage());
                }

                $responsePayload = [
                    'status' => 'paid',
                    'matched_type' => 'order',
                    'order_ref' => $order->order_ref,
                    'amount' => $amount,
                    'message' => 'Pembayaran order berhasil diverifikasi otomatis oleh Android Listener dan pesanan telah dikirimkan',
                ];

                if (!empty($idempotencyKey)) {
                    Cache::put('listener_event_' . md5($idempotencyKey), $responsePayload, now()->addDays(7));
                }

                return response()->json($responsePayload);
            }
        }

        // Tidak ada kecocokan
        return response()->json([
            'status' => 'not_found',
            'amount' => $amount,
            'message' => 'Tidak ditemukan transaksi rekber maupun pesanan toko pending dengan nominal ini.',
        ], 404);
    }
}
