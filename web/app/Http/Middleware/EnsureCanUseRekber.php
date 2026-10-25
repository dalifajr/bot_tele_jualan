<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanUseRekber
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->canUseRekber()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Akses fitur Rekber untuk akun Anda telah dinonaktifkan oleh Administrator.')
                ], 403);
            }

            return redirect()->route('dashboard')
                ->with('error', __('Akses fitur Rekber untuk akun Anda telah dinonaktifkan oleh Administrator. Silakan hubungi admin.'));
        }

        return $next($request);
    }
}
