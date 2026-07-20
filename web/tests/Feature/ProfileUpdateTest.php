<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_without_changing_username()
    {
        $user = User::forceCreate([
            'full_name' => 'Dzulfikri Alifajri',
            'username' => 'dzulfikrialifajri',
            'email' => 'dzulfikrialifajri@gmail.com',
            'telegram_id' => 1167516058,
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($user)->post(route('profile.update'), [
            'full_name' => 'M Dzulfikri Alifajri',
            'username' => 'dzulfikrialifajri',
            'email' => 'dzulfikrialifajri@gmail.com',
            'telegram_id' => 1167516058,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $this->assertEquals('M Dzulfikri Alifajri', $user->fresh()->full_name);
    }

    public function test_user_can_update_profile_with_case_different_username()
    {
        $user = User::forceCreate([
            'full_name' => 'Dzulfikri Alifajri',
            'username' => 'DzulfikriAlifajri',
            'email' => 'dzulfikrialifajri@gmail.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($user)->post(route('profile.update'), [
            'full_name' => 'Dzulfikri Alifajri Baru',
            'username' => 'dzulfikrialifajri',
            'email' => 'dzulfikrialifajri@gmail.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    public function test_user_cannot_change_username_to_one_already_taken()
    {
        $otherUser = User::forceCreate([
            'full_name' => 'Other User',
            'username' => 'otherusername',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $user = User::forceCreate([
            'full_name' => 'Dzulfikri Alifajri',
            'username' => 'dzulfikrialifajri',
            'email' => 'dzulfikrialifajri@gmail.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($user)->post(route('profile.update'), [
            'full_name' => 'Dzulfikri Alifajri',
            'username' => 'otherusername', // taken
            'email' => 'dzulfikrialifajri@gmail.com',
        ]);

        $response->assertSessionHasErrors(['username']);
    }

    public function test_user_cannot_change_email_to_one_already_taken()
    {
        $otherUser = User::forceCreate([
            'full_name' => 'Other User',
            'username' => 'otherusername',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $user = User::forceCreate([
            'full_name' => 'Dzulfikri Alifajri',
            'username' => 'dzulfikrialifajri',
            'email' => 'dzulfikrialifajri@gmail.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($user)->post(route('profile.update'), [
            'full_name' => 'Dzulfikri Alifajri',
            'username' => 'dzulfikrialifajri',
            'email' => 'other@example.com', // taken
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_user_cannot_change_telegram_id_to_one_already_taken()
    {
        $otherUser = User::forceCreate([
            'full_name' => 'Other User',
            'username' => 'otherusername',
            'email' => 'other@example.com',
            'telegram_id' => 11223344,
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $user = User::forceCreate([
            'full_name' => 'Dzulfikri Alifajri',
            'username' => 'dzulfikrialifajri',
            'email' => 'dzulfikrialifajri@gmail.com',
            'telegram_id' => 55667788,
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($user)->post(route('profile.update'), [
            'full_name' => 'Dzulfikri Alifajri',
            'username' => 'dzulfikrialifajri',
            'email' => 'dzulfikrialifajri@gmail.com',
            'telegram_id' => 11223344, // taken
        ]);

        $response->assertSessionHasErrors(['telegram_id']);
    }
}
