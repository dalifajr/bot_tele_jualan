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

    public function stock()
    {
        $stockUnits = StockUnit::with('product')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        return view('admin.stock.index', compact('stockUnits'));
    }

    public function orders(Request $request)
    {
        $query = Order::with(['customer', 'items.product'])->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate(10);
        $status = $request->status;

        return view('admin.orders.index', compact('orders', 'status'));
    }

    public function users()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.users.index', compact('users'));
    }

    // --- CRUD Products ---
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);
        Product::create($request->only(['name', 'description', 'price']));
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
        $product = Product::findOrFail($id);
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil dihapus.');
    }

    // --- CRUD Stock ---
    public function storeStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'raw_text' => 'required|string',
        ]);

        // Split by lines and create multiple stock units
        $lines = array_filter(array_map('trim', explode("\n", $request->raw_text)));
        $count = 0;
        foreach ($lines as $line) {
            if (!empty($line)) {
                StockUnit::create([
                    'product_id' => $request->product_id,
                    'raw_text' => $line,
                    'stock_status' => 'ready',
                    'is_sold' => false
                ]);
                $count++;
            }
        }
        return redirect()->route('admin.stock.index')->with('success', "$count stok berhasil ditambahkan.");
    }

    public function destroyStock($id)
    {
        $stock = StockUnit::findOrFail($id);
        $stock->delete();
        return redirect()->route('admin.stock.index')->with('success', 'Stok berhasil dihapus.');
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
            'role' => 'required|in:admin,customer'
        ]);
        $user->role = $request->role;
        $user->save();
        return redirect()->route('admin.users.index')
            ->with('success', 'Hak akses pengguna berhasil diperbarui.');
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

        $token = env('BOT_TOKEN'); // Atau ambil dari config jika ada
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
        $settings = \App\Models\BotSetting::all()->pluck('value', 'key');
        return view('admin.settings.index', compact('settings'));
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
        return view('admin.website.settings');
    }
}
