<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockUnit;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->query('sort', 'bestselling');
        if (!in_array($sort, ['bestselling', 'price_asc', 'price_desc', 'latest'], true)) {
            $sort = 'bestselling';
        }

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

        $defaultOfficialSellerId = \App\Models\User::where('role', 'admin')->value('id') ?: 1;

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
            ->sort(function ($a, $b) use ($sort) {
                // 1. Ready stock first (stock_count > 0 comes before stock_count == 0)
                $aHasStock = $a->stock_count > 0 ? 1 : 0;
                $bHasStock = $b->stock_count > 0 ? 1 : 0;
                if ($aHasStock !== $bHasStock) {
                    return $bHasStock <=> $aHasStock;
                }

                if ($sort === 'price_asc') {
                    return ($a->price <=> $b->price) ?: ($b->id <=> $a->id);
                }
                if ($sort === 'price_desc') {
                    return ($b->price <=> $a->price) ?: ($b->id <=> $a->id);
                }
                if ($sort === 'latest') {
                    return $b->id <=> $a->id;
                }

                // Default (bestselling): Penjualan terbanyak (highest sales_count first)
                if ($a->sales_count !== $b->sales_count) {
                    return $b->sales_count <=> $a->sales_count;
                }

                // Tie-breaker: Newest product ID first
                return $b->id <=> $a->id;
            })
            ->values();

        return view('catalog.index', compact('products', 'sort', 'defaultOfficialSellerId'));
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

        $stockSold = StockUnit::where('product_id', $product->id)->where('is_sold', true)->count();
        $orderSold = OrderItem::where('product_id', $product->id)->whereHas('order', function ($q) {
            $q->whereIn('status', ['delivered', 'completed', 'paid']);
        })->sum('quantity');
        
        $product->sales_count = max($stockSold, $orderSold);

        return view('catalog.show', compact('product', 'stockCount'));
    }

    /**
     * Get VCC services and live stock availability for customer modal/drawer.
     */
    public function vccInfo($id)
    {
        $product = Product::findOrFail($id);
        if (!$product->is_vcc) {
            return response()->json([
                'success' => false,
                'message' => __('Produk ini bukan produk VCC.'),
            ], 400);
        }

        $services = $product->getVccServicesWithStock();

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'formatted_price' => $product->formatted_price,
                'image_url' => $product->image_url,
                'services' => $services,
            ]
        ]);
    }

    /**
     * Real-time AJAX availability check for a requested VPN username on the product's target VPN server.
     */
    public function checkVpnUsername(Request $request, $id)
    {
        $product = Product::with('vpnServer')->findOrFail($id);
        if (!$product->is_vpn) {
            return response()->json(['success' => false, 'message' => __('Produk ini bukan layanan VPN.')], 400);
        }

        $username = trim((string) $request->query('username', ''));
        if (!preg_match('/^[a-zA-Z0-9_-]{3,24}$/', $username)) {
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => __('Username harus 3–24 karakter (huruf, angka, _ atau -).'),
            ], 422);
        }

        $res = app(\App\Services\VpnService::class)->checkUsername($username, $product->vpn_protocol, null, $product);

        return response()->json([
            'success' => !empty($res['success']),
            'available' => (bool) ($res['available'] ?? false),
            'exists' => (bool) ($res['exists'] ?? false),
            'message' => !empty($res['success'])
                ? (!empty($res['available']) ? __('Username tersedia.') : __('Username sudah terpakai di server VPN.'))
                : ($res['message'] ?? __('Gagal memeriksa ketersediaan username.')),
        ]);
    }
}
