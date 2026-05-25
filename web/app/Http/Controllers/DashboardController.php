<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $userId = $user->id;

        $totalOrders = Order::where('customer_id', $userId)->count();
        $completedOrders = Order::where('customer_id', $userId)->where('status', 'delivered')->count();
        $pendingOrders = Order::where('customer_id', $userId)->whereIn('status', ['pending_payment', 'paid'])->count();
        $totalSpent = Order::where('customer_id', $userId)->where('status', 'delivered')->sum('total_amount');

        $recentOrders = Order::where('customer_id', $userId)
            ->with('items.product')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'totalOrders',
            'completedOrders',
            'pendingOrders',
            'totalSpent',
            'recentOrders'
        ));
    }
}
