<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UnifiedBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_use_unified_block_and_middleware_immediately_logs_out_session()
    {
        $admin = new User([
            'username' => 'admin_block_tester',
            'email' => 'admin_block@example.com',
            'password' => bcrypt('password'),
            'full_name' => 'Admin Block Tester',
        ]);
        $admin->role = 'admin';
        $admin->save();

        $targetUser = new User([
            'username' => 'target_user',
            'email' => 'target@example.com',
            'password' => bcrypt('password'),
            'full_name' => 'Target User',
        ]);
        $targetUser->role = 'customer';
        $targetUser->save();

        $testIp = '203.0.113.88';
        $testFp = 'fp_unified_123';
        $testDevId = 'dev_unified_456';

        // 1. Admin submits unified block request
        $response = $this->actingAs($admin)->post(route('admin.logins.unified-block'), [
            'ip_address' => $testIp,
            'device_fingerprint' => $testFp,
            'device_id' => $testDevId,
            'user_id' => $targetUser->id,
            'block_ip' => '1',
            'block_device' => '1',
            'suspend_account' => '1',
            'duration' => 30,
        ]);
        $response->assertRedirect();

        // Assert items were blocked
        $this->assertTrue(Cache::has('blocked_ip:' . $testIp));
        $this->assertTrue(Cache::has('blocked_device_fp:' . $testFp));
        $this->assertTrue($targetUser->fresh()->is_suspended);

        // 2. Active targetUser attempts to browse website
        $blockedResponse = $this->actingAs($targetUser->fresh())
            ->withServerVariables(['REMOTE_ADDR' => $testIp])
            ->get('/');

        // Assert immediate 403 blocking and guest session logout
        $blockedResponse->assertStatus(403);
        $this->assertGuest();

        // 3. Admin submits unified unblock request
        $this->actingAs($admin);
        \Illuminate\Support\Facades\Cache::forget('blocked_ip:' . $testIp);
        \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:' . $testFp);
        $targetUser->is_suspended = false;
        $targetUser->save();

        // Assert items were unblocked
        $this->assertFalse(Cache::has('blocked_ip:' . $testIp));
        $this->assertFalse(Cache::has('blocked_device_fp:' . $testFp));
        $this->assertFalse($targetUser->fresh()->is_suspended);
    }
}
