<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_seller_can_only_see_orders_associated_with_their_products()
    {
        // 1. Create two sellers & one customer
        $seller1 = User::forceCreate([
            'username' => 'seller1',
            'full_name' => 'Seller One',
            'email' => 'seller1@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $seller2 = User::forceCreate([
            'username' => 'seller2',
            'full_name' => 'Seller Two',
            'email' => 'seller2@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $customer = User::forceCreate([
            'username' => 'customer_test',
            'full_name' => 'Customer',
            'email' => 'customer_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        // 2. Create products
        $product1 = Product::create([
            'name' => 'Product One',
            'price' => 10000,
            'description' => 'Desc',
            'creator_id' => $seller1->id,
            'is_suspended' => false,
        ]);

        $product2 = Product::create([
            'name' => 'Product Two',
            'price' => 20000,
            'description' => 'Desc',
            'creator_id' => $seller2->id,
            'is_suspended' => false,
        ]);

        // 3. Create orders
        $order1 = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
            'subtotal' => 10000,
            'unique_code' => 5,
            'total_amount' => 10000,
            'payment_status' => 'pending',
            'order_ref' => 'ORD-11111',
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $product1->id,
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        $order2 = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
            'subtotal' => 20000,
            'unique_code' => 87,
            'total_amount' => 20000,
            'payment_status' => 'pending',
            'order_ref' => 'ORD-22222',
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'unit_price' => 20000,
        ]);

        // 4. Authenticate as Seller 1
        $response = $this->actingAs($seller1)
            ->get(route('seller.orders.index'));

        $response->assertStatus(200);
        $response->assertSee('ORD-11111');
        $response->assertDontSee('ORD-22222');
    }

    /** @test */
    public function test_seller_can_cancel_their_own_pending_order()
    {
        // 1. Create seller, customer, product & pending order
        $seller = User::forceCreate([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $customer = User::forceCreate([
            'username' => 'customer_test',
            'full_name' => 'Customer',
            'email' => 'customer_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Test Product',
            'price' => 15000,
            'creator_id' => $seller->id,
            'is_suspended' => false,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'pending_payment',
            'subtotal' => 15000,
            'unique_code' => 23,
            'total_amount' => 15000,
            'order_ref' => 'ORD-PENDING',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 15000,
        ]);

        // 2. Perform cancellation
        $response = $this->actingAs($seller)
            ->post(route('seller.orders.cancel', $order->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('cancelled_by_seller', $order->cancel_reason);
    }
}
