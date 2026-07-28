<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPdfReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_seller_performance_pdf_report(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_seller_test',
            'full_name' => 'Admin Seller Test',
            'email' => 'admin_seller@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $seller = User::forceCreate([
            'username' => 'seller_perf_test',
            'full_name' => 'Seller Performance Test',
            'email' => 'seller_perf@test.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
            'platform_fee_percent' => 10,
        ]);

        $product = Product::create([
            'name' => 'Spotify Premium 1 Month',
            'price' => 30000,
            'description' => 'Test Product Spotify',
            'creator_id' => $seller->id,
        ]);

        $customer = User::forceCreate([
            'username' => 'customer_perf_test',
            'full_name' => 'Customer Perf Test',
            'email' => 'customer_perf@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-PERF-12345',
            'customer_id' => $customer->id,
            'subtotal' => 30000,
            'unique_code' => 0,
            'total_amount' => 30000,
            'status' => 'delivered',
            'payment_method' => 'wallet',
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'raw_text' => 'user_spotify:pass_spotify',
            'is_sold' => true,
            'sold_order_id' => $order->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.sellers.report-pdf', [
                'id' => $seller->id,
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_customer_cannot_access_seller_pdf_report(): void
    {
        $seller = User::forceCreate([
            'username' => 'seller_blocked',
            'full_name' => 'Seller Blocked',
            'email' => 'seller_blocked@test.com',
            'password' => bcrypt('password'),
            'role' => 'seller',
        ]);

        $customer = User::forceCreate([
            'username' => 'customer_blocked',
            'full_name' => 'Customer Blocked',
            'email' => 'customer_blocked@test.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($customer)
            ->get(route('admin.sellers.report-pdf', [
                'id' => $seller->id,
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]));

        $response->assertRedirect(route('dashboard'));
    }
}
