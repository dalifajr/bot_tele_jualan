<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_stock_page_metrics_are_responsive_to_filters()
    {
        $admin = User::forceCreate([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $product1 = Product::create(['name' => 'Product 1', 'price' => 100]);
        $product2 = Product::create(['name' => 'Product 2', 'price' => 200]);

        // Stock 1 for Product 1 (ready)
        StockUnit::create([
            'product_id' => $product1->id,
            'raw_text' => 'Ready stock product 1',
            'is_sold' => false,
            'stock_status' => 'ready'
        ]);

        // Stock 2 for Product 1 (sold)
        StockUnit::create([
            'product_id' => $product1->id,
            'raw_text' => 'Sold stock product 1',
            'is_sold' => true,
            'stock_status' => 'ready'
        ]);

        // Stock 3 for Product 2 (ready)
        StockUnit::create([
            'product_id' => $product2->id,
            'raw_text' => 'Ready stock product 2',
            'is_sold' => false,
            'stock_status' => 'ready'
        ]);

        // No filters: should return all stocks
        $response = $this->actingAs($admin)->get(route('admin.stock.index'));
        $response->assertStatus(200);
        $response->assertViewHas('totalStock', 3);
        $response->assertViewHas('readyStock', 2);
        $response->assertViewHas('soldStock', 1);

        // Filtered by Product 1: metrics should adjust
        $responseFiltered = $this->actingAs($admin)->get(route('admin.stock.index', ['product_id' => $product1->id]));
        $responseFiltered->assertStatus(200);
        $responseFiltered->assertViewHas('totalStock', 2);
        $responseFiltered->assertViewHas('readyStock', 1);
        $responseFiltered->assertViewHas('soldStock', 1);

        // Filtered by search "product 2"
        $responseSearch = $this->actingAs($admin)->get(route('admin.stock.index', ['search' => 'product 2']));
        $responseSearch->assertStatus(200);
        $responseSearch->assertViewHas('totalStock', 1);
        $responseSearch->assertViewHas('readyStock', 1);
        $responseSearch->assertViewHas('soldStock', 0);
    }

    public function test_seller_stock_page_metrics_are_displayed_and_responsive_to_filters()
    {
        $seller = User::forceCreate([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        // Another seller's stock to verify scoping
        $otherSeller = User::forceCreate([
            'username' => 'other_seller',
            'full_name' => 'Other Seller',
            'email' => 'other@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create(['name' => 'Product 1', 'price' => 100]);

        // Seller stock 1 (ready)
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Ready stock seller',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller->id
        ]);

        // Seller stock 2 (sold)
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Sold stock seller',
            'is_sold' => true,
            'stock_status' => 'ready',
            'seller_id' => $seller->id
        ]);

        // Other seller stock (should not be counted)
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Ready stock other seller',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $otherSeller->id
        ]);

        // No filters: should return only seller's stock
        $response = $this->actingAs($seller)->get(route('seller.stock.index'));
        $response->assertStatus(200);
        $response->assertViewHas('totalStock', 2);
        $response->assertViewHas('readyStock', 1);
        $response->assertViewHas('soldStock', 1);
        $response->assertSee('Total Stok');
        $response->assertSee('Ready');
        $response->assertSee('Terjual');

        // Filtered by search "sold"
        $responseSearch = $this->actingAs($seller)->get(route('seller.stock.index', ['search' => 'sold']));
        $responseSearch->assertStatus(200);
        $responseSearch->assertViewHas('totalStock', 1);
        $responseSearch->assertViewHas('readyStock', 0);
        $responseSearch->assertViewHas('soldStock', 1);
    }

    public function test_stock_unit_umur_akun_accessor_relative_formatting()
    {
        $product = Product::create(['name' => 'Product 1', 'price' => 100]);

        // Case 1: joined 5 hours ago (should return "5 jam yang lalu")
        $stock1 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Ready stock',
            'github_joined_at' => now()->subHours(5)->toIso8601String(),
        ]);
        $this->assertEquals('5 jam yang lalu', $stock1->umur_akun);

        // Case 2: joined 3 days ago (should return "3 hari yang lalu")
        $date = now()->subDays(3);
        $stock2 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'Ready stock',
            'github_joined_at' => $date->toIso8601String(),
        ]);
        $this->assertEquals('3 hari yang lalu', $stock2->umur_akun);
    }
}
