<?php

namespace Tests\Feature;

use App\Models\EscrowMessage;
use App\Models\EscrowTimeline;
use App\Models\EscrowTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EscrowAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $customer;
    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::forceCreate([
            'username' => 'admin_crud_test',
            'full_name' => 'Admin Escrow CRUD',
            'email' => 'admin_crud@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->customer = User::forceCreate([
            'username' => 'buyer_crud_test',
            'full_name' => 'Buyer CRUD User',
            'email' => 'buyer_crud@test.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $this->seller = User::forceCreate([
            'username' => 'seller_crud_test',
            'full_name' => 'Seller CRUD User',
            'email' => 'seller_crud@test.com',
            'role' => 'seller',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_admin_can_view_create_page_and_initiate_escrow_room()
    {
        // 1. Admin buka halaman form inisiasi rekber
        $pageResponse = $this->actingAs($this->admin)->get(route('admin.escrow.create'));
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee('Inisiasi Ruang Rekber oleh Admin');

        // 2. Admin membuat room rekber baru
        $storeResponse = $this->actingAs($this->admin)->post(route('admin.escrow.store'), [
            'title' => 'Transaksi Lisensi Windows Server 2026',
            'description' => 'Disertai first mail dan garansi aktif 1 tahun',
            'amount' => 150000,
            'fee_payer' => 'buyer',
            'inspection_hours' => 48,
            'buyer_type' => 'registered',
            'buyer_user_id' => $this->customer->id,
            'buyer_name' => $this->customer->full_name,
            'buyer_contact' => '@' . $this->customer->username,
            'seller_type' => 'registered',
            'seller_user_id' => $this->seller->id,
            'seller_name' => $this->seller->full_name,
            'seller_contact' => '@' . $this->seller->username,
            'seller_bank_name' => 'Bank BCA',
            'seller_account_number' => '1234567890',
            'seller_account_holder' => $this->seller->full_name,
        ]);

        $storeResponse->assertRedirect();

        $trx = EscrowTransaction::where('title', 'Transaksi Lisensi Windows Server 2026')->first();
        $this->assertNotNull($trx);
        $this->assertEquals($this->customer->id, $trx->buyer_user_id);
        $this->assertEquals($this->seller->id, $trx->seller_user_id);
        $this->assertEquals(150000, (float)$trx->amount);
        $this->assertEquals(EscrowTransaction::STATUS_WAITING_DEPOSIT, $trx->status);
        $this->assertGreaterThanOrEqual(1, $trx->unique_code);

        // Verifikasi timeline awal dibuat
        $this->assertDatabaseHas('escrow_timelines', [
            'escrow_transaction_id' => $trx->id,
            'action' => 'created_by_admin',
        ]);
    }

    public function test_non_admin_cannot_access_admin_escrow_crud()
    {
        // Request Web biasa dialihkan ke dashboard dengan flash message error
        $response = $this->actingAs($this->customer)->get(route('admin.escrow.create'));
        $response->assertRedirect(route('dashboard'));

        $storeResponse = $this->actingAs($this->customer)->post(route('admin.escrow.store'), [
            'title' => 'Hacking Attempt',
            'amount' => 100000,
        ]);
        $storeResponse->assertRedirect(route('dashboard'));

        // Request JSON mengembalikan HTTP 403 Forbidden
        $jsonResponse = $this->actingAs($this->customer)->getJson(route('admin.escrow.create'));
        $jsonResponse->assertStatus(403);
    }

    public function test_admin_can_edit_and_update_escrow_room()
    {
        $trx = EscrowTransaction::forceCreate([
            'transaction_code' => 'RKB-TEST-EDIT-01',
            'creator_id' => $this->admin->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $this->customer->id,
            'buyer_name' => 'Pembeli Awal',
            'buyer_contact' => '@pembeli_awal',
            'seller_name' => 'Penjual Awal',
            'seller_contact' => '@penjual_awal',
            'title' => 'Judul Sebelum Diedit',
            'amount' => 100000,
            'fee_amount' => 1500,
            'fee_payer' => 'buyer',
            'unique_code' => 55,
            'total_buyer_pay' => 101555,
            'total_seller_receive' => 100000,
            'status' => EscrowTransaction::STATUS_WAITING_DEPOSIT,
            'public_token' => 'token-test-edit-01',
        ]);

        // 1. Admin buka halaman edit
        $editPage = $this->actingAs($this->admin)->get(route('admin.escrow.edit', $trx->transaction_code));
        $editPage->assertStatus(200);
        $editPage->assertSee('Edit Transaksi Rekber');
        $editPage->assertSee('Judul Sebelum Diedit');

        // 2. Admin perbarui data
        $updateResponse = $this->actingAs($this->admin)->put(route('admin.escrow.update', $trx->transaction_code), [
            'title' => 'Judul Sesudah Diupdate Admin',
            'description' => 'Deskripsi baru dari admin',
            'amount' => 200000,
            'fee_payer' => 'seller',
            'inspection_hours' => 36,
            'buyer_name' => 'Pembeli Baru',
            'buyer_contact' => '@pembeli_baru',
            'seller_name' => 'Penjual Baru',
            'seller_contact' => '@penjual_baru',
            'seller_bank_name' => 'Bank Mandiri',
            'seller_account_number' => '9988776655',
            'seller_account_holder' => 'Penjual Baru',
            'status' => EscrowTransaction::STATUS_FUNDS_HELD, // Admin override status
            'admin_notes' => 'Status dinaikkan manual karena transfer via cabang offline',
        ]);

        $updateResponse->assertRedirect(route('admin.escrow.show', $trx->transaction_code));

        $trx->refresh();
        $this->assertEquals('Judul Sesudah Diupdate Admin', $trx->title);
        $this->assertEquals(200000, (float)$trx->amount);
        $this->assertEquals('seller', $trx->fee_payer);
        $this->assertEquals(EscrowTransaction::STATUS_FUNDS_HELD, $trx->status);
        $this->assertEquals('Status dinaikkan manual karena transfer via cabang offline', $trx->admin_notes);

        // Verifikasi timeline tercatat
        $this->assertDatabaseHas('escrow_timelines', [
            'escrow_transaction_id' => $trx->id,
            'action' => 'updated_by_admin',
        ]);
    }

    public function test_admin_can_delete_escrow_room_and_its_associated_data()
    {
        $file = UploadedFile::fake()->image('bukti_deposit.jpg');
        $filePath = $file->store('escrow_deposits', 'public');

        $trx = EscrowTransaction::forceCreate([
            'transaction_code' => 'RKB-TEST-DELETE-01',
            'creator_id' => $this->admin->id,
            'creator_role' => 'buyer',
            'buyer_name' => 'Pembeli Hapus',
            'buyer_contact' => '@pembeli_hapus',
            'seller_name' => 'Penjual Hapus',
            'seller_contact' => '@penjual_hapus',
            'title' => 'Transaksi Siap Dihapus',
            'amount' => 50000,
            'fee_amount' => 750,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 50750,
            'total_seller_receive' => 50000,
            'deposit_proof' => $filePath,
            'status' => EscrowTransaction::STATUS_DEPOSIT_SUBMITTED,
            'public_token' => 'token-test-delete-01',
        ]);

        // Buat pesan obrolan terkait
        $msg = $trx->messages()->create([
            'sender_id' => $this->admin->id,
            'sender_role' => 'admin',
            'sender_name' => 'Admin Escrow',
            'message' => 'Pesan uji coba sebelum dihapus',
        ]);

        $trx->logTimeline('admin', $this->admin->id, 'created_by_admin', 'Timeline uji coba');

        Storage::disk('public')->assertExists($filePath);

        // Admin jalankan operasi delete
        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.escrow.destroy', $trx->transaction_code));
        $deleteResponse->assertRedirect(route('admin.escrow.index'));

        // Pastikan transaksi dan relasinya terhapus
        $this->assertDatabaseMissing('escrow_transactions', ['transaction_code' => 'RKB-TEST-DELETE-01']);
        $this->assertDatabaseMissing('escrow_messages', ['id' => $msg->id]);
        $this->assertDatabaseMissing('escrow_timelines', ['escrow_transaction_id' => $trx->id]);

        // Pastikan file bukti deposit dibersihkan dari storage
        Storage::disk('public')->assertMissing($filePath);
    }

    public function test_ajax_chat_message_flow_and_polling_endpoint()
    {
        $trx = EscrowTransaction::forceCreate([
            'transaction_code' => 'RKB-TEST-CHAT-AJAX',
            'creator_id' => $this->customer->id,
            'creator_role' => 'buyer',
            'buyer_user_id' => $this->customer->id,
            'buyer_name' => 'Buyer Chat',
            'buyer_contact' => '@buyer_chat',
            'seller_user_id' => $this->seller->id,
            'seller_name' => 'Seller Chat',
            'seller_contact' => '@seller_chat',
            'title' => 'Transaksi Obrolan Realtime',
            'amount' => 100000,
            'fee_amount' => 1500,
            'fee_payer' => 'buyer',
            'total_buyer_pay' => 101500,
            'total_seller_receive' => 100000,
            'status' => EscrowTransaction::STATUS_FUNDS_HELD,
            'public_token' => 'token-test-chat-ajax-01',
        ]);

        // 1. Kirim pesan via AJAX request
        $sendResponse = $this->actingAs($this->customer)->postJson(route('escrow.send-message', $trx->transaction_code), [
            'message' => 'Halo penjual, dana sudah diamankan di rekber. Mohon serahkan akun.',
        ]);

        $sendResponse->assertStatus(200);
        $sendResponse->assertJsonStructure([
            'success',
            'total_messages',
            'message' => [
                'id',
                'sender_id',
                'sender_role',
                'sender_name',
                'message',
                'is_mine',
                'time',
            ],
        ]);
        $sendResponse->assertJson([
            'success' => true,
            'total_messages' => 1,
            'message' => [
                'message' => 'Halo penjual, dana sudah diamankan di rekber. Mohon serahkan akun.',
                'sender_role' => 'buyer',
                'is_mine' => true,
            ],
        ]);

        // 2. Fetch polling pesan via JSON
        $pollResponse = $this->actingAs($this->customer)->getJson(route('escrow.messages', $trx->transaction_code));
        $pollResponse->assertStatus(200);
        $pollResponse->assertJsonStructure([
            'success',
            'count',
            'total_messages',
            'messages',
            'status',
        ]);
        $this->assertEquals(1, $pollResponse->json('count'));

        // 3. Public fetch polling pesan via public token
        $publicPollResponse = $this->getJson(route('escrow.public.messages', $trx->public_token));
        $publicPollResponse->assertStatus(200);
        $this->assertEquals(1, $publicPollResponse->json('count'));
    }
}
