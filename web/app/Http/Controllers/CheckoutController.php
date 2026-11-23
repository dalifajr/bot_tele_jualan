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
     * Display the review and payment confirmation page before creating an order.
     */
    public function review(Request $request, Product $product)
    {
        if ($product->is_suspended) {
            return redirect()->route('catalog.index')->with('error', __('Maaf, produk ini sedang tidak aktif.'));
        }

        $user = Auth::user();

        // Rate limiting order check (max 2 active pending orders)
        $pendingOrdersCount = Order::where('customer_id', $user->id)
            ->where('status', 'pending_payment')
            ->count();

        if ($pendingOrdersCount >= 2) {
            return redirect()->route('orders.index', ['status' => 'pending_payment'])
                ->with('error', __('Anda memiliki terlalu banyak pesanan yang menunggu pembayaran. Silakan selesaikan atau batalkan pesanan Anda sebelumnya.'));
        }

        $quantity = max(1, (int)$request->input('quantity', 1));

        // Handle VCC Product validation
        $vccServices = $request->input('vcc_services', []);
        if (is_string($vccServices)) {
            $vccServices = json_decode($vccServices, true) ?? [];
        }
        if ($product->is_vcc) {
            if (!is_array($vccServices) || count($vccServices) === 0) {
                return redirect()->route('catalog.show', $product->id)->with('error', __('Silakan pilih minimal satu layanan VCC.'));
            }

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
                    return redirect()->route('catalog.show', $product->id)->with('error', $msg);
                }
            }

            $quantity = count($vccServices);
        }

        // Available stock count
        $availableStockCount = 999;
        if (!$product->is_vpn && !$product->is_vcc) {
            $availableStockCount = StockUnit::where('product_id', $product->id)
                ->where('is_sold', false)
                ->whereNull('sold_order_id')
                ->where(function ($query) {
                    $query->where('stock_status', 'ready')
                          ->orWhereNull('stock_status');
                })->count();

            if ($availableStockCount < 1) {
                return redirect()->route('catalog.show', $product->id)->with('error', __('Maaf, stok produk ini baru saja habis.'));
            }

            if ($quantity > $availableStockCount) {
                $quantity = $availableStockCount;
            }
        } elseif ($product->is_vcc) {
            $availableStockCount = count($vccServices);
        }

        $vpnUsername = $request->input('vpn_username');
        $vpnPassword = $request->input('vpn_password');
        $allowedProtos = $product->is_vpn ? $product->getAvailableVpnProtocols() : [];
        $reqProto = strtolower(trim((string) $request->input('vpn_protocol', '')));
        $vpnProtocol = ($product->is_vpn && in_array($reqProto, $allowedProtos, true))
            ? $reqProto
            : ($allowedProtos[0] ?? ($product->vpn_protocol ?: 'vmess'));
        $baseVpnDays = max(1, (int) ($product->vpn_duration_days ?: 30));
        $reqDays = (int) $request->input('vpn_days', $baseVpnDays);
        $vpnDays = $product->is_vpn ? max(1, min(365, $reqDays ?: $baseVpnDays)) : $baseVpnDays;
        $vpnServerId = $product->vpn_server_id ?: ($request->filled('vpn_server_id') ? (int) $request->input('vpn_server_id') : null);
        $vpnServerObj = $vpnServerId ? \App\Models\VpnServer::find($vpnServerId) : null;
        $rawSni = (string) $request->input('vpn_sni_mode', $product->vpn_sni_mode ?? '3');
        $vpnSniMode = in_array($rawSni, ['1', '2', '3'], true) ? $rawSni : '3';

        $effectiveUnitPrice = (int) $product->price;
        if ($product->is_vpn && $effectiveUnitPrice > 0 && $vpnDays !== $baseVpnDays) {
            $effectiveUnitPrice = max(1, (int) round(($effectiveUnitPrice / $baseVpnDays) * $vpnDays));
        }

        $subtotal = $effectiveUnitPrice * $quantity;

        // Optional coupon validation
        $couponCode = strtoupper(trim($request->input('coupon_code', '')));
        $discount = 0;
        $coupon = null;
        $couponError = null;

        if ($couponCode) {
            $coupon = \App\Models\Coupon::where('code', $couponCode)->first();
            if ($coupon) {
                if (!$coupon->is_active || ($coupon->expires_at && $coupon->expires_at->isPast()) || ($coupon->qty > 0 && $coupon->used_qty >= $coupon->qty)) {
                    $couponError = __('Kupon tidak valid, kuota habis, atau sudah kedaluwarsa.');
                } elseif (!$coupon->appliesToProduct($product)) {
                    if ($coupon->user_id && $product->creator_id != $coupon->user_id) {
                        $couponError = __('Kupon ini hanya berlaku untuk produk dari penjual toko terkait.');
                    } else {
                        $couponError = __('Kupon ini tidak dapat digunakan untuk produk ini.');
                    }
                } elseif ($subtotal < $coupon->min_spend) {
                    $couponError = __('Minimum belanja untuk kupon ini adalah Rp:min.', ['min' => number_format($coupon->min_spend, 0, ',', '.')]);
                } elseif ($user && $coupon->hasReachedUserLimit((int) $user->id)) {
                    $couponError = $coupon->getUserLimitErrorMessage();
                } else {
                    $discount = $coupon->calculateDiscountForUnits((int) $product->price, (int) $quantity);
                }
            } else {
                $couponError = __('Kode kupon tidak terdaftar.');
            }
        }

        $uniqueCode = rand(1, 200);
        $totalAmount = max(0, $subtotal - $discount) + $uniqueCode;

        return view('checkout.review', compact(
            'product',
            'quantity',
            'availableStockCount',
            'vpnUsername',
            'vpnPassword',
            'vpnProtocol',
            'vpnDays',
            'vpnServerId',
            'vpnServerObj',
            'vpnSniMode',
            'effectiveUnitPrice',
            'vccServices',
            'subtotal',
            'discount',
            'coupon',
            'couponCode',
            'couponError',
            'uniqueCode',
            'totalAmount'
        ));
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(Request $request, Product $product)
    {
        $vccServices = $request->input('vcc_services', []);
        if (is_string($vccServices)) {
            $vccServices = json_decode($vccServices, true) ?? [];
        }

        if ($product->is_vcc) {
            if (!is_array($vccServices) || count($vccServices) === 0) {
                return back()->with('error', __('Silakan pilih minimal satu layanan VCC.'));
            }
            $quantity = count($vccServices);
        } else {
            $request->validate([
                'quantity' => 'required|integer|min:1'
            ]);
            $quantity = (int)$request->input('quantity');
        }

        $user = Auth::user();

        // Rate limiting order check (max 2 active pending orders)
        $pendingOrdersCount = Order::where('customer_id', $user->id)
            ->where('status', 'pending_payment')
            ->count();

        if ($pendingOrdersCount >= 2) {
            return back()->with('error', __('Anda memiliki terlalu banyak pesanan yang menunggu pembayaran. Silakan selesaikan atau batalkan pesanan Anda sebelumnya.'));
        }

        if ($product->is_suspended) {
            return back()->with('error', __('Maaf, produk ini sedang tidak aktif.'));
        }

        $vpnProtocol = $product->vpn_protocol ?: 'vmess';
        $vpnDays = max(1, (int) ($product->vpn_duration_days ?: 30));
        $vpnServerId = $product->vpn_server_id;
        $effectiveUnitPrice = (int) $product->price;

        if ($product->is_vpn) {
            $allowedProtos = $product->getAvailableVpnProtocols();
            $reqProto = strtolower(trim((string) $request->input('vpn_protocol', '')));
            $vpnProtocol = in_array($reqProto, $allowedProtos, true) ? $reqProto : ($allowedProtos[0] ?? 'vmess');

            $baseVpnDays = max(1, (int) ($product->vpn_duration_days ?: 30));
            $reqDays = (int) $request->input('vpn_days', $baseVpnDays);
            $vpnDays = max(1, min(365, $reqDays ?: $baseVpnDays));

            if (!$vpnServerId && $request->filled('vpn_server_id')) {
                $srv = \App\Models\VpnServer::where('is_active', true)->find((int) $request->input('vpn_server_id'));
                $vpnServerId = $srv?->id;
            }

            if ($effectiveUnitPrice > 0 && $vpnDays !== $baseVpnDays) {
                $effectiveUnitPrice = max(1, (int) round(($effectiveUnitPrice / $baseVpnDays) * $vpnDays));
            }

            $request->validate([
                'vpn_username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[a-zA-Z0-9_-]+$/'],
                'vpn_password' => $vpnProtocol === 'ssh'
                    ? ['required', 'string', 'min:3', 'max:64', 'regex:/^[^\r\n\0\s]+$/']
                    : ['nullable', 'string', 'max:64'],
                'vpn_sni_mode' => ['nullable', 'in:1,2,3'],
                'vpn_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            ], [
                'vpn_username.regex' => __('Username VPN hanya boleh berisi huruf, angka, garis bawah (_), atau tanda hubung (-).'),
                'vpn_password.regex' => __('Password SSH tidak boleh mengandung spasi atau baris baru.'),
            ]);

            $targetSrvObj = $vpnServerId ? \App\Models\VpnServer::find($vpnServerId) : null;
            $preCheck = app(\App\Services\VpnService::class)->checkUsername((string) $request->vpn_username, null, $targetSrvObj, $product);
            if (!empty($preCheck['success']) && empty($preCheck['available'])) {
                return back()->with('error', __('Username sudah terpakai.'))->withInput();
            }
        }

        // Mulai transaksi database untuk mengunci stok
        try {
            DB::beginTransaction();

            // Hitung ketersediaan stok
            $availableStockCount = 999;
            if ($product->is_vcc) {
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
                        DB::rollBack();
                        $msg = $srvStock > 0
                            ? __("Stok untuk layanan ':service' hanya tersisa :stock unit.", ['service' => $service, 'stock' => $srvStock])
                            : __("Stok untuk layanan ':service' baru saja habis atau tidak mencukupi.", ['service' => $service]);
                        return back()->with('error', $msg);
                    }
                }
            } elseif (!$product->is_vpn) {
                $availableStockCount = StockUnit::where('product_id', $product->id)
                    ->where('is_sold', false)
                    ->whereNull('sold_order_id')
                    ->where(function ($query) {
                        $query->where('stock_status', 'ready')
                              ->orWhereNull('stock_status');
                    })->count();

                if ($availableStockCount < $quantity) {
                    DB::rollBack();
                    return back()->with('error', __("Stok tidak cukup. Hanya tersisa :availableStockCount unit.", ["availableStockCount" => $availableStockCount]));
                }
            }

            $subtotal = $effectiveUnitPrice * $quantity;

            // Coupon handling
            $couponCode = strtoupper(trim($request->input('coupon_code', '')));
            $discount = 0;
            if ($couponCode) {
                $coupon = \App\Models\Coupon::where('code', $couponCode)->lockForUpdate()->first();
                if ($coupon && $coupon->isValidFor($subtotal, $user->id) && $coupon->appliesToProduct($product)) {
                    $discount = $coupon->calculateDiscountForUnits((int) $product->price, (int) $quantity);
                    $coupon->increment('used_qty');
                    DB::table('coupon_user')->insert([
                        'coupon_id' => $coupon->id,
                        'user_id' => $user->id,
                        'created_at' => now(),
                    ]);
                } else {
                    $couponCode = null;
                }
            }

            $payWithWallet = $request->input('payment_method') === 'wallet';
            $uniqueCode = $payWithWallet ? 0 : rand(1, 200);
            $totalAmount = max(0, $subtotal - $discount) + $uniqueCode;

            // Generate Order Ref ORD + YYMMDDHHMMSS + 5 chars random hex
            $timestamp = now()->format('ymdHis');
            $randomHex = strtoupper(substr(md5(uniqid()), 0, 5));
            $orderRef = 'ORD' . $timestamp . $randomHex;

            if ($payWithWallet && $totalAmount > 0) {
                app(\App\Services\WalletService::class)->debit(
                    $user->id,
                    (int) $totalAmount,
                    'checkout',
                    $orderRef,
                    __('Pembayaran instan pesanan #:ref', ['ref' => $orderRef])
                );
            }

            $expiryMinutes = (int)(BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 15);
            $expiresAt = now()->addMinutes($expiryMinutes);

            // 1. Create Order
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

            $vpnUsername = $product->is_vpn ? trim((string) $request->vpn_username) : null;
            $vpnSniMode = (string) ($request->input('vpn_sni_mode') ?: ($product->vpn_sni_mode ?? '3'));
            if (!in_array($vpnSniMode, ['1', '2', '3'], true)) {
                $vpnSniMode = '3';
            }

            // 2. Create OrderItem
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $effectiveUnitPrice,
                'discount_amount' => $discount,
                'vpn_username' => $vpnUsername,
                'vpn_password' => $product->is_vpn ? $request->vpn_password : null,
                'vpn_meta' => $product->is_vpn ? [
                    'label' => $product->name . ' — ' . strtoupper($vpnProtocol) . ' (' . $vpnUsername . ') ' . $vpnDays . ' Hari',
                    'server_id' => $vpnServerId,
                    'protocol' => $vpnProtocol,
                    'days' => $vpnDays,
                    'quota_gb' => (int) ($product->vpn_quota_gb ?? 0),
                    'ip_limit' => max(1, (int) ($product->vpn_ip_limit ?? 1)),
                    'sni_mode' => $vpnSniMode,
                ] : null,
                'vcc_services' => $product->is_vcc ? $vccServices : null,
            ]);

            // 3. Create Payment
            Payment::create([
                'order_id' => $order->id,
                'payment_ref' => 'PAY-' . $orderRef,
                'expected_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            // 4. Reserve Stock Units
            $reservedIds = [];
            if ($product->is_vcc) {
                // Reserve VCC stock units for each specific service selected
                foreach ($vccServices as $service) {
                    $unit = StockUnit::where('product_id', $product->id)
                        ->where('vcc_service', $service)
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
                        DB::rollBack();
                        return back()->with('error', __("Stok untuk layanan ':service' baru saja dibeli oleh orang lain.", ['service' => $service]));
                    }

                    $unit->update([
                        'stock_status' => 'reserved_checkout',
                        'sold_order_id' => $order->id
                    ]);
                    $reservedIds[] = $unit->id;
                }
            } elseif (!$product->is_vpn) {
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
                    return back()->with('error', __("Stok baru saja dibeli oleh orang lain. Silakan coba kembali."));
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

            if ($payWithWallet) {
                app(\App\Services\OrderService::class)->confirmPayment($order, 'CUSTOMER_WALLET');
                return redirect()->route('orders.show', $order->id)
                    ->with('success', __('Pembayaran instan via Dompet Saldo berhasil! Pesanan Anda telah langsung dikirim.'));
            }

            // Kirim Notifikasi ke Admin via Telegram
            \App\Services\TelegramService::notifyAdminNewOrder($order);

            // Notifikasi In-App ke Admin
            \App\Models\User::where('role', 'admin')->get()->each(function ($admin) use ($order) {
                $admin->notify(new \App\Notifications\OrderCreatedNotification($order));
            });

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

        // Auto cancel jika sudah lewat batas waktu
        if ($order->status === 'pending_payment' && $order->expires_at && $order->expires_at->isPast()) {
            try {
                $orderService = app(\App\Services\OrderService::class);
                $orderService->cancelOrder($order, 'payment_timeout', Auth::id());
                // Reload order from db to get latest status
                $order = $order->fresh();
            } catch (\Exception $e) {
                \Log::error("Auto-cancel on success page failed: " . $e->getMessage());
            }
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
