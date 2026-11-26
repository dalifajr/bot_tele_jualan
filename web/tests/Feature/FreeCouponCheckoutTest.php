<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreeCouponCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected Product $standardProduct;
    protected Product $vpnProduct;
    protected Coupon $freeCoupon;
    protected VpnServer $vpnServer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::forceCreate([
            'full_name' => 'Free Buyer',
            'username' => 'free_buyer',
            'email' => 'free_buyer@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'customer_wallet_balance' => 0,
        ]);

        $this->standardProduct = Product::create([
            'name' => 'Akun Netflix 1 Bulan',
            'price' => 15000,
            'description' => 'Netflix Premium',
            'is_suspended' => false,
            'is_vpn' => false,
        ]);

        StockUnit::create([
            'product_id' => $this->standardProduct->id,
            'raw_text' => 'netflix_user:netflix_pass',
            'credential_payload' => 'netflix_user:netflix_pass',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);

        $this->vpnServer = VpnServer::create([
            'name' => 'Singapore SG-01',
            'code' => 'sg-01',
            'region' => 'SG',
            'driver' => VpnServer::DRIVER_VPNXRAY_API,
            'base_url' => 'https://sg1.vpnpanel.test',
            'api_token' => 'vpnx_token_123',
            'hmac_secret' => 'hmac_secret_456',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->vpnProduct = Product::create([
            'name' => 'VPN ID BATAM 30 Hari',
            'price' => 5500,
            'description' => 'VPN Trojan High Speed',
            'is_suspended' => false,
            'is_vpn' => true,
            'vpn_protocol' => 'trojan',
            'vpn_duration_days' => 30,
            'vpn_server_id' => $this->vpnServer->id,
            'vpn_ip_limit' => 2,
        ]);

        $this->freeCoupon = Coupon::create([
            'code' => 'FREE100',
            'type' => 'percent',
            'value' => 100,
            'min_spend' => 0,
            'qty' => 100,
            'used_qty' => 0,
            'is_active' => true,
        ]);
    }

    public function test_checkout_review_with_100_percent_coupon_sets_zero_unique_code_and_zero_total(): void
    {
        $response = $this->actingAs($this->customer)->get(route('checkout.review', [
            'product' => $this->standardProduct->id,
            'quantity' => 1,
            'coupon_code' => 'FREE100',
        ]));

        $response->assertOk();
        $response->assertViewHas('uniqueCode', 0);
        $response->assertViewHas('totalAmount', 0);
        $response->assertViewHas('discount', 15000);
        $response->assertSee('Pesanan Bebas Biaya (Diskon 100%)');
        $response->assertSee('Klaim &amp; Proses Sekarang (Gratis)', false);
    }

    public function test_checkout_with_100_percent_coupon_immediately_completes_order_without_unique_code(): void
    {
        $response = $this->actingAs($this->customer)->post(route('checkout.store', $this->standardProduct->id), [
            'quantity' => 1,
            'coupon_code' => 'FREE100',
        ]);

        $order = Order::where('customer_id', $this->customer->id)->firstOrFail();

        $response->assertRedirect(route('orders.show', $order->id));
        $response->assertSessionHas('success');

        $this->assertSame(0, (int) $order->unique_code);
        $this->assertSame(0, (int) $order->total_amount);
        $this->assertSame(15000, (int) $order->discount_amount);
        $this->assertSame('delivered', $order->status);

        $payment = $order->payment;
        $this->assertNotNull($payment);
        $this->assertSame(0, (int) $payment->expected_amount);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('FREE_COUPON', $payment->source_app);

        // Stock is assigned and marked sold
        $stockUnit = StockUnit::where('product_id', $this->standardProduct->id)->first();
        $this->assertTrue((bool) $stockUnit->is_sold);
        $this->assertSame($order->id, $stockUnit->sold_order_id);
    }

    public function test_vpn_product_checkout_with_100_percent_coupon_provisions_and_delivers_immediately(): void
    {
        Http::fake([
            'https://sg1.vpnpanel.test/api/v1/vpn/accounts' => Http::response([
                'success' => true,
                'data' => [
                    'username' => 'vpn_free_user',
                    'protocol' => 'trojan',
                    'expired_at' => now()->addDays(30)->toDateTimeString(),
                    'config_link' => 'trojan://uuid@dev.serverope.tech:443?security=tls#vpn_free_user',
                ],
            ], 200),
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->actingAs($this->customer)->post(route('checkout.store', $this->vpnProduct->id), [
            'quantity' => 1,
            'coupon_code' => 'FREE100',
            'vpn_username' => 'vpn_free_user',
            'vpn_password' => 'free_pass123',
        ]);

        $order = Order::where('customer_id', $this->customer->id)->firstOrFail();

        $response->assertRedirect(route('orders.show', $order->id));
        $this->assertSame(0, (int) $order->unique_code);
        $this->assertSame(0, (int) $order->total_amount);
        $this->assertSame(5500, (int) $order->discount_amount);
        $this->assertSame('delivered', $order->status);

        $vpnAccount = VpnAccount::where('order_id', $order->id)->first();
        $this->assertNotNull($vpnAccount);
        $this->assertSame('vpn_free_user', $vpnAccount->username);
        $this->assertSame('active', $vpnAccount->status);
    }

    public function test_cart_checkout_with_100_percent_coupon_delivers_immediately_with_zero_unique_code(): void
    {
        CartItem::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 1,
        ]);

        $reviewResp = $this->actingAs($this->customer)->get(route('cart.checkout', [
            'coupon_code' => 'FREE100',
        ]));
        $reviewResp->assertOk();
        $reviewResp->assertViewHas('uniqueCode', 0);
        $reviewResp->assertViewHas('total', 0);

        $postResp = $this->actingAs($this->customer)->post(route('cart.process'), [
            'coupon_code' => 'FREE100',
        ]);

        $order = Order::where('customer_id', $this->customer->id)->firstOrFail();
        $postResp->assertRedirect(route('orders.show', $order->id));

        $this->assertSame(0, (int) $order->unique_code);
        $this->assertSame(0, (int) $order->total_amount);
        $this->assertSame('delivered', $order->status);
    }
}
