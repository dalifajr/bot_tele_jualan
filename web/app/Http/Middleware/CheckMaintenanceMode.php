<?php

namespace App\Http\Middleware;

use App\Models\BotSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isMaintenance = BotSetting::where('key', 'maintenance_mode')->value('value') === '1';

        if (!$isMaintenance) {
            return $next($request);
        }

        // If user is authenticated and is an admin, allow full access
        if (Auth::check() && Auth::user()->role === 'admin') {
            return $next($request);
        }

        $maintenanceMessage = BotSetting::where('key', 'maintenance_message')->value('value')
            ?: 'Website sedang dalam pemeliharaan sistem (Maintenance). Silakan kembali beberapa saat lagi.';

        // Allow logout and language switcher even during maintenance
        if ($request->routeIs('logout') || $request->is('logout') || $request->routeIs('lang.switch') || $request->is('lang/*')) {
            return $next($request);
        }

        // Allow payment callback routes (Midtrans, etc.)
        if ($request->is('api/payment/*')) {
            return $next($request);
        }

        // Allow login routes so Admin can log in
        if ($request->routeIs('login') || $request->routeIs('login.post') || $request->is('login*')) {
            return $next($request);
        }

        // Allow telegram auth endpoints to process login (AuthController/TelegramAuthController will handle non-admin)
        if ($request->routeIs('auth.telegram.*') || $request->is('auth/telegram/*')) {
            return $next($request);
        }

        // If request is JSON / AJAX / API, return 503 response
        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()->json([
                'success' => false,
                'maintenance' => true,
                'message' => $maintenanceMessage,
            ], 503);
        }

        // Render maintenance view for authenticated non-admins and visitors
        return response()->view('auth.maintenance', [
            'maintenanceMessage' => $maintenanceMessage,
        ], 503);
    }
}
