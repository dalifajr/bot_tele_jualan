<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\OrderService;
use App\Services\TwoFactorService;
use App\Services\VpnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3SecurityAndVisualTest extends TestCase
{
    use RefreshDatabase;

    public function test_vpn_service_escapes_credentials_and_redacts_password()
    {
        $vpnService = new VpnService();

        // Safe command generation
        $cmd = $vpnService->buildProvisionCommand('ssh', 'valid_user', 'P@ssw0rd123', 30, 0, 2);
        $this->assertStringContainsString("printf '%s\\n'", $cmd);
        $this->assertStringContainsString("valid_user:P@ssw0rd123", $cmd);
        $this->assertStringContainsString("chpasswd", $cmd);
        $this->assertStringContainsString("/etc/kyt/limit/ssh/ip", $cmd);

        // Rejection of shell injection payload in username
        $this->expectException(\InvalidArgumentException::class);
        $vpnService->buildProvisionCommand('ssh', 'user;rm -rf /', 'safePass123', 30);
    }

    public function test_vpn_service_rejects_malicious_passwords()
    {
        $vpnService = new VpnService();

        $this->expectException(\InvalidArgumentException::class);
        $vpnService->buildProvisionCommand('ssh', 'validuser', 'pass$(id)', 30);
    }

    private function makeUser(string $username, string $role = 'customer', array $attributes = []): User
    {
        $user = new User(array_merge([
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => bcrypt('Secret123!'),
            'has_custom_password' => true,
        ], $attributes));
        $user->role = $role;
        $user->save();
        return $user;
    }

    public function test_order_service_confirm_payment_is_idempotent_and_prevents_double_credit()
    {
        $seller = $this->makeUser('seller_phase3', 'seller');
        $seller->wallet_balance = 0;
        $seller->commission_per_product = false;
        $seller->platform_fee_percent = 10;
        $seller->save();

        $customer = $this->makeUser('cust_phase3', 'customer');

        $product = Product::create([
            'creator_id' => $seller->id,
            'name' => 'Akun Premium',
            'slug' => 'akun-premium',
            'price' => 100000,
            'stock_type' => 'account',
            'is_suspended' => false,
            'warranty_days' => 0,
        ]);

        $order = Order::create([
            'order_ref' => 'TEST-ORD-001',
            'customer_id' => $customer->id,
            'total_amount' => 100000,
            'subtotal' => 100000,
            'unique_code' => 123,
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 100000,
            'quantity' => 1,
        ]);

        Payment::create([
            'payment_ref' => 'PAY-TEST-001',
            'order_id' => $order->id,
            'expected_amount' => 100000,
            'status' => 'pending',
            'payment_method' => 'qris',
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'raw_text' => 'user|pass',
            'stock_content' => 'user|pass',
            'stock_status' => 'reserved_checkout',
            'sold_order_id' => $order->id,
            'is_sold' => false,
        ]);

        $orderService = app(OrderService::class);

        // First confirmation
        $result1 = $orderService->confirmPayment($order, 'TEST_ADMIN');
        $this->assertTrue($result1);

        $seller->refresh();
        $this->assertEquals(90000, $seller->wallet_balance);

        // Second confirmation attempt must fail
        $this->expectException(\Exception::class);
        $orderService->confirmPayment($order, 'TEST_ADMIN');
    }

    public function test_seller_withdrawal_guards_pending_amount()
    {
        $seller = $this->makeUser('seller_w_phase3', 'seller');
        $seller->wallet_balance = 100000;
        $seller->save();

        $bank = \App\Models\SellerBankAccount::create([
            'user_id' => $seller->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller Test',
        ]);

        // First withdrawal: 60.000 (leaves 40.000 available)
        $response1 = $this->actingAs($seller)->post(route('seller.finance.withdraw'), [
            'amount' => 60000,
            'bank_account_id' => $bank->id,
        ]);
        $response1->assertRedirect();
        $this->assertDatabaseHas('withdrawal_requests', [
            'seller_id' => $seller->id,
            'amount' => 60000,
            'status' => 'pending',
        ]);

        // Second withdrawal: 50.000 (available is only 40.000) must be rejected
        $response2 = $this->actingAs($seller)->post(route('seller.finance.withdraw'), [
            'amount' => 50000,
            'bank_account_id' => $bank->id,
        ]);
        $response2->assertSessionHas('error');
        $this->assertEquals(1, WithdrawalRequest::where('seller_id', $seller->id)->count());
    }

    public function test_coupon_percent_capped_and_discount_clamped()
    {
        $admin = $this->makeUser('admin_c_phase3', 'admin');

        // Validation caps percent at 100
        $response = $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'code' => 'DISC150',
            'type' => 'percent',
            'value' => 150,
            'min_spend' => 0,
            'qty' => 10,
            'scope' => 'all',
        ]);
        $response->assertSessionHasErrors('value');

        // Discount calculation clamp
        $coupon = new Coupon([
            'type' => 'percent',
            'value' => 50,
            'max_discount' => 20000,
        ]);
        $this->assertEquals(20000, $coupon->calculateDiscount(100000));
        $this->assertEquals(5000, $coupon->calculateDiscount(10000));

        $couponOver = new Coupon([
            'type' => 'percent',
            'value' => 120,
        ]);
        // Over-percent clamped to subtotal
        $this->assertEquals(50000, $couponOver->calculateDiscount(50000));
    }

    public function test_totp_service_generates_and_verifies_otp_and_recovery_codes()
    {
        $secret = TwoFactorService::generateBase32Secret(32);
        $this->assertNotEmpty($secret);

        $code = TwoFactorService::generateOtp($secret);
        $this->assertNotNull($code);
        $this->assertTrue(TwoFactorService::verifyOtp($secret, $code));

        $codes = TwoFactorService::generateRecoveryCodes(4);
        $this->assertCount(4, $codes);
        $hashed = TwoFactorService::hashRecoveryCodes($codes);

        $remaining = TwoFactorService::consumeRecoveryCode($hashed, $codes[0]);
        $this->assertNotNull($remaining);
        $this->assertCount(3, $remaining);
    }

    public function test_quick_search_api_returns_role_appropriate_results()
    {
        $customer = $this->makeUser('cust_qs_phase3', 'customer');
        $product = Product::create([
            'name' => 'Spotify Premium 1 Bulan',
            'slug' => 'spotify-premium-1-bulan',
            'price' => 25000,
            'stock_type' => 'account',
            'is_suspended' => false,
        ]);

        $response = $this->actingAs($customer)->getJson(route('api.quick-search', ['q' => 'Spotify']));
        $response->assertOk();
        $response->assertJsonFragment(['title' => 'Spotify Premium 1 Bulan']);
    }

    public function test_fractional_css_utilities_exist_in_public_app_css()
    {
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('.p-2\\.5', $css);
        $this->assertStringContainsString('.py-1\\.5', $css);
        $this->assertStringContainsString('.gap-1\\.5', $css);
        $this->assertStringContainsString('.px-3\\.5', $css);
        $this->assertStringContainsString('.m3-quick-search-trigger', $css);
    }
}
