<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\EscrowTransaction;
use App\Models\LoginLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityAndVisualHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::forceCreate(array_merge([
            'username' => 'u_' . uniqid(),
            'full_name' => 'User Test',
            'email' => 'u_' . uniqid() . '@example.com',
            'password' => Hash::make('ValidPass123!'),
            'role' => 'customer',
            'has_custom_password' => true,
        ], $overrides));
    }

    public function test_security_headers_are_attached_on_web_responses(): void
    {
        $response = $this->get('/catalog');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self' https://web.telegram.org https://*.telegram.org", $csp);
    }

    public function test_escrow_public_token_enforces_opposite_role_and_locks_bank_after_delivery(): void
    {
        $buyer = $this->makeUser();
        $trx = EscrowTransaction::forceCreate([
            'transaction_code' => 'RKB-SEC-001',
            'creator_id' => $buyer->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $buyer->id,
            'buyer_name' => 'Buyer One',
            'buyer_contact' => '@buyer1',
            'seller_name' => 'External Seller',
            'seller_contact' => '@extseller',
            'title' => 'Account Escrow',
            'amount' => 100000,
            'fee_amount' => 1500,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 101500,
            'total_seller_receive' => 100000,
            'status' => EscrowTransaction::STATUS_FUNDS_HELD,
            'public_token' => 'public-token-role-check-001',
        ]);

        // Public link belongs to seller (since creator is buyer): cannot call buyer confirm or deposit
        $this->post(route('escrow.public.confirm', $trx->public_token))->assertStatus(403);
        $this->post(route('escrow.public.deposit', $trx->public_token))->assertStatus(403);

        // Seller delivers first time -> sets bank account
        $this->post(route('escrow.public.deliver', $trx->public_token), [
            'delivery_text' => 'Credentials v1',
            'seller_bank_name' => 'BCA',
            'seller_account_number' => '111222333',
            'seller_account_holder' => 'Seller Original',
        ])->assertRedirect();

        $trx->refresh();
        $this->assertEquals(EscrowTransaction::STATUS_DELIVERED, $trx->status);
        $this->assertEquals('111222333', $trx->seller_account_number);

        // Second delivery update cannot silently overwrite already-set payout bank account
        $this->post(route('escrow.public.deliver', $trx->public_token), [
            'delivery_text' => 'Credentials v2',
            'seller_bank_name' => 'BRI',
            'seller_account_number' => '999888777',
            'seller_account_holder' => 'Attacker',
        ])->assertRedirect();

        $trx->refresh();
        $this->assertEquals('111222333', $trx->seller_account_number);
        $this->assertEquals('BCA', $trx->seller_bank_name);
    }

    public function test_non_admin_cannot_block_arbitrary_ip_or_cause_global_dos(): void
    {
        $attacker = $this->makeUser();
        $victim = $this->makeUser();

        // Arbitrary IP not in attacker's LoginLog -> 403
        $this->actingAs($attacker)
            ->post(route('profile.logins.block-ip'), [
                'ip_address' => '198.51.100.77',
                'duration' => 7,
            ])
            ->assertStatus(403);

        $this->assertFalse(Cache::has('blocked_ip:198.51.100.77'));

        // Record IP in attacker's LoginLog -> block succeeds ONLY scoped to attacker
        LoginLog::create([
            'user_id' => $attacker->id,
            'username_or_email' => $attacker->username,
            'ip_address' => '198.51.100.88',
            'is_successful' => false,
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
        ]);

        $this->actingAs($attacker)
            ->post(route('profile.logins.block-ip'), [
                'ip_address' => '198.51.100.88',
                'duration' => 7,
            ])
            ->assertRedirect();

        $this->assertFalse(Cache::has('blocked_ip:198.51.100.88'));
        $this->assertTrue(Cache::has('blocked_user_ip:' . $attacker->id . ':198.51.100.88'));

        // Victim from same IP is NOT blocked
        $this->actingAs($victim)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.88'])
            ->get('/catalog')
            ->assertStatus(200);

        // Attacker from that IP IS blocked
        $this->actingAs($attacker)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.88'])
            ->get('/catalog')
            ->assertStatus(403);
    }

    public function test_manual_telegram_id_claim_and_username_oracle_are_blocked(): void
    {
        $victim = $this->makeUser(['username' => 'secret_victim_user', 'telegram_id' => 777888999]);
        $attacker = $this->makeUser(['telegram_id' => null]);

        // Public API must not leak victim's username
        $checkRes = $this->postJson(route('api.check.telegram'), ['telegram_id' => 777888999]);
        $checkRes->assertStatus(200)->assertJson(['available' => false]);
        $this->assertStringNotContainsString('secret_victim_user', (string) $checkRes->json('message'));

        // Registration ignores manually supplied telegram_id
        $this->post(route('register.post'), [
            'full_name' => 'New Attacker',
            'username' => 'new_attacker_tg',
            'email' => 'new_attacker_tg@example.com',
            'telegram_id' => 555444333,
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertNull(User::where('username', 'new_attacker_tg')->first()->telegram_id);

        // Profile update rejects manually setting a new telegram_id
        $this->actingAs($attacker)
            ->post(route('profile.update'), [
                'full_name' => 'Attacker Name',
                'email' => $attacker->email,
                'telegram_id' => 444555666,
            ])
            ->assertSessionHasErrors(['telegram_id']);

        $this->assertNull($attacker->fresh()->telegram_id);
    }

    public function test_password_change_requires_current_password_and_unlink_requires_custom_password(): void
    {
        $userWithPassword = $this->makeUser(['has_custom_password' => true, 'password' => Hash::make('OldPass123!')]);

        // Wrong current password rejected
        $this->actingAs($userWithPassword)
            ->post(route('profile.password.update'), [
                'current_password' => 'WrongPass123!',
                'password' => 'NewPass123!',
                'password_confirmation' => 'NewPass123!',
            ])
            ->assertSessionHasErrors(['current_password']);

        // Correct current password accepted
        $this->actingAs($userWithPassword)
            ->post(route('profile.password.update'), [
                'current_password' => 'OldPass123!',
                'password' => 'NewPass123!',
                'password_confirmation' => 'NewPass123!',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPass123!', $userWithPassword->fresh()->password));

        // Telegram-only user without custom password cannot unlink Telegram
        $tgOnlyUser = $this->makeUser(['telegram_id' => 1122334455, 'has_custom_password' => false]);
        $this->actingAs($tgOnlyUser)
            ->post(route('profile.telegram.unlink'))
            ->assertSessionHas('error');

        $this->assertEquals(1122334455, $tgOnlyUser->fresh()->telegram_id);
    }

    public function test_two_factor_invalidates_code_after_five_wrong_attempts(): void
    {
        $user = $this->makeUser([
            'two_factor_enabled' => true,
            'two_factor_code' => '654321',
            'two_factor_expires_at' => now()->addMinutes(5),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $res = $this->withSession(['2fa_user_id' => $user->id])
                ->post(route('auth.two-factor.verify'), ['code' => '000000']);
        }

        $res->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->two_factor_code);
    }

    public function test_webhooks_fail_closed_without_valid_secret_and_reject_amount_mismatch(): void
    {
        // PaymentListener fails closed with 503 if secret is not configured (or default 'change-me')
        BotSetting::updateOrCreate(['key' => 'listener_shared_secret'], ['value' => 'change-me']);
        $this->postJson('/listener/test-connection', ['device_id' => 'd1'], [
            'X-Timestamp' => (string) time(),
            'X-Signature' => 'anything',
        ])->assertStatus(503);

        // Midtrans rejects amount mismatch even with valid signature
        config(['services.midtrans.server_key' => 'test-midtrans-secret-key']);
        $customer = $this->makeUser();
        $order = Order::create([
            'order_ref' => 'ORD-MID-001',
            'customer_id' => $customer->id,
            'subtotal' => 50000,
            'unique_code' => 100,
            'total_amount' => 50100,
            'status' => 'pending_payment',
        ]);

        $grossAmount = '1000.00'; // tampered amount
        $sig = hash('sha512', $order->order_ref . '200' . $grossAmount . 'test-midtrans-secret-key');
        $this->postJson('/api/payment/midtrans/callback', [
            'order_id' => $order->order_ref,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'signature_key' => $sig,
        ])->assertStatus(400);
    }

    public function test_promo_rejects_javascript_url_and_login_sanitizes_announcement_html(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.promos.store'), [
            'title' => 'Malicious Promo',
            'badge_text' => 'XSS',
            'badge_color' => '#0b57d0',
            'description' => 'Test',
            'icon' => 'fas fa-tags',
            'button_text' => 'Click',
            'button_url' => 'javascript:alert(1)',
            'gradient_theme' => 'blue',
            'order_index' => 1,
            'is_active' => '1',
        ])->assertSessionHasErrors(['button_url']);

        BotSetting::updateOrCreate(
            ['key' => 'web_announcement'],
            ['value' => '<b>Halo</b><script>alert("xss")</script><a href="javascript:evil()" onclick="evil()">Link</a>']
        );

        auth()->logout();
        $loginRes = $this->get(route('login'));
        $loginRes->assertStatus(200);
        $loginRes->assertSee('<b>Halo</b>', false);
        $loginRes->assertDontSee('alert("xss")', false);
        $loginRes->assertDontSee('onclick="evil()"', false);
        $loginRes->assertDontSee('javascript:evil()', false);
    }

    public function test_catalog_supports_server_side_sorting_options(): void
    {
        $cheap = Product::create(['name' => 'Cheap Ready', 'price' => 10000, 'is_suspended' => false]);
        $expensive = Product::create(['name' => 'Expensive Ready', 'price' => 90000, 'is_suspended' => false]);
        StockUnit::create(['product_id' => $cheap->id, 'raw_text' => 'a', 'is_sold' => false, 'stock_status' => 'ready']);
        StockUnit::create(['product_id' => $expensive->id, 'raw_text' => 'b', 'is_sold' => false, 'stock_status' => 'ready']);

        $resAsc = $this->get(route('catalog.index', ['sort' => 'price_asc']));
        $resAsc->assertStatus(200);
        $this->assertEquals([$cheap->id, $expensive->id], $resAsc->viewData('products')->pluck('id')->toArray());

        $resDesc = $this->get(route('catalog.index', ['sort' => 'price_desc']));
        $resDesc->assertStatus(200);
        $this->assertEquals([$expensive->id, $cheap->id], $resDesc->viewData('products')->pluck('id')->toArray());
    }
}
