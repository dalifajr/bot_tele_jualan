<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\CartItem;
use App\Models\Review;
use App\Models\ChatMessage;
use App\Models\Order;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewFeaturesBatch2Test extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::forceCreate([
            'full_name' => 'Test User',
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => bcrypt('P@ssword123!'),
            'role' => 'customer',
        ]);

        $this->admin = User::forceCreate([
            'full_name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('P@ssword123!'),
            'role' => 'admin',
        ]);

        $this->product = Product::create([
            'name' => 'Premium Account',
            'price' => 10000,
            'description' => 'Premium description',
            'is_suspended' => false,
        ]);

        // Add 5 stock units
        for ($i = 0; $i < 5; $i++) {
            StockUnit::create([
                'product_id' => $this->product->id,
                'raw_text' => "credential_line_{$i}",
                'stock_status' => 'ready',
                'is_sold' => false,
            ]);
        }
    }

    /**
     * Test Shopping Cart operations.
     */
    public function test_cart_operations()
    {
        $this->actingAs($this->user);

        // 1. Add to cart
        $response = $this->post(route('cart.add', $this->product->id), ['quantity' => 2]);
        $response->assertRedirect(route('cart.index'));
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // 2. View cart page
        $response = $this->get(route('cart.index'));
        $response->assertStatus(200);
        $response->assertSee('Premium Account');

        // 3. Update cart quantity
        $cartItem = CartItem::first();
        $response = $this->put(route('cart.update', $cartItem->id), ['quantity' => 3]);
        $response->assertRedirect(route('cart.index'));
        $this->assertEquals(3, $cartItem->fresh()->quantity);

        // 4. Remove from cart
        $response = $this->delete(route('cart.remove', $cartItem->id));
        $response->assertRedirect(route('cart.index'));
        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    }

    /**
     * Test Coupon verification and calculations.
     */
    public function test_coupon_reductions()
    {
        $this->actingAs($this->admin);

        // 1. Create a percentage coupon
        $couponPercent = Coupon::create([
            'code' => 'PERCENT10',
            'type' => 'percent',
            'value' => 10,
            'min_spend' => 5000,
            'qty' => 10,
            'is_active' => true,
        ]);

        $this->assertTrue($couponPercent->isValidFor(10000, $this->user->id));
        $this->assertEquals(1000, $couponPercent->calculateDiscount(10000));

        // 2. Create a fixed value coupon
        $couponFixed = Coupon::create([
            'code' => 'FIXED5000',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 8000,
            'qty' => 5,
            'is_active' => true,
        ]);

        $this->assertTrue($couponFixed->isValidFor(10000, $this->user->id));
        $this->assertEquals(5000, $couponFixed->calculateDiscount(10000));

        // 3. Invalid spend test
        $this->assertFalse($couponFixed->isValidFor(4000, $this->user->id));
    }

    /**
     * Test Cart Checkout with Coupon.
     */
    public function test_cart_checkout_with_coupon()
    {
        $this->actingAs($this->user);

        // Add item to cart
        CartItem::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // Create coupon
        Coupon::create([
            'code' => 'PROMO50',
            'type' => 'percent',
            'value' => 50,
            'min_spend' => 0,
            'qty' => 10,
            'is_active' => true,
        ]);

        // Process checkout
        $response = $this->post(route('cart.process'), [
            'coupon_code' => 'PROMO50'
        ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('checkout.success', ['order_ref' => $order->order_ref]));

        // Verify discount calculations
        // Subtotal = 20000. 50% discount = 10000. Total = 10000 + unique code.
        $this->assertEquals(20000, $order->subtotal);
        $this->assertEquals(10000, $order->discount_amount);
        $this->assertEquals('PROMO50', $order->coupon_code);
        $this->assertDatabaseMissing('cart_items', ['user_id' => $this->user->id]);
    }

    /**
     * Test Rating and Review submissions.
     */
    public function test_reviews_and_ratings()
    {
        // 1. Create a delivered order
        $order = Order::create([
            'order_ref' => 'ORD-REV-12345',
            'customer_id' => $this->user->id,
            'subtotal' => 10000,
            'unique_code' => 50,
            'total_amount' => 10050,
            'status' => 'delivered',
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        $this->actingAs($this->user);

        // 2. Submit review
        $response = $this->post(route('reviews.store'), [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Excellent service!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'order_id' => $order->id,
            'rating' => 5,
            'comment' => 'Excellent service!',
        ]);
    }

    /**
     * Test Live Chat system messages.
     */
    public function test_live_chat_system()
    {
        $this->actingAs($this->user);

        // 1. Send chat message
        $response = $this->post(route('chat.send'), [
            'receiver_id' => $this->admin->id,
            'message' => 'Hello Admin, I need help with my purchase.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('chat_messages', [
            'sender_id' => $this->user->id,
            'receiver_id' => $this->admin->id,
            'message' => 'Hello Admin, I need help with my purchase.',
        ]);

        // 2. Fetch message history
        $response = $this->get(route('chat.fetch', $this->admin->id));
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
