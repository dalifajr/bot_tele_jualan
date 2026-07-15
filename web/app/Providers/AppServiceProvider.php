<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (request()->server('HTTP_X_FORWARDED_PROTO') == 'https' || config('app.env') !== 'local' || env('FORCE_HTTPS', true)) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Pagination\Paginator::useBootstrapFive();
        \Illuminate\Support\Facades\View::composer('layouts.app', \App\Http\View\Composers\NotificationComposer::class);

        // Dynamic App Name Configuration
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('bot_settings')) {
                $appName = \Illuminate\Support\Facades\DB::table('bot_settings')->where('key', 'app_name')->value('value');
                if ($appName) {
                    config(['app.name' => $appName]);
                }
            }
        } catch (\Exception $e) {
            // Prevent failure during CLI / migrations / DB not set up
        }
    }
}
