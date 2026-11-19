<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductWishlist;
use App\Models\StockUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $wishlists = ProductWishlist::with('product')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($w) => $w->product && !$w->product->is_suspended);

        $productIds = $wishlists->pluck('product_id')->all();
        $readyStockCounts = StockUnit::whereIn('product_id', $productIds)
            ->where('is_sold', false)
            ->whereNull('sold_order_id')
            ->where(function ($q) {
                $q->where('stock_status', 'ready')->orWhereNull('stock_status');
            })
            ->selectRaw('product_id, COUNT(*) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        return view('wishlist.index', compact('wishlists', 'readyStockCounts'));
    }

    public function toggle(Request $request, $productId)
    {
        $product = Product::where('is_suspended', false)->findOrFail($productId);
        $userId = Auth::id();

        $existing = ProductWishlist::where('user_id', $userId)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $wishlisted = false;
            $message = __('Produk dihapus dari daftar favorit.');
        } else {
            ProductWishlist::create([
                'user_id' => $userId,
                'product_id' => $product->id,
                'notify_restock' => (bool) $request->input('notify_restock', true),
            ]);
            $wishlisted = true;
            $message = __('Produk ditambahkan ke daftar favorit.');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'wishlisted' => $wishlisted,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function toggleRestockAlert(Request $request, $productId)
    {
        $product = Product::where('is_suspended', false)->findOrFail($productId);
        $userId = Auth::id();

        $wish = ProductWishlist::firstOrCreate(
            ['user_id' => $userId, 'product_id' => $product->id],
            ['notify_restock' => false]
        );

        $newState = !$wish->notify_restock;
        $wish->update(['notify_restock' => $newState]);

        $message = $newState
            ? __('Pengingat stok aktif! Kami akan mengabari Anda via notifikasi & Telegram saat stok tersedia.')
            : __('Pengingat stok untuk produk ini dinonaktifkan.');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'notify_restock' => $newState,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }
}
