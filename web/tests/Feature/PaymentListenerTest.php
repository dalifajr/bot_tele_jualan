<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
