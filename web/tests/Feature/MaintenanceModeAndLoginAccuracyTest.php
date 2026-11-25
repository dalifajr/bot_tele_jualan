<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\LoginLog;
use App\Models\TelegramLoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaintenanceModeAndLoginAccuracyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Disable Turnstile in test environment
        config([
            'services.turnstile.site_key' => '1x00000000000000000000AA',
            'services.turnstile.secret_key' => '1x00000000000000000000000000000000',
        ]);
    }

    private function createUser(array $overrides = []): User
    {
        static $seq = 1;
        $username = $overrides['username'] ?? ('user_' . $seq++);

        $user = new User([
            'username' => $username,
            'full_name' => ucfirst($username),
            'email' => $username . '@test.local',
            'password' => Hash::make($overrides['password_plain'] ?? 'secret123'),
            'telegram_id' => $overrides['telegram_id'] ?? null,
            'two_factor_enabled' => $overrides['two_factor_enabled'] ?? false,
        ]);
        $user->role = $overrides['role'] ?? 'customer';
        $user->wallet_balance = 0;
        $user->save();

        return $user;
    }

    public function test_updating_maintenance_mode_in_admin_settings_clears_nav_cache(): void
    {
        $admin = $this->createUser([
            'role' => 'admin',
            'username' => 'admin_nav_tester',
        ]);

        // Seed initial maintenance mode ON and populate cache
        BotSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1']);
        Cache::put('nav:global_settings', ['maintenance_mode' => true, 'tool_2fa_access_mode' => 'all'], 30);

        $this->assertTrue(Cache::get('nav:global_settings')['maintenance_mode']);

        // Admin toggles maintenance mode OFF via settings form
        $response = $this->actingAs($admin)->post(route('admin.settings.update'), [
            'settings' => [
                'maintenance_mode' => '0',
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('0', BotSetting::where('key', 'maintenance_mode')->value('value'));

        // Cache must have been invalidated
        $this->assertNull(Cache::get('nav:global_settings'));
    }

    public function test_non_admin_password_login_rejected_and_logged_as_failed_during_maintenance(): void
    {
        BotSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1']);

        $customer = $this->createUser([
            'role' => 'customer',
            'username' => 'customer_test',
            'password_plain' => 'secret123',
        ]);

        $response = $this->post(route('login.post'), [
            'login' => 'customer_test',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();

        // A failed login log must be recorded
        $log = LoginLog::where('username_or_email', 'customer_test')->latest()->first();
        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->is_successful);
    }

    public function test_admin_can_login_during_maintenance(): void
    {
        BotSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1']);

        $admin = $this->createUser([
            'role' => 'admin',
            'username' => 'admin_hero',
            'password_plain' => 'secret123',
        ]);

        $response = $this->post(route('login.post'), [
            'login' => 'admin_hero',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);

        $log = LoginLog::where('username_or_email', 'admin_hero')->latest()->first();
        $this->assertNotNull($log);
        $this->assertTrue((bool) $log->is_successful);
    }

    public function test_telegram_callback_login_rejected_for_non_admin_during_maintenance(): void
    {
        BotSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1']);

        $customer = $this->createUser([
            'role' => 'customer',
            'telegram_id' => '123456789',
            'username' => 'tg_customer',
        ]);

        $token = 'valid_token_' . bin2hex(random_bytes(16));
        TelegramLoginToken::create([
            'token' => 'qr_' . bin2hex(random_bytes(8)),
            'link_token' => $token,
            'telegram_id' => '123456789',
            'status' => 'verified',
            'link_expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->get(route('auth.telegram.callback', ['token' => $token]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();

        $log = LoginLog::where('username_or_email', 'tg_customer')->latest()->first();
        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->is_successful);
    }

    public function test_telegram_webapp_login_rejected_with_503_during_maintenance(): void
    {
        BotSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1']);
        config(['telegram.bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11']);

        $customer = $this->createUser([
            'role' => 'customer',
            'telegram_id' => '987654321',
            'username' => 'webapp_user',
        ]);

        // Build valid signed initData
        $botToken = config('telegram.bot_token');
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $userData = json_encode(['id' => 987654321, 'username' => 'webapp_user', 'first_name' => 'Web', 'last_name' => 'App']);
        $authDate = time();
        $dataCheckString = "auth_date={$authDate}\nuser={$userData}";
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);
        $initData = "auth_date={$authDate}&user=" . urlencode($userData) . "&hash={$hash}";

        $response = $this->postJson(route('auth.telegram.webapp'), [
            'init_data' => $initData,
        ]);

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
            'maintenance' => true,
        ]);
        $this->assertGuest();

        $log = LoginLog::where('username_or_email', 'webapp_user')->latest()->first();
        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->is_successful);
        $this->assertEquals('Telegram WebApp', $log->browser);
    }

    public function test_two_factor_login_only_records_success_after_valid_otp(): void
    {
        $user = $this->createUser([
            'role' => 'customer',
            'username' => 'twofa_user',
            'password_plain' => 'secret123',
            'two_factor_enabled' => true,
            'telegram_id' => '555666777',
        ]);

        // Step 1: Password verified, user redirected to 2FA page without premature success log
        $response = $this->post(route('login.post'), [
            'login' => 'twofa_user',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('auth.two-factor'));
        $this->assertGuest();

        // No successful login log should exist yet!
        $this->assertEquals(0, LoginLog::where('username_or_email', 'twofa_user')->where('is_successful', true)->count());

        // Step 2: Incorrect 2FA code logs failure
        $responseFail = $this->withSession(['2fa_user_id' => $user->id])
            ->post(route('auth.two-factor.verify'), [
                'code' => '999999',
            ]);

        $responseFail->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertEquals(1, LoginLog::where('username_or_email', 'twofa_user')->where('is_successful', false)->count());

        // Step 3: Correct Telegram 2FA code logs success and authenticates
        $user->refresh();
        $validCode = $user->two_factor_code;
        $this->assertNotEmpty($validCode);

        $responseSuccess = $this->withSession(['2fa_user_id' => $user->id])
            ->post(route('auth.two-factor.verify'), [
                'code' => $validCode,
            ]);

        $responseSuccess->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertEquals(1, LoginLog::where('username_or_email', 'twofa_user')->where('is_successful', true)->count());
    }

    public function test_sidebar_css_uses_banner_height_variable(): void
    {
        $cssPath = public_path('css/app.css');
        $this->assertFileExists($cssPath);
        $cssContent = file_get_contents($cssPath);

        $this->assertStringContainsString('top: var(--banner-height) !important;', $cssContent);
        $this->assertStringContainsString('height: calc(100vh - var(--banner-height)) !important;', $cssContent);
    }

    public function test_admin_impersonating_user_during_maintenance_is_not_blocked_by_503(): void
    {
        BotSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1']);

        $admin = $this->createUser(['role' => 'admin', 'username' => 'admin_super']);
        $customer = $this->createUser(['role' => 'customer', 'username' => 'target_cust']);

        // Admin impersonates customer
        $response = $this->actingAs($admin)->post(route('admin.users.impersonate', ['id' => $customer->id]));
        $response->assertRedirect(route('dashboard'));

        // Customer access with admin_impersonator_id session must return 200 during maintenance (NOT 503)
        $dashboardResponse = $this->actingAs($customer)
            ->withSession(['admin_impersonator_id' => $admin->id])
            ->get(route('dashboard'));

        $this->assertNotEquals(503, $dashboardResponse->getStatusCode());
        $dashboardResponse->assertOk();

        // Customer without admin_impersonator_id must be blocked by 503
        $this->flushSession();
        $blockedResponse = $this->actingAs($customer)->get(route('dashboard'));
        $blockedResponse->assertStatus(503);
    }

    public function test_admin_impersonating_can_stop_impersonating_during_maintenance(): void
    {
        BotSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => '1']);

        $admin = $this->createUser(['role' => 'admin', 'username' => 'admin_revert']);
        $customer = $this->createUser(['role' => 'customer', 'username' => 'target_cust_revert']);

        // Stop impersonating request
        $response = $this->actingAs($customer)
            ->withSession(['admin_impersonator_id' => $admin->id])
            ->post(route('admin.users.stop-impersonating'));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('admin_impersonator_id'));
    }

    public function test_auto_release_held_funds_when_expired_and_no_active_complaints(): void
    {
        $seller = $this->createUser(['role' => 'seller', 'username' => 'seller_funds']);
        $seller->wallet_balance = 10000;
        $seller->save();

        $product = \App\Models\Product::create([
            'name' => 'Akun Garansi Test',
            'price' => 50000,
            'creator_id' => $seller->id,
            'warranty_days' => 3,
        ]);

        $order = \App\Models\Order::create([
            'order_ref' => 'ORD-TEST-001',
            'customer_id' => $seller->id,
            'subtotal' => 50000,
            'unique_code' => 0,
            'total_amount' => 50000,
            'status' => 'delivered',
            'created_at' => now()->subDays(5),
        ]);

        $fund = \App\Models\HeldFund::create([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount' => 45000,
            'status' => 'held',
            'release_at' => now()->subHour(),
        ]);

        $result = \App\Services\HeldFundService::releaseEligibleFunds();

        $this->assertEquals(1, $result['released_count']);
        $this->assertEquals(45000, $result['released_amount']);
        $this->assertEquals(0, $result['skipped_complaints']);

        $fund->refresh();
        $this->assertEquals('released', $fund->status);

        $seller->refresh();
        $this->assertEquals(55000, $seller->wallet_balance);
    }

    public function test_auto_release_skips_held_funds_with_active_complaint(): void
    {
        $seller = $this->createUser(['role' => 'seller', 'username' => 'seller_dispute']);
        $seller->wallet_balance = 0;
        $seller->save();

        $product = \App\Models\Product::create([
            'name' => 'Akun Garansi Dispute',
            'price' => 30000,
            'creator_id' => $seller->id,
            'warranty_days' => 3,
        ]);

        $order = \App\Models\Order::create([
            'order_ref' => 'ORD-DISPUTE-001',
            'customer_id' => $seller->id,
            'subtotal' => 30000,
            'unique_code' => 0,
            'total_amount' => 30000,
            'status' => 'delivered',
            'created_at' => now()->subDays(5),
        ]);

        $fund = \App\Models\HeldFund::create([
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount' => 25000,
            'status' => 'held',
            'release_at' => now()->subHour(),
        ]);

        // Active complaint exists
        \App\Models\ComplaintCase::create([
            'complaint_ref' => 'CMP-001',
            'customer_id' => $seller->id,
            'customer_telegram_id' => '123456789',
            'order_id' => $order->id,
            'order_ref_snapshot' => $order->order_ref,
            'status' => 'open',
            'complaint_text' => 'Akun bermasalah',
        ]);

        $result = \App\Services\HeldFundService::releaseEligibleFunds();

        $this->assertEquals(0, $result['released_count']);
        $this->assertEquals(1, $result['skipped_complaints']);

        $fund->refresh();
        $this->assertEquals('held', $fund->status);

        $seller->refresh();
        $this->assertEquals(0, $seller->wallet_balance);
    }
}
