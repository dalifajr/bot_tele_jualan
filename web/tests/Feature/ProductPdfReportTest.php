<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPdfReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_product_pdf_sales_report(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $seller = User::forceCreate([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'platform_fee_percent' => 10,
        ]);

        $product = Product::create([
            'name' => 'Netflix Premium 1 Month',
            'price' => 50000,
            'description' => 'Test Product',
            'creator_id' => $seller->id,
        ]);

        $customer = User::forceCreate([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-TEST-12345',
            'customer_id' => $customer->id,
            'subtotal' => 50000,
            'unique_code' => 0,
            'total_amount' => 50000,
            'status' => 'delivered',
            'payment_method' => 'wallet',
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'raw_text' => 'user:pass',
            'is_sold' => true,
            'sold_order_id' => $order->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.products.report-pdf', [
                'id' => $product->id,
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
