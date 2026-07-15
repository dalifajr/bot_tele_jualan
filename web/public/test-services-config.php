<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain');

echo "TELEGRAM CONFIG DIAGNOSTIC:\n\n";
echo "1. services.telegram.bot_token: " . (config('services.telegram.bot_token') ? 'TERISI (OK)' : 'KOSONG (FAIL)') . "\n";
echo "2. env('TELEGRAM_BOT_TOKEN'): " . (env('TELEGRAM_BOT_TOKEN') ? 'TERISI (OK)' : 'KOSONG (FAIL)') . "\n";
echo "3. services.telegram.bot_username: " . (config('services.telegram.bot_username') ?: 'KOSONG') . "\n";

echo "\nJIKA services.telegram.bot_token KOSONG TETAPI env() TERISI,\n";
echo "MAKA ANDA HARUS MENJALANKAN PERINTAH: php artisan config:clear DI VPS ANDA!\n";
