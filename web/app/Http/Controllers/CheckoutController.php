<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\BotSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * Store a newly created order in storage.
     */
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $quantity = $request->input('quantity');
        $user = Auth::user();

        // Rate limiting order check (max 2 active pending orders)
        $pendingOrdersCount = Order::where('customer_id', $user->id)
            ->where('status', 'pending_payment')
            ->count();

        if ($pendingOrdersCount >= 2) {
            return back()->with('error', 'Anda memiliki terlalu banyak pesanan yang menunggu pembayaran. Silakan selesaikan atau batalkan pesanan Anda sebelumnya.');
        }

        if ($product->is_suspended) {
            return back()->with('error', 'Maaf, produk ini sedang tidak aktif.');
        }

        if ($product->is_vpn) {
            $request->validate([
                'vpn_username' => 'required|string|alpha_dash|max:30',
                'vpn_password' => $product->vpn_protocol === 'ssh' ? 'required|string|min:4|max:30' : 'nullable|string',
            ]);
        }

        // Mulai transaksi database untuk mengunci stok
        try {
            DB::beginTransaction();

            // Hitung ketersediaan stok
            $availableStockCount = 999;
            if (!$product->is_vpn) {
                $availableStockCount = StockUnit::where('product_id', $product->id)
                    ->where('is_sold', false)
                    ->whereNull('sold_order_id')
                    ->where(function ($query) {
                        $query->where('stock_status', 'ready')
                              ->orWhereNull('stock_status');
                    })->count();

                if ($availableStockCount < $quantity) {
                    DB::rollBack();
                    return back()->with('error', "Stok tidak cukup. Hanya tersisa {$availableStockCount} unit.");
                }
            }

            $subtotal = $product->price * $quantity;
            $uniqueCode = rand(1, 200);
            $totalAmount = $subtotal + $uniqueCode;

            // Generate Order Ref ORD + YYMMDDHHMMSS + 5 chars random hex
            $timestamp = now()->format('ymdHis');
            $randomHex = strtoupper(substr(md5(uniqid()), 0, 5));
            $orderRef = 'ORD' . $timestamp . $randomHex;

            $expiryMinutes = BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 15;
            $expiresAt = now()->addMinutes($expiryMinutes);

            // 1. Create Order
            $order = Order::create([
                'order_ref' => $orderRef,
                'customer_id' => $user->id,
                'subtotal' => $subtotal,
                'unique_code' => $uniqueCode,
                'total_amount' => $totalAmount,
                'status' => 'pending_payment',
                'expires_at' => $expiresAt,
            ]);

            // Modify vpn_username to append 4 random characters
            $vpnUsername = null;
            if ($product->is_vpn) {
                $vpnUsername = $request->vpn_username . '-' . strtolower(\Illuminate\Support\Str::random(4));
            }

            // 2. Create OrderItem
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'vpn_username' => $vpnUsername,
                'vpn_password' => $product->is_vpn ? $request->vpn_password : null,
            ]);

            // 3. Create Payment
            Payment::create([
                'order_id' => $order->id,
                'payment_ref' => 'PAY-' . $orderRef,
                'expected_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            // 4. Reserve Stock Units (FIFO)
            $reservedIds = [];
            if (!$product->is_vpn) {
                $stockUnitsToReserve = StockUnit::where('product_id', $product->id)
                    ->where('is_sold', false)
                    ->whereNull('sold_order_id')
                    ->where(function ($query) {
                        $query->where('stock_status', 'ready')
                              ->orWhereNull('stock_status');
                    })
                    ->orderBy('id', 'asc')
                    ->limit($quantity)
                    ->lockForUpdate()
                    ->get();

                if ($stockUnitsToReserve->count() < $quantity) {
                    DB::rollBack();
                    return back()->with('error', "Stok baru saja dibeli oleh orang lain. Silakan coba kembali.");
                }

                foreach ($stockUnitsToReserve as $unit) {
                    $unit->update([
                        'stock_status' => 'reserved_checkout',
                        'sold_order_id' => $order->id
                    ]);
                    $reservedIds[] = $unit->id;
                }
            }

            // 5. Insert Audit Log
            DB::table('audit_logs')->insert([
                'action' => 'order_create',
                'actor_id' => $user->id,
                'entity_type' => 'order',
                'entity_id' => $order->id,
                'detail' => \App\Models\AuditLog::maskSensitiveData("product_id={$product->id}; qty={$quantity}; subtotal={$subtotal}; total={$totalAmount}; reserved_stock_ids=[" . implode(',', $reservedIds) . "]"),
                'created_at' => now(),
            ]);

            DB::commit();

            // Kirim Notifikasi ke Admin via Telegram
            \App\Services\TelegramService::notifyAdminNewOrder($order);

            return redirect()->route('checkout.success', ['order_ref' => $orderRef])->with('checkout_new', true);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Web Checkout Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Terjadi kesalahan sistem saat memproses checkout: ' . $e->getMessage());
        }
    }

    /**
     * Display the checkout success page (Payment details).
     */
    public function success($order_ref)
    {
        $order = Order::with(['payment', 'items.product'])->where('order_ref', $order_ref)->firstOrFail();

        if ($order->customer_id !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        // Jika pesanan sudah bukan pending payment (misal dibatalkan/expired/paid),
        // redirect ke halaman detail pesanan.
        if ($order->status !== 'pending_payment') {
            return redirect()->route('orders.show', $order->id);
        }

        // Coba cari payload QRIS dari bot setting
        $staticQris = \App\Models\BotSetting::where('key', 'qris_static_payload')->value('value');
        $dynamicQris = null;

        if ($staticQris && $order->total_amount > 0 && $order->status === 'pending_payment') {
            try {
                $dynamicQris = \App\Services\QrisService::buildDynamicPayload($staticQris, $order->total_amount);
                
                // Jika baru pertama kali load success page (cek parameter query atau session),
                // kirim pesan ke Telegram user. Agar tidak dobel, kita bisa menggunakan session flash data 
                // yang di set saat store checkout.
                if (session('checkout_new')) {
                    \App\Services\TelegramService::notifyCustomerNewOrder($order, $dynamicQris);
                }
            } catch (\Exception $e) {
                // Jika gagal parsing, fallback ke null
                \Log::error("QRIS Generation Error: " . $e->getMessage());
            }
        }

        $snapToken = null;
        if ($order->payment) {
            $snapToken = $order->payment->snap_token;
            if (!$snapToken) {
                try {
                    $midtransService = app(\App\Services\MidtransService::class);
                    $snapToken = $midtransService->getSnapToken($order, $order->items);
                    if ($snapToken) {
                        $order->payment->update(['snap_token' => $snapToken]);
                    }
                } catch (\Exception $e) {
                    \Log::error("Midtrans Snap Token Generation Error: " . $e->getMessage());
                }
            }
        }

        return view('checkout.success', compact('order', 'dynamicQris', 'snapToken'));
    }
}
