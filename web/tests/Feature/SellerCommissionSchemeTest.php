<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SellerCommissionSchemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 123]], 200),
        ]);
    }

    public function test_commission_calculated_after_coupon_discount_applied_on_seller_product()
    {
        $seller = User::forceCreate([
            'username' => 'mitraseller',
            'full_name' => 'Mitra Seller 1',
            'email' => 'seller1@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'seller',
            'wallet_balance' => 0,
            'platform_fee_percent' => 10, // 10%
            'commission_per_product' => false,
        ]);

        $customer = User::forceCreate([
            'username' => 'buyer1',
            'full_name' => 'Buyer One',
            'email' => 'buyer1@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Akun Premium Pro',
            'price' => 100000, // Rp 100.000
            'creator_id' => $seller->id,
            'warranty_days' => 0,
        ]);

        // Kupon diskon Rp 20.000
        $discountAmount = 20000;
        $order = Order::create([
            'order_ref' => 'ORD-COMM-COUPON-1',
            'customer_id' => $customer->id,
            'subtotal' => 100000,
            'coupon_code' => 'SELLERPROMO',
            'discount_amount' => $discountAmount,
            'unique_code' => 50,
            'total_amount' => (100000 - $discountAmount) + 50,
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'discount_amount' => $discountAmount,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_ref' => 'PAY-COMM-1',
            'expected_amount' => $order->total_amount,
            'status' => 'pending',
        ]);

        $stock = StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'raw_text' => 'user:pass_premium',
            'is_sold' => false,
            'stock_status' => 'reserved_checkout',
            'sold_order_id' => $order->id,
        ]);

        // Konfirmasi pembayaran
        $orderService = app(OrderService::class);
        $orderService->confirmPayment($order, 'TEST_PAYMENT');

        $seller->refresh();

        // Perhitungan yang diharapkan:
        // Harga setelah kupon: 100.000 - 20.000 = 80.000
        // Komisi platform 10%: 80.000 * 10% = 8.000
        // Saldo bersih seller: 80.000 - 8.000 = 72.000
        $this->assertEquals(72000, $seller->wallet_balance);
        $this->assertEquals('delivered', $order->fresh()->status);
    }

    public function test_per_product_commission_toggle_applies_custom_product_fee()
    {
        // Seller mengaktifkan toggle commission_per_product
        $seller = User::forceCreate([
            'username' => 'gadgetseller',
            'full_name' => 'Gadget Seller',
            'email' => 'gadget@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'seller',
            'wallet_balance' => 0,
            'platform_fee_percent' => 20, // Default seller 20%
            'commission_per_product' => true, // Mode per-produk aktif!
        ]);

        $customer = User::forceCreate([
            'username' => 'buyer2',
            'full_name' => 'Buyer Two',
            'email' => 'buyer2@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        // Produk dengan komisi khusus 5% (bukan 20%)
        $product = Product::create([
            'name' => 'Voucher Margin Tipis',
            'price' => 200000,
            'platform_fee_percent' => 5, // 5% khusus produk ini
            'creator_id' => $seller->id,
            'warranty_days' => 0,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-PER-PRODUCT-1',
            'customer_id' => $customer->id,
            'subtotal' => 200000,
            'coupon_code' => null,
            'discount_amount' => 0,
            'unique_code' => 25,
            'total_amount' => 200025,
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 200000,
            'discount_amount' => 0,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_ref' => 'PAY-COMM-2',
            'expected_amount' => $order->total_amount,
            'status' => 'pending',
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'raw_text' => 'VOUCHER-CODE-XYZ',
            'is_sold' => false,
            'stock_status' => 'reserved_checkout',
            'sold_order_id' => $order->id,
        ]);

        $orderService = app(OrderService::class);
        $orderService->confirmPayment($order, 'TEST_PAYMENT');

        $seller->refresh();

        // Perhitungan:
        // Komisi 5% (dari produk, bukan 20% seller): 200.000 * 5% = 10.000
        // Saldo bersih seller: 200.000 - 10.000 = 190.000
        $this->assertEquals(190000, $seller->wallet_balance);
    }

    public function test_per_product_commission_with_coupon_discount_combined()
    {
        // Seller per-produk aktif, produk komisi 15%, dan ada kupon diskon Rp 50.000
        $seller = User::forceCreate([
            'username' => 'comboseller',
            'full_name' => 'Combo Seller',
            'email' => 'combo@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'seller',
            'wallet_balance' => 0,
            'platform_fee_percent' => 25,
            'commission_per_product' => true,
        ]);

        $customer = User::forceCreate([
            'username' => 'buyer3',
            'full_name' => 'Buyer Three',
            'email' => 'buyer3@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Paket Belajar Digital',
            'price' => 200000,
            'platform_fee_percent' => 15, // 15%
            'creator_id' => $seller->id,
            'warranty_days' => 0,
        ]);

        $discount = 50000;
        $order = Order::create([
            'order_ref' => 'ORD-COMBO-1',
            'customer_id' => $customer->id,
            'subtotal' => 200000,
            'coupon_code' => 'HEMAT50K',
            'discount_amount' => $discount,
            'unique_code' => 10,
            'total_amount' => 150010,
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 200000,
            'discount_amount' => $discount,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_ref' => 'PAY-COMBO-1',
            'expected_amount' => $order->total_amount,
            'status' => 'pending',
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'raw_text' => 'AKUN-COMBO-123',
            'is_sold' => false,
            'stock_status' => 'reserved_checkout',
            'sold_order_id' => $order->id,
        ]);

        $orderService = app(OrderService::class);
        $orderService->confirmPayment($order, 'TEST_PAYMENT');

        $seller->refresh();

        // Perhitungan:
        // Harga setelah kupon: 200.000 - 50.000 = 150.000
        // Komisi 15% per produk: 150.000 * 15% = 22.500
        // Saldo bersih seller: 150.000 - 22.500 = 127.500
        $this->assertEquals(127500, $seller->wallet_balance);
    }

    public function test_admin_can_update_seller_commission_toggle_and_product_fee()
    {
        $admin = User::forceCreate([
            'username' => 'adminutama',
            'full_name' => 'Admin Utama',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $seller = User::forceCreate([
            'username' => 'sellerupdate',
            'full_name' => 'Seller Update Test',
            'email' => 'sellerup@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'wallet_balance' => 50000,
            'platform_fee_percent' => 10,
            'commission_per_product' => false,
            'seller_save_hours' => 24,
        ]);

        // 1. Admin update seller toggle commission_per_product
        $response = $this->actingAs($admin)
            ->from(route('admin.sellers.index'))
            ->put(route('admin.users.update', $seller->id), [
                'role' => 'seller',
                'wallet_balance' => 50000,
                'platform_fee_percent' => 12,
                'commission_per_product' => 1,
                'seller_save_hours' => 48,
            ]);

        $response->assertSessionHas('success');
        $seller->refresh();
        $this->assertTrue((bool)$seller->commission_per_product);
        $this->assertEquals(12, $seller->platform_fee_percent);
        $this->assertEquals(48, $seller->seller_save_hours);

        // 2. Admin create & update product platform_fee_percent
        $product = Product::create([
            'name' => 'Produk Admin Test',
            'price' => 50000,
            'creator_id' => $seller->id,
            'platform_fee_percent' => null,
        ]);

        $updateProductResponse = $this->actingAs($admin)->put(route('admin.products.update', $product->id), [
            'name' => 'Produk Admin Test Updated',
            'price' => 60000,
            'platform_fee_percent' => 8,
            'description' => 'Deskripsi update',
        ]);

        $updateProductResponse->assertRedirect(route('admin.products.index'));
        $product->refresh();
        $this->assertEquals(8, $product->platform_fee_percent);
        $this->assertEquals(60000, $product->price);
    }
}
