<?php

namespace Tests\Feature;

use App\Models\HeldFund;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerBankAccount;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerFinanceUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::forceCreate([
            'username' => 'seller_finance_ui',
            'full_name' => 'Seller Finance UI',
            'email' => 'seller_finance_ui@test.com',
            'role' => 'seller',
            'wallet_balance' => 6770,
            'platform_fee_percent' => 5,
            'password' => bcrypt('password'),
        ]);
    }

    public function test_seller_finance_renders_flat_material_you_without_inline_gradients_and_with_mobile_cards(): void
    {
        $buyer = User::forceCreate([
            'username' => 'buyer_fin_ui',
            'full_name' => 'Buyer Test',
            'email' => 'buyer_fin_ui@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $product = Product::create([
            'name' => 'Canva Pro 1 Bulan',
            'price' => 25000,
            'creator_id' => $this->seller->id,
        ]);

        $order = Order::forceCreate([
            'customer_id' => $buyer->id,
            'order_ref' => 'ORD-FIN-001',
            'subtotal' => 25000,
            'discount_amount' => 0,
            'unique_code' => 0,
            'total_amount' => 25000,
            'status' => 'completed',
        ]);

        HeldFund::forceCreate([
            'seller_id' => $this->seller->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount' => 18060,
            'status' => 'held',
            'release_at' => now()->addDays(3),
        ]);

        SellerBankAccount::create([
            'user_id' => $this->seller->id,
            'bank_name' => 'SEABANK',
            'account_number' => '901493958846',
            'account_holder' => 'CUT AMALIA',
        ]);

        WithdrawalRequest::forceCreate([
            'seller_id' => $this->seller->id,
            'bank_name' => 'SEABANK',
            'account_number' => '901493958846',
            'account_holder' => 'CUT AMALIA',
            'amount' => 18000,
            'status' => 'approved',
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($this->seller)->get(route('seller.finance.index'));

        $response->assertStatus(200);
        $response->assertSee('Rp 6.770');
        $response->assertSee('Rp 18.060');
        $response->assertSee('SEABANK');
        $response->assertSee('901493958846');
        $response->assertSee('ORD-FIN-001');
        $response->assertSee('m3-segmented-nav');
        $response->assertSee('m3-mobile-item-card');
        $response->assertDontSee('linear-gradient(135deg');
        $response->assertDontSee('border-radius: 4px');
    }

    public function test_seller_bank_accounts_and_dashboard_render_consistently_without_legacy_gradients(): void
    {
        SellerBankAccount::create([
            'user_id' => $this->seller->id,
            'bank_name' => 'BCA',
            'account_number' => '8012345678',
            'account_holder' => 'Seller Finance UI',
        ]);

        $bankRes = $this->actingAs($this->seller)->get(route('seller.bank-accounts.index'));
        $bankRes->assertStatus(200);
        $bankRes->assertSee('BCA');
        $bankRes->assertSee('8012345678');
        $bankRes->assertSee('m3-segmented-nav');
        $bankRes->assertSee('m3-mobile-item-card');
        $bankRes->assertDontSee('border-radius: 4px');

        $dashRes = $this->actingAs($this->seller)->get(route('seller.dashboard'));
        $dashRes->assertStatus(200);
        $dashRes->assertDontSee('linear-gradient(135deg, #1e3c72');
        $dashRes->assertDontSee('linear-gradient(135deg, #b45309');
    }
}
