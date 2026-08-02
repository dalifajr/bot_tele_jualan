<?php

namespace Tests\Feature;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginHistoryBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_with_null_email_does_not_see_false_badge_or_other_users_logs()
    {
        // 1. Create a customer with null email (common for Telegram logins)
        $customer = User::create([
            'username' => 'customer_tg',
            'email' => 'customer_tg@example.com',
            'password' => bcrypt('password'),
            'full_name' => 'Customer TG',
            'role' => 'customer',
            'telegram_id' => 123456789,
        ]);

        // 2. Create another user and some orphaned/null login_logs with blocked IP
        $otherUser = User::create([
            'username' => 'other_user',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'full_name' => 'Other User',
            'role' => 'customer',
        ]);

        LoginLog::create([
            'user_id' => $otherUser->id,
            'ip_address' => '203.0.113.50',
            'username_or_email' => 'other_user',
            'is_successful' => false,
            'user_agent' => 'Mozilla/5.0',
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
        ]);

        // Create orphaned log with null username_or_email
        LoginLog::create([
            'user_id' => null,
            'ip_address' => '203.0.113.99',
            'username_or_email' => null,
            'is_successful' => false,
            'user_agent' => 'Mozilla/5.0',
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
        ]);

        // Block the IP in cache
        Cache::put('blocked_ip:203.0.113.99', true, 60);

        // 3. Request profile logins page as customer
        $response = $this->actingAs($customer)->get(route('profile.logins'));
        $response->assertStatus(200);

        // Assert customer does not see orphaned log or false badge
        $response->assertDontSee('203.0.113.99');
        $response->assertDontSee('203.0.113.50');
    }
}
