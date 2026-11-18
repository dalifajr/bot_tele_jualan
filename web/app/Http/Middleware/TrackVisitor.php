<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackVisitor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && !$request->expectsJson()) {
            $ip = $request->ip();
            $date = now()->toDateString();
            $path = '/' . ltrim($request->path(), '/');

            try {
                $visitor = \App\Models\Visitor::where('ip_address', $ip)
                    ->where('visited_date', $date)
                    ->first();

                if (!$visitor) {
                    $geo = \App\Services\GeoIpService::resolve($ip, $request->header('CF-IPCountry'));
                    \App\Models\Visitor::create([
                        'ip_address' => $ip,
                        'visited_date' => $date,
                        'location' => $geo['location'] ?? 'Local / Unknown',
                        'country' => $geo['country'] ?? null,
                        'country_code' => $geo['country_code'] ?? null,
                        'region' => $geo['region'] ?? null,
                        'city' => $geo['city'] ?? null,
                        'latitude' => $geo['latitude'] ?? null,
                        'longitude' => $geo['longitude'] ?? null,
                        'last_path' => substr($path, 0, 255),
                        'hits_count' => 1,
                    ]);
                } else {
                    $updates = ['last_path' => substr($path, 0, 255)];
                    if ($visitor->latitude === null && \App\Services\GeoIpService::isPublicIp($ip)) {
                        $geo = \App\Services\GeoIpService::resolve($ip, $request->header('CF-IPCountry'));
                        $updates = array_merge($updates, [
                            'location' => $geo['location'] ?? $visitor->location,
                            'country' => $geo['country'] ?? $visitor->country,
                            'country_code' => $geo['country_code'] ?? $visitor->country_code,
                            'region' => $geo['region'] ?? $visitor->region,
                            'city' => $geo['city'] ?? $visitor->city,
                            'latitude' => $geo['latitude'] ?? $visitor->latitude,
                            'longitude' => $geo['longitude'] ?? $visitor->longitude,
                        ]);
                    }
                    $visitor->increment('hits_count', 1, $updates);
                }
            } catch (\Throwable $e) {
                // Ignore race condition / DB hiccup so visitor tracking never blocks user traffic
            }
        }

        return $next($request);
    }
}
