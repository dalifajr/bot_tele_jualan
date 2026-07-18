<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/login', 'GET');
$response = $kernel->handle($request);
echo 'STATUS: ' . $response->getStatusCode() . "\n";
