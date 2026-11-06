<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsChartsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'username' => 'admin_tester',
            'full_name' => 'Admin Tester',
            'email' => 'admin_chart@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_audit_logs_analytics_empty_state_renders_safely(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index'));

        $response->assertStatus(200);
        $response->assertViewHas('totalAuditCount', 0);
        $response->assertViewHas('weekAuditCount', 0);
        $response->assertViewHas('topActionName', '-');
        $response->assertViewHas('topActionCount', 0);
        $response->assertViewHas('actorAuditCount', 0);
        $response->assertViewHas('systemAuditCount', 0);
        $response->assertViewHas('auditTrendLabels');
        $response->assertViewHas('auditTrendData');
        $response->assertViewHas('actionDistributionLabels');
        $response->assertViewHas('actionDistributionData');

        // Memastikan elemen KPI card dan chart container ada di DOM
        $response->assertSee('Tren Aktivitas Sistem (7 Hari Terakhir)');
        $response->assertSee('Distribusi Aksi Terbanyak');
        $response->assertSee('id="auditTrendChart"', false);
    }

    public function test_audit_logs_analytics_aggregates_data_correctly(): void
    {
        // 3 log dengan action PRODUCT_UPDATE (2 oleh actor, 1 oleh sistem)
        AuditLog::create([
            'action' => 'PRODUCT_UPDATE',
            'detail' => 'Update stok A',
            'actor_id' => $this->admin->id,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'action' => 'PRODUCT_UPDATE',
            'detail' => 'Update stok B',
            'actor_id' => $this->admin->id,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'action' => 'PRODUCT_UPDATE',
            'detail' => 'System sync',
            'actor_id' => null,
            'created_at' => now(),
        ]);

        // 1 log dengan action ORDER_CANCEL
        AuditLog::create([
            'action' => 'ORDER_CANCEL',
            'detail' => 'Pembatalan otomatis',
            'actor_id' => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.audit-logs.index'));

        $response->assertStatus(200);
        $response->assertViewHas('totalAuditCount', 4);
        $response->assertViewHas('topActionName', 'PRODUCT_UPDATE');
        $response->assertViewHas('topActionCount', 3);
        $response->assertViewHas('actorAuditCount', 2);
        $response->assertViewHas('systemAuditCount', 2);

        // Chart container & donut script ter-render
        $response->assertSee('id="actionDistributionChart"', false);
        $response->assertSee('id="entityDistributionChart"', false);
        $response->assertSee('PRODUCT_UPDATE');
        $response->assertSee('Hasil Analisis & Kesimpulan Log Audit');
        $response->assertViewHas('automationRate', 50.0);
        $response->assertViewHas('humanRate', 50.0);
        $response->assertViewHas('criticalAuditCount', 1);
        $response->assertViewHas('auditAnalysisInsights');
        $response->assertViewHas('topActors');
    }

    public function test_audit_logs_can_filter_by_actor_type_and_entity_type(): void
    {
        AuditLog::create([
            'action' => 'ORDER_DELIVERED',
            'entity_type' => 'order',
            'entity_id' => 10,
            'detail' => 'Auto delivery',
            'actor_id' => null,
            'created_at' => now(),
        ]);

        AuditLog::create([
            'action' => 'ADMIN_DELETE_PRODUCT',
            'entity_type' => 'product',
            'entity_id' => 5,
            'detail' => 'Manual delete',
            'actor_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        // Filter hanya system/bot
        $resSystem = $this->actingAs($this->admin)->get(route('admin.audit-logs.index', ['actor_type' => 'system']));
        $resSystem->assertStatus(200);
        $resSystem->assertViewHas('totalAuditCount', 1);
        $resSystem->assertSee('ORDER_DELIVERED');
        $resSystem->assertDontSee('ADMIN_DELETE_PRODUCT');

        // Filter hanya entity_type = product
        $resEntity = $this->actingAs($this->admin)->get(route('admin.audit-logs.index', ['entity_type' => 'product']));
        $resEntity->assertStatus(200);
        $resEntity->assertViewHas('totalAuditCount', 1);
        $resEntity->assertSee('ADMIN_DELETE_PRODUCT');
    }

    public function test_logins_analytics_empty_state_renders_safely(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.logins.index'));

        $response->assertStatus(200);
        $response->assertViewHas('totalLoginAttempts', 0);
        $response->assertViewHas('successLoginCount', 0);
        $response->assertViewHas('failedLoginCount', 0);
        $response->assertViewHas('successRate', 0);
        $response->assertViewHas('loginTrendLabels');
        $response->assertViewHas('loginSuccessTrend');
        $response->assertViewHas('loginFailedTrend');
        $response->assertViewHas('deviceLabels');
        $response->assertViewHas('deviceData');
        $response->assertViewHas('browserLabels');
        $response->assertViewHas('browserData');
        $response->assertViewHas('securityScore', 100);
        $response->assertViewHas('loginAnalysisInsights');

        $response->assertSee('Tren Percobaan Login (7 Hari Terakhir)');
        $response->assertSee('Distribusi Perangkat Pengguna');
        $response->assertSee('Hasil Analisis Keamanan & Evaluasi Risiko Login');
        $response->assertSee('id="loginTrendChart"', false);
        $response->assertSee('id="loginHourlyChart"', false);
    }

    public function test_logins_analytics_aggregates_security_metrics_correctly(): void
    {
        // 2 login sukses dari Desktop (Chrome)
        LoginLog::create([
            'user_id' => $this->admin->id,
            'username_or_email' => 'admin_tester',
            'ip_address' => '127.0.0.1',
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
            'location' => 'Jakarta, ID',
            'is_successful' => true,
            'created_at' => now(),
        ]);
        LoginLog::create([
            'user_id' => $this->admin->id,
            'username_or_email' => 'admin_tester',
            'ip_address' => '127.0.0.1',
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
            'location' => 'Jakarta, ID',
            'is_successful' => true,
            'created_at' => now(),
        ]);

        // 3 login gagal dari Mobile (Safari) oleh IP yang sama untuk menguji deteksi anomali/brute-force
        for ($i = 0; $i < 3; $i++) {
            LoginLog::create([
                'user_id' => null,
                'username_or_email' => 'target_victim@example.com',
                'ip_address' => '192.168.1.100',
                'device_type' => 'Mobile',
                'browser' => 'Safari',
                'location' => 'Singapore, SG',
                'is_successful' => false,
                'created_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.logins.index'));

        $response->assertStatus(200);
        $response->assertViewHas('totalLoginAttempts', 5);
        $response->assertViewHas('successLoginCount', 2);
        $response->assertViewHas('failedLoginCount', 3);
        $response->assertViewHas('successRate', 40.0);
        $response->assertViewHas('uniqueIpCount', 2);
        $response->assertViewHas('uniqueFailedIpCount', 1);

        // Chart container & script ter-render
        $response->assertSee('id="deviceDistChart"', false);
        $response->assertSee('id="browserDistChart"', false);
        $response->assertSee('id="loginHourlyChart"', false);
        $response->assertSee('Desktop');
        $response->assertSee('Mobile');
        $response->assertSee('192.168.1.100');
        $response->assertSee('target_victim@example.');
        $response->assertSee('Deteksi Percobaan Gagal Berulang per IP');

        // Uji filter status = failed
        $resFailed = $this->actingAs($this->admin)->get(route('admin.logins.index', ['status' => 'failed']));
        $resFailed->assertStatus(200);
        $resFailed->assertViewHas('totalLoginAttempts', 3);
        $resFailed->assertViewHas('failedLoginCount', 3);
    }
}
