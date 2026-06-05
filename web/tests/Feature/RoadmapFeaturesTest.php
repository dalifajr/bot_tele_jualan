<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
}
