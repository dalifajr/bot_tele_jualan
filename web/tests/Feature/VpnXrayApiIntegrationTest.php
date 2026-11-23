<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VpnXrayApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private User $otherCustomer;
    private VpnServer $server;
    private Product $vpnProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'username' => 'admin_vpn',
            'full_name' => 'Admin VPN',
            'email' => 'admin_vpn@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_suspended' => false,
        ]);

        $this->customer = User::forceCreate([
            'username' => 'cust_vpn',
            'full_name' => 'Customer VPN',
            'email' => 'cust_vpn@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'is_suspended' => false,
            'customer_wallet_balance' => 100000,
        ]);

        $this->otherCustomer = User::forceCreate([
            'username' => 'other_cust',
            'full_name' => 'Other Customer',
            'email' => 'other_cust@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'is_suspended' => false,
            'customer_wallet_balance' => 50000,
        ]);

        $this->server = VpnServer::create([
            'name' => 'Singapore SG-01',
            'code' => 'sg-01',
            'region' => 'SG',
            'driver' => VpnServer::DRIVER_VPNXRAY_API,
            'base_url' => 'https://sg1.vpnpanel.test',
            'api_token' => 'vpnx_secret_token_abc123',
            'hmac_secret' => 'hmac_secret_key_0123456789abcdef',
            'timeout_seconds' => 10,
            'verify_ssl' => true,
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->vpnProduct = Product::create([
            'name' => 'VMess SG Premium 30 Hari',
            'price' => 15000,
            'description' => 'Akun VMess Singapore High Speed',
            'is_suspended' => false,
            'is_vpn' => true,
            'vpn_protocol' => 'vmess',
            'vpn_duration_days' => 30,
            'vpn_server_id' => $this->server->id,
            'vpn_quota_gb' => 100,
            'vpn_ip_limit' => 2,
            'vpn_sni_mode' => '3',
        ]);
    }

    private function assertValidHmacSignature(HttpClientRequest $request, string $expectedToken, string $hmacSecret): void
    {
        $this->assertSame('Bearer ' . $expectedToken, $request->header('Authorization')[0] ?? null);
        $ts = $request->header('X-VPN-Timestamp')[0] ?? '';
        $nonce = $request->header('X-VPN-Nonce')[0] ?? '';
        $sig = $request->header('X-VPN-Signature')[0] ?? '';

        $this->assertNotEmpty($ts);
        $this->assertNotEmpty($nonce);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sig);

        $parsed = parse_url($request->url());
        $uri = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        $bodyHash = hash('sha256', (string) $request->body());
        $canonical = implode("\n", [strtoupper($request->method()), $uri, $ts, $nonce, $bodyHash]);
        $expectedSig = hash_hmac('sha256', $canonical, $hmacSecret);

        $this->assertTrue(hash_equals($expectedSig, $sig), 'HMAC-SHA256 signature mismatch.');
    }

    public function test_admin_can_manage_vpn_servers_and_ping_health_with_valid_hmac(): void
    {
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/health' => Http::response([
                'success' => true,
                'message' => 'Healthy',
                'data' => [
                    'bridge_ok' => true,
                    'xray_active' => true,
                    'ssh_active' => true,
                    'domain' => 'sg1.vpnpanel.test',
                ],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.vpn-servers.index'))
            ->assertStatus(200)
            ->assertSee('Singapore SG-01');

        $this->actingAs($this->admin)
            ->postJson(route('admin.vpn-servers.ping', $this->server->id))
            ->assertStatus(200)
            ->assertJsonPath('ok', true);

        Http::assertSent(function (HttpClientRequest $req) {
            $this->assertValidHmacSignature($req, 'vpnx_secret_token_abc123', 'hmac_secret_key_0123456789abcdef');
            return str_contains($req->url(), '/api/v1/vpn/health');
        });

        $this->server->refresh();
        $this->assertSame('online', $this->server->last_status);
    }

    public function test_order_service_post_commit_provisioning_populates_structured_config_payload(): void
    {
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => Http::response([
                'success' => true,
                'message' => 'Created',
                'data' => [
                    'protocol' => 'vmess',
                    'username' => 'buyer_vmess01',
                    'domain' => 'sg1.vpnpanel.test',
                    'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                    'quota_gb' => 100,
                    'ip_limit' => 2,
                    'expired_at' => now()->addDays(30)->format('Y-m-d'),
                    'status' => 'active',
                    'links' => [
                        'tls' => 'vmess://dGVzdA==',
                        'ntls' => 'vmess://dGVzdG50bHM=',
                    ],
                    'formatted_text' => "=== VMESS ACCOUNT ===\nUsername: buyer_vmess01",
                ],
            ], 201),
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-VPN-9001',
            'customer_id' => $this->customer->id,
            'subtotal' => 15000,
            'discount_amount' => 0,
            'unique_code' => 0,
            'total_amount' => 15000,
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->vpnProduct->id,
            'quantity' => 1,
            'unit_price' => 15000,
            'vpn_username' => 'buyer_vmess01',
        ]);

        app(OrderService::class)->confirmPayment($order, 'test_gateway', $this->admin->id);

        Http::assertSent(function (HttpClientRequest $req) {
            $this->assertValidHmacSignature($req, 'vpnx_secret_token_abc123', 'hmac_secret_key_0123456789abcdef');
            $this->assertNotEmpty($req->header('Idempotency-Key')[0] ?? '');
            return $req->method() === 'POST' && str_ends_with($req->url(), '/api/v1/vpn/accounts');
        });

        $vpn = VpnAccount::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('active', $vpn->status);
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $vpn->uuid);
        $this->assertSame('vmess://dGVzdA==', $vpn->config_payload['links']['tls'] ?? null);
        $this->assertSame($this->server->id, $vpn->vpn_server_id);
    }

    public function test_customer_full_crud_and_admin_suspend_lock_requires_chat_admin(): void
    {
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/check-username*' => Http::response([
                'success' => true,
                'message' => 'Available',
                'data' => ['available' => true, 'exists' => false],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => Http::response([
                'success' => true,
                'message' => 'Created',
                'data' => [
                    'protocol' => 'vmess',
                    'username' => 'my_self_vpn01',
                    'domain' => 'sg1.vpnpanel.test',
                    'uuid' => '12345678-1234-1234-1234-1234567890ab',
                    'quota_gb' => 100,
                    'ip_limit' => 2,
                    'expired_at' => now()->addDays(30)->format('Y-m-d'),
                    'status' => 'active',
                    'links' => ['tls' => 'vmess://bXl2cG4='],
                    'formatted_text' => 'VMESS my_self_vpn01',
                ],
            ], 201),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/my_self_vpn01/renew' => Http::response([
                'success' => true,
                'message' => 'Renewed',
                'data' => ['expired_at' => now()->addDays(60)->format('Y-m-d')],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/my_self_vpn01/suspend' => Http::response([
                'success' => true,
                'message' => 'Suspended',
                'data' => ['status' => 'suspended'],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/my_self_vpn01/unsuspend' => Http::response([
                'success' => true,
                'message' => 'Unsuspended',
                'data' => ['status' => 'active'],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/my_self_vpn01' => Http::response([
                'success' => true,
                'message' => 'Deleted',
                'data' => ['deleted' => true],
            ], 200),
        ]);

        // 1. Real-time AJAX username availability check
        $this->actingAs($this->customer)
            ->getJson(route('catalog.check-vpn-username', $this->vpnProduct->id) . '?username=my_self_vpn01')
            ->assertStatus(200)
            ->assertJsonPath('available', true);

        // 2. Customer CREATE via /my-vpn using wallet balance
        $this->actingAs($this->customer)
            ->post(route('vpn.store'), [
                'product_id' => $this->vpnProduct->id,
                'username' => 'my_self_vpn01',
            ])
            ->assertRedirect(route('vpn.my-accounts'))
            ->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertSame(85000, (int) $this->customer->customer_wallet_balance);

        $account = VpnAccount::where('username', 'my_self_vpn01')->firstOrFail();
        $this->assertSame('active', $account->status);

        // 3. Ownership isolation: another customer cannot touch this account
        $this->actingAs($this->otherCustomer)
            ->post(route('vpn.suspend', $account->id))
            ->assertStatus(404);

        // 4. Customer SELF-SUSPEND & SELF-UNSUSPEND works when suspended by customer
        $this->actingAs($this->customer)
            ->post(route('vpn.suspend', $account->id))
            ->assertSessionHas('success');
        $account->refresh();
        $this->assertSame('suspended', $account->status);
        $this->assertSame('customer', $account->suspended_by_role);

        $this->actingAs($this->customer)
            ->post(route('vpn.unsuspend', $account->id))
            ->assertSessionHas('success');
        $account->refresh();
        $this->assertSame('active', $account->status);

        // 5. Customer RENEW works when not admin-suspended
        $this->actingAs($this->customer)
            ->post(route('vpn.renew', $account->id), ['days' => 30])
            ->assertSessionHas('success');
        $this->customer->refresh();
        $this->assertSame(70000, (int) $this->customer->customer_wallet_balance);

        // 6. ADMIN SUSPENDS account -> sets admin lock!
        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.suspend', $account->id), ['reason' => 'Multi-login violation'])
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertTrue($account->isAdminSuspended());
        $this->assertSame('admin', $account->suspended_by_role);

        // Customer is BLOCKED from self-unsuspend, renew, and delete!
        $this->actingAs($this->customer)
            ->post(route('vpn.unsuspend', $account->id))
            ->assertSessionHas('error');
        $this->assertSame('suspended', $account->fresh()->status);

        $this->actingAs($this->customer)
            ->post(route('vpn.renew', $account->id), ['days' => 30])
            ->assertSessionHas('error');

        $this->actingAs($this->customer)
            ->delete(route('vpn.destroy', $account->id))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('vpn_accounts', ['id' => $account->id]);

        // /my-vpn view shows Chat Admin CTA to request unlock
        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertSee('Minta Buka Suspend via Chat Admin')
            ->assertSee(route('chat.index', ['contact_id' => $this->admin->id]), false);

        // 7. Admin UNSUSPENDS account -> customer regains CRUD control and can delete
        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.unsuspend', $account->id))
            ->assertSessionHas('success');
        $this->assertSame('active', $account->fresh()->status);

        $this->actingAs($this->customer)
            ->delete(route('vpn.destroy', $account->id))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('vpn_accounts', ['id' => $account->id]);
    }

    public function test_admin_retry_provisioning_recovers_failed_vpn_account(): void
    {
        $failed = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vless',
            'username' => 'retry_user01',
            'quota_gb' => 50,
            'ip_limit' => 1,
            'external_ref' => 'ORD-RETRY-01',
            'expired_at' => now()->addDays(30),
            'status' => 'failed',
            'last_error' => 'Timeout connecting to VPS',
        ]);

        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => Http::response([
                'success' => true,
                'message' => 'Created',
                'data' => [
                    'protocol' => 'vless',
                    'username' => 'retry_user01',
                    'domain' => 'sg1.vpnpanel.test',
                    'uuid' => '99999999-8888-7777-6666-555555555555',
                    'expired_at' => now()->addDays(30)->format('Y-m-d'),
                    'status' => 'active',
                    'links' => ['tls' => 'vless://99999999@sg1.vpnpanel.test:443#retry_user01'],
                    'formatted_text' => 'VLESS retry_user01',
                ],
            ], 201),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.retry', $failed->id))
            ->assertSessionHas('success');

        $failed->refresh();
        $this->assertSame('active', $failed->status);
        $this->assertNull($failed->last_error);
        $this->assertSame('99999999-8888-7777-6666-555555555555', $failed->uuid);
    }

    public function test_admin_can_run_vpn_server_diagnostic_endpoint_and_artisan_command(): void
    {
        // Use a legacy_ssh server without valid host so diagnostic executes deterministically offline without external sockets
        $sshSrv = VpnServer::create([
            'name' => 'Legacy Test Node',
            'code' => 'legacy-diag',
            'driver' => VpnServer::DRIVER_LEGACY_SSH,
            'ssh_host' => '',
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.vpn-servers.diagnose', $sshSrv->id))
            ->assertStatus(200)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('server_code', 'legacy-diag')
            ->assertJsonStructure(['ok', 'server_name', 'endpoint', 'headline', 'steps', 'recommendations']);

        $this->artisan('vpn:diagnose', ['server' => 'legacy-diag'])
            ->assertExitCode(1);
    }

    public function test_unified_admin_vpn_menu_tabs_server_sync_with_config_and_lazy_fetch(): void
    {
        // Unified page renders both tabs cleanly; legacy /admin/vpn-servers route also renders unified view on servers tab
        $this->actingAs($this->admin)
            ->get(route('admin.vpn-accounts.index'))
            ->assertStatus(200)
            ->assertSee('Kelola VPN')
            ->assertSee('tab-vpn-accounts-btn')
            ->assertSee('tab-vpn-servers-btn')
            ->assertSee('adminVpnActionSheetModal');

        $this->actingAs($this->admin)
            ->get(route('admin.vpn-servers.index'))
            ->assertStatus(200)
            ->assertSee('Kelola VPN')
            ->assertSee('Singapore SG-01');

        // 1. Server Sync with include_config=1 populates config_payload, config_link, and uuid immediately
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts?include_config=1' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'accounts' => [
                        [
                            'protocol' => 'vmess',
                            'username' => 'synced_full_01',
                            'domain' => 'sg1.vpnpanel.test',
                            'uuid' => '55555555-6666-7777-8888-999999999999',
                            'status' => 'active',
                            'quota_gb' => 50,
                            'ip_limit' => 2,
                            'expired_at' => now()->addDays(20)->format('Y-m-d'),
                            'links' => ['tls' => 'vmess://c3luY2Vk'],
                            'formatted_text' => "=== VMESS ===\nUser: synced_full_01",
                        ],
                    ],
                ],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/lazy_empty_01*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'protocol' => 'vmess',
                    'username' => 'lazy_empty_01',
                    'domain' => 'sg1.vpnpanel.test',
                    'uuid' => '11111111-aaaa-bbbb-cccc-222222222222',
                    'status' => 'active',
                    'quota_gb' => 10,
                    'ip_limit' => 1,
                    'expired_at' => now()->addDays(15)->format('Y-m-d'),
                    'links' => ['tls' => 'vmess://bGF6eQ=='],
                    'formatted_text' => "=== VMESS LAZY ===\nUser: lazy_empty_01",
                ],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.sync-server'), ['vpn_server_id' => $this->server->id])
            ->assertRedirect(route('admin.vpn-accounts.index', ['tab' => 'accounts', 'server_id' => $this->server->id]))
            ->assertSessionHas('success');

        $synced = VpnAccount::where('username', 'synced_full_01')->firstOrFail();
        $this->assertSame('55555555-6666-7777-8888-999999999999', $synced->uuid);
        $this->assertSame('vmess://c3luY2Vk', $synced->config_payload['links']['tls'] ?? null);
        $this->assertStringContainsString('synced_full_01', (string) $synced->config_link);

        // 2. Lazy auto-fetch AJAX populates empty config on demand (Admin & Customer endpoints)
        $emptyAcc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'lazy_empty_01',
            'status' => 'active',
            'expired_at' => now()->addDays(15),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.vpn-accounts.config', $emptyAcc->id))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('config_payload.links.tls', 'vmess://bGF6eQ==');

        $emptyAcc->refresh();
        $this->assertSame('vmess://bGF6eQ==', $emptyAcc->config_payload['links']['tls'] ?? null);

        $this->actingAs($this->customer)
            ->getJson(route('vpn.config', $emptyAcc->id))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('config_payload.links.tls', 'vmess://bGF6eQ==');
    }

    public function test_admin_manual_vpn_create_paid_and_unpaid_billing_flow(): void
    {
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/check-username*' => Http::response([
                'success' => true,
                'message' => 'Available',
                'data' => ['available' => true, 'exists' => false],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => Http::sequence()
                ->push([
                    'success' => true,
                    'message' => 'Created',
                    'data' => [
                        'protocol' => 'vless',
                        'username' => 'paid_for_cust01',
                        'domain' => 'sg1.vpnpanel.test',
                        'uuid' => 'aaaa1111-bbbb-2222-cccc-333333333333',
                        'status' => 'active',
                        'expired_at' => now()->addDays(30)->format('Y-m-d'),
                        'links' => ['tls' => 'vless://paid@sg1.vpnpanel.test:443#paid_for_cust01'],
                        'formatted_text' => 'VLESS paid_for_cust01',
                    ],
                ], 201)
                ->push([
                    'success' => true,
                    'message' => 'Created',
                    'data' => [
                        'protocol' => 'trojan',
                        'username' => 'billed_cust01',
                        'domain' => 'sg1.vpnpanel.test',
                        'uuid' => 'dddd4444-eeee-5555-ffff-666666666666',
                        'status' => 'active',
                        'expired_at' => now()->addDays(60)->format('Y-m-d'),
                        'links' => ['tls' => 'trojan://billed@sg1.vpnpanel.test:443#billed_cust01'],
                        'formatted_text' => 'TROJAN billed_cust01',
                    ],
                ], 201),
        ]);

        // 1. Live Admin AJAX username check
        $this->actingAs($this->admin)
            ->getJson(route('admin.vpn-accounts.check-username', ['username' => 'paid_for_cust01', 'protocol' => 'vless', 'server_id' => $this->server->id]))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('available', true);

        // 2. Paid mode (is_paid = 1): provisions immediately & appears in customer's /my-vpn
        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.store'), [
                'assign_target' => 'customer',
                'customer_id' => $this->customer->id,
                'vpn_server_id' => $this->server->id,
                'protocol' => 'vless',
                'username' => 'paid_for_cust01',
                'days' => 30,
                'quota_gb' => 100,
                'ip_limit' => 2,
                'is_paid' => 1,
            ])
            ->assertRedirect(route('admin.vpn-accounts.index', ['tab' => 'accounts']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vpn_accounts', [
            'user_id' => $this->customer->id,
            'username' => 'paid_for_cust01',
            'protocol' => 'vless',
            'status' => 'active',
        ]);

        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertSee('paid_for_cust01');

        // 3. Unpaid Billing mode (is_paid = 0): creates pending Order + OrderItem(vpn_meta) + Payment; auto-provisions on confirmPayment!
        $res = $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.store'), [
                'assign_target' => 'customer',
                'customer_id' => $this->customer->id,
                'vpn_server_id' => $this->server->id,
                'protocol' => 'trojan',
                'username' => 'billed_cust01',
                'days' => 60,
                'quota_gb' => 250,
                'ip_limit' => 3,
                'is_paid' => 0,
                'billing_amount' => 25000,
            ]);

        $order = Order::where('customer_id', $this->customer->id)
            ->where('status', 'pending_payment')
            ->latest('id')
            ->firstOrFail();

        $res->assertRedirect(route('checkout.success', ['order_ref' => $order->order_ref]));
        $this->assertDatabaseMissing('vpn_accounts', ['username' => 'billed_cust01']);

        $item = $order->items()->firstOrFail();
        $this->assertSame('trojan', $item->vpn_meta['protocol'] ?? null);
        $this->assertSame(60, $item->vpn_meta['days'] ?? null);
        $this->assertSame(250, $item->vpn_meta['quota_gb'] ?? null);

        // Once paid -> OrderService automatically provisions custom trojan VPN account for customer!
        app(OrderService::class)->confirmPayment($order, 'qris_auto', $this->admin->id);

        $provisioned = VpnAccount::where('username', 'billed_cust01')->firstOrFail();
        $this->assertSame('active', $provisioned->status);
        $this->assertSame('trojan', $provisioned->protocol);
        $this->assertSame($this->customer->id, (int) $provisioned->user_id);
        $this->assertSame(250, (int) $provisioned->quota_gb);
        $this->assertSame(3, (int) $provisioned->ip_limit);
    }

    public function test_customer_my_vpn_gating_and_one_day_quick_renew(): void
    {
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/quick_renew_01/renew' => Http::response([
                'success' => true,
                'message' => 'Renewed',
                'data' => ['expired_at' => now()->addDays(11)->format('Y-m-d')],
            ], 200),
        ]);

        $account = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'quick_renew_01',
            'status' => 'active',
            'expired_at' => now()->addDays(10),
        ]);

        // With active VPN product: Create button is shown & +1 day renew works
        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertSee('customerCreateVpnModal')
            ->assertSee('myVpnActionSheetModal');

        $this->actingAs($this->customer)
            ->post(route('vpn.renew', $account->id), ['days' => 1])
            ->assertSessionHas('success');

        // Suspend all VPN products -> "Buat Akun VPN" button & modal hidden, and POST blocked
        Product::where('is_vpn', true)->update(['is_suspended' => true]);

        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertDontSee('customerCreateVpnModal');

        $this->actingAs($this->customer)
            ->post(route('vpn.store'), [
                'product_id' => $this->vpnProduct->id,
                'username' => 'blocked_create01',
            ])
            ->assertSessionHas('error');
    }

    public function test_desktop_dropdown_preserved_mobile_sheet_and_sni_preset_across_all_create_flows(): void
    {
        $capturedSniModes = [];

        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/check-username*' => Http::response([
                'success' => true,
                'message' => 'Available',
                'data' => ['available' => true, 'exists' => false],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => function (HttpClientRequest $req) use (&$capturedSniModes) {
                $body = json_decode((string) $req->body(), true) ?: [];
                $capturedSniModes[$body['username'] ?? 'unknown'] = (string) ($body['sni_mode'] ?? '');
                return Http::response([
                    'success' => true,
                    'message' => 'Created',
                    'data' => [
                        'protocol' => $body['protocol'] ?? 'vmess',
                        'username' => $body['username'] ?? 'user',
                        'domain' => 'sg1.vpnpanel.test',
                        'uuid' => '77777777-8888-9999-aaaa-bbbbbbbbbbbb',
                        'sni_mode' => (string) ($body['sni_mode'] ?? '3'),
                        'status' => 'active',
                        'expired_at' => now()->addDays(30)->format('Y-m-d'),
                        'links' => ['tls' => 'vmess://em9vbQ=='],
                        'formatted_text' => 'VMESS ' . ($body['username'] ?? ''),
                    ],
                ], 201);
            },
        ]);

        // 1. Admin manual create with Zoom preset (sni_mode = 1)
        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.store'), [
                'assign_target' => 'customer',
                'customer_id' => $this->customer->id,
                'vpn_server_id' => $this->server->id,
                'protocol' => 'vmess',
                'username' => 'admin_zoom_01',
                'days' => 30,
                'quota_gb' => 50,
                'ip_limit' => 2,
                'sni_mode' => '1',
                'is_paid' => 1,
            ])
            ->assertSessionHas('success');

        $this->assertSame('1', $capturedSniModes['admin_zoom_01'] ?? null);
        $adminAcc = VpnAccount::where('username', 'admin_zoom_01')->firstOrFail();
        $this->assertSame('1', (string) ($adminAcc->config_payload['sni_mode'] ?? ''));

        // Verify Admin page renders Desktop Dropdown (strategy: fixed) AND keeps Mobile Sheet + SNI selectors
        $this->actingAs($this->admin)
            ->get(route('admin.vpn-accounts.index'))
            ->assertStatus(200)
            ->assertSee('data-bs-toggle="dropdown"', false)
            ->assertSee('data-bs-popper-config=\'{"strategy": "fixed"}\'', false)
            ->assertSee('btn-open-admin-vpn-sheet')
            ->assertSee('adminCreateSniMode')
            ->assertSee('vpnConfigSniSelect');

        // 2. Customer /my-vpn create with Iflix preset (sni_mode = 2)
        $this->actingAs($this->customer)
            ->post(route('vpn.store'), [
                'product_id' => $this->vpnProduct->id,
                'username' => 'cust_iflix_01',
                'sni_mode' => '2',
            ])
            ->assertRedirect(route('vpn.my-accounts'))
            ->assertSessionHas('success');

        $this->assertSame('2', $capturedSniModes['cust_iflix_01'] ?? null);

        // Verify /my-vpn renders Desktop Dropdown AND keeps Mobile Sheet + SNI selectors
        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertSee('data-bs-toggle="dropdown"', false)
            ->assertSee('data-bs-popper-config=\'{"strategy": "fixed"}\'', false)
            ->assertSee('btn-open-my-vpn-sheet')
            ->assertSee('custCreateSniMode')
            ->assertSee('myVpnConfigSniSelect');

        // 3. Catalog show (VCC-style VPN modal) -> Checkout review -> Checkout store -> OrderService forwards Zoom preset (vpn_sni_mode = 1)
        $this->actingAs($this->customer)
            ->get(route('catalog.show', $this->vpnProduct->id))
            ->assertStatus(200)
            ->assertSee('vpnSelectorBackdrop', false)
            ->assertSee('vpnSelSniMode', false)
            ->assertSee('support.zoom.us');

        $this->actingAs($this->customer)
            ->get(route('checkout.review', [
                'product' => $this->vpnProduct->id,
                'vpn_username' => 'cat_zoom_01',
                'vpn_sni_mode' => '1',
            ]))
            ->assertStatus(200)
            ->assertSee('Zoom (support.zoom.us)')
            ->assertSee('name="vpn_sni_mode" value="1"', false);

        $this->actingAs($this->customer)
            ->post(route('checkout.store', $this->vpnProduct->id), [
                'quantity' => 1,
                'vpn_username' => 'cat_zoom_01',
                'vpn_sni_mode' => '1',
                'payment_method' => 'qris',
            ])
            ->assertRedirect();

        $order = Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertSame('1', (string) ($item->vpn_meta['sni_mode'] ?? ''));

        app(OrderService::class)->confirmPayment($order, 'qris_auto', $this->admin->id);
        $this->assertSame('1', $capturedSniModes[$item->vpn_username] ?? null);
    }

    public function test_admin_manual_vpn_billing_works_with_zero_products_and_valid_foreign_key(): void
    {
        // Delete ALL catalog products so table `products` is completely empty (reproduces MySQL FK 1452 scenario)
        Product::query()->forceDelete();
        $this->assertSame(0, Product::withTrashed()->count());

        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/check-username*' => Http::response([
                'success' => true,
                'message' => 'Available',
                'data' => ['available' => true, 'exists' => false],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => Http::response([
                'success' => true,
                'message' => 'Created',
                'data' => [
                    'protocol' => 'vmess',
                    'username' => 'iann_billing01',
                    'domain' => 'sg1.vpnpanel.test',
                    'uuid' => '1234abcd-5678-ef90-1234-abcdef567890',
                    'sni_mode' => '1',
                    'status' => 'active',
                    'expired_at' => now()->addDays(30)->format('Y-m-d'),
                    'links' => ['tls' => 'vmess://aWFubg=='],
                    'formatted_text' => 'VMESS iann_billing01',
                ],
            ], 201),
        ]);

        $res = $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.store'), [
                'assign_target' => 'customer',
                'customer_id' => $this->customer->id,
                'vpn_server_id' => $this->server->id,
                'protocol' => 'vmess',
                'username' => 'iann_billing01',
                'days' => 30,
                'quota_gb' => 0,
                'ip_limit' => 2,
                'sni_mode' => '1',
                'is_paid' => 0,
                'billing_amount' => 10000,
            ]);

        $order = Order::where('customer_id', $this->customer->id)
            ->where('status', 'pending_payment')
            ->latest('id')
            ->firstOrFail();

        $res->assertRedirect(route('checkout.success', ['order_ref' => $order->order_ref]));

        // OrderItem product_id MUST reference an existing row in `products` (MySQL FK order_items_ibfk_2)
        $item = $order->items()->firstOrFail();
        $this->assertGreaterThan(0, (int) $item->product_id);
        $this->assertDatabaseHas('products', [
            'id' => $item->product_id,
            'is_suspended' => true,
            'vpn_duration_days' => 0,
        ]);

        // Hidden system product must NOT appear in Admin Products list, Public Catalog, or unlock /my-vpn Create button
        $this->actingAs($this->admin)
            ->get(route('admin.products.index'))
            ->assertStatus(200)
            ->assertDontSee('Layanan VPN Custom (Tagihan Admin)');

        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertDontSee('customerCreateVpnModal');

        // Customer's notification URL must point to checkout.success (not /admin/orders)
        $custNotif = $this->customer->notifications()->latest()->first();
        $this->assertNotNull($custNotif);
        $this->assertStringContainsString('/checkout/success/' . $order->order_ref, (string) ($custNotif->data['url'] ?? ''));

        // Order detail displays custom VPN label
        $this->actingAs($this->customer)
            ->get(route('orders.show', $order->id))
            ->assertStatus(200)
            ->assertSee('VPN VMESS (iann_billing01) 30 Hari');

        // Paying the invoice auto-provisions the VPN account for customer
        app(OrderService::class)->confirmPayment($order, 'qris_auto', $this->admin->id);
        $this->assertDatabaseHas('vpn_accounts', [
            'user_id' => $this->customer->id,
            'username' => 'iann_billing01',
            'protocol' => 'vmess',
            'status' => 'active',
            'purchase_price' => 10000,
            'purchase_days' => 30,
        ]);

        // Both Admin Order Detail and Customer Order Detail display VPN details, Zoom Preset SNI, and QR button/modal
        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $order->id))
            ->assertStatus(200)
            ->assertSee('Detail Akun VPN Terkirim')
            ->assertSee('iann_billing01')
            ->assertSee('Zoom (support.zoom.us)')
            ->assertSee('Lihat Kode QR')
            ->assertSee('orderVpnQrModal');

        $this->actingAs($this->customer)
            ->get(route('orders.show', $order->id))
            ->assertStatus(200)
            ->assertSee('Konfigurasi Akun VPN')
            ->assertSee('Zoom (support.zoom.us)')
            ->assertSee('Lihat Kode QR')
            ->assertSee('orderVpnQrModal');
    }

    public function test_customer_renew_charges_purchase_price_and_blocks_free_renew_when_unpriced(): void
    {
        // Delete all catalog products to guarantee pricing is NOT falling back to catalog
        Product::query()->forceDelete();

        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/billed_renew01/renew' => Http::response([
                'success' => true,
                'message' => 'Renewed',
                'data' => ['expired_at' => now()->addDays(60)->format('Y-m-d')],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/paid_manual01/renew' => Http::response([
                'success' => true,
                'message' => 'Renewed',
                'data' => ['expired_at' => now()->addDays(37)->format('Y-m-d')],
            ], 200),
        ]);

        // Case 1: Legacy order-billed account (purchase_price null, but order_items.unit_price = 1000 / 30 days)
        $sysProd = Product::create([
            'name' => 'Layanan VPN Custom (Tagihan Admin)',
            'price' => 0,
            'is_suspended' => true,
            'is_vpn' => true,
            'vpn_protocol' => 'vmess',
            'vpn_duration_days' => 0,
        ]);
        $legacyOrder = Order::create([
            'order_ref' => 'ORD-LEG-953',
            'customer_id' => $this->customer->id,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'unique_code' => 152,
            'total_amount' => 1152,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $legacyOrder->id,
            'product_id' => $sysProd->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'vpn_username' => 'billed_renew01',
            'vpn_meta' => ['protocol' => 'vmess', 'days' => 30, 'sni_mode' => '1'],
        ]);
        $billedAcc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'order_id' => $legacyOrder->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'billed_renew01',
            'status' => 'active',
            'expired_at' => now()->addDays(30),
        ]);

        $balanceBefore = (int) $this->customer->customer_wallet_balance; // 100000
        $this->actingAs($this->customer)
            ->post(route('vpn.renew', $billedAcc->id), ['days' => 30])
            ->assertSessionHas('success');
        $this->customer->refresh();
        $this->assertSame($balanceBefore - 1000, (int) $this->customer->customer_wallet_balance);

        // Case 2: Admin manual paid account with explicit purchase_price = 15000 / 30 days -> +7 days = Rp 3.500
        $manualAcc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'paid_manual01',
            'purchase_price' => 15000,
            'purchase_days' => 30,
            'status' => 'active',
            'expired_at' => now()->addDays(30),
        ]);

        $balanceBeforeManual = (int) $this->customer->customer_wallet_balance; // 99000
        $this->actingAs($this->customer)
            ->post(route('vpn.renew', $manualAcc->id), ['days' => 7])
            ->assertSessionHas('success');
        $this->customer->refresh();
        $this->assertSame($balanceBeforeManual - 3500, (int) $this->customer->customer_wallet_balance);

        // Case 3: Unpriced account (no purchase_price, no order, no catalog VPN product) -> MUST BLOCK free renew!
        $unpricedAcc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'unpriced_sync01',
            'status' => 'active',
            'expired_at' => now()->addDays(30),
        ]);

        $balanceBeforeBlocked = (int) $this->customer->customer_wallet_balance;
        $this->actingAs($this->customer)
            ->post(route('vpn.renew', $unpricedAcc->id), ['days' => 30])
            ->assertSessionHas('error');
        $this->customer->refresh();
        $this->assertSame($balanceBeforeBlocked, (int) $this->customer->customer_wallet_balance);

        // Verify /my-vpn uses Material You confirmAction, clean M3 action tiles, live cost preview, QRIS renew option, and zero native confirm()
        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertSee('m3-sheet-action-tile')
            ->assertSee('custRenewCostPreview')
            ->assertSee('custRenewPayQris')
            ->assertSee('confirmAction(event')
            ->assertDontSee('return confirm(');
    }

    public function test_vcc_style_multi_protocol_vpn_product_checkout_and_proportional_pricing(): void
    {
        $capturedPayload = null;
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/check-username*' => Http::response([
                'success' => true,
                'message' => 'Available',
                'data' => ['available' => true, 'exists' => false],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => function ($request) use (&$capturedPayload) {
                $capturedPayload = $request->data();
                return Http::response([
                    'success' => true,
                    'message' => 'Created',
                    'data' => [
                        'protocol' => $capturedPayload['protocol'] ?? 'vless',
                        'username' => $capturedPayload['username'] ?? 'vless_custom01',
                        'domain' => 'sg1.vpnpanel.test',
                        'uuid' => '9999aaaa-bbbb-cccc-dddd-eeeeffff0000',
                        'sni_mode' => $capturedPayload['sni_mode'] ?? '1',
                        'status' => 'active',
                        'expired_at' => now()->addDays((int) ($capturedPayload['days'] ?? 60))->format('Y-m-d'),
                        'links' => ['tls' => 'vless://9999aaaa@sg1.vpnpanel.test:443'],
                        'formatted_text' => 'VLESS Account',
                    ],
                ], 201);
            },
        ]);

        // 1. Admin creates multi-protocol VPN product (like VCC multi-service checkboxes)
        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'VPN All-in-One SG',
                'price' => 15000,
                'description' => 'Multi protokol fleksibel',
                'is_vpn' => 1,
                'vpn_protocols' => ['vmess', 'vless', 'ssh'],
                'vpn_duration_days' => 30,
                'vpn_quota_gb' => 0,
                'vpn_ip_limit' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $multiProd = Product::where('name', 'VPN All-in-One SG')->firstOrFail();
        $this->assertSame(['vmess', 'vless', 'ssh'], $multiProd->getAvailableVpnProtocols());
        $this->assertSame('vmess', $multiProd->vpn_protocol);

        // 2. Catalog show renders VCC-style VPN selector modal & config summary
        $this->actingAs($this->customer)
            ->get(route('catalog.show', $multiProd->id))
            ->assertStatus(200)
            ->assertSee('openVpnCheckoutSelector')
            ->assertSee('vpnSelectorBackdrop')
            ->assertSee('VMESS')
            ->assertSee('VLESS')
            ->assertSee('SSH');

        // 3. Checkout review with user-configured VLESS, 60 days (proportional = Rp 30.000), Zoom SNI
        $this->actingAs($this->customer)
            ->get(route('checkout.review', [
                'product' => $multiProd->id,
                'vpn_protocol' => 'vless',
                'vpn_days' => 60,
                'vpn_server_id' => $this->server->id,
                'vpn_username' => 'vless_custom01',
                'vpn_sni_mode' => '1',
            ]))
            ->assertStatus(200)
            ->assertSee('VLESS')
            ->assertSee('vless_custom01')
            ->assertSee('60')
            ->assertSee('Zoom (support.zoom.us)')
            ->assertSee('30.000');

        // 4. Checkout store preserves exact username (no random suffix) and proportional price
        $this->actingAs($this->customer)
            ->post(route('checkout.store', $multiProd->id), [
                'quantity' => 1,
                'vpn_protocol' => 'vless',
                'vpn_days' => 60,
                'vpn_server_id' => $this->server->id,
                'vpn_username' => 'vless_custom01',
                'vpn_sni_mode' => '1',
                'payment_method' => 'qris',
            ])
            ->assertRedirect();

        $order = Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertSame(30000, (int) $order->subtotal);
        $item = $order->items()->firstOrFail();
        $this->assertSame('vless_custom01', $item->vpn_username);
        $this->assertSame('vless', $item->vpn_meta['protocol'] ?? null);
        $this->assertSame(60, (int) ($item->vpn_meta['days'] ?? 0));
        $this->assertSame('1', (string) ($item->vpn_meta['sni_mode'] ?? ''));

        // 5. Confirm payment provisions VLESS for 60 days & records purchase pricing
        app(OrderService::class)->confirmPayment($order, 'qris_auto', $this->admin->id);
        $this->assertSame('vless', $capturedPayload['protocol'] ?? null);
        $this->assertSame('vless_custom01', $capturedPayload['username'] ?? null);
        $this->assertSame(60, (int) ($capturedPayload['days'] ?? 0));
        $this->assertDatabaseHas('vpn_accounts', [
            'user_id' => $this->customer->id,
            'username' => 'vless_custom01',
            'protocol' => 'vless',
            'purchase_price' => 30000,
            'purchase_days' => 60,
            'status' => 'active',
        ]);
    }

    public function test_cross_protocol_username_uniqueness_and_ssh_create_with_short_password(): void
    {
        // Existing VMESS account named "iann"
        VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'iann',
            'status' => 'active',
            'expired_at' => now()->addDays(30),
        ]);

        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/check-username*' => Http::response([
                'success' => true,
                'message' => 'Available',
                'data' => ['available' => true, 'exists' => false],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => Http::response([
                'success' => true,
                'message' => 'Created',
                'data' => [
                    'protocol' => 'ssh',
                    'username' => 'iann_ssh01',
                    'domain' => 'sg1.vpnpanel.test',
                    'status' => 'active',
                    'expired_at' => now()->addDays(30)->format('Y-m-d'),
                    'formatted_text' => 'SSH Account iann_ssh01',
                ],
            ], 201),
        ]);

        // 1. Checking "iann" on SSH MUST return unavailable with plain message (no protocol leak!)
        $this->actingAs($this->admin)
            ->getJson(route('admin.vpn-accounts.check-username', [
                'username' => 'iann',
                'protocol' => 'ssh',
                'vpn_server_id' => $this->server->id,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('available', false)
            ->assertJsonPath('message', 'Username sudah terpakai.');

        // 2. Trying to create SSH "iann" via admin store MUST be rejected
        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.store'), [
                'assign_target' => 'customer',
                'customer_id' => $this->customer->id,
                'vpn_server_id' => $this->server->id,
                'protocol' => 'ssh',
                'username' => 'iann',
                'password' => '123',
                'days' => 30,
                'is_paid' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Username sudah terpakai.');

        // 3. Creating SSH with fresh username "iann_ssh01" and 3-char password "123" MUST succeed
        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.store'), [
                'assign_target' => 'customer',
                'customer_id' => $this->customer->id,
                'vpn_server_id' => $this->server->id,
                'protocol' => 'ssh',
                'username' => 'iann_ssh01',
                'password' => '123',
                'days' => 30,
                'is_paid' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vpn_accounts', [
            'user_id' => $this->customer->id,
            'username' => 'iann_ssh01',
            'protocol' => 'ssh',
            'status' => 'active',
        ]);

        // 4. Invalid 2-char password flashes validation error & renders visibly via layout $errors->any()
        $resInvalid = $this->actingAs($this->admin)
            ->from(route('admin.vpn-accounts.index'))
            ->post(route('admin.vpn-accounts.store'), [
                'assign_target' => 'self',
                'vpn_server_id' => $this->server->id,
                'protocol' => 'ssh',
                'username' => 'iann_ssh02',
                'password' => '12',
                'days' => 30,
                'is_paid' => 1,
            ]);
        $resInvalid->assertRedirect(route('admin.vpn-accounts.index'))
            ->assertSessionHasErrors('password');
    }

    public function test_customer_renew_via_qris_creates_pending_order_and_renews_existing_account_on_payment(): void
    {
        // Customer has Rp 0 wallet balance -> can still renew via QRIS!
        $this->customer->update(['customer_wallet_balance' => 0]);

        $renewCapturedDays = null;
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/qris_renew01/renew' => function ($request) use (&$renewCapturedDays) {
                $renewCapturedDays = (int) ($request->data()['days'] ?? 0);
                return Http::response([
                    'success' => true,
                    'message' => 'Renewed',
                    'data' => ['expired_at' => now()->addDays(44)->format('Y-m-d')],
                ], 200);
            },
        ]);

        $acc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'qris_renew01',
            'purchase_price' => 15000,
            'purchase_days' => 30,
            'status' => 'active',
            'expired_at' => now()->addDays(30),
        ]);

        $res = $this->actingAs($this->customer)
            ->post(route('vpn.renew', $acc->id), [
                'days' => 14,
                'payment_method' => 'qris',
            ]);

        $order = Order::where('customer_id', $this->customer->id)
            ->where('status', 'pending_payment')
            ->latest('id')
            ->firstOrFail();

        $res->assertRedirect(route('checkout.success', ['order_ref' => $order->order_ref]));
        $this->assertSame(7000, (int) $order->subtotal);

        $item = $order->items()->firstOrFail();
        $this->assertSame('renew', $item->vpn_meta['action'] ?? null);
        $this->assertSame($acc->id, (int) ($item->vpn_meta['vpn_account_id'] ?? 0));
        $this->assertSame(14, (int) ($item->vpn_meta['days'] ?? 0));

        // Simulate QRIS payment confirmation -> MUST call renewAccount, NOT create duplicate account
        $countBefore = VpnAccount::count();
        app(OrderService::class)->confirmPayment($order, 'qris_auto', $this->admin->id);

        $this->assertSame(14, $renewCapturedDays);
        $this->assertSame($countBefore, VpnAccount::count());
        $acc->refresh();
        $this->assertSame('active', $acc->status);
    }

    public function test_vpn_checkout_custom_device_limit_affects_price_clamps_bounds_and_provisions_ip_limit(): void
    {
        $capturedPayload = null;
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/check-username*' => Http::response([
                'success' => true,
                'message' => 'Available',
                'data' => ['available' => true, 'exists' => false],
            ], 200),
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => function ($request) use (&$capturedPayload) {
                $capturedPayload = $request->data();
                return Http::response([
                    'success' => true,
                    'message' => 'Created',
                    'data' => [
                        'protocol' => $capturedPayload['protocol'] ?? 'vmess',
                        'username' => $capturedPayload['username'] ?? 'devlimit_01',
                        'domain' => 'sg1.vpnpanel.test',
                        'uuid' => 'aaaa1111-2222-3333-4444-555566667777',
                        'sni_mode' => '3',
                        'status' => 'active',
                        'expired_at' => now()->addDays(30)->format('Y-m-d'),
                        'links' => ['tls' => 'vmess://ZGV2bGltaXRfMDE='],
                        'formatted_text' => 'VMESS Device Limit Account',
                    ],
                ], 201);
            },
        ]);

        // 1. Admin creates VPN product with base 2 devices, max 5 devices, +Rp 5.000 / extra device
        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'name' => 'VPN Custom Device SG',
                'price' => 15000,
                'description' => 'Bisa tambah limit perangkat',
                'is_vpn' => 1,
                'vpn_protocols' => ['vmess', 'vless'],
                'vpn_duration_days' => 30,
                'vpn_quota_gb' => 0,
                'vpn_ip_limit' => 2,
                'vpn_max_ip_limit' => 5,
                'vpn_extra_ip_price' => 5000,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $prod = Product::where('name', 'VPN Custom Device SG')->firstOrFail();
        $this->assertSame(2, (int) $prod->vpn_ip_limit);
        $this->assertSame(5, (int) $prod->vpn_max_ip_limit);
        $this->assertSame(5000, (int) $prod->vpn_extra_ip_price);

        // Verify model pricing helper directly (including clamping)
        $this->assertSame(15000, $prod->calculateVpnPrice(30, 1));  // clamped up to base 2
        $this->assertSame(15000, $prod->calculateVpnPrice(30, 2));  // base 2
        $this->assertSame(25000, $prod->calculateVpnPrice(30, 4));  // 15000 + 2*5000
        $this->assertSame(50000, $prod->calculateVpnPrice(60, 4));  // proportional 60d
        $this->assertSame(30000, $prod->calculateVpnPrice(30, 99)); // clamped down to max 5 (15000 + 3*5000)

        // 2. Catalog show exposes device limit selector & extra device price
        $this->actingAs($this->customer)
            ->get(route('catalog.show', $prod->id))
            ->assertStatus(200)
            ->assertSee('vpnSelIpLimit', false)
            ->assertSee('"extraIpPrice":5000', false)
            ->assertSee('"maxIpLimit":5', false);

        // 3. Review shows 4 IP (+2) and Rp 25.000 (30d) / Rp 50.000 (60d)
        $this->actingAs($this->customer)
            ->get(route('checkout.review', [
                'product' => $prod->id,
                'vpn_protocol' => 'vmess',
                'vpn_days' => 30,
                'vpn_ip_limit' => 4,
                'vpn_username' => 'devlimit_01',
            ]))
            ->assertStatus(200)
            ->assertSee('4 IP')
            ->assertSee('(+2)')
            ->assertSee('25.000')
            ->assertSee('name="vpn_ip_limit" value="4"', false);

        $this->actingAs($this->customer)
            ->get(route('checkout.review', [
                'product' => $prod->id,
                'vpn_protocol' => 'vmess',
                'vpn_days' => 60,
                'vpn_ip_limit' => 4,
                'vpn_username' => 'devlimit_01',
            ]))
            ->assertStatus(200)
            ->assertSee('50.000');

        // 4. Checkout store with 4 devices -> subtotal Rp 25.000 & ip_limit=4 forwarded to panel
        $this->actingAs($this->customer)
            ->post(route('checkout.store', $prod->id), [
                'quantity' => 1,
                'vpn_protocol' => 'vmess',
                'vpn_days' => 30,
                'vpn_ip_limit' => 4,
                'vpn_username' => 'devlimit_01',
                'vpn_sni_mode' => '3',
                'payment_method' => 'qris',
            ])
            ->assertRedirect();

        $order = Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertSame(25000, (int) $order->subtotal);
        $item = $order->items()->firstOrFail();
        $this->assertSame(4, (int) ($item->vpn_meta['ip_limit'] ?? 0));

        app(OrderService::class)->confirmPayment($order, 'qris_auto', $this->admin->id);
        $this->assertSame(4, (int) ($capturedPayload['ip_limit'] ?? 0));
        $this->assertDatabaseHas('vpn_accounts', [
            'user_id' => $this->customer->id,
            'username' => 'devlimit_01',
            'ip_limit' => 4,
            'purchase_price' => 25000,
            'purchase_days' => 30,
            'status' => 'active',
        ]);
    }

    public function test_vpn_expiry_highlight_dual_notifications_anti_spam_and_renew_reset(): void
    {
        config(['services.telegram.bot_token' => 'test_bot_token_123']);
        $this->customer->update(['telegram_id' => '6083760754']);

        $telegramCalls = [];
        Http::fake([
            'https://api.telegram.org/bot*' => function ($request) use (&$telegramCalls) {
                $telegramCalls[] = $request->data();
                return Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200);
            },
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/crit_exp01/renew' => Http::response([
                'success' => true,
                'message' => 'Renewed',
                'data' => ['expired_at' => now()->addDays(30)->format('Y-m-d')],
            ], 200),
        ]);

        $criticalAcc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'crit_exp01',
            'status' => 'active',
            'expired_at' => now()->addHours(12),
        ]);

        $warningAcc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vless',
            'username' => 'warn_exp02',
            'status' => 'active',
            'expired_at' => now()->addHours(48),
        ]);

        $normalAcc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'trojan',
            'username' => 'norm_ok03',
            'status' => 'active',
            'expired_at' => now()->addDays(15),
        ]);

        // 1. Highlight classes & filter `expiring_soon` on both Admin and Customer views
        $this->actingAs($this->admin)
            ->get(route('admin.vpn-accounts.index', ['status' => 'expiring_soon']))
            ->assertStatus(200)
            ->assertSee('crit_exp01')
            ->assertSee('warn_exp02')
            ->assertDontSee('norm_ok03')
            ->assertSee('bg-danger-subtle text-danger')
            ->assertSee('bg-warning-subtle text-warning-emphasis');

        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts', ['renew' => $criticalAcc->id]))
            ->assertStatus(200)
            ->assertSee('crit_exp01')
            ->assertSee('bg-danger-subtle text-danger')
            ->assertSee('const autoRenewId = ' . $criticalAcc->id, false);

        // 2. Run `vpn:notify-expiring` -> sends BOTH Telegram (with Renew Sekarang button) AND Website Notification Hub
        $this->artisan('vpn:notify-expiring')->assertExitCode(0);

        $this->assertCount(2, $telegramCalls);
        $tgMarkup0 = (string) ($telegramCalls[0]['reply_markup'] ?? '');
        $this->assertStringContainsString('Renew Sekarang', $tgMarkup0);
        $this->assertStringContainsString('/my-vpn?renew=', $tgMarkup0);

        $hubNotifs = $this->customer->notifications()->where('type', \App\Notifications\VpnExpiringNotification::class)->get();
        $this->assertCount(2, $hubNotifs);
        $this->assertStringContainsString('/my-vpn?renew=', (string) ($hubNotifs->first()->data['url'] ?? ''));

        $criticalAcc->refresh();
        $warningAcc->refresh();
        $this->assertSame('1d', $criticalAcc->expiry_notified_stage);
        $this->assertSame('3d', $warningAcc->expiry_notified_stage);

        // 3. Running `vpn:notify-expiring` again immediately MUST NOT spam duplicate alerts
        $this->artisan('vpn:notify-expiring')->assertExitCode(0);
        $this->assertCount(2, $telegramCalls);
        $this->assertSame(2, $this->customer->notifications()->where('type', \App\Notifications\VpnExpiringNotification::class)->count());

        // 4. Renewing critical account resets `expiry_notified_stage` to null
        app(\App\Services\VpnService::class)->renewAccount($criticalAcc, 30);
        $criticalAcc->refresh();
        $this->assertNull($criticalAcc->expiry_notified_stage);
    }

    public function test_admin_device_log_monitoring_and_strict_admin_access_control(): void
    {
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts/vmess/mon_dev01/devices*' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'protocol' => 'vmess',
                    'username' => 'mon_dev01',
                    'window_minutes' => 60,
                    'ip_limit' => 2,
                    'active_device_count' => 2,
                    'recent_device_count' => 3,
                    'current_strikes' => 2,
                    'devices' => [
                        ['ip' => '103.50.50.1', 'source' => 'xray', 'is_online' => true, 'hits' => 14, 'first_seen' => '2026-11-23 20:00:00', 'last_seen' => '2026-11-23 20:40:00'],
                    ],
                    'violations' => [
                        '2026-11-23 20:30:00 | vmess | mon_dev01 | ip=3/2 | strikes=2 | 103.50.50.1(4)',
                    ],
                ],
            ], 200),
        ]);

        $acc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'mon_dev01',
            'ip_limit' => 2,
            'status' => 'active',
            'expired_at' => now()->addDays(20),
        ]);

        // Admin can fetch device logs
        $this->actingAs($this->admin)
            ->getJson(route('admin.vpn-accounts.devices', ['id' => $acc->id, 'window_minutes' => 60]))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active_device_count', 2)
            ->assertJsonPath('data.current_strikes', 2)
            ->assertJsonPath('data.devices.0.ip', '103.50.50.1');

        // Customer MUST NOT access Admin device monitoring endpoint
        $custRes = $this->actingAs($this->customer)
            ->get(route('admin.vpn-accounts.devices', ['id' => $acc->id]));
        $this->assertTrue(in_array($custRes->status(), [302, 403], true));
    }

    public function test_persistent_anti_reset_traffic_sync_range_filter_quota_bar_and_customer_isolation(): void
    {
        $otherCustomer = User::create([
            'username' => 'other_vpn_cust',
            'full_name' => 'Other Customer',
            'email' => 'other_vpn_cust@example.test',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $custXrayAcc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'traf_iann',
            'quota_gb' => 10,
            'ip_limit' => 2,
            'status' => 'active',
            'expired_at' => now()->addDays(20),
        ]);

        $custSshAcc = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'ssh',
            'username' => 'traf_ssh01',
            'quota_gb' => 0,
            'ip_limit' => 2,
            'status' => 'active',
            'expired_at' => now()->addDays(20),
        ]);

        $otherAcc = VpnAccount::create([
            'user_id' => $otherCustomer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vless',
            'username' => 'traf_other',
            'quota_gb' => 0,
            'ip_limit' => 2,
            'status' => 'active',
            'expired_at' => now()->addDays(20),
        ]);

        $gb = 1073741824;

        // Snapshot 1: initial panel counters (Xray + SSH + other user)
        Http::fakeSequence('https://sg1.vpnpanel.test/api/v1/vpn/traffic*')
            ->push([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'xray_stats_ok' => true,
                    'ssh_stats_ok' => true,
                    'accounts' => [
                        'traf_iann' => ['uplink_bytes' => 1 * $gb, 'downlink_bytes' => 2 * $gb],
                        'traf_ssh01' => ['uplink_bytes' => 536870912, 'downlink_bytes' => 536870912], // 0.5 GB + 0.5 GB = 1 GB
                        'traf_other' => ['uplink_bytes' => 5 * $gb, 'downlink_bytes' => 5 * $gb],     // 10 GB total
                    ],
                ],
            ], 200)
            // Snapshot 2: SIMULATE VPS REBOOT / XRAY RESTART where panel counters reset and grow from 0!
            ->push([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'xray_stats_ok' => true,
                    'ssh_stats_ok' => true,
                    'accounts' => [
                        'traf_iann' => ['uplink_bytes' => 536870912, 'downlink_bytes' => 536870912], // +0.5 GB up, +0.5 GB down after reboot!
                        'traf_ssh01' => ['uplink_bytes' => 0, 'downlink_bytes' => 0],
                        'traf_other' => ['uplink_bytes' => 0, 'downlink_bytes' => 0],
                    ],
                ],
            ], 200);

        $this->artisan('vpn:sync-traffic')->assertExitCode(0);
        $this->artisan('vpn:sync-traffic')->assertExitCode(0);

        // Verify ANTI-RESET accumulation: traf_iann MUST be 1.5 GB up + 2.5 GB down = 4.0 GB total (NOT reset!)
        $custXrayAcc->refresh();
        $custSshAcc->refresh();
        $this->assertSame((int) (1.5 * $gb), (int) $custXrayAcc->uplink_bytes);
        $this->assertSame((int) (2.5 * $gb), (int) $custXrayAcc->downlink_bytes);
        $this->assertSame(536870912, (int) $custSshAcc->uplink_bytes);

        // Add historical traffic from 10 days ago to verify time-range filtering (today vs 30d)
        \App\Models\VpnTrafficLog::create([
            'vpn_account_id' => $custXrayAcc->id,
            'date' => now()->subDays(10)->toDateString(),
            'uplink_bytes' => 0,
            'downlink_bytes' => 2 * $gb,
        ]);

        $vpnService = app(\App\Services\VpnService::class);
        $todayCust = $vpnService->resolveTrafficForRange('today', $this->customer->id, [$custXrayAcc->id, $custSshAcc->id]);
        $monthCust = $vpnService->resolveTrafficForRange('30d', $this->customer->id, [$custXrayAcc->id, $custSshAcc->id]);
        $allAdmin = $vpnService->resolveTrafficForRange('all', null, [$custXrayAcc->id, $custSshAcc->id, $otherAcc->id]);

        // Customer isolation: todayCust = 4 GB (iann) + 1 GB (ssh) = 5 GB (EXCLUDES other_cust's 10 GB!)
        $this->assertSame(5 * $gb, $todayCust['total_bytes']);
        // 30d includes the 2 GB from 10 days ago -> 7 GB
        $this->assertSame(7 * $gb, $monthCust['total_bytes']);
        // Admin all-time includes all 3 accounts -> 4 + 1 + 10 = 15 GB
        $this->assertSame(15 * $gb, $allAdmin['total_bytes']);

        // Verify UI renders Quota Remaining Progress Bar (10 GB quota - 4 GB used = 6 GB / 60% left) on BOTH Admin & Customer views
        $this->actingAs($this->admin)
            ->get(route('admin.vpn-accounts.index'))
            ->assertStatus(200)
            ->assertSee('15.0 GB')
            ->assertSee('progress-bar')
            ->assertSee('6.00 GB')
            ->assertSee('adminVpnDeviceLogModal');

        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertSee('5.00 GB')       // Customer KPI sees ONLY own 5 GB, NOT admin's 15 GB!
            ->assertDontSee('15.0 GB')
            ->assertSee('progress-bar')  // Quota bar rendered for quota_gb=10
            ->assertSee('6.00 GB');
    }

    public function test_admin_can_transfer_vpn_account_ownership_and_new_owner_manages_it_in_my_vpn(): void
    {
        $newOwner = User::create([
            'username' => 'newowner_vpn',
            'full_name' => 'Pemilik Baru VPN',
            'email' => 'newowner@test.local',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-XFER-01',
            'customer_id' => $this->customer->id,
            'subtotal' => 15000,
            'discount_amount' => 0,
            'unique_code' => 11,
            'total_amount' => 15011,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $account = VpnAccount::create([
            'user_id' => $this->customer->id,
            'order_id' => $order->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'xfer_user01',
            'uuid' => 'aaaa1111-2222-3333-4444-555566667777',
            'config_link' => 'vmess://test-link-xfer',
            'config_payload' => ['links' => ['tls' => 'vmess://test-link-xfer'], 'domain' => 'sg1.vpnpanel.test'],
            'status' => 'active',
            'expired_at' => now()->addDays(20),
        ]);

        // 1. Non-admin cannot transfer ownership (admin middleware blocks with 302 redirect & owner stays unchanged)
        $this->actingAs($this->customer)
            ->post(route('admin.vpn-accounts.transfer-owner', $account->id), [
                'target_user_id' => $newOwner->id,
            ])
            ->assertRedirect();
        $account->refresh();
        $this->assertSame($this->customer->id, (int) $account->user_id);

        // 2. Admin transferring to the exact same owner returns error
        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.transfer-owner', $account->id), [
                'target_user_id' => $this->customer->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // 3. Admin transfers ownership to $newOwner with notification enabled
        $this->actingAs($this->admin)
            ->post(route('admin.vpn-accounts.transfer-owner', $account->id), [
                'target_user_id' => $newOwner->id,
                'notify_new_owner' => 1,
                'note' => 'Permintaan pindah akun',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();
        $order->refresh();
        $this->assertSame($newOwner->id, (int) $account->user_id);
        // Original order history stays with original buyer for accurate financial reporting
        $this->assertSame($this->customer->id, (int) $order->customer_id);

        // 4. Audit log & Notification Hub entry created for new owner
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_vpn_transfer_owner',
            'entity_type' => 'vpn_account',
            'entity_id' => $account->id,
        ]);
        $this->assertSame(1, $newOwner->notifications()->count());
        $notifData = $newOwner->notifications()->first()->data;
        $this->assertSame('vpn_owner_transferred', $notifData['type'] ?? null);

        // 5. New owner sees & manages the VPN account in /my-vpn; old owner loses access
        $this->actingAs($newOwner)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertSee('xfer_user01');

        $this->actingAs($newOwner)
            ->getJson(route('vpn.config', $account->id))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($this->customer)
            ->get(route('vpn.my-accounts'))
            ->assertStatus(200)
            ->assertDontSee('xfer_user01');

        $this->actingAs($this->customer)
            ->getJson(route('vpn.config', $account->id))
            ->assertStatus(404);

        // 6. Admin page renders transfer modal & button cleanly
        $this->actingAs($this->admin)
            ->get(route('admin.vpn-accounts.index'))
            ->assertStatus(200)
            ->assertSee('adminVpnTransferOwnerModal')
            ->assertSee('Ubah Pemilik');
    }

    public function test_vpn_products_are_hidden_and_blocked_in_stock_management(): void
    {
        $digitalProd = Product::create([
            'name' => 'Akun Digital Reguler',
            'price' => 25000,
            'is_suspended' => false,
            'is_vpn' => false,
        ]);

        $vpnProd = Product::create([
            'name' => 'Paket VPN Eksklusif SG',
            'price' => 15000,
            'is_suspended' => false,
            'is_vpn' => true,
            'vpn_protocol' => 'vmess',
            'vpn_duration_days' => 30,
        ]);

        // 1. Admin stock page hides VPN products from product dropdowns and shows regular digital product
        $this->actingAs($this->admin)
            ->get(route('admin.stock.index'))
            ->assertStatus(200)
            ->assertSee('Akun Digital Reguler')
            ->assertDontSee('Paket VPN Eksklusif SG');

        // 2. Backend rejects adding stock to a VPN product with a clear error message
        $this->actingAs($this->admin)
            ->post(route('admin.stock.store'), [
                'product_id' => $vpnProd->id,
                'stock_status' => 'ready',
                'raw_text' => 'user: test_should_block',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, \App\Models\StockUnit::where('product_id', $vpnProd->id)->count());

        // 3. Backend also rejects editing, moving, or bulk-moving existing stock onto a VPN product
        $unit = \App\Models\StockUnit::create([
            'product_id' => $digitalProd->id,
            'raw_text' => 'email: regular@example.com',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.stock.update', $unit->id), [
                'product_id' => $vpnProd->id,
                'stock_status' => 'ready',
                'raw_text' => 'email: regular@example.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->put(route('admin.stock.move', $unit->id), [
                'product_id' => $vpnProd->id,
                'stock_status' => 'ready',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->post(route('admin.stock.bulkMove'), [
                'ids' => json_encode([$unit->id]),
                'product_id' => $vpnProd->id,
                'stock_status' => 'ready',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $unit->refresh();
        $this->assertSame($digitalProd->id, (int) $unit->product_id);
    }
}
