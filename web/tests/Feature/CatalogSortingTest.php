<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_sorts_by_ready_stock_first_then_by_sales_count_descending()
    {
        // 1. Create 4 products:
        // Product 1: Out of stock, 100 sales
        // Product 2: Ready stock (2 units), 5 sales
        // Product 3: Ready stock (5 units), 50 sales
        // Product 4: Out of stock, 10 sales

        $p1 = Product::create(['name' => 'P1 OutOfStock HighSales', 'price' => 10000, 'is_suspended' => false]);
        $p2 = Product::create(['name' => 'P2 Ready LowSales', 'price' => 20000, 'is_suspended' => false]);
        $p3 = Product::create(['name' => 'P3 Ready HighSales', 'price' => 30000, 'is_suspended' => false]);
        $p4 = Product::create(['name' => 'P4 OutOfStock LowSales', 'price' => 40000, 'is_suspended' => false]);

        // Stock for P2 (2 ready)
        StockUnit::create(['product_id' => $p2->id, 'raw_text' => 'stock2_1', 'is_sold' => false, 'stock_status' => 'ready']);
        StockUnit::create(['product_id' => $p2->id, 'raw_text' => 'stock2_2', 'is_sold' => false, 'stock_status' => 'ready']);

        // Stock for P3 (5 ready)
        for ($i = 0; $i < 5; $i++) {
            StockUnit::create(['product_id' => $p3->id, 'raw_text' => "stock3_$i", 'is_sold' => false, 'stock_status' => 'ready']);
        }

        // Sales for P1: 100 sold units
        for ($i = 0; $i < 100; $i++) {
            StockUnit::create(['product_id' => $p1->id, 'raw_text' => "sold1_$i", 'is_sold' => true, 'stock_status' => 'ready']);
        }

        // Sales for P2: 5 sold units
        for ($i = 0; $i < 5; $i++) {
            StockUnit::create(['product_id' => $p2->id, 'raw_text' => "sold2_$i", 'is_sold' => true, 'stock_status' => 'ready']);
        }

        // Sales for P3: 50 sold units
        for ($i = 0; $i < 50; $i++) {
            StockUnit::create(['product_id' => $p3->id, 'raw_text' => "sold3_$i", 'is_sold' => true, 'stock_status' => 'ready']);
        }

        // Sales for P4: 10 sold units
        for ($i = 0; $i < 10; $i++) {
            StockUnit::create(['product_id' => $p4->id, 'raw_text' => "sold4_$i", 'is_sold' => true, 'stock_status' => 'ready']);
        }

        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'full_name' => 'Test User',
            'role' => 'customer',
        ]);
        $response = $this->actingAs($user)->get(route('catalog.index'));
        $response->assertStatus(200);

        /** @var \Illuminate\Database\Eloquent\Collection $products */
        $products = $response->viewData('products');
        $productIds = $products->pluck('id')->toArray();

        // Expected order:
        // Ready stock first: P3 (50 sales), then P2 (5 sales)
        // Out of stock last: P1 (100 sales), then P4 (10 sales)
        $this->assertEquals([$p3->id, $p2->id, $p1->id, $p4->id], $productIds);
    }
}
