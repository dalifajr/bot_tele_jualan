<?php

namespace Tests\Feature;

use App\Models\BroadcastJob;
use App\Models\BroadcastRead;
use App\Models\User;
use App\Services\BroadcastReadAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BroadcastReadAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer1;
    protected User $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'username' => 'admin_bc_analytics',
            'full_name' => 'Admin Analytics',
            'email' => 'admin_bc_analytics@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->customer1 = User::forceCreate([
            'username' => 'cust_bc_1',
            'full_name' => 'Customer One',
            'email' => 'cust_bc_1@test.com',
            'role' => 'customer',
            'telegram_id' => 111000111,
            'password' => bcrypt('password'),
        ]);

        $this->customer2 = User::forceCreate([
            'username' => 'cust_bc_2',
            'full_name' => 'Customer Two',
            'email' => 'cust_bc_2@test.com',
            'role' => 'customer',
            'telegram_id' => 222000222,
            'password' => bcrypt('password'),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_broadcast_embeds_job_id_in_web_notification_and_delivers_telegram_tracking_buttons(): void
    {
        config(['telegram.bot_token' => 'mock_token_123']);

        $capturedPayloads = [];
        Http::fake(function ($request) use (&$capturedPayloads) {
            if (str_contains($request->url(), 'api.telegram.org')) {
                $capturedPayloads[] = $request->data();
                return Http::response(['ok' => true], 200);
            }
            return Http::response(['status' => 'ok'], 200);
        });

        $startRes = $this->actingAs($this->admin)->post(route('admin.broadcast.start'), [
            'message' => '<b>Promo Akhir Pekan!</b>',
            'enable_read_button' => '1',
            'cta_label' => 'Buka Promo',
            'cta_url' => 'https://example.com/promo',
        ]);

        $startRes->assertStatus(200);
        $jobId = $startRes->json('job_id');
        $job = BroadcastJob::findOrFail($jobId);

        $this->assertEquals(3, $job->web_target_count);
        $this->assertTrue($job->enable_read_button);
        $this->assertEquals('Buka Promo', $job->cta_label);
        $this->assertEquals('https://example.com/promo', $job->cta_url);

        // Verify web notification carries broadcast_job_id
        $notif = $this->customer1->notifications()->first();
        $this->assertNotNull($notif);
        $this->assertEquals($job->id, $notif->data['broadcast_job_id'] ?? null);

        // Run delivery command (reset status to pending if loopback touched it)
        $job->update(['status' => 'pending', 'sent_count' => 0, 'failed_count' => 0]);
        $this->artisan('broadcast:run', ['jobId' => $job->id])->assertExitCode(0);

        $job->refresh();
        $this->assertEquals('completed', $job->status);
        $this->assertNotNull($job->completed_at);
        $this->assertEquals(2, $job->sent_count);
        $this->assertNotEmpty($capturedPayloads);

        $markup = json_decode($capturedPayloads[0]['reply_markup'] ?? '{}', true);
        $this->assertCount(2, $markup['inline_keyboard'] ?? []);
        $this->assertEquals('Buka Promo', $markup['inline_keyboard'][0][0]['text']);
        $this->assertStringContainsString('/bc/t/' . $job->id . '/', $markup['inline_keyboard'][0][0]['url']);
        $this->assertEquals('bc_read:' . $job->id, $markup['inline_keyboard'][1][0]['callback_data']);
    }

    public function test_web_read_and_telegram_cta_click_deduplicate_per_user_and_reject_bad_signature(): void
    {
        $analytics = app(BroadcastReadAnalyticsService::class);

        $job = BroadcastJob::create([
            'message' => 'Pengumuman Tracking',
            'total_targets' => 2,
            'web_target_count' => 3,
            'sent_count' => 2,
            'status' => 'completed',
            'completed_at' => now(),
            'admin_id' => $this->admin->id,
            'enable_read_button' => true,
            'cta_label' => 'Lihat Katalog',
            'cta_url' => 'https://example.com/katalog',
        ]);

        // Send web notification to customer1 & customer2
        $this->customer1->notify(new \App\Notifications\BroadcastAnnouncementNotification(
            'Pengumuman Tracking',
            'Info',
            null,
            null,
            $job->id
        ));

        // 1. Customer1 opens notifications page -> recorded as web read
        $this->actingAs($this->customer1)
            ->get(route('notifications.index'))
            ->assertStatus(200);

        $this->assertDatabaseHas('broadcast_reads', [
            'broadcast_job_id' => $job->id,
            'user_id' => $this->customer1->id,
            'via_web' => 1,
            'via_telegram' => 0,
        ]);

        // 2. Customer1 ALSO clicks Telegram CTA link -> stays 1 unique row, both channels true
        $validSig1 = $analytics->generateTrackSignature($job->id, $this->customer1->id);
        $this->get(route('broadcast.track-click', [
            'jobId' => $job->id,
            'userId' => $this->customer1->id,
            'sig' => $validSig1,
        ]))->assertRedirect('https://example.com/katalog');

        // 3. Customer2 clicks Telegram CTA link -> 2nd unique reader (Telegram only)
        $validSig2 = $analytics->generateTrackSignature($job->id, $this->customer2->id);
        $this->get(route('broadcast.track-click', [
            'jobId' => $job->id,
            'userId' => $this->customer2->id,
            'sig' => $validSig2,
        ]))->assertRedirect('https://example.com/katalog');

        // 4. Forged signature rejected with 403
        $this->get(route('broadcast.track-click', [
            'jobId' => $job->id,
            'userId' => $this->customer2->id,
            'sig' => 'invalid_sig_000000000000',
        ]))->assertStatus(403);

        $this->assertEquals(2, BroadcastRead::where('broadcast_job_id', $job->id)->count());

        // Recalculate via Admin endpoint
        $res = $this->actingAs($this->admin)
            ->postJson(route('admin.broadcast.analytics.recalculate', $job->id));

        $res->assertStatus(200);
        $res->assertJsonPath('analytics.read_count', 2);
        $res->assertJsonPath('analytics.web_read_count', 1);
        $res->assertJsonPath('analytics.telegram_read_count', 2);
    }

    public function test_staged_checkpoints_capture_at_10m_30m_1h_and_24h(): void
    {
        $analytics = app(BroadcastReadAnalyticsService::class);

        $baseTime = Carbon::create(2026, 11, 22, 10, 0, 0);
        Carbon::setTestNow($baseTime);

        $job = BroadcastJob::create([
            'message' => 'Broadcast Checkpoint Test',
            'total_targets' => 2,
            'web_target_count' => 2,
            'sent_count' => 2,
            'status' => 'completed',
            'completed_at' => $baseTime,
            'admin_id' => $this->admin->id,
        ]);

        // T+5m: no checkpoint due yet
        Carbon::setTestNow($baseTime->copy()->addMinutes(5));
        $analytics->recordRead($job->id, $this->customer1->id, '111000111', 'web_notif');
        $this->artisan('broadcast:calculate-reads')->assertExitCode(0);
        $this->assertEmpty($job->fresh()->read_snapshots ?? []);

        // T+10m: 10m checkpoint captured with 1 read (50%)
        Carbon::setTestNow($baseTime->copy()->addMinutes(10));
        $this->artisan('broadcast:calculate-reads')->assertExitCode(0);
        $snaps = $job->fresh()->read_snapshots;
        $this->assertArrayHasKey('10m', $snaps);
        $this->assertArrayNotHasKey('30m', $snaps);
        $this->assertEquals(1, $snaps['10m']['count']);
        $this->assertEquals(50.0, $snaps['10m']['rate']);

        // T+25m: customer2 reads via Telegram
        Carbon::setTestNow($baseTime->copy()->addMinutes(25));
        $analytics->recordRead($job->id, $this->customer2->id, '222000222', 'telegram_ack');

        // T+30m: 30m checkpoint captured with 2 reads (100%)
        Carbon::setTestNow($baseTime->copy()->addMinutes(30));
        $this->artisan('broadcast:calculate-reads')->assertExitCode(0);
        $snaps = $job->fresh()->read_snapshots;
        $this->assertArrayHasKey('30m', $snaps);
        $this->assertEquals(2, $snaps['30m']['count']);
        $this->assertEquals(100.0, $snaps['30m']['rate']);
        $this->assertEquals(1, $snaps['10m']['count']); // 10m snapshot stays frozen

        // T+1h: 1h checkpoint captured
        Carbon::setTestNow($baseTime->copy()->addHour());
        $this->artisan('broadcast:calculate-reads')->assertExitCode(0);
        $this->assertArrayHasKey('1h', $job->fresh()->read_snapshots);

        // T+24h: 24h checkpoint captured
        Carbon::setTestNow($baseTime->copy()->addDay());
        $this->artisan('broadcast:calculate-reads')->assertExitCode(0);
        $snaps = $job->fresh()->read_snapshots;
        $this->assertArrayHasKey('24h', $snaps);
        $this->assertCount(4, $snaps);

        // Admin analytics endpoint returns all 4 captured timeline entries
        $res = $this->actingAs($this->admin)
            ->getJson(route('admin.broadcast.analytics', $job->id));
        $res->assertStatus(200);
        $this->assertCount(4, $res->json('analytics.timeline'));
        $this->assertTrue($res->json('analytics.timeline.0.captured'));
        $this->assertTrue($res->json('analytics.timeline.3.captured'));
    }
}
