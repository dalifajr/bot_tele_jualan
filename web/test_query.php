<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Symfony\Component\Process\PhpExecutableFinder;
$finder = new PhpExecutableFinder();
$php = $finder->find();

echo "Detected PHP CLI Binary: " . $php . PHP_EOL;
echo "PHP_BINARY constant: " . PHP_BINARY . PHP_EOL;
echo "PHP_OS_FAMILY: " . PHP_OS_FAMILY . PHP_EOL;
