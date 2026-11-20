<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ErrorPagesVisualTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_page_renders_custom_material_you_view(): void
    {
        $response = $this->get('/halaman-yang-pasti-tidak-ada-404-m3');

        $response->assertStatus(404);
        $response->assertSee('HTTP 404');
        $response->assertSee('Halaman Tidak Ditemukan');
        $response->assertSee('Jelajahi Katalog');
        $response->assertSee('name="text-scale" content="scale"', false);
        $response->assertSee('text-wrap: balance', false);
        $response->assertSee('accent-color: var(--primary)', false);
    }

    public function test_blocked_ip_renders_custom_m3_403_page_with_diagnostics(): void
    {
        $blockedIp = '203.0.113.55';
        Cache::put('blocked_ip:' . $blockedIp, true, 60);

        try {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $blockedIp])
                ->get('/dashboard');

            $response->assertStatus(403);
            $response->assertSee('HTTP 403');
            $response->assertSee('Akses Kamu Diblokir');
            $response->assertSee('Kamu diblokir');
            $response->assertSee($blockedIp);
            $response->assertSee('Salin Info Diagnostik');
        } finally {
            Cache::forget('blocked_ip:' . $blockedIp);
        }
    }

    public function test_all_standalone_http_error_views_render_cleanly(): void
    {
        $cases = [
            ['view' => 'errors.401', 'code' => '401', 'needle' => 'Masuk Diperlukan'],
            ['view' => 'errors.403', 'code' => '403', 'needle' => 'Akses Ditolak'],
            ['view' => 'errors.404', 'code' => '404', 'needle' => 'Halaman Tidak Ditemukan'],
            ['view' => 'errors.419', 'code' => '419', 'needle' => 'Sesi Halaman Kedaluwarsa'],
            ['view' => 'errors.429', 'code' => '429', 'needle' => 'Terlalu Banyak Permintaan'],
            ['view' => 'errors.500', 'code' => '500', 'needle' => 'Terjadi Kendala di Server'],
            ['view' => 'errors.503', 'code' => '503', 'needle' => 'Layanan Sedang Pemeliharaan'],
        ];

        foreach ($cases as $case) {
            $html = view($case['view'], [
                'exception' => new HttpException((int) $case['code'], ''),
            ])->render();

            $this->assertStringContainsString('HTTP ' . $case['code'], $html);
            $this->assertStringContainsString($case['needle'], $html);
            $this->assertStringContainsString('m3-err-card', $html);
        }
    }

    public function test_suspended_page_renders_flat_material_you_layout(): void
    {
        $user = new User([
            'username' => 'suspended_tester',
            'email' => 'suspended_tester@example.com',
            'password' => bcrypt('password'),
            'full_name' => 'Suspended Tester',
        ]);
        $user->role = 'customer';
        $user->is_suspended = true;
        $user->suspension_reason = 'Pelanggaran ketentuan transaksi';
        $user->save();

        // Middleware BlockBannedIps intercepts suspended users with 403 M3 blocked page
        $blockedResponse = $this->actingAs($user)->get('/dashboard');
        $blockedResponse->assertStatus(403);
        $blockedResponse->assertSee('Akses Kamu Diblokir');

        // Standalone auth.suspended view renders Flat Material You layout cleanly
        $this->actingAs($user);
        $html = view('auth.suspended')->render();
        $this->assertStringContainsString('AKUN DITANGGUHKAN', $html);
        $this->assertStringContainsString('Akun Anda Ditangguhkan', $html);
        $this->assertStringContainsString('Pelanggaran ketentuan transaksi', $html);
        $this->assertStringContainsString('border-radius: 28px', $html);
        $this->assertStringNotContainsString('card shadow-sm border-0', $html);
    }

    public function test_maintenance_page_renders_flat_material_you_without_blur_or_rainbow_gradient(): void
    {
        BotSetting::updateOrCreate(
            ['key' => 'maintenance_mode'],
            ['value' => '1']
        );
        BotSetting::updateOrCreate(
            ['key' => 'maintenance_message'],
            ['value' => 'Upgrade database terjadwal sedang berlangsung.']
        );

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('MODE MAINTENANCE AKTIF');
        $response->assertSee('Sistem Sedang Dalam Pemeliharaan');
        $response->assertSee('Upgrade database terjadwal sedang berlangsung.');
        $response->assertDontSee('backdrop-filter: blur(10px)');
        $response->assertDontSee('shadow-lg');
        $response->assertDontSee('#ffc107, #fd7e14, #dc3545');
    }
}
