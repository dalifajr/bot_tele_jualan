<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $status = $request->query('status');

        $query = Order::where('customer_id', $userId)->with('items.product')->orderByDesc('id');

        if ($status && in_array($status, ['pending_payment', 'paid', 'delivered', 'cancelled', 'expired'])) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(15);

        return view('orders.index', compact('orders', 'status'));
    }

    public function show($id)
    {
        $order = Order::where('customer_id', Auth::id())
            ->with(['items.product', 'stockUnits'])
            ->findOrFail($id);

        return view('orders.show', compact('order'));
    }
}
