<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockDividerParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_stock_with_equal_divider_format(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_test',
            'full_name' => 'Admin Test',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $product = Product::create([
            'name' => 'Microsoft Account',
            'price' => 25000,
            'description' => 'Test product',
            'creator_id' => $admin->id,
        ]);

        $bulkText = <<<TEXT
=========================================
Username: mansur.2019@test.com
Password: tanggekmania123
Login Result: SUCCESS
Status Akun Microsoft: Tidak ditemukan
=========================================
Username: mansur.2019@test.com
Password: tanggekmania123
Login Result: SUCCESS
Status Akun Microsoft: Tidak ditemukan
=========================================
TEXT;

        $response = $this->actingAs($admin)->post(route('admin.stock.store'), [
            'product_id' => $product->id,
            'stock_status' => 'ready',
            'raw_text' => $bulkText,
        ]);

        $response->assertRedirect(route('admin.stock.index'));
        $response->assertSessionHas('success');

        $this->assertEquals(2, StockUnit::where('product_id', $product->id)->count());
    }

    public function test_admin_can_upload_stock_with_underscore_divider_and_user_pass_keys(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_test2',
            'full_name' => 'Admin Test 2',
            'email' => 'admin2@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $product = Product::create([
            'name' => 'Jualan ID Account',
            'price' => 50000,
            'description' => 'Test product 2',
            'creator_id' => $admin->id,
        ]);

        $bulkText = <<<TEXT
_______________________________________________
user: 052239629
pass: @Utgiri08
login: jualan.id
status: ✅ Valid, harus update password (update password prompt)
dicek pada: 19:08:35 Minggu, 26-07-2026
_______________________________________________
_______________________________________________
user: 051633483
pass: Bukabuka30
login: jualan.id
status: ✅ Valid, berhasil login dashboard
dicek pada: 19:16:42 Minggu, 26-07-2026
_______________________________________________
TEXT;

        $response = $this->actingAs($admin)->post(route('admin.stock.store'), [
            'product_id' => $product->id,
            'stock_status' => 'ready',
            'raw_text' => $bulkText,
        ]);

        $response->assertRedirect(route('admin.stock.index'));
        $response->assertSessionHas('success');

        $stocks = StockUnit::where('product_id', $product->id)->get();
        $this->assertEquals(2, $stocks->count());
        $this->assertStringContainsString('user: 052239629', $stocks->first()->raw_text);
        $this->assertStringContainsString('pass: @Utgiri08', $stocks->first()->raw_text);
    }
}
