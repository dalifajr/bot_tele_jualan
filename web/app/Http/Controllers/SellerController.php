<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockUnit;
use App\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SellerController extends Controller
{
    // ==========================================
    // SELLER DASHBOARD
    // ==========================================
    public function dashboard()
    {
        $sellerId = Auth::id();
        $user = Auth::user();

        // Stock stats
        $readyStockCount = StockUnit::where('seller_id', $sellerId)->where('stock_status', 'ready')->where('is_sold', false)->count();
        $savedStockCount = StockUnit::where('seller_id', $sellerId)->where('stock_status', 'saved_for_verification')->where('is_sold', false)->count();
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

        return view('seller.dashboard', compact(
            'user',
            'readyStockCount',
            'savedStockCount',
            'soldStockCount',
            'pendingWithdrawalCount',
            'approvedWithdrawalCount',
            'monthlyEarnings'
        ));
    }

    // ==========================================
    // STOCK MANAGEMENT
    // ==========================================
    public function stock(Request $request)
    {
        $sellerId = Auth::id();
        $status = $request->get('status');

        $query = StockUnit::with(['product', 'uploader'])->where('seller_id', $sellerId);

        if ($status === 'ready') {
            $query->where('stock_status', 'ready')->where('is_sold', false);
        } elseif ($status === 'saved_for_verification') {
            $query->where('stock_status', 'saved_for_verification')->where('is_sold', false);
        } elseif ($status === 'terjual') {
            $query->where('is_sold', true);
        }

        $stocks = $query->orderBy('created_at', 'desc')->paginate(20);

        // Fetch products available for this seller to upload stock (either created by them or they are workers)
        $products = Product::where('creator_id', $sellerId)
            ->orWhereHas('workers', function($q) use ($sellerId) {
                $q->where('user_id', $sellerId);
            })->get();

        return view('seller.stock.index', compact('stocks', 'products', 'status'));
    }

    public function storeStock(Request $request)
    {
        $sellerId = Auth::id();
        $user = Auth::user();

        $request->validate([
            'product_id' => 'required|exists:products,id',
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

        // Determine quarantine and available_at based on seller's own configuration
        $saveHours = (int)($user->seller_save_hours ?? 80);
        $stockStatus = 'saved_for_verification';
        $availableAt = now()->addHours($saveHours);

        if ($saveHours <= 0) {
            $stockStatus = 'ready';
            $availableAt = null;
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

    public function destroyStock($id)
    {
        $stock = StockUnit::where('seller_id', Auth::id())->findOrFail($id);
        if ($stock->is_sold) {
            return redirect()->back()->with('error', 'Stok yang sudah terjual tidak dapat dihapus.');
        }
        $stock->delete();
        return redirect()->back()->with('success', 'Stok berhasil dihapus.');
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

        return view('seller.finance.index', compact('user', 'withdrawals'));
    }

    public function requestWithdrawal(Request $request)
    {
        $sellerId = Auth::id();
        $user = Auth::user();

        $request->validate([
            'amount' => 'required|integer|min:10000',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:100',
            'account_holder' => 'required|string|max:255',
        ]);

        if ($user->wallet_balance < $request->amount) {
            return redirect()->back()->with('error', 'Saldo wallet Anda tidak mencukupi untuk melakukan penarikan.');
        }

        WithdrawalRequest::create([
            'seller_id' => $sellerId,
            'amount' => $request->amount,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_holder' => $request->account_holder,
            'status' => 'pending',
        ]);

        return redirect()->route('seller.finance.index')->with('success', 'Pengajuan penarikan dana berhasil dikirim dan sedang menunggu verifikasi admin.');
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
}
