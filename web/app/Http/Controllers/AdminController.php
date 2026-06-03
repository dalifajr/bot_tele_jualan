<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalRevenue = Order::where('status', 'delivered')->sum('total_amount');
        $totalOrders = Order::count();
        $totalProducts = Product::count();
        $totalUsers = User::count();

        $recentOrders = Order::with(['customer', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalRevenue', 'totalOrders', 'totalProducts', 'totalUsers', 'recentOrders'
        ));
    }

    public function products()
    {
        $products = Product::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.products.index', compact('products'));
    }

    public function stock(Request $request)
    {
        $query = StockUnit::with(['product', 'order.customer', 'uploader'])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            if ($request->status === 'terjual') {
                $query->where('is_sold', true);
            } elseif ($request->status === 'saved_for_verification') {
                $query->where('is_sold', false)->whereIn('stock_status', ['saved_for_verification', 'saved_ready_notified']);
            } else {
                $query->where('is_sold', false)->where('stock_status', $request->status);
            }
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

        $stockUnits = $query->paginate(15);
        $status = $request->status;
        return view('admin.stock.index', compact('stockUnits', 'status'));
    }

    public function orders(Request $request)
    {
        $query = Order::with(['customer', 'items.product', 'stockUnits'])->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(10);
        $status = $request->status;

        return view('admin.orders.index', compact('orders', 'status'));
    }

    public function users(Request $request)
    {
        $query = User::orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telegram_id', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(10);
        return view('admin.users.index', compact('users'));
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting users with existing orders
        if (\App\Models\Order::where('customer_id', $user->id)->exists()) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus pengguna karena memiliki riwayat pesanan. Silakan gunakan fitur Suspend sebagai gantinya.');
        }

        $user->delete();
        return redirect()->back()->with('success', 'Pengguna berhasil dihapus.');
    }

    public function suspendUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_suspended = true;
        $user->save();
        return redirect()->back()->with('success', 'Akun pengguna berhasil ditangguhkan (suspended). Akses bot telah dicabut.');
    }

    public function unsuspendUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_suspended = false;
        $user->save();
        return redirect()->back()->with('success', 'Penangguhan akun telah dicabut. Pengguna dapat menggunakan bot kembali.');
    }

    // --- CRUD Products ---
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);
        $data = $request->only(['name', 'description', 'price']);
        $data['description'] = $data['description'] ?? '';
        $data['is_suspended'] = false;
        Product::create($data);
        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function updateProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_suspended' => 'boolean'
        ]);
        $data = $request->only(['name', 'description', 'price']);
        $data['is_suspended'] = $request->has('is_suspended');
        $product->update($data);
        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroyProduct($id)
    {
        $product = \App\Models\Product::findOrFail($id);
        
        // Jika produk adalah milik seller (creator_id tidak null)
        if ($product->creator_id !== null) {
            try {
                \Illuminate\Support\Facades\DB::beginTransaction();
                
                $seller = \App\Models\User::find($product->creator_id);
                $sellerName = $seller ? ($seller->full_name ?? $seller->username) : 'Seller (ID: ' . $product->creator_id . ')';
                
                // Ambil alih semua stok unit:
                // upload_by_id dipertahankan sebagai uploader asli
                // seller_id dipindahkan ke null (Admin Utama)
                $stockUnits = \App\Models\StockUnit::where('product_id', $product->id)->get();
                foreach ($stockUnits as $unit) {
                    $unit->uploaded_by_id = $unit->uploaded_by_id ?? $unit->seller_id;
                    $unit->seller_id = null;
                    $unit->save();
                }
                
                // Tambahkan catatan di deskripsi produk jika belum ada catatan sebelumnya
                $note = "\n\n[Catatan: Produk ini sebelumnya dikelola oleh " . $sellerName . "]";
                if (strpos($product->description ?? '', '[Catatan:') === false) {
                    $product->description = ($product->description ?? '') . $note;
                }
                
                // Pindahkan kepemilikan produk ke Admin Utama (creator_id = null)
                $product->creator_id = null;
                $product->save();
                
                \Illuminate\Support\Facades\DB::commit();
                
                return redirect()->back()->with('success', 'Produk buatan seller dan sisa stoknya berhasil diambil alih oleh Admin Utama.');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                return redirect()->back()->with('error', 'Gagal mengambil alih produk seller: ' . $e->getMessage());
            }
        }
        
        // Jika produk milik admin utama, coba hapus secara fisik
        try {
            $product->delete(); // Cascades to stock units automatically
            return redirect()->back()->with('success', 'Produk berhasil dihapus secara permanen.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Produk tidak dapat dihapus permanen karena sudah memiliki riwayat transaksi/pesanan. Silakan gunakan fitur Suspend sebagai gantinya.');
        }
    }

    public function manageProduct($id)
    {
        $product = \App\Models\Product::with('stockUnits')->findOrFail($id);
        
        // Count stock statistics
        $readyStockCount = $product->stockUnits()->where('stock_status', 'ready')->count();
        $soldStockCount = $product->stockUnits()->where('is_sold', true)->count();

        return view('admin.products.manage', compact('product', 'readyStockCount', 'soldStockCount'));
    }

    // --- CRUD Stock ---
    public function storeStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'stock_status' => 'required|in:ready,awaiting_benefits,saved_for_verification',
            'raw_text' => 'required|string',
        ]);

        // Compute available_at based on status
        $availableAt = null;
        if ($request->stock_status === 'awaiting_benefits') {
            $hours = (int)(\App\Models\BotSetting::where('key', 'github_pack.awaiting_hours')->value('value') ?? 78);
            $availableAt = now()->addHours($hours);
        } elseif ($request->stock_status === 'saved_for_verification') {
            $hours = (int)(\App\Models\BotSetting::where('key', 'github_pack.save_hours')->value('value') ?? 80);
            $availableAt = now()->addHours($hours);
        }

        // Split by double-newlines (blank lines) to separate different accounts,
        // instead of splitting every single line, preserving complex account formats.
        $blocks = array_filter(array_map('trim', preg_split('/\n\s*\n/', $request->raw_text)));
        $count = 0;
        foreach ($blocks as $block) {
            if (!empty($block)) {
                \App\Models\StockUnit::create([
                    'product_id' => $request->product_id,
                    'raw_text' => $block,
                    'stock_status' => $request->stock_status,
                    'is_sold' => false,
                    'available_at' => $availableAt,
                ]);
                $count++;
            }
        }
        return redirect()->route('admin.stock.index')->with('success', "$count stok berhasil ditambahkan.");
    }

    public function moveStock(Request $request, $id)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'stock_status' => 'required|in:ready,awaiting_benefits,saved_for_verification,terjual',
        ]);

        $stock = StockUnit::findOrFail($id);
        $stock->product_id = $request->product_id;
        
        if ($request->stock_status === 'terjual') {
            $stock->is_sold = true;
        } else {
            $stock->is_sold = false;
            $stock->sold_order_id = null; // Tambahkan ini agar stok dapat dicheckout kembali
            $stock->stock_status = $request->stock_status;
            
            // Recalculate available_at
            if ($request->stock_status === 'awaiting_benefits') {
                $hours = (int)(\App\Models\BotSetting::where('key', 'github_pack.awaiting_hours')->value('value') ?? 78);
                $stock->available_at = now()->addHours($hours);
            } elseif ($request->stock_status === 'saved_for_verification') {
                $hours = (int)(\App\Models\BotSetting::where('key', 'github_pack.save_hours')->value('value') ?? 80);
                $stock->available_at = now()->addHours($hours);
            } else {
                $stock->available_at = null;
            }
        }
        
        $stock->save();

        return back()->with('success', 'Status/Produk stok berhasil dipindahkan.');
    }

    public function destroyStock($id)
    {
        $stock = StockUnit::findOrFail($id);
        $stock->delete();
        return redirect()->route('admin.stock.index')->with('success', 'Stok berhasil dihapus.');
    }

    public function bulkMoveStock(Request $request)
    {
        $request->validate([
            'ids' => 'required|string',
            'product_id' => 'nullable|exists:products,id',
            'stock_status' => 'required|in:ready,awaiting_benefits,saved_for_verification',
        ]);

        $ids = json_decode($request->ids, true);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', 'Tidak ada stok terpilih.');
        }

        $stockUnits = StockUnit::whereIn('id', $ids)->where('is_sold', false)->get();
        if ($stockUnits->isEmpty()) {
            return back()->with('error', 'Stok terpilih tidak ditemukan atau sudah terjual.');
        }

        $awaitingHours = (int)(\App\Models\BotSetting::where('key', 'github_pack.awaiting_hours')->value('value') ?? 78);
        $saveHours = (int)(\App\Models\BotSetting::where('key', 'github_pack.save_hours')->value('value') ?? 80);

        $count = 0;
        foreach ($stockUnits as $stock) {
            if ($request->filled('product_id')) {
                $stock->product_id = $request->product_id;
            }
            $stock->stock_status = $request->stock_status;
            $stock->sold_order_id = null;

            if ($request->stock_status === 'awaiting_benefits') {
                $stock->available_at = now()->addHours($awaitingHours);
            } elseif ($request->stock_status === 'saved_for_verification') {
                $stock->available_at = now()->addHours($saveHours);
            } else {
                $stock->available_at = null;
            }

            $stock->save();
            $count++;
        }

        return back()->with('success', "$count status/produk stok berhasil dipindahkan secara masal.");
    }

    public function bulkDestroyStock(Request $request)
    {
        $request->validate([
            'ids' => 'required|string',
        ]);

        $ids = json_decode($request->ids, true);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', 'Tidak ada stok terpilih.');
        }

        $count = StockUnit::whereIn('id', $ids)->where('is_sold', false)->delete();

        return back()->with('success', "$count stok berhasil dihapus secara masal.");
    }

    // --- CRUD Orders ---
    public function updateOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $request->validate([
            'status' => 'required|in:pending_payment,paid,delivered,cancelled,expired'
        ]);
        $order->status = $request->status;
        $order->save();
        return redirect()->back()->with('success', 'Status pesanan berhasil diperbarui.');
    }

    // --- CRUD Users ---
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'role' => 'required|in:admin,customer,seller',
            'wallet_balance' => 'required|integer|min:0',
            'platform_fee_percent' => 'required|integer|between:0,100',
            'seller_save_hours' => 'required|integer|min:0',
        ]);
        $user->role = $request->role;
        $user->wallet_balance = $request->wallet_balance;
        $user->platform_fee_percent = $request->platform_fee_percent;
        $user->seller_save_hours = $request->seller_save_hours;
        $user->save();
        return redirect()->route('admin.users.index')
            ->with('success', 'Informasi pengguna berhasil diperbarui.');
    }

    // ==========================================
    // NOTIFICATION: LOGINS
    // ==========================================
    public function logins()
    {
        $loginTokens = \App\Models\TelegramLoginToken::orderBy('created_at', 'desc')->paginate(15);
        return view('admin.logins.index', compact('loginTokens'));
    }

    public function notifications()
    {
        $pendingOrders = \App\Models\Order::whereIn('status', ['pending_payment', 'paid'])->orderBy('created_at', 'desc')->get();
        $pendingLogins = \App\Models\TelegramLoginToken::where('status', 'pending')->orderBy('created_at', 'desc')->get();
        
        $saveHours = \App\Models\BotSetting::where('key', 'github_pack.save_hours')->value('value') ?? 80;
        $readyToVerify = \App\Models\StockUnit::where('stock_status', 'saved_for_verification')
            ->where('is_sold', false)
            ->where(function($query) use ($saveHours) {
                $query->whereNotNull('available_at')
                      ->where('available_at', '<=', now())
                      ->orWhere(function($q) use ($saveHours) {
                          $q->whereNull('available_at')
                            ->where('created_at', '<=', now()->subHours((int)$saveHours));
                      });
            })->get();
            
        return view('admin.notifications.index', compact('pendingOrders', 'pendingLogins', 'readyToVerify'));
    }

    public function markNotificationsRead()
    {
        session(['notifications_read_at' => now()->toDateTimeString()]);
        return response()->json(['success' => true]);
    }

    // ==========================================
    // COMPLAINTS MANAGEMENT
    // ==========================================
    public function complaints()
    {
        // For simplicity, we just fetch all complaints ordered by newest.
        $complaints = \App\Models\ComplaintCase::with(['customer', 'order'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        return view('admin.complaints.index', compact('complaints'));
    }

    // ==========================================
    // BROADCAST
    // ==========================================
    public function broadcast()
    {
        return view('admin.broadcast.index');
    }

    public function prepareBroadcast(Request $request)
    {
        $request->validate(['message' => 'required|string']);
        
        // Dapatkan semua user dengan role customer dan punya telegram_id valid
        $customers = \App\Models\User::whereNotNull('telegram_id')
            ->where('role', 'customer')
            ->pluck('telegram_id');
            
        return response()->json([
            'status' => 'success',
            'total' => $customers->count(),
            'targets' => $customers
        ]);
    }

    public function sendBroadcast(Request $request)
    {
        $request->validate([
            'telegram_id' => 'required',
            'message' => 'required|string'
        ]);

        $token = config('telegram.bot_token');
        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Bot token tidak dikonfigurasi.']);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $request->telegram_id,
                'text' => $request->message,
                'parse_mode' => 'HTML'
            ]);

            if ($response->successful()) {
                return response()->json(['status' => 'success']);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Telegram API Error']);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ==========================================
    // SETTINGS (Payment)
    // ==========================================
    public function settings()
    {
        $settings = \App\Models\BotSetting::all()->pluck('value', 'key')->toArray();
        return view('admin.settings.index', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $settings = $request->input('settings', []);
        
        foreach ($settings as $key => $value) {
            \App\Models\BotSetting::updateOrCreate(
                ['key' => $key],
                ['value' => (string)$value, 'updated_at' => now()]
            );
        }

        // Recalculate available_at for existing stock
        if (isset($settings['github_pack.awaiting_hours'])) {
            $awaitingHours = (int)$settings['github_pack.awaiting_hours'];
            $awaitingStocks = \App\Models\StockUnit::where('is_sold', false)
                ->where('stock_status', 'awaiting_benefits')
                ->get();
                
            foreach ($awaitingStocks as $stock) {
                $stock->available_at = $stock->created_at->copy()->addHours($awaitingHours);
                $stock->save();
            }
        }

        if (isset($settings['github_pack.save_hours'])) {
            $saveHours = (int)$settings['github_pack.save_hours'];
            $savedStocks = \App\Models\StockUnit::where('is_sold', false)
                ->where('stock_status', 'saved_for_verification')
                ->get();
                
            foreach ($savedStocks as $stock) {
                $stock->available_at = $stock->created_at->copy()->addHours($saveHours);
                $stock->save();
            }
        }

        return back()->with('success', 'Konfigurasi berhasil disimpan dan jadwal stok telah diperbarui!');
    }

    public function uploadQris(Request $request)
    {
        $request->validate([
            'qris_image' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $file = $request->file('qris_image');
        $imagePath = $file->path();

        // Cross-platform: Linux uses bin/python, Windows uses Scripts/python.exe
        $venvBase = base_path('../.venv');
        if (PHP_OS_FAMILY === 'Windows') {
            $venvPython = $venvBase . '/Scripts/python.exe';
        } else {
            $venvPython = $venvBase . '/bin/python';
        }
        $scriptPath = base_path('../src/extract_qris_cli.py');
        $cmd = escapeshellcmd($venvPython) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($imagePath);
        
        $output = shell_exec($cmd . ' 2>&1');
        
        if ($output && strpos($output, 'PAYLOAD:') !== false) {
            preg_match('/PAYLOAD:(.*)/', $output, $matches);
            $payload = trim($matches[1]);
            
            if (empty($payload)) {
                return back()->with('error', 'Payload QRIS kosong atau tidak terbaca dari gambar.');
            }

            // Simpan ke Laravel storage (untuk admin panel)
            $filename = 'qris_latest.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('qris', $filename, 'public');
            
            // Simpan juga ke path yang dipakai bot (src/data/qris.png) agar terintegrasi
            try {
                $botQrisPath = base_path('../src/data/qris.png');
                $botQrisDir = dirname($botQrisPath);
                if (!is_dir($botQrisDir)) {
                    mkdir($botQrisDir, 0755, true);
                }
                copy($file->path(), $botQrisPath);
            } catch (\Exception $e) {
                \Log::warning('Gagal copy QRIS ke bot: ' . $e->getMessage());
            }

            \Illuminate\Support\Facades\DB::table('bot_settings')->updateOrInsert(
                ['key' => 'qris_static_payload'],
                ['value' => $payload, 'updated_at' => now()]
            );
            \Illuminate\Support\Facades\DB::table('bot_settings')->updateOrInsert(
                ['key' => 'qris_image_path'],
                ['value' => $path, 'updated_at' => now()]
            );

            return back()->with('success', 'Gambar QRIS berhasil diunggah dan payload terekstraksi. Bot Telegram & Web sudah terintegrasi.');
        } else {
            return back()->with('error', 'Gagal mengekstraksi kode QRIS. Pastikan gambar mengandung QR Code yang valid. Output: ' . substr($output ?? '', 0, 150));
        }
    }

    public function deleteQris()
    {
        // Hapus dari Laravel storage
        $imagePath = \App\Models\BotSetting::where('key', 'qris_image_path')->value('value');
        if ($imagePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($imagePath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
        }

        // Hapus juga file qris.png milik bot
        $botQrisPath = base_path('../src/data/qris.png');
        if (file_exists($botQrisPath)) {
            unlink($botQrisPath);
        }

        \App\Models\BotSetting::whereIn('key', ['qris_static_payload', 'qris_image_path'])->delete();

        return back()->with('success', 'QRIS dan payload berhasil dihapus dari Web & Bot Telegram.');
    }

    public function showQrisImage()
    {
        $imagePath = \App\Models\BotSetting::where('key', 'qris_image_path')->value('value');
        if (!$imagePath || !\Illuminate\Support\Facades\Storage::disk('public')->exists($imagePath)) {
            abort(404);
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($imagePath);
        $mime = mime_content_type($fullPath);
        return response()->file($fullPath, ['Content-Type' => $mime]);
    }

    // ==========================================
    // REPORTS
    // ==========================================
    public function reports()
    {
        // Simple aggregate data
        $totalSales = \App\Models\Order::where('status', 'delivered')->sum('total_amount');
        $totalOrders = \App\Models\Order::count();
        $deliveredOrders = \App\Models\Order::where('status', 'delivered')->count();
        $cancelledOrders = \App\Models\Order::whereIn('status', ['cancelled', 'expired'])->count();
        $totalUsers = \App\Models\User::count();
        
        // Latest 5 delivered orders for table
        $latestOrders = \App\Models\Order::with('customer')
            ->where('status', 'delivered')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('admin.reports.index', compact(
            'totalSales',
            'totalOrders',
            'deliveredOrders',
            'cancelledOrders',
            'totalUsers',
            'latestOrders'
        ));
    }

    // ==========================================
    // WEBSITE SETTINGS
    // ==========================================
    public function websiteSettings()
    {
        $announcement = \App\Models\BotSetting::where('key', 'web_announcement')->value('value') ?? 'Selamat datang Jurangan!<br>kalau punya akun telegram, langsung saja klik "Login Via Telegram" kalau gak punya, bisa regis dulu.';
        return view('admin.website.settings', compact('announcement'));
    }

    // ==========================================
    // ORDER ACTIONS
    // ==========================================
    public function acceptOrder($id, \App\Services\OrderService $orderService)
    {
        $order = \App\Models\Order::findOrFail($id);

        try {
            $orderService->confirmPayment($order, \Illuminate\Support\Facades\Auth::id());
            return redirect()->back()->with('success', 'Pembayaran pesanan berhasil dikonfirmasi.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal konfirmasi pesanan: ' . $e->getMessage());
        }
    }

    public function rejectOrder($id, \App\Services\OrderService $orderService)
    {
        $order = \App\Models\Order::findOrFail($id);

        try {
            $orderService->cancelOrder($order, 'cancelled_by_admin', \Illuminate\Support\Facades\Auth::id());
            return redirect()->back()->with('success', 'Pesanan berhasil ditolak (dibatalkan).');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membatalkan pesanan: ' . $e->getMessage());
        }
    }

    // ==========================================
    // SELLER WALLET WITHDRAWALS
    // ==========================================
    public function withdrawals()
    {
        $withdrawals = \App\Models\WithdrawalRequest::with('seller')->orderBy('created_at', 'desc')->paginate(15);
        return view('admin.withdrawals.index', compact('withdrawals'));
    }

    public function approveWithdrawal(Request $request, $id)
    {
        $withdrawal = \App\Models\WithdrawalRequest::findOrFail($id);
        
        $request->validate([
            'proof_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($withdrawal->status !== 'pending') {
            return redirect()->back()->with('error', 'Permintaan penarikan ini sudah diproses sebelumnya.');
        }

        $seller = $withdrawal->seller;
        if ($seller->wallet_balance < $withdrawal->amount) {
            return redirect()->back()->with('error', 'Saldo wallet seller tidak mencukupi untuk penarikan ini.');
        }

        // Upload proof image
        $image = $request->file('proof_image');
        $fileName = 'proof_' . $withdrawal->id . '_' . time() . '.' . $image->getClientOriginalExtension();
        
        // Store on public storage disk under 'proofs' folder
        $path = \Illuminate\Support\Facades\Storage::disk('public')->putFileAs('proofs', $image, $fileName);
        
        $withdrawal->proof_image_path = 'storage/' . $path;

        // Deduct balance and update status
        $seller->wallet_balance -= $withdrawal->amount;
        $seller->save();

        $withdrawal->status = 'approved';
        $withdrawal->processed_at = now();
        $withdrawal->save();

        return redirect()->back()->with('success', 'Permintaan penarikan berhasil disetujui.');
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $withdrawal = \App\Models\WithdrawalRequest::findOrFail($id);
        
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($withdrawal->status !== 'pending') {
            return redirect()->back()->with('error', 'Permintaan penarikan ini sudah diproses sebelumnya.');
        }

        $withdrawal->status = 'rejected';
        $withdrawal->rejection_reason = $request->rejection_reason;
        $withdrawal->processed_at = now();
        $withdrawal->save();

        return redirect()->back()->with('success', 'Permintaan penarikan berhasil ditolak.');
    }

    // ==========================================
    // PRODUCT WORKERS MANAGEMENT
    // ==========================================
    public function addWorker(Request $request, $id)
    {
        $product = \App\Models\Product::findOrFail($id);
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = \App\Models\User::findOrFail($request->user_id);
        if ($user->role !== 'seller') {
            return redirect()->back()->with('error', 'Hanya pengguna dengan role seller yang dapat ditambahkan sebagai worker.');
        }

        // Attach worker if not already attached
        if (!$product->workers()->where('user_id', $user->id)->exists()) {
            $product->workers()->attach($user->id);
        }

        return redirect()->back()->with('success', 'Worker berhasil ditambahkan ke produk.');
    }

    public function removeWorker($id, $userId)
    {
        $product = \App\Models\Product::findOrFail($id);
        
        // Detach worker
        $product->workers()->detach($userId);

        // Transfer stock ownership to the original product owner (creator_id)
        // If creator_id is null, it belongs to Admin (null)
        $creatorId = $product->creator_id;

        // Find all stock units of this product uploaded by the worker
        $stocks = \App\Models\StockUnit::where('product_id', $product->id)
            ->where('seller_id', $userId)
            ->get();

        foreach ($stocks as $stock) {
            // Populate uploaded_by_id with worker ID if not set, to preserve the creator information
            $stock->uploaded_by_id = $stock->uploaded_by_id ?? $userId;
            $stock->seller_id = $creatorId;
            $stock->save();
        }

        return redirect()->back()->with('success', 'Worker berhasil dihapus dari produk, dan stok miliknya telah dialihkan ke pemilik produk.');
    }
}
