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

        // 3. Catalog show -> Checkout review -> Checkout store -> OrderService forwards Zoom preset (vpn_sni_mode = 1)
        $this->actingAs($this->customer)
            ->get(route('catalog.show', $this->vpnProduct->id))
            ->assertStatus(200)
            ->assertSee('name="vpn_sni_mode"', false)
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
}
