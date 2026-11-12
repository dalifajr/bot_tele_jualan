<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_does_not_see_segmented_tab_switch()
    {
        $customer = User::forceCreate([
            'username' => 'customer_role_test',
            'full_name' => 'Customer Role Test',
            'email' => 'cust_test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'customer',
        ]);

        $response = $this->actingAs($customer)->get(route('dashboard'));

        $response->assertStatus(200);
        // Customer MUST NOT see segmented switcher tab
        $response->assertDontSee('m3-segmented-switch', false);
        // Customer sees shopping menu items
        $response->assertSee('MENU UTAMA');
        $response->assertSee('Home');
        $response->assertSee('Katalog Produk');
        $response->assertSee('Riwayat Pesanan');
        $response->assertSee('Kelola Komplain');
        $response->assertSee('Pusat Chat');
        $response->assertSee('AKUN');
        $response->assertSee('Profil Saya');
        $response->assertSee('Riwayat Login');

        // Customer MUST NOT see admin or seller management panels
        $response->assertDontSee('PANEL ADMIN');
        $response->assertDontSee('PANEL MITRA');
        $response->assertDontSee('Dashboard Admin');
        $response->assertDontSee('Dashboard Seller');
    }

    public function test_admin_sees_segmented_tab_and_toggles_between_belanja_and_admin()
    {
        $admin = User::forceCreate([
            'username' => 'admin_role_test',
            'full_name' => 'Admin Role Test',
            'email' => 'admin_test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        // When admin visits dashboard (Belanja mode)
        $responseBelanja = $this->actingAs($admin)->get(route('dashboard'));
        $responseBelanja->assertStatus(200);
        $responseBelanja->assertSee('m3-segmented-switch', false);
        $responseBelanja->assertSee('Belanja');
        $responseBelanja->assertSee('Admin');
        // In shopping mode, admin sees shopping menu
        $responseBelanja->assertSee('MENU UTAMA');
        $responseBelanja->assertSee('Katalog Produk');

        // When admin visits admin.dashboard (Admin mode)
        $responseAdmin = $this->actingAs($admin)->get(route('admin.dashboard'));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('m3-segmented-switch', false);
        $responseAdmin->assertSee('PANEL ADMIN');
        $responseAdmin->assertSee('Dashboard Admin');
        $responseAdmin->assertSee('Katalog Admin');
        $responseAdmin->assertSee('Kelola Stok');
        $responseAdmin->assertSee('Kelola Pesanan');
    }

    public function test_seller_sees_segmented_tab_and_toggles_between_belanja_and_seller()
    {
        $seller = User::forceCreate([
            'username' => 'seller_role_test',
            'full_name' => 'Seller Role Test',
            'email' => 'seller_test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'seller',
        ]);

        // When seller visits dashboard (Belanja mode)
        $responseBelanja = $this->actingAs($seller)->get(route('dashboard'));
        $responseBelanja->assertStatus(200);
        $responseBelanja->assertSee('m3-segmented-switch', false);
        $responseBelanja->assertSee('Belanja');
        $responseBelanja->assertSee('Seller');
        $responseBelanja->assertSee('MENU UTAMA');
        $responseBelanja->assertSee('Katalog Produk');

        // When seller visits seller.dashboard (Seller mode)
        $responseSeller = $this->actingAs($seller)->get(route('seller.dashboard'));
        $responseSeller->assertStatus(200);
        $responseSeller->assertSee('m3-segmented-switch', false);
        $responseSeller->assertSee('PANEL MITRA');
        $responseSeller->assertSee('Dashboard Seller');
        $responseSeller->assertSee('Produk Saya');
        $responseSeller->assertSee('Stok Akun');
        $responseSeller->assertSee('Kelola Pesanan');
    }
}

