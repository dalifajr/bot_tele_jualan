<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SavedTwoFactorAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SavedTwoFactorAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function createUser(array $attributes = []): User
    {
        static $counter = 1;
        return User::create(array_merge([
            'username' => 'testuser_' . $counter . '_' . uniqid(),
            'email' => 'test_' . $counter++ . '_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
        ], $attributes));
    }

    /**
     * Test user can access 2FA generator page with saved accounts.
     */
    public function test_user_can_view_2fa_generator_page_with_saved_accounts(): void
    {
        $user = $this->createUser();
        
        SavedTwoFactorAccount::create([
            'user_id' => $user->id,
            'name' => 'Akun Test GitHub',
            'secret' => 'JBSWY3DPEHPK3PXP',
            'issuer' => 'GitHub',
        ]);

        $response = $this->actingAs($user)->get(route('tools.2fa-generator'));
        $response->assertStatus(200);
        $response->assertSee('Akun Test GitHub');
        $response->assertSee('Akun Tersimpan');
    }

    /**
     * Test user can save a valid 2FA account.
     */
    public function test_user_can_save_valid_2fa_account(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson(route('tools.2fa-generator.accounts.store'), [
            'name' => 'Akun Toko Online',
            'secret' => 'JBSWY3DPEHPK3PXP',
            'issuer' => 'Google',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('saved_two_factor_accounts', [
            'user_id' => $user->id,
            'name' => 'Akun Toko Online',
            'secret' => 'JBSWY3DPEHPK3PXP',
            'issuer' => 'Google',
        ]);
    }

    /**
     * Test user cannot save account with invalid secret key.
     */
    public function test_user_cannot_save_account_with_invalid_secret(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson(route('tools.2fa-generator.accounts.store'), [
            'name' => 'Akun Rusak',
            'secret' => '89898989!@#$%^', // Bukan Base32
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);

        $this->assertDatabaseMissing('saved_two_factor_accounts', [
            'user_id' => $user->id,
            'name' => 'Akun Rusak',
        ]);
    }

    /**
     * Test user can update their own account name.
     */
    public function test_user_can_update_account_name(): void
    {
        $user = $this->createUser();
        $account = SavedTwoFactorAccount::create([
            'user_id' => $user->id,
            'name' => 'Nama Lama',
            'secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $response = $this->actingAs($user)->putJson(route('tools.2fa-generator.accounts.update', $account->id), [
            'name' => 'Nama Baru Yang Keren',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('saved_two_factor_accounts', [
            'id' => $account->id,
            'name' => 'Nama Baru Yang Keren',
        ]);
    }

    /**
     * Test user can delete their own account.
     */
    public function test_user_can_delete_account(): void
    {
        $user = $this->createUser();
        $account = SavedTwoFactorAccount::create([
            'user_id' => $user->id,
            'name' => 'Akun Mau Dihapus',
            'secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $response = $this->actingAs($user)->deleteJson(route('tools.2fa-generator.accounts.destroy', $account->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseMissing('saved_two_factor_accounts', [
            'id' => $account->id,
        ]);
    }

    /**
     * Test user cannot update or delete other user's account.
     */
    public function test_user_cannot_update_or_delete_other_user_account(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $account = SavedTwoFactorAccount::create([
            'user_id' => $user1->id,
            'name' => 'Akun Milik User 1',
            'secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        // User 2 mencoba update akun milik user 1
        $responseUpdate = $this->actingAs($user2)->putJson(route('tools.2fa-generator.accounts.update', $account->id), [
            'name' => 'Nama Dibajak',
        ]);
        $responseUpdate->assertStatus(404);

        // User 2 mencoba delete akun milik user 1
        $responseDelete = $this->actingAs($user2)->deleteJson(route('tools.2fa-generator.accounts.destroy', $account->id));
        $responseDelete->assertStatus(404);

        $this->assertDatabaseHas('saved_two_factor_accounts', [
            'id' => $account->id,
            'name' => 'Akun Milik User 1',
        ]);
    }
}
