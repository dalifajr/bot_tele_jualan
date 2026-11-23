<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
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
            } else {
                $query->where('status', $status);
            }
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('username', 'like', "%{$search}%");
        }

        $accounts = $query->paginate(15)->withQueryString();

        // Only expose "Buat Akun VPN" if admin has configured >= 1 valid, active VPN catalog product with an available VPN server
        $vpnProducts = $this->getConfiguredVpnProducts($vpnService);

        $adminContact = User::where('role', 'admin')->orderBy('id')->first();
        $defaultAdminId = $adminContact?->id;

        $stats = [
            'total' => VpnAccount::where('user_id', $user->id)->count(),
            'active' => VpnAccount::where('user_id', $user->id)->where('status', 'active')->count(),
            'suspended' => VpnAccount::where('user_id', $user->id)->where('status', 'suspended')->count(),
            'admin_suspended' => VpnAccount::where('user_id', $user->id)
                ->where('status', 'suspended')
                ->where('suspended_by_role', 'admin')
                ->count(),
        ];

        $renewPricing = [];
        foreach ($accounts as $acc) {
            $renewPricing[$acc->id] = $this->resolveRenewPricing($acc);
        }

        return view('vpn.my_accounts', compact('accounts', 'vpnProducts', 'defaultAdminId', 'stats', 'renewPricing'));
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

        return response()->json([
            'success' => true,
            'title' => strtoupper($account->protocol) . ' — ' . $account->username,
            'config_payload' => $account->config_payload,
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
     * Customer CREATE: purchase & provision a new VPN account directly using Wallet Balance.
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

        $userId = Auth::id();
        $price = max(0, (int) $product->price);
        $days = max(1, (int) ($product->vpn_duration_days ?: 30));
        $quotaGb = max(0, (int) ($product->vpn_quota_gb ?? 0));
        $ipLimit = max(1, (int) ($product->vpn_ip_limit ?? 1));
        $sniMode = (string) ($validated['sni_mode'] ?? $product->vpn_sni_mode ?? '3');
        if (!in_array($sniMode, ['1', '2', '3'], true)) {
            $sniMode = '3';
        }

        $orderRef = 'VPN-' . strtoupper(Str::random(10));
        $vpnAccount = null;

        try {
            DB::transaction(function () use (
                $userId, $product, $server, $validated, $price, $days, $quotaGb, $ipLimit, $orderRef, $chosenProto, $sniMode, &$vpnAccount
            ) {
                $lockedUser = User::where('id', $userId)->lockForUpdate()->firstOrFail();
                $balance = (int) ($lockedUser->customer_wallet_balance ?? 0);

                if ($price > 0 && $balance < $price) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }

                $order = Order::create([
                    'order_ref' => $orderRef,
                    'customer_id' => $lockedUser->id,
                    'subtotal' => $price,
                    'discount_amount' => 0,
                    'unique_code' => 0,
                    'total_amount' => $price,
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'vpn_username' => $validated['username'],
                    'vpn_password' => $validated['password'] ?? null,
                    'vpn_meta' => [
                        'label' => $product->name . ' — ' . strtoupper($chosenProto) . ' (' . $validated['username'] . ') ' . $days . ' Hari',
                        'protocol' => $chosenProto,
                        'days' => $days,
                        'sni_mode' => $sniMode,
                    ],
                ]);

                if ($price > 0) {
                    $newBalance = $balance - $price;
                    $lockedUser->customer_wallet_balance = $newBalance;
                    $lockedUser->save();

                    WalletTransaction::create([
                        'user_id' => $lockedUser->id,
                        'type' => 'debit',
                        'category' => 'purchase',
                        'amount' => $price,
                        'balance_before' => $balance,
                        'balance_after' => $newBalance,
                        'reference' => $orderRef,
                        'description' => __('Buat Akun VPN :proto (:user)', [
                            'proto' => strtoupper($chosenProto),
                            'user' => $validated['username'],
                        ]),
                    ]);
                }

                $vpnAccount = VpnAccount::create([
                    'user_id' => $lockedUser->id,
                    'order_id' => $order->id,
                    'vpn_server_id' => $server->exists ? $server->id : null,
                    'protocol' => $chosenProto,
                    'username' => $validated['username'],
                    'password' => $validated['password'] ?? null,
                    'quota_gb' => $quotaGb,
                    'ip_limit' => $ipLimit,
                    'purchase_price' => $price > 0 ? $price : null,
                    'purchase_days' => $days,
                    'external_ref' => $orderRef,
                    'expired_at' => now()->addDays($days),
                    'status' => 'provisioning',
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', __('Saldo Dompet tidak mencukupi. Silakan top-up dompet terlebih dahulu atau beli melalui Katalog dengan QRIS.'));
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
            return redirect()->route('vpn.my-accounts')
                ->with('success', __('Akun VPN :user (:proto) berhasil dibuat dan langsung aktif!', [
                    'user' => $provisioned->username,
                    'proto' => strtoupper($provisioned->protocol),
                ]));
        }

        // Refund wallet automatically if provisioning failed
        if ($price > 0) {
            DB::transaction(function () use ($userId, $price, $provisioned) {
                $lockedUser = User::where('id', $userId)->lockForUpdate()->first();
                if ($lockedUser) {
                    $before = (int) ($lockedUser->customer_wallet_balance ?? 0);
                    $after = $before + $price;
                    $lockedUser->customer_wallet_balance = $after;
                    $lockedUser->save();

                    WalletTransaction::create([
                        'user_id' => $lockedUser->id,
                        'type' => 'credit',
                        'category' => 'refund',
                        'amount' => $price,
                        'balance_before' => $before,
                        'balance_after' => $after,
                        'reference' => 'VPN-REFUND-' . $provisioned->id,
                        'description' => __('Refund otomatis gagal buat VPN (:user)', ['user' => $provisioned->username]),
                    ]);
                }
            });
        }

        return redirect()->route('vpn.my-accounts')
            ->with('error', __('Gagal membuat akun VPN di server (:err). Saldo Anda telah dikembalikan otomatis.', [
                'err' => $provisioned->last_error ?: 'Server error',
            ]));
    }

    /**
     * Customer RENEW: extend own VPN account using Wallet Balance. Blocked if Admin-suspended!
     */
    public function renew(Request $request, $id, VpnService $vpnService)
    {
        $account = $this->findOwnedAccount($id);

        if ($account->isAdminSuspended()) {
            return $this->adminSuspendedResponse($account);
        }

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'payment_method' => 'nullable|in:wallet,qris',
        ]);

        $days = (int) $validated['days'];
        $paymentMethod = (string) ($validated['payment_method'] ?? 'wallet');
        $renewCost = $this->calculateRenewCost($account, $days);
        $userId = Auth::id();

        if ($renewCost === null || $renewCost <= 0) {
            return back()->with('error', __('Tarif perpanjangan untuk akun VPN ini belum diatur oleh Admin. Silakan hubungi Admin melalui Chat untuk perpanjangan.'));
        }

        // QRIS payment mode: create pending Order + Payment -> redirect to QRIS checkout.success; auto-renew runs on confirmPayment()!
        if ($paymentMethod === 'qris') {
            $pendingCount = Order::where('customer_id', $userId)->where('status', 'pending_payment')->count();
            if ($pendingCount >= 5) {
                return back()->with('error', __('Anda memiliki terlalu banyak pesanan yang menunggu pembayaran. Silakan selesaikan atau batalkan pesanan sebelumnya.'));
            }

            try {
                DB::beginTransaction();

                $uniqueCode = rand(1, 200);
                $totalAmount = $renewCost + $uniqueCode;
                $orderRef = 'ORD' . now()->format('ymdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 5));
                $expiryMinutes = (int) (BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 15);

                $order = Order::create([
                    'order_ref' => $orderRef,
                    'customer_id' => $userId,
                    'subtotal' => $renewCost,
                    'discount_amount' => 0,
                    'unique_code' => $uniqueCode,
                    'total_amount' => $totalAmount,
                    'status' => 'pending_payment',
                    'expires_at' => now()->addMinutes($expiryMinutes),
                ]);

                $resolvedProductId = $this->resolveRenewProductId($account);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $resolvedProductId,
                    'quantity' => 1,
                    'unit_price' => $renewCost,
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
                    TelegramService::notifyAdminNewOrder($order);
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

        try {
            DB::transaction(function () use ($userId, $renewCost, $account, $days) {
                $lockedUser = User::where('id', $userId)->lockForUpdate()->firstOrFail();
                $before = (int) ($lockedUser->customer_wallet_balance ?? 0);
                if ($before < $renewCost) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }
                $after = $before - $renewCost;
                $lockedUser->customer_wallet_balance = $after;
                $lockedUser->save();

                WalletTransaction::create([
                    'user_id' => $lockedUser->id,
                    'type' => 'debit',
                    'category' => 'purchase',
                    'amount' => $renewCost,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'reference' => 'VPN-RENEW-' . $account->id,
                    'description' => __('Perpanjang VPN :proto (:user) :days hari', [
                        'proto' => strtoupper($account->protocol),
                        'user' => $account->username,
                        'days' => $days,
                    ]),
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', __('Saldo Dompet tidak mencukupi untuk perpanjangan (butuh Rp :amount).', [
                    'amount' => number_format($renewCost, 0, ',', '.'),
                ]));
            }
            throw $e;
        }

        $res = $vpnService->renewAccount($account, $days);

        if (!empty($res['success'])) {
            return back()->with('success', __('Akun VPN :user berhasil diperpanjang :days hari.', [
                'user' => $account->username,
                'days' => $days,
            ]));
        }

        // Refund wallet if panel API call fails
        if ($renewCost > 0) {
            DB::transaction(function () use ($userId, $renewCost, $account) {
                $lockedUser = User::where('id', $userId)->lockForUpdate()->first();
                if ($lockedUser) {
                    $before = (int) ($lockedUser->customer_wallet_balance ?? 0);
                    $after = $before + $renewCost;
                    $lockedUser->customer_wallet_balance = $after;
                    $lockedUser->save();

                    WalletTransaction::create([
                        'user_id' => $lockedUser->id,
                        'type' => 'credit',
                        'category' => 'refund',
                        'amount' => $renewCost,
                        'balance_before' => $before,
                        'balance_after' => $after,
                        'reference' => 'VPN-RENEW-REFUND-' . $account->id,
                        'description' => __('Refund gagal perpanjang VPN (:user)', ['user' => $account->username]),
                    ]);
                }
            });
        }

        return back()->with('error', __('Perpanjangan gagal (:err). Saldo telah dikembalikan.', [
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
        $msg = __('Akun VPN :user disuspend oleh Admin. Anda diwajibkan menghubungi Admin melalui Chat untuk meminta pembukaan akses kembali.', [
            'user' => $account->username,
        ]);

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
}
