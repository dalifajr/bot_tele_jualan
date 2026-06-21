<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    protected $serverKey;
    protected $isProduction;
    protected $snapUrl;

    public function __construct()
    {
        $this->serverKey = env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-lhY1q8Z9-wI3jL5pG1Wf5pG1');
        $this->isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        $this->snapUrl = $this->isProduction 
            ? 'https://app.midtrans.com/snap/v1/transactions' 
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /**
     * Create Snap Token from transaction details.
     */
    public function getSnapToken($order, $items)
    {
        $authHeader = base64_encode($this->serverKey . ':');

        $transactionDetails = [
            'order_id' => $order->order_ref,
            'gross_amount' => (int) $order->total_amount,
        ];

        $itemDetails = [];
        foreach ($items as $item) {
            $itemDetails[] = [
                'id' => $item->product_id,
                'price' => (int) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'name' => substr($item->product->name ?? 'Produk', 0, 50),
            ];
        }

        // Add unique code / fee as item detail if present
        if ($order->unique_code > 0) {
            $itemDetails[] = [
                'id' => 'UNIQUE-CODE',
                'price' => (int) $order->unique_code,
                'quantity' => 1,
                'name' => 'Kode Unik Pembayaran',
            ];
        }

        // Add discount if present
        if (isset($order->discount_amount) && $order->discount_amount > 0) {
            $itemDetails[] = [
                'id' => 'DISCOUNT',
                'price' => -((int) $order->discount_amount),
                'quantity' => 1,
                'name' => 'Diskon Kupon (' . ($order->coupon_code ?? 'PROMO') . ')',
            ];
        }

        $customerDetails = [
            'first_name' => $order->customer->full_name ?? $order->customer->username ?? 'Pelanggan',
            'email' => $order->customer->email ?? 'customer@example.com',
        ];

        $payload = [
            'transaction_details' => $transactionDetails,
            'item_details' => $itemDetails,
            'customer_details' => $customerDetails,
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $authHeader,
            ])->post($this->snapUrl, $payload);

            if ($response->successful()) {
                return $response->json('token');
            }

            Log::error('Midtrans API error response: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to connect to Midtrans: ' . $e->getMessage());
            return null;
        }
    }
}
