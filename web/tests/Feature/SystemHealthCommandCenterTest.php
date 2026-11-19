<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemHealthCommandCenterTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/testing/sys_health_' . uniqid());
        File::ensureDirectoryExists($this->tempDir . '/backups');

        File::put($this->tempDir . '/root.env', "BOT_TOKEN=123:abc\nWEBSITE_ENABLED=true\nWEBSITE_DOMAIN=belajaridn.id\nDISABLE_BOT_COMMANDS=false\n");
        File::put($this->tempDir . '/web.env', "APP_NAME=Test\nCLOUDFLARE_TURNSTILE_SITE_KEY=1x00000000000000000000AA\nCLOUDFLARE_TURNSTILE_SECRET_KEY=old_secret_123456\n");

        config([
            'system_health.root_env_path' => $this->tempDir . '/root.env',
            'system_health.web_env_path' => $this->tempDir . '/web.env',
            'system_health.ops_backup_dir' => $this->tempDir . '/backups',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    private function makeUser(string $username, string $role = 'customer', string $password = 'SecretPass123!'): User
    {
        $user = new User([
            'username' => $username,
            'full_name' => ucfirst($username),
            'email' => $username . '@example.test',
            'password' => Hash::make($password),
            'has_custom_password' => true,
        ]);
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function test_health_command_center_renders_five_tabs_and_blocks_non_admin(): void
    {
        $admin = $this->makeUser('sys_admin1', 'admin');
        $customer = $this->makeUser('sys_cust1', 'customer');

        $this->actingAs($customer)->get(route('admin.system.health'))->assertRedirect();

        $this->actingAs($admin)->get(route('admin.system.health'))
            ->assertOk()
            ->assertViewHas('activeTab', 'ringkasan')
            ->assertSee('sys-pane-ringkasan', false)
            ->assertSee('sys-pane-layanan', false)
            ->assertSee('sys-pane-pemeliharaan', false)
            ->assertSee('sys-pane-pembaruan', false)
            ->assertSee('sys-pane-log', false)
            ->assertSee('Diagnosis Kesehatan &amp; Rekomendasi', false);

        $this->actingAs($admin)->get(route('admin.system.health', ['tab' => 'layanan']))
            ->assertOk()
            ->assertViewHas('activeTab', 'layanan');
    }

    public function test_artisan_maintenance_actions_and_toggle_maintenance_work(): void
    {
        $admin = $this->makeUser('sys_admin2', 'admin');

        foreach (['release_expired', 'release_held', 'clean_tokens', 'optimize_clear'] as $action) {
            $this->actingAs($admin)
                ->post(route('admin.system.health.artisan'), [
                    'action' => $action,
                    'active_tab' => 'pemeliharaan',
                ])
                ->assertRedirect(route('admin.system.health', ['tab' => 'pemeliharaan']))
                ->assertSessionHas('success');
        }

        // Reject unknown artisan command
        $this->actingAs($admin)
            ->post(route('admin.system.health.artisan'), ['action' => 'db:wipe'])
            ->assertSessionHasErrors(['action']);

        // Toggle maintenance ON then OFF
        $this->actingAs($admin)
            ->post(route('admin.system.health.maintenance'), ['active_tab' => 'pemeliharaan'])
            ->assertRedirect(route('admin.system.health', ['tab' => 'pemeliharaan']));
        $this->assertSame('1', BotSetting::where('key', 'maintenance_mode')->value('value'));

        $this->actingAs($admin)
            ->post(route('admin.system.health.maintenance'), ['active_tab' => 'pemeliharaan'])
            ->assertRedirect(route('admin.system.health', ['tab' => 'pemeliharaan']));
        $this->assertSame('0', BotSetting::where('key', 'maintenance_mode')->value('value'));
    }

    public function test_prune_ops_backups_and_log_viewer_fetch_work(): void
    {
        $admin = $this->makeUser('sys_admin3', 'admin');

        // Create 5 dummy backups with distinct mtimes
        for ($i = 1; $i <= 5; $i++) {
            $p = $this->tempDir . "/backups/botdb_2026111{$i}_000000.sqlite3";
            File::put($p, str_repeat('X', 256));
            touch($p, time() - (6 - $i) * 60);
        }

        $this->actingAs($admin)
            ->post(route('admin.system.health.backups.prune'), [
                'keep' => 2,
                'active_tab' => 'pemeliharaan',
            ])
            ->assertRedirect(route('admin.system.health', ['tab' => 'pemeliharaan']))
            ->assertSessionHas('success');

        $remaining = glob($this->tempDir . '/backups/*.sqlite3');
        $this->assertCount(2, $remaining);

        // Fetch logs JSON endpoint
        $this->actingAs($admin)
            ->getJson(route('admin.system.health.logs.fetch', [
                'source' => 'laravel',
                'lines' => 50,
                'level' => 'all',
            ]))
            ->assertOk()
            ->assertJsonStructure(['ok', 'source', 'lines', 'level', 'content', 'fetched_at']);
    }

    public function test_vps_panel_imports_bot_commands_turnstile_and_admin_password_reset(): void
    {
        $admin = $this->makeUser('sys_admin4', 'admin', 'CurrentAdmin99!');
        $otherAdmin = $this->makeUser('sys_admin_target', 'admin', 'OldTarget99!');

        // 1. Toggle DISABLE_BOT_COMMANDS=true in isolated root.env
        $this->actingAs($admin)
            ->post(route('admin.system.health.bot-commands'), [
                'mode' => 'disable',
                'active_tab' => 'layanan',
            ])
            ->assertRedirect(route('admin.system.health', ['tab' => 'layanan']))
            ->assertSessionHas('success');
        $this->assertStringContainsString('DISABLE_BOT_COMMANDS=true', File::get($this->tempDir . '/root.env'));

        // Toggle back to enable
        $this->actingAs($admin)
            ->post(route('admin.system.health.bot-commands'), [
                'mode' => 'enable',
                'active_tab' => 'layanan',
            ])
            ->assertRedirect(route('admin.system.health', ['tab' => 'layanan']))
            ->assertSessionHas('success');
        $this->assertStringContainsString('DISABLE_BOT_COMMANDS=false', File::get($this->tempDir . '/root.env'));

        // 2. Update Cloudflare Turnstile keys in isolated web.env
        $this->actingAs($admin)
            ->post(route('admin.system.health.turnstile'), [
                'site_key' => '0x4AAAAAAATestSiteKey123',
                'secret_key' => '0x4AAAAAAATestSecretKey999',
                'active_tab' => 'pembaruan',
            ])
            ->assertRedirect(route('admin.system.health', ['tab' => 'pembaruan']))
            ->assertSessionHas('success');
        $webEnvAfter = File::get($this->tempDir . '/web.env');
        $this->assertStringContainsString('CLOUDFLARE_TURNSTILE_SITE_KEY=0x4AAAAAAATestSiteKey123', $webEnvAfter);
        $this->assertStringContainsString('CLOUDFLARE_TURNSTILE_SECRET_KEY=0x4AAAAAAATestSecretKey999', $webEnvAfter);

        // 3. Reset admin password rejects wrong current_password
        $this->actingAs($admin)
            ->post(route('admin.system.health.reset-admin-password'), [
                'current_password' => 'WrongPassword!',
                'target_admin_id' => $otherAdmin->id,
                'new_password' => 'BrandNewPass2026!',
                'new_password_confirmation' => 'BrandNewPass2026!',
                'active_tab' => 'pembaruan',
            ])
            ->assertRedirect(route('admin.system.health', ['tab' => 'pembaruan']))
            ->assertSessionHas('error');
        $otherAdmin->refresh();
        $this->assertTrue(Hash::check('OldTarget99!', $otherAdmin->password));

        // 4. Reset admin password succeeds with valid current_password and logs audit
        $this->actingAs($admin)
            ->post(route('admin.system.health.reset-admin-password'), [
                'current_password' => 'CurrentAdmin99!',
                'target_admin_id' => $otherAdmin->id,
                'new_password' => 'BrandNewPass2026!',
                'new_password_confirmation' => 'BrandNewPass2026!',
                'active_tab' => 'pembaruan',
            ])
            ->assertRedirect(route('admin.system.health', ['tab' => 'pembaruan']))
            ->assertSessionHas('success');
        $otherAdmin->refresh();
        $this->assertTrue(Hash::check('BrandNewPass2026!', $otherAdmin->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'vps_admin_password_reset']);

        // 5. Service action rejects non-whitelisted unit/action (command injection protection)
        $this->actingAs($admin)
            ->post(route('admin.system.health.service-action'), [
                'unit' => 'jualan-bot; rm -rf /',
                'action' => 'restart',
            ])
            ->assertSessionHasErrors(['unit']);

        // 6. Rollback rejects wrong password
        $this->actingAs($admin)
            ->post(route('admin.system.health.rollback'), [
                'current_password' => 'Nope!',
                'active_tab' => 'pembaruan',
            ])
            ->assertRedirect(route('admin.system.health', ['tab' => 'pembaruan']))
            ->assertSessionHas('error');

        // 7. Repair falls back safely to Laravel maintenance recovery when bridge inactive
        $this->actingAs($admin)
            ->post(route('admin.system.health.repair'), ['active_tab' => 'layanan'])
            ->assertRedirect(route('admin.system.health', ['tab' => 'layanan']))
            ->assertSessionHas('success');
        $this->assertTrue(AuditLog::where('action', 'vps_task_repair_laravel')->exists());
    }
}
