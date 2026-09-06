<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\StockUnit;
use App\Models\BotSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TwoFactorGeneratorFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::forceCreate(array_merge([
            'username' => 'user_' . uniqid(),
            'full_name' => 'Test User',
            'email' => 'user_' . uniqid() . '@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ], $attributes));
    }

    public function test_guest_is_redirected_from_2fa_generator(): void
    {
        $response = $this->get('/tools/2fa-generator');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_always_access_2fa_generator(): void
    {
        $admin = $this->createUser(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/tools/2fa-generator');
        $response->assertStatus(200);
        $response->assertSee('Generator Kode 2FA');

        $adminAlias = $this->actingAs($admin)->get('/admin/tools/2fa-generator');
        $adminAlias->assertStatus(200);
    }

    public function test_customer_can_access_by_default_when_access_mode_is_all(): void
    {
        $customer = $this->createUser(['role' => 'customer']);

        // Default setting is all
        $response = $this->actingAs($customer)->get('/tools/2fa-generator');
        $response->assertStatus(200);
        $response->assertSee('Generator Kode 2FA');
    }

    public function test_customer_is_restricted_when_access_mode_is_assigned_without_permission(): void
    {
        BotSetting::updateOrCreate(
            ['key' => 'tool_2fa_access_mode'],
            ['value' => 'assigned']
        );

        $customer = $this->createUser([
            'role' => 'customer',
            'allowed_tools' => null,
        ]);

        $response = $this->actingAs($customer)->get('/tools/2fa-generator');
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_customer_can_access_when_assigned_permission_in_restricted_mode(): void
    {
        BotSetting::updateOrCreate(
            ['key' => 'tool_2fa_access_mode'],
            ['value' => 'assigned']
        );

        $customer = $this->createUser([
            'role' => 'customer',
            'allowed_tools' => ['2fa_generator'],
        ]);

        $response = $this->actingAs($customer)->get('/tools/2fa-generator');
        $response->assertStatus(200);
        $response->assertSee('Generator Kode 2FA');
    }

    public function test_generate_ajax_endpoint_returns_valid_totp(): void
    {
        $user = $this->createUser(['role' => 'customer']);

        $response = $this->actingAs($user)->postJson('/tools/2fa-generator/generate', [
            'secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'code',
            'remaining',
            'server_time',
        ]);
        $this->assertEquals(6, strlen($response->json('code')));
    }

    public function test_batch_generate_ajax_endpoint(): void
    {
        $user = $this->createUser(['role' => 'customer']);

        $input = "user1@gmail.com|pass1|JBSWY3DPEHPK3PXP\nF2A: JBSWY3DPEHPK3PXP\ninvalid_line";
        $response = $this->actingAs($user)->postJson('/tools/2fa-generator/batch-generate', [
            'accounts' => $input,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertCount(3, $response->json('items'));
        $this->assertTrue($response->json('items.0.valid'));
        $this->assertTrue($response->json('items.1.valid'));
        $this->assertFalse($response->json('items.2.valid'));
    }

    public function test_order_detail_renders_f2a_code_for_customer(): void
    {
        $customer = $this->createUser(['role' => 'customer']);
        $product = Product::forceCreate([
            'name' => 'Akun Test',
            'description' => 'Test Desc',
            'price' => 50000,
            'is_suspended' => false,
        ]);

        $order = Order::forceCreate([
            'customer_id' => $customer->id,
            'order_ref' => 'ORD-TEST-2FA',
            'status' => 'delivered',
            'subtotal' => 50000,
            'unique_code' => 123,
            'total_amount' => 50123,
        ]);

        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'sold_order_id' => $order->id,
            'raw_text' => "Email: buyer@example.com\nPassword: secretPassword123\nF2A: JBSWY3DPEHPK3PXP",
            'is_sold' => true,
            'stock_status' => 'terjual',
        ]);

        $response = $this->actingAs($customer)->get('/orders/' . $order->id);
        $response->assertStatus(200);
        $response->assertSee('F2A: JBSWY3DPEHPK3PXP');
        $response->assertSee('F2A Code:');
        $response->assertSee('data-totp-container', false);
    }
}
