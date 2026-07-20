<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\GithubCheckBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserToolAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear session between tests
        session()->forget(['github_cookie', 'github_cookie_valid', 'github_cookie_user']);
    }

    public function test_admin_can_assign_tools_to_seller()
    {
        $admin = User::forceCreate([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $seller = User::forceCreate([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $seller->id), [
            'role' => 'seller',
            'wallet_balance' => 100000,
            'platform_fee_percent' => 15,
            'seller_save_hours' => 24,
            'allowed_tools' => ['github_checker', 'gmail_checker']
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $seller->id,
            'allowed_tools' => json_encode(['github_checker', 'gmail_checker'])
        ]);
    }

    public function test_seller_with_access_can_open_checker()
    {
        $seller = User::forceCreate([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'allowed_tools' => ['github_checker'],
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($seller)->get(route('admin.tools.github-checker'));
        $response->assertStatus(200);

        // Accessing gmail checker should be blocked
        $response2 = $this->actingAs($seller)->get(route('admin.tools.gmail-checker'));
        $response2->assertRedirect(route('dashboard'));
    }

    public function test_seller_without_access_is_blocked()
    {
        $seller = User::forceCreate([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'allowed_tools' => [],
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($seller)->get(route('admin.tools.github-checker'));
        $response->assertRedirect(route('dashboard'));
    }

    public function test_seller_only_loads_own_stock_and_products()
    {
        $seller1 = User::forceCreate([
            'username' => 'seller1',
            'full_name' => 'Seller 1',
            'email' => 'seller1@test.com',
            'role' => 'seller',
            'allowed_tools' => ['github_checker'],
            'password' => bcrypt('password'),
        ]);

        $seller2 = User::forceCreate([
            'username' => 'seller2',
            'full_name' => 'Seller 2',
            'email' => 'seller2@test.com',
            'role' => 'seller',
            'allowed_tools' => ['github_checker'],
            'password' => bcrypt('password'),
        ]);

        $product1 = Product::create(['name' => 'Product 1', 'price' => 100, 'creator_id' => $seller1->id]);
        $product2 = Product::create(['name' => 'Product 2', 'price' => 200, 'creator_id' => $seller2->id]);

        $stock1 = StockUnit::create([
            'product_id' => $product1->id,
            'raw_text' => "Username: seller1_user\nPassword: 123",
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller1->id
        ]);

        $stock2 = StockUnit::create([
            'product_id' => $product2->id,
            'raw_text' => "Username: seller2_user\nPassword: 123",
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller2->id
        ]);

        // Seller 1 accesses loader for Product 1 (success)
        $response1 = $this->actingAs($seller1)->post(route('admin.tools.github-checker.load-stock'), [
            'product_id' => $product1->id
        ]);
        $response1->assertStatus(200);
        $this->assertCount(1, $response1->json('usernames'));
        $this->assertEquals('seller1_user', $response1->json('usernames.0.username'));

        // Seller 1 accesses loader for Product 2 (should return 0 usernames because the stock belongs to seller 2)
        $response2 = $this->actingAs($seller1)->post(route('admin.tools.github-checker.load-stock'), [
            'product_id' => $product2->id
        ]);
        $response2->assertStatus(200);
        $this->assertCount(0, $response2->json('usernames'));
    }

    public function test_admin_can_export_users()
    {
        $admin = User::forceCreate([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.export'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_admin_can_export_stock()
    {
        $admin = User::forceCreate([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create(['name' => 'Product Test', 'price' => 10000]);
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Some stock data',
            'is_sold' => false,
            'stock_status' => 'ready'
        ]);

        $response = $this->actingAs($admin)->get(route('admin.stock.export'));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_non_admin_cannot_update_status_to_awaiting_benefits_github()
    {
        $seller = User::forceCreate([
            'username' => 'seller_test_bulk_gh',
            'full_name' => 'Seller Test',
            'email' => 'seller_bulk_gh@test.com',
            'role' => 'seller',
            'allowed_tools' => ['github_checker'],
            'password' => bcrypt('password'),
        ]);

        $product = Product::create(['name' => 'Product GH', 'price' => 100, 'creator_id' => $seller->id]);

        $stock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => "Username: seller_gh_user\nPassword: 123",
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller->id
        ]);

        $response = $this->actingAs($seller)->post(route('admin.tools.github-checker.bulk-update-stock'), [
            'stock_ids' => [$stock->id],
            'stock_status' => 'awaiting_benefits'
        ]);

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
        $this->assertEquals('ready', $stock->fresh()->stock_status);
    }
}
