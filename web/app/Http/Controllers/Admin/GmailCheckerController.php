<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GmailCheckerController extends Controller
{
    /**
     * Main page: Gmail Live Checker tool.
     */
    public function index()
    {
        // Get products that have stock
        $products = Product::whereHas('stockUnits', function ($q) {
            $q->where('is_sold', false);
        })->withCount(['stockUnits' => function ($q) {
            $q->where('is_sold', false);
        }])->get();

        return view('admin.tools.gmail-checker.index', compact('products'));
    }

    /**
     * Load stock units for a specific product and extract gmail addresses.
     */
    public function loadStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $stockUnits = StockUnit::where('product_id', $request->input('product_id'))
            ->where('is_sold', false)
            ->get();

        $emails = [];
        foreach ($stockUnits as $stock) {
            // Find email matching gmail.com in raw_text
            if (preg_match('/[a-zA-Z0-9._%+-]+@gmail\.com/i', $stock->raw_text, $matches)) {
                $emails[] = [
                    'stock_id' => $stock->id,
                    'email' => strtolower(trim($matches[0])),
                    'raw_text' => $stock->raw_text,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'count' => count($emails),
            'emails' => $emails,
        ]);
    }

    /**
     * Perform bulk action (delete/update status) on selected stock units.
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|string|in:delete,update_status',
            'stock_ids' => 'required|array|min:1',
            'stock_ids.*' => 'integer|exists:stock_units,id',
            'status' => 'required_if:action,update_status|string|nullable',
        ]);

        $stockIds = $request->input('stock_ids');
        $action = $request->input('action');

        if ($action === 'delete') {
            $deleted = StockUnit::whereIn('id', $stockIds)
                ->where('is_sold', false)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "{$deleted} stok akun berhasil dihapus.",
            ]);
        }

        if ($action === 'update_status') {
            $status = $request->input('status');
            $updated = StockUnit::whereIn('id', $stockIds)
                ->where('is_sold', false)
                ->update(['stock_status' => $status]);

            return response()->json([
                'success' => true,
                'message' => "{$updated} stok akun berhasil diperbarui statusnya menjadi " . strtoupper($status) . ".",
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Aksi tidak dikenal.',
        ], 422);
    }
}
