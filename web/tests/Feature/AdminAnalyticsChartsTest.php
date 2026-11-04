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
        $response->assertSee('PRODUCT_UPDATE');
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

        $response->assertSee('Tren Percobaan Login (7 Hari Terakhir)');
        $response->assertSee('Distribusi Perangkat Pengguna');
        $response->assertSee('id="loginTrendChart"', false);
    }

    public function test_logins_analytics_aggregates_security_metrics_correctly(): void
    {
        // 2 login sukses dari Desktop (Chrome)
        LoginLog::create([
            'user_id' => $this->admin->id,
            'ip_address' => '127.0.0.1',
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'is_successful' => true,
            'created_at' => now(),
        ]);
        LoginLog::create([
            'user_id' => $this->admin->id,
            'ip_address' => '127.0.0.1',
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'is_successful' => true,
            'created_at' => now(),
        ]);

        // 1 login gagal dari Mobile (Safari)
        LoginLog::create([
            'user_id' => null,
            'ip_address' => '192.168.1.100',
            'device_type' => 'Mobile',
            'browser' => 'Safari',
            'platform' => 'iOS',
            'is_successful' => false,
            'failure_reason' => 'Invalid credentials',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.logins.index'));

        $response->assertStatus(200);
        $response->assertViewHas('totalLoginAttempts', 3);
        $response->assertViewHas('successLoginCount', 2);
        $response->assertViewHas('failedLoginCount', 1);
        $response->assertViewHas('successRate', 66.7);

        // Chart container & donut script ter-render
        $response->assertSee('id="deviceDistChart"', false);
        $response->assertSee('Desktop');
        $response->assertSee('Mobile');
    }
}
