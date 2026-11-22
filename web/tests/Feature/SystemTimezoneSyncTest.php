<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\BroadcastJob;
use App\Models\Coupon;
use App\Models\User;
use App\Models\UserSession;
use App\Support\SystemTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemTimezoneSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        SystemTimezone::forgetCache();
        parent::tearDown();
    }

    public function test_user_last_seen_and_last_active_label_sync_to_system_timezone_and_midnight_boundary(): void
    {
        BotSetting::updateOrCreate(['key' => 'system_timezone'], ['value' => 'Asia/Jakarta']);
        SystemTimezone::forgetCache();

        // Now = 2026-11-22 09:45 UTC = 2026-11-22 16:45 WIB
        $nowUtc = Carbon::create(2026, 11, 22, 9, 45, 0, 'UTC');
        Carbon::setTestNow($nowUtc);

        $user = User::forceCreate([
            'username' => 'tz_user_wib',
            'full_name' => 'User WIB',
            'email' => 'tz_user_wib@example.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
            'last_seen_at' => $nowUtc->copy()->subMinutes(15), // 09:30 UTC = 16:30 WIB
        ]);

        // Stored in DB as UTC
        $rawDbVal = DB::table('users')->where('id', $user->id)->value('last_seen_at');
        $this->assertStringStartsWith('2026-11-22 09:30:00', (string) $rawDbVal);

        // Retrieved in Asia/Jakarta (WIB)
        $fresh = $user->fresh();
        $this->assertEquals('Asia/Jakarta', $fresh->last_seen_at->getTimezone()->getName());
        $this->assertEquals('16:30', $fresh->last_seen_at->format('H:i'));
        $this->assertFalse($fresh->isOnline());
        $this->assertStringContainsString('16:30', $fresh->last_active_label);
        $this->assertStringContainsString('hari ini', $fresh->last_active_label);

        // Midnight WIB boundary check:
        // Now = 2026-11-22 18:00 UTC = 2026-11-23 01:00 WIB
        // Last seen = 2026-11-22 17:30 UTC = 2026-11-23 00:30 WIB (same day in WIB, even though UTC is 22 Nov!)
        $midnightUtc = Carbon::create(2026, 11, 22, 18, 0, 0, 'UTC');
        Carbon::setTestNow($midnightUtc);
        $fresh->update(['last_seen_at' => $midnightUtc->copy()->subMinutes(30)]);
        $fresh->refresh();

        $this->assertStringContainsString('hari ini', $fresh->last_active_label);
        $this->assertStringContainsString('00:30', $fresh->last_active_label);

        // Switch timezone to WITA (Asia/Makassar = UTC+8) -> 17:30 UTC = 01:30 WITA
        BotSetting::updateOrCreate(['key' => 'system_timezone'], ['value' => 'Asia/Makassar']);
        SystemTimezone::forgetCache();
        $fresh->refresh();
        $this->assertEquals('01:30', $fresh->last_seen_at->format('H:i'));
        $this->assertEquals('WITA', SystemTimezone::abbr());
    }

    public function test_user_is_online_and_middleware_updates_last_seen_cleanly(): void
    {
        BotSetting::updateOrCreate(['key' => 'system_timezone'], ['value' => 'Asia/Jakarta']);
        SystemTimezone::forgetCache();

        $nowUtc = Carbon::create(2026, 11, 22, 10, 0, 0, 'UTC');
        Carbon::setTestNow($nowUtc);

        $user = User::forceCreate([
            'username' => 'tz_online_user',
            'full_name' => 'Online User',
            'email' => 'tz_online_user@example.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
            'last_seen_at' => $nowUtc->copy()->subMinutes(2),
        ]);

        $this->assertTrue($user->fresh()->isOnline());
        $this->assertEquals(__('Online'), $user->fresh()->last_active_label);

        // Move time forward 3 minutes -> hitting dashboard updates last_seen_at via UpdateUserLastSeen
        Carbon::setTestNow($nowUtc->copy()->addMinutes(3));
        $this->actingAs($user)->get(route('dashboard'))->assertStatus(200);

        $this->assertEquals('17:03', $user->fresh()->last_seen_at->format('H:i'));
    }

    public function test_datetime_local_input_on_coupon_converts_from_system_timezone_to_utc_and_roundtrips(): void
    {
        BotSetting::updateOrCreate(['key' => 'system_timezone'], ['value' => 'Asia/Jakarta']);
        SystemTimezone::forgetCache();

        // Admin enters "2026-12-01T23:59" (WIB) in HTML datetime-local input
        $coupon = Coupon::create([
            'code' => 'TZWIBTEST',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 0,
            'qty' => 10,
            'expires_at' => '2026-12-01T23:59',
            'is_active' => true,
        ]);

        // Stored in DB as UTC (23:59 WIB - 7h = 16:59 UTC)
        $rawDb = DB::table('coupons')->where('id', $coupon->id)->value('expires_at');
        $this->assertStringStartsWith('2026-12-01 16:59:00', (string) $rawDb);

        // Read back in WIB -> exact 23:59!
        $fresh = $coupon->fresh();
        $this->assertEquals('2026-12-01 23:59', $fresh->expires_at->format('Y-m-d H:i'));
        $this->assertEquals('Asia/Jakarta', $fresh->expires_at->getTimezone()->getName());
    }

    public function test_admin_update_settings_clears_cached_timezone_and_other_models_sync(): void
    {
        BotSetting::updateOrCreate(['key' => 'system_timezone'], ['value' => 'Asia/Jakarta']);
        SystemTimezone::forgetCache();
        $this->assertEquals('Asia/Jakarta', SystemTimezone::active());

        $admin = User::forceCreate([
            'username' => 'admin_tz_ctrl',
            'full_name' => 'Admin TZ',
            'email' => 'admin_tz_ctrl@example.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        // Update timezone via Admin settings controller -> cache immediately invalidated
        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings' => [
                'system_timezone' => 'Asia/Jayapura',
            ],
        ])->assertRedirect();

        $this->assertEquals('Asia/Jayapura', SystemTimezone::active());
        $this->assertEquals('WIT', SystemTimezone::abbr());

        // Verify BroadcastJob & UserSession also return WIT (UTC+9)
        $utcTime = Carbon::create(2026, 11, 22, 6, 0, 0, 'UTC'); // 15:00 WIT
        Carbon::setTestNow($utcTime);

        $bc = BroadcastJob::create([
            'message' => 'TZ Broadcast',
            'total_targets' => 1,
            'status' => 'completed',
            'completed_at' => $utcTime,
            'admin_id' => $admin->id,
        ]);
        $this->assertEquals('15:00', $bc->fresh()->completed_at->format('H:i'));

        $session = UserSession::create([
            'user_id' => $admin->id,
            'session_id' => 'sess_tz_test_123',
            'ip_address' => '127.0.0.1',
            'last_active_at' => $utcTime,
        ]);
        $this->assertEquals('15:00', $session->fresh()->last_active_at->format('H:i'));
    }
}
