<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\VpnAccount;
use App\Models\WalletTransaction;
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

        return view('vpn.my_accounts', compact('accounts', 'vpnProducts', 'defaultAdminId', 'stats'));
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
            ->whereIn('vpn_protocol', ['ssh', 'vmess', 'vless', 'trojan', 'shadowsocks'])
            ->where('vpn_duration_days', '>', 0)
            ->orderBy('price')
            ->get()
            ->filter(fn (Product $p) => $vpnService->resolveServer(null, $p) !== null)
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
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{3,24}$/'],
            'password' => 'nullable|string|min:4|max:64',
        ]);

        $product = Product::with('vpnServer')->findOrFail($validated['product_id']);
        if (!$product->is_vpn || $product->is_suspended) {
            return back()->with('error', __('Produk VPN yang dipilih tidak tersedia.'));
        }

        if ($product->vpn_protocol === 'ssh' && empty($validated['password'])) {
            return back()->with('error', __('Password wajib diisi untuk akun SSH.'))->withInput();
        }

        $server = $vpnService->resolveServer(null, $product);
        if (!$server) {
            return back()->with('error', __('Server VPN untuk layanan ini sedang tidak tersedia. Hubungi Admin.'));
        }

        // Pre-flight check username availability on panel
        $check = $vpnService->checkUsername($validated['username'], $product->vpn_protocol, $server, $product);
        if (!empty($check['success']) && empty($check['available'])) {
            return back()->with('error', __('Username :user sudah terpakai di server VPN. Silakan pilih username lain.', ['user' => $validated['username']]))->withInput();
        }

        $userId = Auth::id();
        $price = max(0, (int) $product->price);
        $days = max(1, (int) ($product->vpn_duration_days ?: 30));
        $quotaGb = max(0, (int) ($product->vpn_quota_gb ?? 0));
        $ipLimit = max(1, (int) ($product->vpn_ip_limit ?? 1));
        $sniMode = (string) ($product->vpn_sni_mode ?? '3');

        $orderRef = 'VPN-' . strtoupper(Str::random(10));
        $vpnAccount = null;

        try {
            DB::transaction(function () use (
                $userId, $product, $server, $validated, $price, $days, $quotaGb, $ipLimit, $orderRef, &$vpnAccount
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
                            'proto' => strtoupper($product->vpn_protocol),
                            'user' => $validated['username'],
                        ]),
                    ]);
                }

                $vpnAccount = VpnAccount::create([
                    'user_id' => $lockedUser->id,
                    'order_id' => $order->id,
                    'vpn_server_id' => $server->exists ? $server->id : null,
                    'protocol' => $product->vpn_protocol,
                    'username' => $validated['username'],
                    'password' => $validated['password'] ?? null,
                    'quota_gb' => $quotaGb,
                    'ip_limit' => $ipLimit,
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
        ]);

        $days = (int) $validated['days'];
        $renewCost = $this->calculateRenewCost($account, $days);
        $userId = Auth::id();

        if ($renewCost > 0) {
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

    private function calculateRenewCost(VpnAccount $account, int $days): int
    {
        $product = null;
        if ($account->order) {
            $vpnItem = $account->order->items->first(fn ($i) => $i->product && $i->product->is_vpn);
            $product = $vpnItem?->product;
        }
        if (!$product) {
            $product = Product::where('is_vpn', true)
                ->where('vpn_protocol', $account->protocol)
                ->when($account->vpn_server_id, fn ($q) => $q->where('vpn_server_id', $account->vpn_server_id))
                ->orderBy('price')
                ->first();
        }
        if (!$product) {
            $product = Product::where('is_vpn', true)
                ->where('vpn_protocol', $account->protocol)
                ->orderBy('price')
                ->first();
        }

        if (!$product || $product->price <= 0) {
            return 0;
        }

        $baseDays = max(1, (int) ($product->vpn_duration_days ?: 30));
        return (int) round(($product->price / $baseDays) * $days);
    }
}
