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
        $cartItems = CartItem::with('product.creator')
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
        if (!$product->is_vcc) {
            $request->validate([
                'quantity' => 'nullable|integer|min:1|max:100',
            ]);
        }

        $quantity = max(1, (int) $request->input('quantity', 1));

        if ($product->is_suspended) {
            return back()->with('error', __('Produk ini sedang tidak aktif.'));
        }

        // Handle VCC Products
        if ($product->is_vcc) {
            $vccServices = $request->input('vcc_services', []);
            if (is_string($vccServices)) {
                $vccServices = json_decode($vccServices, true) ?? [];
            }
            if (!is_array($vccServices) || count($vccServices) === 0) {
                $msg = __('Silakan pilih minimal satu layanan VCC.');
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }
                return back()->with('error', $msg);
            }

            // Validate stock availability per selected service (accounting for multi-quantity)
            $serviceCounts = array_count_values($vccServices);
            foreach ($serviceCounts as $service => $requiredQty) {
                $srvStock = StockUnit::where('product_id', $product->id)
                    ->where('vcc_service', $service)
                    ->where('is_sold', false)
                    ->whereNull('sold_order_id')
                    ->where(function ($query) {
                        $query->where('stock_status', 'ready')
                              ->orWhereNull('stock_status');
                    })->count();

                if ($srvStock < $requiredQty) {
                    $msg = $srvStock > 0 
                        ? __("Stok untuk layanan ':service' hanya tersisa :stock unit.", ['service' => $service, 'stock' => $srvStock])
                        : __("Stok untuk layanan ':service' saat ini sedang habis.", ['service' => $service]);
                    if ($request->expectsJson()) {
                        return response()->json(['success' => false, 'message' => $msg], 422);
                    }
                    return back()->with('error', $msg);
                }
            }

            $quantity = count($vccServices);

            $existingCartItem = CartItem::where('user_id', Auth::id())
                ->where('product_id', $product->id)
                ->first();

            if ($existingCartItem) {
                $existingCartItem->update([
                    'quantity' => $quantity,
                    'vcc_services' => $vccServices,
                ]);
            } else {
                CartItem::create([
                    'user_id' => Auth::id(),
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'vcc_services' => $vccServices,
                ]);
            }

            if ($request->expectsJson()) {
                $totalCartQty = CartItem::where('user_id', Auth::id())->sum('quantity');
                return response()->json([
                    'success' => true,
                    'message' => __('Produk VCC berhasil ditambahkan ke keranjang belanja.'),
                    'cart_total_qty' => $totalCartQty,
                ]);
            }

            return redirect()->route('cart.index')->with('success', __('Produk VCC berhasil ditambahkan ke keranjang belanja.'));
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
            $msg = __("Stok tidak mencukupi. Tersedia :availableStock unit, dan Anda sudah memiliki :currentCartQty unit di keranjang.", ["availableStock" => $availableStock, "currentCartQty" => $currentCartQty]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg, 'available_stock' => $availableStock], 422);
            }
            return back()->with('error', $msg);
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

        if ($request->expectsJson()) {
            $totalCartQty = CartItem::where('user_id', Auth::id())->sum('quantity');
            return response()->json([
                'success' => true,
                'message' => __('Produk berhasil ditambahkan ke keranjang belanja.'),
                'cart_total_qty' => $totalCartQty,
            ]);
        }

        return redirect()->route('cart.index')->with('success', __('Produk berhasil ditambahkan ke keranjang belanja.'));
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
            $msg = __("Stok tidak mencukupi. Tersedia :availableStock unit.", ["availableStock" => $availableStock]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg, 'available_stock' => $availableStock], 422);
            }
            return back()->with('error', $msg);
        }

        $cartItem->update(['quantity' => $newQuantity]);

        if ($request->expectsJson()) {
            $userCart = CartItem::where('user_id', Auth::id())->with('product')->get();
            $totalQty = $userCart->sum('quantity');
            $subtotal = $userCart->sum(fn($i) => $i->product ? $i->product->price * $i->quantity : 0);
            return response()->json([
                'success' => true,
                'message' => __('Jumlah keranjang berhasil diperbarui.'),
                'quantity' => $newQuantity,
                'item_subtotal' => $cartItem->product ? $cartItem->product->price * $newQuantity : 0,
                'cart_total_qty' => $totalQty,
                'cart_subtotal' => $subtotal,
            ]);
        }

        return redirect()->route('cart.index')->with('success', __('Jumlah keranjang berhasil diperbarui.'));
    }

    /**
     * Remove item from cart.
     */
    public function remove($id)
    {
        $cartItem = CartItem::where('user_id', Auth::id())->findOrFail($id);
        $cartItem->delete();

        return redirect()->route('cart.index')->with('success', __('Produk dihapus dari keranjang.'));
    }

    /**
     * Review order details before final checkout.
     */
    public function checkout(Request $request)
    {
        $cartItems = CartItem::with('product.creator')
            ->where('user_id', Auth::id())
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', __('Keranjang belanja Anda kosong.'));
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
                if (!$coupon->is_active || ($coupon->expires_at && $coupon->expires_at->isPast()) || ($coupon->qty > 0 && $coupon->used_qty >= $coupon->qty)) {
                    $couponError = __('Kupon tidak valid, kuota habis, atau sudah kedaluwarsa.');
                } elseif (Auth::check() && $coupon->hasReachedUserLimit((int) Auth::id())) {
                    $couponError = $coupon->getUserLimitErrorMessage();
                } else {
                    // Calculate eligible subtotal & items for this coupon
                    $eligibleSubtotal = 0;
                    $eligibleItems = [];
                    foreach ($cartItems as $item) {
                        if ($coupon->appliesToProduct($item->product)) {
                            $eligibleSubtotal += $item->product->price * $item->quantity;
                            $eligibleItems[] = $item;
                        }
                    }

                    if ($eligibleSubtotal <= 0) {
                        if ($coupon->user_id) {
                            $couponError = __('Kupon ini hanya berlaku untuk produk dari toko penjual tertentu.');
                        } else {
                            $couponError = __('Kupon ini tidak dapat digunakan untuk produk yang ada di keranjang Anda.');
                        }
                    } elseif ($eligibleSubtotal < $coupon->min_spend) {
                        $couponError = __('Minimum belanja untuk produk kupon ini adalah Rp:min. (Subtotal produk terkait: Rp:subtotal)', [
                            'min' => number_format($coupon->min_spend, 0, ',', '.'),
                            'subtotal' => number_format($eligibleSubtotal, 0, ',', '.')
                        ]);
                    } else {
                        $calc = $coupon->calculateDiscountForItems($eligibleItems);
                        $discount = $calc['total_discount'];
                    }
                }
            } else {
                $couponError = __('Kode kupon tidak terdaftar.');
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
            return redirect()->route('cart.index')->with('error', __('Anda memiliki terlalu banyak pesanan pending. Selesaikan pembayaran dahulu.'));
        }

        $cartItems = CartItem::with('product')
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', __('Keranjang belanja kosong.'));
        }

        try {
            DB::beginTransaction();

            $subtotal = 0;
            // 1. Verify stock availability for all products in cart
            foreach ($cartItems as $item) {
                $product = $item->product;
                if (!$product || $product->is_suspended) {
                    throw new \Exception(__('Produk tidak tersedia atau sedang tidak aktif.'));
                }

                if ((int) $item->quantity < 1) {
                    throw new \Exception(__('Jumlah produk di keranjang tidak valid.'));
                }

                if ($product->is_vcc && !empty($item->vcc_services)) {
                    $serviceCounts = array_count_values($item->vcc_services);
                    foreach ($serviceCounts as $srv => $requiredQty) {
                        $srvStock = StockUnit::where('product_id', $product->id)
                            ->where('vcc_service', $srv)
                            ->where('is_sold', false)
                            ->whereNull('sold_order_id')
                            ->where(function ($query) {
                                $query->where('stock_status', 'ready')
                                      ->orWhereNull('stock_status');
                            })->count();
                        if ($srvStock < $requiredQty) {
                            throw new \Exception("Stok VCC untuk layanan '{$srv}' pada produk '{$product->name}' hanya tersisa {$srvStock} unit.");
                        }
                    }
                } else {
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
                }

                $subtotal += $product->price * $item->quantity;
            }

            // 2. Validate Coupon
            $couponCode = strtoupper($request->input('coupon_code', ''));
            $discount = 0;
            $coupon = null;
            $itemDiscounts = [];

            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)->lockForUpdate()->first();
                if ($coupon && $coupon->is_active && (!$coupon->expires_at || !$coupon->expires_at->isPast()) && ($coupon->qty == 0 || $coupon->used_qty < $coupon->qty)) {
                    if (!$coupon->hasReachedUserLimit((int) $user->id)) {
                        $eligibleSubtotal = 0;
                        $eligibleItems = [];
                        foreach ($cartItems as $item) {
                            if ($coupon->appliesToProduct($item->product)) {
                                $eligibleSubtotal += $item->product->price * $item->quantity;
                                $eligibleItems[] = $item;
                            }
                        }

                        if ($eligibleSubtotal > 0 && $eligibleSubtotal >= $coupon->min_spend) {
                            $calc = $coupon->calculateDiscountForItems($eligibleItems);
                            $discount = $calc['total_discount'];
                            $itemDiscounts = $calc['item_discounts'];
                            $coupon->increment('used_qty');
                            DB::table('coupon_user')->insert([
                                'coupon_id' => $coupon->id,
                                'user_id' => $user->id,
                                'created_at' => now()
                            ]);
                        } else {
                            $couponCode = null;
                        }
                    } else {
                        $couponCode = null;
                    }
                } else {
                    $couponCode = null;
                }
            }

            $payWithWallet = $request->input('payment_method') === 'wallet';
            $uniqueCode = $payWithWallet ? 0 : rand(1, 200);
            $totalAmount = max(0, $subtotal - $discount) + $uniqueCode;

            // Generate Order Ref
            $timestamp = now()->format('ymdHis');
            $randomHex = strtoupper(substr(md5(uniqid()), 0, 5));
            $orderRef = 'ORD' . $timestamp . $randomHex;

            if ($payWithWallet && $totalAmount > 0) {
                app(\App\Services\WalletService::class)->debit(
                    $user->id,
                    (int) $totalAmount,
                    'checkout',
                    $orderRef,
                    __('Pembayaran instan keranjang #:ref', ['ref' => $orderRef])
                );
            }

            $expiryMinutes = (int)(BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 15);
            $expiresAt = now()->addMinutes($expiryMinutes);

            // 3. Create Order
            $order = Order::create([
                'order_ref' => $orderRef,
                'customer_id' => $user->id,
                'subtotal' => $subtotal,
                'coupon_code' => $couponCode ?: null,
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
                    'discount_amount' => $itemDiscounts[$item->id] ?? 0,
                    'vcc_services' => $item->vcc_services,
                ]);

                if ($item->product && $item->product->is_vcc && !empty($item->vcc_services)) {
                    // Reserve VCC stock unit per service
                    foreach ($item->vcc_services as $srv) {
                        $unit = StockUnit::where('product_id', $item->product_id)
                            ->where('vcc_service', $srv)
                            ->where('is_sold', false)
                            ->whereNull('sold_order_id')
                            ->where(function ($query) {
                                $query->where('stock_status', 'ready')
                                      ->orWhereNull('stock_status');
                            })
                            ->orderBy('id', 'asc')
                            ->lockForUpdate()
                            ->first();

                        if (!$unit) {
                            throw new \Exception("Stok VCC untuk layanan '{$srv}' pada produk '{$item->product->name}' baru saja dibeli orang lain.");
                        }

                        $unit->update([
                            'stock_status' => 'reserved_checkout',
                            'sold_order_id' => $order->id
                        ]);
                        $reservedIds[] = $unit->id;
                    }
                } else {
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

            if ($payWithWallet) {
                app(\App\Services\OrderService::class)->confirmPayment($order, 'CUSTOMER_WALLET');
                return redirect()->route('orders.show', $order->id)
                    ->with('success', __('Pembayaran instan via Dompet Saldo berhasil! Pesanan keranjang Anda telah langsung dikirim.'));
            }

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
