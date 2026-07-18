<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        $products = Product::with('creator')
            ->where('is_suspended', false)
            ->orderByDesc('id')
            ->get()
            ->map(function ($product) {
                if ($product->is_vpn) {
                    $product->stock_count = 999;
                } else {
                    $product->stock_count = StockUnit::where('product_id', $product->id)
                        ->where('is_sold', false)
                        ->where('stock_status', 'ready')
                        ->count();
                }
                return $product;
            });

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
