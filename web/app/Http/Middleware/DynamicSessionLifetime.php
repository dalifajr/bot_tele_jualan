<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\BotSetting;
use Illuminate\Support\Facades\Cache;

class DynamicSessionLifetime
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $role = strtolower($user->role ?? 'customer');

            // Map setting key (session_lifetime_admin, session_lifetime_seller, session_lifetime_customer)
            $settingKey = 'session_lifetime_' . $role;

            // Fetch dynamic lifetime from cache (cache for 60 seconds)
            $lifetime = Cache::remember('session_lifetime_' . $role, 60, function () use ($settingKey, $role) {
                try {
                    $dbValue = BotSetting::where('key', $settingKey)->value('value');
                    if ($dbValue) {
                        return (int) $dbValue;
                    }
                } catch (\Exception $e) {
                    // Fallback
                }

                // Fallback default values in minutes
                switch ($role) {
                    case 'admin':
                        return 120; // 2 hours
                    case 'seller':
                        return 1440; // 1 day
                    case 'customer':
                    default:
                        return 43200; // 30 days
                }
            });

            // Set the session config dynamically for the current request
            config(['session.lifetime' => $lifetime]);
        }

        return $next($request);
    }
}
