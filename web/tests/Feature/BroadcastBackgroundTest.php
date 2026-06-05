<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BroadcastJob;
use App\Jobs\SendBroadcastJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BroadcastBackgroundTest extends TestCase
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
            'telegram_id' => 99998888,
            'password' => bcrypt('password'),
        ]);
    }

    public function test_admin_can_start_background_broadcast(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.broadcast.start'), [
                'message' => '<b>Promo Spesial!</b>'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'total' => 1
        ]);

        $this->assertDatabaseHas('broadcast_jobs', [
            'message' => '<b>Promo Spesial!</b>',
            'total_targets' => 1,
            'status' => 'pending'
        ]);

        Queue::assertPushed(SendBroadcastJob::class);
    }

    public function test_can_retrieve_broadcast_status(): void
    {
        $job = BroadcastJob::create([
            'message' => 'Hello',
            'total_targets' => 10,
            'sent_count' => 3,
            'failed_count' => 1,
            'status' => 'processing',
            'admin_id' => $this->admin->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.broadcast.status', $job->id));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'job' => [
                'id' => $job->id,
                'status' => 'processing',
                'total' => 10,
                'sent' => 3,
                'failed' => 1
            ]
        ]);
    }

    public function test_can_retrieve_active_broadcast(): void
    {
        $job = BroadcastJob::create([
            'message' => 'Running Job',
            'total_targets' => 5,
            'status' => 'processing',
            'admin_id' => $this->admin->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.broadcast.active'));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'has_active' => true,
            'job' => [
                'id' => $job->id,
                'status' => 'processing'
            ]
        ]);
    }
}
