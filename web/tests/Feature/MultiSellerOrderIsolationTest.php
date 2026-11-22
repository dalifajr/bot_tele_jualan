<?php

namespace Tests\Feature;

use App\Models\ComplaintCase;
use App\Models\HeldFund;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiSellerOrderIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $sellerA;
    protected User $sellerB;
    protected User $sellerC;
    protected User $customer;
    protected Product $prodA;
    protected Product $prodB;
    protected Product $prodC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'full_name' => 'Admin Boss',
            'username' => 'admin_ms',
            'email' => 'admin_ms@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->sellerA = User::forceCreate([
            'full_name' => 'Toko Alpha',
            'username' => 'toko_alpha',
            'email' => 'alpha@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $this->sellerB = User::forceCreate([
            'full_name' => 'Toko Beta',
            'username' => 'toko_beta',
            'email' => 'beta@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $this->sellerC = User::forceCreate([
            'full_name' => 'Toko Gamma',
            'username' => 'toko_gamma',
            'email' => 'gamma@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $this->customer = User::forceCreate([
            'full_name' => 'Pembeli Multi',
            'username' => 'pembeli_multi',
            'email' => 'pembeli@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'customer_wallet_balance' => 0,
        ]);

        // Product A: 0 warranty days (Seller A)
        $this->prodA = Product::create([
            'name' => 'Produk Alpha No-Warranty',
            'price' => 30000,
            'warranty_days' => 0,
            'creator_id' => $this->sellerA->id,
        ]);

        // Product B: 30 warranty days (Seller B)
        $this->prodB = Product::create([
            'name' => 'Produk Beta 30D',
            'price' => 50000,
            'warranty_days' => 30,
            'creator_id' => $this->sellerB->id,
        ]);

        // Product C: 7 warranty days (Seller C)
        $this->prodC = Product::create([
            'name' => 'Produk Gamma 7D',
            'price' => 70000,
            'warranty_days' => 7,
            'creator_id' => $this->sellerC->id,
        ]);
    }

    protected function createMultiSellerOrder(string $status = 'delivered'): array
    {
        $order = Order::create([
            'order_ref' => 'ORD-MULTI-' . strtoupper(uniqid()),
            'customer_id' => $this->customer->id,
            'subtotal' => 150000,
            'discount_amount' => 0,
            'unique_code' => 111,
            'total_amount' => 150111,
            'status' => $status,
            'delivered_at' => $status === 'delivered' ? now() : null,
            'expires_at' => now()->addMinutes(30),
        ]);

        $itemA = OrderItem::create(['order_id' => $order->id, 'product_id' => $this->prodA->id, 'quantity' => 1, 'unit_price' => 30000, 'discount_amount' => 0]);
        $itemB = OrderItem::create(['order_id' => $order->id, 'product_id' => $this->prodB->id, 'quantity' => 1, 'unit_price' => 50000, 'discount_amount' => 0]);
        $itemC = OrderItem::create(['order_id' => $order->id, 'product_id' => $this->prodC->id, 'quantity' => 1, 'unit_price' => 70000, 'discount_amount' => 0]);

        $stockA = StockUnit::create(['product_id' => $this->prodA->id, 'seller_id' => $this->sellerA->id, 'raw_text' => 'SECRET_ALPHA_CRED_111', 'is_sold' => true, 'sold_order_id' => $order->id, 'stock_status' => 'ready']);
        $stockB = StockUnit::create(['product_id' => $this->prodB->id, 'seller_id' => $this->sellerB->id, 'raw_text' => 'SECRET_BETA_CRED_222', 'is_sold' => true, 'sold_order_id' => $order->id, 'stock_status' => 'ready']);
        $stockC = StockUnit::create(['product_id' => $this->prodC->id, 'seller_id' => $this->sellerC->id, 'raw_text' => 'SECRET_GAMMA_CRED_333', 'is_sold' => true, 'sold_order_id' => $order->id, 'stock_status' => 'ready']);

        return compact('order', 'itemA', 'itemB', 'itemC', 'stockA', 'stockB', 'stockC');
    }

    public function test_customer_order_detail_shows_separate_chat_buttons_for_all_three_sellers(): void
    {
        $ctx = $this->createMultiSellerOrder('delivered');

        $res = $this->actingAs($this->customer)->get(route('orders.show', $ctx['order']->id));
        $res->assertStatus(200);

        // Displays all 3 shop names and 3 distinct chat links
        $res->assertSee('Toko Alpha');
        $res->assertSee('Toko Beta');
        $res->assertSee('Toko Gamma');
        $res->assertSee('contact_id=' . $this->sellerA->id, false);
        $res->assertSee('contact_id=' . $this->sellerB->id, false);
        $res->assertSee('contact_id=' . $this->sellerC->id, false);
        $res->assertSee('Hubungi Penjual (3 Toko)');
    }

    public function test_seller_order_detail_strictly_isolates_credentials_items_and_blocks_cross_cancel(): void
    {
        $ctx = $this->createMultiSellerOrder('pending_payment');

        // Seller A sees ONLY Alpha product & secret, NEVER Beta or Gamma!
        $resA = $this->actingAs($this->sellerA)->get(route('seller.orders.show', $ctx['order']->id));
        $resA->assertStatus(200);
        $resA->assertSee('Produk Alpha No-Warranty');
        $resA->assertSee('SECRET_ALPHA_CRED_111');
        $resA->assertDontSee('Produk Beta 30D');
        $resA->assertDontSee('SECRET_BETA_CRED_222');
        $resA->assertDontSee('Produk Gamma 7D');
        $resA->assertDontSee('SECRET_GAMMA_CRED_333');
        $resA->assertSee('Pesanan Keranjang Gabungan:');

        // Seller A cannot cancel a shared multi-seller order
        $cancelRes = $this->actingAs($this->sellerA)->post(route('seller.orders.cancel', $ctx['order']->id));
        $cancelRes->assertSessionHas('error');
        $this->assertEquals('pending_payment', $ctx['order']->fresh()->status);
    }

    public function test_per_item_warranty_and_complaints_route_to_exact_seller_and_isolate_access(): void
    {
        $ctx = $this->createMultiSellerOrder('delivered');

        // Even though Item #1 (Product A) has 0 warranty days, customer CAN claim warranty on Item #2 (Product B, Seller B)
        $this->actingAs($this->customer)->post(route('orders.complaint', $ctx['order']->id), [
            'order_item_id' => $ctx['itemB']->id,
            'complaint_text' => 'Akun Produk Beta tidak bisa login setelah diterima.',
        ])->assertRedirect();

        $cmpB = ComplaintCase::where('order_id', $ctx['order']->id)->where('order_item_id', $ctx['itemB']->id)->firstOrFail();
        $this->assertEquals($this->prodB->id, $cmpB->product_id);
        $this->assertEquals($this->sellerB->id, $cmpB->seller_id);

        // Seller B CAN view complaint; Seller A gets 404!
        $this->actingAs($this->sellerB)->get(route('seller.complaints.show', $cmpB->id))->assertStatus(200);
        $this->actingAs($this->sellerA)->get(route('seller.complaints.show', $cmpB->id))->assertStatus(404);

        // Customer can ALSO file a second separate complaint for Item #3 (Product C, Seller C) on the same order
        $this->actingAs($this->customer)->post(route('orders.complaint', $ctx['order']->id), [
            'order_item_id' => $ctx['itemC']->id,
            'complaint_text' => 'Akun Produk Gamma terkunci password baru.',
        ])->assertRedirect();

        $this->assertEquals(2, ComplaintCase::where('order_id', $ctx['order']->id)->count());
    }

    public function test_admin_complaint_refund_is_proportional_and_supports_wallet_vs_external_manual_refund(): void
    {
        $ctx = $this->createMultiSellerOrder('delivered');

        $cmpB = ComplaintCase::create([
            'complaint_ref' => 'CMP-PROP-01',
            'customer_id' => $this->customer->id,
            'customer_telegram_id' => 0,
            'customer_username_snapshot' => $this->customer->username,
            'order_id' => $ctx['order']->id,
            'order_item_id' => $ctx['itemB']->id,
            'product_id' => $this->prodB->id,
            'seller_id' => $this->sellerB->id,
            'order_ref_snapshot' => $ctx['order']->order_ref,
            'order_created_at_snapshot' => $ctx['order']->created_at,
            'complaint_text' => 'Produk Beta rusak mohon refund',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Admin auto-refunds Item B to wallet -> credits ONLY 50,000 (not 150,000!)
        $this->actingAs($this->admin)->post(route('admin.complaints.updateStatus', $cmpB->id), [
            'status' => 'refund_wallet',
            'refund_note' => 'Refund proporsional produk Beta',
        ])->assertRedirect();

        $this->assertEquals(50000, (int) $this->customer->fresh()->customer_wallet_balance);
        $this->assertEquals('done', $cmpB->fresh()->status);

        // 2. Admin resolves Item C complaint via External Manual Transfer -> wallet stays 50,000 (NOT incremented!)
        $cmpC = ComplaintCase::create([
            'complaint_ref' => 'CMP-EXT-02',
            'customer_id' => $this->customer->id,
            'customer_telegram_id' => 0,
            'customer_username_snapshot' => $this->customer->username,
            'order_id' => $ctx['order']->id,
            'order_item_id' => $ctx['itemC']->id,
            'product_id' => $this->prodC->id,
            'seller_id' => $this->sellerC->id,
            'order_ref_snapshot' => $ctx['order']->order_ref,
            'order_created_at_snapshot' => $ctx['order']->created_at,
            'complaint_text' => 'Produk Gamma refund ke rekening BCA',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('admin.complaints.updateStatus', $cmpC->id), [
            'status' => 'refund_external',
            'refund_note' => 'Sudah ditransfer ke BCA pembeli Rp70.000',
        ])->assertRedirect();

        $this->assertEquals(50000, (int) $this->customer->fresh()->customer_wallet_balance);
        $this->assertEquals('done', $cmpC->fresh()->status);
        $this->assertNotNull($cmpC->fresh()->refund_transferred_at);
    }

    public function test_admin_order_full_refund_supports_wallet_credit_or_external_cancel_only(): void
    {
        // Order 1: Admin chooses `external` (cancel only, admin transferred outside website) -> wallet stays 0
        $ctx1 = $this->createMultiSellerOrder('delivered');
        $this->actingAs($this->admin)->post(route('admin.orders.refund', $ctx1['order']->id), [
            'refund_destination' => 'external',
        ])->assertRedirect();

        $this->assertEquals('cancelled', $ctx1['order']->fresh()->status);
        $this->assertEquals(0, (int) $this->customer->fresh()->customer_wallet_balance);

        // Order 2: Admin chooses `wallet` -> order cancelled AND wallet credited 150,000 (total - unique_code)
        $ctx2 = $this->createMultiSellerOrder('delivered');
        $this->actingAs($this->admin)->post(route('admin.orders.refund', $ctx2['order']->id), [
            'refund_destination' => 'wallet',
        ])->assertRedirect();

        $this->assertEquals('cancelled', $ctx2['order']->fresh()->status);
        $this->assertEquals(150000, (int) $this->customer->fresh()->customer_wallet_balance);
    }
}
