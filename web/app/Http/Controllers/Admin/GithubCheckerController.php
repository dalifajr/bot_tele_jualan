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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
     * Show batch progress and detail.
     */
    public function showBatch($batchId)
    {
        $batch = GithubCheckBatch::findOrFail($batchId);
        $delay = session("github_batch_{$batchId}_delay", 2);

        $usernames = session("github_batch_{$batchId}_usernames");
        if (!$usernames) {
            $usernames = $batch->results->pluck('username')->toArray();
        }

        $stockMap = session("github_batch_{$batchId}_stock_map", []);

        return view('admin.tools.github-checker.batch', compact('batch', 'delay', 'usernames', 'stockMap'));
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
            'stock_map' => 'nullable|array',
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
        session(["github_batch_{$batch->id}_stock_map" => $request->input('stock_map', [])]);

        return response()->json([
            'success' => true,
            'batch_id' => $batch->id,
            'total' => count($usernames),
            'usernames' => $usernames,
            'redirect_url' => route('admin.tools.github-checker.batch', $batch->id),
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
        $filterStatus = $request->input('status');

        $results = $batch->results;
        if ($filterStatus && $filterStatus !== 'all') {
            $results = $results->where('result', $filterStatus);
        }

        $statusLabels = [
            'approved' => '✅ APPROVED',
            'not_approved' => '⚠️ REVOKED',
            'suspended' => '❌ SUSPENDED',
            'error' => '🔄 ERROR',
        ];

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Batch #' . $batchId);

        // Show grid lines explicitly
        $sheet->setShowGridlines(true);

        // Header style
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0D6EFD'], // Bootstrap primary color
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D3D3D3'],
                ],
            ],
        ];

        // Data border style
        $dataBorderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E0E0E0'],
                ],
            ],
        ];

        // Headers
        $headers = ['No', 'Username', 'Status', 'Detail', 'Waktu Cek'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Populate data
        $row = 2;
        $no = 1;
        foreach ($results as $result) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $result->username);
            
            $statusText = $statusLabels[$result->result] ?? strtoupper($result->result);
            $sheet->setCellValue('C' . $row, $statusText);
            
            $sheet->setCellValue('D' . $row, $result->detail);
            $sheet->setCellValue('E' . $row, $result->checked_at ? $result->checked_at->format('Y-m-d H:i:s') : '-');

            // Set alignment
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Apply borders
            $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($dataBorderStyle);

            // Color status column
            $statusColor = '000000';
            $statusBg = 'FFFFFF';
            if ($result->result === 'approved') {
                $statusColor = '198754';
                $statusBg = 'D1E7DD';
            } elseif ($result->result === 'not_approved') {
                $statusColor = 'A18000';
                $statusBg = 'FFF3CD';
            } elseif ($result->result === 'suspended') {
                $statusColor = 'DC3545';
                $statusBg = 'F8D7DA';
            } elseif ($result->result === 'error') {
                $statusColor = '6C757D';
                $statusBg = 'E2E3E5';
            }

            if ($statusBg !== 'FFFFFF') {
                $sheet->getStyle('C' . $row)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => $statusColor],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $statusBg],
                    ],
                ]);
            }

            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        // Auto-fit column widths
        foreach (range('A', 'E') as $colChar) {
            $sheet->getColumnDimension($colChar)->setAutoSize(true);
        }

        $filename = "github_check_batch_{$batchId}";
        if ($filterStatus && $filterStatus !== 'all') {
            $filename .= "_{$filterStatus}";
        }
        $filename .= '_' . now()->format('Y-m-d_His') . '.xlsx';

        $responseHeaders = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ];

        return response()->stream(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, $responseHeaders);
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
