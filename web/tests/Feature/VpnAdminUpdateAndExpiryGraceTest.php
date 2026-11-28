<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VpnAdminUpdateAndExpiryGraceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private VpnServer $server;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'test-bot-token-99999',
            'app.url' => 'https://belajaridn.id',
        ]);

        $this->admin = User::forceCreate([
            'username' => 'admin_vpnedit',
            'full_name' => 'Admin VPN',
            'email' => 'admin_vpnedit@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'telegram_id' => '111222333',
            'is_suspended' => false,
        ]);

        $this->customer = User::forceCreate([
            'username' => 'cust_vpnedit',
            'full_name' => 'Siti Pelanggan',
            'email' => 'cust_vpnedit@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'telegram_id' => '444555666',
            'customer_wallet_balance' => 100000,
            'is_suspended' => false,
        ]);

        $this->server = VpnServer::create([
            'name' => 'SG Node 01',
            'code' => 'sg-01',
            'region' => 'SG',
            'driver' => VpnServer::DRIVER_VPNXRAY_API,
            'base_url' => 'https://dev.serverope.tech',
            'api_token' => 'vpnx_token_test_123',
            'hmac_secret' => 'hmac_secret_test_1234567890',
            'timeout_seconds' => 10,
            'verify_ssl' => true,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function test_admin_can_update_vpn_info_without_changing_expiry_and_with_reactivate(): void
    {
        $account = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vless',
            'username' => 'siti_vless01',
            'status' => 'suspended',
            'suspended_by_role' => 'system',
            'suspended_reason' => 'Kadaluarsa — Masa tenggang hingga 01 Des 2026',
            'suspended_at' => now()->subDay(),
            'quota_gb' => 10,
            'ip_limit' => 1,
            'purchase_price' => 15000,
            'purchase_days' => 30,
            'expired_at' => now()->addDays(10),
        ]);

        $targetExp = now()->addDays(25)->format('Y-m-d');

        Http::fake([
            'https://dev.serverope.tech/api/v1/vpn/accounts/vless/siti_vless01/update' => Http::response([
                'success' => true,
                'message' => 'VPN account info updated successfully.',
                'data' => [
                    'protocol' => 'vless',
                    'username' => 'siti_vless01',
                    'status' => 'active',
                    'expired_at' => $targetExp,
                    'quota_gb' => 50,
                    'ip_limit' => 3,
                ],
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $res = $this->actingAs($this->admin)->post(route('admin.vpn-accounts.update-info', $account->id), [
            'expiry_mode' => 'set_date',
            'expired_at' => $targetExp,
            'quota_gb' => 50,
            'ip_limit' => 3,
            'purchase_price' => 25000,
            'reactivate' => '1',
        ]);

        $res->assertSessionHas('success');
        $account->refresh();

        $this->assertSame('active', $account->status);
        $this->assertNull($account->suspended_by_role);
        $this->assertNull($account->suspended_reason);
        $this->assertSame(50, $account->quota_gb);
        $this->assertSame(3, $account->ip_limit);
        $this->assertSame(25000, $account->purchase_price);
        $this->assertStringStartsWith($targetExp, (string) $account->getRawOriginal('expired_at'));
    }

    public function test_admin_vpn_page_renders_ubah_info_button_and_render_expect_link(): void
    {
        VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'siti_vmess01',
            'status' => 'active',
            'quota_gb' => 0,
            'ip_limit' => 2,
            'expired_at' => now()->addDays(14),
        ]);

        Http::fake([
            'https://dev.serverope.tech/*' => Http::response(['success' => true, 'data' => ['accounts' => []]], 200),
        ]);

        $res = $this->actingAs($this->admin)->get(route('admin.vpn-accounts.index'));
        $res->assertOk();
        $res->assertSee('Ubah Info');
        $res->assertSee('<link rel="expect" href="#mobileBottomWrapper" blocking="render">', false);
    }

    public function test_expired_account_enters_3_day_grace_suspend_blocks_self_unsuspend_but_allows_customer_renew(): void
    {
        $account = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'trojan',
            'username' => 'siti_tr01',
            'status' => 'active',
            'quota_gb' => 0,
            'ip_limit' => 2,
            'purchase_price' => 15000,
            'purchase_days' => 30,
            'expired_at' => now()->subDay(),
        ]);

        $renewedExp = now()->addDays(30)->format('Y-m-d');

        Http::fake([
            'https://dev.serverope.tech/api/v1/vpn/accounts/trojan/siti_tr01/suspend' => Http::response([
                'success' => true,
                'data' => ['status' => 'suspended'],
            ], 200),
            'https://dev.serverope.tech/api/v1/vpn/accounts/trojan/siti_tr01/renew' => Http::response([
                'success' => true,
                'data' => [
                    'status' => 'active',
                    'expired_at' => $renewedExp,
                    'quota_gb' => 0,
                    'ip_limit' => 2,
                ],
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        // Phase 1: Enforce expiry grace -> auto-suspend
        $this->artisan('vpn:enforce-expiry-grace')->assertSuccessful();

        $account->refresh();
        $this->assertSame('suspended', $account->status);
        $this->assertSame('system', $account->suspended_by_role);
        $this->assertTrue($account->isExpiredGraceSuspended());
        $this->assertTrue($account->canCustomerRenew());

        // Customer CANNOT self-unsuspend
        $unsuspRes = $this->actingAs($this->customer)->post(route('vpn.unsuspend', $account->id));
        $unsuspRes->assertSessionHas('error');
        $this->assertSame('suspended', $account->fresh()->status);

        // Customer CAN renew (Option B) -> reactivates account & deducts wallet
        $renewRes = $this->actingAs($this->customer)->post(route('vpn.renew', $account->id), [
            'days' => 30,
            'payment_method' => 'wallet',
        ]);
        $renewRes->assertSessionHas('success');

        $account->refresh();
        $this->assertSame('active', $account->status);
        $this->assertNull($account->suspended_by_role);
        $this->assertStringStartsWith($renewedExp, (string) $account->getRawOriginal('expired_at'));
    }

    public function test_account_past_3_day_grace_is_permanently_deleted_and_order_history_shows_deleted_notice(): void
    {
        $product = Product::create([
            'name' => 'VPN VLESS SG 30 Hari',
            'price' => 15000,
            'description' => 'Paket VPN VLESS',
            'is_suspended' => false,
            'creator_id' => $this->admin->id,
            'is_vpn' => true,
            'vpn_protocol' => 'vless',
            'vpn_server_id' => $this->server->id,
            'vpn_duration_days' => 30,
            'vpn_quota_gb' => 0,
            'vpn_ip_limit' => 2,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-GRACE-DEL-01',
            'customer_id' => $this->customer->id,
            'subtotal' => 15000,
            'discount_amount' => 0,
            'unique_code' => 0,
            'total_amount' => 15000,
            'status' => 'delivered',
            'paid_at' => now()->subDays(35),
            'delivered_at' => now()->subDays(35),
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 15000,
            'vpn_username' => 'siti_old01',
            'vpn_meta' => [
                'protocol' => 'vless',
                'days' => 30,
            ],
        ]);

        $account = VpnAccount::create([
            'user_id' => $this->customer->id,
            'order_id' => $order->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vless',
            'username' => 'siti_old01',
            'status' => 'suspended',
            'suspended_by_role' => 'system',
            'suspended_reason' => 'Kadaluarsa — Masa tenggang',
            'suspended_at' => now()->subDays(4),
            'quota_gb' => 0,
            'ip_limit' => 2,
            'expired_at' => now()->subDays(4),
        ]);

        Http::fake([
            'https://dev.serverope.tech/api/v1/vpn/accounts/vless/siti_old01' => Http::response([
                'success' => true,
                'data' => ['deleted' => true],
            ], 200),
        ]);

        // Phase 2: Past 3-day grace -> permanently deleted
        $this->artisan('vpn:enforce-expiry-grace')->assertSuccessful();

        $this->assertDatabaseMissing('vpn_accounts', ['id' => $account->id]);
        $orderItem->refresh();
        $this->assertTrue((bool) ($orderItem->vpn_meta['deleted_due_to_expiry'] ?? false));

        // Customer opens Order History detail page -> sees expired & deleted notice!
        $orderRes = $this->actingAs($this->customer)->get(route('orders.show', $order->id));
        $orderRes->assertOk();
        $orderRes->assertSee('Akun VPN telah kadaluarsa dan sudah dihapus dari sistem.');
        $orderRes->assertSee('siti_old01');
    }
}
