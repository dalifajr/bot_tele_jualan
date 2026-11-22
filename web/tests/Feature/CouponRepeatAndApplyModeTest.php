<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CouponRepeatAndApplyModeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $seller;
    protected User $customer;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'full_name' => 'Admin Coupon',
            'username' => 'admin_coupon_repeat',
            'email' => 'admin_coupon_repeat@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);

        $this->seller = User::forceCreate([
            'full_name' => 'Seller Coupon',
            'username' => 'seller_coupon_repeat',
            'email' => 'seller_coupon_repeat@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'seller',
        ]);

        $this->customer = User::forceCreate([
            'full_name' => 'Customer Coupon',
            'username' => 'cust_coupon_repeat',
            'email' => 'cust_coupon_repeat@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'customer',
        ]);

        $this->productA = Product::create([
            'name' => 'Produk A 50K',
            'price' => 50000,
            'is_suspended' => false,
            'creator_id' => $this->seller->id,
        ]);

        $this->productB = Product::create([
            'name' => 'Produk B 40K',
            'price' => 40000,
            'is_suspended' => false,
            'creator_id' => $this->admin->id,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            StockUnit::create([
                'product_id' => $this->productA->id,
                'seller_id' => $this->seller->id,
                'raw_text' => "accA{$i}:pass",
                'stock_status' => 'ready',
                'is_sold' => false,
            ]);
            StockUnit::create([
                'product_id' => $this->productB->id,
                'seller_id' => $this->admin->id,
                'raw_text' => "accB{$i}:pass",
                'stock_status' => 'ready',
                'is_sold' => false,
            ]);
        }
    }

    public function test_admin_and_seller_can_create_and_update_repeat_limit_and_apply_mode(): void
    {
        // Admin creates repeatable per-item coupon with max 3 uses per user
        $this->actingAs($this->admin)->post(route('admin.coupons.store'), [
            'code' => 'REPEAT3ITEM',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 0,
            'qty' => 50,
            'allow_multiple_uses' => '1',
            'max_uses_per_user' => 3,
            'apply_per_item' => '1',
            'scope' => 'all',
            'is_active' => '1',
        ])->assertRedirect(route('admin.coupons.index'));

        $coupon = Coupon::where('code', 'REPEAT3ITEM')->firstOrFail();
        $this->assertTrue($coupon->allow_multiple_uses);
        $this->assertEquals(3, $coupon->max_uses_per_user);
        $this->assertEquals(Coupon::APPLY_PER_ITEM, $coupon->apply_mode);
        $this->assertTrue($coupon->isPerItemMode());

        // Toggle OFF repeat & per-item on update -> resets max_uses_per_user to null and apply_mode to per_order
        $this->actingAs($this->admin)->put(route('admin.coupons.update', $coupon->id), [
            'code' => 'REPEAT3ITEM',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 0,
            'qty' => 50,
            'scope' => 'all',
            'is_active' => '1',
        ])->assertRedirect(route('admin.coupons.index'));

        $coupon->refresh();
        $this->assertFalse($coupon->allow_multiple_uses);
        $this->assertNull($coupon->max_uses_per_user);
        $this->assertEquals(Coupon::APPLY_PER_ORDER, $coupon->apply_mode);

        // Seller creates unlimited-per-user coupon in per-item mode
        $this->actingAs($this->seller)->post(route('seller.coupons.store'), [
            'code' => 'SELLERINF',
            'type' => 'fixed',
            'value' => 3000,
            'min_spend' => 0,
            'qty' => 20,
            'allow_multiple_uses' => '1',
            'max_uses_per_user' => 0,
            'apply_per_item' => '1',
            'scope' => 'all',
            'is_active' => '1',
        ])->assertRedirect(route('seller.coupons.index'));

        $sellerCoupon = Coupon::where('code', 'SELLERINF')->firstOrFail();
        $this->assertTrue($sellerCoupon->allow_multiple_uses);
        $this->assertNull($sellerCoupon->getEffectiveMaxUsesPerUser());
        $this->assertTrue($sellerCoupon->isPerItemMode());
    }

    public function test_repeat_coupon_enforces_per_user_limit_and_unlimited_respects_global_quota(): void
    {
        $limitedCoupon = Coupon::create([
            'code' => 'LIMIT2X',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 0,
            'qty' => 10,
            'allow_multiple_uses' => true,
            'max_uses_per_user' => 2,
            'apply_mode' => Coupon::APPLY_PER_ORDER,
            'is_active' => true,
        ]);

        // Usage 1 -> valid
        $this->assertTrue($limitedCoupon->isValidFor(50000, $this->customer->id));
        DB::table('coupon_user')->insert(['coupon_id' => $limitedCoupon->id, 'user_id' => $this->customer->id, 'created_at' => now()]);
        $limitedCoupon->increment('used_qty');

        // Usage 2 -> still valid!
        $this->assertTrue($limitedCoupon->fresh()->isValidFor(50000, $this->customer->id));
        $review2 = $this->actingAs($this->customer)->get(route('checkout.review', [
            'product' => $this->productA->id,
            'quantity' => 1,
            'coupon_code' => 'LIMIT2X',
        ]));
        $review2->assertStatus(200);
        $review2->assertSee('5.000');

        DB::table('coupon_user')->insert(['coupon_id' => $limitedCoupon->id, 'user_id' => $this->customer->id, 'created_at' => now()]);
        $limitedCoupon->increment('used_qty');

        // Usage 3 -> blocked with per-account limit message
        $this->assertFalse($limitedCoupon->fresh()->isValidFor(50000, $this->customer->id));
        $review3 = $this->actingAs($this->customer)->get(route('checkout.review', [
            'product' => $this->productA->id,
            'quantity' => 1,
            'coupon_code' => 'LIMIT2X',
        ]));
        $review3->assertStatus(200);
        $review3->assertSee('maksimal 2 kali per akun');

        // Unlimited per user coupon works 3+ times until global qty is reached
        $unlimitedCoupon = Coupon::create([
            'code' => 'UNLIMUSER',
            'type' => 'fixed',
            'value' => 2000,
            'min_spend' => 0,
            'qty' => 3,
            'allow_multiple_uses' => true,
            'max_uses_per_user' => null,
            'apply_mode' => Coupon::APPLY_PER_ORDER,
            'is_active' => true,
        ]);

        for ($u = 1; $u <= 3; $u++) {
            $this->assertTrue($unlimitedCoupon->fresh()->isValidFor(50000, $this->customer->id));
            DB::table('coupon_user')->insert(['coupon_id' => $unlimitedCoupon->id, 'user_id' => $this->customer->id, 'created_at' => now()]);
            $unlimitedCoupon->increment('used_qty');
        }
        // Global qty (3/3) exhausted -> rejected
        $this->assertFalse($unlimitedCoupon->fresh()->isValidFor(50000, $this->customer->id));
    }

    public function test_per_item_vs_per_order_discount_calculation_in_direct_and_cart_checkout(): void
    {
        // 1. Direct Checkout (qty = 3, unit price = 50,000, fixed discount = 5,000)
        $perOrderCoupon = Coupon::create([
            'code' => 'ORDER5K',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 0,
            'qty' => 10,
            'allow_multiple_uses' => true,
            'apply_mode' => Coupon::APPLY_PER_ORDER,
            'is_active' => true,
        ]);

        $perItemCoupon = Coupon::create([
            'code' => 'UNIT5K',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 0,
            'qty' => 10,
            'allow_multiple_uses' => true,
            'apply_mode' => Coupon::APPLY_PER_ITEM,
            'is_active' => true,
        ]);

        // Direct math check including percent with max_discount cap
        $percentPerItem = Coupon::create([
            'code' => 'PCT20UNIT',
            'type' => 'percent',
            'value' => 20,
            'max_discount' => 6000,
            'min_spend' => 0,
            'qty' => 10,
            'apply_mode' => Coupon::APPLY_PER_ITEM,
            'is_active' => true,
        ]);
        // 20% of 50,000 = 10,000 capped at 6,000 per unit x 3 units = 18,000
        $this->assertEquals(18000, $percentPerItem->calculateDiscountForUnits(50000, 3));
        // Fixed 5,000 per order x 3 units = 5,000; per item x 3 units = 15,000
        $this->assertEquals(5000, $perOrderCoupon->calculateDiscountForUnits(50000, 3));
        $this->assertEquals(15000, $perItemCoupon->calculateDiscountForUnits(50000, 3));
        // Protection against unit price smaller than fixed discount (3,000 < 5,000 -> max 3,000/unit)
        $this->assertEquals(9000, $perItemCoupon->calculateDiscountForUnits(3000, 3));

        // 2. Direct checkout store with UNIT5K on qty=3 -> discount 15,000
        $this->actingAs($this->customer)->post(route('checkout.store', $this->productA->id), [
            'quantity' => 3,
            'coupon_code' => 'UNIT5K',
        ])->assertRedirect();

        $directOrder = Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertEquals(150000, $directOrder->subtotal);
        $this->assertEquals(15000, $directOrder->discount_amount);
        $this->assertEquals('UNIT5K', $directOrder->coupon_code);

        // Cancel pending order so customer can test cart checkout cleanly
        $directOrder->update(['status' => 'cancelled']);

        // 3. Cart Checkout with multi-item (2x Product A eligible + 1x Product B NOT eligible)
        $perItemCoupon->products()->sync([$this->productA->id]);

        CartItem::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
        ]);
        CartItem::create([
            'user_id' => $this->customer->id,
            'product_id' => $this->productB->id,
            'quantity' => 1,
        ]);

        // Preview cart shows 10.000 (2 x 5.000 on Product A only)
        $this->actingAs($this->customer)
            ->get(route('cart.checkout', ['coupon_code' => 'UNIT5K']))
            ->assertStatus(200)
            ->assertSee('10.000');

        // Process cart -> Order gets 10,000 discount, Product A OrderItem gets 10,000, Product B gets 0
        $this->actingAs($this->customer)->post(route('cart.process'), [
            'coupon_code' => 'UNIT5K',
        ])->assertRedirect();

        $cartOrder = Order::with('items')->where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertEquals(140000, $cartOrder->subtotal);
        $this->assertEquals(10000, $cartOrder->discount_amount);
        $this->assertEquals('UNIT5K', $cartOrder->coupon_code);

        $itemA = $cartOrder->items->firstWhere('product_id', $this->productA->id);
        $itemB = $cartOrder->items->firstWhere('product_id', $this->productB->id);
        $this->assertEquals(10000, $itemA->discount_amount);
        $this->assertEquals(0, $itemB->discount_amount);
    }
}
