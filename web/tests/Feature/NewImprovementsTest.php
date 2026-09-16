<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockUnit;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $seller;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'username' => 'adminuser',
            'full_name' => 'Admin Utama',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'telegram_id' => 111111,
            'password' => bcrypt('password'),
        ]);

        $this->seller = User::forceCreate([
            'username' => 'sellershop',
            'full_name' => 'Super Seller Store',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'platform_fee_percent' => 10,
            'telegram_id' => 222222,
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::forceCreate([
            'username' => 'customerone',
            'full_name' => 'Customer One',
            'email' => 'customer@test.com',
            'role' => 'customer',
            'telegram_id' => 333333,
            'password' => bcrypt('password'),
        ]);
    }

    public function test_catalog_show_renders_seller_profile_and_custom_warranty_svg(): void
    {
        $product = Product::create([
            'name' => 'Premium Account Lifetime',
            'price' => 50000,
            'description' => 'Lifetime warranty account',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
            'warranty_days' => 30,
        ]);

        $response = $this->actingAs($this->customer)->get(route('catalog.show', $product->id));

        $response->assertStatus(200);
        // Garansi SVG / 100% check
        $response->assertSee('Garansi 100%');
        $response->assertSee('Jaminan uang kembali');
        // Verified badge and seller join info
        $response->assertSee('Super Seller Store');
        $response->assertSee('Bergabung');
        $response->assertSee(route('sellers.show', $this->seller->id));
        $response->assertSee(route('chat.index', ['contact_id' => $this->seller->id]));
    }

    public function test_seller_profile_page_displays_seller_information_and_products(): void
    {
        $product1 = Product::create([
            'name' => 'Seller Game Key',
            'price' => 75000,
            'description' => 'Original key',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        StockUnit::create([
            'product_id' => $product1->id,
            'raw_text' => 'key12345',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $this->seller->id,
            'uploaded_by_id' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->customer)->get(route('sellers.show', $this->seller->id));

        $response->assertStatus(200);
        $response->assertSee('Super Seller Store');
        $response->assertSee('Profil Seller');
        $response->assertSee('Seller Game Key');
        $response->assertSee('Rp 75.000');
        $response->assertSee(route('chat.index', ['contact_id' => $this->seller->id]));
    }

    public function test_admin_can_upload_product_image_and_update(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('netflix.jpg', 400, 400);

        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'name' => 'Netflix 4K HDR',
            'price' => 45000,
            'description' => 'Akun Netflix 4K UHD',
            'image' => $file,
        ]);

        $response->assertRedirect(route('admin.products.index'));

        $product = Product::where('name', 'Netflix 4K HDR')->first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
        $this->assertStringContainsString('storage/' . $product->image, $product->image_url);

        // Edit / update with new image
        $newFile = UploadedFile::fake()->image('netflix_new.png', 400, 400);
        $oldPath = $product->image;

        $updateResponse = $this->actingAs($this->admin)->put(route('admin.products.update', $product->id), [
            'name' => 'Netflix 4K HDR Updated',
            'price' => 48000,
            'description' => 'Deskripsi baru',
            'image' => $newFile,
        ]);

        $updateResponse->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertEquals('Netflix 4K HDR Updated', $product->name);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_seller_can_create_product_with_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('canva.webp', 300, 300);

        $response = $this->actingAs($this->seller)->post(route('seller.products.store'), [
            'name' => 'Canva Pro Edu',
            'price' => 20000,
            'description' => 'Canva Pro Edu 1 Tahun',
            'image' => $file,
        ]);

        $response->assertRedirect(route('seller.products.index'));

        $product = Product::where('name', 'Canva Pro Edu')->first();
        $this->assertNotNull($product);
        $this->assertEquals($this->seller->id, $product->creator_id);
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_admin_dashboard_supports_all_time_period(): void
    {
        // Order delivered 400 days ago
        Order::create([
            'order_ref' => 'REF-HISTORIC-001',
            'customer_id' => $this->customer->id,
            'subtotal' => 200000,
            'unique_code' => 0,
            'total_amount' => 200000,
            'status' => 'delivered',
            'delivered_at' => now()->subDays(400),
            'created_at' => now()->subDays(400),
        ]);

        // Order delivered today
        Order::create([
            'order_ref' => 'REF-TODAY-001',
            'customer_id' => $this->customer->id,
            'subtotal' => 100000,
            'unique_code' => 0,
            'total_amount' => 100000,
            'status' => 'delivered',
            'delivered_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard', ['period' => 'all_time']));

        $response->assertStatus(200);
        $response->assertSee('Sepanjang waktu');
        $response->assertViewHas('period', 'all_time');
        // Total revenue across all time is 200,000 + 100,000 = 300,000
        $response->assertSee('300.000');
    }

    public function test_seller_dashboard_supports_all_time_days_filter(): void
    {
        $product = Product::create([
            'name' => 'Seller Item All Time',
            'price' => 150000,
            'description' => 'Item',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        // Order delivered 200 days ago
        $oldOrder = Order::create([
            'order_ref' => 'REF-SELLER-OLD',
            'customer_id' => $this->customer->id,
            'subtotal' => 150000,
            'unique_code' => 0,
            'total_amount' => 150000,
            'status' => 'delivered',
            'delivered_at' => now()->subDays(200),
            'created_at' => now()->subDays(200),
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'credentials-old',
            'is_sold' => true,
            'sold_order_id' => $oldOrder->id,
            'stock_status' => 'ready',
            'seller_id' => $this->seller->id,
            'uploaded_by_id' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->seller)->get(route('seller.dashboard', ['days' => 'all']));

        $response->assertStatus(200);
        $response->assertViewHas('days', 'all');
        $response->assertSee('Sepanjang Waktu');
        $response->assertSee('150.000');
    }

    public function test_seller_profile_shows_admin_products_with_null_creator_id(): void
    {
        // Admin product with creator_id = null
        $adminProductNull = Product::create([
            'name' => 'Admin Exclusive Product Null Creator',
            'price' => 250000,
            'description' => 'Admin exclusive null creator item',
            'creator_id' => null,
            'is_suspended' => false,
        ]);

        // Admin product with creator_id = admin id
        $adminProductExplicit = Product::create([
            'name' => 'Admin Explicit Creator Product',
            'price' => 350000,
            'description' => 'Admin explicit item',
            'creator_id' => $this->admin->id,
            'is_suspended' => false,
        ]);

        $response = $this->actingAs($this->customer)->get(route('sellers.show', $this->admin->id));

        $response->assertStatus(200);
        $response->assertSee('Admin Exclusive Product Null Creator');
        $response->assertSee('Admin Explicit Creator Product');
        $response->assertSee('Official Store Admin');
        $this->assertStringNotContainsString('fas fa-shopping-cart text-primary', $response->getContent());
    }

    public function test_admin_sellers_page_has_visit_profile_links(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.sellers.index'));

        $response->assertStatus(200);
        $response->assertSee('Kunjungi Profil Toko');
        $response->assertSee(route('sellers.show', $this->seller->id));
        $response->assertSee(route('sellers.show', $this->admin->id));
    }

    public function test_seller_profile_zero_reviews_shows_no_review_state(): void
    {
        $response = $this->actingAs($this->customer)->get(route('sellers.show', $this->seller->id));

        $response->assertStatus(200);
        $response->assertSee('Belum ada ulasan');
        $response->assertSee('badge bg-white text-secondary rounded-pill', false);
    }

    public function test_catalog_index_has_seller_store_profile_link(): void
    {
        Product::create([
            'name' => 'Linked Seller Product Demo',
            'price' => 50000,
            'description' => 'Demo',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        $response = $this->actingAs($this->customer)->get(route('catalog.index'));

        $response->assertStatus(200);
        $response->assertSee(route('sellers.show', $this->seller->id));
    }

    public function test_checkout_review_page_loads_correctly(): void
    {
        $product = Product::create([
            'name' => 'Review Test Product',
            'price' => 100000,
            'description' => 'Test Desc',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'key999',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $this->seller->id,
            'uploaded_by_id' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->customer)->get(route('checkout.review', ['product' => $product->id, 'quantity' => 1]));

        $response->assertStatus(200);
        $response->assertSee('Review &amp; Pembayaran', false);
        $response->assertSee('Review Test Product');
        $response->assertSee('Rp 100.000');
        $response->assertSee('Gunakan Kode Promo / Kupon');
    }

    public function test_guest_can_access_dashboard_and_catalog_pages(): void
    {
        // 1. Guest can access dashboard
        $dashResponse = $this->get(route('dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Tamu (Guest)');
        $dashResponse->assertSee('guestAuthModal');

        // 2. Guest can access catalog index
        $catalogResponse = $this->get(route('catalog.index'));
        $catalogResponse->assertStatus(200);

        // 3. Guest can access seller profile
        $sellerResponse = $this->get(route('sellers.show', $this->seller->id));
        $sellerResponse->assertStatus(200);
        $sellerResponse->assertSee('Super Seller Store');
    }

    public function test_guest_checkout_redirects_to_login(): void
    {
        $product = Product::create([
            'name' => 'Guest Check Product',
            'price' => 50000,
            'description' => 'Test Desc',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        $response = $this->get(route('checkout.review', ['product' => $product->id]));
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_stat_cards_are_clickable_links(): void
    {
        $response = $this->actingAs($this->customer)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('orders.index'));
        $response->assertSee(route('orders.index', ['status' => 'pending_payment']));
        $response->assertSee(route('orders.index', ['status' => 'delivered']));
    }

    public function test_orders_index_renders_ecommerce_cards_with_quick_actions(): void
    {
        $product = Product::create([
            'name' => 'Ecommerce Card Product',
            'price' => 25000,
            'description' => 'Test Item',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        $order = Order::create([
            'customer_id' => $this->customer->id,
            'order_ref' => 'REF-' . time(),
            'subtotal' => 25000,
            'unique_code' => 123,
            'total_amount' => 25123,
            'status' => 'pending_payment',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
        ]);

        $response = $this->actingAs($this->customer)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('order-transaction-card');
        $response->assertSee($order->reference);
        $response->assertSee('Super Seller Store');
        $response->assertSee('Bayar Sekarang');
        $response->assertSee(route('checkout.success', ['order_ref' => $order->order_ref]));
    }

    public function test_admin_broadcast_generates_database_notifications_for_all_users(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.broadcast.start'), [
            'message' => 'Pengumuman promo diskon 50% untuk semua member!'
        ]);

        $response->assertStatus(200);

        // Verify customer has received database notification
        $this->customer->refresh();
        $this->assertGreaterThan(0, $this->customer->notifications()->count());
        $notification = $this->customer->notifications()->first();
        $this->assertEquals('broadcast_announcement', $notification->data['type']);
        $this->assertStringContainsString('Pengumuman promo diskon 50%', $notification->data['message']);
    }

    public function test_notifications_compact_view_renders(): void
    {
        $this->customer->notify(new \App\Notifications\SystemEventNotification('Info Update', 'Sistem berhasil diperbarui'));

        $response = $this->actingAs($this->customer)->get(route('notifications.index'));

        $response->assertStatus(200);
        $response->assertSee('Info Update');
        $response->assertSee('Sistem berhasil diperbarui');
    }

    public function test_customer_orders_search_by_product_name_reference_and_nominal(): void
    {
        $prod1 = Product::create([
            'name' => 'Canva Pro Edu 1 Tahun',
            'price' => 35000,
            'description' => 'Canva',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        $prod2 = Product::create([
            'name' => 'Netflix Premium 4K UHD',
            'price' => 50000,
            'description' => 'Netflix',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        $order1 = Order::create([
            'customer_id' => $this->customer->id,
            'order_ref' => 'REF-CANVA-111',
            'subtotal' => 35000,
            'unique_code' => 55,
            'total_amount' => 35055,
            'status' => 'pending_payment',
        ]);
        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $prod1->id,
            'quantity' => 1,
            'unit_price' => 35000,
            'subtotal' => 35000,
        ]);

        $order2 = Order::create([
            'customer_id' => $this->customer->id,
            'order_ref' => 'REF-NETFLIX-222',
            'subtotal' => 50000,
            'unique_code' => 88,
            'total_amount' => 50088,
            'status' => 'delivered',
        ]);
        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $prod2->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'subtotal' => 50000,
        ]);

        // Search by product name 'Canva'
        $resName = $this->actingAs($this->customer)->get(route('orders.index', ['search' => 'Canva']));
        $resName->assertStatus(200);
        $resName->assertSee('REF-CANVA-111');
        $resName->assertDontSee('REF-NETFLIX-222');

        // Search by order reference
        $resRef = $this->actingAs($this->customer)->get(route('orders.index', ['search' => 'NETFLIX-222']));
        $resRef->assertStatus(200);
        $resRef->assertSee('REF-NETFLIX-222');
        $resRef->assertDontSee('REF-CANVA-111');

        // Search by formatted nominal '35.000' or '35055'
        $resNominal = $this->actingAs($this->customer)->get(route('orders.index', ['search' => '35.055']));
        $resNominal->assertStatus(200);
        $resNominal->assertSee('REF-CANVA-111');
        $resNominal->assertDontSee('REF-NETFLIX-222');

        // Test simplified filter 'cancelled_expired'
        $order3 = Order::create([
            'customer_id' => $this->customer->id,
            'order_ref' => 'REF-EXPIRED-333',
            'subtotal' => 10000,
            'unique_code' => 1,
            'total_amount' => 10001,
            'status' => 'expired',
        ]);
        OrderItem::create([
            'order_id' => $order3->id,
            'product_id' => $prod1->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ]);

        $resFilter = $this->actingAs($this->customer)->get(route('orders.index', ['status' => 'cancelled_expired']));
        $resFilter->assertStatus(200);
        $resFilter->assertSee('REF-EXPIRED-333');
        $resFilter->assertDontSee('REF-CANVA-111');
    }

    public function test_seller_orders_show_and_index(): void
    {
        $product = Product::create([
            'name' => 'Seller Stock Item',
            'price' => 60000,
            'description' => 'Test Item',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        $order = Order::create([
            'customer_id' => $this->customer->id,
            'order_ref' => 'REF-SELLER-999',
            'subtotal' => 60000,
            'unique_code' => 99,
            'total_amount' => 60099,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 60000,
            'subtotal' => 60000,
        ]);

        $stock = StockUnit::create([
            'product_id' => $product->id,
            'sold_order_id' => $order->id,
            'raw_text' => 'email:pass|seller-test',
            'is_sold' => true,
            'stock_status' => 'sold',
            'seller_id' => $this->seller->id,
            'uploaded_by_id' => $this->seller->id,
        ]);

        // Seller can access dedicated show page
        $response = $this->actingAs($this->seller)->get(route('seller.orders.show', $order->id));
        $response->assertStatus(200);
        $response->assertSee('REF-SELLER-999');
        $response->assertSee('Customer One');
        $response->assertSee('Seller Stock Item');
        $response->assertSee('email:pass|seller-test');

        // Other seller cannot access this order
        $otherSeller = User::forceCreate([
            'username' => 'otherseller',
            'full_name' => 'Other Seller',
            'email' => 'other@test.com',
            'role' => 'seller',
            'telegram_id' => 888888,
            'password' => bcrypt('password'),
        ]);
        $forbidden = $this->actingAs($otherSeller)->get(route('seller.orders.show', $order->id));
        $forbidden->assertStatus(404);
    }

    public function test_admin_orders_show_renders_complete_order_management(): void
    {
        $product = Product::create([
            'name' => 'Admin Managed Item',
            'price' => 80000,
            'description' => 'Test Item',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        $order = Order::create([
            'customer_id' => $this->customer->id,
            'order_ref' => 'REF-ADMIN-777',
            'subtotal' => 80000,
            'unique_code' => 11,
            'total_amount' => 80011,
            'status' => 'pending_payment',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 80000,
            'subtotal' => 80000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.show', $order->id));
        $response->assertStatus(200);
        $response->assertSee('REF-ADMIN-777');
        $response->assertSee('Detail Pesanan (Admin)');
        $response->assertSee('Customer One');
        $response->assertSee('Super Seller Store');
        $response->assertSee('Terima Pembayaran');
        $response->assertSee('Tolak Pesanan');
        $response->assertSee('Ubah Status');
    }

    public function test_catalog_show_renders_sticky_mobile_action_bar_and_retains_bottom_nav(): void
    {
        $product = Product::create([
            'name' => 'Sticky Bar Test Item',
            'price' => 25000,
            'description' => 'Testing mobile sticky purchase bar',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'sample_credentials_123',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $this->seller->id,
            'uploaded_by_id' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->customer)->get(route('catalog.show', $product->id));

        $response->assertStatus(200);

        // Sticky action bar elements must be present
        $response->assertSee('mobile-sticky-action-bar');
        $response->assertSee('btnMobileBuyNow');
        $response->assertSee('btnMobileAddToCart');
        $response->assertSee(route('chat.index', ['contact_id' => $this->seller->id]));

        // Global mobileBottomNav must also be present (stacked below the sticky action bar)
        $response->assertSee('id="mobileBottomNav"', false);
    }

    public function test_catalog_show_out_of_stock_renders_sticky_bar_with_chat_and_disabled_button(): void
    {
        $product = Product::create([
            'name' => 'Out of Stock Item',
            'price' => 30000,
            'description' => 'Sold out item',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        $response = $this->actingAs($this->customer)->get(route('catalog.show', $product->id));

        $response->assertStatus(200);

        // Sticky bar still present
        $response->assertSee('mobile-sticky-action-bar');
        $response->assertSee('Stok Habis');
        // Chat seller is still available
        $response->assertSee(route('chat.index', ['contact_id' => $this->seller->id]));
        // Bottom nav must also be present
        $response->assertSee('id="mobileBottomNav"', false);
    }

    public function test_checkout_review_renders_sticky_mobile_action_bar_and_bottom_nav(): void
    {
        $product = Product::create([
            'name' => 'Review Checkout Item',
            'price' => 50000,
            'description' => 'Checkout review test',
            'creator_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'checkout_item_key_999',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $this->seller->id,
            'uploaded_by_id' => $this->seller->id,
        ]);

        $response = $this->actingAs($this->customer)->get(route('checkout.review', [
            'product' => $product->id,
            'quantity' => 1,
        ]));

        $response->assertStatus(200);

        // Sticky action bar for checkout review must be present
        $response->assertSee('mobile-sticky-action-bar');
        $response->assertSee('Total Pembayaran');
        $response->assertSee('Bayar Sekarang');
        $response->assertSee('btn-buy-now');
        $response->assertSee('btn-buy-icon');
        $response->assertSee('btn-buy-text');
        $response->assertSee('id="btnMobilePayNow"', false);

        // Mobile bottom nav must also be present
        $response->assertSee('id="mobileBottomNav"', false);
    }
}


