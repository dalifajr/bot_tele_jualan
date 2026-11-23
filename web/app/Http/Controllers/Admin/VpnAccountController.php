<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Notifications\OrderCreatedNotification;
use App\Services\TelegramService;
use App\Services\VpnService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VpnAccountController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->query('tab') === 'servers' ? 'servers' : 'accounts';

        $query = VpnAccount::with(['user', 'order', 'vpnServer', 'suspendedBy'])
            ->orderByDesc('created_at');

        if ($request->filled('server_id')) {
            $query->where('vpn_server_id', (int) $request->input('server_id'));
        }
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
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('server_ip', 'like', "%{$search}%")
                  ->orWhere('external_ref', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $accounts = $query->paginate(20)->withQueryString();

        $servers = VpnServer::withCount(['vpnAccounts', 'products'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $customers = User::orderBy('full_name')
            ->orderBy('username')
            ->take(60)
            ->get(['id', 'full_name', 'username', 'email', 'telegram_id', 'role']);

        $vpnProducts = Product::with('vpnServer')
            ->where('is_vpn', true)
            ->where('is_suspended', false)
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => VpnAccount::count(),
            'active' => VpnAccount::where('status', 'active')->count(),
            'suspended' => VpnAccount::where('status', 'suspended')->count(),
            'failed' => VpnAccount::whereIn('status', ['failed', 'provisioning'])->count(),
        ];

        $serverStats = [
            'total' => $servers->count(),
            'active' => $servers->where('is_active', true)->count(),
            'api_driver' => $servers->where('driver', VpnServer::DRIVER_VPNXRAY_API)->count(),
            'online' => $servers->where('last_status', 'online')->count(),
        ];

        return view('admin.vpn.index', compact(
            'activeTab',
            'accounts',
            'servers',
            'customers',
            'vpnProducts',
            'stats',
            'serverStats'
        ));
    }

    public function checkUsername(Request $request, VpnService $vpnService)
    {
        $username = trim((string) $request->query('username', ''));
        $protocol = strtolower(trim((string) $request->query('protocol', 'vmess')));
        $serverId = $request->query('server_id');

        if (!preg_match('/^[a-zA-Z0-9_-]{3,24}$/', $username)) {
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => __('Username harus 3–24 karakter (huruf, angka, _ atau -).'),
            ], 422);
        }

        $server = $serverId ? VpnServer::find((int) $serverId) : null;
        $res = $vpnService->checkUsername($username, $protocol !== '' ? $protocol : null, $server);

        return response()->json([
            'success' => !empty($res['success']),
            'available' => (bool) ($res['available'] ?? false),
            'exists' => (bool) ($res['exists'] ?? false),
            'message' => !empty($res['success'])
                ? (!empty($res['available']) ? __('Username tersedia.') : __('Username sudah terpakai di server VPN.'))
                : ($res['message'] ?? __('Gagal memeriksa ketersediaan username.')),
        ]);
    }

    public function fetchConfig(Request $request, $id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);

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

    public function store(Request $request, VpnService $vpnService)
    {
        $validated = $request->validate([
            'vpn_server_id' => 'nullable|exists:vpn_servers,id',
            'assign_target' => 'nullable|in:self,customer',
            'customer_id' => 'nullable|exists:users,id',
            'user_id' => 'nullable|exists:users,id',
            'product_id' => 'nullable|exists:products,id',
            'protocol' => 'required|in:ssh,vmess,vless,trojan,shadowsocks',
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{3,24}$/'],
            'password' => 'required_if:protocol,ssh|nullable|string|min:4|max:64',
            'days' => 'required|integer|min:1|max:365',
            'quota_gb' => 'nullable|integer|min:0|max:10000',
            'ip_limit' => 'nullable|integer|min:1|max:50',
            'sni_mode' => 'nullable|in:1,2,3',
            'is_paid' => 'nullable|boolean',
            'billing_amount' => 'nullable|integer|min:0|max:100000000',
        ]);

        $admin = Auth::user();
        $targetUserId = $admin->id;
        if (($validated['assign_target'] ?? 'customer') === 'customer' && !empty($validated['customer_id'])) {
            $targetUserId = (int) $validated['customer_id'];
        } elseif (!empty($validated['user_id'])) {
            $targetUserId = (int) $validated['user_id'];
        }
        $targetUser = User::findOrFail($targetUserId);

        $product = !empty($validated['product_id']) ? Product::with('vpnServer')->find((int) $validated['product_id']) : null;
        $server = !empty($validated['vpn_server_id'])
            ? VpnServer::find((int) $validated['vpn_server_id'])
            : $vpnService->resolveServer(null, $product);

        if (!$server) {
            return back()->with('error', __('Belum ada Server VPN yang aktif dan terkonfigurasi.'));
        }

        // Toggle: is_paid = 1 (already paid -> provision immediately), is_paid = 0 (unpaid -> send billing order!)
        $isPaid = $request->has('is_paid') ? $request->boolean('is_paid') : true;

        if (!$isPaid) {
            $billingAmount = (int) ($validated['billing_amount'] ?? 0);
            if ($billingAmount <= 0 && $product) {
                $billingAmount = max(0, (int) $product->price);
            }
            if ($billingAmount <= 0) {
                return back()->with('error', __('Nominal tagihan (Rp) wajib diisi jika status belum terbayar.'))->withInput();
            }

            // Pre-flight username check before issuing billing
            $check = $vpnService->checkUsername($validated['username'], $validated['protocol'], $server);
            if (!empty($check['success']) && empty($check['available'])) {
                return back()->with('error', __('Username :user sudah terpakai di server VPN.', ['user' => $validated['username']]))->withInput();
            }

            try {
                DB::beginTransaction();

                $uniqueCode = rand(1, 200);
                $totalAmount = $billingAmount + $uniqueCode;
                $orderRef = 'ORD' . now()->format('ymdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 5));
                $expiryMinutes = (int) (BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 15);

                $order = Order::create([
                    'order_ref' => $orderRef,
                    'customer_id' => $targetUser->id,
                    'subtotal' => $billingAmount,
                    'discount_amount' => 0,
                    'unique_code' => $uniqueCode,
                    'total_amount' => $totalAmount,
                    'status' => 'pending_payment',
                    'expires_at' => now()->addMinutes($expiryMinutes),
                ]);

                $resolvedProductId = $this->resolveBillingProductId($product, $validated['protocol']);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $resolvedProductId,
                    'quantity' => 1,
                    'unit_price' => $billingAmount,
                    'vpn_username' => $validated['username'],
                    'vpn_password' => $validated['password'] ?? null,
                    'vpn_meta' => [
                        'label' => 'VPN ' . strtoupper($validated['protocol']) . ' (' . $validated['username'] . ') ' . $validated['days'] . ' Hari',
                        'server_id' => $server->exists ? $server->id : null,
                        'protocol' => $validated['protocol'],
                        'days' => (int) $validated['days'],
                        'quota_gb' => (int) ($validated['quota_gb'] ?? 0),
                        'ip_limit' => max(1, (int) ($validated['ip_limit'] ?? 1)),
                        'sni_mode' => (string) ($validated['sni_mode'] ?? '3'),
                    ],
                ]);

                Payment::create([
                    'order_id' => $order->id,
                    'payment_ref' => 'PAY-' . $orderRef,
                    'expected_amount' => $totalAmount,
                    'status' => 'pending',
                ]);

                DB::table('audit_logs')->insert([
                    'action' => 'admin_vpn_billing_created',
                    'actor_id' => $admin->id,
                    'entity_type' => 'order',
                    'entity_id' => $order->id,
                    'detail' => AuditLog::maskSensitiveData("order_ref={$orderRef}; customer_id={$targetUser->id}; protocol={$validated['protocol']}; username={$validated['username']}; total={$totalAmount}"),
                    'created_at' => now(),
                ]);

                DB::commit();

                try {
                    TelegramService::notifyAdminNewOrder($order);
                    User::where('role', 'admin')->get()->each(fn ($a) => $a->notify(new OrderCreatedNotification($order)));
                    if ($targetUser->id !== $admin->id) {
                        $targetUser->notify(new OrderCreatedNotification($order));
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Admin manual VPN billing notification error: ' . $e->getMessage());
                }

                return redirect()->route('checkout.success', ['order_ref' => $orderRef])
                    ->with('checkout_new', true)
                    ->with('success', __('Tagihan VPN (:ref) berhasil dibuat dan dikirim ke :name. Akun VPN akan aktif otomatis begitu pembayaran lunas.', [
                        'ref' => $orderRef,
                        'name' => $targetUser->full_name ?: $targetUser->username,
                    ]));
            } catch (\Throwable $e) {
                DB::rollBack();
                return back()->with('error', __('Gagal membuat tagihan VPN: :err', ['err' => $e->getMessage()]))->withInput();
            }
        }

        // Paid mode: provision VPN account immediately & deliver directly to target user's /my-vpn
        $account = VpnAccount::create([
            'user_id' => $targetUser->id,
            'vpn_server_id' => $server->exists ? $server->id : null,
            'protocol' => $validated['protocol'],
            'username' => $validated['username'],
            'password' => $validated['password'] ?? null,
            'quota_gb' => (int) ($validated['quota_gb'] ?? 0),
            'ip_limit' => max(1, (int) ($validated['ip_limit'] ?? 1)),
            'external_ref' => 'admin_manual_' . $admin->id,
            'expired_at' => now()->addDays((int) $validated['days']),
            'status' => 'provisioning',
        ]);

        $provisioned = $vpnService->provisionAccount(
            $account,
            (int) $validated['days'],
            'admin-create-' . $account->id,
            (string) ($validated['sni_mode'] ?? '3')
        );

        $this->logAudit('admin_vpn_create', $provisioned, "owner={$targetUser->id}; protocol={$provisioned->protocol}; username={$provisioned->username}; status={$provisioned->status}");

        if ($provisioned->status === 'active') {
            return redirect()->route('admin.vpn-accounts.index', ['tab' => 'accounts'])
                ->with('success', __('Akun VPN :user (:proto) berhasil dibuat dan langsung muncul di menu Akun VPN Saya milik :owner.', [
                    'user' => $provisioned->username,
                    'proto' => strtoupper($provisioned->protocol),
                    'owner' => $targetUser->full_name ?: $targetUser->username,
                ]));
        }

        return redirect()->route('admin.vpn-accounts.index', ['tab' => 'accounts'])
            ->with('error', __('Akun disimpan dengan status gagal: :err', ['err' => $provisioned->last_error ?: 'Unknown error']));
    }

    public function renew(Request $request, $id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'quota_gb' => 'nullable|integer|min:0|max:10000',
            'ip_limit' => 'nullable|integer|min:1|max:50',
        ]);

        $res = $vpnService->renewAccount(
            $account,
            (int) $validated['days'],
            $request->filled('quota_gb') ? (int) $validated['quota_gb'] : null,
            $request->filled('ip_limit') ? (int) $validated['ip_limit'] : null
        );

        $this->logAudit('admin_vpn_renew', $account, "days={$validated['days']}; success=" . (!empty($res['success']) ? '1' : '0'));

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil diperpanjang :days hari.', ['user' => $account->username, 'days' => $validated['days']])
                : ($res['message'] ?? __('Gagal memperpanjang akun VPN.'))
        );
    }

    public function suspend(Request $request, $id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);
        $reason = trim((string) $request->input('reason', ''));

        $res = $vpnService->suspendAccount(
            $account,
            'admin',
            Auth::id(),
            $reason !== '' ? $reason : __('Ditangguhkan oleh Admin')
        );

        $this->logAudit('admin_vpn_suspend', $account, "reason={$reason}; success=" . (!empty($res['success']) ? '1' : '0'));

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil disuspend (terkunci dari unsuspend mandiri pelanggan).', ['user' => $account->username])
                : ($res['message'] ?? __('Gagal mensuspend akun VPN.'))
        );
    }

    public function unsuspend($id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);
        $res = $vpnService->unsuspendAccount($account);

        $this->logAudit('admin_vpn_unsuspend', $account, 'success=' . (!empty($res['success']) ? '1' : '0'));

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil diaktifkan kembali.', ['user' => $account->username])
                : ($res['message'] ?? __('Gagal mengaktifkan kembali akun VPN.'))
        );
    }

    public function retry($id, VpnService $vpnService)
    {
        $account = VpnAccount::with(['vpnServer', 'order.items.product'])->findOrFail($id);

        $days = 30;
        $sniMode = '3';
        if ($account->order) {
            $vpnItem = $account->order->items->first(fn ($i) => ($i->product && $i->product->is_vpn) || !empty($i->vpn_meta['protocol']));
            if ($vpnItem) {
                $days = max(1, (int) ($vpnItem->vpn_meta['days'] ?? $vpnItem->product?->vpn_duration_days ?: 30));
                $sniMode = (string) ($vpnItem->vpn_meta['sni_mode'] ?? $vpnItem->product?->vpn_sni_mode ?? '3');
            }
        } elseif ($account->expired_at && $account->created_at) {
            $days = max(1, min(365, (int) $account->created_at->diffInDays($account->expired_at)));
        }

        $idempotencyKey = $account->external_ref
            ? "order-{$account->external_ref}-acct-{$account->id}"
            : "vpn-acct-{$account->id}-create";

        $provisioned = $vpnService->provisionAccount($account, $days, $idempotencyKey, $sniMode);
        $this->logAudit('admin_vpn_retry', $provisioned, "status={$provisioned->status}");

        return back()->with(
            $provisioned->status === 'active' ? 'success' : 'error',
            $provisioned->status === 'active'
                ? __('Provisioning ulang akun :user berhasil!', ['user' => $provisioned->username])
                : __('Provisioning ulang gagal: :err', ['err' => $provisioned->last_error ?: 'Unknown error'])
        );
    }

    public function sync($id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);
        $res = $vpnService->syncAccountFromPanel($account);

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            $res['message'] ?? __('Sinkronisasi selesai.')
        );
    }

    public function syncServer(Request $request, VpnService $vpnService)
    {
        $validated = $request->validate([
            'vpn_server_id' => 'required|exists:vpn_servers,id',
        ]);

        $server = VpnServer::findOrFail($validated['vpn_server_id']);
        $res = $vpnService->listRemoteAccounts($server, null, null, null, true);
        if (empty($res['success'])) {
            return back()->with('error', $res['message'] ?? __('Gagal menarik daftar akun dari panel VPN.'));
        }

        $synced = 0;
        $imported = 0;
        $detailFallbacks = 0;
        $adminId = Auth::id();

        foreach ($res['accounts'] as $remote) {
            $protocol = strtolower((string) ($remote['protocol'] ?? ''));
            $username = (string) ($remote['username'] ?? '');
            if ($protocol === '' || $username === '') {
                continue;
            }

            $existing = VpnAccount::where('vpn_server_id', $server->id)
                ->where('protocol', $protocol)
                ->where('username', $username)
                ->first();

            $expiredAt = !empty($remote['expired_at']) ? Carbon::parse($remote['expired_at'])->endOfDay() : null;
            $status = in_array($remote['status'] ?? 'active', ['active', 'suspended', 'expired'], true)
                ? $remote['status']
                : 'active';

            $hasInlineConfig = !empty($remote['formatted_text']) || !empty($remote['links']) || !empty($remote['ssh_info']);
            $inlinePayload = $hasInlineConfig ? [
                'protocol' => $protocol,
                'username' => $username,
                'domain' => $remote['domain'] ?? (parse_url((string) $server->base_url, PHP_URL_HOST) ?: $server->ssh_host),
                'uuid' => $remote['uuid'] ?? null,
                'status' => $status,
                'quota_gb' => (int) ($remote['quota_gb'] ?? 0),
                'ip_limit' => max(1, (int) ($remote['ip_limit'] ?? 1)),
                'expired_at' => $remote['expired_at'] ?? '',
                'ports' => $remote['ports'] ?? [],
                'links' => $remote['links'] ?? [],
                'ssh_info' => $remote['ssh_info'] ?? null,
                'formatted_text' => $remote['formatted_text'] ?? '',
            ] : null;

            if ($existing) {
                $update = [
                    'status' => $status,
                    'expired_at' => $expiredAt ?: $existing->expired_at,
                    'quota_gb' => (int) ($remote['quota_gb'] ?? $existing->quota_gb),
                    'ip_limit' => max(1, (int) ($remote['ip_limit'] ?? $existing->ip_limit)),
                    'last_synced_at' => now(),
                ];
                if ($inlinePayload) {
                    $update['config_payload'] = $inlinePayload;
                    if (!empty($inlinePayload['formatted_text'])) {
                        $update['config_link'] = $inlinePayload['formatted_text'];
                    }
                    if (!empty($inlinePayload['uuid'])) {
                        $update['uuid'] = $inlinePayload['uuid'];
                    }
                    if (!empty($inlinePayload['domain'])) {
                        $update['server_ip'] = $inlinePayload['domain'];
                    }
                }
                $existing->forceFill($update)->save();
                $accountModel = $existing;
                $synced++;
            } else {
                $accountModel = VpnAccount::create([
                    'user_id' => $adminId,
                    'vpn_server_id' => $server->id,
                    'server_ip' => $remote['domain'] ?? (parse_url((string) $server->base_url, PHP_URL_HOST) ?: $server->ssh_host),
                    'protocol' => $protocol,
                    'username' => $username,
                    'uuid' => $remote['uuid'] ?? null,
                    'quota_gb' => (int) ($remote['quota_gb'] ?? 0),
                    'ip_limit' => max(1, (int) ($remote['ip_limit'] ?? 1)),
                    'config_payload' => $inlinePayload,
                    'config_link' => $inlinePayload['formatted_text'] ?? null,
                    'expired_at' => $expiredAt,
                    'status' => $status,
                    'suspended_by_role' => ($status === 'suspended' && !empty($remote['admin_suspended'])) ? 'admin' : null,
                    'external_ref' => 'panel_sync',
                    'last_synced_at' => now(),
                ]);
                $imported++;
            }

            // Fallback detail fetch for older panels that don't yet support include_config=1 (capped to avoid long timeouts)
            if (!$inlinePayload && empty($accountModel->config_payload) && $detailFallbacks < 15) {
                $vpnService->syncAccountFromPanel($accountModel);
                $detailFallbacks++;
            }
        }

        DB::table('audit_logs')->insert([
            'action' => 'admin_vpn_server_sync',
            'actor_id' => $adminId,
            'entity_type' => 'vpn_server',
            'entity_id' => $server->id,
            'detail' => "server={$server->code}; synced={$synced}; imported={$imported}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.vpn-accounts.index', ['tab' => 'accounts', 'server_id' => $server->id])
            ->with('success', __('Sinkronisasi server :name selesai: :synced diperbarui, :imported akun baru diimpor lengkap dengan detail konfigurasinya.', [
                'name' => $server->name,
                'synced' => $synced,
                'imported' => $imported,
            ]));
    }

    public function destroy($id, VpnService $vpnService)
    {
        $account = VpnAccount::with('vpnServer')->findOrFail($id);
        $username = $account->username;
        $protocol = $account->protocol;

        $res = $vpnService->deleteAccount($account, true);

        DB::table('audit_logs')->insert([
            'action' => 'admin_vpn_delete',
            'actor_id' => Auth::id(),
            'entity_type' => 'vpn_account',
            'entity_id' => (int) $id,
            'detail' => "protocol={$protocol}; username={$username}; success=" . (!empty($res['success']) ? '1' : '0'),
            'created_at' => now(),
        ]);

        return back()->with(
            !empty($res['success']) ? 'success' : 'error',
            !empty($res['success'])
                ? __('Akun VPN :user berhasil dihapus permanen.', ['user' => $username])
                : ($res['message'] ?? __('Gagal menghapus akun VPN.'))
        );
    }

    private function resolveBillingProductId(?Product $selectedProduct, string $protocol): int
    {
        if ($selectedProduct && $selectedProduct->exists) {
            return (int) $selectedProduct->id;
        }

        $matchingId = Product::where('is_vpn', true)
            ->where('is_suspended', false)
            ->where('vpn_protocol', strtolower($protocol))
            ->value('id');
        if ($matchingId) {
            return (int) $matchingId;
        }

        // Fallback: reuse a hidden system product so MySQL FK (order_items.product_id -> products.id)
        // is always satisfied even when the public catalog has 0 VPN products configured.
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

        $created = Product::create([
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
        ]);

        return (int) $created->id;
    }

    private function logAudit(string $action, VpnAccount $account, string $detail): void
    {
        DB::table('audit_logs')->insert([
            'action' => $action,
            'actor_id' => Auth::id(),
            'entity_type' => 'vpn_account',
            'entity_id' => $account->id,
            'detail' => AuditLog::maskSensitiveData($detail),
            'created_at' => now(),
        ]);
    }
}
