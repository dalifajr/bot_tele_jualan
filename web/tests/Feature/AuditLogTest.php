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

        $this->admin = User::forceCreate([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->seller = User::forceCreate([
            'username' => 'seller_test',
            'full_name' => 'Seller Test',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::forceCreate([
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

    public function test_admin_can_filter_audit_logs_by_date(): void
    {
        // Log 1: Older log (5 days ago)
        AuditLog::create([
            'action' => 'OLD_ACTION',
            'detail' => 'Old change',
            'actor_id' => $this->admin->id,
            'created_at' => now()->subDays(5),
        ]);

        // Log 2: Newer log (today)
        AuditLog::create([
            'action' => 'NEW_ACTION',
            'detail' => 'New change',
            'actor_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        // Querying for today's logs
        $response = $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', [
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
            ]));

        $response->assertStatus(200);
        $response->assertSee('NEW_ACTION');
        $response->assertDontSee('OLD_ACTION');

        // Querying for old logs
        $responseOld = $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', [
                'start_date' => now()->subDays(6)->toDateString(),
                'end_date' => now()->subDays(4)->toDateString(),
            ]));

        $responseOld->assertStatus(200);
        $responseOld->assertSee('OLD_ACTION');
        $responseOld->assertDontSee('NEW_ACTION');
    }

    public function test_audit_logs_displays_correct_role_labels(): void
    {
        // 1. Admin log
        AuditLog::create([
            'action' => 'ADMIN_ACTION',
            'detail' => 'Admin did something',
            'actor_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        // 2. Seller log
        AuditLog::create([
            'action' => 'SELLER_ACTION',
            'detail' => 'Seller did something',
            'actor_id' => $this->seller->id,
            'created_at' => now(),
        ]);

        // 3. Customer log
        AuditLog::create([
            'action' => 'CUSTOMER_ACTION',
            'detail' => 'Customer did something',
            'actor_id' => $this->customer->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index'));

        $response->assertStatus(200);

        // Check labels
        $response->assertSee('Admin');
        $response->assertSee('Seller');
        $response->assertSee('Customer');
    }
}
