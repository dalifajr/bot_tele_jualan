<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\LoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DeviceBlockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_block_device_fingerprint_and_middleware_enforces_it()
    {
        $admin = new User([
            'username' => 'admin_tester',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'full_name' => 'Admin Tester',
        ]);
        $admin->role = 'admin';
        $admin->save();

        $testFp = 'fp_test_123456789';
        $testDevId = 'dev_test_987654321';

        // 1. Admin blocks the device
        $response = $this->actingAs($admin)->post(route('admin.logins.block-device'), [
            'device_fingerprint' => $testFp,
            'device_id' => $testDevId,
            'duration' => 7,
        ]);
        $response->assertRedirect();

        // Verify cache keys exist
        $this->assertTrue(Cache::has('blocked_device_fp:' . $testFp));
        $this->assertTrue(Cache::has('blocked_device_id:' . $testDevId));

        // 2. Guest attempts to access website with different IP but sending blocked device fingerprint
        $blockedResponse = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->withCookies(['_sec_device_fp' => $testFp])
            ->get('/');

        $blockedResponse->assertStatus(403);
    }
}
