<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoadmapFeaturesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that order rate limiting prevents customer from having more than 2 pending payment orders.
     */
    public function test_checkout_rate_limiting()
    {
        $customer = User::create([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Premium Account',
            'price' => 50000,
        ]);

        // Add enough stock units for checkout
        for ($i = 0; $i < 5; $i++) {
            StockUnit::create([
                'product_id' => $product->id,
                'raw_text' => "Credentials {$i}",
                'is_sold' => false,
                'stock_status' => 'ready',
            ]);
        }

        $this->actingAs($customer);

        // 1st checkout should succeed
        $response1 = $this->post(route('checkout.store', $product->id), ['quantity' => 1]);
        $response1->assertRedirect();
        $this->assertEquals(1, Order::where('customer_id', $customer->id)->where('status', 'pending_payment')->count());

        // 2nd checkout should succeed
        $response2 = $this->post(route('checkout.store', $product->id), ['quantity' => 1]);
        $response2->assertRedirect();
        $this->assertEquals(2, Order::where('customer_id', $customer->id)->where('status', 'pending_payment')->count());

        // 3rd checkout should fail and redirect back with error
        $response3 = $this->post(route('checkout.store', $product->id), ['quantity' => 1]);
        $response3->assertStatus(302);
        $response3->assertSessionHas('error', 'Anda memiliki terlalu banyak pesanan yang menunggu pembayaran. Silakan selesaikan atau batalkan pesanan Anda sebelumnya.');
        
        // Assert order count is still 2
        $this->assertEquals(2, Order::where('customer_id', $customer->id)->where('status', 'pending_payment')->count());
    }

    /**
     * Test that artisan orders:release-expired command cancels expired pending orders and releases stock.
     */
    public function test_cron_auto_release_stock()
    {
        $customer = User::create([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Premium Account',
            'price' => 50000,
        ]);

        // Create expired order
        $expiredOrder = Order::create([
            'order_ref' => 'ORD-EXPIRED',
            'customer_id' => $customer->id,
            'subtotal' => 50000,
            'unique_code' => 12,
            'total_amount' => 50012,
            'status' => 'pending_payment',
            'expires_at' => now()->subMinutes(5),
        ]);

        OrderItem::create([
            'order_id' => $expiredOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        $expiredStock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Expired Stock Credentials',
            'is_sold' => false,
            'stock_status' => 'reserved_checkout',
            'sold_order_id' => $expiredOrder->id,
        ]);

        // Create a non-expired (active) order
        $activeOrder = Order::create([
            'order_ref' => 'ORD-ACTIVE',
            'customer_id' => $customer->id,
            'subtotal' => 50000,
            'unique_code' => 54,
            'total_amount' => 50054,
            'status' => 'pending_payment',
            'expires_at' => now()->addMinutes(15),
        ]);

        OrderItem::create([
            'order_id' => $activeOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        $activeStock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Active Stock Credentials',
            'is_sold' => false,
            'stock_status' => 'reserved_checkout',
            'sold_order_id' => $activeOrder->id,
        ]);

        // Run the auto-release command
        Artisan::call('orders:release-expired');

        // Reload data from DB
        $expiredOrder->refresh();
        $activeOrder->refresh();
        $expiredStock->refresh();
        $activeStock->refresh();

        // Expired order assertions
        $this->assertEquals('expired', $expiredOrder->status);
        $this->assertNotNull($expiredOrder->cancelled_at);
        $this->assertEquals('Batas waktu pembayaran telah habis (Sistem)', $expiredOrder->cancel_reason);
        $this->assertEquals('ready', $expiredStock->stock_status);
        $this->assertNull($expiredStock->sold_order_id);

        // Active order assertions
        $this->assertEquals('pending_payment', $activeOrder->status);
        $this->assertNull($activeOrder->cancelled_at);
        $this->assertEquals('reserved_checkout', $activeStock->stock_status);
        $this->assertEquals($activeOrder->id, $activeStock->sold_order_id);
    }

    /**
     * Test admin can replace problematic stock unit with another ready stock from the same seller.
     */
    public function test_replace_stock_by_admin()
    {
        $admin = User::create([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $seller = User::create([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'seller_save_hours' => 12,
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Product Test',
            'price' => 50000,
            'creator_id' => $seller->id,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-123',
            'customer_id' => $seller->id,
            'subtotal' => 50000,
            'unique_code' => 0,
            'total_amount' => 50000,
            'status' => 'delivered',
        ]);

        $problemStock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Problem stock credentials',
            'is_sold' => true,
            'stock_status' => 'ready',
            'sold_order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        $newStock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Good replacement stock',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller->id,
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.orders.replace-stock', [$order->id, $problemStock->id]));

        $response->assertRedirect();
        
        $problemStock->refresh();
        $newStock->refresh();

        $this->assertFalse($problemStock->is_sold);
        $this->assertNull($problemStock->sold_order_id);
        $this->assertEquals('saved_for_verification', $problemStock->stock_status);
        $this->assertNotNull($problemStock->available_at);

        $this->assertTrue($newStock->is_sold);
        $this->assertEquals($order->id, $newStock->sold_order_id);
    }

    /**
     * Test admin refunding order cancels held funds and moves stock back to saved_for_verification.
     */
    public function test_refund_order_by_admin()
    {
        $admin = User::create([
            'username' => 'admin_test2',
            'full_name' => 'Admin Test 2',
            'email' => 'admin2@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $seller = User::create([
            'username' => 'seller_test2',
            'full_name' => 'Seller Test 2',
            'email' => 'seller2@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Product Test',
            'price' => 50000,
            'warranty_days' => 3,
            'creator_id' => $seller->id,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-456',
            'customer_id' => $seller->id,
            'subtotal' => 50000,
            'unique_code' => 0,
            'total_amount' => 50000,
            'status' => 'delivered',
        ]);

        $stock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Some credentials',
            'is_sold' => true,
            'stock_status' => 'ready',
            'sold_order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        // Create held funds record
        DB::table('held_funds')->insert([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount' => 45000,
            'status' => 'held',
            'release_at' => now()->addDays(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.orders.refund', $order->id));

        $response->assertRedirect();
        
        $order->refresh();
        $stock->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertFalse($stock->is_sold);
        $this->assertEquals('saved_for_verification', $stock->stock_status);

        $heldFund = DB::table('held_funds')->where('order_id', $order->id)->first();
        $this->assertEquals('cancelled', $heldFund->status);
    }

    /**
     * Test command releases held funds and notifies seller.
     */
    public function test_release_held_funds_command()
    {
        $seller = User::create([
            'username' => 'seller_test3',
            'full_name' => 'Seller Test 3',
            'email' => 'seller3@test.com',
            'role' => 'seller',
            'wallet_balance' => 1000,
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Product Test',
            'price' => 50000,
            'warranty_days' => 3,
            'creator_id' => $seller->id,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-789',
            'customer_id' => $seller->id,
            'subtotal' => 50000,
            'unique_code' => 0,
            'total_amount' => 50000,
            'status' => 'delivered',
        ]);

        // Held fund that is ready for release
        DB::table('held_funds')->insert([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount' => 45000,
            'status' => 'held',
            'release_at' => now()->subMinutes(5),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        // Held fund that is not ready for release
        DB::table('held_funds')->insert([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount' => 30000,
            'status' => 'held',
            'release_at' => now()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('funds:release-held');

        $seller->refresh();
        $this->assertEquals(46000, $seller->wallet_balance); // 1000 + 45000

        $fund1 = DB::table('held_funds')->where('amount', 45000)->first();
        $this->assertEquals('released', $fund1->status);

        $fund2 = DB::table('held_funds')->where('amount', 30000)->first();
        $this->assertEquals('held', $fund2->status);
    }
}
