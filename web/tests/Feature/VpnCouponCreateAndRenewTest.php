<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VpnCouponCreateAndRenewTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private VpnServer $server;
    private Product $vpnProduct;
    private Coupon $freeCoupon;
    private Coupon $partialCoupon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::forceCreate([
            'username' => 'cust_vpncoupon',
            'full_name' => 'Customer VPN Coupon',
            'email' => 'cust_vpncoupon@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'customer_wallet_balance' => 0,
            'is_suspended' => false,
        ]);

        $this->server = VpnServer::create([
            'name' => 'Oracle Batam ID',
            'code' => 'batam-01',
            'region' => 'ID',
            'driver' => VpnServer::DRIVER_VPNXRAY_API,
            'base_url' => 'https://dev.serverope.tech',
            'api_token' => 'vpnx_token_coupon_test',
            'hmac_secret' => 'hmac_secret_coupon_test_123456',
            'timeout_seconds' => 10,
            'verify_ssl' => true,
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->vpnProduct = Product::create([
            'name' => 'VPN ID BATAM',
            'price' => 5500,
            'description' => 'Multi-protocol VPN 30 Hari',
            'is_suspended' => false,
            'is_vpn' => true,
            'vpn_protocol' => 'vmess',
            'vpn_duration_days' => 30,
            'vpn_server_id' => $this->server->id,
            'vpn_quota_gb' => 0,
            'vpn_ip_limit' => 2,
            'vpn_sni_mode' => '3',
        ]);

        $this->freeCoupon = Coupon::create([
            'code' => 'VPNFREE100',
            'type' => 'percent',
            'value' => 100,
            'min_spend' => 0,
            'qty' => 50,
            'used_qty' => 0,
            'allow_multiple_uses' => true,
            'max_uses_per_user' => 5,
            'is_active' => true,
        ]);

        $this->partialCoupon = Coupon::create([
            'code' => 'HEMAT2000',
            'type' => 'fixed',
            'value' => 2000,
            'min_spend' => 3000,
            'qty' => 50,
            'used_qty' => 0,
            'allow_multiple_uses' => true,
            'max_uses_per_user' => 5,
            'is_active' => true,
        ]);
    }

    public function test_ajax_validate_coupon_works_for_create_and_renew_and_enforces_rules(): void
    {
        $account = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'iann_test',
            'status' => 'active',
            'purchase_price' => 5500,
            'purchase_days' => 30,
            'expired_at' => now()->addDays(10),
        ]);

        // 1. Valid 100% coupon for create
        $this->actingAs($this->customer)
            ->postJson(route('vpn.validate-coupon'), [
                'mode' => 'create',
                'coupon_code' => 'vpnfree100',
                'product_id' => $this->vpnProduct->id,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'code' => 'VPNFREE100',
                'subtotal' => 5500,
                'discount' => 5500,
                'final_price' => 0,
                'is_free' => true,
            ]);

        // 2. Partial coupon for renew (30 days = Rp 5.500 -> discount Rp 2.000 -> final Rp 3.500)
        $this->actingAs($this->customer)
            ->postJson(route('vpn.validate-coupon'), [
                'mode' => 'renew',
                'coupon_code' => 'HEMAT2000',
                'account_id' => $account->id,
                'days' => 30,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'code' => 'HEMAT2000',
                'subtotal' => 5500,
                'discount' => 2000,
                'final_price' => 3500,
                'is_free' => false,
            ]);

        // 3. Min spend check for renew (1 day = ~Rp 183 < min_spend Rp 3.000)
        $this->actingAs($this->customer)
            ->postJson(route('vpn.validate-coupon'), [
                'mode' => 'renew',
                'coupon_code' => 'HEMAT2000',
                'account_id' => $account->id,
                'days' => 1,
            ])
            ->assertOk()
            ->assertJson([
                'valid' => false,
                'discount' => 0,
            ]);

        // 4. Unknown coupon rejected
        $this->actingAs($this->customer)
            ->postJson(route('vpn.validate-coupon'), [
                'mode' => 'create',
                'coupon_code' => 'TIDAKADA',
                'product_id' => $this->vpnProduct->id,
            ])
            ->assertOk()
            ->assertJson(['valid' => false]);
    }

    public function test_create_vpn_with_100_percent_coupon_activates_instantly_with_zero_balance_and_preserves_base_purchase_price(): void
    {
        Http::fake([
            'https://dev.serverope.tech/api/v1/vpn/accounts/check*' => Http::response([
                'success' => true,
                'data' => ['available' => true, 'exists' => false],
            ], 200),
            'https://dev.serverope.tech/api/v1/vpn/accounts' => Http::response([
                'success' => true,
                'data' => [
                    'username' => 'free_vpn_01',
                    'protocol' => 'vmess',
                    'expired_at' => now()->addDays(30)->format('Y-m-d'),
                    'formatted_text' => '=== VMESS ===',
                ],
            ], 200),
        ]);

        // Customer has Rp 0 wallet balance and even submits payment_method=qris -> 100% coupon must bypass QRIS!
        $resp = $this->actingAs($this->customer)->post(route('vpn.store'), [
            'product_id' => $this->vpnProduct->id,
            'protocol' => 'vmess',
            'username' => 'free_vpn_01',
            'payment_method' => 'qris',
            'coupon_code' => 'VPNFREE100',
        ]);

        $resp->assertRedirect(route('vpn.my-accounts'));
        $resp->assertSessionHas('success');

        $order = Order::where('customer_id', $this->customer->id)->firstOrFail();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('VPNFREE100', $order->coupon_code);
        $this->assertSame(5500, (int) $order->subtotal);
        $this->assertSame(5500, (int) $order->discount_amount);
        $this->assertSame(0, (int) $order->unique_code);
        $this->assertSame(0, (int) $order->total_amount);

        $account = VpnAccount::where('username', 'free_vpn_01')->firstOrFail();
        $this->assertSame('active', $account->status);
        // Critical: base purchase_price preserved for future proportional renewals!
        $this->assertSame(5500, (int) $account->purchase_price);

        $this->freeCoupon->refresh();
        $this->assertSame(1, (int) $this->freeCoupon->used_qty);
        $this->assertSame(1, DB::table('coupon_user')->where('coupon_id', $this->freeCoupon->id)->where('user_id', $this->customer->id)->count());
    }

    public function test_create_vpn_with_partial_coupon_applies_discount_to_wallet_and_qris(): void
    {
        $this->customer->forceFill(['customer_wallet_balance' => 4000])->save();

        Http::fake([
            'https://dev.serverope.tech/api/v1/vpn/accounts/check*' => Http::response([
                'success' => true,
                'data' => ['available' => true, 'exists' => false],
            ], 200),
            'https://dev.serverope.tech/api/v1/vpn/accounts' => Http::response([
                'success' => true,
                'data' => [
                    'username' => 'disc_wallet01',
                    'protocol' => 'vmess',
                    'expired_at' => now()->addDays(30)->format('Y-m-d'),
                    'formatted_text' => '=== VMESS ===',
                ],
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        // 1. Wallet: Rp 5.500 - Rp 2.000 = Rp 3.500 (succeeds with Rp 4.000 balance -> remaining Rp 500)
        $this->actingAs($this->customer)->post(route('vpn.store'), [
            'product_id' => $this->vpnProduct->id,
            'protocol' => 'vmess',
            'username' => 'disc_wallet01',
            'payment_method' => 'wallet',
            'coupon_code' => 'HEMAT2000',
        ])->assertRedirect(route('vpn.my-accounts'))->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertSame(500, (int) $this->customer->customer_wallet_balance);

        // 2. QRIS: Rp 5.500 - Rp 2.000 = Rp 3.500 + unique_code (1..200)
        $this->actingAs($this->customer)->post(route('vpn.store'), [
            'product_id' => $this->vpnProduct->id,
            'protocol' => 'vmess',
            'username' => 'disc_qris01',
            'payment_method' => 'qris',
            'coupon_code' => 'HEMAT2000',
        ])->assertRedirect();

        $qrisOrder = Order::where('customer_id', $this->customer->id)->where('status', 'pending_payment')->firstOrFail();
        $this->assertSame('HEMAT2000', $qrisOrder->coupon_code);
        $this->assertSame(5500, (int) $qrisOrder->subtotal);
        $this->assertSame(2000, (int) $qrisOrder->discount_amount);
        $this->assertSame(3500 + (int) $qrisOrder->unique_code, (int) $qrisOrder->total_amount);
    }

    public function test_renew_vpn_with_100_percent_coupon_and_partial_coupon_and_rollback_on_vps_failure(): void
    {
        $account = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vmess',
            'username' => 'iann_renew01',
            'status' => 'active',
            'purchase_price' => 5500,
            'purchase_days' => 30,
            'expired_at' => now()->addDays(10),
        ]);

        Http::fake([
            'https://dev.serverope.tech/api/v1/vpn/accounts/vmess/iann_renew01/renew' => Http::sequence()
                ->push([
                    'success' => true,
                    'data' => [
                        'username' => 'iann_renew01',
                        'expired_at' => now()->addDays(40)->format('Y-m-d'),
                    ],
                ], 200)
                ->push([
                    'success' => false,
                    'message' => 'Simulated VPS error',
                ], 500),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        // 1. Renew with 100% free coupon (Rp 0 balance, even with payment_method=qris -> instant renew!)
        $resp = $this->actingAs($this->customer)->post(route('vpn.renew', $account->id), [
            'days' => 30,
            'payment_method' => 'qris',
            'coupon_code' => 'VPNFREE100',
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        $this->freeCoupon->refresh();
        $this->assertSame(1, (int) $this->freeCoupon->used_qty);
        $this->assertSame(0, Order::where('status', 'pending_payment')->count());

        // 2. Renew with partial coupon via wallet where VPS fails -> both wallet & coupon MUST rollback!
        $this->customer->forceFill(['customer_wallet_balance' => 5000])->save();
        $failResp = $this->actingAs($this->customer)->post(route('vpn.renew', $account->id), [
            'days' => 30,
            'payment_method' => 'wallet',
            'coupon_code' => 'HEMAT2000',
        ]);
        $failResp->assertRedirect();
        $failResp->assertSessionHas('error');

        $this->customer->refresh();
        $this->partialCoupon->refresh();
        $this->assertSame(5000, (int) $this->customer->customer_wallet_balance, 'Wallet must be refunded on VPS renew failure.');
        $this->assertSame(0, (int) $this->partialCoupon->used_qty, 'Coupon used_qty must be rolled back on VPS renew failure.');
        $this->assertSame(0, DB::table('coupon_user')->where('coupon_id', $this->partialCoupon->id)->where('user_id', $this->customer->id)->count());

        // 3. Renew with partial coupon via QRIS creates discounted pending order
        $qrisResp = $this->actingAs($this->customer)->post(route('vpn.renew', $account->id), [
            'days' => 30,
            'payment_method' => 'qris',
            'coupon_code' => 'HEMAT2000',
        ]);
        $qrisResp->assertRedirect();
        $qrisOrder = Order::where('customer_id', $this->customer->id)->where('status', 'pending_payment')->firstOrFail();
        $this->assertSame('HEMAT2000', $qrisOrder->coupon_code);
        $this->assertSame(5500, (int) $qrisOrder->subtotal);
        $this->assertSame(2000, (int) $qrisOrder->discount_amount);
        $this->assertSame(3500 + (int) $qrisOrder->unique_code, (int) $qrisOrder->total_amount);
    }
}
