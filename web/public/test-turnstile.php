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
    echo "=> Config and Env are in sync (OK).\n";
} else {
    echo "=> WARNING: Config and Env are NOT in sync! Did you cache config? Run: php artisan config:clear\n";
}

if ($siteKeyConfig === $secretKeyConfig && $siteKeyConfig !== '1x00000000000000000000AA') {
    echo "=> CRITICAL ERROR: site_key and secret_key are IDENTICAL! You probably copied the site_key into the secret_key field by mistake!\n";
} else {
    echo "=> site_key and secret_key are different (OK).\n";
}

// Check for whitespaces/malformed keys
$siteKeyHasWhitespace = (strlen($siteKeyConfig) !== strlen(trim($siteKeyConfig)));
$secretKeyHasWhitespace = (strlen($secretKeyConfig) !== strlen(trim($secretKeyConfig)));

echo "\n4. KEY VALIDATION CHECKS:\n";
echo "   - site_key length: " . strlen($siteKeyConfig) . " chars\n";
echo "   - secret_key length: " . strlen($secretKeyConfig) . " chars\n";
echo "   - site_key has whitespace: " . ($siteKeyHasWhitespace ? 'YES (WARNING: Remove spaces in .env)' : 'NO (OK)') . "\n";
echo "   - secret_key has whitespace: " . ($secretKeyHasWhitespace ? 'YES (WARNING: Remove spaces in .env)' : 'NO (OK)') . "\n";

// A typical secret key is 40 characters long
if (strlen($secretKeyConfig) !== 40 && $secretKeyConfig !== '1x00000000000000000000000000000000') {
    echo "   - WARNING: Secret key length is " . strlen($secretKeyConfig) . " chars, but typical Cloudflare Turnstile secret keys are exactly 40 chars long!\n";
} else {
    echo "   - Secret key length matches standard (40 chars) (OK).\n";
}
echo "\n";

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
