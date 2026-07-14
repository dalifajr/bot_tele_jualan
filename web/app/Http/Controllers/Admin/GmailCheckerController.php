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
     * Internal method to check Gmail using SMTP Validation
     */
    private function checkGmailStatus($email, $proxy = null)
    {
        $domain = substr(strrchr($email, "@"), 1);
        if (strtolower($domain) === 'gmail.com') {
            $mx = 'gmail-smtp-in.l.google.com';
        } else {
            getmxrr($domain, $mxhosts);
            if (empty($mxhosts)) {
                return 'error: no mx records';
            }
            $mx = $mxhosts[0];
        }
        
        $timeout = 10;
        $fp = false;
        
        if ($proxy) {
            // Attempt HTTP CONNECT via proxy (basic implementation)
            $proxyParts = explode(':', $proxy);
            if (count($proxyParts) >= 2) {
                $pHost = $proxyParts[0];
                $pPort = $proxyParts[1];
                $fp = @fsockopen($pHost, $pPort, $errno, $errstr, $timeout);
                if ($fp) {
                    $auth = '';
                    if (count($proxyParts) == 4) {
                        $auth = "Proxy-Authorization: Basic " . base64_encode($proxyParts[2] . ':' . $proxyParts[3]) . "\r\n";
                    }
                    fputs($fp, "CONNECT $mx:25 HTTP/1.1\r\nHost: $mx:25\r\n" . $auth . "\r\n");
                    $res = fgets($fp, 1024);
                    if (stripos($res, '200') === false) {
                        fclose($fp);
                        return 'error';
                    }
                }
            }
        } else {
            $fp = @fsockopen($mx, 25, $errno, $errstr, $timeout);
        }

        if (!$fp) {
            return 'error: connection blocked (port 25) - ' . $errstr;
        }
        
        // Wait for greeting
        $res = fgets($fp, 1024);
        if (substr($res, 0, 3) != '220') {
            fclose($fp);
            return 'error: invalid greeting ' . $res;
        }
        
        fputs($fp, "HELO google.com\r\n");
        fgets($fp, 1024);
        
        fputs($fp, "MAIL FROM: <no-reply@gmail.com>\r\n");
        fgets($fp, 1024);
        
        fputs($fp, "RCPT TO: <$email>\r\n");
        $res = fgets($fp, 1024); // This contains the result
        
        fputs($fp, "QUIT\r\n");
        fclose($fp);
        
        if (substr($res, 0, 3) == '250') {
            return 'live';
        } elseif (substr($res, 0, 3) == '550') {
            return 'die';
        }
        
        // Sometimes Google sends 450/452 for rate limits
        if (substr($res, 0, 1) == '4') {
            return 'error';
        }
        
        return 'die';
    }
}
