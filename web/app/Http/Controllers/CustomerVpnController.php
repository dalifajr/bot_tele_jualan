<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Models\WalletTransaction;
use App\Notifications\OrderCreatedNotification;
use App\Services\TelegramService;
use App\Services\VpnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerVpnController extends Controller
{
    public function index(Request $request, VpnService $vpnService)
    {
        $user = Auth::user();

        // Auto-refresh traffic for customer's VPN accounts from active VPN servers with cooldown
        $this->autoSyncUserTraffic($vpnService, $user->id);

        $query = VpnAccount::with(['vpnServer', 'order.items.product', 'suspendedBy'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('protocol')) {
            $query->where('protocol', strtolower((string) $request->input('protocol')));
        }
        if ($request->filled('status')) {
            $status = strtolower((string) $request->input('status'));
            if ($status === 'expired') {
                $query->where(function ($q) {
                    $q->where('status', 'expired')
                      ->orWhere(function ($q2) {
                          $q2->where('status', 'active')->whereNotNull('expired_at')->where('expired_at', '<', now());
                      });
                });
            } elseif ($status === 'expiring_soon') {
                $query->where('status', 'active')
                    ->whereNotNull('expired_at')
                    ->where('expired_at', '>', now())
                    ->where('expired_at', '<=', now()->addHours(72));
            } else {
                $query->where('status', $status);
            }
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('username', 'like', "%{$search}%");
        }

        $trafficRange = in_array((string) $request->query('traffic_range', 'all'), ['all', 'today', '7d', '30d', 'month'], true)
            ? (string) $request->query('traffic_range', 'all')
            : 'all';

        $accounts = $query->paginate(15)->withQueryString();
        $trafficSummary = $vpnService->resolveTrafficForRange(
            $trafficRange,
            (int) $user->id,
            $accounts->pluck('id')->all()
        );

        // Only expose "Buat Akun VPN" if admin has configured >= 1 valid, active VPN catalog product with an available VPN server
        $vpnProducts = $this->getConfiguredVpnProducts($vpnService);

        $adminContact = User::where('role', 'admin')->orderBy('id')->first();
        $defaultAdminId = $adminContact?->id;

        $stats = [
            'total' => VpnAccount::where('user_id', $user->id)->count(),
            'active' => VpnAccount::where('user_id', $user->id)->where('status', 'active')->count(),
            'suspended' => VpnAccount::where('user_id', $user->id)->where('status', 'suspended')->count(),
            'expiring_soon' => VpnAccount::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNotNull('expired_at')
                ->where('expired_at', '>', now())
                ->where('expired_at', '<=', now()->addHours(72))
                ->count(),
            'admin_suspended' => VpnAccount::where('user_id', $user->id)
                ->where('status', 'suspended')
                ->whereIn('suspended_by_role', ['admin', 'system'])
                ->get()
                ->filter(fn (VpnAccount $a) => !$a->isExpiredGraceSuspended())
                ->count(),
            'expired_grace' => VpnAccount::where('user_id', $user->id)
                ->where('status', 'suspended')
                ->where('suspended_by_role', 'system')
                ->get()
                ->filter(fn (VpnAccount $a) => $a->isExpiredGraceSuspended())
                ->count(),
        ];

        $renewPricing = [];
        foreach ($accounts as $acc) {
            $renewPricing[$acc->id] = $this->resolveRenewPricing($acc);
        }

        $autoRenewAccountId = $request->filled('renew') ? (int) $request->query('renew') : null;

        return view('vpn.my_accounts', compact(
            'accounts',
            'vpnProducts',
            'defaultAdminId',
            'stats',
            'renewPricing',
            'trafficRange',
            'trafficSummary',
            'autoRenewAccountId'
        ));
    }

    public function fetchConfig(Request $request, $id, VpnService $vpnService)
    {
        $account = $this->findOwnedAccount($id);

        if ($account->isAdminSuspended()) {
            return $this->adminSuspendedResponse($account);
        }

        $needsSync = $request->boolean('refresh')
            || empty($account->config_payload)
            || empty($account->config_link);

        if ($needsSync && $account->status !== 'failed') {
            $vpnService->syncAccountFromPanel($account);
            $account->refresh();
        }

        $payload = is_array($account->config_payload) ? $account->config_payload : null;
        if ($account->protocol === 'ssh' && !empty($account->password)) {
            $payload = is_array($payload) ? $payload : ['protocol' => 'ssh', 'username' => $account->username, 'domain' => $account->display_domain];
            $sshInfo = is_array($payload['ssh_info'] ?? null) ? $payload['ssh_info'] : ['host' => $account->display_domain, 'username' => $account->username];
            if (empty($sshInfo['password'])) {
                $sshInfo['password'] = $account->password;
            }
            $payload['ssh_info'] = $sshInfo;
        }

        return response()->json([
            'success' => true,
            'title' => strtoupper($account->protocol) . ' — ' . $account->username,
            'config_payload' => $payload,
            'config_link' => $account->config_link ?: __('Konfigurasi belum tersedia dari server VPN.'),
        ]);
    }

    private function getConfiguredVpnProducts(VpnService $vpnService)
    {
        return Product::with('vpnServer')
            ->where('is_vpn', true)
            ->where('is_suspended', false)
            ->where('vpn_duration_days', '>', 0)
            ->orderBy('price')
            ->get()
            ->filter(fn (Product $p) => count($p->getAvailableVpnProtocols()) > 0 && $vpnService->resolveServer(null, $p) !== null)
            ->values();
    }

    /**
     * AJAX live coupon validation for /my-vpn Create & Renew modals.
     */
    public function validateCoupon(Request $request)
    {
        $validated = $request->validate([
            'mode' => 'required|in:create,renew',
            'coupon_code' => 'required|string|max:64',
            'product_id' => 'required_if:mode,create|nullable|integer|exists:products,id',
            'account_id' => 'required_if:mode,renew|nullable|integer',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $userId = (int) Auth::id();
        $code = strtoupper(trim((string) $validated['coupon_code']));

        if ($validated['mode'] === 'create') {
            $product = Product::findOrFail((int) $validated['product_id']);
            if (!$product->is_vpn || $product->is_suspended) {
                return response()->json([
                    'valid' => false,
                    'message' => __('Produk VPN yang dipilih tidak tersedia.'),
                ], 422);
            }
            $subtotal = max(0, (int) $product->price);
        } else {
            $account = $this->findOwnedAccount((int) $validated['account_id']);
            if (!$account->canCustomerRenew()) {
                return response()->json([
                    'valid' => false,
                    'message' => __('Akun VPN ini sedang disuspend dan tidak dapat diperpanjang.'),
                ], 403);
            }
            $days = max(1, min(365, (int) ($validated['days'] ?? 30)));
            $subtotal = (int) ($this->calculateRenewCost($account, $days) ?? 0);
            if ($subtotal <= 0) {
                return response()->json([
                    'valid' => false,
                    'message' => __('Tarif perpanjangan untuk akun VPN ini belum diatur oleh Admin.'),
                ], 422);
            }
            $product = $this->resolveRenewProductModel($account);
        }

        $eval = $this->evaluateVpnCoupon($code, $subtotal, $userId, $product, false);
        if (!$eval['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $eval['error'],
                'subtotal' => $subtotal,
                'discount' => 0,
                'final_price' => $subtotal,
                'is_free' => $subtotal <= 0,
            ]);
        }

        $discount = (int) $eval['discount'];
        $finalPrice = max(0, $subtotal - $discount);
        $appliedCode = $eval['coupon']->code;

        return response()->json([
            'valid' => true,
            'code' => $appliedCode,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'final_price' => $finalPrice,
            'is_free' => $finalPrice <= 0,
            'message' => $finalPrice <= 0
                ? __('Kupon :code aktif! Diskon 100% — Bebas biaya tanpa pembayaran.', ['code' => $appliedCode])
                : __('Kupon :code diterapkan! Hemat Rp :amt.', [
                    'code' => $appliedCode,
                    'amt' => number_format($discount, 0, ',', '.'),
                ]),
        ]);
    }

    /**
     * Customer CREATE: purchase & provision a new VPN account directly using Wallet Balance, QRIS, or 100% Free Coupon.
     */
    public function store(Request $request, VpnService $vpnService)
    {
        if ($this->getConfiguredVpnProducts($vpnService)->isEmpty()) {
            return back()->with('error', __('Saat ini belum ada produk VPN yang aktif dan tersedia untuk dibuat.'));
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'protocol' => 'nullable|in:ssh,vmess,vless,trojan,shadowsocks',
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{3,24}$/'],
            'password' => ['nullable', 'string', 'min:3', 'max:64', 'regex:/^[^\r\n\0\s]+$/'],
            'sni_mode' => 'nullable|in:1,2,3',
            'payment_method' => 'nullable|in:wallet,qris',
            'coupon_code' => 'nullable|string|max:64',
        ]);

        $product = Product::with('vpnServer')->findOrFail($validated['product_id']);
        if (!$product->is_vpn || $product->is_suspended) {
            return back()->with('error', __('Produk VPN yang dipilih tidak tersedia.'));
        }

        $allowedProtos = $product->getAvailableVpnProtocols();
        $chosenProto = !empty($validated['protocol']) && in_array($validated['protocol'], $allowedProtos, true)
            ? $validated['protocol']
            : ($allowedProtos[0] ?? 'vmess');

        if ($chosenProto === 'ssh' && empty($validated['password'])) {
            return back()->with('error', __('Password wajib diisi untuk akun SSH.'))->withInput();
        }

        $server = $vpnService->resolveServer(null, $product);
        if (!$server) {
            return back()->with('error', __('Server VPN untuk layanan ini sedang tidak tersedia. Hubungi Admin.'));
        }

        // Pre-flight global username availability check across all protocols
        $check = $vpnService->checkUsername($validated['username'], null, $server, $product);
        if (!empty($check['success']) && empty($check['available'])) {
            return back()->with('error', __('Username sudah terpakai.'))->withInput();
        }

        $userId = (int) Auth::id();
        $basePrice = max(0, (int) $product->price);
        $days = max(1, (int) ($product->vpn_duration_days ?: 30));
        $quotaGb = max(0, (int) ($product->vpn_quota_gb ?? 0));
        $ipLimit = max(1, (int) ($product->vpn_ip_limit ?? 1));
        $sniMode = (string) ($validated['sni_mode'] ?? $product->vpn_sni_mode ?? '3');
        if (!in_array($sniMode, ['1', '2', '3'], true)) {
            $sniMode = '3';
        }
        $paymentMethod = (string) ($validated['payment_method'] ?? 'wallet');
        $couponCodeInput = strtoupper(trim((string) ($validated['coupon_code'] ?? '')));

        $estimatedDiscount = 0;
        if ($couponCodeInput !== '') {
            $preEval = $this->evaluateVpnCoupon($couponCodeInput, $basePrice, $userId, $product, false);
            if (!$preEval['valid']) {
                return back()->with('error', $preEval['error'])->withInput();
            }
            $estimatedDiscount = (int) $preEval['discount'];
        }
        $estimatedFinal = max(0, $basePrice - $estimatedDiscount);

        // Direct QRIS payment mode (ONLY when final price after coupon is > 0; 100% coupon bypasses QRIS!)
        if ($paymentMethod === 'qris' && $estimatedFinal > 0) {
            $pendingCount = Order::where('customer_id', $userId)->where('status', 'pending_payment')->count();
            if ($pendingCount >= 5) {
                return back()->with('error', __('Anda memiliki terlalu banyak pesanan yang menunggu pembayaran. Silakan selesaikan atau batalkan pesanan sebelumnya.'));
            }

            try {
                DB::beginTransaction();

                $discount = 0;
                $appliedCouponCode = null;
                if ($couponCodeInput !== '') {
                    $lockedEval = $this->evaluateVpnCoupon($couponCodeInput, $basePrice, $userId, $product, true);
                    if (!$lockedEval['valid']) {
                        DB::rollBack();
                        return back()->with('error', $lockedEval['error'])->withInput();
                    }
                    $discount = (int) $lockedEval['discount'];
                    $appliedCouponCode = $lockedEval['coupon']->code;
                    $this->recordCouponRedemption($lockedEval['coupon'], $userId);
                }

                $finalPrice = max(0, $basePrice - $discount);
                $uniqueCode = rand(1, 200);
                $totalAmount = $finalPrice + $uniqueCode;
                $orderRef = 'ORD' . now()->format('ymdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 5));
                $expiryMinutes = (int) (BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 15);

                $order = Order::create([
                    'order_ref' => $orderRef,
                    'customer_id' => $userId,
                    'subtotal' => $basePrice,
                    'coupon_code' => $appliedCouponCode,
                    'discount_amount' => $discount,
                    'unique_code' => $uniqueCode,
                    'total_amount' => $totalAmount,
                    'status' => 'pending_payment',
                    'expires_at' => now()->addMinutes($expiryMinutes),
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $basePrice,
                    'discount_amount' => $discount,
                    'vpn_username' => $validated['username'],
                    'vpn_password' => $validated['password'] ?? null,
                    'vpn_meta' => [
                        'label' => $product->name . ' — ' . strtoupper($chosenProto) . ' (' . $validated['username'] . ') ' . $days . ' Hari',
                        'server_id' => $server->exists ? $server->id : null,
                        'protocol' => $chosenProto,
                        'days' => $days,
                        'quota_gb' => $quotaGb,
                        'ip_limit' => $ipLimit,
                        'sni_mode' => $sniMode,
                    ],
                ]);

                Payment::create([
                    'order_id' => $order->id,
                    'payment_ref' => 'PAY-' . $orderRef,
                    'expected_amount' => $totalAmount,
                    'status' => 'pending',
                ]);

                DB::commit();

                try {
                    $order->load(['customer', 'items.product']);
                    $staticQris = BotSetting::where('key', 'qris_static_payload')->value('value');
                    $dynamicQris = (!empty($staticQris) && $totalAmount > 0)
                        ? \App\Services\QrisService::buildDynamicPayload($staticQris, $totalAmount)
                        : null;
                    TelegramService::notifyAdminNewOrder($order);
                    TelegramService::notifyCustomerNewOrder($order, $dynamicQris);
                    User::where('role', 'admin')->get()->each(fn ($a) => $a->notify(new OrderCreatedNotification($order)));
                } catch (\Throwable $e) {
                    \Log::warning('Customer VPN QRIS create notification error: ' . $e->getMessage());
                }

                return redirect()->route('checkout.success', ['order_ref' => $orderRef])
                    ->with('checkout_new', true)
                    ->with('success', __('Tagihan QRIS untuk akun VPN :user (:proto) berhasil dibuat. Akun akan otomatis aktif segera setelah pembayaran lunas.', [
                        'user' => $validated['username'],
                        'proto' => strtoupper($chosenProto),
                    ]));
            } catch (\Throwable $e) {
                DB::rollBack();
                return back()->with('error', __('Gagal membuat tagihan QRIS: :err', ['err' => $e->getMessage()]))->withInput();
            }
        }

        $orderRef = 'VPN-' . strtoupper(Str::random(10));
        $vpnAccount = null;
        $chargedCost = 0;
        $redeemedCouponId = null;
        $redeemedCouponUserId = null;

        try {
            DB::transaction(function () use (
                $userId, $product, $server, $validated, $basePrice, $days, $quotaGb, $ipLimit, $orderRef, $chosenProto, $sniMode, $couponCodeInput,
                &$vpnAccount, &$chargedCost, &$redeemedCouponId, &$redeemedCouponUserId
            ) {
                $lockedUser = User::where('id', $userId)->lockForUpdate()->firstOrFail();
                $balance = (int) ($lockedUser->customer_wallet_balance ?? 0);

                $discount = 0;
                $appliedCouponCode = null;
                if ($couponCodeInput !== '') {
                    $lockedEval = $this->evaluateVpnCoupon($couponCodeInput, $basePrice, $userId, $product, true);
                    if (!$lockedEval['valid']) {
                        throw new \RuntimeException('COUPON_INVALID:' . $lockedEval['error']);
                    }
                    $discount = (int) $lockedEval['discount'];
                    $appliedCouponCode = $lockedEval['coupon']->code;
                    $redeemedCouponId = (int) $lockedEval['coupon']->id;
                    $redeemedCouponUserId = $this->recordCouponRedemption($lockedEval['coupon'], $userId);
                }

                $finalCost = max(0, $basePrice - $discount);
                $chargedCost = $finalCost;

                if ($finalCost > 0 && $balance < $finalCost) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }

                $order = Order::create([
                    'order_ref' => $orderRef,
                    'customer_id' => $lockedUser->id,
                    'subtotal' => $basePrice,
                    'coupon_code' => $appliedCouponCode,
                    'discount_amount' => $discount,
                    'unique_code' => 0,
                    'total_amount' => $finalCost,
                    'status' => 'delivered',
                    'paid_at' => now(),
                    'delivered_at' => now(),
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $basePrice,
                    'discount_amount' => $discount,
                    'vpn_username' => $validated['username'],
                    'vpn_password' => $validated['password'] ?? null,
                    'vpn_meta' => [
                        'label' => $product->name . ' — ' . strtoupper($chosenProto) . ' (' . $validated['username'] . ') ' . $days . ' Hari',
                        'protocol' => $chosenProto,
                        'days' => $days,
                        'sni_mode' => $sniMode,
                    ],
                ]);

                if ($finalCost > 0) {
                    $newBalance = $balance - $finalCost;
                    $lockedUser->customer_wallet_balance = $newBalance;
                    $lockedUser->save();

                    $desc = __('Buat Akun VPN :proto (:user)', [
                        'proto' => strtoupper($chosenProto),
                        'user' => $validated['username'],
                    ]);
                    if ($appliedCouponCode) {
                        $desc .= ' [Kupon ' . $appliedCouponCode . ' -Rp ' . number_format($discount, 0, ',', '.') . ']';
                    }

                    WalletTransaction::create([
                        'user_id' => $lockedUser->id,
                        'type' => 'debit',
                        'category' => 'purchase',
                        'amount' => $finalCost,
                        'balance_before' => $balance,
                        'balance_after' => $newBalance,
                        'reference' => $orderRef,
                        'description' => $desc,
                    ]);
                }

                // IMPORTANT: Keep purchase_price as normal base price so future proportional renewals remain accurate even when redeemed with a 100% free coupon!
                $vpnAccount = VpnAccount::create([
                    'user_id' => $lockedUser->id,
                    'order_id' => $order->id,
                    'vpn_server_id' => $server->exists ? $server->id : null,
                    'protocol' => $chosenProto,
                    'username' => $validated['username'],
                    'password' => $validated['password'] ?? null,
                    'quota_gb' => $quotaGb,
                    'ip_limit' => $ipLimit,
                    'purchase_price' => $basePrice > 0 ? $basePrice : null,
                    'purchase_days' => $days,
                    'external_ref' => $orderRef,
                    'expired_at' => now()->addDays($days),
                    'status' => 'provisioning',
                ]);
            });
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'COUPON_INVALID:')) {
                return back()->with('error', substr($e->getMessage(), 15))->withInput();
            }
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', __('Saldo Dompet tidak mencukupi. Silakan top-up dompet terlebih dahulu atau gunakan Bayar QRIS.'));
            }
            throw $e;
        }

        // Post-commit external REST API provisioning with Idempotency-Key
        $provisioned = $vpnService->provisionAccount(
            $vpnAccount,
            $days,
            "order-{$orderRef}-acct-{$vpnAccount->id}",
            $sniMode
        );

        if ($provisioned->status === 'active') {
            $msg = $chargedCost <= 0 && $couponCodeInput !== ''
                ? __('Akun VPN :user (:proto) berhasil diklaim gratis (Kupon :code) dan langsung aktif!', [
                    'user' => $provisioned->username,
                    'proto' => strtoupper($provisioned->protocol),
                    'code' => $couponCodeInput,
                ])
                : __('Akun VPN :user (:proto) berhasil dibuat dan langsung aktif!', [
                    'user' => $provisioned->username,
                    'proto' => strtoupper($provisioned->protocol),
                ]);

            return redirect()->route('vpn.my-accounts')->with('success', $msg);
        }

        // Refund wallet & rollback coupon automatically if provisioning failed
        $this->rollbackCouponRedemption($redeemedCouponId, $redeemedCouponUserId);
        if ($chargedCost > 0) {
            DB::transaction(function () use ($userId, $chargedCost, $provisioned) {
                $lockedUser = User::where('id', $userId)->lockForUpdate()->first();
                if ($lockedUser) {
                    $before = (int) ($lockedUser->customer_wallet_balance ?? 0);
                    $after = $before + $chargedCost;
                    $lockedUser->customer_wallet_balance = $after;
                    $lockedUser->save();

                    WalletTransaction::create([
                        'user_id' => $lockedUser->id,
                        'type' => 'credit',
                        'category' => 'refund',
                        'amount' => $chargedCost,
                        'balance_before' => $before,
                        'balance_after' => $after,
                        'reference' => 'VPN-REFUND-' . $provisioned->id,
                        'description' => __('Refund otomatis gagal buat VPN (:user)', ['user' => $provisioned->username]),
                    ]);
                }
            });
        }

        return redirect()->route('vpn.my-accounts')
            ->with('error', __('Gagal membuat akun VPN di server (:err). Saldo & kupon Anda telah dikembalikan otomatis.', [
                'err' => $provisioned->last_error ?: 'Server error',
            ]));
    }

    /**
     * Customer RENEW: extend own VPN account using Wallet Balance, QRIS, or 100% Free Coupon. Blocked if Admin/System-suspended!
     */
    public function renew(Request $request, $id, VpnService $vpnService)
    {
        $account = $this->findOwnedAccount($id);

        if (!$account->canCustomerRenew()) {
            return $this->adminSuspendedResponse($account);
        }

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'payment_method' => 'nullable|in:wallet,qris',
            'coupon_code' => 'nullable|string|max:64',
        ]);

        $days = (int) $validated['days'];
        $paymentMethod = (string) ($validated['payment_method'] ?? 'wallet');
        $baseRenewCost = $this->calculateRenewCost($account, $days);
        $userId = (int) Auth::id();
        $couponCodeInput = strtoupper(trim((string) ($validated['coupon_code'] ?? '')));

        if ($baseRenewCost === null || $baseRenewCost <= 0) {
            return back()->with('error', __('Tarif perpanjangan untuk akun VPN ini belum diatur oleh Admin. Silakan hubungi Admin melalui Chat untuk perpanjangan.'));
        }

        $renewProduct = $this->resolveRenewProductModel($account);
        $estimatedDiscount = 0;
        if ($couponCodeInput !== '') {
            $preEval = $this->evaluateVpnCoupon($couponCodeInput, $baseRenewCost, $userId, $renewProduct, false);
            if (!$preEval['valid']) {
                return back()->with('error', $preEval['error']);
            }
            $estimatedDiscount = (int) $preEval['discount'];
        }
        $estimatedFinal = max(0, $baseRenewCost - $estimatedDiscount);

        // QRIS payment mode (ONLY when final cost after coupon > 0; 100% coupon bypasses QRIS!)
        if ($paymentMethod === 'qris' && $estimatedFinal > 0) {
            $pendingCount = Order::where('customer_id', $userId)->where('status', 'pending_payment')->count();
            if ($pendingCount >= 5) {
                return back()->with('error', __('Anda memiliki terlalu banyak pesanan yang menunggu pembayaran. Silakan selesaikan atau batalkan pesanan sebelumnya.'));
            }

            try {
                DB::beginTransaction();

                $discount = 0;
                $appliedCouponCode = null;
                if ($couponCodeInput !== '') {
                    $lockedEval = $this->evaluateVpnCoupon($couponCodeInput, $baseRenewCost, $userId, $renewProduct, true);
                    if (!$lockedEval['valid']) {
                        DB::rollBack();
                        return back()->with('error', $lockedEval['error']);
                    }
                    $discount = (int) $lockedEval['discount'];
                    $appliedCouponCode = $lockedEval['coupon']->code;
                    $this->recordCouponRedemption($lockedEval['coupon'], $userId);
                }

                $finalRenewCost = max(0, $baseRenewCost - $discount);
                $uniqueCode = rand(1, 200);
                $totalAmount = $finalRenewCost + $uniqueCode;
                $orderRef = 'ORD' . now()->format('ymdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 5));
                $expiryMinutes = (int) (BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 15);

                $order = Order::create([
                    'order_ref' => $orderRef,
                    'customer_id' => $userId,
                    'subtotal' => $baseRenewCost,
                    'coupon_code' => $appliedCouponCode,
                    'discount_amount' => $discount,
                    'unique_code' => $uniqueCode,
                    'total_amount' => $totalAmount,
                    'status' => 'pending_payment',
                    'expires_at' => now()->addMinutes($expiryMinutes),
                ]);

                $resolvedProductId = $renewProduct ? (int) $renewProduct->id : $this->resolveRenewProductId($account);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $resolvedProductId,
                    'quantity' => 1,
                    'unit_price' => $baseRenewCost,
                    'discount_amount' => $discount,
                    'vpn_username' => $account->username,
                    'vpn_meta' => [
                        'action' => 'renew',
                        'vpn_account_id' => $account->id,
                        'label' => 'Perpanjang VPN ' . strtoupper($account->protocol) . ' (' . $account->username . ') +' . $days . ' Hari',
                        'server_id' => $account->vpn_server_id,
                        'protocol' => $account->protocol,
                        'days' => $days,
                    ],
                ]);

                Payment::create([
                    'order_id' => $order->id,
                    'payment_ref' => 'PAY-' . $orderRef,
                    'expected_amount' => $totalAmount,
                    'status' => 'pending',
                ]);

                DB::commit();

                try {
                    $order->load(['customer', 'items.product']);
                    $staticQris = BotSetting::where('key', 'qris_static_payload')->value('value');
                    $dynamicQris = (!empty($staticQris) && $totalAmount > 0)
                        ? \App\Services\QrisService::buildDynamicPayload($staticQris, $totalAmount)
                        : null;
                    TelegramService::notifyAdminNewOrder($order);
                    TelegramService::notifyCustomerNewOrder($order, $dynamicQris);
                    User::where('role', 'admin')->get()->each(fn ($a) => $a->notify(new OrderCreatedNotification($order)));
                } catch (\Throwable $e) {
                    \Log::warning('Customer VPN QRIS renew notification error: ' . $e->getMessage());
                }

                return redirect()->route('checkout.success', ['order_ref' => $orderRef])
                    ->with('checkout_new', true)
                    ->with('success', __('Tagihan QRIS perpanjangan akun VPN :user (+:days hari) berhasil dibuat. Masa aktif akan bertambah otomatis begitu pembayaran lunas.', [
                        'user' => $account->username,
                        'days' => $days,
                    ]));
            } catch (\Throwable $e) {
                DB::rollBack();
                return back()->with('error', __('Gagal membuat tagihan QRIS perpanjangan: :err', ['err' => $e->getMessage()]));
            }
        }

        $chargedCost = 0;
        $redeemedCouponId = null;
        $redeemedCouponUserId = null;

        try {
            DB::transaction(function () use (
                $userId, $baseRenewCost, $account, $days, $renewProduct, $couponCodeInput,
                &$chargedCost, &$redeemedCouponId, &$redeemedCouponUserId
            ) {
                $lockedUser = User::where('id', $userId)->lockForUpdate()->firstOrFail();
                $before = (int) ($lockedUser->customer_wallet_balance ?? 0);

                $discount = 0;
                $appliedCouponCode = null;
                if ($couponCodeInput !== '') {
                    $lockedEval = $this->evaluateVpnCoupon($couponCodeInput, $baseRenewCost, $userId, $renewProduct, true);
                    if (!$lockedEval['valid']) {
                        throw new \RuntimeException('COUPON_INVALID:' . $lockedEval['error']);
                    }
                    $discount = (int) $lockedEval['discount'];
                    $appliedCouponCode = $lockedEval['coupon']->code;
                    $redeemedCouponId = (int) $lockedEval['coupon']->id;
                    $redeemedCouponUserId = $this->recordCouponRedemption($lockedEval['coupon'], $userId);
                }

                $finalCost = max(0, $baseRenewCost - $discount);
                $chargedCost = $finalCost;

                if ($finalCost > 0) {
                    if ($before < $finalCost) {
                        throw new \RuntimeException('INSUFFICIENT_BALANCE');
                    }
                    $after = $before - $finalCost;
                    $lockedUser->customer_wallet_balance = $after;
                    $lockedUser->save();

                    $desc = __('Perpanjang VPN :proto (:user) :days hari', [
                        'proto' => strtoupper($account->protocol),
                        'user' => $account->username,
                        'days' => $days,
                    ]);
                    if ($appliedCouponCode) {
                        $desc .= ' [Kupon ' . $appliedCouponCode . ' -Rp ' . number_format($discount, 0, ',', '.') . ']';
                    }

                    WalletTransaction::create([
                        'user_id' => $lockedUser->id,
                        'type' => 'debit',
                        'category' => 'purchase',
                        'amount' => $finalCost,
                        'balance_before' => $before,
                        'balance_after' => $after,
                        'reference' => 'VPN-RENEW-' . $account->id,
                        'description' => $desc,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'COUPON_INVALID:')) {
                return back()->with('error', substr($e->getMessage(), 15));
            }
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', __('Saldo Dompet tidak mencukupi untuk perpanjangan (butuh Rp :amount).', [
                    'amount' => number_format($chargedCost ?: $estimatedFinal, 0, ',', '.'),
                ]));
            }
            throw $e;
        }

        $res = $vpnService->renewAccount($account, $days);

        if (!empty($res['success'])) {
            $msg = $chargedCost <= 0 && $couponCodeInput !== ''
                ? __('Akun VPN :user berhasil diperpanjang gratis :days hari (Kupon :code)!', [
                    'user' => $account->username,
                    'days' => $days,
                    'code' => $couponCodeInput,
                ])
                : __('Akun VPN :user berhasil diperpanjang :days hari.', [
                    'user' => $account->username,
                    'days' => $days,
                ]);

            return back()->with('success', $msg);
        }

        // Refund wallet & rollback coupon if panel API call fails
        $this->rollbackCouponRedemption($redeemedCouponId, $redeemedCouponUserId);
        if ($chargedCost > 0) {
            DB::transaction(function () use ($userId, $chargedCost, $account) {
                $lockedUser = User::where('id', $userId)->lockForUpdate()->first();
                if ($lockedUser) {
                    $before = (int) ($lockedUser->customer_wallet_balance ?? 0);
                    $after = $before + $chargedCost;
                    $lockedUser->customer_wallet_balance = $after;
                    $lockedUser->save();

                    WalletTransaction::create([
                        'user_id' => $lockedUser->id,
                        'type' => 'credit',
                        'category' => 'refund',
                        'amount' => $chargedCost,
                        'balance_before' => $before,
                        'balance_after' => $after,
                        'reference' => 'VPN-RENEW-REFUND-' . $account->id,
                        'description' => __('Refund gagal perpanjang VPN (:user)', ['user' => $account->username]),
                    ]);
                }
            });
        }

        return back()->with('error', __('Perpanjangan gagal (:err). Saldo & kupon telah dikembalikan.', [
            'err' => $res['message'] ?? 'Server error',
        ]));
    }

    /**
     * Customer SELF-SUSPEND: customer can temporarily pause/suspend their own active VPN account.
     */
    public function suspend(Request $request, $id, VpnService $vpnService)
    {
        $account = $this->findOwnedAccount($id);

        if ($account->isAdminSuspended()) {
            return $this->adminSuspendedResponse($account);
        }

        $res = $vpnService->suspendAccount(
            $account,
            'customer',
            Auth::id(),
            __('Ditangguhkan sementara oleh pemilik')
        );

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil ditangguhkan sementara. Anda dapat mengaktifkannya kembali kapan saja.', ['user' => $account->username])
                : ($res['message'] ?? __('Gagal menangguhkan akun VPN.'))
        );
    }

    /**
     * Customer SELF-UNSUSPEND: strictly BLOCKED if account was suspended by Admin!
     */
    public function unsuspend($id, VpnService $vpnService)
    {
        $account = $this->findOwnedAccount($id);

        if ($account->isAdminSuspended()) {
            return $this->adminSuspendedResponse($account);
        }

        $res = $vpnService->unsuspendAccount($account);

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil diaktifkan kembali.', ['user' => $account->username])
                : ($res['message'] ?? __('Gagal mengaktifkan kembali akun VPN.'))
        );
    }

    /**
     * Customer DELETE: customer can delete their own VPN account, UNLESS suspended/locked by Admin.
     */
    public function destroy($id, VpnService $vpnService)
    {
        $account = $this->findOwnedAccount($id);

        if ($account->isAdminSuspended()) {
            return $this->adminSuspendedResponse($account);
        }

        $username = $account->username;
        $res = $vpnService->deleteAccount($account, true);

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil dihapus dari server dan daftar Anda.', ['user' => $username])
                : ($res['message'] ?? __('Gagal menghapus akun VPN.'))
        );
    }

    public function sync($id, VpnService $vpnService)
    {
        $account = $this->findOwnedAccount($id);
        $res = $vpnService->syncAccountFromPanel($account);

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            $res['message'] ?? __('Sinkronisasi konfigurasi selesai.')
        );
    }

    private function findOwnedAccount($id): VpnAccount
    {
        return VpnAccount::with(['vpnServer', 'order.items.product', 'suspendedBy'])
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }

    private function adminSuspendedResponse(VpnAccount $account)
    {
        if ($account->isExpiredGraceSuspended()) {
            $remDays = $account->getGraceDaysRemaining();
            $msg = __('Akun VPN :user sedang disuspend otomatis karena masa aktif telah kadaluarsa dan tidak dapat dibuka sendiri (akan dihapus permanen dalam :days hari). Silakan klik tombol Perpanjang untuk mengaktifkan kembali akun Anda atau hubungi Admin.', [
                'user' => $account->username,
                'days' => max(1, $remDays),
            ]);
        } elseif ($account->isSystemSuspended()) {
            $msg = __('Akun VPN :user disuspend otomatis oleh sistem (:reason). Anda diwajibkan menghubungi Admin melalui Chat untuk meminta pembukaan akses kembali.', [
                'user' => $account->username,
                'reason' => $account->suspended_reason ?: __('pelanggaran batas penggunaan'),
            ]);
        } else {
            $msg = __('Akun VPN :user disuspend oleh Admin. Anda diwajibkan menghubungi Admin melalui Chat untuk meminta pembukaan akses kembali.', [
                'user' => $account->username,
            ]);
        }

        if (request()->expectsJson()) {
            return response()->json(['success' => false, 'message' => $msg], 403);
        }

        return back()->with('error', $msg);
    }

    private function resolveRenewPricing(VpnAccount $account): ?array
    {
        // Priority 1: Explicit purchase price & days stored on the VPN account itself
        if ((int) ($account->purchase_price ?? 0) > 0 && (int) ($account->purchase_days ?? 0) > 0) {
            return [
                'base_price' => (int) $account->purchase_price,
                'base_days' => max(1, (int) $account->purchase_days),
            ];
        }

        // Priority 2: Originating order item (covers both catalog purchases & Admin manual billing orders like unit_price > 0)
        if ($account->order && $account->order->items) {
            $vpnItem = $account->order->items->first(function ($i) use ($account) {
                $meta = is_array($i->vpn_meta) ? $i->vpn_meta : [];
                $matchesUser = !empty($i->vpn_username) && strtolower((string) $i->vpn_username) === strtolower((string) $account->username);
                $isVpn = ($i->product && $i->product->is_vpn) || !empty($meta['protocol']);
                return $matchesUser || $isVpn;
            });

            if ($vpnItem) {
                $meta = is_array($vpnItem->vpn_meta) ? $vpnItem->vpn_meta : [];
                $itemPrice = (int) ($vpnItem->unit_price ?: ($vpnItem->product?->price ?? 0));
                $itemDays = max(1, (int) ($meta['days'] ?? $vpnItem->product?->vpn_duration_days ?: 30));
                if ($itemPrice > 0) {
                    return [
                        'base_price' => $itemPrice,
                        'base_days' => $itemDays,
                    ];
                }
            }
        }

        // Priority 3: Active catalog VPN product matching protocol (+ server)
        $product = Product::where('is_vpn', true)
            ->where('is_suspended', false)
            ->where('price', '>', 0)
            ->where('vpn_duration_days', '>', 0)
            ->where('vpn_protocol', $account->protocol)
            ->when($account->vpn_server_id, fn ($q) => $q->where('vpn_server_id', $account->vpn_server_id))
            ->orderBy('price')
            ->first();

        if (!$product) {
            $product = Product::where('is_vpn', true)
                ->where('is_suspended', false)
                ->where('price', '>', 0)
                ->where('vpn_duration_days', '>', 0)
                ->where('vpn_protocol', $account->protocol)
                ->orderBy('price')
                ->first();
        }

        if ($product && (int) $product->price > 0) {
            return [
                'base_price' => (int) $product->price,
                'base_days' => max(1, (int) ($product->vpn_duration_days ?: 30)),
            ];
        }

        return null;
    }

    /**
     * Evaluate a coupon code for VPN create/renew with identical business rules to CheckoutController.
     *
     * @return array{valid: bool, coupon: Coupon|null, discount: int, error: string|null}
     */
    private function evaluateVpnCoupon(string $code, int $subtotal, int $userId, ?Product $product = null, bool $lockForUpdate = false): array
    {
        $query = Coupon::where('code', $code);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $coupon = $query->first();

        if (!$coupon) {
            return ['valid' => false, 'coupon' => null, 'discount' => 0, 'error' => __('Kode kupon tidak terdaftar.')];
        }

        if (!$coupon->is_active || ($coupon->expires_at && $coupon->expires_at->isPast()) || ($coupon->qty > 0 && $coupon->used_qty >= $coupon->qty)) {
            return ['valid' => false, 'coupon' => null, 'discount' => 0, 'error' => __('Kupon tidak valid, kuota habis, atau sudah kedaluwarsa.')];
        }

        if ($product) {
            if (!$coupon->appliesToProduct($product)) {
                $err = ($coupon->user_id && $product->creator_id != $coupon->user_id)
                    ? __('Kupon ini hanya berlaku untuk produk dari penjual toko terkait.')
                    : __('Kupon ini tidak dapat digunakan untuk paket VPN ini.');
                return ['valid' => false, 'coupon' => null, 'discount' => 0, 'error' => $err];
            }
        } elseif ($coupon->isRestrictedToProducts()) {
            return ['valid' => false, 'coupon' => null, 'discount' => 0, 'error' => __('Kupon ini hanya berlaku untuk produk tertentu.')];
        }

        if ($subtotal < (int) $coupon->min_spend) {
            return [
                'valid' => false,
                'coupon' => null,
                'discount' => 0,
                'error' => __('Minimum transaksi untuk kupon ini adalah Rp :min.', [
                    'min' => number_format((int) $coupon->min_spend, 0, ',', '.'),
                ]),
            ];
        }

        if ($coupon->hasReachedUserLimit($userId)) {
            return ['valid' => false, 'coupon' => null, 'discount' => 0, 'error' => $coupon->getUserLimitErrorMessage()];
        }

        $discount = $coupon->calculateDiscountForUnits($subtotal, 1);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => max(0, min($discount, $subtotal)),
            'error' => null,
        ];
    }

    private function recordCouponRedemption(Coupon $coupon, int $userId): int
    {
        $coupon->increment('used_qty');
        return (int) DB::table('coupon_user')->insertGetId([
            'coupon_id' => $coupon->id,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    private function rollbackCouponRedemption(?int $couponId, ?int $couponUserRowId): void
    {
        if (!$couponId) {
            return;
        }
        try {
            DB::transaction(function () use ($couponId, $couponUserRowId) {
                $coupon = Coupon::where('id', $couponId)->lockForUpdate()->first();
                if ($coupon && (int) $coupon->used_qty > 0) {
                    $coupon->decrement('used_qty');
                }
                if ($couponUserRowId) {
                    DB::table('coupon_user')->where('id', $couponUserRowId)->delete();
                }
            });
        } catch (\Throwable $e) {
            \Log::warning('Failed to rollback VPN coupon redemption: ' . $e->getMessage());
        }
    }

    private function resolveRenewProductModel(VpnAccount $account): ?Product
    {
        if ($account->order && $account->order->items) {
            $originItem = $account->order->items->first(fn ($i) => !empty($i->product_id));
            if ($originItem) {
                $prod = Product::withTrashed()->find((int) $originItem->product_id);
                if ($prod) {
                    return $prod;
                }
            }
        }

        $matching = Product::where('is_vpn', true)
            ->where('is_suspended', false)
            ->where('vpn_protocol', strtolower($account->protocol))
            ->when($account->vpn_server_id, fn ($q) => $q->where('vpn_server_id', $account->vpn_server_id))
            ->orderBy('price')
            ->first();

        if ($matching) {
            return $matching;
        }

        return Product::where('is_vpn', true)
            ->where('is_suspended', false)
            ->where('vpn_protocol', strtolower($account->protocol))
            ->orderBy('price')
            ->first();
    }

    private function resolveRenewProductId(VpnAccount $account): int
    {
        if ($account->order && $account->order->items) {
            $originItem = $account->order->items->first(fn ($i) => !empty($i->product_id));
            if ($originItem && Product::withTrashed()->where('id', $originItem->product_id)->exists()) {
                return (int) $originItem->product_id;
            }
        }

        $matchingId = Product::where('is_vpn', true)
            ->where('is_suspended', false)
            ->where('vpn_protocol', strtolower($account->protocol))
            ->value('id');
        if ($matchingId) {
            return (int) $matchingId;
        }

        $systemProduct = Product::withTrashed()
            ->where('name', 'Layanan VPN Custom (Tagihan Admin)')
            ->where('is_vpn', true)
            ->where('is_suspended', true)
            ->where('vpn_duration_days', 0)
            ->first();

        if ($systemProduct) {
            if ($systemProduct->trashed()) {
                $systemProduct->restore();
            }
            return (int) $systemProduct->id;
        }

        return (int) Product::create([
            'name' => 'Layanan VPN Custom (Tagihan Admin)',
            'price' => 0,
            'description' => 'Produk sistem internal otomatis untuk tagihan VPN manual Admin (tidak tampil di katalog publik).',
            'is_suspended' => true,
            'creator_id' => null,
            'is_vpn' => true,
            'vpn_protocol' => 'vmess',
            'vpn_duration_days' => 0,
            'vpn_quota_gb' => 0,
            'vpn_ip_limit' => 1,
            'vpn_sni_mode' => '3',
        ])->id;
    }

    private function calculateRenewCost(VpnAccount $account, int $days): ?int
    {
        $pricing = $this->resolveRenewPricing($account);
        if (!$pricing || $pricing['base_price'] <= 0) {
            return null;
        }

        return max(1, (int) round(($pricing['base_price'] / $pricing['base_days']) * max(1, $days)));
    }

    private function autoSyncUserTraffic(VpnService $vpnService, int $userId): void
    {
        $cacheKey = "vpn_customer_traffic_cooldown_{$userId}";
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return;
        }
        \Illuminate\Support\Facades\Cache::put($cacheKey, true, 30); // 30 seconds cooldown

        try {
            $serverIds = VpnAccount::where('user_id', $userId)
                ->whereNotNull('vpn_server_id')
                ->pluck('vpn_server_id')
                ->unique();

            if ($serverIds->isNotEmpty()) {
                $servers = \App\Models\VpnServer::whereIn('id', $serverIds)
                    ->where('is_active', true)
                    ->get();

                foreach ($servers as $server) {
                    $vpnService->syncServerTraffic($server);
                    if ($server->isApiDriver()) {
                        $vpnService->syncServerStatuses($server);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Customer auto traffic sync issue: " . $e->getMessage());
        }
    }
}
