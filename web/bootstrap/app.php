<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('orders:release-expired')->everyMinute();
        $schedule->command('funds:release-held')->hourly();
        $schedule->command('app:auto-backup')->hourly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'seller' => \App\Http\Middleware\EnsureSeller::class,
            'telegram.auth' => \App\Http\Middleware\EnsureTelegramAuthenticated::class,
            'tool.access' => \App\Http\Middleware\EnsureToolAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
