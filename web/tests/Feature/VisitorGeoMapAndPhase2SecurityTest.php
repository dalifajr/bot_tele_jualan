<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\LoginLog;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use App\Models\Visitor;
use App\Services\BackupService;
use App\Services\GeoIpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Tests\TestCase;
use ZipArchive;

class VisitorGeoMapAndPhase2SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::forceCreate(array_merge([
            'username' => 'u_' . uniqid(),
            'full_name' => 'Test User',
            'email' => 'u_' . uniqid() . '@example.com',
            'password' => Hash::make('AdminPass123!'),
            'role' => 'customer',
            'has_custom_password' => true,
        ], $overrides));
    }

    public function test_cart_rejects_negative_or_zero_quantity_price_manipulation(): void
    {
        $customer = $this->makeUser();
        $expensive = Product::create(['name' => 'Expensive License', 'price' => 500000, 'is_suspended' => false]);
        $cheap = Product::create(['name' => 'Cheap Account', 'price' => 100000, 'is_suspended' => false]);
        StockUnit::create(['product_id' => $expensive->id, 'raw_text' => 'k1', 'is_sold' => false, 'stock_status' => 'ready']);
        StockUnit::create(['product_id' => $cheap->id, 'raw_text' => 'k2', 'is_sold' => false, 'stock_status' => 'ready']);

        // Add negative quantity via JSON API -> 422
        $this->actingAs($customer)
            ->postJson(route('cart.add', $cheap->id), ['quantity' => -5])
            ->assertStatus(422);

        // Add zero quantity -> 422
        $this->actingAs($customer)
            ->postJson(route('cart.add', $cheap->id), ['quantity' => 0])
            ->assertStatus(422);

        // Even if corrupted negative item exists in DB, checkout refuses to process
        CartItem::create(['user_id' => $customer->id, 'product_id' => $expensive->id, 'quantity' => 1]);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $cheap->id, 'quantity' => -5]);

        $res = $this->actingAs($customer)->post(route('cart.process'));
        $res->assertRedirect();
        $res->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_admin_export_endpoints_block_get_and_require_valid_password(): void
    {
        $admin = $this->makeUser(['role' => 'admin', 'password' => Hash::make('TopSecret99!')]);

        // GET method must be blocked (405 Method Not Allowed)
        $this->actingAs($admin)->get('/admin/stock/export')->assertStatus(405);
        $this->actingAs($admin)->get('/admin/users/export')->assertStatus(405);

        // POST without password -> validation error
        $this->actingAs($admin)->post(route('admin.stock.export'))->assertSessionHasErrors(['password']);
        $this->actingAs($admin)->post(route('admin.users.export'))->assertSessionHasErrors(['password']);

        // POST with wrong password -> rejected with error
        $this->actingAs($admin)->post(route('admin.stock.export'), ['password' => 'wrong'])->assertSessionHas('error');

        // POST with valid password -> 200 xlsx download
        $this->actingAs($admin)->post(route('admin.users.export'), ['password' => 'TopSecret99!'])->assertStatus(200);
    }

    public function test_backup_restore_media_blocks_zip_slip_and_php_files(): void
    {
        $tmpZip = storage_path('app/temp_test_zipslip_' . uniqid() . '.zip');
        File::ensureDirectoryExists(dirname($tmpZip));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('media/../../escaped_evil.txt', 'pwned');
        $zip->addFromString('media/avatars/shell.php', '<?php echo "evil";');
        $zip->addFromString('media/avatars/safe_avatar_test.txt', 'safe-content');
        $zip->close();

        try {
            $zipRead = new ZipArchive();
            $this->assertTrue($zipRead->open($tmpZip) === true);

            $method = new ReflectionMethod(BackupService::class, 'restoreMediaFromZip');
            $method->setAccessible(true);
            $method->invoke(null, $zipRead);
            $zipRead->close();

            $this->assertFileDoesNotExist(storage_path('escaped_evil.txt'));
            $this->assertFileDoesNotExist(storage_path('app/public/avatars/shell.php'));
            $this->assertFileExists(storage_path('app/public/avatars/safe_avatar_test.txt'));
        } finally {
            @unlink($tmpZip);
            @unlink(storage_path('app/public/avatars/safe_avatar_test.txt'));
        }
    }

    public function test_geoip_service_resolves_public_ips_and_historical_location_text(): void
    {
        $this->assertFalse(GeoIpService::isPublicIp('127.0.0.1'));
        $this->assertFalse(GeoIpService::isPublicIp('192.168.1.15'));
        $this->assertFalse(GeoIpService::isPublicIp('10.0.0.5'));
        $this->assertTrue(GeoIpService::isPublicIp('8.8.8.8'));

        $jakarta = GeoIpService::resolveFromLocationText('Jakarta, ID');
        $this->assertNotNull($jakarta);
        $this->assertEquals('ID', $jakarta['country_code']);
        $this->assertEquals('Jakarta', $jakarta['city']);

        $singapore = GeoIpService::resolveFromLocationText('Singapore, SG');
        $this->assertNotNull($singapore);
        $this->assertEquals('SG', $singapore['country_code']);
    }

    public function test_track_visitor_records_public_catalog_visits_and_increments_hits(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->withHeaders(['CF-IPCountry' => 'ID'])
            ->get('/catalog')
            ->assertStatus(200);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->withHeaders(['CF-IPCountry' => 'ID'])
            ->get('/dashboard')
            ->assertStatus(200);

        $visitor = Visitor::where('ip_address', '8.8.8.8')->first();
        $this->assertNotNull($visitor);
        $this->assertEquals(2, $visitor->hits_count);
        $this->assertEquals('/dashboard', $visitor->last_path);
        $this->assertEquals('ID', $visitor->country_code);
        $this->assertNotNull($visitor->latitude);
    }

    public function test_admin_logins_page_aggregates_and_renders_visitor_and_login_geo_map(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        // Visitor dengan koordinat eksplisit (Surabaya)
        Visitor::create([
            'ip_address' => '103.10.10.10',
            'visited_date' => now()->toDateString(),
            'location' => 'Surabaya, Indonesia',
            'country' => 'Indonesia',
            'country_code' => 'ID',
            'city' => 'Surabaya',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'hits_count' => 4,
            'last_path' => '/catalog',
        ]);

        // Historical LoginLog tanpa lat/lng (akan di-resolve dari teks location "Jakarta, ID")
        LoginLog::create([
            'user_id' => $admin->id,
            'username_or_email' => $admin->username,
            'ip_address' => '103.20.20.20',
            'is_successful' => true,
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
            'location' => 'Jakarta, ID',
        ]);

        // Failed LoginLog dari Singapore
        LoginLog::create([
            'user_id' => null,
            'username_or_email' => 'attacker',
            'ip_address' => '45.64.10.10',
            'is_successful' => false,
            'device_type' => 'Desktop',
            'browser' => 'Firefox',
            'location' => 'Singapore, SG',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.logins.index'));
        $response->assertStatus(200);
        $response->assertSee('id="visitorGeoMap"', false);
        $response->assertSee('Peta Sebaran Lokasi Pengunjung &amp; Percobaan Login', false);
        $response->assertSee('Surabaya, Indonesia');
        $response->assertSee('Jakarta, Indonesia');
        $response->assertSee('Singapore');

        $points = $response->viewData('geoMapPoints');
        $summary = $response->viewData('geoMapSummary');

        $this->assertCount(3, $points);
        $this->assertEquals(3, $summary['mapped_locations']);
        $this->assertEquals(2, $summary['unique_countries']);
        $this->assertEquals(1, $summary['visitors_today']);
    }
}
