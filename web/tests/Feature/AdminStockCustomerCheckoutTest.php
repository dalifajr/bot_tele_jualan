<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStockCustomerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $customer;
    protected $otherUser;
    protected $product;
    protected $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'full_name' => 'Admin Boss',
            'username' => 'adminboss',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'telegram_id' => 999001,
        ]);

        $this->customer = User::forceCreate([
            'full_name' => 'Budi Customer',
            'username' => 'budicust',
            'email' => 'budi@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'telegram_id' => 888001,
        ]);

        $this->otherUser = User::forceCreate([
            'full_name' => 'Siti Customer',
            'username' => 'siticust',
            'email' => 'siti@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
            'telegram_id' => 888002,
        ]);

        $this->product = Product::create([
            'name' => 'Canva Pro Lifetime',
            'price' => 25000,
            'description' => 'Akun Canva Pro Lifetime',
            'user_id' => $this->admin->id,
            'is_suspended' => false,
        ]);

        $this->stock = StockUnit::create([
            'product_id' => $this->product->id,
            'raw_text' => 'canva_user@mail.com:secretcanva123',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);
    }

    public function test_admin_can_search_customers_json()
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.stock.search-customers', ['q' => 'budi']));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->customer->id,
            'username' => 'budicust',
            'full_name' => 'Budi Customer',
        ]);
        $response->assertJsonMissing([
            'id' => $this->otherUser->id,
            'username' => 'siticust',
        ]);
    }

    public function test_non_admin_cannot_search_customers()
    {
        $response = $this->actingAs($this->customer)
            ->getJson(route('admin.stock.search-customers', ['q' => 'budi']));

        $response->assertStatus(403);
    }

    public function test_admin_checkout_stocks_default_assigns_to_admin()
    {
        $payload = [
            'ids' => json_encode([$this->stock->id]),
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.stock.checkout'), $payload);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->admin->id, $order->customer_id);
        $this->assertEquals(25000, $order->subtotal);

        $response->assertRedirect(route('checkout.success', ['order_ref' => $order->order_ref]));

        $this->stock->refresh();
        $this->assertEquals('reserved_checkout', $this->stock->stock_status);
        $this->assertEquals($order->id, $this->stock->sold_order_id);
    }

    public function test_admin_checkout_stocks_assigned_to_specific_customer()
    {
        $payload = [
            'ids' => json_encode([$this->stock->id]),
            'customer_id' => $this->customer->id,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.stock.checkout'), $payload);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->customer->id, $order->customer_id);
        $this->assertEquals(25000, $order->subtotal);

        // Admin is redirected to invoice page
        $response->assertRedirect(route('checkout.success', ['order_ref' => $order->order_ref]));

        // Customer logs in and checks /orders
        $ordersResponse = $this->actingAs($this->customer)->get(route('orders.index'));
        $ordersResponse->assertStatus(200);
        $ordersResponse->assertSee($order->order_ref);
        $ordersResponse->assertSee('Canva Pro Lifetime');

        // Customer views order detail
        $orderDetailResponse = $this->actingAs($this->customer)->get(route('orders.show', $order->id));
        $orderDetailResponse->assertStatus(200);
        $orderDetailResponse->assertSee($order->order_ref);
        $orderDetailResponse->assertSee('Canva Pro Lifetime');

        // Customer views checkout.success (payment invoice page)
        $invoiceResponse = $this->actingAs($this->customer)->get(route('checkout.success', ['order_ref' => $order->order_ref]));
        $invoiceResponse->assertStatus(200);
        $invoiceResponse->assertSee($order->order_ref);
        $invoiceResponse->assertSee('Budi Customer');
    }

    public function test_other_customer_cannot_access_assigned_order()
    {
        $payload = [
            'ids' => json_encode([$this->stock->id]),
            'customer_id' => $this->customer->id,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.stock.checkout'), $payload);

        $order = Order::latest('id')->first();

        // Other customer tries to view orders.show
        $detailResponse = $this->actingAs($this->otherUser)->get(route('orders.show', $order->id));
        $detailResponse->assertStatus(404);

        // Other customer tries to view checkout.success
        $invoiceResponse = $this->actingAs($this->otherUser)->get(route('checkout.success', ['order_ref' => $order->order_ref]));
        $invoiceResponse->assertStatus(403);
    }

    public function test_checkout_fails_if_stock_already_sold()
    {
        $this->stock->update(['is_sold' => true]);

        $payload = [
            'ids' => json_encode([$this->stock->id]),
            'customer_id' => $this->customer->id,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.stock.checkout'), $payload);

        $response->assertSessionHas('error');
        $this->assertEquals(0, Order::count());
    }

    public function test_checkout_fails_if_customer_id_does_not_exist()
    {
        $payload = [
            'ids' => json_encode([$this->stock->id]),
            'customer_id' => 999999,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.stock.checkout'), $payload);

        $response->assertSessionHasErrors(['customer_id']);
        $this->assertEquals(0, Order::count());
    }

    public function test_admin_can_checkout_awaiting_benefits_and_saved_stocks()
    {
        $this->stock->update(['stock_status' => 'awaiting_benefits']);

        $payload = [
            'ids' => json_encode([$this->stock->id]),
            'customer_id' => $this->customer->id,
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.stock.checkout'), $payload);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->customer->id, $order->customer_id);
        $response->assertRedirect(route('checkout.success', ['order_ref' => $order->order_ref]));
    }
}

