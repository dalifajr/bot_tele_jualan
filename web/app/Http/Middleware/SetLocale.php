<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('locale')) {
            $locale = session()->get('locale');
            if (in_array($locale, ['id', 'en'])) {
                App::setLocale($locale);
            }
        } else {
            App::setLocale('id'); // Fallback to Indonesian
        }

        return $next($request);
    }
}
