<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_listener_test_connection_succeeds_with_valid_signature()
    {
        $secret = 'test-secret-key-super-secure';
        BotSetting::create([
            'key' => 'listener_shared_secret',
            'value' => $secret,
        ]);

        $timestamp = (string)time();
        $payload = ['device_id' => 'android-test-device', 'app_version' => '0.1.0'];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        $response = $this->postJson('/listener/test-connection', $payload, [
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'message' => 'Koneksi listener valid ke server web',
            ]);
    }

    public function test_listener_rejects_invalid_signature_with_401()
    {
        $secret = 'test-secret-key-super-secure';
        BotSetting::create([
            'key' => 'listener_shared_secret',
            'value' => $secret,
        ]);

        $timestamp = (string)time();
        $payload = ['device_id' => 'android-test-device'];
        $body = json_encode($payload);
        $signature = 'invalid-signature-value';

        $response = $this->postJson('/listener/test-connection', $payload, [
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'detail' => 'Signature tidak valid',
            ]);
    }

    public function test_listener_cleans_quotes_and_whitespaces_in_stored_secret()
    {
        // Simulasi secret tersimpan dengan kutip dan whitespace
        BotSetting::create([
            'key' => 'listener_shared_secret',
            'value' => ' "quoted-secret-key" ',
        ]);

        $cleanSecret = 'quoted-secret-key';
        $timestamp = (string)time();
        $payload = ['device_id' => 'device-1'];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $cleanSecret);

        $response = $this->postJson('/listener/test-connection', $payload, [
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(200);
    }

    public function test_listener_delivers_catalog_order_and_sets_status_to_delivered()
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        $secret = 'secret-catalog-12345';
        BotSetting::create([
            'key' => 'listener_shared_secret',
            'value' => $secret,
        ]);

        $customer = User::create([
            'telegram_id' => 123456789,
            'username' => 'testbuyer',
            'full_name' => 'Test Buyer',
            'email' => 'testbuyer@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Akun Premium Spotify',
            'price' => 50000,
            'stock' => 1,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_ref' => 'ORD-TEST-9988',
            'customer_id' => $customer->id,
            'subtotal' => 50000,
            'unique_code' => 123,
            'total_amount' => 50123,
            'status' => 'pending_payment',
            'expires_at' => now()->addMinutes(15),
        ]);
        $order->checkout_chat_id = 123456789;
        $order->checkout_message_id = 777;
        $order->save();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        $stockUnit = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'user@spotify.com:rahasia123',
            'stock_status' => 'reserved_checkout',
            'is_sold' => false,
            'sold_order_id' => $order->id,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_ref' => 'PAY-TEST-9988',
            'status' => 'pending',
            'expected_amount' => 50123,
            'method' => 'qris',
        ]);

        $timestamp = (string)time();
        $payload = [
            'amount' => 50123,
            'source_app' => 'SHOPEEPAY',
            'reference' => 'ORD-TEST-9988',
            'raw_text' => 'ShopeePay transfer Rp50.123 berhasil',
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        $response = $this->postJson('/listener/payment', $payload, [
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'paid',
                'matched_type' => 'order',
                'order_ref' => 'ORD-TEST-9988',
            ]);

        // Verifikasi bahwa order berstatus 'delivered' (Selesai), bukan hanya 'paid'
        $order->refresh();
        $this->assertEquals('delivered', $order->status);
        $this->assertEquals('Selesai', $order->status_label);
        $this->assertNotNull($order->delivered_at);

        // Verifikasi bahwa stok sudah terkonsumsi (is_sold = true)
        $stockUnit->refresh();
        $this->assertTrue((bool)$stockUnit->is_sold);

        // Verifikasi bahwa deleteMessage dipanggil untuk menghapus pesan QRIS lama
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'deleteMessage') &&
                   $request['chat_id'] == 123456789 &&
                   $request['message_id'] == 777;
        });

        // Verifikasi bahwa pesan delivery berisi kredensial akun dikirim ke pembeli
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage') &&
                   str_contains($request['text'], 'user@spotify.com:rahasia123');
        });
    }

    public function test_artisan_deliver_paid_orders_reconciles_stuck_orders()
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        $customer = User::create([
            'telegram_id' => 987654321,
            'username' => 'stuckbuyer',
            'email' => 'stuckbuyer@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $product = Product::create([
            'name' => 'Akun Netflix 1 Bulan',
            'price' => 35000,
            'stock' => 1,
            'is_active' => true,
        ]);

        // Order yang tersangkut di status 'paid'
        $order = Order::create([
            'order_ref' => 'ORD-STUCK-1234',
            'customer_id' => $customer->id,
            'subtotal' => 35000,
            'unique_code' => 0,
            'total_amount' => 35000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $order->checkout_chat_id = 987654321;
        $order->checkout_message_id = 888;
        $order->save();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 35000,
        ]);

        $stockUnit = StockUnit::create([
            'product_id' => $product->id,
            'raw_text' => 'netflix_user@email.com:passnetflix',
            'stock_status' => 'reserved_checkout',
            'is_sold' => false,
            'sold_order_id' => $order->id,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_ref' => 'PAY-STUCK-1234',
            'status' => 'paid',
            'expected_amount' => 35000,
            'received_amount' => 35000,
            'method' => 'qris',
        ]);

        // Jalankan perintah artisan recovery
        $this->artisan('orders:deliver-paid')
            ->expectsOutputToContain('ORD-STUCK-1234')
            ->assertExitCode(0);

        // Verifikasi status order berubah menjadi 'delivered' (Selesai)
        $order->refresh();
        $this->assertEquals('delivered', $order->status);
        $this->assertEquals('Selesai', $order->status_label);
        $this->assertNotNull($order->delivered_at);

        // Verifikasi stok unit
        $stockUnit->refresh();
        $this->assertTrue((bool)$stockUnit->is_sold);
    }
}
