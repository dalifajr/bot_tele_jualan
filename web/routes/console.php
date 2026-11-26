<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:release-expired')->everyMinute();
Schedule::command('funds:release-held')->everyMinute();
Schedule::command('broadcast:calculate-reads')->everyFiveMinutes();
Schedule::command('vpn:sync-status')->everyMinute()->withoutOverlapping();
Schedule::command('vpn:sync-traffic')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('vpn:notify-expiring')->hourly()->withoutOverlapping();
