<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\Order;
use App\Models\CartItem;
use App\Models\BotSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VccProductFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_vcc_product_creation_and_stock_assignment_flow()
    {
        $admin = User::forceCreate([
            'full_name' => 'Admin VCC',
            'username' => 'adminvcc',
            'email' => 'adminvcc@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        // 1. Simpan pengaturan list master VCC
        BotSetting::updateOrCreate(
            ['key' => 'vcc_services_list'],
            ['value' => json_encode(['Shopee', 'Lazada', 'DigitalOcean', 'Linode', 'Vultr'])]
        );

        // 2. Buat Produk VCC
        $response = $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'VCC 16 Digit Multi Platform',
            'price' => 50000,
            'description' => 'VCC saldo aktif siap pakai',
            'is_vcc' => '1',
            'vcc_services' => ['Shopee', 'Lazada', 'DigitalOcean'],
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::where('name', 'VCC 16 Digit Multi Platform')->first();
        $this->assertNotNull($product);
        $this->assertTrue((bool)$product->is_vcc);
        $this->assertEquals(['Shopee', 'Lazada', 'DigitalOcean'], $product->vcc_services);

        // 3. Admin Input 1 Data Kartu VCC -> Sistem mengalokasikan 3 Stok Unit (1 per layanan)
        $stockInput = "4111111111111111|12|28|123";
        $stockResp = $this->actingAs($admin)->post(route('admin.stock.store'), [
            'product_id' => $product->id,
            'raw_text' => $stockInput,
            'stock_status' => 'ready',
        ]);
        $stockResp->assertRedirect();

        // Verifikasi terbuat 3 unit stok
        $units = StockUnit::where('product_id', $product->id)->get();
        $this->assertCount(3, $units);

        $servicesAssigned = $units->pluck('vcc_service')->sort()->values()->toArray();
        $this->assertEquals(['DigitalOcean', 'Lazada', 'Shopee'], $servicesAssigned);

        // Verifikasi group_id sama untuk 1 kartu fisik VCC tersebut
        $groupIds = $units->pluck('vcc_group_id')->unique();
        $this->assertCount(1, $groupIds);

        // 4. Test Endpoint VCC Info
        $infoResp = $this->getJson(route('catalog.vcc-info', $product->id));
        $infoResp->assertOk();
        $infoResp->assertJsonStructure([
            'success',
            'product' => [
                'id',
                'name',
                'price',
                'formatted_price',
                'services' => [
                    '*' => ['name', 'stock', 'available']
                ]
            ]
        ]);
        $servicesData = $infoResp->json('product.services');
        $this->assertCount(3, $servicesData);
        foreach ($servicesData as $sd) {
            $this->assertEquals(1, $sd['stock']);
            $this->assertTrue($sd['available']);
        }
    }

    public function test_customer_cart_and_checkout_vcc_flow()
    {
        $customer = User::forceCreate([
            'full_name' => 'Customer VCC',
            'username' => 'customervcc',
            'email' => 'customervcc@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'VCC Digital Ocean & Shopee',
            'price' => 75000,
            'is_vcc' => true,
            'vcc_services' => ['Shopee', 'DigitalOcean'],
            'is_suspended' => false,
        ]);

        // Input 2 unit stok (1 Shopee, 1 DigitalOcean)
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => '4111111111111111|12|28|123',
            'vcc_service' => 'Shopee',
            'vcc_group_id' => 'vcc_grp_1',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => '4111111111111111|12|28|123',
            'vcc_service' => 'DigitalOcean',
            'vcc_group_id' => 'vcc_grp_1',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);

        // 1. Customer Tambahkan VCC ke Keranjang dengan memilih layanan Shopee & DigitalOcean
        $cartResp = $this->actingAs($customer)->postJson(route('cart.add', $product->id), [
            'vcc_services' => ['Shopee', 'DigitalOcean']
        ]);
        $cartResp->assertOk();
        $cartResp->assertJson(['success' => true]);

        $cartItem = CartItem::where('user_id', $customer->id)->where('product_id', $product->id)->first();
        $this->assertNotNull($cartItem);
        $this->assertEquals(2, $cartItem->quantity);
        $this->assertEquals(['Shopee', 'DigitalOcean'], $cartItem->vcc_services);

        // 2. Customer Checkout Keranjang Belanja
        $checkoutResp = $this->actingAs($customer)->post(route('cart.process'));
        $checkoutResp->assertRedirect();

        $order = Order::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals(150000, $order->subtotal);

        // Verifikasi StockUnit ter-reserve untuk masing-masing layanan yang dipilih
        $reservedUnits = StockUnit::where('sold_order_id', $order->id)->get();
        $this->assertCount(2, $reservedUnits);
        $this->assertEquals(['DigitalOcean', 'Shopee'], $reservedUnits->pluck('vcc_service')->sort()->values()->toArray());
        foreach ($reservedUnits as $u) {
            $this->assertEquals('reserved_checkout', $u->stock_status);
        }

        // 3. Customer Buka Halaman Rincian Pesanan
        $orderViewResp = $this->actingAs($customer)->get(route('orders.show', $order->id));
        $orderViewResp->assertOk();
        $orderViewResp->assertSee('Shopee');
        $orderViewResp->assertSee('DigitalOcean');
    }

    public function test_customer_direct_checkout_vcc_flow()
    {
        $customer = User::forceCreate([
            'full_name' => 'Direct Cust',
            'username' => 'directcust',
            'email' => 'directcust@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'VCC Cloud VPS 3 Platform',
            'price' => 100000,
            'is_vcc' => true,
            'vcc_services' => ['Linode', 'Vultr', 'DigitalOcean'],
            'is_suspended' => false,
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => '5123456789012345|10|29|999',
            'vcc_service' => 'Linode',
            'vcc_group_id' => 'vcc_grp_2',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => '5123456789012345|10|29|999',
            'vcc_service' => 'Vultr',
            'vcc_group_id' => 'vcc_grp_2',
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);

        // Review Checkout langsung
        $reviewResp = $this->actingAs($customer)->get(route('checkout.review', [
            'product' => $product->id,
            'vcc_services' => ['Linode', 'Vultr']
        ]));
        $reviewResp->assertOk();
        $reviewResp->assertSee('Linode');
        $reviewResp->assertSee('Vultr');

        // Store Checkout
        $storeResp = $this->actingAs($customer)->post(route('checkout.store', $product->id), [
            'vcc_services' => ['Linode', 'Vultr']
        ]);
        $storeResp->assertRedirect();

        $order = Order::where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals(200000, $order->subtotal);

        // Verifikasi StockUnit dialokasikan ke Linode & Vultr
        $reservedUnits = StockUnit::where('sold_order_id', $order->id)->get();
        $this->assertCount(2, $reservedUnits);
        $this->assertEquals(['Linode', 'Vultr'], $reservedUnits->pluck('vcc_service')->sort()->values()->toArray());
    }
}
