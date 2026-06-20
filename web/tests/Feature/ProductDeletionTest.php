<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_admin_can_take_over_seller_product_and_stocks()
    {
        // 1. Create Admin & Seller
        $admin = User::create([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $seller = User::create([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        // 2. Create Product owned by Seller
        $product = Product::create([
            'name' => 'Seller Premium Account',
            'price' => 50000,
            'description' => 'Original description.',
            'creator_id' => $seller->id,
            'is_suspended' => false,
        ]);

        // 3. Create Stock Units uploaded by Seller and owned by Seller
        $stock1 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'credentials1',
            'stock_status' => 'ready',
            'is_sold' => false,
            'seller_id' => $seller->id,
            'uploaded_by_id' => $seller->id,
        ]);

        $stock2 = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'credentials2',
            'stock_status' => 'ready',
            'is_sold' => true,
            'seller_id' => $seller->id,
            'uploaded_by_id' => $seller->id,
        ]);

        // 4. Authenticate as Admin and perform deletion (takeover)
        $response = $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 5. Verify database state
        $product->refresh();
        $this->assertNull($product->creator_id);
        $this->assertStringContainsString('sebelumnya dikelola oleh Seller Test', $product->description);

        $stock1->refresh();
        $this->assertNull($stock1->seller_id);
        $this->assertEquals($seller->id, $stock1->uploaded_by_id);

        $stock2->refresh();
        $this->assertNull($stock2->seller_id);
        $this->assertEquals($seller->id, $stock2->uploaded_by_id);
    }

    /** @test */
    public function test_seller_cannot_delete_product_with_remaining_stock()
    {
        // 1. Create Seller
        $seller = User::create([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        // 2. Create Product owned by Seller
        $product = Product::create([
            'name' => 'Seller Premium Account',
            'price' => 50000,
            'description' => 'Original description.',
            'creator_id' => $seller->id,
            'is_suspended' => false,
        ]);

        // 3. Create active Stock Unit (unsold)
        $stock = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'credentials1',
            'stock_status' => 'ready',
            'is_sold' => false,
            'seller_id' => $seller->id,
            'uploaded_by_id' => $seller->id,
        ]);

        // 4. Authenticate as Seller and perform deletion
        $response = $this->actingAs($seller)
            ->delete(route('seller.products.destroy', $product->id));

        $response->assertRedirect();
        $response->assertSessionHas('swal_error');
        $this->assertStringContainsString('sisa stok aktif', session('swal_error'));

        // Verify product still exists
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /** @test */
    public function test_seller_cannot_delete_product_with_transaction_history()
    {
        // 1. Create Seller & Customer
        $seller = User::create([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $customer = User::create([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        // 2. Create Product owned by Seller
        $product = Product::create([
            'name' => 'Seller Premium Account',
            'price' => 50000,
            'description' => 'Original description.',
            'creator_id' => $seller->id,
            'is_suspended' => false,
        ]);

        // 3. Create an order with transaction history
        $order = Order::create([
            'customer_id' => $customer->id,
            'status' => 'delivered',
            'subtotal' => 50000,
            'unique_code' => 12,
            'total_amount' => 50000,
            'payment_status' => 'paid',
            'order_ref' => 'ORD-TEST123',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        // 4. Authenticate as Seller and perform deletion
        $response = $this->actingAs($seller)
            ->delete(route('seller.products.destroy', $product->id));

        $response->assertRedirect();
        $response->assertSessionHas('swal_error');
        $this->assertStringContainsString('riwayat transaksi', session('swal_error'));

        // Verify product still exists
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    /** @test */
    public function test_seller_can_delete_product_without_stock_or_transactions()
    {
        // 1. Create Seller
        $seller = User::create([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller_test@example.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        // 2. Create Product owned by Seller
        $product = Product::create([
            'name' => 'Seller Premium Account',
            'price' => 50000,
            'description' => 'Original description.',
            'creator_id' => $seller->id,
            'is_suspended' => false,
        ]);

        // 3. Authenticate as Seller and perform deletion
        $response = $this->actingAs($seller)
            ->delete(route('seller.products.destroy', $product->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify product is deleted
        $this->assertSoftDeleted($product);
    }
}
