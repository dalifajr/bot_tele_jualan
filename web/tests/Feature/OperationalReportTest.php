<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::create([
            'username' => 'customer_test',
            'full_name' => 'Customer Test',
            'email' => 'customer@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_guest_is_redirected_from_reports(): void
    {
        $response = $this->get(route('admin.reports.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_reports(): void
    {
        $response = $this->actingAs($this->customer)
            ->get(route('admin.reports.index'));
        
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    public function test_admin_can_access_reports_and_filter_by_days(): void
    {
        // Create an order delivered 10 days ago
        $oldOrder = Order::create([
            'order_ref' => 'REFOLD123',
            'customer_id' => $this->customer->id,
            'subtotal' => 150000,
            'unique_code' => 0,
            'total_amount' => 150000,
            'status' => 'delivered',
            'delivered_at' => now()->subDays(10),
        ]);

        // Create an order delivered today
        $newOrder = Order::create([
            'order_ref' => 'REFNEW123',
            'customer_id' => $this->customer->id,
            'subtotal' => 250000,
            'unique_code' => 0,
            'total_amount' => 250000,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        // 1. Test 7 days filter (should not contain old order amount but should contain new order)
        $response7 = $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['days' => 7]));

        $response7->assertStatus(200);
        $response7->assertSee('250.000'); // new order total sales representation
        
        // 2. Test 14 days filter (should contain both)
        $response14 = $this->actingAs($this->admin)
            ->get(route('admin.reports.index', ['days' => 14]));

        $response14->assertStatus(200);
        $response14->assertSee('Rp 400.000'); // total sales count sum Rp 150.000 + Rp 250.000 = Rp 400.000
    }
}
