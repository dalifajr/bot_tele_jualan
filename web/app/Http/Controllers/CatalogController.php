<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index()
    {
        $products = Product::where('is_suspended', false)
            ->orderByDesc('id')
            ->get()
            ->map(function ($product) {
                $product->stock_count = StockUnit::where('product_id', $product->id)
                    ->where('is_sold', false)
                    ->where('stock_status', 'ready')
                    ->count();
                return $product;
            });

        return view('catalog.index', compact('products'));
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);

        if ($product->is_suspended) {
            return redirect()->route('catalog.index')->with('error', 'Produk tidak tersedia.');
        }

        $stockCount = StockUnit::where('product_id', $product->id)
            ->where('is_sold', false)
            ->where('stock_status', 'ready')
            ->count();

        return view('catalog.show', compact('product', 'stockCount'));
    }
}
