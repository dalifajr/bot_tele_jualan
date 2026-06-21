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
        \Illuminate\Pagination\Paginator::useBootstrapFive();
        \Illuminate\Support\Facades\View::composer('layouts.app', \App\Http\View\Composers\NotificationComposer::class);

        // Dynamic Timezone & App Name Configuration
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('bot_settings')) {
                $timezone = \Illuminate\Support\Facades\DB::table('bot_settings')->where('key', 'system_timezone')->value('value');
                if ($timezone) {
                    config(['app.timezone' => $timezone]);
                    date_default_timezone_set($timezone);
                }

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
