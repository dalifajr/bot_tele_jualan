<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain');

echo "CLOUDFLARE TURNSTILE DIAGNOSTIC:\n\n";

$siteKeyConfig = config('services.turnstile.site_key');
$secretKeyConfig = config('services.turnstile.secret_key');
$siteKeyEnv = env('CLOUDFLARE_TURNSTILE_SITE_KEY');
$secretKeyEnv = env('CLOUDFLARE_TURNSTILE_SECRET_KEY');

echo "1. CONFIG VALUES:\n";
echo "   - site_key: " . ($siteKeyConfig ?: 'EMPTY') . "\n";
echo "   - secret_key: " . ($secretKeyConfig ? substr($secretKeyConfig, 0, 8) . '...' : 'EMPTY') . "\n\n";

echo "2. ENV VALUES:\n";
echo "   - site_key: " . ($siteKeyEnv ?: 'EMPTY') . "\n";
echo "   - secret_key: " . ($secretKeyEnv ? substr($secretKeyEnv, 0, 8) . '...' : 'EMPTY') . "\n\n";

if ($siteKeyConfig === $siteKeyEnv && $secretKeyConfig === $secretKeyEnv) {
    echo "=> Config and Env are in sync (OK).\n\n";
} else {
    echo "=> WARNING: Config and Env are NOT in sync! Did you cache config? Run: php artisan config:clear\n\n";
}

echo "3. CONNECTIVITY TEST TO CLOUDFLARE:\n";
$start = microtime(true);
try {
    $response = Illuminate\Support\Facades\Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'secret' => $secretKeyConfig,
        'response' => 'test-token-diagnostic-mock',
    ]);
    
    $duration = round(microtime(true) - $start, 3);
    echo "   - Status Code: " . $response->status() . "\n";
    echo "   - Duration: {$duration} seconds\n";
    echo "   - Response Body: " . $response->body() . "\n";
} catch (\Exception $e) {
    echo "   - Error connecting: " . $e->getMessage() . "\n";
}
