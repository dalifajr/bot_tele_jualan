<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockUnit;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
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

        $products = Product::with('creator')
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
                // 1. Ready stock first (stock_count > 0 comes before stock_count == 0)
                $aHasStock = $a->stock_count > 0 ? 1 : 0;
                $bHasStock = $b->stock_count > 0 ? 1 : 0;
                if ($aHasStock !== $bHasStock) {
                    return $bHasStock <=> $aHasStock;
                }

                // 2. Penjualan terbanyak (highest sales_count first)
                if ($a->sales_count !== $b->sales_count) {
                    return $b->sales_count <=> $a->sales_count;
                }

                // 3. Newest product ID first
                return $b->id <=> $a->id;
            })
            ->values();

        return view('catalog.index', compact('products'));
    }

    public function show($id)
    {
        $product = Product::with('creator')->findOrFail($id);

        if ($product->is_suspended) {
            return redirect()->route('catalog.index')->with('error', __('Produk tidak tersedia.'));
        }

        if ($product->is_vpn) {
            $stockCount = 999;
        } else {
            $stockCount = StockUnit::where('product_id', $product->id)
                ->where('is_sold', false)
                ->where('stock_status', 'ready')
                ->count();
        }

        return view('catalog.show', compact('product', 'stockCount'));
    }
}
