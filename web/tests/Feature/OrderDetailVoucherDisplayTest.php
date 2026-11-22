<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDetailVoucherDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $seller;
    protected User $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'username' => 'admin_voucher_test',
            'full_name' => 'Admin Voucher',
            'email' => 'admin_voucher@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->seller = User::forceCreate([
            'username' => 'seller_voucher_test',
            'full_name' => 'Seller Voucher',
            'email' => 'seller_voucher@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::forceCreate([
            'username' => 'cust_voucher_test',
            'full_name' => 'Customer Voucher',
            'email' => 'cust_voucher@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $this->product = Product::create([
            'name' => 'Akun Premium Voucher Test',
            'price' => 50000,
            'creator_id' => $this->seller->id,
        ]);
    }

    public function test_voucher_and_discount_display_on_customer_admin_seller_and_checkout_success_pages(): void
    {
        $order = Order::create([
            'order_ref' => 'ORD-VCH-001',
            'customer_id' => $this->customer->id,
            'subtotal' => 50000,
            'coupon_code' => 'HEMAT10K',
            'discount_amount' => 10000,
            'unique_code' => 123,
            'total_amount' => 40123,
            'status' => 'pending_payment',
            'expires_at' => now()->addMinutes(30),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'discount_amount' => 10000,
        ]);

        // 1. Customer Order Detail
        $customerRes = $this->actingAs($this->customer)->get(route('orders.show', $order->id));
        $customerRes->assertStatus(200);
        $customerRes->assertSee('Rp 50.000');
        $customerRes->assertSee('Diskon Voucher');
        $customerRes->assertSee('HEMAT10K');
        $customerRes->assertSee('- Rp 10.000');
        $customerRes->assertSee('+ Rp 123');
        $customerRes->assertSee('Rp 40.123');

        // 2. Admin Order Detail
        $adminRes = $this->actingAs($this->admin)->get(route('admin.orders.show', $order->id));
        $adminRes->assertStatus(200);
        $adminRes->assertSee('Rp 50.000');
        $adminRes->assertSee('Diskon Voucher');
        $adminRes->assertSee('HEMAT10K');
        $adminRes->assertSee('- Rp 10.000');
        $adminRes->assertSee('+ Rp 123');
        $adminRes->assertSee('Rp 40.123');

        // 3. Seller Order Detail
        $sellerRes = $this->actingAs($this->seller)->get(route('seller.orders.show', $order->id));
        $sellerRes->assertStatus(200);
        $sellerRes->assertSee('Rp 50.000');
        $sellerRes->assertSee('Diskon Voucher');
        $sellerRes->assertSee('HEMAT10K');
        $sellerRes->assertSee('- Rp 10.000');
        $sellerRes->assertSee('+ Rp 123');
        $sellerRes->assertSee('Rp 40.123');

        // 4. Checkout Payment / Success Page
        $checkoutRes = $this->actingAs($this->customer)->get(route('checkout.success', $order->order_ref));
        $checkoutRes->assertStatus(200);
        $checkoutRes->assertSee('Rp 50.000');
        $checkoutRes->assertSee('Diskon Voucher');
        $checkoutRes->assertSee('HEMAT10K');
        $checkoutRes->assertSee('- Rp 10.000');
        $checkoutRes->assertSee('+ Rp 123');
        $checkoutRes->assertSee('Rp 40.123');
    }

    public function test_order_without_voucher_does_not_render_discount_row(): void
    {
        $order = Order::create([
            'order_ref' => 'ORD-NOVCH-002',
            'customer_id' => $this->customer->id,
            'subtotal' => 50000,
            'coupon_code' => null,
            'discount_amount' => 0,
            'unique_code' => 45,
            'total_amount' => 50045,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'discount_amount' => 0,
        ]);

        $customerRes = $this->actingAs($this->customer)->get(route('orders.show', $order->id));
        $customerRes->assertStatus(200);
        $customerRes->assertSee('Rp 50.000');
        $customerRes->assertSee('+ Rp 45');
        $customerRes->assertSee('Rp 50.045');
        $customerRes->assertDontSee('Diskon Voucher');
    }
}
