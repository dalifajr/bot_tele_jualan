<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Handle Midtrans callback notifications.
     */
    public function callback(Request $request)
    {
        $serverKey = trim((string) (config('services.midtrans.server_key') ?: env('MIDTRANS_SERVER_KEY', '')));
        if ($serverKey === '') {
            Log::error('Midtrans callback rejected: MIDTRANS_SERVER_KEY is not configured.');
            return response()->json(['message' => 'Payment gateway not configured'], 503);
        }
        
        $signatureKey = (string) $request->input('signature_key', '');
        $orderId = (string) $request->input('order_id', '');
        $statusCode = (string) $request->input('status_code', '');
        $grossAmount = (string) $request->input('gross_amount', '');

        if ($signatureKey === '' || $orderId === '' || $statusCode === '' || $grossAmount === '') {
            return response()->json(['message' => 'Incomplete callback payload'], 400);
        }

        // Verify signature using constant-time comparison to secure the webhook
        $localSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        
        if (!hash_equals($localSignature, $signatureKey)) {
            Log::warning('Midtrans callback signature verification failed.', [
                'order_id' => $orderId,
                'client_ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $transactionStatus = $request->input('transaction_status');

        Log::info("Midtrans webhook notification received: order_id={$orderId}, status={$transactionStatus}");

        $order = Order::where('order_ref', $orderId)->first();
        if (!$order) {
            // Cek apakah order_id adalah referensi Top-Up Dompet Saldo (TOP...)
            if (\Illuminate\Support\Facades\Schema::hasTable('wallet_topups')) {
                $topup = \App\Models\WalletTopup::where('topup_ref', $orderId)->first();
                if ($topup) {
                    if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
                        if (!is_numeric($grossAmount) || abs((float) $grossAmount - (float) $topup->total_pay) > 0.01) {
                            Log::warning('Midtrans wallet topup callback amount mismatch.', [
                                'topup_ref' => $orderId,
                                'expected_amount' => $topup->total_pay,
                                'received_gross_amount' => $grossAmount,
                            ]);
                            return response()->json(['message' => 'Amount mismatch'], 400);
                        }

                        if ($topup->status === 'pending') {
                            try {
                                app(\App\Services\WalletService::class)->confirmTopup(
                                    $topup->id,
                                    null,
                                    'MIDTRANS_WEBHOOK',
                                    'Verified via Midtrans settlement',
                                    false
                                );
                                Log::info("Wallet topup {$orderId} confirmed via Midtrans settlement.");
                            } catch (\Throwable $e) {
                                Log::error("Failed to confirm wallet topup {$orderId} via Midtrans: " . $e->getMessage());
                            }
                        }
                    } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                        if ($topup->status === 'pending') {
                            $topup->update([
                                'status' => $transactionStatus === 'expire' ? 'expired' : 'cancelled',
                            ]);
                        }
                    }

                    return response()->json(['message' => 'Wallet topup callback processed successfully']);
                }
            }

            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
            if (!is_numeric($grossAmount) || abs((float) $grossAmount - (float) $order->total_amount) > 0.01) {
                Log::warning('Midtrans callback amount mismatch.', [
                    'order_id' => $orderId,
                    'expected_amount' => $order->total_amount,
                    'received_gross_amount' => $grossAmount,
                ]);
                return response()->json(['message' => 'Amount mismatch'], 400);
            }

            if ($order->status === 'pending_payment') {
                try {
                    $this->orderService->confirmPayment($order, null);
                    Log::info("Order {$orderId} marked as paid via Midtrans settlement.");
                } catch (\Exception $e) {
                    Log::error("Failed to confirm order {$orderId} payment via Midtrans webhook: " . $e->getMessage());
                }
            }
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            if ($order->status === 'pending_payment') {
                try {
                    $this->orderService->cancelOrder($order, 'Dibatalkan oleh sistem pembayaran Midtrans (' . $transactionStatus . ')', null);
                    Log::info("Order {$orderId} marked as cancelled/expired via Midtrans.");
                } catch (\Exception $e) {
                    Log::error("Failed to cancel order {$orderId} via Midtrans webhook: " . $e->getMessage());
                }
            }
        }

        return response()->json(['message' => 'Callback processed successfully']);
    }
}
