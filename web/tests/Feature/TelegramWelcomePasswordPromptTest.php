<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TelegramLoginToken;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TelegramWelcomePasswordPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_login_sets_session_flag(): void
    {
        $user = User::create([
            'username' => 'testuser1',
            'full_name' => 'Test User One',
            'email' => 'test1@example.com',
            'password' => Hash::make('secret123'),
            'telegram_id' => 123456789,
            'dismiss_set_password_prompt' => false,
            'has_custom_password' => false,
        ]);

        $tokenStr = 'testlink123';
        TelegramLoginToken::create([
            'token' => 't123',
            'link_token' => $tokenStr,
            'telegram_id' => 123456789,
            'status' => 'verified',
            'link_expires_at' => Carbon::now('UTC')->addMinutes(5)->format('Y-m-d H:i:s'),
        ]);

        $response = $this->get('/auth/telegram/callback?token=' . $tokenStr);

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue(session()->has('show_telegram_welcome_modal') || session()->has('logged_in_via_telegram'));
        $this->assertEquals($user->id, Auth::id());
    }

    public function test_dismiss_password_prompt_endpoint_updates_user_preference(): void
    {
        $user = User::create([
            'username' => 'testuser2',
            'full_name' => 'Test User Two',
            'email' => 'test2@example.com',
            'password' => Hash::make('secret123'),
            'telegram_id' => 987654321,
            'dismiss_set_password_prompt' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('profile.password.dismiss-prompt'));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertTrue($user->fresh()->dismiss_set_password_prompt);
    }

    public function test_updating_password_marks_custom_password_and_dismisses_prompt(): void
    {
        $user = User::create([
            'username' => 'testuser3',
            'full_name' => 'Test User Three',
            'email' => 'test3@example.com',
            'password' => Hash::make('secret123'),
            'telegram_id' => 555666777,
            'dismiss_set_password_prompt' => false,
            'has_custom_password' => false,
        ]);

        $response = $this->actingAs($user)->post(route('profile.password.update'), [
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $response->assertSessionHasNoErrors();
        $freshUser = $user->fresh();
        $this->assertTrue($freshUser->has_custom_password);
        $this->assertTrue($freshUser->dismiss_set_password_prompt);
    }
}
