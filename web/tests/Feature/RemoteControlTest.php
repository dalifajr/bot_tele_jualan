<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemoteControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'username' => 'admin_rc_test',
            'full_name' => 'Admin Remote Control',
            'email' => 'admin_rc@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::forceCreate([
            'username' => 'customer_rc_test',
            'full_name' => 'Customer User',
            'email' => 'customer_rc@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_guest_and_non_admin_cannot_access_remote_control()
    {
        // 1. Guest
        $guestResponse = $this->get(route('admin.remote-control.index'));
        $guestResponse->assertRedirect(route('login'));

        // 2. Non-admin (Customer)
        $customerResponse = $this->actingAs($this->customer)->get(route('admin.remote-control.index'));
        $customerResponse->assertRedirect(route('dashboard'));

        // 3. API devices non-admin
        $customerApi = $this->actingAs($this->customer)->getJson(route('admin.remote-control.devices'));
        $customerApi->assertStatus(403);

        // 4. Update settings non-admin
        $customerPost = $this->actingAs($this->customer)->post(route('admin.remote-control.settings.update'), [
            'device_auth_token' => 'HACK_TOKEN',
            'admin_secret_key' => 'HACK_KEY',
            'relay_port' => 8099,
        ]);
        $customerPost->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_access_remote_control_page()
    {
        Http::fake([
            '*/api/devices' => Http::response([
                'status' => 'success',
                'count' => 1,
                'devices' => [
                    [
                        'device_id' => 'ANDROID_TEST_DEV',
                        'device_name' => 'Xiaomi Root Testing',
                        'ip' => '192.168.1.50',
                        'connected_at' => time(),
                        'controllers_count' => 0,
                    ]
                ]
            ], 200),
            '*/api/health' => Http::response([
                'status' => 'healthy',
                'active_devices' => 1,
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.remote-control.index'));
        $response->assertStatus(200);
        $response->assertSee('Remote Control Perangkat Android (Root)');
        $response->assertSee('Xiaomi Root Testing');
        // Pastikan tidak menggunakan port 8080 sebagai default loopback
        $response->assertDontSee(':8080');
        $response->assertSee(':8099');
    }

    public function test_admin_can_poll_devices_json()
    {
        Http::fake([
            '*/api/devices' => Http::response([
                'status' => 'success',
                'count' => 1,
                'devices' => [
                    [
                        'device_id' => 'ANDROID_TEST_DEV',
                        'device_name' => 'Pixel 8 Root',
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.remote-control.devices'));
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'count' => 1,
        ]);
    }

    public function test_admin_can_update_auth_settings()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.remote-control.settings.update'), [
            'device_auth_token' => 'MY_NEW_SUPER_SECRET_TOKEN',
            'admin_secret_key' => 'MY_NEW_ADMIN_SECRET_KEY',
            'relay_port' => 8099,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('MY_NEW_SUPER_SECRET_TOKEN', BotSetting::getSetting('remote_device_auth_token'));
        $this->assertEquals('MY_NEW_ADMIN_SECRET_KEY', BotSetting::getSetting('remote_admin_secret_key'));
        $this->assertEquals(8099, (int)BotSetting::getSetting('remote_relay_port'));
    }

    public function test_admin_can_fetch_service_status_json()
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.remote-control.service-status'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'status' => [
                'is_installed',
                'is_running',
                'is_nginx_configured',
                'port',
                'detail',
                'ssh_command',
            ]
        ]);
    }

    public function test_admin_can_trigger_install_and_restart_service()
    {
        $installResponse = $this->actingAs($this->admin)->postJson(route('admin.remote-control.install-service'));
        $installResponse->assertStatus(200);
        $installResponse->assertJsonStructure([
            'success',
            'message',
            'output',
            'status',
        ]);

        $restartResponse = $this->actingAs($this->admin)->postJson(route('admin.remote-control.restart-service'));
        $restartResponse->assertStatus(200);
        $restartResponse->assertJsonStructure([
            'success',
            'message',
            'status',
        ]);
    }
}
