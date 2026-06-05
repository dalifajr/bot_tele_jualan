<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $seller;
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

        $this->seller = User::create([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
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

    public function test_guest_is_redirected_from_audit_logs(): void
    {
        $response = $this->get(route('admin.audit-logs.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_audit_logs(): void
    {
        $response = $this->actingAs($this->customer)
            ->get(route('admin.audit-logs.index'));
        
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    public function test_seller_cannot_access_audit_logs(): void
    {
        $response = $this->actingAs($this->seller)
            ->get(route('admin.audit-logs.index'));
        
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    public function test_admin_can_access_audit_logs_and_see_records(): void
    {
        AuditLog::create([
            'action' => 'ADD_STOCK',
            'detail' => 'Added 5 accounts to product A',
            'actor_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('ADD_STOCK');
        $response->assertSee('Added 5 accounts to product A');
    }
}
