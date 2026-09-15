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
}
