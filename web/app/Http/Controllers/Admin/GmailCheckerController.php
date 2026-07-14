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
        $productsQuery = Product::query();

        if (Auth::user()->role !== 'admin') {
            $productsQuery->where(function($query) {
                $query->where('creator_id', Auth::id())
                    ->orWhereHas('workers', function($q) {
                        $q->where('user_id', Auth::id());
                    });
            });
        }

        // Get products that have stock
        $products = $productsQuery->whereHas('stockUnits', function ($q) {
            $q->where('is_sold', false);
            if (Auth::user()->role !== 'admin') {
                $q->where('seller_id', Auth::id());
            }
        })->withCount(['stockUnits' => function ($q) {
            $q->where('is_sold', false);
            if (Auth::user()->role !== 'admin') {
                $q->where('seller_id', Auth::id());
            }
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

        $query = StockUnit::where('product_id', $request->input('product_id'))
            ->where('is_sold', false);

        if (Auth::user()->role !== 'admin') {
            $query->where('seller_id', Auth::id());
        }

        $stockUnits = $query->get();

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

        $query = StockUnit::whereIn('id', $stockIds)
            ->where('is_sold', false);

        if (Auth::user()->role !== 'admin') {
            $query->where('seller_id', Auth::id());
        }

        if ($action === 'delete') {
            $deleted = $query->delete();

            return response()->json([
                'success' => true,
                'message' => "{$deleted} stok akun berhasil dihapus.",
            ]);
        }

        if ($action === 'update_status') {
            $status = $request->input('status');

            if ($status === 'awaiting_benefits' && Auth::user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Status Awaiting Benefits hanya diperbolehkan untuk Admin.',
                ], 403);
            }

            $updated = $query->update(['stock_status' => $status]);

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

    /**
     * Start the bulk checker using Streaming API (Server-Sent Events style)
     */
    public function startCheck(Request $request)
    {
        $emails = $request->input('emails', []);
        $proxy = $request->input('proxy', null); // format: ip:port or ip:port:user:pass

        if (!is_array($emails) || count($emails) === 0) {
            return response()->json(['error' => 'No emails provided'], 400);
        }

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($emails, $proxy) {
            $total = count($emails);
            $processed = 0;
            
            echo json_encode(['total' => $total, 'processed' => 0]) . "\n";
            ob_flush();
            flush();

            foreach ($emails as $email) {
                // To avoid immediate ban, add small delay if no proxy (2 seconds)
                if (!$proxy) {
                    usleep(2000000); 
                }

                $status = $this->checkGmailStatus($email, $proxy);
                $processed++;
                
                echo json_encode([
                    'processed' => $processed,
                    'total' => $total,
                    'email' => $email,
                    'result' => $status
                ]) . "\n";
                
                ob_flush();
                flush();
            }
            
            echo json_encode(['done' => true]) . "\n";
            ob_flush();
            flush();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Nginx bypass

        return $response;
    }

    /**
     * Internal method to check Gmail using cURL
     */
    private function checkGmailStatus($email, $proxy = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://mail.google.com/mail/gxlu?email=' . urlencode($email));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        if ($proxy) {
            $proxyParts = explode(':', $proxy);
            if (count($proxyParts) >= 2) {
                curl_setopt($ch, CURLOPT_PROXY, $proxyParts[0] . ':' . $proxyParts[1]);
                if (count($proxyParts) == 4) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyParts[2] . ':' . $proxyParts[3]);
                }
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 429) {
            return 'error'; // Rate limited
        }

        if ($response && stripos($response, 'Set-Cookie:') !== false) {
            return 'live';
        }

        return 'die';
    }
}
