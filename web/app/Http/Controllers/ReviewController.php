<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * Store a newly created review in database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'product_id' => 'required|integer|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();

        // 1. Verify that the order belongs to the user and is delivered
        $order = Order::where('id', $validated['order_id'])
            ->where('customer_id', $user->id)
            ->where('status', 'delivered')
            ->first();

        if (!$order) {
            return back()->with('error', 'Anda hanya dapat memberikan ulasan pada pesanan yang sudah selesai.');
        }

        // 2. Verify that the product is part of the order
        $orderItemExists = OrderItem::where('order_id', $order->id)
            ->where('product_id', $validated['product_id'])
            ->exists();

        if (!$orderItemExists) {
            return back()->with('error', 'Produk tidak ditemukan dalam riwayat pesanan ini.');
        }

        // 3. Verify if user already reviewed this product for this order
        $alreadyReviewed = Review::where('order_id', $order->id)
            ->where('product_id', $validated['product_id'])
            ->exists();

        if ($alreadyReviewed) {
            return back()->with('error', 'Anda sudah memberikan ulasan untuk produk ini pada pesanan ini.');
        }

        // 4. Create review
        Review::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        return back()->with('success', 'Terima kasih! Ulasan Anda berhasil dikirim.');
    }
}
