<?php

namespace Tests\Feature;

use App\Models\ComplaintCase;
use App\Models\FirewallRule;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductWishlist;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Phase4NewMenusAndFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $username, string $role = 'customer', array $extra = []): User
    {
        $user = new User([
            'username' => $username,
            'full_name' => ucfirst($username),
            'email' => "{$username}@example.com",
            'password' => bcrypt('Secret123!'),
            'has_custom_password' => true,
        ]);
        $user->role = $role;
        foreach ($extra as $k => $v) {
            $user->{$k} = $v;
        }
        $user->save();
        return $user;
    }

    public function test_wallet_topup_confirmation_and_instant_one_click_checkout(): void
    {
        $admin = $this->makeUser('admin_w4', 'admin');
        $customer = $this->makeUser('cust_w4', 'customer', ['customer_wallet_balance' => 0]);

        // 1. Customer requests top-up
        $resTopup = $this->actingAs($customer)->post(route('wallet.topup.store'), [
            'amount' => 100000,
        ]);
        $resTopup->assertRedirect(route('wallet.index'));

        $topup = WalletTopup::where('user_id', $customer->id)->firstOrFail();
        $this->assertEquals('pending', $topup->status);
        $this->assertEquals(100000, $topup->amount);

        // 2. Admin confirms top-up
        $resConfirm = $this->actingAs($admin)->post(route('admin.wallet.topup.confirm', $topup->id));
        $resConfirm->assertSessionHas('success');

        $customer->refresh();
        $this->assertEquals(100000, (int) $customer->customer_wallet_balance);

        // 3. Double-confirmation must be blocked (idempotent)
        $resConfirmAgain = $this->actingAs($admin)->post(route('admin.wallet.topup.confirm', $topup->id));
        $resConfirmAgain->assertSessionHas('error');
        $customer->refresh();
        $this->assertEquals(100000, (int) $customer->customer_wallet_balance);

        // 4. Instant 1-Click Checkout via Wallet
        $product = Product::create([
            'name' => 'Figma Pro 1 Tahun',
            'slug' => 'figma-pro-1-tahun',
            'price' => 40000,
            'stock_type' => 'account',
            'is_suspended' => false,
            'warranty_days' => 30,
        ]);
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'figma@example.com|pass123',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);

        $resBuy = $this->actingAs($customer)->post(route('checkout.store', $product->id), [
            'quantity' => 1,
            'payment_method' => 'wallet',
        ]);
        $resBuy->assertSessionHas('success');

        $customer->refresh();
        $this->assertEquals(60000, (int) $customer->customer_wallet_balance);

        $order = Order::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals('delivered', $order->status);
        $this->assertEquals(0, (int) $order->unique_code);
        $this->assertEquals(40000, (int) $order->total_amount);
    }

    public function test_wishlist_and_restock_alert_notifies_customer_on_admin_stock_upload(): void
    {
        $admin = $this->makeUser('admin_wl4', 'admin');
        $customer = $this->makeUser('cust_wl4', 'customer');

        $product = Product::create([
            'name' => 'GitHub Student Pack',
            'slug' => 'github-student-pack',
            'price' => 50000,
            'stock_type' => 'account',
            'is_suspended' => false,
        ]);

        // Customer enables restock alert
        $resAlert = $this->actingAs($customer)->post(route('wishlist.restock-alert', $product->id));
        $resAlert->assertSessionHas('success');

        $wish = ProductWishlist::where('user_id', $customer->id)->where('product_id', $product->id)->firstOrFail();
        $this->assertTrue((bool) $wish->notify_restock);

        // Admin uploads ready stock
        $resUpload = $this->actingAs($admin)->post(route('admin.stock.store'), [
            'product_id' => $product->id,
            'stock_status' => 'ready',
            'raw_text' => 'gh_user1|gh_pass1',
        ]);
        $resUpload->assertRedirect(route('admin.stock.index'));

        // Customer received in-app restock notification and notify_restock flag reset
        $wish->refresh();
        $this->assertFalse((bool) $wish->notify_restock);
        $this->assertNotNull($wish->last_notified_at);
        $this->assertEquals(1, $customer->notifications()->where('data->type', 'restock_alert')->count());

        // Wishlist page renders cleanly
        $this->actingAs($customer)->get(route('wishlist.index'))
            ->assertOk()
            ->assertSee('GitHub Student Pack');
    }

    public function test_warranty_center_and_auto_refund_complaint_to_wallet(): void
    {
        $admin = $this->makeUser('admin_cmp4', 'admin');
        $customer = $this->makeUser('cust_cmp4', 'customer', ['customer_wallet_balance' => 0]);

        $product = Product::create([
            'name' => 'Canva Pro Garansi',
            'slug' => 'canva-pro-garansi',
            'price' => 35000,
            'stock_type' => 'account',
            'is_suspended' => false,
            'warranty_days' => 14,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-WRN-001',
            'customer_id' => $customer->id,
            'subtotal' => 35000,
            'discount_amount' => 0,
            'unique_code' => 120,
            'total_amount' => 35120,
            'status' => 'delivered',
            'delivered_at' => now()->subDays(2),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 35000,
        ]);

        // Warranty center displays active warranty
        $this->actingAs($customer)->get(route('warranties.index'))
            ->assertOk()
            ->assertSee('Canva Pro Garansi')
            ->assertSee('ORD-WRN-001');

        // Auto-refund complaint to customer wallet
        $complaint = ComplaintCase::create([
            'complaint_ref' => 'CMP-WAL-001',
            'order_id' => $order->id,
            'order_ref_snapshot' => $order->order_ref,
            'order_created_at_snapshot' => $order->created_at ?? now(),
            'customer_id' => $customer->id,
            'customer_telegram_id' => 999888777,
            'customer_username_snapshot' => $customer->username,
            'complaint_text' => 'Akun tidak bisa login',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resRefund = $this->actingAs($admin)->post(route('admin.complaints.updateStatus', $complaint->id), [
            'status' => 'refund_wallet',
            'refund_note' => 'Refund otomatis diterima',
        ]);
        $resRefund->assertSessionHas('success');

        $customer->refresh();
        $complaint->refresh();
        $this->assertEquals(35000, (int) $customer->customer_wallet_balance);
        $this->assertEquals('done', $complaint->status);
        $this->assertNotNull($complaint->refunded_to_wallet_at);

        // Second refund attempt must be rejected (idempotent)
        $resDouble = $this->actingAs($admin)->post(route('admin.complaints.updateStatus', $complaint->id), [
            'status' => 'refund_wallet',
        ]);
        $resDouble->assertSessionHas('error');
        $customer->refresh();
        $this->assertEquals(35000, (int) $customer->customer_wallet_balance);
    }

    public function test_seller_analytics_system_health_and_firewall_cidr_blocking(): void
    {
        $admin = $this->makeUser('admin_sec4', 'admin');
        $seller = $this->makeUser('seller_ana4', 'seller');
        $customer = $this->makeUser('cust_fw4', 'customer');

        // Seller Analytics page renders
        $this->actingAs($seller)->get(route('seller.analytics'))
            ->assertOk()
            ->assertSee('Analitik');

        // System Health page accessible by admin, blocked (redirected) for customer
        $this->actingAs($admin)->get(route('admin.system.health'))
            ->assertOk()
            ->assertSee('Database');
        $this->actingAs($customer)->get(route('admin.system.health'))
            ->assertRedirect();

        // Admin adds CIDR subnet block rule in Firewall Center
        $resRule = $this->actingAs($admin)->post(route('admin.security.firewall.store'), [
            'type' => 'subnet',
            'value' => '203.0.113.0/24',
            'duration_days' => 7,
            'reason' => 'Scraper subnet blocked',
        ]);
        $resRule->assertRedirect(route('admin.security.firewall'));

        $rule = FirewallRule::where('value', '203.0.113.0/24')->firstOrFail();
        $this->assertTrue(FirewallRule::ipMatchesCidr('203.0.113.42', '203.0.113.0/24'));
        $this->assertFalse(FirewallRule::ipMatchesCidr('198.51.100.10', '203.0.113.0/24'));

        // Customer request from blocked subnet returns 403
        Cache::forget('firewall:effective_rules');
        $this->actingAs($customer)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->get(route('dashboard'))
            ->assertForbidden();

        // Removing rule immediately restores access
        $this->actingAs($admin)->delete(route('admin.security.firewall.destroy', $rule->id))
            ->assertRedirect(route('admin.security.firewall'));

        $this->actingAs($customer)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->get(route('dashboard'))
            ->assertOk();
    }
}
