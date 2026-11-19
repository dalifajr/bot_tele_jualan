<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\EscrowTransaction;
use App\Models\HeldFund;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ResellerApiKey;
use App\Models\ResellerApiLog;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5ResellerApiAndFinanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role = 'customer', array $attrs = []): User
    {
        $user = new User();
        $user->telegram_id = $attrs['telegram_id'] ?? random_int(100000000, 999999999);
        $user->username = $attrs['username'] ?? ($role . '_' . random_int(1000, 9999));
        $user->email = $attrs['email'] ?? ($user->username . '@test.local');
        $user->password = bcrypt('password');
        $user->full_name = $attrs['full_name'] ?? ucfirst($role) . ' Test';
        $user->role = $role;
        $user->customer_wallet_balance = $attrs['customer_wallet_balance'] ?? 0;
        $user->wallet_balance = $attrs['wallet_balance'] ?? 0;
        $user->platform_fee_percent = $attrs['platform_fee_percent'] ?? 10;
        $user->allowed_tools = $attrs['allowed_tools'] ?? null;
        $user->is_suspended = $attrs['is_suspended'] ?? false;
        $user->save();

        return $user;
    }

    public function test_reseller_api_portal_key_lifecycle_and_admin_access_policy(): void
    {
        $admin = $this->makeUser('admin');
        $seller = $this->makeUser('seller', ['customer_wallet_balance' => 150000]);
        $customer = $this->makeUser('customer', ['customer_wallet_balance' => 150000]);

        // Default policy ('seller_admin'): OFF for normal customers, ON for seller & admin
        $this->assertFalse($customer->canUseResellerApi());
        $this->assertTrue($seller->canUseResellerApi());
        $this->assertTrue($admin->canUseResellerApi());

        $this->actingAs($customer)
            ->get(route('developer.api.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($customer)
            ->post(route('developer.api.keys.store'), ['name' => 'Unauthorized Key'])
            ->assertStatus(403);

        // Seller can open portal & create API key by default
        $this->actingAs($seller)
            ->get(route('developer.api.index'))
            ->assertStatus(200)
            ->assertSee('API Reseller (H2H)');

        $createResp = $this->actingAs($seller)
            ->post(route('developer.api.keys.store'), [
                'name' => 'Bot Reseller Utama',
                'allowed_ips' => '127.0.0.1, 10.20.30.0/24',
                'rate_limit_per_minute' => 45,
            ]);

        $createResp->assertRedirect(route('developer.api.index', ['tab' => 'keys']));
        $plainKey = session('new_api_key');
        $this->assertNotEmpty($plainKey);
        $this->assertStringStartsWith('rsk_live_', $plainKey);

        $key = ResellerApiKey::where('user_id', $seller->id)->firstOrFail();
        $this->assertSame(hash('sha256', $plainKey), $key->key_hash);
        $this->assertSame(['127.0.0.1', '10.20.30.0/24'], $key->allowed_ips);
        $this->assertSame(45, $key->rate_limit_per_minute);

        // Rotate key invalidates old hash
        $oldHash = $key->key_hash;
        $this->actingAs($seller)
            ->post(route('developer.api.keys.rotate', $key->id))
            ->assertRedirect(route('developer.api.index', ['tab' => 'keys']));
        $key->refresh();
        $this->assertNotSame($oldHash, $key->key_hash);

        // Granting 'reseller_api' in allowed_tools enables customer even under default seller_admin mode
        $customer->allowed_tools = ['reseller_api'];
        $customer->save();
        $this->assertTrue($customer->canUseResellerApi());

        $this->actingAs($customer)
            ->get(route('developer.api.index'))
            ->assertStatus(200);

        // Admin switches global mode to 'selected' -> seller without allowed_tools loses access
        $this->actingAs($admin)
            ->post(route('developer.api.admin.access-mode'), ['access_mode' => 'selected'])
            ->assertRedirect(route('developer.api.index', ['tab' => 'admin']));

        $this->assertSame('selected', BotSetting::where('key', 'reseller_api_access_mode')->value('value'));
        $this->assertFalse($seller->fresh()->canUseResellerApi());

        $this->actingAs($seller)
            ->get(route('developer.api.index'))
            ->assertRedirect(route('dashboard'));

        // Admin switches global mode to 'all' -> regular customer without allowed_tools gains access
        $customer2 = $this->makeUser('customer');
        $this->actingAs($admin)
            ->post(route('developer.api.admin.access-mode'), ['access_mode' => 'all'])
            ->assertRedirect(route('developer.api.index', ['tab' => 'admin']));
        $this->assertTrue($customer2->canUseResellerApi());

        // Admin revokes key
        $this->actingAs($admin)
            ->post(route('developer.api.admin.keys.revoke', $key->id))
            ->assertRedirect(route('developer.api.index', ['tab' => 'admin']));

        $key->refresh();
        $this->assertFalse($key->is_active);
        $this->assertNotNull($key->revoked_at);
    }

    public function test_reseller_rest_api_auth_whitelist_instant_order_and_idempotency(): void
    {
        $unauthorizedCustomer = $this->makeUser('customer', ['customer_wallet_balance' => 100000]);
        $unauthPair = ResellerApiKey::generateKeyPair();
        ResellerApiKey::create([
            'user_id' => $unauthorizedCustomer->id,
            'name' => 'Legacy Customer Key',
            'key_prefix' => $unauthPair['prefix'],
            'key_hash' => $unauthPair['hash'],
            'is_active' => true,
        ]);

        // Even if a key row exists for a normal customer, REST API denies with 403 by default
        $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer ' . $unauthPair['plain']])
            ->assertStatus(403)
            ->assertJsonPath('error', 'access_denied');

        $reseller = $this->makeUser('customer', [
            'customer_wallet_balance' => 100000,
            'allowed_tools' => ['reseller_api'],
        ]);
        $seller = $this->makeUser('seller', ['platform_fee_percent' => 10]);

        $product = Product::create([
            'name' => 'GitHub Student Fresh',
            'price' => 30000,
            'description' => 'Akun siap pakai',
            'is_suspended' => false,
            'creator_id' => $seller->id,
            'warranty_days' => 7,
        ]);

        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'user1@mail.test|pass123|2FASECRET1',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller->id,
        ]);
        StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'user2@mail.test|pass456|2FASECRET2',
            'is_sold' => false,
            'stock_status' => 'ready',
            'seller_id' => $seller->id,
        ]);

        $pair = ResellerApiKey::generateKeyPair();
        $apiKey = ResellerApiKey::create([
            'user_id' => $reseller->id,
            'name' => 'AutoBot Key',
            'key_prefix' => $pair['prefix'],
            'key_hash' => $pair['hash'],
            'allowed_ips' => ['127.0.0.1'],
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);

        // 1. Reject missing/invalid key
        $this->getJson('/api/v1/ping')->assertStatus(401);
        $this->withHeader('Authorization', 'Bearer rsk_live_invalid')
            ->getJson('/api/v1/ping')
            ->assertStatus(401);

        // 2. Reject non-whitelisted IP
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
            ->withHeader('Authorization', 'Bearer ' . $pair['plain'])
            ->getJson('/api/v1/ping')
            ->assertStatus(403)
            ->assertJsonPath('error', 'ip_not_whitelisted');

        // 3. Valid ping, balance, products
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Authorization', 'Bearer ' . $pair['plain'])
            ->getJson('/api/v1/ping')
            ->assertStatus(200)
            ->assertJsonPath('data.user_id', $reseller->id);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('X-API-Key', $pair['plain'])
            ->getJson('/api/v1/balance')
            ->assertStatus(200)
            ->assertJsonPath('data.wallet_balance', 100000);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Authorization', 'Bearer ' . $pair['plain'])
            ->getJson('/api/v1/products?in_stock=1')
            ->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.available_stock', 2);

        // 4. Reject out-of-stock (quantity > available) without debiting wallet
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Authorization', 'Bearer ' . $pair['plain'])
            ->postJson('/api/v1/orders', [
                'product_id' => $product->id,
                'quantity' => 5,
            ])
            ->assertStatus(402); // 5 * 30,000 = 150,000 > 100,000 balance check first

        $reseller->customer_wallet_balance = 500000;
        $reseller->save();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Authorization', 'Bearer ' . $pair['plain'])
            ->postJson('/api/v1/orders', [
                'product_id' => $product->id,
                'quantity' => 5,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'insufficient_stock');

        $this->assertSame(500000, (int) $reseller->fresh()->customer_wallet_balance);

        // 5. Instant atomic purchase with client_ref
        $orderResp = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Authorization', 'Bearer ' . $pair['plain'])
            ->postJson('/api/v1/orders', [
                'product_id' => $product->id,
                'quantity' => 1,
                'client_ref' => 'EXT-ORDER-001',
            ]);

        $orderResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.total_amount', 30000)
            ->assertJsonPath('data.remaining_balance', 470000)
            ->assertJsonCount(1, 'data.credentials');

        $orderRef = $orderResp->json('data.order_ref');
        $this->assertSame('user1@mail.test|pass123|2FASECRET1', $orderResp->json('data.credentials.0.data'));
        $this->assertSame(470000, (int) $reseller->fresh()->customer_wallet_balance);

        // 6. Idempotency replay returns same order with status 200 and does NOT double-charge
        $replayResp = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Authorization', 'Bearer ' . $pair['plain'])
            ->postJson('/api/v1/orders', [
                'product_id' => $product->id,
                'quantity' => 1,
                'client_ref' => 'EXT-ORDER-001',
            ]);

        $replayResp->assertStatus(200)
            ->assertJsonPath('idempotent', true)
            ->assertJsonPath('data.order_ref', $orderRef);

        $this->assertSame(470000, (int) $reseller->fresh()->customer_wallet_balance);
        $this->assertSame(1, StockUnit::where('product_id', $product->id)->where('is_sold', false)->count());

        // 7. Fetch order detail via GET /api/v1/orders/{orderRef}
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeader('Authorization', 'Bearer ' . $pair['plain'])
            ->getJson('/api/v1/orders/' . $orderRef)
            ->assertStatus(200)
            ->assertJsonPath('data.order_ref', $orderRef);

        $this->assertGreaterThan(0, ResellerApiLog::where('api_key_id', $apiKey->id)->count());
    }

    public function test_admin_financial_report_pnl_liabilities_and_exports(): void
    {
        $admin = $this->makeUser('admin');
        $seller = $this->makeUser('seller', [
            'wallet_balance' => 85000,
            'platform_fee_percent' => 10,
        ]);
        $customer = $this->makeUser('customer', [
            'customer_wallet_balance' => 120000,
        ]);

        // Admin product (Rp 50,000) & Seller product (Rp 100,000, fee 10% = Rp 10,000)
        $adminProduct = Product::create([
            'name' => 'VPN Private Admin',
            'price' => 50000,
            'is_suspended' => false,
            'creator_id' => $admin->id,
        ]);
        $sellerProduct = Product::create([
            'name' => 'Akun Cloud Seller',
            'price' => 100000,
            'is_suspended' => false,
            'creator_id' => $seller->id,
        ]);

        // Order 1: Admin product Rp 50,000 + unique code 123
        $ord1 = Order::create([
            'order_ref' => 'ORD-FIN-001',
            'customer_id' => $customer->id,
            'subtotal' => 50000,
            'discount_amount' => 0,
            'unique_code' => 123,
            'total_amount' => 50123,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $ord1->id,
            'product_id' => $adminProduct->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'discount_amount' => 0,
        ]);
        StockUnit::create([
            'product_id' => $adminProduct->id,
            'raw_text' => 'admin-cred-1',
            'is_sold' => true,
            'sold_order_id' => $ord1->id,
            'seller_id' => null,
        ]);

        // Order 2: Seller product Rp 100,000 (10% platform commission = Rp 10,000, seller net = Rp 90,000)
        $ord2 = Order::create([
            'order_ref' => 'ORD-FIN-002',
            'customer_id' => $customer->id,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'unique_code' => 0,
            'total_amount' => 100000,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $ord2->id,
            'product_id' => $sellerProduct->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'discount_amount' => 0,
        ]);
        StockUnit::create([
            'product_id' => $sellerProduct->id,
            'raw_text' => 'seller-cred-1',
            'is_sold' => true,
            'sold_order_id' => $ord2->id,
            'seller_id' => $seller->id,
        ]);

        // Held fund Rp 90,000
        HeldFund::create([
            'seller_id' => $seller->id,
            'order_id' => $ord2->id,
            'product_id' => $sellerProduct->id,
            'amount' => 90000,
            'status' => 'held',
            'release_at' => now()->addDays(7),
        ]);

        // Completed Escrow with fee Rp 15,000
        EscrowTransaction::create([
            'creator_id' => $customer->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $customer->id,
            'buyer_name' => 'Buyer A',
            'buyer_contact' => '@buyera',
            'seller_name' => 'Seller B',
            'seller_contact' => '@sellerb',
            'title' => 'Jual Beli Domain',
            'amount' => 300000,
            'fee_amount' => 15000,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 315000,
            'total_seller_receive' => 300000,
            'status' => EscrowTransaction::STATUS_COMPLETED,
            'payout_at' => now(),
        ]);

        // Active Escrow holding Rp 205,000
        EscrowTransaction::create([
            'creator_id' => $customer->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $customer->id,
            'buyer_name' => 'Buyer C',
            'buyer_contact' => '@buyerc',
            'seller_name' => 'Seller D',
            'seller_contact' => '@sellerd',
            'title' => 'Rekber Akun Game',
            'amount' => 200000,
            'fee_amount' => 5000,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 205000,
            'total_seller_receive' => 200000,
            'status' => EscrowTransaction::STATUS_FUNDS_HELD,
        ]);

        // Refund to customer wallet Rp 5,000
        WalletTransaction::create([
            'user_id' => $customer->id,
            'type' => 'credit',
            'category' => 'warranty_refund',
            'amount' => 5000,
            'balance_before' => 115000,
            'balance_after' => 120000,
            'reference' => 'REF-001',
            'description' => 'Kompensasi garansi',
        ]);

        // Confirmed topup & approved payout
        WalletTopup::create([
            'topup_ref' => 'TOP-FIN-001',
            'user_id' => $customer->id,
            'amount' => 200000,
            'unique_code' => 111,
            'total_pay' => 200111,
            'status' => 'paid',
            'paid_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        WithdrawalRequest::create([
            'seller_id' => $seller->id,
            'amount' => 40000,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller Test',
            'status' => 'approved',
            'processed_at' => now(),
        ]);

        // Verify exact mathematical accuracy from FinancialReportService
        $service = app(FinancialReportService::class);
        $report = $service->buildReport(now()->startOfDay(), now()->endOfDay());

        $this->assertSame(150000, $report['pnl']['gross_gmv']);
        $this->assertSame(50000, $report['pnl']['admin_product_revenue']);
        $this->assertSame(100000, $report['pnl']['seller_gross_sales']);
        $this->assertSame(10000, $report['pnl']['platform_seller_commission']);
        $this->assertSame(15000, $report['pnl']['escrow_fee_revenue']);
        $this->assertSame(123, $report['pnl']['unique_code_revenue']);
        $this->assertSame(5000, $report['pnl']['wallet_refund_total']);
        // Net Platform Revenue = 50,000 + 10,000 + 15,000 + 123 - 5,000 = 70,123
        $this->assertSame(70123, $report['pnl']['net_platform_revenue']);

        // Liabilities = 120,000 (customer wallet) + 85,000 (seller payable) + 90,000 (held) + 205,000 (active escrow) = 500,000
        $this->assertSame(120000, $report['liabilities']['customer_wallet_liability']);
        $this->assertSame(85000, $report['liabilities']['seller_available_payable']);
        $this->assertSame(90000, $report['liabilities']['held_funds_liability']);
        $this->assertSame(205000, $report['liabilities']['active_escrow_liability']);
        $this->assertSame(500000, $report['liabilities']['total_liabilities']);

        // Non-admin blocked (redirected by EnsureAdmin middleware)
        $this->actingAs($customer)
            ->get(route('admin.finance.reports'))
            ->assertRedirect(route('dashboard'));

        // Admin renders all 4 tabs cleanly
        foreach (['pnl', 'liabilities', 'performance', 'ledger'] as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.finance.reports', ['tab' => $tab, 'preset' => 'today']))
                ->assertStatus(200)
                ->assertSee('Laporan Keuangan');
        }

        // CSV Export
        $csvResp = $this->actingAs($admin)
            ->get(route('admin.finance.reports.export-csv', ['preset' => 'today']));
        $csvResp->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $csvResp->headers->get('Content-Type'));
        $csvContent = $csvResp->streamedContent();
        $this->assertStringContainsString('70123', $csvContent);
        $this->assertStringContainsString('500000', $csvContent);

        // PDF Export
        $pdfResp = $this->actingAs($admin)
            ->get(route('admin.finance.reports.export-pdf', ['preset' => 'today']));
        $pdfResp->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $pdfResp->headers->get('Content-Type'));
    }
}
