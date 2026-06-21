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
        $serverKey = env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-lhY1q8Z9-wI3jL5pG1Wf5pG1');
        
        $signatureKey = $request->input('signature_key');
        $orderId = $request->input('order_id');
        $statusCode = $request->input('status_code');
        $grossAmount = $request->input('gross_amount');

        // Verify signature to secure the webhook
        $localSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        
        if ($localSignature !== $signatureKey) {
            Log::warning('Midtrans callback signature verification failed.', [
                'order_id' => $orderId,
                'signature_received' => $signatureKey,
                'signature_local' => $localSignature
            ]);
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $transactionStatus = $request->input('transaction_status');

        Log::info("Midtrans webhook notification received: order_id={$orderId}, status={$transactionStatus}");

        $order = Order::where('order_ref', $orderId)->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
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
