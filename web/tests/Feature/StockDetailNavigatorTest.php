<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockDetailNavigatorTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $seller;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'full_name' => 'Admin Stock',
            'username' => 'adminstock',
            'email' => 'adminstock@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->seller = User::forceCreate([
            'full_name' => 'Seller Stock',
            'username' => 'sellerstock',
            'email' => 'sellerstock@example.com',
            'password' => bcrypt('password123'),
            'role' => 'seller',
        ]);

        $this->product = Product::create([
            'name' => 'GitHub Student Developer Pack',
            'price' => 50000,
            'description' => 'Akun GitHub Student Pack',
            'user_id' => $this->seller->id,
            'is_suspended' => false,
        ]);

        StockUnit::create([
            'product_id' => $this->product->id,
            'seller_id' => $this->seller->id,
            'raw_text' => "Username: user_one\nPassword: pass_one\nF2A: JBSWY3DPEHPK3PXP",
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);

        StockUnit::create([
            'product_id' => $this->product->id,
            'seller_id' => $this->seller->id,
            'raw_text' => "Username: user_two\nPassword: pass_two\nF2A: JBSWY3DPEHPK3PXQ",
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);
    }

    public function test_admin_stock_page_renders_detail_navigation_controls()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.stock.index'));

        $response->assertStatus(200);
        $response->assertSee('id="detailStockModal"', false);
        $response->assertSee('id="stockDetailPrevBtn"', false);
        $response->assertSee('id="stockDetailNextBtn"', false);
        $response->assertSee('id="stockDetailCounterBadge"', false);
        $response->assertSee('id="stockDetailViewerContainer"', false);
        $response->assertSee('btn-view-stock-detail', false);
        $response->assertSee('stock-detail-source-item', false);
        $response->assertSee('id="btn-save-draft"', false);
        $response->assertSee('id="btn-discard-draft"', false);
        $response->assertSee('id="stock-preview-container"', false);
        $response->assertDontSee('id="stock-live-2fa-alert"', false);
        $response->assertSee('user_one');
        $response->assertSee('user_two');
    }

    public function test_seller_stock_page_renders_detail_navigation_controls()
    {
        $response = $this->actingAs($this->seller)->get(route('seller.stock.index'));

        $response->assertStatus(200);
        $response->assertSee('id="detailStockModal"', false);
        $response->assertSee('id="stockDetailPrevBtn"', false);
        $response->assertSee('id="stockDetailNextBtn"', false);
        $response->assertSee('id="stockDetailCounterBadge"', false);
        $response->assertSee('id="stockDetailViewerContainer"', false);
        $response->assertSee('btn-view-stock-detail', false);
        $response->assertSee('stock-detail-source-item', false);
        $response->assertSee('id="btn-save-draft"', false);
        $response->assertSee('id="btn-discard-draft"', false);
        $response->assertSee('id="stock-preview-container"', false);
        $response->assertDontSee('id="stock-live-2fa-alert"', false);
        $response->assertSee('user_one');
        $response->assertSee('user_two');
    }

    public function test_account_credential_viewer_has_no_leading_indentation_or_spaces()
    {
        $rawWithTabs = "   \t  email: test@example.com\npass: 123456\nF2A: JBSWY3DPEHPK3PXP";
        $rendered = \App\Services\TwoFactorService::renderWith2fa($rawWithTabs, true);
        
        $this->assertStringStartsWith('email: test@example.com', $rendered);
        $this->assertStringNotContainsString("\t", $rendered);

        $viewHtml = (string) $this->blade('<x-account-credential-viewer :rawText="$raw" />', ['raw' => $rawWithTabs]);
        $this->assertMatchesRegularExpression('/>email: test@example\.com/', $viewHtml);
    }
}
