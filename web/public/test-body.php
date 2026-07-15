<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain');

echo "CLOUDFLARE TURNSTILE TEST KEYS VERIFICATION:\n\n";

$secret = '1x00000000000000000000000000000000'; // Cloudflare test secret (always passes)
$responseToken = '1x00000000000000000000000000000000'; // Cloudflare test token (always passes)

try {
    $response = Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'secret' => $secret,
        'response' => $responseToken,
    ]);
    
    echo "1. API Response status: " . $response->status() . "\n";
    echo "2. API Response body: " . $response->body() . "\n";
} catch (\Exception $e) {
    echo "Error connecting: " . $e->getMessage() . "\n";
}
