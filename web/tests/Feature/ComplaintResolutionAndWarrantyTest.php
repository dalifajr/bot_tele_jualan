<?php

namespace Tests\Feature;

use App\Models\ComplaintCase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintResolutionAndWarrantyTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;
    protected $productWithWarranty;
    protected $productWithoutWarranty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::forceCreate([
            'full_name' => 'Customer Tester',
            'username' => 'customertester',
            'email' => 'customer@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $this->productWithWarranty = Product::create([
            'name' => 'Figma Pro 2 Tahun',
            'price' => 15000,
            'description' => 'Figma Pro Garansi 30 Hari',
            'warranty_days' => 30,
            'is_suspended' => false,
        ]);

        $this->productWithoutWarranty = Product::create([
            'name' => 'Akun Tanpa Garansi',
            'price' => 10000,
            'description' => 'Tidak Bergaransi',
            'warranty_days' => 0,
            'is_suspended' => false,
        ]);
    }

    public function test_complaint_view_does_not_leak_blade_source_code_and_renders_resolution_note(): void
    {
        $order = Order::create([
            'order_ref' => 'ORD-WARRANTY-1',
            'customer_id' => $this->customer->id,
            'subtotal' => 15000,
            'unique_code' => 0,
            'status' => 'delivered',
            'total_amount' => 15000,
            'delivered_at' => now()->subDays(2),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productWithWarranty->id,
            'unit_price' => 15000,
            'quantity' => 1,
        ]);

        $complaint = ComplaintCase::create([
            'complaint_ref' => 'CMP-001',
            'customer_id' => $this->customer->id,
            'customer_telegram_id' => 123456789,
            'order_id' => $order->id,
            'order_ref_snapshot' => $order->order_ref,
            'complaint_text' => 'Akun kena suspend',
            'status' => 'done',
            'refund_note' => 'Mengirimkan akun pengganti. Akun Pengganti (Manual): Cek pesanan',
            'created_at' => now()->subDays(1),
            'updated_at' => now(),
            'reopen_count' => 0,
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.complaints.show', $complaint->id));

        $response->assertStatus(200);
        // Blade source code must NOT be leaked
        $response->assertDontSee('@if', false);
        $response->assertDontSee('@endif', false);

        // Resolution note must be visible
        $response->assertSee('Mengirimkan akun pengganti. Akun Pengganti (Manual): Cek pesanan');

        // Warranty is active, so reopen button should be clickable and form present
        $response->assertSee(route('customer.complaints.reopen', $complaint->id));
        $response->assertSee('Buka Kembali Komplain');
        $response->assertDontSee('Garansi Berakhir');
    }

    public function test_reopen_button_is_disabled_when_warranty_has_expired(): void
    {
        // Delivered 40 days ago, warranty was 30 days -> expired 10 days ago
        $order = Order::create([
            'order_ref' => 'ORD-EXPIRED-1',
            'customer_id' => $this->customer->id,
            'subtotal' => 15000,
            'unique_code' => 0,
            'status' => 'delivered',
            'total_amount' => 15000,
            'delivered_at' => now()->subDays(40),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productWithWarranty->id,
            'unit_price' => 15000,
            'quantity' => 1,
        ]);

        $complaint = ComplaintCase::create([
            'complaint_ref' => 'CMP-002',
            'customer_id' => $this->customer->id,
            'customer_telegram_id' => 123456789,
            'order_id' => $order->id,
            'order_ref_snapshot' => $order->order_ref,
            'complaint_text' => 'Kendala akun lama',
            'status' => 'done',
            'refund_note' => 'Sudah diselesaikan sebelumnya',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(2),
            'reopen_count' => 0,
        ]);

        $response = $this->actingAs($this->customer)->get(route('customer.complaints.show', $complaint->id));

        $response->assertStatus(200);
        // Button should be disabled and notice rendered
        $response->assertSee('disabled', false);
        $response->assertSee('Garansi Berakhir');
        $response->assertSee('Masa garansi toko untuk pesanan ini telah berakhir');

        // Post request should also be rejected by backend
        $postResponse = $this->actingAs($this->customer)->post(route('customer.complaints.reopen', $complaint->id));
        $postResponse->assertSessionHas('error');
    }
}
