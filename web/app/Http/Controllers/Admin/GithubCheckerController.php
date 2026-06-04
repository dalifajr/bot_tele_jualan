<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GithubCheckBatch;
use App\Models\GithubCheckResult;
use App\Models\Product;
use App\Models\StockUnit;
use App\Services\GithubCheckerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GithubCheckerController extends Controller
{
    protected GithubCheckerService $service;

    public function __construct(GithubCheckerService $service)
    {
        $this->service = $service;
    }

    /**
     * Main page: GitHub Live Checker tool.
     */
    public function index()
    {
        // Get products that have stock (for "Load from Stock" feature)
        $products = Product::whereHas('stockUnits', function ($q) {
            $q->where('is_sold', false);
        })->withCount(['stockUnits' => function ($q) {
            $q->where('is_sold', false);
        }])->get();

        // Get recent batches for history
        $batches = GithubCheckBatch::where('admin_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Check if cookie is already set in session
        $cookieValid = session('github_cookie_valid', false);
        $cookieUser = session('github_cookie_user', null);

        return view('admin.tools.github-checker.index', compact(
            'products', 'batches', 'cookieValid', 'cookieUser'
        ));
    }

    /**
     * Validate and store GitHub cookie in session.
     */
    public function setCookie(Request $request)
    {
        $request->validate([
            'github_cookie' => 'required|string|min:10',
        ]);

        $cookie = $request->input('github_cookie');
        $result = $this->service->validateCookie($cookie);

        if ($result['valid']) {
            session([
                'github_cookie' => $cookie,
                'github_cookie_valid' => true,
                'github_cookie_user' => $result['logged_in_as'],
            ]);
        } else {
            session()->forget(['github_cookie', 'github_cookie_valid', 'github_cookie_user']);
        }

        return response()->json($result);
    }

    /**
     * Start a new checking batch.
     * Receives list of usernames, creates batch and stores them for processing.
     */
    public function start(Request $request)
    {
        $request->validate([
            'usernames' => 'required|string',
            'delay' => 'nullable|integer|min:1|max:10',
        ]);

        $cookie = session('github_cookie');
        if (!$cookie) {
            return response()->json([
                'success' => false,
                'message' => 'Cookie GitHub belum diset. Silakan validasi cookie terlebih dahulu.',
            ], 422);
        }

        // Parse usernames
        $usernames = $this->service->parseUsernames($request->input('usernames'));

        if (empty($usernames)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada username valid yang ditemukan dari input.',
            ], 422);
        }

        // Create batch
        $batch = GithubCheckBatch::create([
            'admin_id' => Auth::id(),
            'total_accounts' => count($usernames),
            'checked_count' => 0,
            'status' => 'running',
            'started_at' => now(),
        ]);

        // Store usernames in session for this batch (to process one by one via AJAX)
        session(["github_batch_{$batch->id}_usernames" => $usernames]);
        session(["github_batch_{$batch->id}_delay" => $request->input('delay', 2)]);

        return response()->json([
            'success' => true,
            'batch_id' => $batch->id,
            'total' => count($usernames),
            'usernames' => $usernames,
            'message' => "Batch #{$batch->id} dibuat. Memulai pengecekan " . count($usernames) . " akun...",
        ]);
    }

    /**
     * Check the next username in a batch (called via AJAX polling).
     * Processes one account at a time to avoid PHP timeout.
     */
    public function checkNext(Request $request, $batchId)
    {
        $batch = GithubCheckBatch::findOrFail($batchId);
        $cookie = session('github_cookie');

        if (!$cookie) {
            return response()->json(['success' => false, 'message' => 'Cookie expired'], 422);
        }

        $username = $request->input('username');
        if (!$username) {
            return response()->json(['success' => false, 'message' => 'Username required'], 422);
        }

        // Perform the check
        $result = $this->service->checkUsername($username, $cookie);

        // Save result to database
        GithubCheckResult::create([
            'batch_id' => $batch->id,
            'username' => $result['username'],
            'result' => $result['result'],
            'detail' => $result['detail'],
            'stock_unit_id' => $request->input('stock_id'),
            'checked_at' => now(),
        ]);

        // Update batch progress
        $batch->increment('checked_count');

        if ($batch->checked_count >= $batch->total_accounts) {
            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'result' => array_merge($result, [
                'stock_id' => $request->input('stock_id')
            ]),
            'progress' => [
                'checked' => $batch->fresh()->checked_count,
                'total' => $batch->total_accounts,
                'percentage' => round(($batch->fresh()->checked_count / $batch->total_accounts) * 100),
            ],
        ]);
    }

    /**
     * Stop a running batch.
     */
    public function stopBatch($batchId)
    {
        $batch = GithubCheckBatch::where('admin_id', Auth::id())->findOrFail($batchId);
        $batch->update([
            'status' => 'stopped',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch dihentikan.',
        ]);
    }

    /**
     * Get progress and results for a batch.
     */
    public function progress($batchId)
    {
        $batch = GithubCheckBatch::with('results')->findOrFail($batchId);

        return response()->json([
            'batch' => [
                'id' => $batch->id,
                'status' => $batch->status,
                'total' => $batch->total_accounts,
                'checked' => $batch->checked_count,
                'percentage' => $batch->total_accounts > 0
                    ? round(($batch->checked_count / $batch->total_accounts) * 100) : 0,
            ],
            'summary' => [
                'approved' => $batch->results->where('result', 'approved')->count(),
                'not_approved' => $batch->results->where('result', 'not_approved')->count(),
                'suspended' => $batch->results->where('result', 'suspended')->count(),
                'error' => $batch->results->where('result', 'error')->count(),
            ],
            'results' => $batch->results->map(function ($r) {
                return [
                    'username' => $r->username,
                    'result' => $r->result,
                    'detail' => $r->detail,
                    'stock_id' => $r->stock_unit_id,
                    'checked_at' => $r->checked_at ? $r->checked_at->format('H:i:s') : '-',
                ];
            }),
        ]);
    }

    /**
     * Export batch results as CSV (compatible with Excel/XLSX).
     */
    public function export(Request $request, $batchId)
    {
        $batch = GithubCheckBatch::with('results')->findOrFail($batchId);

        $filterStatus = $request->input('status'); // optional: filter by status

        $results = $batch->results;
        if ($filterStatus && $filterStatus !== 'all') {
            $results = $results->where('result', $filterStatus);
        }

        $statusLabels = [
            'approved' => 'APPROVED (PRO)',
            'not_approved' => 'BELUM DI-APPROVE / REVOKED',
            'suspended' => 'TIDAK DITEMUKAN / SUSPEN',
            'error' => 'ERROR',
        ];

        // Generate CSV with BOM for Excel compatibility
        $filename = "github_check_batch_{$batchId}";
        if ($filterStatus && $filterStatus !== 'all') {
            $filename .= "_{$filterStatus}";
        }
        $filename .= '_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($results, $statusLabels) {
            $file = fopen('php://output', 'w');
            // BOM for UTF-8 Excel compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['No', 'Username', 'Status', 'Detail', 'Waktu Cek']);

            $no = 1;
            foreach ($results as $result) {
                fputcsv($file, [
                    $no++,
                    $result->username,
                    $statusLabels[$result->result] ?? strtoupper($result->result),
                    $result->detail,
                    $result->checked_at ? $result->checked_at->format('Y-m-d H:i:s') : '-',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Load stock usernames for a specific product.
     */
    public function loadStockUsernames(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $stockUnits = StockUnit::where('product_id', $request->input('product_id'))
            ->where('is_sold', false)
            ->get();

        // Parse usernames from raw_text field
        $usernames = [];
        foreach ($stockUnits as $stock) {
            $parsed = $this->service->parseUsernames($stock->raw_text);
            if (!empty($parsed)) {
                $usernames[] = [
                    'stock_id' => $stock->id,
                    'username' => $parsed[0], // First parsed username
                    'raw_text' => $stock->raw_text,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'count' => count($usernames),
            'usernames' => $usernames,
        ]);
    }

    /**
     * Bulk delete stock units for suspended accounts.
     */
    public function bulkDeleteStock(Request $request)
    {
        $request->validate([
            'stock_ids' => 'required|array|min:1',
            'stock_ids.*' => 'integer|exists:stock_units,id',
        ]);

        $deleted = StockUnit::whereIn('id', $request->input('stock_ids'))
            ->where('is_sold', false)
            ->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => "{$deleted} stok akun berhasil dihapus.",
        ]);
    }

    /**
     * Bulk move stock units to a different status.
     */
    public function bulkUpdateStockStatus(Request $request)
    {
        $request->validate([
            'stock_ids' => 'required|array|min:1',
            'stock_ids.*' => 'integer|exists:stock_units,id',
            'stock_status' => 'required|string',
        ]);

        $updated = StockUnit::whereIn('id', $request->input('stock_ids'))
            ->where('is_sold', false)
            ->update(['stock_status' => $request->input('stock_status')]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'message' => "{$updated} stok akun berhasil diperbarui statusnya.",
        ]);
    }
}
