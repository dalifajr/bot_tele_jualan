<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockUnit;
use App\Models\WithdrawalRequest;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    // ==========================================
    // SELLER DASHBOARD
    // ==========================================
    public function dashboard(Request $request)
    {
        $sellerId = Auth::id();
        $user = Auth::user();
        $productId = $request->query('product_id');

        // Held Balance
        $heldQuery = DB::table('held_funds')
            ->where('seller_id', $sellerId)
            ->where('status', 'held');
        if ($productId) {
            $heldQuery->where('product_id', $productId);
        }
        $heldBalance = (int) $heldQuery->sum('amount');

        // Stock stats
        $stockQuery = StockUnit::where('seller_id', $sellerId);
        if ($productId) {
            $stockQuery->where('product_id', $productId);
        }
        $readyStockCount = (clone $stockQuery)->where('stock_status', 'ready')->where('is_sold', false)->count();
        $savedStockCount = (clone $stockQuery)->whereIn('stock_status', ['saved_for_verification', 'saved_ready_notified'])->where('is_sold', false)->count();
        $soldStockCount = (clone $stockQuery)->where('is_sold', true)->count();

        // Finance stats
        $pendingWithdrawalCount = WithdrawalRequest::where('seller_id', $sellerId)->where('status', 'pending')->count();
        $approvedWithdrawalCount = WithdrawalRequest::where('seller_id', $sellerId)->where('status', 'approved')->count();

        // Filter rentang waktu untuk pendapatan kotor
        $earningsDays = $request->query('earnings_days', 'all');
        if (!in_array($earningsDays, ['7', '30', '90', '365', 'all'])) {
            $earningsDays = 'all';
        }

        $earningsQuery = DB::table('stock_units')
            ->join('products', 'stock_units.product_id', '=', 'products.id')
            ->join('orders', 'stock_units.sold_order_id', '=', 'orders.id')
            ->where('stock_units.seller_id', $sellerId)
            ->where('stock_units.is_sold', true);

        if ($productId) {
            $earningsQuery->where('stock_units.product_id', $productId);
        }

        if ($earningsDays !== 'all') {
            $dateLimit = now()->subDays((int) $earningsDays)->startOfDay();
            $earningsQuery->where('orders.created_at', '>=', $dateLimit);
        }

        $monthlyEarnings = (int) $earningsQuery->sum('products.price');

        // Log diagnostik untuk didebug di laravel.log
        \Log::info("DIAGNOSTIC - Seller Earnings Filter: days={$earningsDays}, sum={$monthlyEarnings}, sql=" . $earningsQuery->toSql() . ", bindings=" . json_encode($earningsQuery->getBindings()));

        $feePercent = $user->platform_fee_percent ?? 10;
        $monthlyCommission = (int) ($monthlyEarnings * $feePercent / 100);
        $monthlyNet = $monthlyEarnings - $monthlyCommission;

        // 1. Total Sales (earnings sum of their sold stock units where orders are delivered)
        $totalSalesQuery = DB::table('stock_units')
            ->join('products', 'stock_units.product_id', '=', 'products.id')
            ->join('orders', 'stock_units.sold_order_id', '=', 'orders.id')
            ->where('stock_units.seller_id', $sellerId)
            ->where('stock_units.is_sold', true)
            ->where('orders.status', 'delivered');
        if ($productId) {
            $totalSalesQuery->where('stock_units.product_id', $productId);
        }
        $totalSales = (int) $totalSalesQuery->sum('products.price');

        // 2. Delivered orders containing their stock units
        $deliveredOrders = Order::where('status', 'delivered')
            ->whereHas('stockUnits', function ($query) use ($sellerId, $productId) {
                $query->where('seller_id', $sellerId);
                if ($productId) {
                    $query->where('product_id', $productId);
                }
            })->count();

        // 3. Cancelled and expired orders containing their stock units
        $cancelledOrders = Order::whereIn('status', ['cancelled', 'expired'])
            ->whereHas('stockUnits', function ($query) use ($sellerId, $productId) {
                $query->where('seller_id', $sellerId);
                if ($productId) {
                    $query->where('product_id', $productId);
                }
            })->count();

        $totalOrders = $deliveredOrders + $cancelledOrders;

        // 4. Total products owned by them
        $totalProducts = Product::where('creator_id', $sellerId)->count();

        // 5. Latest 5 delivered orders containing their stock units
        $latestOrders = Order::with(['customer', 'stockUnits.product'])
            ->where('status', 'delivered')
            ->whereHas('stockUnits', function ($query) use ($sellerId, $productId) {
                $query->where('seller_id', $sellerId);
                if ($productId) {
                    $query->where('product_id', $productId);
                }
            })
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // 6. Dynamic trend logic based on filtered days
        $days = (int) $request->query('days', 7);
        if (!in_array($days, [7, 14, 30, 180, 365])) {
            $days = 7;
        }

        $chartLabels = [];
        $chartData = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dateObj = now()->subDays($i);
            $date = $dateObj->toDateString();

            if ($days > 30) {
                $chartLabels[] = $dateObj->format('d M y');
            } else {
                $chartLabels[] = $dateObj->format('d M');
            }

            $chartDataQuery = DB::table('stock_units')
                ->join('products', 'stock_units.product_id', '=', 'products.id')
                ->join('orders', 'stock_units.sold_order_id', '=', 'orders.id')
                ->where('stock_units.seller_id', $sellerId)
                ->where('stock_units.is_sold', true)
                ->where('orders.status', 'delivered')
                ->whereDate('orders.delivered_at', $date);

            if ($productId) {
                $chartDataQuery->where('stock_units.product_id', $productId);
            }

            $chartData[] = (int) $chartDataQuery->sum('products.price');
        }

        // Advanced Analytics for Seller Dashboard
        $ratingQuery = DB::table('reviews')
            ->join('products', 'reviews.product_id', '=', 'products.id')
            ->where('products.creator_id', $sellerId);
        if ($productId) {
            $ratingQuery->where('reviews.product_id', $productId);
        }
        $avgRating = $ratingQuery->avg('reviews.rating') ?? 0;
        $totalReviews = $ratingQuery->count();

        $topProductsQuery = DB::table('stock_units')
            ->join('products', 'stock_units.product_id', '=', 'products.id')
            ->select('products.name', 'products.price', DB::raw('count(stock_units.id) as units_sold'), DB::raw('sum(products.price) as total_earnings'))
            ->where('stock_units.seller_id', $sellerId)
            ->where('stock_units.is_sold', true);
        if ($productId) {
            $topProductsQuery->where('stock_units.product_id', $productId);
        }
        $topProducts = $topProductsQuery->groupBy('products.id', 'products.name', 'products.price')
            ->orderByDesc('units_sold')
            ->take(5)
            ->get();

        $productShareQuery = DB::table('stock_units')
            ->join('products', 'stock_units.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('count(stock_units.id) as units_sold'))
            ->where('stock_units.seller_id', $sellerId)
            ->where('stock_units.is_sold', true);
        if ($productId) {
            $productShareQuery->where('stock_units.product_id', $productId);
        }
        $productShare = $productShareQuery->groupBy('products.id', 'products.name')
            ->orderByDesc('units_sold')
            ->get();

        $shareLabels = $productShare->pluck('name')->toArray();
        $shareData = $productShare->pluck('units_sold')->toArray();

        // Fetch products available for this seller to filter on the dashboard
        $products = Product::where('creator_id', $sellerId)
            ->orWhereHas('workers', function($q) use ($sellerId) {
                $q->where('user_id', $sellerId);
            })->orderBy('name')->get();

        return view('seller.dashboard', compact(
            'user',
            'readyStockCount',
            'savedStockCount',
            'soldStockCount',
            'pendingWithdrawalCount',
            'approvedWithdrawalCount',
            'monthlyEarnings',
            'monthlyCommission',
            'monthlyNet',
            'earningsDays',
            'totalSales',
            'deliveredOrders',
            'cancelledOrders',
            'totalOrders',
            'totalProducts',
            'latestOrders',
            'chartLabels',
            'chartData',
            'days',
            'heldBalance',
            'avgRating',
            'totalReviews',
            'topProducts',
            'shareLabels',
            'shareData',
            'products',
            'productId'
        ));
    }

    // ==========================================
    // STOCK MANAGEMENT
    // ==========================================
    public function stock(Request $request)
    {
        $sellerId = Auth::id();
        $status = $request->get('status');

        $query = StockUnit::with(['product', 'uploader', 'order.customer'])->where('seller_id', $sellerId);

        if ($status === 'ready') {
            $query->where('stock_status', 'ready')->where('is_sold', false);
        } elseif ($status === 'saved_for_verification') {
            $query->where('is_sold', false)->whereIn('stock_status', ['saved_for_verification', 'saved_ready_notified']);
        } elseif ($status === 'terjual') {
            $query->where('is_sold', true);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('raw_text', 'like', "%{$search}%")
                  ->orWhere('stock_status', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('order.customer', function ($cq) use ($search) {
                      $cq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%")
                         ->orWhere('telegram_id', 'like', "%{$search}%");
                  });
            });
        }

        // Metrics
        $metricsQuery = StockUnit::where('seller_id', $sellerId);

        if ($request->filled('product_id')) {
            $metricsQuery->where('product_id', $request->product_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $metricsQuery->where(function ($q) use ($search) {
                $q->where('raw_text', 'like', "%{$search}%")
                  ->orWhere('stock_status', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('order.customer', function ($cq) use ($search) {
                      $cq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%")
                         ->orWhere('telegram_id', 'like', "%{$search}%");
                  });
            });
        }

        $totalStock = (clone $metricsQuery)->count();
        $readyStock = (clone $metricsQuery)->where('is_sold', false)->where('stock_status', 'ready')->count();
        $awaitingStock = (clone $metricsQuery)->where('is_sold', false)->where('stock_status', 'awaiting_benefits')->count();
        $savedStock = (clone $metricsQuery)->where('is_sold', false)->whereIn('stock_status', ['saved_for_verification', 'saved_ready_notified'])->count();
        $soldStock = (clone $metricsQuery)->where('is_sold', true)->count();

        $stocks = $query->orderBy('created_at', 'desc')->paginate(15);

        // Fetch products available for this seller to upload stock (either created by them or they are workers)
        $products = Product::where('creator_id', $sellerId)
            ->orWhereHas('workers', function($q) use ($sellerId) {
                $q->where('user_id', $sellerId);
            })->get();

        return view('seller.stock.index', compact(
            'stocks', 'products', 'status',
            'totalStock', 'readyStock', 'awaitingStock', 'savedStock', 'soldStock'
        ));
    }

    public function storeStock(Request $request)
    {
        $sellerId = Auth::id();
        $user = Auth::user();

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'stock_status' => 'required|in:ready,saved_for_verification',
            'raw_text' => 'required|string',
        ]);

        // Verify seller is allowed to upload stock for this product
        $allowed = Product::where('id', $request->product_id)
            ->where(function($q) use ($sellerId) {
                $q->where('creator_id', $sellerId)
                  ->orWhereHas('workers', function($w) use ($sellerId) {
                      $w->where('user_id', $sellerId);
                  });
            })->exists();

        if (!$allowed) {
            return redirect()->back()->with('error', 'Anda tidak memiliki hak untuk mengunggah stok pada produk ini.');
        }

        $stockStatus = $request->stock_status;
        $availableAt = null;
        if ($stockStatus === 'saved_for_verification') {
            $saveHours = (int)($user->seller_save_hours ?? 80);
            $availableAt = now()->addHours($saveHours);
        }

        // Split raw text by double-newlines
        $blocks = array_filter(array_map('trim', preg_split('/\n\s*\n/', $request->raw_text)));
        $count = 0;

        foreach ($blocks as $block) {
            if (!empty($block)) {
                StockUnit::create([
                    'product_id' => $request->product_id,
                    'raw_text' => $block,
                    'stock_status' => $stockStatus,
                    'is_sold' => false,
                    'available_at' => $availableAt,
                    'seller_id' => $sellerId,
                    'uploaded_by_id' => $sellerId,
                ]);
                $count++;
            }
        }

        return redirect()->route('seller.stock.index')->with('success', "$count stok berhasil diunggah.");
    }

    public function moveStock(Request $request, $id)
    {
        $sellerId = Auth::id();
        $user = Auth::user();

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'stock_status' => 'required|in:ready,saved_for_verification,terjual',
        ]);

        $stock = StockUnit::where('seller_id', $sellerId)->findOrFail($id);

        // Verify seller is allowed to move stock to this product
        $allowed = Product::where('id', $request->product_id)
            ->where(function($q) use ($sellerId) {
                $q->where('creator_id', $sellerId)
                  ->orWhereHas('workers', function($w) use ($sellerId) {
                      $w->where('user_id', $sellerId);
                  });
            })->exists();

        if (!$allowed) {
            return redirect()->back()->with('error', 'Anda tidak memiliki hak untuk memindahkan stok ke produk ini.');
        }

        $stock->product_id = $request->product_id;

        if ($request->stock_status === 'terjual') {
            $stock->is_sold = true;
        } else {
            $stock->is_sold = false;
            $stock->sold_order_id = null;
            $stock->stock_status = $request->stock_status;

            if ($request->stock_status === 'saved_for_verification') {
                $saveHours = (int)($user->seller_save_hours ?? 80);
                $stock->available_at = now()->addHours($saveHours);
            } else {
                $stock->available_at = null;
            }
        }

        $stock->save();

        return back()->with('success', 'Status/Produk stok berhasil dipindahkan.');
    }

    public function bulkMoveStock(Request $request)
    {
        $sellerId = Auth::id();
        $user = Auth::user();

        $request->validate([
            'ids' => 'required|string',
            'product_id' => 'nullable|exists:products,id',
            'stock_status' => 'required|in:ready,saved_for_verification',
        ]);

        $ids = json_decode($request->ids, true);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', 'Tidak ada stok terpilih.');
        }

        // Verify product if filled
        if ($request->filled('product_id')) {
            $allowed = Product::where('id', $request->product_id)
                ->where(function($q) use ($sellerId) {
                    $q->where('creator_id', $sellerId)
                      ->orWhereHas('workers', function($w) use ($sellerId) {
                          $w->where('user_id', $sellerId);
                      });
                })->exists();

            if (!$allowed) {
                return redirect()->back()->with('error', 'Anda tidak memiliki hak untuk memindahkan stok ke produk ini.');
            }
        }

        $stockUnits = StockUnit::whereIn('id', $ids)->where('seller_id', $sellerId)->where('is_sold', false)->get();
        if ($stockUnits->isEmpty()) {
            return back()->with('error', 'Stok terpilih tidak ditemukan atau sudah terjual.');
        }

        $saveHours = (int)($user->seller_save_hours ?? 80);

        $count = 0;
        foreach ($stockUnits as $stock) {
            if ($request->filled('product_id')) {
                $stock->product_id = $request->product_id;
            }
            $stock->stock_status = $stock->stock_status; // Keep or change
            $stock->stock_status = $request->stock_status;
            $stock->sold_order_id = null;

            if ($request->stock_status === 'saved_for_verification') {
                $stock->available_at = now()->addHours($saveHours);
            } else {
                $stock->available_at = null;
            }

            $stock->save();
            $count++;
        }

        return back()->with('success', "$count status/produk stok berhasil dipindahkan secara masal.");
    }

    public function destroyStock($id)
    {
        $stock = StockUnit::where('seller_id', Auth::id())->findOrFail($id);
        if ($stock->is_sold) {
            return redirect()->back()->with('error', 'Stok yang sudah terjual tidak dapat dihapus.');
        }
        $stock->delete();
        return redirect()->back()->with('success', 'Stok berhasil dihapus.');
    }

    public function bulkDestroyStock(Request $request)
    {
        $sellerId = Auth::id();

        $request->validate([
            'ids' => 'required|string',
        ]);

        $ids = json_decode($request->ids, true);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', 'Tidak ada stok terpilih.');
        }

        $count = StockUnit::whereIn('id', $ids)->where('seller_id', $sellerId)->where('is_sold', false)->delete();

        return back()->with('success', "$count stok berhasil dihapus secara masal.");
    }

    // ==========================================
    // PRODUCT MANAGEMENT (WITH WORKERS)
    // ==========================================
    public function products()
    {
        $sellerId = Auth::id();

        // Products owned by this seller
        $myProducts = Product::with(['workers', 'stockUnits'])->where('creator_id', $sellerId)->get();

        // Products this seller works on
        $workedProducts = Product::with('stockUnits')
            ->whereHas('workers', function($q) use ($sellerId) {
                $q->where('user_id', $sellerId);
            })->get();

        // All sellers available to be added as workers
        $allSellers = User::where('role', 'seller')->where('id', '!=', $sellerId)->get();

        return view('seller.products.index', compact('myProducts', 'workedProducts', 'allSellers'));
    }

    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'warranty_days' => 'required_if:enable_warranty,1|nullable|integer|min:1',
        ]);

        Product::create([
            'name' => $request->name,
            'price' => $request->price,
            'description' => $request->description ?? '',
            'creator_id' => Auth::id(),
            'is_suspended' => false,
            'warranty_days' => $request->has('enable_warranty') ? $request->warranty_days : 0,
        ]);

        return redirect()->route('seller.products.index')->with('success', 'Produk baru berhasil dibuat.');
    }

    public function addWorker(Request $request, $id)
    {
        $product = Product::where('creator_id', Auth::id())->findOrFail($id);
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        if ($user->role !== 'seller') {
            return redirect()->back()->with('error', 'Hanya pengguna dengan role seller yang dapat ditambahkan sebagai worker.');
        }

        // Attach worker
        if (!$product->workers()->where('user_id', $user->id)->exists()) {
            $product->workers()->attach($user->id);
        }

        return redirect()->back()->with('success', 'Worker berhasil ditambahkan ke produk Anda.');
    }

    public function removeWorker($id, $userId)
    {
        $product = Product::where('creator_id', Auth::id())->findOrFail($id);
        $ownerId = Auth::id();

        // Detach worker
        $product->workers()->detach($userId);

        // Transfer stock ownership to the product owner
        $stocks = StockUnit::where('product_id', $product->id)
            ->where('seller_id', $userId)
            ->get();

        foreach ($stocks as $stock) {
            $stock->uploaded_by_id = $stock->uploaded_by_id ?? $userId;
            $stock->seller_id = $ownerId;
            $stock->save();
        }

        return redirect()->back()->with('success', 'Worker berhasil dihapus dari produk Anda, dan kepemilikan stok miliknya telah dialihkan kepada Anda.');
    }

    public function updateProduct(Request $request, $id)
    {
        $product = Product::where('creator_id', Auth::id())->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        $product->update([
            'name' => $request->name,
            'price' => $request->price,
            'description' => $request->description ?? '',
        ]);

        return redirect()->route('seller.products.index')->with('success', 'Informasi produk berhasil diperbarui.');
    }

    public function destroyProduct($id)
    {
        $product = Product::where('creator_id', Auth::id())->findOrFail($id);

        try {
            $productId = $product->id;
            
            // Delete all unsold stock units of this product
            \App\Models\StockUnit::where('product_id', $productId)->where('is_sold', false)->delete();
            // Delete from all user carts
            \Illuminate\Support\Facades\DB::table('cart_items')->where('product_id', $productId)->delete();

            $product->delete();
            return redirect()->route('seller.products.index')->with('success', 'Produk berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('seller.products.index')->with('swal_error', 'Gagal menghapus produk: ' . $e->getMessage());
        }
    }

    public function exportUnsoldStock($id)
    {
        $product = \App\Models\Product::where('creator_id', Auth::id())->findOrFail($id);

        $stockUnits = \App\Models\StockUnit::with(['uploader', 'seller'])
            ->where('product_id', $product->id)
            ->where('is_sold', false)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($stockUnits->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada stok belum terjual yang tersedia untuk diekspor.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sisa Stok');
        $sheet->setShowGridlines(true);

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '198754'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'D3D3D3'],
                ],
            ],
        ];

        $dataBorderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'E0E0E0'],
                ],
            ],
        ];

        $headers = ['No', 'Produk', 'Detail Akun (Raw Text)', 'Status', 'Uploader', 'Tanggal Input'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $row = 2;
        $no = 1;
        foreach ($stockUnits as $unit) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $product->name);
            $sheet->setCellValue('C' . $row, $unit->raw_text);
            $sheet->setCellValue('D' . $row, strtoupper($unit->stock_status));
            
            $uploaderName = $unit->uploader->full_name ?? $unit->uploader->username ?? ($unit->seller->full_name ?? $unit->seller->username ?? 'Seller');
            $sheet->setCellValue('E' . $row, $uploaderName);
            $sheet->setCellValue('F' . $row, $unit->created_at ? $unit->created_at->format('Y-m-d H:i:s') : '-');

            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($dataBorderStyle);
            
            $statusColor = '000000';
            $statusBg = 'FFFFFF';
            if ($unit->stock_status === 'ready') {
                $statusColor = '198754';
                $statusBg = 'D1E7DD';
            } elseif ($unit->stock_status === 'awaiting_benefits') {
                $statusColor = 'A18000';
                $statusBg = 'FFF3CD';
            } elseif ($unit->stock_status === 'saved_for_verification') {
                $statusColor = '0D6EFD';
                $statusBg = 'CFF4FC';
            }

            if ($statusBg !== 'FFFFFF') {
                $sheet->getStyle('D' . $row)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => $statusColor],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $statusBg],
                    ],
                ]);
            }

            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        foreach (range('A', 'F') as $colChar) {
            $sheet->getColumnDimension($colChar)->setAutoSize(true);
        }

        $filename = 'sisa_stok_' . str_replace(' ', '_', $product->name) . '_' . now()->format('Y-m-d_His') . '.xlsx';

        $responseHeaders = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ];

        return response()->stream(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, $responseHeaders);
    }

    // ==========================================
    // FINANCE & WITHDRAWALS
    // ==========================================
    public function finance()
    {
        $sellerId = Auth::id();
        $user = Auth::user();

        $withdrawals = WithdrawalRequest::where('seller_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->paginate(15, ['*'], 'page');

        // Fetch saved bank accounts
        $bankAccounts = \App\Models\SellerBankAccount::where('user_id', $sellerId)->get();

        // Held funds
        $heldBalance = (int) DB::table('held_funds')
            ->where('seller_id', $sellerId)
            ->where('status', 'held')
            ->sum('amount');

        $heldFunds = \App\Models\HeldFund::with(['product', 'order'])
            ->where('seller_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'held_page');

        return view('seller.finance.index', compact('user', 'withdrawals', 'bankAccounts', 'heldBalance', 'heldFunds'));
    }

    public function requestWithdrawal(Request $request)
    {
        $sellerId = Auth::id();
        $user = Auth::user();

        $request->validate([
            'amount' => 'required|integer|min:10000',
            'bank_account_id' => 'required|exists:seller_bank_accounts,id',
        ]);

        if ($user->wallet_balance < $request->amount) {
            return redirect()->back()->with('error', 'Saldo wallet Anda tidak mencukupi untuk melakukan penarikan.');
        }

        // Get bank account and verify it belongs to this seller
        $bankAccount = \App\Models\SellerBankAccount::where('user_id', $sellerId)->findOrFail($request->bank_account_id);

        WithdrawalRequest::create([
            'seller_id' => $sellerId,
            'amount' => $request->amount,
            'bank_name' => $bankAccount->bank_name,
            'account_number' => $bankAccount->account_number,
            'account_holder' => $bankAccount->account_holder,
            'status' => 'pending',
        ]);

        return redirect()->route('seller.finance.index')->with('success', 'Pengajuan penarikan dana berhasil dikirim dan sedang menunggu verifikasi admin.');
    }

    // ==========================================
    // SELLER SAVED BANK ACCOUNTS
    // ==========================================
    public function bankAccounts()
    {
        $sellerId = Auth::id();
        $user = Auth::user();

        $bankAccounts = \App\Models\SellerBankAccount::where('user_id', $sellerId)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('seller.finance.bank_accounts', compact('user', 'bankAccounts'));
    }

    public function storeBankAccount(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:100',
            'account_holder' => 'required|string|max:255',
        ]);

        \App\Models\SellerBankAccount::create([
            'user_id' => Auth::id(),
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_holder' => $request->account_holder,
        ]);

        return redirect()->route('seller.bank-accounts.index')->with('success', 'Rekening bank berhasil disimpan.');
    }

    public function destroyBankAccount($id)
    {
        $bankAccount = \App\Models\SellerBankAccount::where('user_id', Auth::id())->findOrFail($id);
        $bankAccount->delete();

        return redirect()->route('seller.bank-accounts.index')->with('success', 'Rekening bank berhasil dihapus.');
    }

    // ==========================================
    // ORDER MANAGEMENT
    // ==========================================
    public function orders(Request $request)
    {
        $sellerId = Auth::id();
        $query = Order::with(['customer', 'items.product', 'stockUnits'])
            ->whereHas('items.product', function($q) use ($sellerId) {
                $q->where('creator_id', $sellerId);
            })
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(10);
        $status = $request->status;

        return view('seller.orders.index', compact('orders', 'status'));
    }

    public function cancelOrder($id, \App\Services\OrderService $orderService)
    {
        $order = Order::whereHas('items.product', function($q) {
            $q->where('creator_id', Auth::id());
        })->findOrFail($id);

        try {
            $orderService->cancelOrder($order, 'cancelled_by_seller', Auth::id());
            return redirect()->back()->with('success', 'Pesanan berhasil dibatalkan.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membatalkan pesanan: ' . $e->getMessage());
        }
    }

    // ==========================================
    // SETTINGS
    // ==========================================
    public function settings()
    {
        $user = Auth::user();
        return view('seller.settings.index', compact('user'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'seller_save_hours' => 'required|integer|min:0|max:1000',
        ]);

        $user = Auth::user();
        $user->seller_save_hours = $request->seller_save_hours;
        $user->save();

        return redirect()->route('seller.settings.index')->with('success', 'Pengaturan jam karantina berhasil disimpan.');
    }

    // ==========================================
    // COMPLAINTS MANAGEMENT
    // ==========================================
    public function complaints()
    {
        $sellerId = Auth::id();

        $complaints = \App\Models\ComplaintCase::whereHas('order.stockUnits', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })
        ->with(['customer', 'order'])
        ->orderBy('created_at', 'desc')
        ->paginate(15);

        return view('seller.complaints.index', compact('complaints'));
    }

    public function showComplaint($id)
    {
        $sellerId = Auth::id();

        $complaint = \App\Models\ComplaintCase::whereHas('order.stockUnits', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })
        ->with(['customer', 'order.items.product', 'order.stockUnits' => function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        }])
        ->findOrFail($id);

        $productId = $complaint->order->items->first()->product_id ?? 0;
        
        $availableStocks = \App\Models\StockUnit::where('seller_id', $sellerId)
            ->where('product_id', $productId)
            ->where('is_sold', false)
            ->whereNull('sold_order_id')
            ->where(function($q) {
                $q->where('stock_status', 'ready')->orWhereNull('stock_status');
            })
            ->get();
            
        $availableStockCount = $availableStocks->count();
        // Ambil stok random jika ada
        $randomStock = $availableStockCount > 0 ? $availableStocks->random() : null;

        return view('seller.complaints.show', compact('complaint', 'availableStockCount', 'randomStock'));
    }

    public function updateComplaintStatus(Request $request, $id)
    {
        $sellerId = Auth::id();

        $complaint = \App\Models\ComplaintCase::whereHas('order.stockUnits', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })
        ->findOrFail($id);

        $request->validate([
            'status' => 'required|string|in:review,done,rejected,refund,replacement',
            'rejected_reason' => 'required_if:status,rejected|nullable|string|max:500',
            'refund_note' => 'required_if:status,done,refund|nullable|string|max:500',
            'replacement_data' => 'required_without:replacement_stock_id|nullable|string',
        ], [
            'rejected_reason.required_if' => 'Alasan penolakan wajib diisi jika status ditolak.',
            'refund_note.required_if' => 'Catatan penyelesaian/refund wajib diisi.',
            'replacement_data.required_without' => 'Data akun pengganti wajib diisi jika tidak ada stok otomatis.',
        ]);

        $updateData = [
            'status' => $request->status,
            'updated_at' => now(),
        ];

        if ($request->status === 'rejected') {
            $updateData['rejected_reason'] = $request->rejected_reason;
            $updateData['closed_at'] = now();
        } elseif ($request->status === 'done') {
            $updateData['refund_note'] = $request->refund_note;
            $updateData['closed_at'] = now();
        } elseif ($request->status === 'refund') {
            $updateData['status'] = 'refund_requested';
            $updateData['refund_note'] = "Permintaan refund ke admin: " . $request->refund_note;
            // Di sini kita bisa menambahkan notifikasi khusus ke admin untuk melakukan refund.
        } elseif ($request->status === 'replacement') {
            $updateData['status'] = 'done';
            $updateData['refund_note'] = "Mengirimkan akun pengganti.";
            $updateData['closed_at'] = now();
            
            // Proses penggantian akun
            $newStockId = $request->input('replacement_stock_id');
            $replacementData = $request->input('replacement_data');
            
            if ($newStockId) {
                $stock = \App\Models\StockUnit::where('id', $newStockId)->where('is_sold', false)->first();
                if ($stock) {
                    $stock->update([
                        'is_sold' => true,
                        'sold_order_id' => $complaint->order_id,
                        'sold_at' => now(),
                        'stock_status' => 'sold'
                    ]);
                    $updateData['refund_note'] .= "\nAkun Pengganti: \n" . $stock->raw_text;
                }
            } else {
                $updateData['refund_note'] .= "\nAkun Pengganti (Manual): \n" . $replacementData;
            }
        }

        $complaint->update($updateData);
        
        \App\Services\TelegramService::notifyComplaintStatusUpdate($complaint);

        // Put an audit log entry
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'seller_complaint_update_status',
            'actor_id' => $sellerId,
            'entity_type' => 'complaint_case',
            'entity_id' => $complaint->id,
            'detail' => "status={$request->status}; ref={$complaint->complaint_ref}",
            'created_at' => now(),
        ]);

        return redirect()->route('seller.complaints.index')->with('success', 'Status komplain berhasil diperbarui.');
    }
}
