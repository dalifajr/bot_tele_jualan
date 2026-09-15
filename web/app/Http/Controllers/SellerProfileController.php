<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\Request;

class SellerProfileController extends Controller
{
    /**
     * Display the public seller profile and store catalog.
     */
    public function show($id)
    {
        $seller = User::findOrFail($id);

        // Verify seller or admin
        if (!in_array($seller->role, ['seller', 'admin'])) {
            $hasProducts = Product::where('creator_id', $seller->id)->exists();
            if (!$hasProducts) {
                abort(404, __('Toko atau seller tidak ditemukan.'));
            }
        }

        // Preload stock counts
        $readyStockCounts = StockUnit::selectRaw('product_id, count(*) as count')
            ->where('is_sold', false)
            ->where('stock_status', 'ready')
            ->groupBy('product_id')
            ->pluck('count', 'product_id');

        $soldStockCounts = StockUnit::selectRaw('product_id, count(*) as count')
            ->where('is_sold', true)
            ->groupBy('product_id')
            ->pluck('count', 'product_id');

        $orderItemSoldCounts = OrderItem::selectRaw('product_id, sum(quantity) as total')
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['delivered', 'completed', 'paid']);
            })
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        // Products owned by this seller
        $products = Product::with('creator')
            ->where('creator_id', $seller->id)
            ->where('is_suspended', false)
            ->get()
            ->map(function ($product) use ($readyStockCounts, $soldStockCounts, $orderItemSoldCounts) {
                if ($product->is_vpn) {
                    $product->stock_count = 999;
                } else {
                    $product->stock_count = (int) ($readyStockCounts[$product->id] ?? 0);
                }

                $stockSold = (int) ($soldStockCounts[$product->id] ?? 0);
                $orderSold = (int) ($orderItemSoldCounts[$product->id] ?? 0);
                $product->sales_count = max($stockSold, $orderSold);

                return $product;
            })
            ->sort(function ($a, $b) {
                $aHasStock = $a->stock_count > 0 ? 1 : 0;
                $bHasStock = $b->stock_count > 0 ? 1 : 0;
                if ($aHasStock !== $bHasStock) {
                    return $bHasStock <=> $aHasStock;
                }
                return $b->sales_count <=> $a->sales_count;
            })
            ->values();

        // Metrics
        $totalProducts = $products->count();

        $totalSoldUnits = StockUnit::where('seller_id', $seller->id)
            ->where('is_sold', true)
            ->count();

        $avgRating = Review::whereHas('product', function ($q) use ($seller) {
            $q->where('creator_id', $seller->id);
        })->avg('rating');

        $totalReviews = Review::whereHas('product', function ($q) use ($seller) {
            $q->where('creator_id', $seller->id);
        })->count();

        $sellerJoinDate = $seller->created_at ? $seller->created_at->translatedFormat('F Y') : 'Mei 2024';

        return view('sellers.show', compact(
            'seller',
            'products',
            'totalProducts',
            'totalSoldUnits',
            'avgRating',
            'totalReviews',
            'sellerJoinDate'
        ));
    }
}
