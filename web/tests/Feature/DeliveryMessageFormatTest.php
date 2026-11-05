<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryMessageFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => 'dummy-bot-token']);
        config(['telegram.domain' => 'https://toko.example.com']);
    }

    public function test_delivery_message_formats_single_product_with_webapp_button()
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $customer = User::forceCreate([
            'telegram_id' => 999888,
            'username' => 'buyer_single',
            'email' => 'buyer_single@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Netflix 1 Bulan 4K',
            'price' => 35000,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-SINGLE-001',
            'customer_id' => $customer->id,
            'subtotal' => 35000,
            'unique_code' => 50,
            'total_amount' => 35050,
            'status' => 'delivered',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 35000,
        ]);

        TelegramService::notifyCustomerOrderDelivered($order, []);

        Http::assertSent(function ($request) use ($order) {
            if (!str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            $text = $request['text'];
            $this->assertStringContainsString('✅ <b>Pembayaran Berhasil Dikonfirmasi</b>', $text);
            $this->assertStringContainsString('Order Ref: <code>ORD-SINGLE-001</code>', $text);
            $this->assertStringContainsString('Produk: Netflix 1 Bulan 4K', $text);
            $this->assertStringContainsString('Harga: Rp 35.000', $text);
            $this->assertStringContainsString('Qty: 1', $text);
            $this->assertStringContainsString("Nilai Pesanan:\nRp 35.050", $text);
            $this->assertStringContainsString('Silakan cek pesanan dengan klik tombol dibawah:', $text);

            $markup = json_decode($request['reply_markup'], true);
            $btn = $markup['inline_keyboard'][0][0];
            $expectedToken = $order->generateTelegramAccessToken();
            $this->assertEquals('📱 Cek Detail Pesanan', $btn['text']);
            $this->assertEquals("https://toko.example.com/orders/{$order->id}?token={$expectedToken}", $btn['web_app']['url']);

            return true;
        });
    }

    public function test_delivery_message_formats_multiple_products_simultaneously()
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $customer = User::forceCreate([
            'telegram_id' => 888777,
            'username' => 'buyer_multi',
            'email' => 'buyer_multi@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $prod1 = Product::create(['name' => 'Canva Pro Edu 1 Tahun', 'price' => 15000]);
        $prod2 = Product::create(['name' => 'YouTube Premium 3 Bulan', 'price' => 25000]);

        $order = Order::create([
            'order_ref' => 'ORD-MULTI-999',
            'customer_id' => $customer->id,
            'subtotal' => 65000, // (15000 * 1) + (25000 * 2) = 65000
            'unique_code' => 120,
            'total_amount' => 65120,
            'status' => 'delivered',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $prod1->id,
            'quantity' => 1,
            'unit_price' => 15000,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $prod2->id,
            'quantity' => 2,
            'unit_price' => 25000,
        ]);

        TelegramService::notifyCustomerOrderDelivered($order, []);

        Http::assertSent(function ($request) use ($order) {
            if (!str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            $text = $request['text'];
            // Cek produk 1
            $this->assertStringContainsString('Produk: Canva Pro Edu 1 Tahun', $text);
            $this->assertStringContainsString('Harga: Rp 15.000', $text);
            $this->assertStringContainsString('Qty: 1', $text);

            // Cek produk 2
            $this->assertStringContainsString('Produk: YouTube Premium 3 Bulan', $text);
            $this->assertStringContainsString('Harga: Rp 25.000', $text);
            $this->assertStringContainsString('Qty: 2', $text);

            // Cek total dan tombol
            $this->assertStringContainsString("Nilai Pesanan:\nRp 65.120", $text);

            $markup = json_decode($request['reply_markup'], true);
            $btn = $markup['inline_keyboard'][0][0];
            $expectedToken = $order->generateTelegramAccessToken();
            $this->assertEquals("https://toko.example.com/orders/{$order->id}?token={$expectedToken}", $btn['web_app']['url']);

            return true;
        });
    }

    public function test_clicking_order_detail_button_with_token_auto_authenticates_guest_and_avoids_404()
    {
        $customer = User::forceCreate([
            'telegram_id' => 777666,
            'username' => 'web_buyer',
            'email' => 'web_buyer@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Spotify Family 1 Bulan',
            'price' => 20000,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-TOKEN-100',
            'customer_id' => $customer->id,
            'subtotal' => 20000,
            'unique_code' => 77,
            'total_amount' => 20077,
            'status' => 'delivered',
        ]);

        $stock = StockUnit::forceCreate([
            'product_id' => $product->id,
            'raw_text' => 'spotify_user@mail.com | pass123',
            'is_sold' => true,
            'sold_order_id' => $order->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'stock_unit_id' => $stock->id,
        ]);

        $token = $order->generateTelegramAccessToken();

        // 1. Akses sebagai Guest (seperti baru membuka WebView Telegram)
        $response = $this->get("/orders/{$order->id}?token={$token}");
        $response->assertStatus(200);
        $response->assertSee('ORD-TOKEN-100');
        $response->assertSee('Spotify Family 1 Bulan');
        $response->assertSee('spotify_user@mail.com | pass123');
        $this->assertAuthenticatedAs($customer);
    }

    public function test_clicking_order_detail_button_with_token_switches_mismatched_customer_session_and_supports_order_ref()
    {
        $realBuyer = User::forceCreate([
            'telegram_id' => null, // Checkout via browser web tanpa link Telegram
            'username' => 'browser_buyer',
            'email' => 'browser_buyer@example.com',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $oldWebviewAccount = User::forceCreate([
            'telegram_id' => 555444,
            'username' => 'tg_555444',
            'email' => 'tg_555444@telegram.local',
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'ChatGPT Plus 1 Bulan',
            'price' => 45000,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-BROWSER-777',
            'customer_id' => $realBuyer->id,
            'checkout_chat_id' => 555444,
            'subtotal' => 45000,
            'unique_code' => 12,
            'total_amount' => 45012,
            'status' => 'delivered',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 45000,
        ]);

        $token = $order->generateTelegramAccessToken();

        // Simulasikan WebView Telegram masih nyangkut session akun tg_555444
        // dan mengakses baik via ID maupun via order_ref
        $responseById = $this->actingAs($oldWebviewAccount)->get("/orders/{$order->id}?token={$token}");
        $responseById->assertStatus(200);
        $responseById->assertSee('ORD-BROWSER-777');
        $this->assertAuthenticatedAs($realBuyer);

        $responseByRef = $this->actingAs($realBuyer)->get("/orders/{$order->order_ref}");
        $responseByRef->assertStatus(200);
        $responseByRef->assertSee('ORD-BROWSER-777');
    }
}
