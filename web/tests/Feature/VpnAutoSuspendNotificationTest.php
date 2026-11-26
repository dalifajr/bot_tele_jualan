<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpnAccount;
use App\Models\VpnServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VpnAutoSuspendNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private VpnServer $server;
    private VpnAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'test-bot-token-12345',
            'app.url' => 'https://belajaridn.id',
        ]);

        $this->admin = User::forceCreate([
            'username' => 'admin_autosusp',
            'full_name' => 'Admin Store',
            'email' => 'admin_autosusp@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'telegram_id' => '999000111',
            'is_suspended' => false,
        ]);

        $this->customer = User::forceCreate([
            'username' => 'cust_autosusp',
            'full_name' => 'Budi Pelanggan',
            'email' => 'cust_autosusp@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'telegram_id' => '777888999',
            'is_suspended' => false,
        ]);

        $this->server = VpnServer::create([
            'name' => 'Oracle Batam ID',
            'code' => 'batam-01',
            'region' => 'ID',
            'driver' => VpnServer::DRIVER_VPNXRAY_API,
            'base_url' => 'https://dev.serverope.tech',
            'api_token' => 'vpnx_secret_token_autosusp',
            'hmac_secret' => 'hmac_secret_key_autosusp_123456',
            'timeout_seconds' => 10,
            'verify_ssl' => true,
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->account = VpnAccount::create([
            'user_id' => $this->customer->id,
            'vpn_server_id' => $this->server->id,
            'protocol' => 'vless',
            'username' => 'budi_vless01',
            'status' => 'active',
            'quota_gb' => 0,
            'ip_limit' => 1,
            'expired_at' => now()->addDays(25),
        ]);
    }

    public function test_auto_suspend_sync_notifies_customer_and_admin_once_with_miniapp_and_locks_self_unsuspend(): void
    {
        Http::fake([
            'https://dev.serverope.tech/api/v1/vpn/accounts*' => Http::response([
                'success' => true,
                'data' => [
                    'accounts' => [
                        [
                            'protocol' => 'vless',
                            'username' => 'budi_vless01',
                            'status' => 'suspended',
                            'active' => false,
                            'admin_suspended' => false,
                            'auto_suspended' => true,
                            'suspend_reason' => 'auto-limit:vless:ip=3/1:strike=2',
                            'expired_at' => now()->addDays(25)->format('Y-m-d'),
                            'quota_gb' => 0,
                            'ip_limit' => 1,
                        ],
                    ],
                ],
            ], 200),
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 42],
            ], 200),
        ]);

        // 1. Run scheduled command vpn:sync-status
        $this->artisan('vpn:sync-status')
            ->assertExitCode(0);

        $this->account->refresh();
        $this->assertSame('suspended', $this->account->status);
        $this->assertSame('system', $this->account->suspended_by_role);
        $this->assertTrue($this->account->isSystemSuspended());
        $this->assertTrue($this->account->isAdminSuspended());
        $this->assertFalse($this->account->isCustomerSuspended());
        $this->assertStringContainsString('3 IP terdeteksi', (string) $this->account->suspended_reason);
        $this->assertStringContainsString('1 IP', (string) $this->account->suspended_reason);

        // 2. Customer Telegram bot received notification with MiniApp buttons
        Http::assertSent(function ($req) {
            if (!str_contains($req->url(), '/sendMessage')) {
                return false;
            }
            $data = $req->data();
            if ((string) ($data['chat_id'] ?? '') !== '777888999') {
                return false;
            }
            $text = (string) ($data['text'] ?? '');
            if (!str_contains($text, 'Disuspend Otomatis oleh Sistem')
                || !str_contains($text, 'budi_vless01')
                || !str_contains($text, '3 IP terdeteksi')) {
                return false;
            }
            $markup = $data['reply_markup'] ?? null;
            if (is_string($markup)) {
                $markup = json_decode($markup, true);
            }
            $buttons = $markup['inline_keyboard'][0] ?? [];
            return count($buttons) === 2
                && isset($buttons[0]['web_app']['url'])
                && isset($buttons[1]['web_app']['url']);
        });

        // 3. Admin Telegram bot also received auto-suspend alert
        Http::assertSent(function ($req) {
            if (!str_contains($req->url(), '/sendMessage')) {
                return false;
            }
            $data = $req->data();
            return (string) ($data['chat_id'] ?? '') === '999000111'
                && str_contains((string) ($data['text'] ?? ''), 'Auto-Suspend Sistem VPN')
                && str_contains((string) ($data['text'] ?? ''), 'budi_vless01');
        });

        // 4. Customer website Notification Hub received database notification
        $notifs = $this->customer->notifications()->get();
        $this->assertCount(1, $notifs);
        $this->assertSame('vpn_system_suspended', $notifs->first()->data['type'] ?? null);
        $this->assertStringContainsString('Disuspend Otomatis oleh Sistem', (string) ($notifs->first()->data['title'] ?? ''));

        // 5. Anti-spam: re-running vpn:sync-status twice more does NOT send duplicate notifications
        $this->artisan('vpn:sync-status')->assertExitCode(0);
        $this->artisan('vpn:sync-status')->assertExitCode(0);

        $customerTeleCount = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/sendMessage')
                && (string) ($pair[0]->data()['chat_id'] ?? '') === '777888999')
            ->count();
        $this->assertSame(1, $customerTeleCount, 'Customer Telegram notification must only be sent once per suspension.');
        $this->assertSame(1, $this->customer->notifications()->count());

        // 6. Anti-bypass: customer CANNOT self-unsuspend a system auto-suspended account
        $unsuspendResp = $this->actingAs($this->customer)
            ->post(route('vpn.unsuspend', $this->account->id));
        $unsuspendResp->assertRedirect();
        $unsuspendResp->assertSessionHas('error');
        $this->account->refresh();
        $this->assertSame('suspended', $this->account->status);

        // JSON fetchConfig is also blocked (403)
        $this->actingAs($this->customer)
            ->getJson(route('vpn.config', $this->account->id))
            ->assertStatus(403);
    }

    public function test_remote_unsuspend_sync_notifies_customer_and_clears_system_lock(): void
    {
        $this->account->forceFill([
            'status' => 'suspended',
            'suspended_by_role' => 'system',
            'suspended_reason' => 'Pelanggaran batas multi-login (3 IP terdeteksi, batas maksimal 1 IP)',
            'suspended_at' => now()->subMinutes(10),
        ])->save();

        Http::fake([
            'https://dev.serverope.tech/api/v1/vpn/accounts*' => Http::response([
                'success' => true,
                'data' => [
                    'accounts' => [
                        [
                            'protocol' => 'vless',
                            'username' => 'budi_vless01',
                            'status' => 'active',
                            'active' => true,
                            'admin_suspended' => false,
                            'auto_suspended' => false,
                            'suspend_reason' => '',
                            'expired_at' => now()->addDays(25)->format('Y-m-d'),
                            'quota_gb' => 0,
                            'ip_limit' => 1,
                        ],
                    ],
                ],
            ], 200),
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 43],
            ], 200),
        ]);

        $this->artisan('vpn:sync-status')->assertExitCode(0);

        $this->account->refresh();
        $this->assertSame('active', $this->account->status);
        $this->assertNull($this->account->suspended_by_role);
        $this->assertNull($this->account->suspended_reason);

        Http::assertSent(function ($req) {
            if (!str_contains($req->url(), '/sendMessage')) {
                return false;
            }
            $data = $req->data();
            return (string) ($data['chat_id'] ?? '') === '777888999'
                && str_contains((string) ($data['text'] ?? ''), 'Suspend Akun VPN Anda Telah Dibuka');
        });
    }
}
