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

    public function test_artisan_command_executes_broadcast_successfully()
    {
        config(['telegram.bot_token' => 'mock_telegram_token_here']);

        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200)
        ]);

        $job = BroadcastJob::create([
            'message' => 'Test message',
            'total_targets' => 1,
            'status' => 'pending',
            'admin_id' => $this->admin->id
        ]);

        $this->artisan('broadcast:run', ['jobId' => $job->id])
            ->assertExitCode(0);

        $this->assertEquals('completed', $job->fresh()->status);
        $this->assertEquals(1, $job->fresh()->sent_count);
    }

    public function test_loopback_fallback_executes_broadcast_successfully()
    {
        config(['telegram.bot_token' => 'mock_telegram_token_here']);

        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200)
        ]);

        $job = BroadcastJob::create([
            'message' => 'Test message',
            'total_targets' => 1,
            'status' => 'pending',
            'admin_id' => $this->admin->id
        ]);

        $token = hash_hmac('sha256', $job->id, config('app.key'));

        $response = $this->get(route('admin.broadcast.run-bg', ['jobId' => $job->id, 'token' => $token]));

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertEquals('completed', $job->fresh()->status);
        $this->assertEquals(1, $job->fresh()->sent_count);
    }

    public function test_admin_can_cancel_active_broadcast(): void
    {
        $job = BroadcastJob::create([
            'message' => 'To be cancelled',
            'total_targets' => 10,
            'status' => 'processing',
            'admin_id' => $this->admin->id
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.broadcast.cancel', $job->id));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Broadcast berhasil dihentikan.'
        ]);

        $this->assertEquals('failed', $job->fresh()->status);
    }
}
