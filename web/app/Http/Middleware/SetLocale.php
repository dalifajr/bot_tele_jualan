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
        $targetLocale = 'id';
        if (session()->has('locale')) {
            $locale = session()->get('locale');
            if (in_array($locale, ['id', 'en'])) {
                $targetLocale = $locale;
            }
        }

        App::setLocale($targetLocale);
        \Carbon\Carbon::setLocale($targetLocale);

        return $next($request);
    }
}
