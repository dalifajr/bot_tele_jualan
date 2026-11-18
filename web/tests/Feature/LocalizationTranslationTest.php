<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\StockUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocalizationTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_switcher_sets_app_and_carbon_locale_to_english(): void
    {
        $this->from('/catalog')
            ->get('/lang/en')
            ->assertRedirect('/catalog');

        $response = $this->withSession(['locale' => 'en'])->get('/catalog');
        $response->assertStatus(200);
        $this->assertEquals('en', App::getLocale());
        $this->assertEquals('en', Carbon::getLocale());
        $response->assertSee('Product Catalog');
        $response->assertSee('Sign In Now');
        $response->assertSee('Create New Account');
        $response->assertDontSee('Katalog Produk');
        $response->assertDontSee('Masuk Sekarang');
    }

    public function test_customer_dashboard_renders_translated_english_elements_and_promos(): void
    {
        $user = User::forceCreate([
            'username' => 'john_tester',
            'full_name' => 'John Tester',
            'email' => 'john@tester.com',
            'role' => 'customer',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('YOUR SHOPPING SPACE');
        $response->assertSee('Total orders');
        $response->assertDontSee('Total pesanan');
        $response->assertSee('SPECIAL PROMO');
        $response->assertSee('Up to 30% Off Licenses');
        $response->assertDontSee('PROMO SPESIAL');
    }

    public function test_model_accessors_translate_dynamically_between_id_and_en(): void
    {
        $order = (new Order())->forceFill(['status' => 'pending_payment']);
        $stock = (new StockUnit())->forceFill(['created_at' => now()->subHours(5)]);

        App::setLocale('id');
        Carbon::setLocale('id');
        $this->assertEquals('Menunggu Pembayaran', $order->status_label);
        $this->assertEquals('5 jam yang lalu', $stock->localized_umur_akun);

        App::setLocale('en');
        Carbon::setLocale('en');
        $this->assertEquals('Waiting for Payment', $order->status_label);
        $this->assertEquals('5 hours ago', $stock->localized_umur_akun);
        $this->assertEquals('5 jam yang lalu', $stock->umur_akun);
    }

    public function test_en_json_dictionary_is_valid_and_preserves_placeholders(): void
    {
        $path = base_path('lang/en.json');
        $this->assertFileExists($path);

        $dictionary = json_decode(file_get_contents($path), true);
        $this->assertIsArray($dictionary);
        $this->assertGreaterThan(2700, count($dictionary));
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());

        foreach ($dictionary as $key => $value) {
            $cleanKey = preg_replace(['~https?://[^\s"\'<>]+~', '~style\s*=\s*["\'][^"\']*["\']~'], '', $key);
            $cleanVal = preg_replace(['~https?://[^\s"\'<>]+~', '~style\s*=\s*["\'][^"\']*["\']~'], '', $value);

            preg_match_all('/(?<![a-zA-Z0-9_]):([a-zA-Z_][a-zA-Z0-9_]*)/', $cleanKey, $keyMatches);
            preg_match_all('/(?<![a-zA-Z0-9_]):([a-zA-Z_][a-zA-Z0-9_]*)/', $cleanVal, $valMatches);

            $keyParams = array_unique($keyMatches[1] ?? []);
            $valParams = array_unique($valMatches[1] ?? []);
            sort($keyParams);
            sort($valParams);

            $this->assertEquals($keyParams, $valParams, "Placeholder mismatch on key: {$key}");
        }
    }
}
