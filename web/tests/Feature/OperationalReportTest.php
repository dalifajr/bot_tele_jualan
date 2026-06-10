<?php
 
namespace Tests\Feature;
 
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
 
class OperationalReportTest extends TestCase
{
    use RefreshDatabase;
 
    protected User $admin;
    protected User $customer;
    protected User $seller;
    protected User $otherSeller;
 
    protected function setUp(): void
    {
        parent::setUp();
 
        $this->admin = User::create([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);
 
        $this->customer = User::create([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);
 
        $this->seller = User::create([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);
 
        $this->otherSeller = User::create([
            'username' => 'other_seller',
            'full_name' => 'Other Seller',
            'email' => 'other@seller.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);
    }
 
    public function test_old_report_routes_do_not_exist(): void
    {
        // Assert old report routes are not accessible / return 404
        $this->actingAs($this->admin)->get('/admin/reports')->assertStatus(404);
        $this->actingAs($this->seller)->get('/seller/reports')->assertStatus(404);
    }
 
    public function test_admin_dashboard_can_filter_and_display_reports(): void
    {
        // Create an order delivered 10 days ago
        $oldOrder = Order::create([
            'order_ref' => 'REFOLD123',
            'customer_id' => $this->customer->id,
            'subtotal' => 150000,
            'unique_code' => 0,
            'total_amount' => 150000,
            'status' => 'delivered',
            'delivered_at' => now()->subDays(10),
        ]);
 
        // Create an order delivered today
        $newOrder = Order::create([
            'order_ref' => 'REFNEW123',
            'customer_id' => $this->customer->id,
            'subtotal' => 250000,
            'unique_code' => 0,
            'total_amount' => 250000,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
 
        // 1. Test 7 days filter on dashboard
        $response7 = $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['days' => 7]));
 
        $response7->assertStatus(200);
        $response7->assertSee('250.000'); // new order amount is visible
        
        // 2. Test 14 days filter on dashboard
        $response14 = $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['days' => 14]));
 
        $response14->assertStatus(200);
    }
 
    public function test_admin_dashboard_displays_platform_commission_accurately(): void
    {
        // 1. Create a seller with a 15% platform fee percent
        $seller = User::create([
            'username' => 'commission_seller',
            'full_name' => 'Commission Seller',
            'email' => 'commseller@test.com',
            'role' => 'seller',
            'platform_fee_percent' => 15,
            'password' => bcrypt('password'),
        ]);

        $productSeller = Product::create([
            'name' => 'Seller Item',
            'price' => 10000,
            'description' => 'Test Desc',
            'creator_id' => $seller->id,
            'is_suspended' => false,
        ]);

        // 2. Create an admin product (which should yield 100% commission/revenue to platform)
        $productAdmin = Product::create([
            'name' => 'Admin Item',
            'price' => 20000,
            'description' => 'Test Desc',
            'creator_id' => $this->admin->id,
            'is_suspended' => false,
        ]);

        // 3. Create a delivered order for the seller product
        $order1 = Order::create([
            'order_ref' => 'REFCOMM1',
            'customer_id' => $this->customer->id,
            'subtotal' => 10000,
            'unique_code' => 5,
            'total_amount' => 10005,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        StockUnit::create([
            'product_id' => $productSeller->id,
            'raw_text' => 'creds_seller_1',
            'is_sold' => true,
            'sold_order_id' => $order1->id,
            'stock_status' => 'ready',
            'seller_id' => $seller->id,
            'uploaded_by_id' => $seller->id,
        ]);

        // 4. Create a delivered order for the admin product
        $order2 = Order::create([
            'order_ref' => 'REFCOMM2',
            'customer_id' => $this->customer->id,
            'subtotal' => 20000,
            'unique_code' => 12,
            'total_amount' => 20012,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        StockUnit::create([
            'product_id' => $productAdmin->id,
            'raw_text' => 'creds_admin_1',
            'is_sold' => true,
            'sold_order_id' => $order2->id,
            'stock_status' => 'ready',
            'seller_id' => null, // admin / platform sale
            'uploaded_by_id' => $this->admin->id,
        ]);

        // Expected platform commission calculation:
        // - From seller order (10000 * 15% = 1500)
        // Total expected platformCommission = 1500
        //
        // Expected adminEarnings:
        // - From admin order (20000 * 100% = 20000)
        // - Unique codes (5 + 12 = 17)
        // Total expected adminEarnings = 20000 + 17 = 20017

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        
        $response->assertStatus(200);
        $response->assertViewHas('platformCommission', 1500);
        $response->assertViewHas('adminEarnings', 20017);
        $response->assertSee('1.500');
        $response->assertSee('20.017');
    }
 
    public function test_seller_dashboard_can_filter_display_reports_and_see_own_data_isolated(): void
    {
        // Create products
        $product1 = Product::create([
            'name' => 'Seller Product',
            'price' => 100000,
            'description' => 'Test Desc',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);
 
        $product2 = Product::create([
            'name' => 'Other Seller Product',
            'price' => 200000,
            'description' => 'Test Desc 2',
            'creator_id' => $this->otherSeller->id,
            'is_suspended' => false,
        ]);
 
        // Create orders and stock units
        $order1 = Order::create([
            'order_ref' => 'REFS1',
            'customer_id' => $this->customer->id,
            'subtotal' => 100000,
            'unique_code' => 0,
            'total_amount' => 100000,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        StockUnit::create([
            'product_id' => $product1->id,
            'raw_text' => 'credentials1',
            'is_sold' => true,
            'sold_order_id' => $order1->id,
            'stock_status' => 'ready',
            'seller_id' => $this->seller->id,
            'uploaded_by_id' => $this->seller->id,
        ]);
 
        $order2 = Order::create([
            'order_ref' => 'REFS2',
            'customer_id' => $this->customer->id,
            'subtotal' => 200000,
            'unique_code' => 0,
            'total_amount' => 200000,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        StockUnit::create([
            'product_id' => $product2->id,
            'raw_text' => 'credentials2',
            'is_sold' => true,
            'sold_order_id' => $order2->id,
            'stock_status' => 'ready',
            'seller_id' => $this->otherSeller->id,
            'uploaded_by_id' => $this->otherSeller->id,
        ]);
 
        // Test seller accesses dashboard
        $response = $this->actingAs($this->seller)
            ->get(route('seller.dashboard'));
 
        $response->assertStatus(200);
        // Seller should see their own total gross earnings (100,000)
        $response->assertSee('100.000');
        // Seller should NOT see other seller's total revenue (200,000) as their own
        $response->assertDontSee('Rp 200.000');
    }
 
    public function test_seller_dashboard_days_filter(): void
    {
        $product = Product::create([
            'name' => 'Seller Product',
            'price' => 120000,
            'description' => 'Test Desc',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);
 
        // Order today
        $orderToday = Order::create([
            'order_ref' => 'REFTODAY',
            'customer_id' => $this->customer->id,
            'subtotal' => 120000,
            'unique_code' => 0,
            'total_amount' => 120000,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'credentials today',
            'is_sold' => true,
            'sold_order_id' => $orderToday->id,
            'stock_status' => 'ready',
            'seller_id' => $this->seller->id,
            'uploaded_by_id' => $this->seller->id,
        ]);
 
        // Order 10 days ago
        $order10DaysAgo = Order::create([
            'order_ref' => 'REF10DAYS',
            'customer_id' => $this->customer->id,
            'subtotal' => 120000,
            'unique_code' => 0,
            'total_amount' => 120000,
            'status' => 'delivered',
            'delivered_at' => now()->subDays(10),
        ]);
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'credentials old',
            'is_sold' => true,
            'sold_order_id' => $order10DaysAgo->id,
            'stock_status' => 'ready',
            'seller_id' => $this->seller->id,
            'uploaded_by_id' => $this->seller->id,
        ]);
 
        // 1. With 7 days filter on seller dashboard
        $response7 = $this->actingAs($this->seller)
            ->get(route('seller.dashboard', ['days' => 7]));
        $response7->assertStatus(200);
        $response7->assertSee('120.000');
 
        // 2. With 14 days filter on seller dashboard
        $response14 = $this->actingAs($this->seller)
            ->get(route('seller.dashboard', ['days' => 14]));
        $response14->assertStatus(200);
    }
}
