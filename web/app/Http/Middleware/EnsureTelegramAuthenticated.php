<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTelegramAuthenticated
{
    /**
     * Handle an incoming request.
     * Ensures the user is authenticated via Telegram login flow.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Auto-authenticate atau sinkronisasi sesi ketika membuka detail pesanan dari tombol Bot Telegram
        if ($request->routeIs('orders.show') && $request->filled('token')) {
            $orderParam = $request->route('id');
            $order = \App\Models\Order::with('customer')
                ->where(function ($q) use ($orderParam) {
                    $q->where('id', $orderParam)->orWhere('order_ref', $orderParam);
                })
                ->first();

            if ($order && $order->verifyTelegramAccessToken($request->query('token'))) {
                $customer = $order->customer;
                if ($customer && !$customer->is_suspended) {
                    if (!Auth::check() || (Auth::id() !== $customer->id && Auth::user()->role !== 'admin')) {
                        Auth::login($customer, true);
                    }
                }
            }
        }

        if (!Auth::check()) {
            return redirect()->guest(route('login'))->with('error', 'Silakan login terlebih dahulu.');
        }

        $user = Auth::user();

        if ($user->is_suspended && !$request->routeIs('suspended') && !$request->routeIs('logout')) {
            return redirect()->route('suspended');
        }

        return $next($request);
    }
}
