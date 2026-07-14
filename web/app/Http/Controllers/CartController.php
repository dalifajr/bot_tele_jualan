<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\BotSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * Display the shopping cart.
     */
    public function index()
    {
        $cartItems = CartItem::with('product')
            ->where('user_id', Auth::id())
            ->get();

        $subtotal = 0;
        foreach ($cartItems as $item) {
            if ($item->product) {
                $subtotal += $item->product->price * $item->quantity;
            }
        }

        return view('cart.index', compact('cartItems', 'subtotal'));
    }

    /**
     * Add a product to the cart.
     */
    public function add(Request $request, Product $product)
    {
        $quantity = $request->input('quantity', 1);

        if ($product->is_suspended) {
            return back()->with('error', 'Produk ini sedang tidak aktif.');
        }

        // Get available stock
        $availableStock = StockUnit::where('product_id', $product->id)
            ->where('is_sold', false)
            ->whereNull('sold_order_id')
            ->where(function ($query) {
                $query->where('stock_status', 'ready')
                      ->orWhereNull('stock_status');
            })->count();

        // Get current quantity in cart
        $existingCartItem = CartItem::where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->first();

        $currentCartQty = $existingCartItem ? $existingCartItem->quantity : 0;
        $totalRequestedQty = $currentCartQty + $quantity;

        if ($availableStock < $totalRequestedQty) {
            return back()->with('error', "Stok tidak mencukupi. Tersedia {$availableStock} unit, dan Anda sudah memiliki {$currentCartQty} unit di keranjang.");
        }

        if ($existingCartItem) {
            $existingCartItem->update(['quantity' => $totalRequestedQty]);
        } else {
            CartItem::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Produk berhasil ditambahkan ke keranjang belanja.');
    }

    /**
     * Update cart item quantity.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cartItem = CartItem::where('user_id', Auth::id())->findOrFail($id);
        $newQuantity = $request->input('quantity');

        // Check stock
        $availableStock = StockUnit::where('product_id', $cartItem->product_id)
            ->where('is_sold', false)
            ->whereNull('sold_order_id')
            ->where(function ($query) {
                $query->where('stock_status', 'ready')
                      ->orWhereNull('stock_status');
            })->count();

        if ($availableStock < $newQuantity) {
            return back()->with('error', "Stok tidak mencukupi. Tersedia {$availableStock} unit.");
        }

        $cartItem->update(['quantity' => $newQuantity]);

        return redirect()->route('cart.index')->with('success', 'Jumlah keranjang berhasil diperbarui.');
    }

    /**
     * Remove item from cart.
     */
    public function remove($id)
    {
        $cartItem = CartItem::where('user_id', Auth::id())->findOrFail($id);
        $cartItem->delete();

        return redirect()->route('cart.index')->with('success', 'Produk dihapus dari keranjang.');
    }

    /**
     * Review order details before final checkout.
     */
    public function checkout(Request $request)
    {
        $cartItems = CartItem::with('product')
            ->where('user_id', Auth::id())
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang belanja Anda kosong.');
        }

        $subtotal = 0;
        foreach ($cartItems as $item) {
            if ($item->product) {
                $subtotal += $item->product->price * $item->quantity;
            }
        }

        // Validate Coupon Code
        $couponCode = strtoupper($request->query('coupon_code', ''));
        $discount = 0;
        $coupon = null;
        $couponError = null;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon) {
                if ($coupon->isValidFor($subtotal, Auth::id())) {
                    $discount = $coupon->calculateDiscount($subtotal);
                } else {
                    $couponError = 'Kupon ini tidak dapat digunakan. Cek minimum belanja, batas pemakaian, atau kupon sudah kedaluwarsa.';
                }
            } else {
                $couponError = 'Kode kupon tidak terdaftar.';
            }
        }

        $uniqueCode = rand(1, 200);
        $total = max(0, $subtotal - $discount) + $uniqueCode;

        return view('cart.checkout', compact('cartItems', 'subtotal', 'discount', 'coupon', 'couponError', 'uniqueCode', 'total', 'couponCode'));
    }

    /**
     * Process order generation from shopping cart.
     */
    public function processCheckout(Request $request)
    {
        $user = Auth::user();

        // Limit active pending orders
        $pendingCount = Order::where('customer_id', $user->id)
            ->where('status', 'pending_payment')
            ->count();

        if ($pendingCount >= 2) {
            return redirect()->route('cart.index')->with('error', 'Anda memiliki terlalu banyak pesanan pending. Selesaikan pembayaran dahulu.');
        }

        $cartItems = CartItem::with('product')
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang belanja kosong.');
        }

        try {
            DB::beginTransaction();

            $subtotal = 0;
            // 1. Verify stock availability for all products in cart
            foreach ($cartItems as $item) {
                $product = $item->product;
                if ($product->is_suspended) {
                    throw new \Exception("Produk '{$product->name}' sedang tidak aktif.");
                }

                $availableStock = StockUnit::where('product_id', $product->id)
                    ->where('is_sold', false)
                    ->whereNull('sold_order_id')
                    ->where(function ($query) {
                        $query->where('stock_status', 'ready')
                              ->orWhereNull('stock_status');
                    })->count();

                if ($availableStock < $item->quantity) {
                    throw new \Exception("Stok produk '{$product->name}' tidak cukup. Tersisa {$availableStock} unit.");
                }

                $subtotal += $product->price * $item->quantity;
            }

            // 2. Validate Coupon
            $couponCode = strtoupper($request->input('coupon_code', ''));
            $discount = 0;
            $coupon = null;

            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)->first();
                if ($coupon && $coupon->isValidFor($subtotal, $user->id)) {
                    $discount = $coupon->calculateDiscount($subtotal);
                    
                    // Increment coupon usage
                    $coupon->increment('used_qty');

                    // Save coupon_user mapping
                    DB::table('coupon_user')->insert([
                        'coupon_id' => $coupon->id,
                        'user_id' => $user->id,
                        'created_at' => now()
                    ]);
                }
            }

            $uniqueCode = rand(1, 200);
            $totalAmount = max(0, $subtotal - $discount) + $uniqueCode;

            // Generate Order Ref
            $timestamp = now()->format('ymdHis');
            $randomHex = strtoupper(substr(md5(uniqid()), 0, 5));
            $orderRef = 'ORD' . $timestamp . $randomHex;

            $expiryMinutes = (int)(BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 15);
            $expiresAt = now()->addMinutes($expiryMinutes);

            // 3. Create Order
            $order = Order::create([
                'order_ref' => $orderRef,
                'customer_id' => $user->id,
                'subtotal' => $subtotal,
                'coupon_code' => $coupon ? $coupon->code : null,
                'discount_amount' => $discount,
                'unique_code' => $uniqueCode,
                'total_amount' => $totalAmount,
                'status' => 'pending_payment',
                'expires_at' => $expiresAt,
            ]);

            $reservedIds = [];

            // 4. Create OrderItems & Reserve Stock
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->product->price,
                ]);

                // Reserve FIFO stock units
                $stockUnitsToReserve = StockUnit::where('product_id', $item->product_id)
                    ->where('is_sold', false)
                    ->whereNull('sold_order_id')
                    ->where(function ($query) {
                        $query->where('stock_status', 'ready')
                              ->orWhereNull('stock_status');
                    })
                    ->orderBy('id', 'asc')
                    ->limit($item->quantity)
                    ->lockForUpdate()
                    ->get();

                if ($stockUnitsToReserve->count() < $item->quantity) {
                    throw new \Exception("Stok untuk produk '{$item->product->name}' baru saja dibeli orang lain.");
                }

                foreach ($stockUnitsToReserve as $unit) {
                    $unit->update([
                        'stock_status' => 'reserved_checkout',
                        'sold_order_id' => $order->id
                    ]);
                    $reservedIds[] = $unit->id;
                }
            }

            // 5. Create Payment record
            Payment::create([
                'order_id' => $order->id,
                'payment_ref' => 'PAY-' . $orderRef,
                'expected_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            // 6. Clear shopping cart
            CartItem::where('user_id', $user->id)->delete();

            // 7. Insert Audit Log (masked detail if password or raw_text is logged, handled globally)
            DB::table('audit_logs')->insert([
                'action' => 'cart_checkout',
                'actor_id' => $user->id,
                'entity_type' => 'order',
                'entity_id' => $order->id,
                'detail' => "order_ref={$orderRef}; subtotal={$subtotal}; discount={$discount}; total={$totalAmount}; reserved_stock_ids=[" . implode(',', $reservedIds) . "]",
                'created_at' => now(),
            ]);

            DB::commit();

            // Send notification to Admin
            try {
                \App\Services\TelegramService::notifyAdminNewOrder($order);
                \App\Models\User::where('role', 'admin')->get()->each(function ($admin) use ($order) {
                    $admin->notify(new \App\Notifications\OrderCreatedNotification($order));
                });
            } catch (\Exception $te) {
                Log::warning("Telegram/In-app admin notification failed: " . $te->getMessage());
            }

            return redirect()->route('checkout.success', ['order_ref' => $orderRef])->with('checkout_new', true);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Cart Checkout Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->route('cart.index')->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
