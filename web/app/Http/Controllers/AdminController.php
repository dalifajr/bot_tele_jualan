<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use \App\Traits\ParsesStockBlocks;

    public function dashboard(Request $request)
    {
        $productId = $request->query('product_id');
        $period = $request->query('period');        // Backward compatibility if ?days= is passed (e.g. from existing links or tests)
        if ($request->has('days') && !$period) {
            $daysParam = $request->query('days');
            if ($daysParam === 'all' || $daysParam === 'all_time') {
                $period = 'all_time';
            } else {
                $daysInt = (int)$daysParam;
                if ($daysInt <= 1) {
                    $period = '24_hours';
                } elseif ($daysInt <= 7) {
                    $period = '7_days';
                } elseif ($daysInt <= 30) {
                    $period = '30_days';
                } else {
                    $period = '6_months';
                }
            }
        }
        if (!in_array($period, ['24_hours', '7_days', '30_days', '6_months', 'all_time'])) {
            $period = '24_hours';
        }

        // Set date limits
        $currentEnd = now();
        if ($period === '24_hours') {
            $currentStart = now()->subHours(24);
            $previousEnd = $currentStart;
            $previousStart = now()->subHours(48);
            $days = 1;
        } elseif ($period === '7_days') {
            $currentStart = now()->subDays(7)->startOfDay();
            $previousEnd = $currentStart;
            $previousStart = now()->subDays(14)->startOfDay();
            $days = 7;
        } elseif ($period === '30_days') {
            $currentStart = now()->subDays(30)->startOfDay();
            $previousEnd = $currentStart;
            $previousStart = now()->subDays(60)->startOfDay();
            $days = 30;
        } elseif ($period === '6_months') {
            $currentStart = now()->subMonths(6)->startOfDay();
            $previousEnd = $currentStart;
            $previousStart = now()->subMonths(12)->startOfDay();
            $days = 180;
        } else { // all_time
            $currentStart = \Carbon\Carbon::create(2000, 1, 1);
            $previousEnd = null;
            $previousStart = null;
            $days = 'all';
        }

        // Helper to query metrics
        $getMetrics = function ($start, $end) use ($productId) {
            // 1. Total Revenue
            $revQuery = Order::where('status', 'delivered')
                ->whereBetween('created_at', [$start, $end]);
            if ($productId) {
                $revQuery->whereHas('items', function($q) use ($productId) {
                    $q->where('product_id', $productId);
                });
            }
            $totalRevenue = $revQuery->sum('total_amount');

            // 2. Platform Commission
            $commQuery = \Illuminate\Support\Facades\DB::table('stock_units')
                ->join('products', 'stock_units.product_id', '=', 'products.id')
                ->join('users', 'stock_units.seller_id', '=', 'users.id')
                ->where('stock_units.is_sold', true)
                ->where('users.role', 'seller')
                ->whereIn('stock_units.sold_order_id', function ($query) use ($start, $end) {
                    $query->select('id')->from('orders')
                        ->where('status', 'delivered')
                        ->whereBetween('created_at', [$start, $end]);
                });
            if ($productId) {
                $commQuery->where('stock_units.product_id', $productId);
            }
            $platformCommission = $commQuery->selectRaw("SUM(products.price * COALESCE(users.platform_fee_percent, 10) / 100) as total_commission")
                ->value('total_commission') ?? 0;

            // 3. Admin Earnings
            $adminSalesQuery = \Illuminate\Support\Facades\DB::table('stock_units')
                ->join('products', 'stock_units.product_id', '=', 'products.id')
                ->leftJoin('users', 'stock_units.seller_id', '=', 'users.id')
                ->where('stock_units.is_sold', true)
                ->where(function ($query) {
                    $query->whereNull('stock_units.seller_id')
                          ->orWhere('users.role', '!=', 'seller');
                })
                ->whereIn('stock_units.sold_order_id', function ($query) use ($start, $end) {
                    $query->select('id')->from('orders')
                        ->where('status', 'delivered')
                        ->whereBetween('created_at', [$start, $end]);
                });
            if ($productId) {
                $adminSalesQuery->where('stock_units.product_id', $productId);
            }
            $adminSalesEarnings = $adminSalesQuery->selectRaw("SUM(products.price) as total_earnings")
                ->value('total_earnings') ?? 0;

            $codeQuery = Order::where('status', 'delivered')
                ->whereBetween('created_at', [$start, $end]);
            if ($productId) {
                $codeQuery->whereHas('items', function($q) use ($productId) {
                    $q->where('product_id', $productId);
                });
            }
            $totalUniqueCodes = $codeQuery->sum('unique_code');
            $adminEarnings = (int)$adminSalesEarnings + (int)$totalUniqueCodes;

            // 4. Seller Earnings
            $sellerEarningsQuery = \Illuminate\Support\Facades\DB::table('stock_units')
                ->join('products', 'stock_units.product_id', '=', 'products.id')
                ->join('users', 'stock_units.seller_id', '=', 'users.id')
                ->where('stock_units.is_sold', true)
                ->where('users.role', 'seller')
                ->whereIn('stock_units.sold_order_id', function ($query) use ($start, $end) {
                    $query->select('id')->from('orders')
                        ->where('status', 'delivered')
                        ->whereBetween('created_at', [$start, $end]);
                });
            if ($productId) {
                $sellerEarningsQuery->where('stock_units.product_id', $productId);
            }
            $totalSellerEarnings = $sellerEarningsQuery->selectRaw("SUM(products.price - (products.price * COALESCE(users.platform_fee_percent, 10) / 100)) as total_earnings")
                ->value('total_earnings') ?? 0;

            // 5. Total Orders
            $ordersQuery = Order::whereBetween('created_at', [$start, $end]);
            if ($productId) {
                $ordersQuery->whereHas('items', function($q) use ($productId) {
                    $q->where('product_id', $productId);
                });
            }
            $totalOrders = $ordersQuery->count();

            // 8. Delivered and Cancelled Orders for chart
            $delQuery = Order::where('status', 'delivered')
                ->whereBetween('created_at', [$start, $end]);
            if ($productId) {
                $delQuery->whereHas('items', function($q) use ($productId) {
                    $q->where('product_id', $productId);
                });
            }
            $deliveredOrders = $delQuery->count();

            $canQuery = Order::whereIn('status', ['cancelled', 'expired'])
                ->whereBetween('created_at', [$start, $end]);
            if ($productId) {
                $canQuery->whereHas('items', function($q) use ($productId) {
                    $q->where('product_id', $productId);
                });
            }
            $cancelledOrders = $canQuery->count();

            return compact(
                'totalRevenue', 'platformCommission', 'adminEarnings', 'totalSellerEarnings',
                'totalOrders', 'deliveredOrders', 'cancelledOrders'
            );
        };

        $currentMetrics = $getMetrics($currentStart, $currentEnd);
        $previousMetrics = ($previousStart && $previousEnd) ? $getMetrics($previousStart, $previousEnd) : [
            'totalRevenue' => 0, 'platformCommission' => 0, 'adminEarnings' => 0, 'totalSellerEarnings' => 0,
            'totalOrders' => 0, 'deliveredOrders' => 0, 'cancelledOrders' => 0
        ];

        // For Products and Users, we want cumulative counts up to end dates
        $currentProducts = Product::where('created_at', '<=', $currentEnd)->count();
        $previousProducts = $previousEnd ? Product::where('created_at', '<=', $previousEnd)->count() : 0;

        $currentUsers = User::where('created_at', '<=', $currentEnd)->count();
        $previousUsers = $previousEnd ? User::where('created_at', '<=', $previousEnd)->count() : 0;

        $webUsersCount = User::whereNotNull('password')
            ->where('created_at', '<=', $currentEnd)
            ->count();
        $tgUsersCount = User::whereNotNull('telegram_id')
            ->where('created_at', '<=', $currentEnd)
            ->count();

        // Helper to calculate percentage growth / change
        $calculateChange = function ($current, $previous) use ($period) {
            if ($period === 'all_time') {
                return [
                    'value' => $current,
                    'diff' => 0,
                    'percent' => 0,
                    'formatted_percent' => 'Total',
                    'class' => 'text-muted',
                    'icon' => 'fa-check'
                ];
            }
            $diff = $current - $previous;
            if ($previous > 0) {
                $percent = round(($diff / $previous) * 100, 1);
            } else {
                $percent = $current > 0 ? 100.0 : 0.0;
            }
            return [
                'value' => $current,
                'diff' => $diff,
                'percent' => $percent,
                'formatted_percent' => ($percent >= 0 ? '+' : '') . $percent . '%',
                'class' => $percent >= 0 ? 'text-success' : 'text-danger',
                'icon' => $percent >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'
            ];
        };

        $revenueStats = $calculateChange($currentMetrics['totalRevenue'], $previousMetrics['totalRevenue']);
        $commissionStats = $calculateChange($currentMetrics['platformCommission'], $previousMetrics['platformCommission']);
        $adminEarningsStats = $calculateChange($currentMetrics['adminEarnings'], $previousMetrics['adminEarnings']);
        $sellerEarningsStats = $calculateChange($currentMetrics['totalSellerEarnings'], $previousMetrics['totalSellerEarnings']);
        $ordersStats = $calculateChange($currentMetrics['totalOrders'], $previousMetrics['totalOrders']);
        $productsStats = $calculateChange($currentProducts, $previousProducts);
        $usersStats = $calculateChange($currentUsers, $previousUsers);

        // Unpack for blade compatibility
        $totalRevenue = $currentMetrics['totalRevenue'];
        $platformCommission = $currentMetrics['platformCommission'];
        $adminEarnings = $currentMetrics['adminEarnings'];
        $totalSellerEarnings = $currentMetrics['totalSellerEarnings'];
        $totalOrders = $currentMetrics['totalOrders'];
        $totalProducts = $currentProducts;
        $totalUsers = $currentUsers;
        $deliveredOrders = $currentMetrics['deliveredOrders'];
        $cancelledOrders = $currentMetrics['cancelledOrders'];

        $recQuery = Order::with(['customer', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(5);
        if ($productId) {
            $recQuery->whereHas('items', function($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }
        $recentOrders = $recQuery->get();

        // Helper for chart values
        $getChartOrderSum = function ($start, $end) use ($productId) {
            $query = Order::where('status', 'delivered')
                ->whereBetween('created_at', [$start, $end]);
            if ($productId) {
                $query->whereHas('items', function($q) use ($productId) {
                    $q->where('product_id', $productId);
                });
            }
            return (int) $query->sum('total_amount');
        };

        // Generate Chart Data based on period
        $chartLabels = [];
        $chartData = [];

        if ($period === '24_hours') {
            for ($i = 23; $i >= 0; $i--) {
                $hourObj = now()->subHours($i);
                $start = (clone $hourObj)->startOfHour();
                $end = (clone $hourObj)->endOfHour();
                
                $chartLabels[] = $hourObj->format('H:00');
                $chartData[] = $getChartOrderSum($start, $end);
            }
        } elseif ($period === '7_days') {
            for ($i = 6; $i >= 0; $i--) {
                $dayObj = now()->subDays($i);
                $start = (clone $dayObj)->startOfDay();
                $end = (clone $dayObj)->endOfDay();
                
                $chartLabels[] = $dayObj->format('d M');
                $chartData[] = $getChartOrderSum($start, $end);
            }
        } elseif ($period === '30_days') {
            for ($i = 29; $i >= 0; $i--) {
                $dayObj = now()->subDays($i);
                $start = (clone $dayObj)->startOfDay();
                $end = (clone $dayObj)->endOfDay();
                
                $chartLabels[] = $dayObj->format('d M');
                $chartData[] = $getChartOrderSum($start, $end);
            }
        } elseif ($period === '6_months') {
            for ($i = 5; $i >= 0; $i--) {
                $monthObj = now()->subMonths($i);
                $start = (clone $monthObj)->startOfMonth()->startOfDay();
                $end = (clone $monthObj)->endOfMonth()->endOfDay();
                
                $chartLabels[] = $monthObj->format('M Y');
                $chartData[] = $getChartOrderSum($start, $end);
            }
        } else { // all_time
            for ($i = 11; $i >= 0; $i--) {
                $monthObj = now()->subMonths($i);
                $start = (clone $monthObj)->startOfMonth()->startOfDay();
                $end = (clone $monthObj)->endOfMonth()->endOfDay();
                
                $chartLabels[] = $monthObj->format('M Y');
                $chartData[] = $getChartOrderSum($start, $end);
            }
        }

        $periodLabel = match($period) {
            '24_hours' => __('24 jam terakhir'),
            '7_days' => __('7 hari terakhir'),
            '30_days' => __('30 hari terakhir'),
            '6_months' => __('6 bulan terakhir'),
            'all_time' => __('Sepanjang waktu'),
        };

        $products = \App\Models\Product::orderBy('name')->get();

        return view('admin.dashboard', compact(
            'totalRevenue', 'platformCommission', 'adminEarnings', 'totalSellerEarnings', 'totalOrders', 'totalProducts', 'totalUsers', 'webUsersCount', 'tgUsersCount', 'recentOrders',
            'deliveredOrders', 'cancelledOrders', 'chartLabels', 'chartData', 'days', 'period', 'periodLabel',
            'revenueStats', 'commissionStats', 'adminEarningsStats', 'sellerEarningsStats', 'ordersStats', 'productsStats', 'usersStats', 'products', 'productId'
        ));
    }

    public function products(Request $request)
    {
        $query = Product::with(['creator', 'stockUnits', 'workers']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $q->orWhere('id', (int)$search);
                }
            });
        }

        if ($request->filled('seller_id')) {
            if ($request->seller_id === 'admin') {
                $query->whereNull('creator_id');
            } else {
                $query->where('creator_id', $request->seller_id);
            }
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(16)->withQueryString();
        $sellers = User::where('role', 'seller')->orderBy('full_name')->orderBy('username')->get();

        return view('admin.products.index', compact('products', 'sellers'));
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

        // Metrics
        $metricsQuery = StockUnit::query();

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

        $stockUnits = $query->paginate(15);
        $status = $request->status;
        $customers = User::orderBy('full_name')->orderBy('username')->take(60)->get(['id', 'full_name', 'username', 'email', 'telegram_id', 'role']);

        return view('admin.stock.index', compact(
            'stockUnits', 'status', 'totalStock', 'readyStock', 'awaitingStock', 'savedStock', 'soldStock', 'customers'
        ));
    }

    public function searchCustomers(Request $request)
    {
        $search = trim($request->query('q', ''));
        $users = User::where(function ($query) use ($search) {
                $query->where('username', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telegram_id', 'like', "%{$search}%");
            })
            ->limit(25)
            ->get(['id', 'username', 'full_name', 'email', 'telegram_id', 'role']);

        return response()->json($users);
    }

    public function exportStock(Request $request)
    {
        // Always require admin password verification before exporting sensitive credentials
        $request->validate(['password' => 'required|string']);
        if (!\Illuminate\Support\Facades\Hash::check($request->password, \Illuminate\Support\Facades\Auth::user()->password)) {
            return redirect()->back()->with('error', __('Password salah. Ekspor data dibatalkan.'));
        }

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_export_stock',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'stock_unit',
            'entity_id' => 0,
            'detail' => 'Admin exported stock data',
            'created_at' => now(),
        ]);

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

        $stockUnits = $query->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Stok');
        $sheet->setShowGridlines(true);

        // Header style
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '198754'], // Green for stock
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

        // Data border style
        $dataBorderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'E0E0E0'],
                ],
            ],
        ];

        $headers = ['No', 'Produk', 'Detail Akun (Raw Text)', 'Status', 'Uploader', 'Pembeli', 'No Order', 'Tanggal Input'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $row = 2;
        $no = 1;
        foreach ($stockUnits as $unit) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $unit->product->name ?? '-');
            $sheet->setCellValue('C' . $row, $unit->raw_text);
            
            $statusText = $unit->is_sold ? 'TERJUAL' : strtoupper($unit->stock_status);
            $sheet->setCellValue('D' . $row, $statusText);
            
            $uploaderName = $unit->uploader->full_name ?? $unit->uploader->username ?? ($unit->seller->full_name ?? $unit->seller->username ?? 'Admin Utama');
            $sheet->setCellValue('E' . $row, $uploaderName);
            $sheet->setCellValue('F' . $row, $unit->order->customer->full_name ?? $unit->order->customer->username ?? '-');
            $sheet->setCellValue('G' . $row, $unit->order->reference ?? '-');
            $sheet->setCellValue('H' . $row, $unit->created_at ? $unit->created_at->format('Y-m-d H:i:s') : '-');

            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($dataBorderStyle);
            
            // Color status column
            $statusColor = '000000';
            $statusBg = 'FFFFFF';
            if ($unit->is_sold) {
                $statusColor = '6C757D';
                $statusBg = 'E2E3E5';
            } elseif ($unit->stock_status === 'ready') {
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

        foreach (range('A', 'H') as $colChar) {
            $sheet->getColumnDimension($colChar)->setAutoSize(true);
        }

        $filename = 'data_stok_' . now()->format('Y-m-d_His') . '.xlsx';

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

    public function orders(Request $request)
    {
        $query = Order::with(['customer', 'items.product', 'stockUnits'])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            if ($request->status === 'cancelled_expired') {
                $query->whereIn('status', ['cancelled', 'expired']);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('product_id')) {
            $query->whereHas('items', function ($iq) use ($request) {
                $iq->where('product_id', $request->product_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $cleanNominal = preg_replace('/[^0-9]/', '', $search);
            $query->where(function ($q) use ($search, $cleanNominal) {
                $q->where('order_ref', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%")
                         ->orWhere('telegram_id', 'like', "%{$search}%");
                  })
                  ->orWhereHas('items.product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('stockUnits', function ($suq) use ($search) {
                      $suq->where('raw_text', 'like', "%{$search}%");
                  });

                if (!empty($cleanNominal) && is_numeric($cleanNominal)) {
                    $q->orWhere('total_amount', (int)$cleanNominal)
                      ->orWhere('subtotal', (int)$cleanNominal);
                }
            });
        }

        $orders = $query->paginate(12)->withQueryString();
        $status = $request->status;

        return view('admin.orders.index', compact('orders', 'status'));
    }

    public function showOrder($id)
    {
        $order = Order::with(['customer', 'items.product.creator', 'stockUnits.seller', 'complaintCase', 'complaints.product', 'complaints.seller', 'payment', 'vpnAccounts'])
            ->findOrFail($id);

        return view('admin.orders.show', compact('order'));
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

        if ($request->filled('role')) {
            if ($request->role === 'suspended') {
                $query->where('is_suspended', true);
            } else {
                $query->where('role', $request->role);
            }
        }

        // Metrics
        $customerCount = User::where('role', 'customer')->count();
        $sellerCount = User::where('role', 'seller')->count();
        $adminCount = User::where('role', 'admin')->count();
        $suspendedCount = User::where('is_suspended', true)->count();

        $users = $query->paginate(10);
        return view('admin.users.index', compact(
            'users', 'customerCount', 'sellerCount', 'adminCount', 'suspendedCount'
        ));
    }

    public function sellers(Request $request)
    {
        $query = User::where('role', 'seller')->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telegram_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'suspended') {
                $query->where('is_suspended', true);
            } elseif ($request->status === 'active') {
                $query->where('is_suspended', false);
            }
        }

        $sellers = $query->paginate(10);

        // Determine period filter limits
        $period = $request->input('period', '7_days');
        if ($period === '30_days') {
            $currentPeriodStartDate = now()->subDays(30)->startOfDay();
            $startDate = now()->subDays(60)->startOfDay();
            $totalPointsCount = 60;
            $sparklineLength = 30;
            $dateFormat = 'Y-m-d';
            $dateStep = 'day';
        } elseif ($period === '6_months') {
            $currentPeriodStartDate = now()->subMonths(6)->startOfMonth()->startOfDay();
            $startDate = now()->subMonths(12)->startOfMonth()->startOfDay();
            $totalPointsCount = 12;
            $sparklineLength = 6;
            $dateFormat = 'Y-m';
            $dateStep = 'month';
        } elseif ($period === '1_year') {
            $currentPeriodStartDate = now()->subMonths(12)->startOfMonth()->startOfDay();
            $startDate = now()->subMonths(24)->startOfMonth()->startOfDay();
            $totalPointsCount = 24;
            $sparklineLength = 12;
            $dateFormat = 'Y-m';
            $dateStep = 'month';
        } else { // 7_days (default)
            $currentPeriodStartDate = now()->subDays(7)->startOfDay();
            $startDate = now()->subDays(14)->startOfDay();
            $totalPointsCount = 14;
            $sparklineLength = 7;
            $dateFormat = 'Y-m-d';
            $dateStep = 'day';
        }

        // Calculate total platform commission from ALL sellers in the selected period
        $totalSellersCommission = \Illuminate\Support\Facades\DB::table('stock_units')
            ->join('products', 'stock_units.product_id', '=', 'products.id')
            ->join('users', 'stock_units.seller_id', '=', 'users.id')
            ->where('stock_units.is_sold', true)
            ->where('users.role', 'seller')
            ->whereIn('stock_units.sold_order_id', function ($q) use ($currentPeriodStartDate) {
                $q->select('id')->from('orders')
                  ->where('status', 'delivered')
                  ->where('orders.created_at', '>=', $currentPeriodStartDate);
            })
            ->selectRaw("SUM(products.price * COALESCE(users.platform_fee_percent, 10) / 100) as total")
            ->value('total') ?? 0;

        foreach ($sellers as $seller) {
            $seller->products_count = \App\Models\Product::where('creator_id', $seller->id)->count();
            
            // Available Wallet balance is absolute and does not depend on date filter
            // Held balance is filtered by period limit
            $seller->held_balance = (int) \Illuminate\Support\Facades\DB::table('held_funds')
                ->where('seller_id', $seller->id)
                ->where('status', 'held')
                ->where('created_at', '>=', $currentPeriodStartDate)
                ->sum('amount');

            // 1. Commission Contribution
            $sellerCommission = \Illuminate\Support\Facades\DB::table('stock_units')
                ->join('products', 'stock_units.product_id', '=', 'products.id')
                ->where('stock_units.is_sold', true)
                ->where('stock_units.seller_id', $seller->id)
                ->whereIn('stock_units.sold_order_id', function ($q) use ($currentPeriodStartDate) {
                    $q->select('id')->from('orders')
                      ->where('status', 'delivered')
                      ->where('orders.created_at', '>=', $currentPeriodStartDate);
                })
                ->selectRaw("SUM(products.price * " . (int)($seller->platform_fee_percent ?? 10) . " / 100) as total")
                ->value('total') ?? 0;

            $seller->commission_amount = $sellerCommission;
            $seller->contribution_percentage = $totalSellersCommission > 0 
                ? round(($sellerCommission / $totalSellersCommission) * 100, 1) 
                : 0;

            // 2. Total Net Sales Earnings (Total Pendapatan Bersih)
            $sellerNetEarnings = \Illuminate\Support\Facades\DB::table('stock_units')
                ->join('products', 'stock_units.product_id', '=', 'products.id')
                ->where('stock_units.is_sold', true)
                ->where('stock_units.seller_id', $seller->id)
                ->whereIn('stock_units.sold_order_id', function ($q) use ($currentPeriodStartDate) {
                    $q->select('id')->from('orders')
                      ->where('status', 'delivered')
                      ->where('orders.created_at', '>=', $currentPeriodStartDate);
                })
                ->selectRaw("SUM(products.price - (products.price * " . (int)($seller->platform_fee_percent ?? 10) . " / 100)) as total")
                ->value('total') ?? 0;
            
            $seller->net_earnings = $sellerNetEarnings;

            // 3. Sales Trend & Sparkline (last 14 days / 60 days / 12 months / 24 months)
            $dates = [];
            for ($i = $totalPointsCount - 1; $i >= 0; $i--) {
                if ($dateStep === 'month') {
                    $dates[] = now()->subMonths($i)->format('Y-m');
                } else {
                    $dates[] = now()->subDays($i)->format('Y-m-d');
                }
            }

            $salesData = \Illuminate\Support\Facades\DB::table('stock_units')
                ->join('products', 'stock_units.product_id', '=', 'products.id')
                ->join('orders', 'stock_units.sold_order_id', '=', 'orders.id')
                ->where('stock_units.seller_id', $seller->id)
                ->where('stock_units.is_sold', true)
                ->where('orders.status', 'delivered')
                ->where('orders.created_at', '>=', $startDate)
                ->selectRaw("orders.created_at, products.price")
                ->get();

            $salesByDate = [];
            foreach ($salesData as $row) {
                $formattedDate = date($dateFormat, strtotime($row->created_at));
                if (!isset($salesByDate[$formattedDate])) {
                    $salesByDate[$formattedDate] = 0;
                }
                $salesByDate[$formattedDate] += $row->price;
            }

            $points = [];
            foreach ($dates as $date) {
                $points[] = $salesByDate[$date] ?? 0;
            }

            // Slice into two halves (current vs previous)
            $salesA = array_sum(array_slice($points, $sparklineLength, $sparklineLength)); // Current period
            $salesB = array_sum(array_slice($points, 0, $sparklineLength)); // Previous period

            if ($salesB > 0) {
                $seller->percentage_change = round((($salesA - $salesB) / $salesB) * 100, 1);
            } else {
                $seller->percentage_change = $salesA > 0 ? 100.0 : 0.0;
            }

            $seller->trend_direction = $seller->percentage_change > 0 
                ? 'up' 
                : ($seller->percentage_change < 0 ? 'down' : 'neutral');

            // Generate sparkline path using the last half (current period)
            $sparklinePoints = array_slice($points, $sparklineLength, $sparklineLength);
            $seller->sparkline_path = $this->generateSparklinePath($sparklinePoints);
        }

        $totalSellers = User::where('role', 'seller')->count();
        $activeSellers = User::where('role', 'seller')->where('is_suspended', false)->count();
        $suspendedSellers = User::where('role', 'seller')->where('is_suspended', true)->count();

        $users = $sellers; // Maintain naming compatibility for modal hooks

        return view('admin.sellers.index', compact(
            'sellers', 'users', 'totalSellers', 'activeSellers', 'suspendedSellers'
        ));
    }

    private function generateSparklinePath($points) {
        $count = count($points);
        if ($count === 0) return 'M 0 15 L 100 15';
        
        $max = max($points);
        $min = min($points);
        $range = $max - $min;
        
        $width = 100;
        $height = 30;
        $padding = 2;
        
        $path = [];
        for ($i = 0; $i < $count; $i++) {
            $x = ($i / ($count - 1)) * $width;
            if ($range > 0) {
                $y = $height - (($points[$i] - $min) / $range) * ($height - 2 * $padding) - $padding;
            } else {
                $y = $height / 2;
            }
            $path[] = ($i === 0 ? 'M' : 'L') . " " . number_format($x, 1) . " " . number_format($y, 1);
        }
        return implode(' ', $path);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting users with existing orders
        if (\App\Models\Order::where('customer_id', $user->id)->exists()) {
            return redirect()->back()->with('error', __('Tidak dapat menghapus pengguna karena memiliki riwayat pesanan. Silakan gunakan fitur Suspend sebagai gantinya.'));
        }

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_delete_user',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'detail' => "Deleted user: {$user->username} (ID: {$user->id})",
            'created_at' => now(),
        ]);

        $user->delete();
        return redirect()->back()->with('success', __('Pengguna berhasil dihapus.'));
    }

    public function suspendUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->is_suspended = true;
        $user->suspension_reason = $request->input('reason');
        $user->save();

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_suspend_user',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'detail' => "Suspended user: {$user->username}; reason: {$request->input('reason')}",
            'created_at' => now(),
        ]);

        return redirect()->back()->with('success', __('Akun pengguna berhasil ditangguhkan (suspended). Akses bot telah dicabut.'));
    }

    public function unsuspendUser($id)
    {
        $user = User::findOrFail($id);
        $user->is_suspended = false;
        $user->suspension_reason = null;
        $user->save();

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_unsuspend_user',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'detail' => "Unsuspended user: {$user->username}",
            'created_at' => now(),
        ]);

        return redirect()->back()->with('success', __('Penangguhan akun telah dicabut. Pengguna dapat menggunakan bot kembali.'));
    }

    public function impersonate(Request $request, $id)
    {
        $targetUser = User::findOrFail($id);
        
        if ($targetUser->id === \Illuminate\Support\Facades\Auth::id()) {
            return redirect()->back()->with('error', __('Tidak dapat login sebagai diri sendiri.'));
        }

        // Store original admin ID in session
        $adminId = \Illuminate\Support\Facades\Auth::id();
        session(['admin_impersonator_id' => $adminId]);

        // Audit Log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_impersonate_start',
            'actor_id' => $adminId,
            'entity_type' => 'user',
            'entity_id' => $targetUser->id,
            'detail' => "admin_id={$adminId} impersonating user_id={$targetUser->id} (username: {$targetUser->username})",
            'created_at' => now(),
        ]);

        // Login as the target user
        \Illuminate\Support\Facades\Auth::loginUsingId($targetUser->id);

        $name = $targetUser->full_name ?? $targetUser->username;
        return redirect()->route('dashboard')->with('success', __("Berhasil masuk sebagai :name.", ['name' => $name]));
    }

    public function stopImpersonating(Request $request)
    {
        if (!session()->has('admin_impersonator_id')) {
            return redirect()->route('dashboard')->with('error', __('Sesi impersonasi tidak ditemukan.'));
        }

        $adminId = session()->pull('admin_impersonator_id');
        $targetUser = \Illuminate\Support\Facades\Auth::user();

        // Audit Log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_impersonate_stop',
            'actor_id' => $adminId,
            'entity_type' => 'user',
            'entity_id' => $targetUser ? $targetUser->id : 0,
            'detail' => "admin_id={$adminId} stopped impersonating",
            'created_at' => now(),
        ]);

        // Login back as admin
        \Illuminate\Support\Facades\Auth::loginUsingId($adminId);

        return redirect()->route('admin.users.index')->with('success', __('Kembali ke sesi Admin.'));
    }

    // --- CRUD Products ---
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'platform_fee_percent' => 'nullable|integer|between:0,100',
            'warranty_days' => 'required_if:enable_warranty,1|nullable|integer|min:1',
            'vpn_protocol' => 'required_if:is_vpn,1|nullable|string',
            'vpn_duration_days' => 'required_if:is_vpn,1|nullable|integer|min:1',
            'vcc_services' => 'required_if:is_vcc,1|nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048',
        ]);
        $data = $request->only(['name', 'description', 'price', 'vpn_protocol', 'vpn_duration_days']);
        $data['description'] = $data['description'] ?? '';
        $data['platform_fee_percent'] = $request->filled('platform_fee_percent') ? (int)$request->platform_fee_percent : null;
        $data['is_suspended'] = false;
        $data['is_vpn'] = $request->has('is_vpn');
        $data['warranty_days'] = $request->has('enable_warranty') ? $request->warranty_days : 0;
        $data['is_vcc'] = $request->has('is_vcc');
        $data['vcc_services'] = $request->has('is_vcc') ? array_values(array_unique(array_filter((array)$request->input('vcc_services', [])))) : null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);
        return redirect()->route('admin.products.index')->with('success', __('Produk berhasil ditambahkan.'));
    }

    public function updateProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'platform_fee_percent' => 'nullable|integer|between:0,100',
            'is_suspended' => 'boolean',
            'warranty_days' => $request->has('enable_warranty') ? 'required|integer|min:1' : 'nullable',
            'vpn_protocol' => $request->has('is_vpn') ? 'required|string' : 'nullable',
            'vpn_duration_days' => $request->has('is_vpn') ? 'required|integer|min:1' : 'nullable',
            'vcc_services' => $request->has('is_vcc') ? 'required|array' : 'nullable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048',
        ]);
        $data = $request->only(['name', 'description', 'price', 'vpn_protocol', 'vpn_duration_days']);
        $data['description'] = $data['description'] ?? '';
        $data['platform_fee_percent'] = $request->filled('platform_fee_percent') ? (int)$request->platform_fee_percent : null;
        $data['is_suspended'] = $request->has('is_suspended');
        $data['is_vpn'] = $request->has('is_vpn');
        $data['warranty_days'] = $request->has('enable_warranty') ? $request->warranty_days : 0;
        $data['is_vcc'] = $request->has('is_vcc');
        $data['vcc_services'] = $request->has('is_vcc') ? array_values(array_unique(array_filter((array)$request->input('vcc_services', [])))) : null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            if ($product->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->image)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        return redirect()->route('admin.products.index')->with('success', __('Produk berhasil diperbarui.'));
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
                
                // Audit log for takeover
                \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                    'action' => 'admin_takeover_product',
                    'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                    'entity_type' => 'product',
                    'entity_id' => $product->id,
                    'detail' => "Admin took over seller product: {$product->name} (Original Seller ID: {$seller->id})",
                    'created_at' => now(),
                ]);

                \Illuminate\Support\Facades\DB::commit();
                
                return redirect()->back()->with('success', __('Produk buatan seller dan sisa stoknya berhasil diambil alih oleh Admin Utama.'));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                return redirect()->back()->with('error', 'Gagal mengambil alih produk seller: ' . $e->getMessage());
            }
        }
        
        try {
            $productId = $product->id;
            $productName = $product->name;

            // Delete all unsold stock units of this product
            \App\Models\StockUnit::where('product_id', $productId)->where('is_sold', false)->delete();
            // Delete from all user carts
            \Illuminate\Support\Facades\DB::table('cart_items')->where('product_id', $productId)->delete();

            $product->delete();

            // Audit log for delete
            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'action' => 'admin_delete_product',
                'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                'entity_type' => 'product',
                'entity_id' => $productId,
                'detail' => "Deleted product: {$productName} (ID: {$productId})",
                'created_at' => now(),
            ]);

            return redirect()->back()->with('success', __('Produk berhasil dihapus.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus produk: ' . $e->getMessage());
        }
    }

    public function takeoverProduct($id)
    {
        $product = Product::findOrFail($id);

        if ($product->creator_id === null) {
            return redirect()->back()->with('info', __('Produk ini sudah dikelola oleh Admin Utama.'));
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $seller = User::find($product->creator_id);
            $sellerName = $seller ? ($seller->full_name ?? $seller->username) : 'Seller (ID: ' . $product->creator_id . ')';

            // Ambil alih semua stok unit
            $stockUnits = \App\Models\StockUnit::where('product_id', $product->id)->get();
            foreach ($stockUnits as $unit) {
                $unit->uploaded_by_id = $unit->uploaded_by_id ?? $unit->seller_id;
                $unit->seller_id = null;
                $unit->save();
            }

            $note = "\n\n[Catatan: Produk ini sebelumnya dikelola oleh " . $sellerName . "]";
            if (strpos($product->description ?? '', '[Catatan:') === false) {
                $product->description = ($product->description ?? '') . $note;
            }

            $product->creator_id = null;
            $product->save();

            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'action' => 'admin_takeover_product',
                'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                'entity_type' => 'product',
                'entity_id' => $product->id,
                'detail' => "Admin took over seller product: {$product->name} (Original Seller ID: {$seller->id})",
                'created_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->back()->with('success', __('Produk buatan seller dan sisa stoknya berhasil diambil alih oleh Admin Utama.'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Gagal mengambil alih produk seller: ' . $e->getMessage());
        }
    }

    public function reassignProduct(Request $request, $id)
    {
        $request->validate([
            'seller_id' => 'required|exists:users,id',
        ]);

        $product = Product::findOrFail($id);
        $targetSeller = User::where('id', $request->seller_id)->whereIn('role', ['seller', 'admin'])->firstOrFail();

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $oldCreatorName = $product->creator ? ($product->creator->full_name ?? $product->creator->username) : 'Admin Utama';
            $targetSellerName = $targetSeller->full_name ?? $targetSeller->username;

            // Transfer unsold stock units to target seller
            $stockUnits = \App\Models\StockUnit::where('product_id', $product->id)->where('is_sold', false)->get();
            foreach ($stockUnits as $unit) {
                $unit->uploaded_by_id = $unit->uploaded_by_id ?? $unit->seller_id;
                $unit->seller_id = $targetSeller->id;
                $unit->save();
            }

            $note = "\n\n[Catatan: Produk ini dilimpahkan ke " . $targetSellerName . " (sebelumnya dikelola oleh " . $oldCreatorName . ")]";
            $product->description = ($product->description ?? '') . $note;

            $product->creator_id = $targetSeller->id;
            $product->save();

            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'action' => 'admin_reassign_product',
                'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                'entity_type' => 'product',
                'entity_id' => $product->id,
                'detail' => "Admin reassigned product {$product->name} from {$oldCreatorName} to {$targetSellerName} (ID: {$targetSeller->id})",
                'created_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->back()->with('success', __('Produk :product berhasil dilimpahkan ke :seller.', ['product' => $product->name, 'seller' => $targetSellerName]));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Gagal melimpahkan produk: ' . $e->getMessage());
        }
    }

    public function exportUnsoldStock($id)
    {
        $product = \App\Models\Product::findOrFail($id);

        $stockUnits = \App\Models\StockUnit::with(['uploader', 'seller'])
            ->where('product_id', $product->id)
            ->where('is_sold', false)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($stockUnits->isEmpty()) {
            return redirect()->back()->with('error', __('Tidak ada stok belum terjual yang tersedia untuk diekspor.'));
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
            
            $uploaderName = $unit->uploader->full_name ?? $unit->uploader->username ?? ($unit->seller->full_name ?? $unit->seller->username ?? 'Admin Utama');
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

    public function exportProductReportPdf(Request $request, $id)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $product = Product::with(['creator'])->findOrFail($id);

        $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
        $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();

        $stockUnits = \App\Models\StockUnit::with(['order.customer', 'uploader', 'seller'])
            ->where('product_id', $product->id)
            ->where('is_sold', true)
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereIn('status', ['delivered', 'paid', 'completed'])
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate])
                          ->orWhereBetween('delivered_at', [$startDate, $endDate]);
                    });
            })
            ->get()
            ->sortByDesc(function ($unit) {
                return $unit->order->created_at ?? $unit->created_at;
            })
            ->values();

        $seller = $product->creator;
        $sellerName = $seller ? ($seller->full_name ?? $seller->username) : 'Admin Utama';
        $sellerRole = $seller ? ucfirst($seller->role) : 'Admin';
        
        $totalSoldUnits = $stockUnits->count();
        $totalGrossRevenue = $totalSoldUnits * $product->price;

        $platformFeePercent = ($seller && $seller->role === 'seller') ? (int)($seller->platform_fee_percent ?? 10) : 0;
        $platformCommission = (int) round(($totalGrossRevenue * $platformFeePercent) / 100);
        $totalNetEarnings = $totalGrossRevenue - $platformCommission;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.products.pdf_report', compact(
            'product',
            'seller',
            'sellerName',
            'sellerRole',
            'startDate',
            'endDate',
            'stockUnits',
            'totalSoldUnits',
            'totalGrossRevenue',
            'platformFeePercent',
            'platformCommission',
            'totalNetEarnings'
        ))->setPaper('a4', 'portrait');

        $filename = 'laporan_penjualan_' . \Illuminate\Support\Str::slug($product->name) . '_' . $startDate->format('Ymd') . '-' . $endDate->format('Ymd') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function exportSellerReportPdf(Request $request, $id)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $seller = User::findOrFail($id);

        $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
        $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();

        // 1. Sold Stock Units for this seller in date range
        $soldStockUnits = \App\Models\StockUnit::with(['product', 'order.customer', 'uploader'])
            ->where('seller_id', $seller->id)
            ->where('is_sold', true)
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereIn('status', ['delivered', 'paid', 'completed'])
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate])
                          ->orWhereBetween('delivered_at', [$startDate, $endDate]);
                    });
            })
            ->get()
            ->sortByDesc(function ($unit) {
                return $unit->order->created_at ?? $unit->created_at;
            })
            ->values();

        $totalSoldUnits = $soldStockUnits->count();

        // Calculate Gross Revenue, Platform Fee, and Net Earnings per unit
        $platformFeePercent = (int)($seller->platform_fee_percent ?? 10);
        
        $totalGrossRevenue = 0;
        $totalPlatformCommission = 0;

        foreach ($soldStockUnits as $unit) {
            $unitPrice = (int)($unit->product->price ?? 0);
            $comm = (int) round(($unitPrice * $platformFeePercent) / 100);
            $net = $unitPrice - $comm;

            $unit->unit_price = $unitPrice;
            $unit->unit_commission = $comm;
            $unit->unit_net = $net;

            $totalGrossRevenue += $unitPrice;
            $totalPlatformCommission += $comm;
        }

        $totalNetEarnings = $totalGrossRevenue - $totalPlatformCommission;
        $averageOrderValue = $totalSoldUnits > 0 ? (int)round($totalGrossRevenue / $totalSoldUnits) : 0;

        // 2. Ready Stock Count for this seller
        $readyStockCount = \App\Models\StockUnit::where('seller_id', $seller->id)
            ->where('is_sold', false)
            ->where('stock_status', 'ready')
            ->count();

        // 3. Warranty Claims / Refunds count in period
        $refundedStockCount = \App\Models\StockUnit::where('seller_id', $seller->id)
            ->where('is_sold', true)
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereIn('status', ['cancelled', 'refunded'])
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate])
                          ->orWhereBetween('cancelled_at', [$startDate, $endDate]);
                    });
            })
            ->count();

        $refundRatePercent = ($totalSoldUnits + $refundedStockCount) > 0 
            ? round(($refundedStockCount / ($totalSoldUnits + $refundedStockCount)) * 100, 1) 
            : 0;

        // 4. Platform Contribution Percentage
        $totalPlatformGross = \App\Models\StockUnit::where('is_sold', true)
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->whereIn('status', ['delivered', 'paid', 'completed'])
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate])
                          ->orWhereBetween('delivered_at', [$startDate, $endDate]);
                    });
            })
            ->join('products', 'stock_units.product_id', '=', 'products.id')
            ->sum('products.price');

        $contributionPercentage = $totalPlatformGross > 0 
            ? round(($totalGrossRevenue / $totalPlatformGross) * 100, 1) 
            : 0;

        // 5. Top Performing Products for this seller
        $topProducts = $soldStockUnits->groupBy('product_id')->map(function ($group) use ($platformFeePercent) {
            $product = $group->first()->product;
            $qty = $group->count();
            $price = (int)($product->price ?? 0);
            $gross = $qty * $price;
            $comm = (int) round(($gross * $platformFeePercent) / 100);
            $net = $gross - $comm;

            return [
                'name' => $product->name ?? 'Produk Dihapus',
                'price' => $price,
                'qty' => $qty,
                'gross' => $gross,
                'commission' => $comm,
                'net' => $net,
            ];
        })->sortByDesc('gross')->take(5)->values();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.sellers.pdf_report', compact(
            'seller',
            'startDate',
            'endDate',
            'soldStockUnits',
            'totalSoldUnits',
            'totalGrossRevenue',
            'platformFeePercent',
            'totalPlatformCommission',
            'totalNetEarnings',
            'averageOrderValue',
            'readyStockCount',
            'refundedStockCount',
            'refundRatePercent',
            'contributionPercentage',
            'topProducts'
        ))->setPaper('a4', 'portrait');

        $sellerSlug = \Illuminate\Support\Str::slug($seller->full_name ?? $seller->username ?? 'seller');
        $filename = 'laporan_performa_seller_' . $sellerSlug . '_' . $startDate->format('Ymd') . '-' . $endDate->format('Ymd') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
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

        // Split raw text into individual stock blocks supporting line dividers & double-newlines
        $product = Product::findOrFail($request->product_id);
        $isVcc = (bool) $product->is_vcc;
        $vccServices = [];
        if ($isVcc) {
            $vccServices = (array) ($product->vcc_services ?? []);
            if (empty($vccServices)) {
                $vccServices = \App\Models\BotSetting::getVccServicesList();
            }
            if ($request->filled('vcc_services_target') && is_array($request->vcc_services_target)) {
                $filtered = array_intersect($vccServices, $request->vcc_services_target);
                if (!empty($filtered)) {
                    $vccServices = $filtered;
                }
            }
        }

        $blocks = $this->splitStockBlocks($request->raw_text);
        $count = 0;
        foreach ($blocks as $block) {
            if (!empty($block)) {
                if ($isVcc && !empty($vccServices)) {
                    $vccGroupId = (string) \Illuminate\Support\Str::uuid();
                    foreach ($vccServices as $service) {
                        \App\Models\StockUnit::create([
                            'product_id' => $request->product_id,
                            'raw_text' => $block,
                            'stock_status' => $request->stock_status,
                            'vcc_service' => $service,
                            'vcc_group_id' => $vccGroupId,
                            'is_sold' => false,
                            'available_at' => $availableAt,
                        ]);
                        $count++;
                    }
                } else {
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
        }
        if ($request->stock_status === 'ready' && $count > 0) {
            try {
                \App\Services\RestockAlertService::notifySubscribers($product, $count);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Restock alert notify error: ' . $e->getMessage());
            }
        }
        return redirect()->route('admin.stock.index')->with('success', __(":count stok berhasil ditambahkan.", ["count" => $count]));
    }

    public function updateStock(Request $request, $id)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'stock_status' => 'required|in:ready,awaiting_benefits,saved_for_verification',
            'raw_text' => 'required|string',
        ]);

        $stock = StockUnit::findOrFail($id);
        
        if ($stock->is_sold) {
            return redirect()->route('admin.stock.index')->with('error', __('Stok yang sudah terjual tidak dapat diedit.'));
        }

        // Only compute available_at if status has changed, or re-compute unconditionally since admin explicitly saves.
        // We re-compute unconditionally to ensure correctness.
        $availableAt = null;
        if ($request->stock_status === 'awaiting_benefits') {
            $hours = (int)(\App\Models\BotSetting::where('key', 'github_pack.awaiting_hours')->value('value') ?? 78);
            $availableAt = now()->addHours($hours);
        } elseif ($request->stock_status === 'saved_for_verification') {
            $hours = (int)(\App\Models\BotSetting::where('key', 'github_pack.save_hours')->value('value') ?? 80);
            $availableAt = now()->addHours($hours);
        }

        $stock->update([
            'product_id' => $request->product_id,
            'raw_text' => trim($request->raw_text),
            'stock_status' => $request->stock_status,
            'available_at' => $availableAt,
        ]);

        return redirect()->route('admin.stock.index')->with('success', __('Detail stok berhasil diperbarui.'));
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

        return back()->with('success', __('Status/Produk stok berhasil dipindahkan.'));
    }

    public function destroyStock($id)
    {
        $stock = StockUnit::findOrFail($id);

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_delete_stock',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'stock_unit',
            'entity_id' => $stock->id,
            'detail' => "Deleted stock ID: {$stock->id}, product_id: {$stock->product_id}",
            'created_at' => now(),
        ]);

        $stock->delete();
        return redirect()->route('admin.stock.index')->with('success', __('Stok berhasil dihapus.'));
    }

    public function checkoutStocks(Request $request)
    {
        $request->validate([
            'ids' => 'required|string',
            'customer_id' => 'nullable|exists:users,id',
        ]);

        $ids = json_decode($request->ids, true);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', __('Tidak ada stok terpilih.'));
        }

        $user = \Illuminate\Support\Facades\Auth::user();

        // Determine target customer (optional assignment)
        $targetUser = $user;
        if ($request->filled('customer_id')) {
            $targetUser = User::findOrFail($request->customer_id);
        } else {
            // Limit pending orders only when checkout is for admin themselves
            $pendingCount = Order::where('customer_id', $user->id)
                ->where('status', 'pending_payment')
                ->count();
            if ($pendingCount >= 2) {
                return back()->with('error', __('Anda memiliki terlalu banyak pesanan yang menunggu pembayaran. Selesaikan atau batalkan pesanan sebelumnya.'));
            }
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $stocks = StockUnit::with('product')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($stocks->count() !== count($ids)) {
                throw new \Exception(__('Beberapa stok tidak ditemukan.'));
            }

            $subtotal = 0;
            $itemsGroup = [];
            $reservedIds = [];

            foreach ($stocks as $stock) {
                if ($stock->is_sold || $stock->sold_order_id) {
                    throw new \Exception(__('Stok ID #:id sudah dibeli atau tidak tersedia.', ['id' => $stock->id]));
                }
                if (!in_array($stock->stock_status, ['ready', 'awaiting_benefits', 'saved_for_verification'])) {
                    throw new \Exception(__('Stok ID #:id memiliki status yang tidak valid untuk dicheckout.', ['id' => $stock->id]));
                }

                $product = $stock->product;
                if (!$product || $product->is_suspended) {
                    throw new \Exception(__('Produk untuk stok ID #:id tidak aktif.', ['id' => $stock->id]));
                }

                $subtotal += $product->price;
                
                if (!isset($itemsGroup[$product->id])) {
                    $itemsGroup[$product->id] = [
                        'product_id' => $product->id,
                        'price' => $product->price,
                        'quantity' => 0
                    ];
                }
                $itemsGroup[$product->id]['quantity']++;
                $reservedIds[] = $stock->id;
            }

            $uniqueCode = rand(1, 200);
            $totalAmount = $subtotal + $uniqueCode;
            
            $timestamp = now()->format('ymdHis');
            $randomHex = strtoupper(substr(md5(uniqid()), 0, 5));
            $orderRef = 'ORD' . $timestamp . $randomHex;

            $expiryMinutes = (int)(\App\Models\BotSetting::where('key', 'checkout_expiry_minutes')->value('value') ?? 15);
            $expiresAt = now()->addMinutes($expiryMinutes);

            $order = Order::create([
                'order_ref' => $orderRef,
                'customer_id' => $targetUser->id,
                'subtotal' => $subtotal,
                'unique_code' => $uniqueCode,
                'total_amount' => $totalAmount,
                'status' => 'pending_payment',
                'expires_at' => $expiresAt,
            ]);

            foreach ($itemsGroup as $item) {
                \App\Models\OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                ]);
            }

            \App\Models\Payment::create([
                'order_id' => $order->id,
                'payment_ref' => 'PAY-' . $orderRef,
                'expected_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            foreach ($stocks as $stock) {
                $stock->update([
                    'stock_status' => 'reserved_checkout',
                    'sold_order_id' => $order->id
                ]);
            }

            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'action' => 'order_create_specific_stock',
                'actor_id' => $user->id,
                'entity_type' => 'order',
                'entity_id' => $order->id,
                'detail' => \App\Models\AuditLog::maskSensitiveData("qty=" . count($reservedIds) . "; customer_id={$targetUser->id}; subtotal={$subtotal}; total={$totalAmount}; reserved_stock_ids=[" . implode(',', $reservedIds) . "]"),
                'created_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::commit();

            \App\Services\TelegramService::notifyAdminNewOrder($order);
            User::where('role', 'admin')->get()->each(function ($admin) use ($order) {
                $admin->notify(new \App\Notifications\OrderCreatedNotification($order));
            });

            // If assigned to another customer, notify customer
            if ($targetUser->id !== $user->id) {
                $targetUser->notify(new \App\Notifications\OrderCreatedNotification($order));
            }

            $successMsg = $targetUser->id !== $user->id 
                ? __("Pesanan berhasil dibuat dan ditugaskan ke customer :name (:ref).", ['name' => $targetUser->full_name ?? $targetUser->username, 'ref' => $orderRef])
                : __("Pesanan berhasil dibuat (:ref).", ['ref' => $orderRef]);

            return redirect()->route('checkout.success', ['order_ref' => $orderRef])
                ->with('checkout_new', true)
                ->with('success', $successMsg);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
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
            return back()->with('error', __('Tidak ada stok terpilih.'));
        }

        $stockUnits = StockUnit::whereIn('id', $ids)->where('is_sold', false)->get();
        if ($stockUnits->isEmpty()) {
            return back()->with('error', __('Stok terpilih tidak ditemukan atau sudah terjual.'));
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

        return back()->with('success', __(":count status/produk stok berhasil dipindahkan secara masal.", ["count" => $count]));
    }

    public function bulkDestroyStock(Request $request)
    {
        $request->validate([
            'ids' => 'required|string',
        ]);

        $ids = json_decode($request->ids, true);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', __('Tidak ada stok terpilih.'));
        }

        $count = StockUnit::whereIn('id', $ids)->where('is_sold', false)->delete();

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_bulk_delete_stock',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'stock_unit',
            'entity_id' => 0,
            'detail' => "Bulk deleted {$count} stock units (IDs: " . implode(',', $ids) . ")",
            'created_at' => now(),
        ]);

        return back()->with('success', __(":count stok berhasil dihapus secara masal.", ["count" => $count]));
    }

    // --- CRUD Orders ---
    public function updateOrder(Request $request, $id, \App\Services\OrderService $orderService)
    {
        $order = Order::findOrFail($id);
        $request->validate([
            'status' => 'required|in:pending_payment,paid,delivered,cancelled,expired'
        ]);

        $newStatus = $request->status;
        $oldStatus = $order->status;

        if ($newStatus !== $oldStatus) {
            try {
                if ($newStatus === 'cancelled') {
                    $orderService->cancelOrder($order, 'Dibatalkan oleh Admin (CRUD Website)', \Illuminate\Support\Facades\Auth::id());
                } elseif ($newStatus === 'expired') {
                    $orderService->cancelOrder($order, 'Kadaluarsa / Waktu Habis (CRUD Website)', \Illuminate\Support\Facades\Auth::id());
                    $order->status = 'expired';
                    $order->save();
                } elseif ($newStatus === 'delivered') {
                    $orderService->confirmPayment($order, \Illuminate\Support\Facades\Auth::id());
                } else {
                    $order->status = $newStatus;
                    $order->save();
                    \App\Services\TelegramService::updateAdminOrderMessage($order);
                }
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Gagal memperbarui status pesanan: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', __('Status pesanan berhasil diperbarui.'));
    }

    // --- CRUD Users ---
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $request->validate([
            'role' => 'required|in:admin,customer,seller',
            'wallet_balance' => 'required|integer|min:0',
            'platform_fee_percent' => 'required|integer|between:0,100',
            'commission_per_product' => 'nullable|boolean',
            'seller_save_hours' => 'required|integer|min:0',
            'allowed_tools' => 'nullable|array',
            'allowed_tools.*' => 'string|in:github_checker,gmail_checker,2fa_generator,rekber,reseller_api',
        ]);

        $oldRole = $user->role;
        $user->role = $request->role;
        $user->wallet_balance = $request->wallet_balance;
        $user->platform_fee_percent = $request->platform_fee_percent;
        $user->commission_per_product = $request->boolean('commission_per_product');
        $user->seller_save_hours = $request->seller_save_hours;
        $user->allowed_tools = in_array($request->role, ['seller', 'customer']) ? ($request->allowed_tools ?? []) : null;
        $user->save();

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_update_user',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'detail' => "Updated user: {$user->username}; role: {$oldRole}->{$request->role}; balance: {$request->wallet_balance}; comm_per_product: " . ($user->commission_per_product ? 'yes' : 'no'),
            'created_at' => now(),
        ]);

        return redirect()->back(302, [], route('admin.users.index'))
            ->with('success', __('Informasi pengguna berhasil diperbarui.'));
    }

    public function exportUsers(Request $request)
    {
        // Always require admin password verification before exporting user database
        $request->validate(['password' => 'required|string']);
        if (!\Illuminate\Support\Facades\Hash::check($request->password, \Illuminate\Support\Facades\Auth::user()->password)) {
            return redirect()->back()->with('error', __('Password salah. Ekspor data dibatalkan.'));
        }

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_export_users',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'user',
            'entity_id' => 0,
            'detail' => 'Admin exported users data',
            'created_at' => now(),
        ]);

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

        if ($request->filled('role')) {
            if ($request->role === 'suspended') {
                $query->where('is_suspended', true);
            } else {
                $query->where('role', $request->role);
            }
        }

        $users = $query->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Daftar Pengguna');
        $sheet->setShowGridlines(true);

        // Header style
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0D6EFD'],
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

        // Data border style
        $dataBorderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'E0E0E0'],
                ],
            ],
        ];

        $headers = ['No', 'ID Telegram', 'Username', 'Nama Lengkap', 'Email', 'Role', 'Status', 'Tanggal Bergabung'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $row = 2;
        $no = 1;
        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $user->telegram_id ?? '-');
            $sheet->setCellValue('C' . $row, $user->username ? '@' . $user->username : '-');
            $sheet->setCellValue('D' . $row, $user->full_name ?? '-');
            $sheet->setCellValue('E' . $row, $user->email ?? '-');
            $sheet->setCellValue('F' . $row, ucfirst($user->role));
            $sheet->setCellValue('G' . $row, $user->is_suspended ? 'Suspended' : 'Aktif');
            $sheet->setCellValue('H' . $row, $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '-');

            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($dataBorderStyle);
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        foreach (range('A', 'H') as $colChar) {
            $sheet->getColumnDimension($colChar)->setAutoSize(true);
        }

        $filename = 'daftar_pengguna_' . now()->format('Y-m-d_His') . '.xlsx';

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
    // NOTIFICATION: LOGINS (Unified Security Hub)
    // ==========================================
    public function logins(Request $request)
    {
        $data = app(\App\Services\SecurityHubService::class)->buildHubData($request, 'peta');
        return view('admin.security.hub', $data);
    }

    public function blockIp(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'duration' => 'required|integer|in:1,7,30,365'
        ]);
        $ip = $request->ip_address;
        $duration = (int)$request->duration;
        
        $durationText = '';
        if ($duration === 1) {
            $expire = now()->addDay();
            $durationText = '1 hari';
        } elseif ($duration === 7) {
            $expire = now()->addDays(7);
            $durationText = '7 hari';
        } elseif ($duration === 30) {
            $expire = now()->addDays(30);
            $durationText = '30 hari';
        } elseif ($duration === 365) {
            $expire = now()->addYear();
            $durationText = '1 tahun';
        } else {
            $expire = now()->addDay();
            $durationText = '1 hari';
        }
        
        \Illuminate\Support\Facades\Cache::put('blocked_ip:' . $ip, true, $expire);
        
        return back()->with('success', __("IP Address :ip telah diblokir selama :durationText.", ["ip" => $ip, "durationText" => $durationText]));
    }

    public function unblockIp(Request $request)
    {
        $request->validate(['ip_address' => 'required|ip']);
        $ip = $request->ip_address;
        
        \Illuminate\Support\Facades\Cache::forget('blocked_ip:' . $ip);
        
        return back()->with('success', __("Blokir IP Address :ip telah dibuka.", ["ip" => $ip]));
    }

    public function blockDevice(Request $request)
    {
        $request->validate([
            'device_fingerprint' => 'nullable|string|max:64',
            'device_id' => 'nullable|string|max:64',
            'duration' => 'required|integer|in:1,7,30,365'
        ]);

        $fp = $request->device_fingerprint;
        $deviceId = $request->device_id;
        $duration = (int)$request->duration;

        $expire = now()->addDays($duration);
        if ($duration === 365) {
            $expire = now()->addYear();
        }

        if ($fp) {
            \Illuminate\Support\Facades\Cache::put('blocked_device_fp:' . $fp, true, $expire);
        }
        if ($deviceId) {
            \Illuminate\Support\Facades\Cache::put('blocked_device_id:' . $deviceId, true, $expire);
        }

        return back()->with('success', __('Perangkat berhasil diblokir.'));
    }

    public function unblockDevice(Request $request)
    {
        $request->validate([
            'device_fingerprint' => 'nullable|string|max:64',
            'device_id' => 'nullable|string|max:64',
            'ip_address' => 'nullable|ip',
        ]);

        if ($request->device_fingerprint) {
            \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:' . $request->device_fingerprint);
        }
        if ($request->device_id) {
            \Illuminate\Support\Facades\Cache::forget('blocked_device_id:' . $request->device_id);
        }

        // Also clear fallback User-Agent fingerprint if request has User-Agent
        if ($request->header('User-Agent')) {
            $fallbackFp = 'fp_ua_' . substr(md5($request->header('User-Agent')), 0, 16);
            \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:' . $fallbackFp);
        }

        // If IP is passed, clear all device fingerprints logged under this IP
        if ($request->ip_address) {
            $logs = \App\Models\LoginLog::where('ip_address', $request->ip_address)->get();
            foreach ($logs as $l) {
                if ($l->device_fingerprint) {
                    \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:' . $l->device_fingerprint);
                }
                if ($l->device_id) {
                    \Illuminate\Support\Facades\Cache::forget('blocked_device_id:' . $l->device_id);
                }
                if ($l->user_agent) {
                    \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:fp_ua_' . substr(md5($l->user_agent), 0, 16));
                }
            }
        }

        return back()->with('success', __('Blokir perangkat telah dibuka.'));
    }

    public function unifiedBlock(Request $request)
    {
        $request->validate([
            'ip_address' => 'nullable|ip',
            'device_fingerprint' => 'nullable|string|max:64',
            'device_id' => 'nullable|string|max:64',
            'user_id' => 'nullable|exists:users,id',
            'block_ip' => 'nullable|boolean',
            'block_device' => 'nullable|boolean',
            'suspend_account' => 'nullable|boolean',
            'duration' => 'required|integer|in:1,7,30,365'
        ]);

        $duration = (int)$request->duration;
        $expire = now()->addDays($duration);
        if ($duration === 365) {
            $expire = now()->addYear();
        }

        $actionsDone = [];

        if ($request->boolean('block_ip') && $request->ip_address) {
            \Illuminate\Support\Facades\Cache::put('blocked_ip:' . $request->ip_address, true, $expire);
            $actionsDone[] = "IP {$request->ip_address}";
        }

        if ($request->boolean('block_device')) {
            if ($request->device_fingerprint) {
                \Illuminate\Support\Facades\Cache::put('blocked_device_fp:' . $request->device_fingerprint, true, $expire);
            }
            if ($request->device_id) {
                \Illuminate\Support\Facades\Cache::put('blocked_device_id:' . $request->device_id, true, $expire);
            }

            $actionsDone[] = "Perangkat (Fingerprint/ID)";
        }

        if ($request->boolean('suspend_account') && $request->user_id) {
            if ((int)$request->user_id === (int)auth()->id()) {
                return back()->with('error', __('Anda tidak dapat menangguhkan akun Admin Anda sendiri.'));
            }
            $user = User::find($request->user_id);
            if ($user && !$user->is_suspended) {
                $user->is_suspended = true;
                $user->suspension_reason = 'Diblokir oleh Admin dari Riwayat Login.';
                $user->save();
                $actionsDone[] = "Akun ({$user->username})";
            }
        }

        $msg = empty($actionsDone) 
            ? __('Pilih minimal satu opsi yang ingin diblokir.') 
            : __('Tindakan pemblokiran berhasil diterapkan untuk: ') . implode(', ', $actionsDone);

        return back()->with(empty($actionsDone) ? 'error' : 'success', $msg);
    }

    public function unifiedUnblock(Request $request)
    {
        $request->validate([
            'ip_address' => 'nullable|ip',
            'device_fingerprint' => 'nullable|string|max:64',
            'device_id' => 'nullable|string|max:64',
            'user_id' => 'nullable|exists:users,id',
            'unblock_ip' => 'nullable|boolean',
            'unblock_device' => 'nullable|boolean',
            'unsuspend_account' => 'nullable|boolean',
        ]);

        $actionsDone = [];

        if ($request->boolean('unblock_ip') && $request->ip_address) {
            \Illuminate\Support\Facades\Cache::forget('blocked_ip:' . $request->ip_address);
            $actionsDone[] = "IP {$request->ip_address}";
        }

        if ($request->boolean('unblock_device')) {
            if ($request->device_fingerprint) {
                \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:' . $request->device_fingerprint);
            }
            if ($request->device_id) {
                \Illuminate\Support\Facades\Cache::forget('blocked_device_id:' . $request->device_id);
            }

            // Also clear request's own User-Agent fallback
            if ($request->header('User-Agent')) {
                $fallbackFp = 'fp_ua_' . substr(md5($request->header('User-Agent')), 0, 16);
                \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:' . $fallbackFp);
            }

            // Clear all fingerprints logged under this IP or User ID
            $query = \App\Models\LoginLog::query();
            if ($request->ip_address && $request->user_id) {
                $query->where('ip_address', $request->ip_address)->orWhere('user_id', $request->user_id);
            } elseif ($request->ip_address) {
                $query->where('ip_address', $request->ip_address);
            } elseif ($request->user_id) {
                $query->where('user_id', $request->user_id);
            }
            $logs = $query->get();
            foreach ($logs as $l) {
                if ($l->device_fingerprint) {
                    \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:' . $l->device_fingerprint);
                }
                if ($l->device_id) {
                    \Illuminate\Support\Facades\Cache::forget('blocked_device_id:' . $l->device_id);
                }
                if ($l->user_agent) {
                    \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:fp_ua_' . substr(md5($l->user_agent), 0, 16));
                }
            }

            // Also clear request client cookies if present
            if ($request->cookie('_sec_device_fp')) {
                \Illuminate\Support\Facades\Cache::forget('blocked_device_fp:' . $request->cookie('_sec_device_fp'));
            }
            if ($request->cookie('_sec_device_id')) {
                \Illuminate\Support\Facades\Cache::forget('blocked_device_id:' . $request->cookie('_sec_device_id'));
            }

            $actionsDone[] = "Perangkat";
        }

        if ($request->boolean('unsuspend_account') && $request->user_id) {
            $user = User::find($request->user_id);
            if ($user && $user->is_suspended) {
                $user->is_suspended = false;
                $user->suspension_reason = null;
                $user->save();
                $actionsDone[] = "Akun ({$user->username})";
            }
        }

        $msg = empty($actionsDone) 
            ? __('Pilih minimal satu opsi yang ingin dibuka blokirnya.') 
            : __('Buka blokir berhasil dilakukan untuk: ') . implode(', ', $actionsDone);

        return back()->with(empty($actionsDone) ? 'error' : 'success', $msg);
    }

    public function unblockAllDevices(Request $request)
    {
        \Illuminate\Support\Facades\Cache::flush();

        return back()->with('success', __('Seluruh pemblokiran IP, Perangkat, dan Cache sistem berhasil direset/dibuka secara total.'));
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

    public function showComplaint($id)
    {
        $complaint = \App\Models\ComplaintCase::with(['customer', 'product', 'seller', 'orderItem.product.creator', 'order.items.product.creator', 'order.stockUnits.seller'])->findOrFail($id);

        $targetProduct = $complaint->resolveTargetProduct();
        $targetSeller = $complaint->resolveTargetSeller();
        $productId = $targetProduct?->id ?? 0;
        $sellerId = $targetSeller?->id ?? 0;
        $refundableAmount = $complaint->calculateRefundableAmount();

        $availableStocks = \App\Models\StockUnit::where('seller_id', $sellerId)
            ->where('product_id', $productId)
            ->where('is_sold', false)
            ->whereNull('sold_order_id')
            ->where(function($q) {
                $q->where('stock_status', 'ready')->orWhereNull('stock_status');
            })
            ->get();

        $availableStockCount = $availableStocks->count();
        $randomStock = $availableStockCount > 0 ? $availableStocks->random() : null;

        return view('admin.complaints.show', compact('complaint', 'availableStockCount', 'randomStock', 'targetProduct', 'targetSeller', 'refundableAmount'));
    }

    public function updateComplaintStatus(Request $request, $id)
    {
        $complaint = \App\Models\ComplaintCase::with(['order.items.product', 'orderItem.product', 'product', 'seller'])->findOrFail($id);

        $request->validate([
            'status' => 'required|string|in:review,done,rejected,refund,replacement,refund_wallet,refund_external',
            'rejected_reason' => 'required_if:status,rejected|nullable|string|max:500',
            'refund_note' => 'required_if:status,done,refund,refund_external|nullable|string|max:500',
            'replacement_data' => 'nullable|string',
            'replacement_stock_id' => 'nullable|integer',
            'cancel_order' => 'nullable|boolean',
        ], [
            'rejected_reason.required_if' => 'Alasan penolakan wajib diisi jika status ditolak.',
            'refund_note.required_if' => 'Catatan penyelesaian/refund wajib diisi.',
        ]);

        if ($request->status === 'replacement') {
            if (!$request->filled('replacement_stock_id') && !$request->filled('replacement_data')) {
                return back()->withErrors(['replacement_data' => 'Data akun pengganti wajib diisi jika tidak ada stok otomatis.']);
            }
        }

        if (in_array($request->status, ['refund_wallet', 'refund_external'], true)) {
            $refundError = null;
            $refundAmount = 0;
            $isWalletRefund = $request->status === 'refund_wallet';
            $shouldCancelOrder = $request->boolean('cancel_order');

            \Illuminate\Support\Facades\DB::transaction(function () use ($id, $request, $isWalletRefund, $shouldCancelOrder, &$refundError, &$refundAmount, &$complaint) {
                $locked = \App\Models\ComplaintCase::with(['order.items.product', 'orderItem.product', 'product', 'seller'])
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($isWalletRefund && $locked->refunded_to_wallet_at !== null) {
                    $refundError = __('Komplain ini sudah pernah di-refund ke Dompet Saldo pelanggan.');
                    return;
                }

                $order = $locked->order;
                if (!$order) {
                    $refundError = __('Pesanan terkait komplain tidak ditemukan.');
                    return;
                }

                $refundAmount = $locked->calculateRefundableAmount();
                $targetProduct = $locked->resolveTargetProduct();
                $targetSeller = $locked->resolveTargetSeller();

                if ($isWalletRefund && $refundAmount > 0) {
                    app(\App\Services\WalletService::class)->credit(
                        (int) $locked->customer_id,
                        $refundAmount,
                        'refund',
                        $locked->complaint_ref ?: ('CMP-' . $locked->id),
                        __('Auto-Refund komplain :product (pesanan #:ref)', [
                            'product' => $targetProduct?->name ?? __('Produk'),
                            'ref' => $order->order_ref,
                        ]),
                        [
                            'complaint_id' => $locked->id,
                            'order_id' => $order->id,
                            'order_item_id' => $locked->order_item_id,
                            'product_id' => $targetProduct?->id,
                        ]
                    );
                }

                // Cancel held funds scoped to the complained product/seller (or whole order for legacy unscoped complaints)
                $heldQuery = \Illuminate\Support\Facades\DB::table('held_funds')
                    ->where('order_id', $order->id)
                    ->where('status', 'held');
                if ($targetProduct) {
                    $heldQuery->where('product_id', $targetProduct->id);
                }
                if ($targetSeller) {
                    $heldQuery->where('seller_id', $targetSeller->id);
                }
                $heldQuery->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

                // Optional order cancellation (only safe when single-item order or legacy order-wide complaint)
                if ($shouldCancelOrder && (!$order->isMultiSeller() && $order->items->count() <= 1)) {
                    $order->status = 'cancelled';
                    $order->cancelled_at = now();
                    $order->cancel_reason = $isWalletRefund
                        ? 'Refund ke Dompet & Dibatalkan via Komplain #' . $locked->complaint_ref
                        : 'Refund Manual (Luar Website) & Dibatalkan via Komplain #' . $locked->complaint_ref;
                    $order->save();
                }

                $customNote = trim((string) $request->input('refund_note', ''));
                if ($isWalletRefund) {
                    $noteText = __('Dana sebesar Rp:amount telah dikembalikan otomatis ke Dompet Saldo Anda.', [
                        'amount' => number_format($refundAmount, 0, ',', '.'),
                    ]);
                } else {
                    $noteText = __('Pengembalian dana (Rp:amount) telah diproses secara manual di luar website oleh Admin (tanpa penambahan saldo dompet).', [
                        'amount' => number_format($refundAmount, 0, ',', '.'),
                    ]);
                }
                if ($customNote !== '') {
                    $noteText .= "\n" . $customNote;
                }

                $updatePayload = [
                    'status' => 'done',
                    'refund_note' => $noteText,
                    'closed_at' => now(),
                ];
                if ($isWalletRefund) {
                    $updatePayload['refunded_to_wallet_at'] = now();
                } else {
                    $updatePayload['refund_transferred_at'] = now();
                }

                $locked->update($updatePayload);
                $complaint = $locked;
            });

            if ($refundError !== null) {
                return redirect()->back()->with('error', $refundError);
            }

            \App\Services\TelegramService::notifyComplaintStatusUpdate($complaint);
            $customer = \App\Models\User::find($complaint->customer_id);
            if ($customer) {
                $customer->notify(new \App\Notifications\ComplaintNotification($complaint, 'status_update'));
            }

            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'action' => $isWalletRefund ? 'complaint_refund_wallet' : 'complaint_refund_external',
                'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                'entity_type' => 'complaint_case',
                'entity_id' => $complaint->id,
                'detail' => ($isWalletRefund ? "refund_wallet={$refundAmount}" : "refund_external={$refundAmount}") . "; ref={$complaint->complaint_ref}; cancel_order=" . ($shouldCancelOrder ? '1' : '0'),
                'created_at' => now(),
            ]);

            if ($isWalletRefund) {
                return redirect()->back()->with('success', __('Komplain diselesaikan! Auto-refund Rp:amount berhasil masuk ke Dompet Saldo pelanggan.', [
                    'amount' => number_format($refundAmount, 0, ',', '.'),
                ]));
            }

            return redirect()->back()->with('success', __('Komplain diselesaikan sebagai Refund Manual di Luar Website (saldo dompet tidak ditambah).'));
        }

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
            $updateData['refund_note'] = "Permintaan refund: " . $request->refund_note;
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

        // Notify the customer in the web app
        $customer = \App\Models\User::find($complaint->customer_id);
        if ($customer) {
            $customer->notify(new \App\Notifications\ComplaintNotification($complaint, 'status_update'));
        }

        // Put an audit log entry
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'complaint_update_status',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'complaint_case',
            'entity_id' => $complaint->id,
            'detail' => "status={$request->status}; ref={$complaint->complaint_ref}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.complaints.index')->with('success', __('Status komplain berhasil diperbarui.'));
    }

    // ==========================================
    // BROADCAST
    // ==========================================
    public function broadcast()
    {
        $broadcastHistory = \App\Models\BroadcastJob::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.broadcast.index', compact('broadcastHistory'));
    }

    public function startBroadcast(Request $request)
    {
        $request->validate([
            'message' => 'nullable|string',
            'media_file' => 'nullable|file|max:51200', // max 50MB
            'enable_read_button' => 'nullable|boolean',
            'cta_label' => 'nullable|string|max:80',
            'cta_open_mode' => 'nullable|in:miniapp,browser',
            'cta_url' => [
                'nullable',
                'string',
                'max:500',
                function ($attribute, $value, $fail) {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '') {
                        return;
                    }
                    $isRelative = str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '//');
                    $isHttp = (bool) preg_match('/^https?:\/\/.+/i', $trimmed);
                    if (!$isRelative && !$isHttp) {
                        $fail(__('URL CTA harus berupa path internal diawali / (misal: /catalog) atau tautan https://.'));
                    }
                },
            ],
        ]);

        if (empty($request->message) && !$request->hasFile('media_file') && empty($request->existing_media_path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Harap isi pesan teks atau pilih file untuk broadcast.'
            ], 422);
        }

        $targets = \App\Models\User::whereNotNull('telegram_id')
            ->where('role', 'customer')
            ->pluck('telegram_id')
            ->toArray();

        if (empty($targets)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada pelanggan dengan Telegram ID untuk dikirimi broadcast.'
            ], 422);
        }

        $mediaType = null;
        $mediaPath = null;
        if ($request->hasFile('media_file')) {
             $file = $request->file('media_file');
             $ext = strtolower($file->getClientOriginalExtension());
             if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                 $mediaType = 'photo';
             } elseif (in_array($ext, ['mp4'])) {
                 $mediaType = 'video';
             } else {
                 $mediaType = 'document';
             }
             $filename = 'broadcast_' . time() . '.' . $ext;
             $mediaPath = $file->storeAs('broadcasts', $filename, 'public');
        } elseif ($request->filled('existing_media_path')) {
            $mediaPath = $request->existing_media_path;
            $mediaType = $request->existing_media_type;
        }

        $enableReadButton = $request->has('enable_read_button')
            ? $request->boolean('enable_read_button')
            : true;
        $ctaUrl = trim((string) $request->input('cta_url', '')) ?: null;
        $ctaLabel = $ctaUrl ? (trim((string) $request->input('cta_label', '')) ?: '🔗 Buka Tautan') : null;
        $ctaOpenMode = $request->input('cta_open_mode');
        if (!in_array($ctaOpenMode, ['miniapp', 'browser'], true)) {
            $ctaOpenMode = ($ctaUrl && str_starts_with($ctaUrl, '/')) ? 'miniapp' : 'browser';
        }

        $allUsers = \App\Models\User::all();

        // Create the background job record
        $job = \App\Models\BroadcastJob::create([
            'message' => $request->message ?? '',
            'media_type' => $mediaType,
            'media_path' => $mediaPath,
            'total_targets' => count($targets),
            'web_target_count' => $allUsers->count(),
            'sent_count' => 0,
            'failed_count' => 0,
            'read_count' => 0,
            'web_read_count' => 0,
            'telegram_read_count' => 0,
            'status' => 'pending',
            'admin_id' => \Illuminate\Support\Facades\Auth::id(),
            'is_read' => false,
            'enable_read_button' => $enableReadButton,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
            'cta_open_mode' => $ctaOpenMode,
        ]);

        // Send Website Announcement Notification to all registered users
        try {
            if ($allUsers->isNotEmpty()) {
                $broadcastNotification = new \App\Notifications\BroadcastAnnouncementNotification(
                    $request->message ?? 'Ada pengumuman baru dari Admin.',
                    $request->title ?? 'Pengumuman Penting dari Admin',
                    $mediaPath,
                    $mediaType,
                    (int) $job->id
                );
                \Illuminate\Support\Facades\Notification::send($allUsers, $broadcastNotification);
            }
        } catch (\Throwable $e) {
            \Log::warning("Gagal mengirim notifikasi web broadcast: " . $e->getMessage());
        }

        // 1. Try background CLI execution
        $artisan = base_path('artisan');
        $phpFinder = new \Symfony\Component\Process\PhpExecutableFinder();
        $php = $phpFinder->find(false);
        if (!$php) {
            $php = 'php';
        }

        $logFile = storage_path('logs/broadcast_command_' . $job->id . '.log');

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows background execution
                pclose(popen("start /B " . escapeshellarg($php) . " " . escapeshellarg($artisan) . " broadcast:run {$job->id} > " . escapeshellarg($logFile) . " 2>&1", "r"));
            } else {
                // Linux/Unix background execution
                exec(escapeshellarg($php) . " " . escapeshellarg($artisan) . " broadcast:run {$job->id} > " . escapeshellarg($logFile) . " 2>&1 &");
            }
        } catch (\Exception $e) {
            \Log::warning("Gagal memulai Artisan command untuk broadcast: " . $e->getMessage());
        }

        // 2. Trigger HTTP loopback fallback
        // We generate a signed URL and ping it asynchronously with a 1-second timeout
        $token = hash_hmac('sha256', $job->id, config('app.key'));
        $url = route('admin.broadcast.run-bg', ['jobId' => $job->id, 'token' => $token]);

        try {
            \Illuminate\Support\Facades\Http::timeout(1)
                ->connectTimeout(1)
                ->withoutVerifying()
                ->get($url);
        } catch (\Exception $e) {
            // Timeout is expected and indicates the loopback was triggered successfully
        }

        return response()->json([
            'status' => 'success',
            'job_id' => $job->id,
            'total' => $job->total_targets
        ]);
    }

    public function resendBroadcast(Request $request, $jobId)
    {
        $oldJob = \App\Models\BroadcastJob::findOrFail($jobId);

        $request->merge([
            'message' => $oldJob->message,
            'existing_media_path' => $oldJob->media_path,
            'existing_media_type' => $oldJob->media_type,
            'enable_read_button' => $oldJob->enable_read_button,
            'cta_label' => $oldJob->cta_label,
            'cta_url' => $oldJob->cta_url,
            'cta_open_mode' => $oldJob->cta_open_mode ?: 'miniapp',
        ]);

        return $this->startBroadcast($request);
    }

    public function runBroadcastBackground(Request $request, $jobId)
    {
        $expectedToken = hash_hmac('sha256', $jobId, config('app.key'));
        if ($request->query('token') !== $expectedToken) {
            abort(403, 'Unauthorized');
        }

        // Run the broadcast logic here in the background
        ignore_user_abort(true);
        set_time_limit(0);

        $result = app(\App\Services\BroadcastDeliveryService::class)->deliver((int) $jobId);

        if ($result['status'] === 'not_found') {
            return response()->json(['status' => 'not_found'], 404);
        }

        if ($result['status'] === 'already_started') {
            return response()->json(['status' => 'already_started']);
        }

        if ($result['status'] === 'failed') {
            return response()->json(['status' => 'failed', 'reason' => $result['reason'] ?? 'failed'], 422);
        }

        return response()->json(['status' => 'success']);
    }

    public function getBroadcastAnalytics($jobId, \App\Services\BroadcastReadAnalyticsService $analytics)
    {
        $job = \App\Models\BroadcastJob::findOrFail($jobId);

        if ($job->status === 'completed' && $job->last_read_calculated_at === null) {
            $job = $analytics->recalculateJob($job);
        }

        return response()->json([
            'status' => 'success',
            'analytics' => $analytics->buildAnalyticsPayload($job),
        ]);
    }

    public function recalculateBroadcastAnalytics($jobId, \App\Services\BroadcastReadAnalyticsService $analytics)
    {
        $job = \App\Models\BroadcastJob::findOrFail($jobId);
        $job = $analytics->recalculateJob($job);

        return response()->json([
            'status' => 'success',
            'analytics' => $analytics->buildAnalyticsPayload($job),
        ]);
    }

    public function exportBroadcastFailures(Request $request, $jobId)
    {
        $job = \App\Models\BroadcastJob::findOrFail($jobId);
        $category = trim((string) $request->query('category', ''));
        $tz = \App\Support\SystemTimezone::active();

        $query = \App\Models\BroadcastFailure::where('broadcast_job_id', $job->id)
            ->orderBy('id');

        if ($category !== '' && $category !== 'all') {
            $query->where('error_category', $category);
        }

        $filename = sprintf('broadcast-%d-failures%s.csv', $job->id, $category && $category !== 'all' ? '-' . preg_replace('/[^a-z0-9_-]/i', '', $category) : '');

        $sanitizeCsv = static function ($value): string {
            $str = (string) $value;
            if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $str;
            }
            return $str;
        };

        return response()->streamDownload(function () use ($query, $tz, $sanitizeCsv) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('Tanggal'),
                __('Penerima'),
                __('Telegram ID'),
                __('Kategori'),
                __('Kode Error'),
                __('Pesan Telegram'),
            ]);

            $query->chunk(500, function ($rows) use ($handle, $tz, $sanitizeCsv) {
                foreach ($rows as $row) {
                    $meta = \App\Models\BroadcastFailure::categoryMeta((string) $row->error_category);
                    fputcsv($handle, [
                        $row->created_at ? $row->created_at->copy()->timezone($tz)->format('Y-m-d H:i:s') : '-',
                        $sanitizeCsv($row->recipient_name ?: '-'),
                        $sanitizeCsv($row->telegram_id ?: '-'),
                        $sanitizeCsv($meta['label']),
                        $row->error_code !== null ? (string) $row->error_code : '-',
                        $sanitizeCsv($row->error_message),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function trackBroadcastClick(Request $request, $jobId, $userId, $sig, \App\Services\BroadcastReadAnalyticsService $analytics)
    {
        $jobId = (int) $jobId;
        $userId = (int) $userId;

        if (!$analytics->verifyTrackSignature($jobId, $userId, (string) $sig)) {
            abort(403, 'Invalid tracking signature.');
        }

        $job = \App\Models\BroadcastJob::findOrFail($jobId);
        $user = \App\Models\User::find($userId);

        $analytics->recordRead(
            $jobId,
            $user ? (int) $user->id : null,
            $user && $user->telegram_id ? (string) $user->telegram_id : null,
            'telegram_link'
        );

        $targetUrl = trim((string) $job->cta_url);
        if ($targetUrl === '') {
            return redirect()->route('dashboard');
        }

        if (str_starts_with($targetUrl, '/') && !str_starts_with($targetUrl, '//')) {
            return redirect($targetUrl);
        }

        if (!preg_match('/^https?:\/\//i', $targetUrl)) {
            return redirect()->route('dashboard');
        }

        return redirect()->away($targetUrl);
    }

    public function suggestBroadcastCtaUrls(Request $request)
    {
        $rawQuery = trim((string) $request->query('q', ''));
        $lowerQuery = strtolower($rawQuery);
        $suggestions = [];

        $staticPages = [
            ['url' => '/dashboard', 'title' => __('Dashboard Utama'), 'subtitle' => __('Beranda & promo toko'), 'icon' => 'fas fa-home', 'category' => 'page', 'suggested_label' => __('Buka Dashboard')],
            ['url' => '/catalog', 'title' => __('Katalog Produk'), 'subtitle' => __('Daftar semua produk tersedia'), 'icon' => 'fas fa-store', 'category' => 'page', 'suggested_label' => __('Lihat Katalog')],
            ['url' => '/wallet', 'title' => __('Saldo & Top Up Wallet'), 'subtitle' => __('Cek saldo dan isi ulang'), 'icon' => 'fas fa-wallet', 'category' => 'page', 'suggested_label' => __('Cek Wallet')],
            ['url' => '/orders', 'title' => __('Riwayat Pesanan'), 'subtitle' => __('Pantau status pesanan pelanggan'), 'icon' => 'fas fa-box-open', 'category' => 'page', 'suggested_label' => __('Lihat Pesanan')],
            ['url' => '/escrow', 'title' => __('Rekening Bersama (Rekber)'), 'subtitle' => __('Transaksi escrow aman'), 'icon' => 'fas fa-shield-halved', 'category' => 'page', 'suggested_label' => __('Buka Rekber')],
            ['url' => '/notifications', 'title' => __('Pusat Notifikasi'), 'subtitle' => __('Semua pengumuman & info akun'), 'icon' => 'fas fa-bell', 'category' => 'page', 'suggested_label' => __('Lihat Notifikasi')],
            ['url' => '/profile', 'title' => __('Profil & Keamanan'), 'subtitle' => __('Pengaturan akun & 2FA'), 'icon' => 'fas fa-user-cog', 'category' => 'page', 'suggested_label' => __('Buka Profil')],
        ];

        $isCatalogProductQuery = str_starts_with($lowerQuery, '/catalog/');
        $isSellerQuery = str_starts_with($lowerQuery, '/sellers/');

        // 1. Static Mini App pages (unless user is explicitly drilling into /catalog/ or /sellers/)
        if (!$isCatalogProductQuery && !$isSellerQuery) {
            foreach ($staticPages as $page) {
                if (
                    $lowerQuery === '' ||
                    str_contains(strtolower($page['url']), $lowerQuery) ||
                    str_contains(strtolower($page['title']), $lowerQuery) ||
                    str_contains(strtolower($page['subtitle']), $lowerQuery)
                ) {
                    $suggestions[] = $page;
                }
            }
        }

        // 2. Smart Product Predictions: triggered when typing `/catalog/...`, `/cat`, or any product name/ID
        $productSearch = null;
        if ($isCatalogProductQuery) {
            $productSearch = trim(substr($rawQuery, strlen('/catalog/')));
        } elseif ($lowerQuery !== '' && !str_starts_with($lowerQuery, 'http://') && !str_starts_with($lowerQuery, 'https://') && !$isSellerQuery) {
            $productSearch = ltrim($rawQuery, '/');
        }

        if ($productSearch !== null) {
            $productQuery = \App\Models\Product::query()
                ->where(function ($q) {
                    $q->where('is_suspended', false)->orWhereNull('is_suspended');
                });

            if ($productSearch !== '') {
                $productQuery->where(function ($q) use ($productSearch) {
                    $q->where('name', 'like', '%' . $productSearch . '%');
                    if (ctype_digit($productSearch)) {
                        $q->orWhere('id', (int) $productSearch)
                          ->orWhereRaw('CAST(id AS CHAR) LIKE ?', [$productSearch . '%']);
                    }
                });
            }

            $products = $productQuery->orderBy('id', 'asc')->limit(12)->get(['id', 'name', 'price']);
            foreach ($products as $product) {
                $priceFormatted = 'Rp ' . number_format((int) $product->price, 0, ',', '.');
                $suggestions[] = [
                    'url' => '/catalog/' . $product->id,
                    'title' => '#' . $product->id . ' — ' . $product->name,
                    'subtitle' => __('Produk') . ' • ' . $priceFormatted,
                    'icon' => 'fas fa-tag',
                    'category' => 'product',
                    'suggested_label' => '🛍️ ' . \Illuminate\Support\Str::limit($product->name, 26),
                ];
            }
        }

        // 3. Smart Seller Predictions when typing `/sellers/...`
        if ($isSellerQuery) {
            $sellerSearch = trim(substr($rawQuery, strlen('/sellers/')));
            $sellerQuery = \App\Models\User::where('role', 'seller');
            if ($sellerSearch !== '') {
                $sellerQuery->where(function ($q) use ($sellerSearch) {
                    $q->where('full_name', 'like', '%' . $sellerSearch . '%')
                      ->orWhere('username', 'like', '%' . $sellerSearch . '%');
                    if (ctype_digit($sellerSearch)) {
                        $q->orWhere('id', (int) $sellerSearch);
                    }
                });
            }
            $sellers = $sellerQuery->orderBy('id', 'asc')->limit(10)->get(['id', 'full_name', 'username']);
            foreach ($sellers as $seller) {
                $displayName = $seller->full_name ?: ($seller->username ?: 'Seller #' . $seller->id);
                $suggestions[] = [
                    'url' => '/sellers/' . $seller->id,
                    'title' => '#' . $seller->id . ' — ' . $displayName,
                    'subtitle' => __('Toko Penjual') . ($seller->username ? ' (@' . $seller->username . ')' : ''),
                    'icon' => 'fas fa-store-alt',
                    'category' => 'seller',
                    'suggested_label' => '🏪 ' . \Illuminate\Support\Str::limit($displayName, 26),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'suggestions' => array_slice($suggestions, 0, 15),
        ]);
    }

    public function getBroadcastStatus($jobId)
    {
        $job = \App\Models\BroadcastJob::findOrFail($jobId);
        return response()->json([
            'status' => 'success',
            'job' => [
                'id' => $job->id,
                'status' => $job->status,
                'total' => $job->total_targets,
                'sent' => $job->sent_count,
                'failed' => $job->failed_count,
                'created_at' => $job->created_at ? $job->created_at->toIso8601String() : null,
                'updated_at' => $job->updated_at ? $job->updated_at->toIso8601String() : null,
            ]
        ]);
    }

    public function getActiveBroadcast()
    {
        $job = \App\Models\BroadcastJob::whereIn('status', ['pending', 'processing'])
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'status' => 'success',
            'has_active' => $job ? true : false,
            'job' => $job ? [
                'id' => $job->id,
                'message' => $job->message,
                'status' => $job->status,
                'total' => $job->total_targets,
                'sent' => $job->sent_count,
                'failed' => $job->failed_count,
                'created_at' => $job->created_at ? $job->created_at->toIso8601String() : null,
                'updated_at' => $job->updated_at ? $job->updated_at->toIso8601String() : null,
            ] : null
        ]);
    }

    public function markBroadcastRead($jobId)
    {
        $job = \App\Models\BroadcastJob::findOrFail($jobId);
        $job->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    public function cancelBroadcast($jobId)
    {
        $job = \App\Models\BroadcastJob::findOrFail($jobId);

        if (in_array($job->status, ['pending', 'processing'])) {
            $job->update(['status' => 'failed']);
            return response()->json([
                'status' => 'success',
                'message' => 'Broadcast berhasil dihentikan.'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Broadcast tidak dapat dihentikan karena sudah selesai atau gagal.'
        ], 422);
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

        if (array_key_exists('system_timezone', $settings)) {
            \App\Support\SystemTimezone::forgetCache();
        }

        if ($request->has('vcc_services_input')) {
            $rawServices = $request->input('vcc_services_input');
            if (is_array($rawServices)) {
                $cleaned = array_values(array_unique(array_filter(array_map('trim', $rawServices))));
            } else {
                $cleaned = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)$rawServices)))));
            }
            if (empty($cleaned)) {
                $cleaned = ['Shopee', 'Lazada', 'DigitalOcean', 'Linode', 'Vultr', 'Google Cloud', 'AWS', 'PayPal', 'OpenAI'];
            }
            \App\Models\BotSetting::updateOrCreate(
                ['key' => 'vcc_services_list'],
                ['value' => json_encode($cleaned), 'updated_at' => now()]
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

        if (array_key_exists('maintenance_mode', $settings)) {
            $statusStr = $settings['maintenance_mode'] == '1' ? 'Aktif' : 'Nonaktif';
            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'action' => 'toggle_maintenance_mode',
                'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                'entity_type' => 'setting',
                'entity_id' => 0,
                'detail' => "Admin mengubah status Mode Maintenance menjadi: {$statusStr}",
                'created_at' => now(),
            ]);
        }

        return back()->with('success', __('Konfigurasi berhasil disimpan!'));
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
                return back()->with('error', __('Payload QRIS kosong atau tidak terbaca dari gambar.'));
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

            return back()->with('success', __('Gambar QRIS berhasil diunggah dan payload terekstraksi. Bot Telegram & Web sudah terintegrasi.'));
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

        return back()->with('success', __('QRIS dan payload berhasil dihapus dari Web & Bot Telegram.'));
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

    public function runHeldFunds()
    {
        try {
            $exitCode = \Illuminate\Support\Facades\Artisan::call('funds:release-held');
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            $msg = trim($output) ?: 'Proses selesai tanpa output tambahan.';
            return redirect()->back()->with('success', 'Berhasil menjalankan pelepasan saldo tertahan: ' . $msg);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menjalankan pelepasan saldo: ' . $e->getMessage());
        }
    }

    public function runReleaseExpired()
    {
        try {
            $exitCode = \Illuminate\Support\Facades\Artisan::call('orders:release-expired');
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            $msg = trim($output) ?: 'Proses selesai tanpa output tambahan.';
            return redirect()->back()->with('success', 'Berhasil membatalkan pesanan kedaluwarsa: ' . $msg);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membatalkan pesanan: ' . $e->getMessage());
        }
    }



    public function auditLogs(Request $request)
    {
        $query = \App\Models\AuditLog::with('actor')->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('detail', 'like', "%{$search}%")
                  ->orWhere('entity_type', 'like', "%{$search}%")
                  ->orWhereHas('actor', function($aq) use ($search) {
                      $aq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('actor_type')) {
            if ($request->actor_type === 'user') {
                $query->whereNotNull('actor_id');
            } elseif ($request->actor_type === 'system') {
                $query->whereNull('actor_id');
            }
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->paginate(20)->withQueryString();

        // --- Analitik Data Audit Logs (Menyesuaikan Filter Aktif Jika Ada) ---
        $analyticsQuery = \App\Models\AuditLog::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $analyticsQuery->where(function($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('detail', 'like', "%{$search}%")
                  ->orWhere('entity_type', 'like', "%{$search}%")
                  ->orWhereHas('actor', function($aq) use ($search) {
                      $aq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%");
                  });
            });
        }
        if ($request->filled('actor_type')) {
            if ($request->actor_type === 'user') {
                $analyticsQuery->whereNotNull('actor_id');
            } elseif ($request->actor_type === 'system') {
                $analyticsQuery->whereNull('actor_id');
            }
        }
        if ($request->filled('entity_type')) {
            $analyticsQuery->where('entity_type', $request->entity_type);
        }
        if ($request->filled('start_date')) {
            $analyticsQuery->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $analyticsQuery->whereDate('created_at', '<=', $request->end_date);
        }

        $totalAuditCount = (clone $analyticsQuery)->count();
        $weekAuditCount = \App\Models\AuditLog::where('created_at', '>=', now()->subDays(7))->count();
        $todayAuditCount = (clone $analyticsQuery)->whereDate('created_at', now()->toDateString())->count();
        $yesterdayAuditCount = (clone $analyticsQuery)->whereDate('created_at', now()->subDay()->toDateString())->count();
        $dailyAverageAudit = round($weekAuditCount / 7, 1);

        $topActionRecord = (clone $analyticsQuery)->select('action', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('action')
            ->orderByDesc('total')
            ->first();
        $topActionName = $topActionRecord ? $topActionRecord->action : '-';
        $topActionCount = $topActionRecord ? $topActionRecord->total : 0;
        
        $actorAuditCount = (clone $analyticsQuery)->whereNotNull('actor_id')->count();
        $systemAuditCount = (clone $analyticsQuery)->whereNull('actor_id')->count();
        $automationRate = $totalAuditCount > 0 ? round(($systemAuditCount / $totalAuditCount) * 100, 1) : 0;
        $humanRate = $totalAuditCount > 0 ? round(($actorAuditCount / $totalAuditCount) * 100, 1) : 0;

        // Deteksi Aksi Kritis / Sensitif (Delete, Suspend, Refund, Takeover, Impersonate, Replace, Cancel)
        $criticalAuditCount = (clone $analyticsQuery)->where(function ($q) {
            $q->where('action', 'like', '%delete%')
              ->orWhere('action', 'like', '%suspend%')
              ->orWhere('action', 'like', '%refund%')
              ->orWhere('action', 'like', '%takeover%')
              ->orWhere('action', 'like', '%impersonate%')
              ->orWhere('action', 'like', '%replace%')
              ->orWhere('action', 'like', '%cancel%')
              ->orWhere('action', 'like', '%reject%');
        })->count();

        // 1. Tren Harian (7 Hari Terakhir: Total, Pelaku Manual, dan Otomatis Sistem/Bot)
        $auditTrendLabels = [];
        $auditTrendData = [];
        $auditActorTrendData = [];
        $auditSystemTrendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $auditTrendLabels[] = $date->translatedFormat('d M');
            $dayActor = (clone $analyticsQuery)->whereDate('created_at', $dateStr)->whereNotNull('actor_id')->count();
            $daySystem = (clone $analyticsQuery)->whereDate('created_at', $dateStr)->whereNull('actor_id')->count();
            $auditActorTrendData[] = $dayActor;
            $auditSystemTrendData[] = $daySystem;
            $auditTrendData[] = $dayActor + $daySystem;
        }

        // 2. Distribusi Kategori Aksi Terbanyak (Top 5 Actions + Lainnya)
        $actionGroups = (clone $analyticsQuery)->select('action', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        
        $actionDistributionLabels = [];
        $actionDistributionData = [];
        $sumTopActions = 0;
        foreach ($actionGroups as $ag) {
            $actionDistributionLabels[] = $ag->action;
            $actionDistributionData[] = (int) $ag->count;
            $sumTopActions += $ag->count;
        }

        $otherActionsCount = max(0, $totalAuditCount - $sumTopActions);
        if ($otherActionsCount > 0) {
            $actionDistributionLabels[] = 'Lainnya';
            $actionDistributionData[] = $otherActionsCount;
        }

        // 3. Distribusi Tipe Entitas yang Dimodifikasi (Entity Type Breakdown)
        $entityGroups = (clone $analyticsQuery)
            ->select(
                \Illuminate\Support\Facades\DB::raw("COALESCE(NULLIF(entity_type, ''), 'system') as entity_label"),
                \Illuminate\Support\Facades\DB::raw('count(*) as count')
            )
            ->groupBy('entity_label')
            ->orderByDesc('count')
            ->limit(6)
            ->get();

        $entityDistributionLabels = [];
        $entityDistributionData = [];
        foreach ($entityGroups as $eg) {
            $entityDistributionLabels[] = strtoupper($eg->entity_label);
            $entityDistributionData[] = (int) $eg->count;
        }

        // Daftar pilihan entity_type untuk dropdown filter
        $availableEntityTypes = \App\Models\AuditLog::whereNotNull('entity_type')
            ->where('entity_type', '!=', '')
            ->distinct()
            ->pluck('entity_type')
            ->toArray();

        // 4. Top Pelaku (Actors) Paling Aktif
        $topActorsRaw = (clone $analyticsQuery)
            ->whereNotNull('actor_id')
            ->select('actor_id', \Illuminate\Support\Facades\DB::raw('count(*) as total_actions'), \Illuminate\Support\Facades\DB::raw('MAX(created_at) as last_active_at'))
            ->groupBy('actor_id')
            ->orderByDesc('total_actions')
            ->limit(5)
            ->get();

        $actorUserMap = \App\Models\User::whereIn('id', $topActorsRaw->pluck('actor_id'))->get()->keyBy('id');
        $topActors = $topActorsRaw->map(function ($row) use ($actorUserMap, $totalAuditCount) {
            $u = $actorUserMap->get($row->actor_id);
            $row->actor_name = $u ? ($u->full_name ?? $u->username) : "User #{$row->actor_id}";
            $row->actor_username = $u ? $u->username : '-';
            $row->actor_role = $u ? $u->role : 'unknown';
            $row->share_percent = $totalAuditCount > 0 ? round(($row->total_actions / $totalAuditCount) * 100, 1) : 0;
            return $row;
        });

        // 5. Hasil Analisis & Kesimpulan Otomatis Log Audit (Audit Intelligence)
        $dominantEntity = !empty($entityDistributionLabels) ? $entityDistributionLabels[0] : '-';
        $auditAnalysisInsights = [];

        if ($totalAuditCount === 0) {
            $auditAnalysisInsights[] = [
                'icon' => 'fa-info-circle',
                'color' => 'secondary',
                'title' => 'Belum Ada Catatan Audit',
                'desc' => 'Tidak ditemukan rekaman jejak audit yang cocok dengan kriteria pencarian saat ini.'
            ];
        } else {
            $auditAnalysisInsights[] = [
                'icon' => 'fa-robot',
                'color' => 'primary',
                'title' => 'Keseimbangan Otomatisasi vs Intervensi Manual',
                'desc' => "Sebesar {$automationRate}% aktivitas ({$systemAuditCount} log) dijalankan otomatis oleh Sistem/Bot, sedangkan {$humanRate}% ({$actorAuditCount} log) merupakan tindakan langsung oleh Admin/User."
            ];

            $auditAnalysisInsights[] = [
                'icon' => 'fa-layer-group',
                'color' => 'info',
                'title' => 'Konsentrasi Aktivitas & Entitas Dominan',
                'desc' => "Aksi yang paling sering terjadi adalah \"{$topActionName}\" ({$topActionCount} kejadian), dengan objek perubahan terbanyak pada entitas {$dominantEntity}. Rata-rata beban aktivitas 7 hari terakhir adalah {$dailyAverageAudit} log/hari."
            ];

            if ($criticalAuditCount > 0) {
                $auditAnalysisInsights[] = [
                    'icon' => 'fa-shield-virus',
                    'color' => 'warning',
                    'title' => 'Pemantauan Aksi Kritis & Sensitif',
                    'desc' => "Tercatat {$criticalAuditCount} aksi berdampak tinggi (penghapusan, penangguhan akun, refund, penggantian stok, atau impersonasi) yang perlu ditinjau berkala untuk kepatuhan operasional."
                ];
            } else {
                $auditAnalysisInsights[] = [
                    'icon' => 'fa-check-circle',
                    'color' => 'success',
                    'title' => 'Operasional Stabil Tanpa Aksi Destruktif',
                    'desc' => 'Tidak tercatat aksi penghapusan, penangguhan akun, atau refund pada periode data yang sedang ditampilkan.'
                ];
            }
        }

        return view('admin.audit_logs.index', compact(
            'logs',
            'totalAuditCount',
            'weekAuditCount',
            'todayAuditCount',
            'yesterdayAuditCount',
            'dailyAverageAudit',
            'topActionName',
            'topActionCount',
            'actorAuditCount',
            'systemAuditCount',
            'automationRate',
            'humanRate',
            'criticalAuditCount',
            'auditTrendLabels',
            'auditTrendData',
            'auditActorTrendData',
            'auditSystemTrendData',
            'actionDistributionLabels',
            'actionDistributionData',
            'entityDistributionLabels',
            'entityDistributionData',
            'availableEntityTypes',
            'topActors',
            'auditAnalysisInsights'
        ));
    }

    // ==========================================
    // WEBSITE SETTINGS
    // ==========================================
    public function websiteSettings()
    {
        $announcement = \App\Models\BotSetting::where('key', 'web_announcement')->value('value') ?? 'Selamat datang Jurangan!<br>kalau punya akun telegram, langsung saja klik "Login Via Telegram" kalau gak punya, bisa regis dulu.';
        $companyName = \App\Models\BotSetting::where('key', 'company_name')->value('value') ?? 'PT BANGUN JAYA KASIH';
        return view('admin.website.settings', compact('announcement', 'companyName'));
    }

    // ==========================================
    // ORDER ACTIONS
    // ==========================================
    public function acceptOrder($id, \App\Services\OrderService $orderService)
    {
        $order = \App\Models\Order::findOrFail($id);

        try {
            $orderService->confirmPayment($order, \Illuminate\Support\Facades\Auth::id());
            return redirect()->back()->with('success', __('Pembayaran pesanan berhasil dikonfirmasi.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal konfirmasi pesanan: ' . $e->getMessage());
        }
    }

    public function rejectOrder($id, \App\Services\OrderService $orderService)
    {
        $order = \App\Models\Order::findOrFail($id);

        try {
            $orderService->cancelOrder($order, 'cancelled_by_admin', \Illuminate\Support\Facades\Auth::id());
            return redirect()->back()->with('success', __('Pesanan berhasil ditolak (dibatalkan).'));
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
        $request->validate([
            'proof_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $image = $request->file('proof_image');
        $fileName = 'proof_' . (int) $id . '_' . time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $image->getClientOriginalExtension();
        $storedPath = \Illuminate\Support\Facades\Storage::disk('public')->putFileAs('proofs', $image, $fileName);

        $errorMessage = null;
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($id, $storedPath, &$errorMessage) {
                $withdrawal = \App\Models\WithdrawalRequest::where('id', $id)->lockForUpdate()->firstOrFail();

                if ($withdrawal->status !== 'pending') {
                    $errorMessage = __('Permintaan penarikan ini sudah diproses sebelumnya.');
                    return;
                }

                $seller = \App\Models\User::where('id', $withdrawal->seller_id)->lockForUpdate()->firstOrFail();
                if ((int) ($seller->wallet_balance ?? 0) < (int) $withdrawal->amount) {
                    $errorMessage = __('Saldo wallet seller tidak mencukupi untuk penarikan ini.');
                    return;
                }

                $seller->decrement('wallet_balance', (int) $withdrawal->amount);

                $withdrawal->proof_image_path = 'storage/' . $storedPath;
                $withdrawal->status = 'approved';
                $withdrawal->processed_at = now();
                $withdrawal->save();
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($storedPath);
            }
            throw $e;
        }

        if ($errorMessage !== null) {
            if ($storedPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($storedPath);
            }
            return redirect()->back()->with('error', $errorMessage);
        }

        return redirect()->back()->with('success', __('Permintaan penarikan berhasil disetujui.'));
    }

    public function rejectWithdrawal(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $errorMessage = null;
        \Illuminate\Support\Facades\DB::transaction(function () use ($id, $request, &$errorMessage) {
            $withdrawal = \App\Models\WithdrawalRequest::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status !== 'pending') {
                $errorMessage = __('Permintaan penarikan ini sudah diproses sebelumnya.');
                return;
            }

            $withdrawal->status = 'rejected';
            $withdrawal->rejection_reason = $request->rejection_reason;
            $withdrawal->processed_at = now();
            $withdrawal->save();
        });

        if ($errorMessage !== null) {
            return redirect()->back()->with('error', $errorMessage);
        }

        return redirect()->back()->with('success', __('Permintaan penarikan berhasil ditolak.'));
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
            return redirect()->back()->with('error', __('Hanya pengguna dengan role seller yang dapat ditambahkan sebagai worker.'));
        }

        // Attach worker if not already attached
        if (!$product->workers()->where('user_id', $user->id)->exists()) {
            $product->workers()->attach($user->id);
        }

        return redirect()->back()->with('success', __('Worker berhasil ditambahkan ke produk.'));
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

        return redirect()->back()->with('success', __('Worker berhasil dihapus dari produk, dan stok miliknya telah dialihkan ke pemilik produk.'));
    }

    public function replaceStock($orderId, $stockUnitId)
    {
        $order = \App\Models\Order::findOrFail($orderId);
        $problemUnit = \App\Models\StockUnit::where('id', $stockUnitId)
            ->where('sold_order_id', $orderId)
            ->where('is_sold', true)
            ->firstOrFail();

        // Find a replacement unit of same product & same seller
        $newUnit = \App\Models\StockUnit::where('product_id', $problemUnit->product_id)
            ->where('seller_id', $problemUnit->seller_id)
            ->where('stock_status', 'ready')
            ->where('is_sold', false)
            ->orderBy('id', 'asc')
            ->first();

        if (!$newUnit) {
            return redirect()->back()->with('error', __('Stok pengganti dari seller ini tidak tersedia. Silakan gunakan opsi refund.'));
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // Cooldown for problem unit is seller's cooldown hours
            $seller = \App\Models\User::find($problemUnit->seller_id);
            $saveHours = $seller ? ($seller->seller_save_hours ?? 80) : 80;

            // Pull back problem unit to karantina (saved_for_verification)
            $problemUnit->is_sold = false;
            $problemUnit->sold_order_id = null;
            $problemUnit->stock_status = 'saved_for_verification';
            $problemUnit->available_at = now()->addHours($saveHours);
            $problemUnit->save();

            // Assign new replacement unit to order
            $newUnit->is_sold = true;
            $newUnit->sold_order_id = $orderId;
            $newUnit->save();

            // Audit Log
            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'action' => 'stock_replaced',
                'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                'entity_type' => 'order',
                'entity_id' => $orderId,
                'detail' => "problem_stock_id={$stockUnitId}; new_stock_id={$newUnit->id}",
                'created_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->back()->with('success', __('Akun berhasil diganti dengan stok baru dari seller.'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Gagal mengganti akun: ' . $e->getMessage());
        }
    }

    public function refundOrder(Request $request, $orderId)
    {
        $refundDestination = $request->input('refund_destination', 'external');
        if (!in_array($refundDestination, ['wallet', 'external'], true)) {
            $refundDestination = 'external';
        }
        $isWalletRefund = $refundDestination === 'wallet';

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $order = \App\Models\Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled') {
                \Illuminate\Support\Facades\DB::rollBack();
                return redirect()->back()->with('error', __('Pesanan ini sudah pernah dibatalkan sebelumnya.'));
            }

            $refundAmount = max(0, (int) $order->total_amount - (int) ($order->unique_code ?? 0));
            if ($refundAmount <= 0) {
                $refundAmount = (int) $order->total_amount;
            }

            // 1. Update Order & Payment
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancel_reason = $isWalletRefund
                ? 'Refund ke Dompet Saldo & Dibatalkan oleh Admin'
                : 'Dibatalkan oleh Admin (Refund Manual di Luar Website)';
            $order->save();

            $payment = $order->payment;
            if ($payment) {
                $payment->status = 'cancelled';
                $payment->save();
            }

            // 2. Credit customer wallet ONLY if Admin selected wallet refund
            if ($isWalletRefund && $refundAmount > 0 && $order->customer_id) {
                app(\App\Services\WalletService::class)->credit(
                    (int) $order->customer_id,
                    $refundAmount,
                    'refund',
                    $order->order_ref,
                    __('Refund pembatalan pesanan #:ref ke Dompet Saldo', ['ref' => $order->order_ref]),
                    ['order_id' => $order->id, 'actor_id' => \Illuminate\Support\Facades\Auth::id()]
                );
            }

            // 3. Pull all stock units back to saved_for_verification (karantina)
            $units = \App\Models\StockUnit::where('sold_order_id', $orderId)->get();
            foreach ($units as $unit) {
                $seller = \App\Models\User::find($unit->seller_id);
                $saveHours = $seller ? ($seller->seller_save_hours ?? 80) : 80;

                $unit->is_sold = false;
                $unit->sold_order_id = null;
                $unit->stock_status = 'saved_for_verification';
                $unit->available_at = now()->addHours($saveHours);
                $unit->save();
            }

            // 4. Cancel held funds
            \Illuminate\Support\Facades\DB::table('held_funds')
                ->where('order_id', $orderId)
                ->where('status', 'held')
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now()
                ]);

            // 5. Audit Log
            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'action' => 'order_refunded',
                'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                'entity_type' => 'order',
                'entity_id' => $order->id,
                'detail' => "order_ref={$order->order_ref}; destination={$refundDestination}; wallet_credit=" . ($isWalletRefund ? $refundAmount : 0) . "; units=" . $units->count(),
                'created_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::commit();

            try {
                \App\Services\TelegramService::notifyCustomerOrderCancelled($order, $order->cancel_reason);
                \App\Services\TelegramService::updateAdminOrderMessage($order);
            } catch (\Exception $e) {
                \Log::error("Gagal kirim notifikasi Telegram untuk refund order {$order->order_ref}: " . $e->getMessage());
            }

            if ($isWalletRefund) {
                return redirect()->back()->with('success', __('Pesanan dibatalkan & dana Rp:amount telah dikirim ke Dompet Saldo pelanggan.', [
                    'amount' => number_format($refundAmount, 0, ',', '.'),
                ]));
            }

            return redirect()->back()->with('success', __('Pesanan berhasil dibatalkan (tanpa pengiriman saldo ke dompet — refund manual di luar website).'));
        } catch (\Exception $e) {
            if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
                \Illuminate\Support\Facades\DB::rollBack();
            }
            return redirect()->back()->with('error', __('Gagal memproses pembatalan/refund pesanan: ') . $e->getMessage());
        }
    }

    public function replaceStockBulk(Request $request, $orderId)
    {
        $order = \App\Models\Order::findOrFail($orderId);
        $stockUnitIds = json_decode($request->input('stock_unit_ids', '[]'), true);

        if (!is_array($stockUnitIds) || empty($stockUnitIds)) {
            return redirect()->back()->with('error', __('Tidak ada akun terpilih.'));
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $assignedReplacementIds = [];
            $replacedCount = 0;

            foreach ($stockUnitIds as $stockUnitId) {
                $problemUnit = \App\Models\StockUnit::where('id', $stockUnitId)
                    ->where('sold_order_id', $orderId)
                    ->where('is_sold', true)
                    ->first();

                if (!$problemUnit) continue;

                // Find a replacement unit of same product & same seller
                $newUnit = \App\Models\StockUnit::where('product_id', $problemUnit->product_id)
                    ->where('seller_id', $problemUnit->seller_id)
                    ->where('stock_status', 'ready')
                    ->where('is_sold', false)
                    ->whereNotIn('id', $assignedReplacementIds)
                    ->orderBy('id', 'asc')
                    ->first();

                if (!$newUnit) {
                    \Illuminate\Support\Facades\DB::rollBack();
                    return redirect()->back()->with('error', __('Stok pengganti tidak mencukupi untuk semua akun terpilih.'));
                }

                $assignedReplacementIds[] = $newUnit->id;

                // Cooldown for problem unit is seller's cooldown hours
                $seller = \App\Models\User::find($problemUnit->seller_id);
                $saveHours = $seller ? ($seller->seller_save_hours ?? 80) : 80;

                // Pull back problem unit to karantina
                $problemUnit->is_sold = false;
                $problemUnit->sold_order_id = null;
                $problemUnit->stock_status = 'saved_for_verification';
                $problemUnit->available_at = now()->addHours($saveHours);
                $problemUnit->save();

                // Assign new replacement unit to order
                $newUnit->is_sold = true;
                $newUnit->sold_order_id = $orderId;
                $newUnit->save();

                $replacedCount++;

                // Audit Log
                \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                    'action' => 'stock_replaced',
                    'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                    'entity_type' => 'order',
                    'entity_id' => $orderId,
                    'detail' => "problem_stock_id={$stockUnitId}; new_stock_id={$newUnit->id} (Bulk)",
                    'created_at' => now(),
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->back()->with('success', __("Berhasil mengganti :replacedCount akun terpilih.", ["replacedCount" => $replacedCount]));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Gagal mengganti akun: ' . $e->getMessage());
        }
    }

    public function refundBulk(Request $request, $orderId)
    {
        $order = \App\Models\Order::with('items')->findOrFail($orderId);
        $stockUnitIds = json_decode($request->input('stock_unit_ids', '[]'), true);
        $refundDestination = $request->input('refund_destination', 'external');
        if (!in_array($refundDestination, ['wallet', 'external'], true)) {
            $refundDestination = 'external';
        }
        $isWalletRefund = $refundDestination === 'wallet';

        if (!is_array($stockUnitIds) || empty($stockUnitIds)) {
            return redirect()->back()->with('error', __('Tidak ada akun terpilih.'));
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $refundedCount = 0;
            $walletRefundTotal = 0;

            // Precompute per-unit net price by product_id
            $netUnitPriceByProduct = [];
            foreach ($order->items as $ordItem) {
                $qty = max(1, (int) $ordItem->quantity);
                $lineGross = (int) $ordItem->unit_price * $qty;
                $lineDisc = (int) ($ordItem->discount_amount ?? 0);
                if ($lineDisc <= 0 && (int) ($order->discount_amount ?? 0) > 0 && (int) ($order->subtotal ?? 0) > 0) {
                    $lineDisc = (int) round(($lineGross / (int) $order->subtotal) * (int) $order->discount_amount);
                }
                $netUnitPriceByProduct[$ordItem->product_id] = (int) floor(max(0, $lineGross - $lineDisc) / $qty);
            }

            foreach ($stockUnitIds as $stockUnitId) {
                $unit = \App\Models\StockUnit::where('id', $stockUnitId)
                    ->where('sold_order_id', $orderId)
                    ->where('is_sold', true)
                    ->lockForUpdate()
                    ->first();

                if (!$unit) continue;

                $unitProductId = $unit->product_id;
                $unitSellerId = $unit->seller_id;

                $seller = \App\Models\User::find($unitSellerId);
                $saveHours = $seller ? ($seller->seller_save_hours ?? 80) : 80;

                // Pull back stock unit to saved_for_verification (karantina)
                $unit->is_sold = false;
                $unit->sold_order_id = null;
                $unit->stock_status = 'saved_for_verification';
                $unit->available_at = now()->addHours($saveHours);
                $unit->save();

                // Cancel 1 matching held fund SPECIFICALLY for this unit's product & seller
                $matchingFund = \App\Models\HeldFund::where('order_id', $orderId)
                    ->where('status', 'held')
                    ->where('product_id', $unitProductId)
                    ->when($unitSellerId, fn ($q) => $q->where('seller_id', $unitSellerId))
                    ->first();
                if ($matchingFund) {
                    $matchingFund->status = 'cancelled';
                    $matchingFund->save();
                }

                $walletRefundTotal += (int) ($netUnitPriceByProduct[$unitProductId] ?? 0);
                $refundedCount++;
            }

            if ($refundedCount > 0) {
                if ($isWalletRefund && $walletRefundTotal > 0 && $order->customer_id) {
                    app(\App\Services\WalletService::class)->credit(
                        (int) $order->customer_id,
                        $walletRefundTotal,
                        'refund',
                        $order->order_ref . '-PARTIAL',
                        __('Refund sebagian (:count unit) pesanan #:ref ke Dompet Saldo', [
                            'count' => $refundedCount,
                            'ref' => $order->order_ref,
                        ]),
                        ['order_id' => $order->id, 'refunded_units' => $refundedCount]
                    );
                }

                // Check if there are any remaining stock units sold in this order
                $remainingUnitsCount = \App\Models\StockUnit::where('sold_order_id', $orderId)
                    ->where('is_sold', true)
                    ->count();

                if ($remainingUnitsCount === 0) {
                    $order->status = 'cancelled';
                    $order->cancelled_at = now();
                    $order->cancel_reason = $isWalletRefund
                        ? 'Refund ke Dompet & Dibatalkan penuh oleh Admin (Bulk)'
                        : 'Dibatalkan penuh oleh Admin (Refund Manual di Luar Website)';
                    $order->save();

                    $payment = $order->payment;
                    if ($payment) {
                        $payment->status = 'cancelled';
                        $payment->save();
                    }

                    try {
                        \App\Services\TelegramService::notifyCustomerOrderCancelled($order, $order->cancel_reason);
                        \App\Services\TelegramService::updateAdminOrderMessage($order);
                    } catch (\Exception $e) {
                        \Log::error("Gagal kirim notifikasi Telegram untuk bulk refund order {$order->order_ref}: " . $e->getMessage());
                    }
                }

                // Audit Log
                \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                    'action' => 'order_partially_refunded',
                    'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                    'entity_type' => 'order',
                    'entity_id' => $orderId,
                    'detail' => "refunded_count={$refundedCount}; remaining_units={$remainingUnitsCount}; destination={$refundDestination}; wallet_credit=" . ($isWalletRefund ? $walletRefundTotal : 0),
                    'created_at' => now(),
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            if ($isWalletRefund && $walletRefundTotal > 0) {
                return redirect()->back()->with('success', __('Berhasil me-refund :count akun terpilih & mengirim Rp:amount ke Dompet Saldo pelanggan.', [
                    'count' => $refundedCount,
                    'amount' => number_format($walletRefundTotal, 0, ',', '.'),
                ]));
            }

            return redirect()->back()->with('success', __('Berhasil membatalkan :count akun terpilih (tanpa pengiriman saldo ke dompet — refund manual di luar website).', [
                'count' => $refundedCount,
            ]));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses refund sebagian: ' . $e->getMessage());
        }
    }
}
