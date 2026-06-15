<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BotSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationSpamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a regular registration logs the IP address.
     */
    public function test_registration_logs_ip_address()
    {
        $response = $this->post(route('register.post'), [
            'full_name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'johndoe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'REMOTE_ADDR' => '192.168.1.100'
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'username' => 'johndoe',
            'registration_ip' => '192.168.1.100',
            'is_suspended' => false,
        ]);
    }

    /**
     * Test that spamming registration from the same IP suspends all accounts.
     */
    public function test_spam_registration_from_same_ip_suspends_all_accounts()
    {
        // Set threshold to 3 for testing speed
        BotSetting::updateOrCreate(['key' => 'spam_registration_threshold'], ['value' => '3']);

        // Register 1st account (IP: 10.0.0.1) - should succeed
        $response1 = $this->post(route('register.post'), [
            'full_name' => 'User One',
            'username' => 'userone',
            'email' => 'userone@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], ['REMOTE_ADDR' => '10.0.0.1']);
        $response1->assertRedirect(route('dashboard'));

        // Register 2nd account (IP: 10.0.0.1) - should succeed
        $response2 = $this->post(route('register.post'), [
            'full_name' => 'User Two',
            'username' => 'usertwo',
            'email' => 'usertwo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], ['REMOTE_ADDR' => '10.0.0.1']);
        $response2->assertRedirect(route('dashboard'));

        // Verify they are not suspended
        $this->assertFalse(User::where('username', 'userone')->first()->is_suspended);
        $this->assertFalse(User::where('username', 'usertwo')->first()->is_suspended);

        // Register 3rd account (IP: 10.0.0.1) - triggers suspension of all 3
        $response3 = $this->post(route('register.post'), [
            'full_name' => 'User Three',
            'username' => 'userthree',
            'email' => 'userthree@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], ['REMOTE_ADDR' => '10.0.0.1']);

        // Should redirect to login page with error
        $response3->assertRedirect(route('login'));
        $response3->assertSessionHas('error');

        // Verify all 3 accounts are now suspended
        $user1 = User::where('username', 'userone')->first();
        $user2 = User::where('username', 'usertwo')->first();
        $user3 = User::where('username', 'userthree')->first();

        $this->assertTrue($user1->is_suspended);
        $this->assertTrue($user2->is_suspended);
        $this->assertTrue($user3->is_suspended);

        $this->assertEquals('Spam registrasi terdeteksi dari IP ini.', $user1->suspension_reason);
        $this->assertEquals('Spam registrasi terdeteksi dari IP ini.', $user2->suspension_reason);
        $this->assertEquals('Spam registrasi terdeteksi dari IP ini.', $user3->suspension_reason);
    }
}
