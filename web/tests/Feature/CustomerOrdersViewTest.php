<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrdersViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_access_orders_page()
    {
        $user = User::forceCreate([
            'full_name' => 'Customer User',
            'username' => 'custuser',
            'email' => 'cust@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'customer',
            'telegram_id' => 12345678,
        ]);

        $product = Product::create([
            'name' => 'Netflix 1 Bulan',
            'price' => 35000,
            'description' => 'Akun Netflix 1 Bulan',
        ]);

        $order = Order::create([
            'order_ref' => 'INV-TEST-001',
            'customer_id' => $user->id,
            'subtotal' => 35000,
            'unique_code' => 123,
            'total_amount' => 35123,
            'status' => 'delivered',
            'paid_at' => now(),
            'delivered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 35000,
        ]);

        $unit = StockUnit::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'raw_text' => 'user@mail.com:secretpass',
            'is_sold' => true,
        ]);

        $response = $this->actingAs($user)->get('/orders');
        $response->assertStatus(200);
        $response->assertSee('INV-TEST-001');
    }
}
