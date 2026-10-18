<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\CartItem;
use App\Models\StockUnit;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CouponImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $seller1;
    protected $seller2;
    protected $customer;
    protected $product1;
    protected $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'full_name' => 'Admin Boss',
            'username' => 'adminboss',
            'email' => 'admin@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);

        $this->seller1 = User::forceCreate([
            'full_name' => 'Seller One',
            'username' => 'sellerone',
            'email' => 'seller1@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'seller',
        ]);

        $this->seller2 = User::forceCreate([
            'full_name' => 'Seller Two',
            'username' => 'sellertwo',
            'email' => 'seller2@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'seller',
        ]);

        $this->customer = User::forceCreate([
            'full_name' => 'Customer John',
            'username' => 'customerjohn',
            'email' => 'customer@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'customer',
        ]);

        $this->product1 = Product::create([
            'name' => 'Canva Pro 1 Tahun',
            'price' => 50000,
            'description' => 'Akun Canva Pro',
            'is_suspended' => false,
            'creator_id' => $this->seller1->id,
        ]);

        $this->product2 = Product::create([
            'name' => 'Netflix Premium 1 Bulan',
            'price' => 40000,
            'description' => 'Akun Netflix',
            'is_suspended' => false,
            'creator_id' => $this->seller2->id,
        ]);

        // Add stocks
        StockUnit::create([
            'product_id' => $this->product1->id,
            'seller_id' => $this->seller1->id,
            'raw_text' => 'canva_user:canva_pass',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);

        StockUnit::create([
            'product_id' => $this->product2->id,
            'seller_id' => $this->seller2->id,
            'raw_text' => 'netflix_user:netflix_pass',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);
    }

    /**
     * 1. Test Admin Coupons View renders with modals outside tables and products list.
     */
    public function test_admin_coupons_page_renders_with_modals_and_products(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('admin.coupons.index'));
        $response->assertStatus(200);
        $response->assertSee('Kupon & Promosi');
        $response->assertSee('Tambah Kupon');
        $response->assertSee('createCouponModal');
        $response->assertSee('Canva Pro 1 Tahun');
        $response->assertSee('Netflix Premium 1 Bulan');
    }

    /**
     * 2. Test Admin can create coupon assigned to specific products.
     */
    public function test_admin_can_create_coupon_assigned_to_specific_products(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('admin.coupons.store'), [
            'code' => 'CANVAONLY',
            'type' => 'fixed',
            'value' => 10000,
            'min_spend' => 20000,
            'qty' => 50,
            'scope' => 'specific',
            'product_ids' => [$this->product1->id],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.coupons.index'));
        $this->assertDatabaseHas('coupons', [
            'code' => 'CANVAONLY',
            'value' => 10000,
            'user_id' => $this->admin->id,
        ]);

        $coupon = Coupon::where('code', 'CANVAONLY')->first();
        $this->assertTrue($coupon->isRestrictedToProducts());
        $this->assertTrue($coupon->appliesToProduct($this->product1));
        $this->assertFalse($coupon->appliesToProduct($this->product2));
    }

    /**
     * 3. Test Seller can access their own coupon portal and create store coupon.
     */
    public function test_seller_can_access_own_coupons_and_create_store_coupon(): void
    {
        $this->actingAs($this->seller1);

        $response = $this->get(route('seller.coupons.index'));
        $response->assertStatus(200);
        $response->assertSee('Kupon & Promosi Toko');
        $response->assertSee('createSellerCouponModal');
        $response->assertSee('Canva Pro 1 Tahun');
        // Seller 1 should NOT see Seller 2's products in their selection list
        $response->assertDontSee('Netflix Premium 1 Bulan');

        // Create coupon for seller 1
        $response = $this->post(route('seller.coupons.store'), [
            'code' => 'SELLER1DISCOUNT',
            'type' => 'percent',
            'value' => 20,
            'min_spend' => 0,
            'max_discount' => 15000,
            'qty' => 30,
            'scope' => 'specific',
            'product_ids' => [$this->product1->id],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('seller.coupons.index'));
        $this->assertDatabaseHas('coupons', [
            'code' => 'SELLER1DISCOUNT',
            'user_id' => $this->seller1->id,
        ]);
    }

    /**
     * 4. Test Seller cannot see or modify other sellers' coupons.
     */
    public function test_seller_cannot_see_or_modify_other_coupons(): void
    {
        // Coupon by Seller 1
        $coupon1 = Coupon::create([
            'user_id' => $this->seller1->id,
            'code' => 'S1COUPON',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 0,
            'qty' => 10,
            'is_active' => true,
        ]);

        // Coupon by Seller 2
        $coupon2 = Coupon::create([
            'user_id' => $this->seller2->id,
            'code' => 'S2COUPON',
            'type' => 'fixed',
            'value' => 7000,
            'min_spend' => 0,
            'qty' => 10,
            'is_active' => true,
        ]);

        // Seller 1 visits coupons page
        $this->actingAs($this->seller1);
        $response = $this->get(route('seller.coupons.index'));
        $response->assertStatus(200);
        $response->assertSee('S1COUPON');
        $response->assertDontSee('S2COUPON');

        // Seller 1 tries to update Seller 2's coupon -> 404
        $updateResponse = $this->put(route('seller.coupons.update', $coupon2->id), [
            'code' => 'HACKED',
            'type' => 'fixed',
            'value' => 1000,
            'min_spend' => 0,
            'qty' => 5,
            'scope' => 'all',
        ]);
        $updateResponse->assertStatus(404);

        // Seller 1 tries to delete Seller 2's coupon -> 404
        $deleteResponse = $this->delete(route('seller.coupons.destroy', $coupon2->id));
        $deleteResponse->assertStatus(404);

        // Admin can see BOTH coupons
        $this->actingAs($this->admin);
        $adminResponse = $this->get(route('admin.coupons.index'));
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('S1COUPON');
        $adminResponse->assertSee('S2COUPON');
    }

    /**
     * 5. Test Seller cannot assign another seller's product to their coupon.
     */
    public function test_seller_cannot_assign_other_sellers_products_to_coupon(): void
    {
        $this->actingAs($this->seller1);

        $response = $this->post(route('seller.coupons.store'), [
            'code' => 'INVALIDPROD',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 0,
            'qty' => 10,
            'scope' => 'specific',
            'product_ids' => [$this->product2->id], // Owned by seller 2!
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('product_ids');
        $this->assertDatabaseMissing('coupons', ['code' => 'INVALIDPROD']);
    }

    /**
     * 6. Test direct checkout validates product-specific coupon restriction.
     */
    public function test_product_specific_coupon_validation_in_direct_checkout(): void
    {
        $this->actingAs($this->customer);

        // Coupon applies only to product1 (Canva)
        $coupon = Coupon::create([
            'code' => 'CANVA50',
            'type' => 'percent',
            'value' => 50,
            'min_spend' => 0,
            'qty' => 10,
            'is_active' => true,
        ]);
        $coupon->products()->sync([$this->product1->id]);

        // Review checkout for product1: Success
        $reviewP1 = $this->get(route('checkout.review', [
            'product' => $this->product1->id,
            'coupon_code' => 'CANVA50',
        ]));
        $reviewP1->assertStatus(200);
        $reviewP1->assertSee('CANVA50');
        $reviewP1->assertSee('25.000'); // 50% of 50000 = 25000 discount

        // Review checkout for product2 (Netflix): Rejected with error
        $reviewP2 = $this->get(route('checkout.review', [
            'product' => $this->product2->id,
            'coupon_code' => 'CANVA50',
        ]));
        $reviewP2->assertStatus(200);
        $reviewP2->assertSee('Kupon ini tidak dapat digunakan untuk produk ini.');
    }

    /**
     * 7. Test seller coupon cannot be applied to another seller's product.
     */
    public function test_seller_coupon_cannot_be_used_on_different_seller_product(): void
    {
        $this->actingAs($this->customer);

        // Seller 1 creates coupon
        $sellerCoupon = Coupon::create([
            'user_id' => $this->seller1->id,
            'code' => 'SELLER1ALL',
            'type' => 'fixed',
            'value' => 10000,
            'min_spend' => 0,
            'qty' => 10,
            'is_active' => true,
        ]);

        // Customer uses on Product 2 (owned by Seller 2) -> Rejected
        $response = $this->get(route('checkout.review', [
            'product' => $this->product2->id,
            'coupon_code' => 'SELLER1ALL',
        ]));
        $response->assertStatus(200);
        $response->assertSee('Kupon ini hanya berlaku untuk produk dari penjual toko terkait.');
    }

    /**
     * 8. Test shopping cart calculates discount only on eligible items.
     */
    public function test_cart_checkout_with_product_specific_coupon_calculates_discount_on_eligible_items_only(): void
    {
        $this->actingAs($this->customer);

        // Add Product 1 (50,000) and Product 2 (40,000) to cart
        CartItem::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->product1->id,
            'quantity' => 1,
        ]);
        CartItem::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->product2->id,
            'quantity' => 1,
        ]);

        // Coupon for Product 1 only: 20%
        $coupon = Coupon::create([
            'code' => 'DISC20P1',
            'type' => 'percent',
            'value' => 20,
            'min_spend' => 0,
            'qty' => 10,
            'is_active' => true,
        ]);
        $coupon->products()->sync([$this->product1->id]);

        // View cart checkout with coupon
        $checkoutView = $this->get(route('cart.checkout', ['coupon_code' => 'DISC20P1']));
        $checkoutView->assertStatus(200);
        // 20% of Product 1 (50,000) = 10,000 discount (NOT 20% of 90,000 = 18,000)
        $checkoutView->assertSee('10.000');

        // Process cart checkout
        $processResponse = $this->post(route('cart.process'), [
            'coupon_code' => 'DISC20P1'
        ]);

        $order = Order::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($order);
        $processResponse->assertRedirect(route('checkout.success', ['order_ref' => $order->order_ref]));

        $this->assertEquals(90000, $order->subtotal);
        $this->assertEquals(10000, $order->discount_amount);
        $this->assertEquals('DISC20P1', $order->coupon_code);

        // Check used_qty incremented and coupon_user inserted
        $this->assertEquals(1, $coupon->fresh()->used_qty);
        $this->assertDatabaseHas('coupon_user', [
            'coupon_id' => $coupon->id,
            'user_id' => $this->customer->id,
        ]);
    }
}
