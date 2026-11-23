<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BroadcastJob;
use App\Models\ComplaintCase;
use App\Models\Coupon;
use App\Models\DashboardPromo;
use App\Models\EscrowTransaction;
use App\Models\FirewallRule;
use App\Models\LoginLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ResellerApiKey;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileResponsiveViewsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $seller;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'username' => 'admin_test_mobile',
            'full_name' => 'Admin Mobile',
            'email' => 'admin_test_mobile@test.com',
            'role' => 'admin',
            'wallet_balance' => 500000,
            'is_suspended' => false,
            'password' => bcrypt('password'),
        ]);

        $this->seller = User::forceCreate([
            'username' => 'seller_test_mobile',
            'full_name' => 'Seller Mobile',
            'email' => 'seller_test_mobile@test.com',
            'role' => 'seller',
            'wallet_balance' => 250000,
            'is_suspended' => false,
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::forceCreate([
            'username' => 'customer_test_mobile',
            'full_name' => 'Customer Mobile',
            'email' => 'customer_test_mobile@test.com',
            'role' => 'customer',
            'wallet_balance' => 100000,
            'is_suspended' => false,
            'password' => bcrypt('password'),
        ]);
    }

    public function test_customer_mobile_responsive_views_render_successfully(): void
    {
        // 1. Customer Wallet Index dengan data mutasi
        WalletTransaction::create([
            'user_id' => $this->customer->id,
            'type' => 'credit',
            'category' => 'topup',
            'amount' => 50000,
            'balance_before' => 50000,
            'balance_after' => 100000,
            'description' => 'Top Up QRIS Berhasil',
            'reference' => 'TOP-12345',
        ]);
        $responseWallet = $this->actingAs($this->customer)->get(route('wallet.index'));
        $responseWallet->assertStatus(200);
        $responseWallet->assertSee('topupModal');
        $responseWallet->assertSee('m3-sheet-modal');
        $responseWallet->assertSee('m3-mobile-item-card');
        $responseWallet->assertSee('is-ultra-compact');
        $responseWallet->assertSee('id="m3MainContent"', false);
        $responseWallet->assertSee('<link rel="expect" href="#m3MainContent" blocking="render">', false);
        $responseWallet->assertSee('m3-slide-forward');

        // 2. Customer Complaints Index
        ComplaintCase::create([
            'complaint_ref' => 'CMP-TEST-001',
            'customer_id' => $this->customer->id,
            'customer_telegram_id' => 123456789,
            'order_ref_snapshot' => 'ORD-TEST-001',
            'seller_id' => $this->seller->id,
            'complaint_text' => 'Akun tidak bisa login',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $responseComplaints = $this->actingAs($this->customer)->get(route('customer.complaints.index'));
        $responseComplaints->assertStatus(200);
        $responseComplaints->assertSee('m3-mobile-item-card');
        $responseComplaints->assertSee('m3-collapsible-card');
        $responseComplaints->assertSee('data-bs-parent="#customerComplaintsAccordion"', false);

        // 3. Customer Escrow Index
        EscrowTransaction::create([
            'transaction_code' => 'ESC-TEST-001',
            'public_token' => 'token-test-001',
            'creator_id' => $this->customer->id,
            'creator_role' => 'buyer',
            'title' => 'Akun Game Premium',
            'buyer_id' => $this->customer->id,
            'buyer_name' => $this->customer->full_name ?: $this->customer->username,
            'buyer_contact' => '@customer_mobile',
            'seller_name' => $this->seller->full_name ?: $this->seller->username,
            'seller_contact' => '@seller_mobile',
            'amount' => 50000,
            'fee_amount' => 2500,
            'total_buyer_pay' => 52500,
            'total_seller_receive' => 50000,
            'fee_payer' => 'buyer',
            'status' => EscrowTransaction::STATUS_WAITING_DEPOSIT,
        ]);
        $responseEscrow = $this->actingAs($this->customer)->get(route('escrow.index'));
        $responseEscrow->assertStatus(200);
        $responseEscrow->assertSee('m3-mobile-item-card');
        $responseEscrow->assertSee('m3-collapsible-card');
        $responseEscrow->assertSee('data-bs-parent="#customerEscrowAccordion"', false);

        // 4. User Logins Log
        LoginLog::create([
            'user_id' => $this->customer->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Mobile',
            'status' => 'success',
            'logged_at' => now(),
        ]);
        $responseLogins = $this->actingAs($this->customer)->get(route('profile.logins'));
        $responseLogins->assertStatus(200);
        $responseLogins->assertSee('m3-mobile-item-card');
        $responseLogins->assertSee('m3-collapsible-card');
        $responseLogins->assertSee('data-bs-parent="#profileLoginsAccordion"', false);
    }

    public function test_seller_mobile_responsive_views_render_successfully(): void
    {
        // 1. Seller Complaints Index
        ComplaintCase::create([
            'complaint_ref' => 'CMP-TEST-002',
            'customer_id' => $this->customer->id,
            'customer_telegram_id' => 987654321,
            'order_ref_snapshot' => 'ORD-TEST-002',
            'seller_id' => $this->seller->id,
            'complaint_text' => 'Garansi 30 hari bermasalah',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $responseSellerComplaints = $this->actingAs($this->seller)->get(route('seller.complaints.index'));
        $responseSellerComplaints->assertStatus(200);
        $responseSellerComplaints->assertSee('m3-mobile-item-card');

        // 2. Seller Analytics (dengan produk creator_id = seller & order delivered)
        $product = Product::forceCreate([
            'creator_id' => $this->seller->id,
            'name' => 'Akun Netflix 1 Bulan',
            'price' => 35000,
            'is_suspended' => false,
        ]);
        $order = Order::create([
            'order_ref' => 'ORD-SELLER-ANL-1',
            'customer_id' => $this->customer->id,
            'total_amount' => 35000,
            'subtotal' => 35000,
            'discount_amount' => 0,
            'unique_code' => 0,
            'status' => 'delivered',
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 35000,
            'quantity' => 1,
            'discount_amount' => 0,
        ]);
        $responseAnalytics = $this->actingAs($this->seller)->get(route('seller.analytics'));
        $responseAnalytics->assertStatus(200);
        $responseAnalytics->assertSee('m3-mobile-item-card');

        // 3. Seller Coupons Index (user_id = sellerId)
        Coupon::create([
            'code' => 'TOKOHEMAT',
            'type' => 'fixed',
            'value' => 5000,
            'min_spend' => 25000,
            'qty' => 50,
            'used_qty' => 5,
            'is_active' => true,
            'user_id' => $this->seller->id,
            'created_by' => $this->seller->id,
        ]);
        $responseSellerCoupons = $this->actingAs($this->seller)->get(route('seller.coupons.index'));
        $responseSellerCoupons->assertStatus(200);
        $responseSellerCoupons->assertSee('m3-mobile-item-card');
        $responseSellerCoupons->assertSee('createSellerCouponModal');
        $responseSellerCoupons->assertSee('m3-sheet-modal');
    }

    public function test_admin_and_developer_mobile_responsive_views_render_successfully(): void
    {
        // 1. Developer API Index
        ResellerApiKey::create([
            'user_id' => $this->seller->id,
            'name' => 'Bot Toko H2H',
            'key_prefix' => 'rsk_live_test',
            'key_hash' => hash('sha256', 'dummy_plain_secret_key_12345'),
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);
        $responseApi = $this->actingAs($this->seller)->get(route('developer.api.index', ['tab' => 'keys']));
        $responseApi->assertStatus(200);
        $responseApi->assertSee('createApiKeyModal');
        $responseApi->assertSee('m3-sheet-modal');

        // 2. Admin Withdrawals Index
        WithdrawalRequest::create([
            'seller_id' => $this->seller->id,
            'amount' => 50000,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Seller Mobile Test',
            'status' => 'pending',
        ]);
        $responseAdminWithdrawals = $this->actingAs($this->admin)->get(route('admin.withdrawals.index'));
        $responseAdminWithdrawals->assertStatus(200);
        $responseAdminWithdrawals->assertSee('m3-mobile-item-card');
        $responseAdminWithdrawals->assertSee('m3-sheet-modal');

        // 3. Admin Complaints Index
        ComplaintCase::create([
            'complaint_ref' => 'CMP-ADM-001',
            'customer_id' => $this->customer->id,
            'customer_telegram_id' => 112233445,
            'order_ref_snapshot' => 'ORD-ADM-001',
            'seller_id' => $this->seller->id,
            'complaint_text' => 'Komplain akun untuk admin review',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $responseAdminComplaints = $this->actingAs($this->admin)->get(route('admin.complaints.index'));
        $responseAdminComplaints->assertStatus(200);
        $responseAdminComplaints->assertSee('m3-mobile-item-card');

        // 4. Admin Wallet Index
        WalletTopup::create([
            'topup_ref' => 'TOP-TEST-001',
            'user_id' => $this->customer->id,
            'amount' => 50000,
            'unique_code' => 123,
            'total_pay' => 50123,
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
        WalletTransaction::create([
            'user_id' => $this->customer->id,
            'amount' => 10000,
            'type' => 'credit',
            'category' => 'adjustment',
            'balance_before' => 90000,
            'balance_after' => 100000,
            'description' => 'Injeksi Saldo Manual Admin: Bonus',
            'reference' => 'ADJ-12345',
        ]);
        $responseAdminWallet = $this->actingAs($this->admin)->get(route('admin.wallet.index'));
        $responseAdminWallet->assertStatus(200);
        $responseAdminWallet->assertSee('injectWalletModal');
        $responseAdminWallet->assertSee('m3-sheet-modal');
        $responseAdminWallet->assertSee('m3-mobile-item-card');

        // 5. Admin Coupons Index
        Coupon::create([
            'code' => 'ADMINPROMO',
            'type' => 'percent',
            'value' => 10,
            'min_spend' => 10000,
            'qty' => 100,
            'used_qty' => 12,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $responseAdminCoupons = $this->actingAs($this->admin)->get(route('admin.coupons.index'));
        $responseAdminCoupons->assertStatus(200);
        $responseAdminCoupons->assertSee('m3-mobile-item-card');
        $responseAdminCoupons->assertSee('createCouponModal');
        $responseAdminCoupons->assertSee('m3-sheet-modal');

        // 6. Admin Escrow Index
        EscrowTransaction::create([
            'transaction_code' => 'ESC-TEST-ADM',
            'public_token' => 'token-test-adm',
            'creator_id' => $this->admin->id,
            'creator_role' => 'buyer',
            'title' => 'Akun Cloud Server',
            'buyer_id' => $this->customer->id,
            'buyer_name' => $this->customer->full_name ?: $this->customer->username,
            'buyer_contact' => '@customer_mobile',
            'seller_name' => $this->seller->full_name ?: $this->seller->username,
            'seller_contact' => '@seller_mobile',
            'amount' => 120000,
            'fee_amount' => 6000,
            'total_buyer_pay' => 120000,
            'total_seller_receive' => 114000,
            'fee_payer' => 'seller',
            'status' => EscrowTransaction::STATUS_WAITING_PAYOUT,
        ]);
        $responseAdminEscrow = $this->actingAs($this->admin)->get(route('admin.escrow.index'));
        $responseAdminEscrow->assertStatus(200);
        $responseAdminEscrow->assertSee('m3-mobile-item-card');
        $responseAdminEscrow->assertSee('escrowSettingsModal');
        $responseAdminEscrow->assertSee('m3-sheet-modal');
    }

    public function test_wave2_mobile_responsive_views_and_wave1_fixes_render_successfully(): void
    {
        // 0. Verifikasi fix bug JS scoped selector di admin.wallet.index
        $resAdminWalletFix = $this->actingAs($this->admin)->get(route('admin.wallet.index'));
        $resAdminWalletFix->assertStatus(200);
        $resAdminWalletFix->assertSee("document.querySelectorAll('.admin-inject-form')", false);
        $resAdminWalletFix->assertDontSee("prefix + 'InjectAmountInput'", false);

        // 0b. Verifikasi Tab 4 Admin Global Control di developer.api.index
        ResellerApiKey::create([
            'user_id' => $this->seller->id,
            'name' => 'Reseller Key Global',
            'key_prefix' => 'rsk_live_glob',
            'key_hash' => hash('sha256', 'dummy_global_key_999'),
            'rate_limit_per_minute' => 60,
            'is_active' => true,
        ]);
        $resDevAdmin = $this->actingAs($this->admin)->get(route('developer.api.index', ['tab' => 'admin']));
        $resDevAdmin->assertStatus(200);
        $resDevAdmin->assertSee('m3-mobile-item-card');

        // 1. Admin Security Firewall (Form -> Bottom Sheet Modal + Dual-View Cards)
        FirewallRule::create([
            'type' => 'ip',
            'value' => '10.20.30.40',
            'reason' => 'Test brute force',
            'is_active' => true,
            'hit_count' => 12,
        ]);
        $resFirewall = $this->actingAs($this->admin)->get(route('admin.security.firewall'));
        $resFirewall->assertStatus(200);
        $resFirewall->assertSee('addFirewallRuleModal');
        $resFirewall->assertSee('m3-sheet-modal');
        $resFirewall->assertSee('m3-mobile-item-card');

        // 2. Admin Broadcast (Single-DOM Switcher + Mobile Cards + Sheet Modal)
        BroadcastJob::forceCreate([
            'message' => 'Promo besar hari ini!',
            'status' => 'completed',
            'total_targets' => 10,
            'sent_count' => 8,
            'failed_count' => 2,
            'read_count' => 4,
        ]);
        $resBroadcast = $this->actingAs($this->admin)->get(route('admin.broadcast.index'));
        $resBroadcast->assertStatus(200);
        $resBroadcast->assertSee('bcMobileTabHistory');
        $resBroadcast->assertSee('bcMobileTabComposer');
        $resBroadcast->assertSee('m3-mobile-item-card');
        $resBroadcast->assertSee('m3-sheet-modal');

        // 3. Admin Promos (Dual-View + Mobile Reorder Sync)
        DashboardPromo::create([
            'title' => 'Diskon Kilat',
            'description' => 'Potongan 20% semua akun',
            'badge_text' => 'PROMO',
            'badge_color' => '#2563eb',
            'icon' => 'fas fa-bolt',
            'gradient_theme' => 'blue',
            'button_text' => 'Belanja Sekarang',
            'button_url' => '/catalog',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $resPromos = $this->actingAs($this->admin)->get(route('admin.promos.index'));
        $resPromos->assertStatus(200);
        $resPromos->assertSee('promoSortableMobile');
        $resPromos->assertSee('m3-mobile-item-card');

        // 4. Seller Dashboard + Stock + Products
        $product = Product::forceCreate([
            'creator_id' => $this->seller->id,
            'name' => 'Akun Spotify Family',
            'price' => 25000,
            'is_suspended' => false,
        ]);
        $order = Order::create([
            'order_ref' => 'ORD-DASH-W2-1',
            'customer_id' => $this->customer->id,
            'total_amount' => 25000,
            'subtotal' => 25000,
            'discount_amount' => 0,
            'unique_code' => 0,
            'status' => 'delivered',
            'delivered_at' => now(),
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'unit_price' => 25000,
            'quantity' => 1,
            'discount_amount' => 0,
        ]);
        StockUnit::forceCreate([
            'product_id' => $product->id,
            'seller_id' => $this->seller->id,
            'raw_text' => "Username: demo_user\nPass: secret123",
            'stock_status' => 'ready',
            'is_sold' => false,
        ]);
        StockUnit::forceCreate([
            'product_id' => $product->id,
            'seller_id' => $this->seller->id,
            'sold_order_id' => $order->id,
            'raw_text' => "Username: sold_user\nPass: secret123",
            'stock_status' => 'ready',
            'is_sold' => true,
        ]);

        $resSellerDash = $this->actingAs($this->seller)->get(route('seller.dashboard'));
        $resSellerDash->assertStatus(200);
        $resSellerDash->assertSee('m3-mobile-item-card');
        $resSellerDash->assertSee('is-ultra-compact');

        $resSellerStock = $this->actingAs($this->seller)->get(route('seller.stock.index'));
        $resSellerStock->assertStatus(200);
        $resSellerStock->assertSee('m3-mobile-item-card');
        $resSellerStock->assertSee('m3-collapsible-card');
        $resSellerStock->assertSee('data-bs-parent="#sellerStockAccordion"', false);
        $resSellerStock->assertSee('m3-sheet-modal');
        $resSellerStock->assertSee('select-all-stocks-toggle');

        $resSellerProducts = $this->actingAs($this->seller)->get(route('seller.products.index'));
        $resSellerProducts->assertStatus(200);
        $resSellerProducts->assertSee('m3-sheet-modal');

        // 5. Admin Users, Sellers, Stock, Products
        $resAdminUsers = $this->actingAs($this->admin)->get(route('admin.users.index'));
        $resAdminUsers->assertStatus(200);
        $resAdminUsers->assertSee('m3-mobile-item-card');
        $resAdminUsers->assertSee('m3-collapsible-card');
        $resAdminUsers->assertSee('data-bs-parent="#adminUsersAccordion"', false);
        $resAdminUsers->assertSee('m3-sheet-modal');

        $resAdminSellers = $this->actingAs($this->admin)->get(route('admin.sellers.index'));
        $resAdminSellers->assertStatus(200);
        $resAdminSellers->assertSee('m3-mobile-item-card');
        $resAdminSellers->assertSee('m3-collapsible-card');
        $resAdminSellers->assertSee('data-bs-parent="#adminSellersAccordion"', false);
        $resAdminSellers->assertSee('m3-sheet-modal');

        $resAdminStock = $this->actingAs($this->admin)->get(route('admin.stock.index'));
        $resAdminStock->assertStatus(200);
        $resAdminStock->assertSee('m3-mobile-item-card');
        $resAdminStock->assertSee('m3-collapsible-card');
        $resAdminStock->assertSee('data-bs-parent="#adminStockAccordion"', false);
        $resAdminStock->assertSee('m3-sheet-modal');
        $resAdminStock->assertSee('select-all-stocks-toggle');

        $resAdminProducts = $this->actingAs($this->admin)->get(route('admin.products.index'));
        $resAdminProducts->assertStatus(200);
        $resAdminProducts->assertSee('m3-sheet-modal');

        // 6. Admin Audit Logs, Logins, Finance Reports, Backup
        AuditLog::forceCreate([
            'actor_id' => $this->admin->id,
            'action' => 'backup_create',
            'entity_type' => 'system',
            'detail' => 'Manual backup snapshot dibuat',
            'created_at' => now(),
        ]);
        $resAudit = $this->actingAs($this->admin)->get(route('admin.audit-logs.index'));
        $resAudit->assertStatus(200);
        $resAudit->assertSee('m3-mobile-item-card');
        $resAudit->assertSee('m3-collapsible-card');
        $resAudit->assertSee('data-bs-parent="#adminAuditLogsAccordion"', false);

        LoginLog::forceCreate([
            'user_id' => $this->customer->id,
            'username_or_email' => $this->customer->email,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Mobile',
            'is_successful' => true,
            'created_at' => now(),
        ]);
        $resAdminLogins = $this->actingAs($this->admin)->get(route('admin.logins.index'));
        $resAdminLogins->assertStatus(200);
        $resAdminLogins->assertSee('m3-mobile-item-card');
        $resAdminLogins->assertSee('m3-collapsible-card');

        $resFinLedger = $this->actingAs($this->admin)->get(route('admin.finance.reports', ['tab' => 'ledger']));
        $resFinLedger->assertStatus(200);
        $resFinLedger->assertSee('m3-mobile-item-card');
        $resFinLedger->assertSee('m3-collapsible-card');
        $resFinLedger->assertSee('data-bs-parent="#adminFinanceLedgerAccordion"', false);

        $resBackupIdx = $this->actingAs($this->admin)->get(route('admin.backup.index'));
        $resBackupIdx->assertStatus(200);
        $resBackupIdx->assertSee('m3-tab-strip');
        $resBackupIdx->assertSee('m3-mobile-item-card');
        $resBackupIdx->assertSee('m3-collapsible-card');

        $resBackupHist = $this->actingAs($this->admin)->get(route('admin.backup.history'));
        $resBackupHist->assertStatus(200);
        $resBackupHist->assertSee('m3-tab-strip');

        $resBackupRestore = $this->actingAs($this->admin)->get(route('admin.backup.restore.show'));
        $resBackupRestore->assertStatus(200);
        $resBackupRestore->assertSee('m3-tab-strip');
        $resBackupRestore->assertSee('m3-sheet-modal');

        $resBackupSettings = $this->actingAs($this->admin)->get(route('admin.backup.settings.show'));
        $resBackupSettings->assertStatus(200);
        $resBackupSettings->assertSee('m3-tab-strip');
    }
}
