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

        // Stock stats
        $readyStockCount = StockUnit::where('seller_id', $sellerId)->where('stock_status', 'ready')->where('is_sold', false)->count();
        $savedStockCount = StockUnit::where('seller_id', $sellerId)->whereIn('stock_status', ['saved_for_verification', 'saved_ready_notified'])->where('is_sold', false)->count();
        $soldStockCount = StockUnit::where('seller_id', $sellerId)->where('is_sold', true)->count();

        // Finance stats
        $pendingWithdrawalCount = WithdrawalRequest::where('seller_id', $sellerId)->where('status', 'pending')->count();
        $approvedWithdrawalCount = WithdrawalRequest::where('seller_id', $sellerId)->where('status', 'approved')->count();

        // Calculate sales statistics for this month (Rupiah)
        // We select the sum of prices of stock units sold that belong to this seller
        $monthlyEarnings = DB::table('stock_units')
            ->join('products', 'stock_units.product_id', '=', 'products.id')
            ->where('stock_units.seller_id', $sellerId)
            ->where('stock_units.is_sold', true)
            ->sum('products.price');

        // 1. Total Sales (earnings sum of their sold stock units where orders are delivered)
        $totalSales = (int) DB::table('stock_units')
            ->join('products', 'stock_units.product_id', '=', 'products.id')
            ->join('orders', 'stock_units.sold_order_id', '=', 'orders.id')
            ->where('stock_units.seller_id', $sellerId)
            ->where('stock_units.is_sold', true)
            ->where('orders.status', 'delivered')
            ->sum('products.price');

        // 2. Delivered orders containing their stock units
        $deliveredOrders = Order::where('status', 'delivered')
            ->whereHas('stockUnits', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
            })->count();

        // 3. Cancelled and expired orders containing their stock units
        $cancelledOrders = Order::whereIn('status', ['cancelled', 'expired'])
            ->whereHas('stockUnits', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
            })->count();

        $totalOrders = $deliveredOrders + $cancelledOrders;

        // 4. Total products owned by them
        $totalProducts = Product::where('creator_id', $sellerId)->count();

        // 5. Latest 5 delivered orders containing their stock units
        $latestOrders = Order::with(['customer', 'stockUnits.product'])
            ->where('status', 'delivered')
            ->whereHas('stockUnits', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
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

            $chartData[] = (int) DB::table('stock_units')
                ->join('products', 'stock_units.product_id', '=', 'products.id')
                ->join('orders', 'stock_units.sold_order_id', '=', 'orders.id')
                ->where('stock_units.seller_id', $sellerId)
                ->where('stock_units.is_sold', true)
                ->where('orders.status', 'delivered')
                ->whereDate('orders.delivered_at', $date)
                ->sum('products.price');
        }

        return view('seller.dashboard', compact(
            'user',
            'readyStockCount',
            'savedStockCount',
            'soldStockCount',
            'pendingWithdrawalCount',
            'approvedWithdrawalCount',
            'monthlyEarnings',
            'totalSales',
            'deliveredOrders',
            'cancelledOrders',
            'totalOrders',
            'totalProducts',
            'latestOrders',
            'chartLabels',
            'chartData',
            'days'
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
        ]);

        Product::create([
            'name' => $request->name,
            'price' => $request->price,
            'description' => $request->description ?? '',
            'creator_id' => Auth::id(),
            'is_suspended' => false,
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

        // Check if there is still remaining stock (unsold stock units)
        $hasRemainingStock = \App\Models\StockUnit::where('product_id', $product->id)
            ->where('is_sold', false)
            ->exists();

        if ($hasRemainingStock) {
            return redirect()->route('seller.products.index')->with('swal_error', 'Gagal menghapus produk! Masih terdapat sisa stok aktif di dalamnya. Harap kosongkan atau pindahkan stok terlebih dahulu sebelum menghapus produk.');
        }

        try {
            $product->delete(); // Cascades to stock units automatically
            return redirect()->route('seller.products.index')->with('success', 'Produk berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('seller.products.index')->with('swal_error', 'Produk tidak dapat dihapus karena sudah memiliki riwayat transaksi/penjualan. Silakan hubungi admin jika ingin menonaktifkan produk ini.');
        }
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
            ->paginate(15);

        // Fetch saved bank accounts
        $bankAccounts = \App\Models\SellerBankAccount::where('user_id', $sellerId)->get();

        return view('seller.finance.index', compact('user', 'withdrawals', 'bankAccounts'));
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

        return view('seller.complaints.show', compact('complaint'));
    }

    public function updateComplaintStatus(Request $request, $id)
    {
        $sellerId = Auth::id();

        $complaint = \App\Models\ComplaintCase::whereHas('order.stockUnits', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })
        ->findOrFail($id);

        $request->validate([
            'status' => 'required|string|in:review,done,rejected',
            'rejected_reason' => 'required_if:status,rejected|nullable|string|max:500',
            'refund_note' => 'required_if:status,done|nullable|string|max:500',
        ], [
            'rejected_reason.required_if' => 'Alasan penolakan wajib diisi jika status ditolak.',
            'refund_note.required_if' => 'Catatan penyelesaian wajib diisi jika status selesai.',
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
        }

        $complaint->update($updateData);

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
