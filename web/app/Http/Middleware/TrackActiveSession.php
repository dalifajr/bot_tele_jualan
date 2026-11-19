<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use App\Services\GeoIpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrackActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && $request->hasSession()) {
            try {
                if (!Schema::hasTable('user_sessions')) {
                    return $next($request);
                }

                $user = Auth::user();
                $sessionId = $request->session()->getId();

                if ($sessionId !== '') {
                    $existing = UserSession::where('session_id', $sessionId)->first();

                    if ($existing && $existing->revoked_at !== null) {
                        Auth::logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();

                        if ($request->expectsJson()) {
                            return response()->json([
                                'message' => __('Sesi Anda telah dicabut dari perangkat lain. Silakan masuk kembali.'),
                            ], 401);
                        }

                        return redirect()->route('login')
                            ->with('error', __('Sesi Anda telah diakhiri dari perangkat lain. Silakan masuk kembali.'));
                    }

                    $shouldTouch = !$existing
                        || (int) $existing->user_id !== (int) $user->id
                        || !$existing->last_active_at
                        || $existing->last_active_at->diffInSeconds(now()) >= 60;

                    if ($shouldTouch) {
                        $ip = $request->ip();
                        $locationLabel = $existing?->location_label;

                        if (empty($locationLabel) || $existing?->ip_address !== $ip) {
                            $geo = GeoIpService::resolve($ip);
                            if (!empty($geo['city']) && !empty($geo['country'])) {
                                $locationLabel = "{$geo['city']}, {$geo['country']}";
                            } elseif (!empty($geo['country'])) {
                                $locationLabel = $geo['country'];
                            } else {
                                $locationLabel = 'Local / Unknown';
                            }
                        }

                        UserSession::updateOrCreate(
                            ['session_id' => $sessionId],
                            [
                                'user_id' => $user->id,
                                'ip_address' => $ip,
                                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                                'device_label' => UserSession::parseDeviceLabel($request->userAgent()),
                                'location_label' => substr((string) $locationLabel, 0, 150),
                                'last_active_at' => now(),
                                'revoked_at' => null,
                            ]
                        );
                    }
                }
            } catch (\Throwable $e) {
                // Jangan memutus request bila database/table belum siap (mis. selama migrasi)
            }
        }

        return $next($request);
    }
}
