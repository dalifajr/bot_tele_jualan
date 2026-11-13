<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DashboardPromo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPromoAndThemeCustomizationTest extends TestCase
{
    use RefreshDatabase;

    private function createTestUser(string $role = 'customer', ?string $themeColor = null): User
    {
        return User::forceCreate([
            'username' => 'user_' . uniqid(),
            'full_name' => 'Test User ' . $role,
            'email' => 'user_' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => $role,
            'theme_color' => $themeColor,
        ]);
    }

    public function test_guest_cannot_access_admin_promos()
    {
        $response = $this->get(route('admin.promos.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_admin_promos()
    {
        $customer = $this->createTestUser('customer');

        $response = $this->actingAs($customer)->get(route('admin.promos.index'));
        $this->assertTrue(in_array($response->status(), [302, 403]));
    }

    public function test_admin_can_view_promos_list()
    {
        $admin = $this->createTestUser('admin');

        $promo = DashboardPromo::create([
            'title' => 'Test Promo Khusus',
            'badge_text' => 'HOT DEALS',
            'badge_color' => '#0b57d0',
            'description' => 'Diskon gila-gilaan untuk produk digital.',
            'icon' => 'fas fa-fire',
            'button_text' => 'Beli Sekarang',
            'button_url' => '/catalog',
            'gradient_theme' => 'red',
            'order_index' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.promos.index'));
        $response->assertStatus(200);
        $response->assertSee('Test Promo Khusus');
        $response->assertSee('HOT DEALS');
    }

    public function test_admin_can_create_new_promo_card()
    {
        $admin = $this->createTestUser('admin');

        $response = $this->actingAs($admin)->post(route('admin.promos.store'), [
            'title' => 'Promo Spesial Akhir Tahun',
            'badge_text' => 'YEAR END',
            'badge_color' => '#2e7d32',
            'description' => 'Dapatkan cashback 50% setiap pembelian lisensi.',
            'icon' => 'fas fa-gift',
            'button_text' => 'Cek Katalog',
            'button_url' => '/catalog',
            'gradient_theme' => 'green',
            'order_index' => 5,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.promos.index'));
        $this->assertDatabaseHas('dashboard_promos', [
            'title' => 'Promo Spesial Akhir Tahun',
            'badge_text' => 'YEAR END',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_toggle_and_update_promo()
    {
        $admin = $this->createTestUser('admin');

        $promo = DashboardPromo::create([
            'title' => 'Promo Awal',
            'badge_text' => 'NEW',
            'badge_color' => '#0b57d0',
            'description' => 'Deskripsi lama.',
            'icon' => 'fas fa-tag',
            'button_text' => 'Klik',
            'button_url' => '/catalog',
            'gradient_theme' => 'blue',
            'order_index' => 1,
            'is_active' => true,
        ]);

        // Toggle status
        $this->actingAs($admin)->post(route('admin.promos.toggle', $promo));
        $this->assertFalse($promo->fresh()->is_active);

        // Update details
        $this->actingAs($admin)->put(route('admin.promos.update', $promo), [
            'title' => 'Promo Diedit',
            'badge_text' => 'UPDATED',
            'badge_color' => '#b3261e',
            'description' => 'Deskripsi baru yang diperbarui.',
            'icon' => 'fas fa-tags',
            'button_text' => 'Beli Segera',
            'button_url' => '/catalog?sort=latest',
            'gradient_theme' => 'red',
            'order_index' => 2,
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('dashboard_promos', [
            'id' => $promo->id,
            'title' => 'Promo Diedit',
            'is_active' => true,
        ]);
    }

    public function test_user_can_customize_and_save_primary_theme_color()
    {
        $user = $this->createTestUser('customer');

        $response = $this->actingAs($user)->postJson(route('profile.theme-color'), [
            'theme_color' => '#00875a',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'theme_color' => '#00875a',
        ]);

        $this->assertEquals('#00875a', $user->fresh()->theme_color);
    }

    public function test_theme_color_validation_rejects_invalid_hex()
    {
        $user = $this->createTestUser('customer');

        $response = $this->actingAs($user)->postJson(route('profile.theme-color'), [
            'theme_color' => 'invalid-color-string',
        ]);

        $response->assertStatus(422);
    }
}
