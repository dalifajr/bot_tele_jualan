<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_contains_help_card_and_not_floating_button()
    {
        config(['telegram.bot_username' => 'test_support_bot']);

        $user = User::forceCreate([
            'username' => 'test_user_nav',
            'full_name' => 'Test User Nav',
            'email' => 'test_user_nav@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);

        // 1. Verify help card exists in sidebar
        $response->assertSee('sidebar-help-card', false);
        $response->assertSee('https://t.me/test_support_bot', false);
        $response->assertSee('Pusat Bantuan');

        // 2. Verify floating button style has been removed from body > a
        $response->assertDontSee('style="bottom: 30px; right: 30px; height: 50px; z-index: 1050;', false);

        // 3. Verify mobile nav handle and card grab handle exist
        $response->assertSee('mobile-nav-handle', false);
        $response->assertSee('mobile-card-handle', false);
        $response->assertSee('mobile-card-handle-bar', false);
        $response->assertSee('sidebarCloseBtn', false);
    }

    public function test_seller_navigation_includes_mobile_handle_and_support_card()
    {
        config(['telegram.bot_username' => 'seller_support_bot']);

        $seller = User::forceCreate([
            'username' => 'test_seller_nav',
            'full_name' => 'Test Seller Nav',
            'email' => 'seller_nav@example.com',
            'password' => bcrypt('password123'),
            'role' => 'seller',
        ]);

        $response = $this->actingAs($seller)->get(route('seller.dashboard'));
        $response->assertStatus(200);

        $response->assertSee('sidebar-help-card', false);
        $response->assertSee('https://t.me/seller_support_bot', false);
        $response->assertSee('mobile-nav-handle', false);
        $response->assertSee('mobile-card-handle', false);
        $response->assertDontSee('style="bottom: 30px; right: 30px;', false);
    }
}
