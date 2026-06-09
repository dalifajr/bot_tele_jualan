<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\HeldFund;
use Carbon\Carbon;

echo "Current config timezone: " . config('app.timezone') . "\n";
echo "Current PHP date_default_timezone: " . date_default_timezone_get() . "\n";
echo "Current App local time: " . now()->toDateTimeString() . "\n";
echo "Current App UTC time: " . now()->setTimezone('UTC')->toDateTimeString() . "\n";

$funds = HeldFund::with(['order', 'seller'])->get();
echo "\nTotal Held Funds in Database: " . $funds->count() . "\n";
foreach ($funds as $fund) {
    echo "ID: {$fund->id}\n";
    echo "  Order ID: {$fund->order_id} (Ref: " . ($fund->order->order_ref ?? 'N/A') . ")\n";
    echo "  Seller ID: {$fund->seller_id} (User: " . ($fund->seller->username ?? 'N/A') . ")\n";
    echo "  Amount: {$fund->amount}\n";
    echo "  Status: {$fund->status}\n";
    echo "  Release At: {$fund->release_at} (Raw: " . $fund->getRawOriginal('release_at') . ")\n";
    echo "  Is Passed?: " . ($fund->release_at->lte(now()) ? 'YES' : 'NO') . "\n";
    echo "---------------------------------\n";
}
