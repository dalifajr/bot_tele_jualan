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
        $ip = $request->ip();
        $date = now()->toDateString();

        try {
            \App\Models\Visitor::firstOrCreate([
                'ip_address' => $ip,
                'visited_date' => $date
            ]);
        } catch (\Exception $e) {
            // Ignore if there is a race condition or db issue
        }

        return $next($request);
    }
}
