<?php

namespace Tests\Feature;

use App\Models\EscrowTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EscrowFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $buyer;
    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::forceCreate([
            'username' => 'admin_test',
            'full_name' => 'Admin Escrow',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->buyer = User::forceCreate([
            'username' => 'buyer_test',
            'full_name' => 'Buyer Escrow',
            'email' => 'buyer@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $this->seller = User::forceCreate([
            'username' => 'seller_test',
            'full_name' => 'Seller Escrow',
            'email' => 'seller@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_user_can_access_escrow_index_and_create_escrow()
    {
        $response = $this->actingAs($this->buyer)->get(route('escrow.index'));
        $response->assertStatus(200);
        $response->assertSee('Layanan Rekber');

        $createResponse = $this->actingAs($this->buyer)->post(route('escrow.store'), [
            'creator_role' => 'buyer',
            'partner_name' => 'Seller Luar',
            'partner_contact' => '@seller_luar',
            'title' => 'Akun GitHub Pack 2026',
            'description' => 'First mail include',
            'amount' => 100000,
            'fee_payer' => 'buyer',
            'inspection_hours' => 24,
        ]);

        $createResponse->assertRedirect();
        
        $this->assertDatabaseHas('escrow_transactions', [
            'title' => 'Akun GitHub Pack 2026',
            'buyer_user_id' => $this->buyer->id,
            'seller_name' => 'Seller Luar',
            'amount' => 100000,
            'status' => EscrowTransaction::STATUS_WAITING_DEPOSIT,
        ]);
    }

    public function test_user_with_revoked_permission_cannot_access_escrow()
    {
        // Admin uncheck rekber (allowed_tools array does not contain 'rekber')
        $this->buyer->allowed_tools = ['github_checker'];
        $this->buyer->save();

        $this->assertFalse($this->buyer->canUseRekber());

        $response = $this->actingAs($this->buyer)->get(route('escrow.index'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_public_user_can_view_escrow_room_via_token()
    {
        $trx = EscrowTransaction::forceCreate([
            'transaction_code' => 'RKB-TEST-001',
            'creator_id' => $this->buyer->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $this->buyer->id,
            'buyer_name' => 'Buyer Escrow',
            'buyer_contact' => '@buyer_tele',
            'seller_name' => 'Penjual Luar',
            'seller_contact' => '@penjual_luar',
            'title' => 'Produk Lisensi Test',
            'amount' => 50000,
            'fee_amount' => 5000,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 55000,
            'total_seller_receive' => 50000,
            'status' => EscrowTransaction::STATUS_WAITING_DEPOSIT,
            'public_token' => 'test-public-token-1234567890abcdef',
        ]);

        $response = $this->get(route('escrow.public.show', $trx->public_token));
        $response->assertStatus(200);
        $response->assertSee('RKB-TEST-001');
        $response->assertSee('Produk Lisensi Test');
    }

    public function test_deposit_and_admin_verification_flow()
    {
        $trx = EscrowTransaction::forceCreate([
            'transaction_code' => 'RKB-TEST-002',
            'creator_id' => $this->buyer->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $this->buyer->id,
            'buyer_name' => 'Buyer Escrow',
            'buyer_contact' => '@buyer_tele',
            'seller_name' => 'Penjual Luar',
            'seller_contact' => '@penjual_luar',
            'title' => 'Produk Test Flow',
            'amount' => 100000,
            'fee_amount' => 5000,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 105000,
            'total_seller_receive' => 100000,
            'status' => EscrowTransaction::STATUS_WAITING_DEPOSIT,
            'public_token' => 'token-deposit-test-123456',
        ]);

        // 1. Buyer uploads deposit proof
        $file = UploadedFile::fake()->image('bukti_transfer.jpg');
        $uploadResponse = $this->actingAs($this->buyer)->post(route('escrow.upload-deposit', $trx->transaction_code), [
            'deposit_proof' => $file,
        ]);
        $uploadResponse->assertRedirect();

        $trx->refresh();
        $this->assertEquals(EscrowTransaction::STATUS_DEPOSIT_SUBMITTED, $trx->status);
        $this->assertNotNull($trx->deposit_proof);

        // 2. Admin verifies deposit
        $verifyResponse = $this->actingAs($this->admin)->post(route('admin.escrow.verify-deposit', $trx->transaction_code));
        $verifyResponse->assertRedirect();

        $trx->refresh();
        $this->assertEquals(EscrowTransaction::STATUS_FUNDS_HELD, $trx->status);
        $this->assertEquals($this->admin->id, $trx->deposit_verified_by);
    }

    public function test_delivery_confirmation_and_payout_flow()
    {
        $trx = EscrowTransaction::forceCreate([
            'transaction_code' => 'RKB-TEST-003',
            'creator_id' => $this->buyer->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $this->buyer->id,
            'buyer_name' => 'Buyer Escrow',
            'buyer_contact' => '@buyer_tele',
            'seller_name' => 'Penjual Luar',
            'seller_contact' => '@penjual_luar',
            'title' => 'Produk Delivery Test',
            'amount' => 200000,
            'fee_amount' => 5000,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 205000,
            'total_seller_receive' => 200000,
            'status' => EscrowTransaction::STATUS_FUNDS_HELD,
            'public_token' => 'token-delivery-test-123456',
        ]);

        // 1. Seller uploads delivery proof via public token
        $deliverResponse = $this->post(route('escrow.public.deliver', $trx->public_token), [
            'delivery_text' => 'Email: test@gmail.com | Pass: 12345678',
            'seller_bank_name' => 'BCA',
            'seller_account_number' => '987654321',
            'seller_account_holder' => 'Penjual Luar',
        ]);
        $deliverResponse->assertRedirect();

        $trx->refresh();
        $this->assertEquals(EscrowTransaction::STATUS_DELIVERED, $trx->status);
        $this->assertStringContainsString('test@gmail.com', $trx->delivery_proof);

        // 2. Buyer confirms complete
        $confirmResponse = $this->actingAs($this->buyer)->post(route('escrow.confirm-complete', $trx->transaction_code));
        $confirmResponse->assertRedirect();

        $trx->refresh();
        $this->assertEquals(EscrowTransaction::STATUS_WAITING_PAYOUT, $trx->status);

        // 3. Admin processes payout
        $payoutFile = UploadedFile::fake()->image('bukti_payout.jpg');
        $payoutResponse = $this->actingAs($this->admin)->post(route('admin.escrow.payout', $trx->transaction_code), [
            'payout_proof' => $payoutFile,
            'admin_notes' => 'Ref: TRX999888',
        ]);
        $payoutResponse->assertRedirect();

        $trx->refresh();
        $this->assertEquals(EscrowTransaction::STATUS_COMPLETED, $trx->status);
        $this->assertNotNull($trx->payout_proof);
    }

    public function test_dispute_and_arbitration_refund_flow()
    {
        $trx = EscrowTransaction::forceCreate([
            'transaction_code' => 'RKB-TEST-004',
            'creator_id' => $this->buyer->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $this->buyer->id,
            'buyer_name' => 'Buyer Escrow',
            'buyer_contact' => '@buyer_tele',
            'seller_name' => 'Penjual Luar',
            'seller_contact' => '@penjual_luar',
            'title' => 'Produk Sengketa Test',
            'amount' => 150000,
            'fee_amount' => 5000,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 155000,
            'total_seller_receive' => 150000,
            'status' => EscrowTransaction::STATUS_DELIVERED,
            'public_token' => 'token-dispute-test-123456',
        ]);

        // Buyer opens dispute
        $disputeResponse = $this->actingAs($this->buyer)->post(route('escrow.open-dispute', $trx->transaction_code), [
            'dispute_reason' => 'Sandi tidak bisa digunakan dan akun terkunci',
        ]);
        $disputeResponse->assertRedirect();

        $trx->refresh();
        $this->assertEquals(EscrowTransaction::STATUS_DISPUTED, $trx->status);
        $this->assertEquals('Sandi tidak bisa digunakan dan akun terkunci', $trx->dispute_reason);

        // Admin resolves dispute with refund to buyer
        $resolveResponse = $this->actingAs($this->admin)->post(route('admin.escrow.resolve-dispute', $trx->transaction_code), [
            'decision' => 'refund_to_buyer',
            'dispute_resolution' => 'Bukti login membuktikan akun tidak valid sejak awal',
        ]);
        $resolveResponse->assertRedirect();

        $trx->refresh();
        $this->assertEquals(EscrowTransaction::STATUS_REFUNDED, $trx->status);
        $this->assertNotNull($trx->dispute_resolved_at);
    }

    public function test_escrow_room_renders_qris_and_bank_options_and_floating_chat_button()
    {
        // 1. Simpan dummy static QRIS payload valid di BotSetting
        $staticQris = "0002010102115204599953033605802ID5910DZUL STORE6007JAKARTA6304ABCD";
        \App\Models\BotSetting::updateOrCreate(
            ['key' => 'qris_static_payload'],
            ['value' => $staticQris]
        );

        $trx = EscrowTransaction::forceCreate([
            'transaction_code' => 'RKB-TEST-QRIS',
            'creator_id' => $this->buyer->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $this->buyer->id,
            'buyer_name' => 'Buyer Escrow',
            'buyer_contact' => '@buyer_tele',
            'seller_name' => 'Penjual Luar',
            'seller_contact' => '@penjual_luar',
            'title' => 'Transaksi Rekber QRIS & Chat',
            'amount' => 50000,
            'fee_amount' => 5000,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 55000,
            'total_seller_receive' => 50000,
            'status' => EscrowTransaction::STATUS_WAITING_DEPOSIT,
            'public_token' => 'token-qris-test-abcdef',
        ]);

        // 2. Akses halaman rekber oleh buyer
        $response = $this->actingAs($this->buyer)->get(route('escrow.show', $trx->transaction_code));
        $response->assertStatus(200);

        // Verifikasi tab pembayaran QRIS dan Bank
        $response->assertSee('QRIS (Otomatis &amp; Instan)', false);
        $response->assertSee('Transfer Rekening Bank');
        $response->assertSee('QRIS Dinamis Terisi Nominal Otomatis');
        $response->assertSee('Unduh Kode QR');
        $response->assertSee('qrcode.min.js');

        // Verifikasi floating action button chat dan offcanvas drawer
        $response->assertSee('escrow-chat-fab');
        $response->assertSee('id="escrowChatDrawer"', false);
        $response->assertSee('Obrolan Ruang Rekber');

        // 3. Buyer mengirim pesan di ruang rekber
        $msgResponse = $this->actingAs($this->buyer)->post(route('escrow.send-message', $trx->transaction_code), [
            'message' => 'Halo admin & penjual, saya sudah bayar via QRIS!',
        ]);
        $msgResponse->assertRedirect();

        $this->assertDatabaseHas('escrow_messages', [
            'escrow_transaction_id' => $trx->id,
            'sender_id' => $this->buyer->id,
            'message' => 'Halo admin & penjual, saya sudah bayar via QRIS!',
        ]);

        // 4. Verifikasi counter pesan bertambah di view
        $responseAfterMsg = $this->actingAs($this->buyer)->get(route('escrow.show', $trx->transaction_code));
        $responseAfterMsg->assertStatus(200);
        $responseAfterMsg->assertSee('Halo admin &amp; penjual, saya sudah bayar via QRIS!', false);
    }

    public function test_admin_can_update_escrow_settings_without_min_fee()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.escrow.settings'), [
            'fee_percent' => 2.5,
            'bank_info' => "Bank Mandiri: 999888777\na.n Toko Resmi",
        ]);

        $response->assertRedirect();
        $this->assertEquals(2.5, (float)\App\Models\BotSetting::where('key', 'escrow.fee_percent')->value('value'));
        $this->assertNull(\App\Models\BotSetting::where('key', 'escrow.min_fee')->value('value'));

        // Cek kalkulasi fee murni persentase tanpa biaya minimum
        // Nominal 100.000 * 2.5% = 2.500
        $feeCalc = \App\Http\Controllers\EscrowController::calculateFee(100000, 'buyer');
        $this->assertEquals(2500, $feeCalc['fee_amount']);
        $this->assertEquals(102500, $feeCalc['total_buyer_pay']);
        $this->assertEquals(100000, $feeCalc['total_seller_receive']);
        $this->assertArrayNotHasKey('min_fee', $feeCalc);

        // Pastikan halaman form pembuatan rekber tidak lagi menampilkan min_fee
        $createPage = $this->actingAs($this->buyer)->get(route('escrow.create'));
        $createPage->assertStatus(200);
        $createPage->assertDontSee('min Rp');
        $createPage->assertSee('Biaya Admin Rekber (2.5%):');
    }
}
