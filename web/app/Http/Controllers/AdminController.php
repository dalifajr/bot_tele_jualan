<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $productId = $request->query('product_id');
        $period = $request->query('period');
        if (!$period && $request->has('days')) {
            $daysParam = (int)$request->query('days');
            if ($daysParam <= 1) {
                $period = '24_hours';
            } elseif ($daysParam <= 7) {
                $period = '7_days';
            } elseif ($daysParam <= 30) {
                $period = '30_days';
            } else {
                $period = '6_months';
            }
        }
        if (!in_array($period, ['24_hours', '7_days', '30_days', '6_months'])) {
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
        } else { // 6_months
            $currentStart = now()->subMonths(6)->startOfDay();
            $previousEnd = $currentStart;
            $previousStart = now()->subMonths(12)->startOfDay();
            $days = 180;
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
        $previousMetrics = $getMetrics($previousStart, $previousEnd);

        // For Products and Users, we want cumulative counts up to end dates
        $currentProducts = Product::where('created_at', '<=', $currentEnd)->count();
        $previousProducts = Product::where('created_at', '<=', $previousEnd)->count();

        $currentUsers = User::where('created_at', '<=', $currentEnd)->count();
        $previousUsers = User::where('created_at', '<=', $previousEnd)->count();

        $webUsersCount = User::whereNotNull('password')
            ->where('created_at', '<=', $currentEnd)
            ->count();
        $tgUsersCount = User::whereNotNull('telegram_id')
            ->where('created_at', '<=', $currentEnd)
            ->count();

        // Helper to calculate percentage growth / change
        $calculateChange = function ($current, $previous) {
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
        } else { // 6_months
            for ($i = 5; $i >= 0; $i--) {
                $monthObj = now()->subMonths($i);
                $start = (clone $monthObj)->startOfMonth()->startOfDay();
                $end = (clone $monthObj)->endOfMonth()->endOfDay();
                
                $chartLabels[] = $monthObj->format('M Y');
                $chartData[] = $getChartOrderSum($start, $end);
            }
        }

        $periodLabel = match($period) {
            '24_hours' => '24 jam terakhir',
            '7_days' => '7 hari terakhir',
            '30_days' => '30 hari terakhir',
            '6_months' => '6 bulan terakhir',
        };

        $products = \App\Models\Product::orderBy('name')->get();

        return view('admin.dashboard', compact(
            'totalRevenue', 'platformCommission', 'adminEarnings', 'totalSellerEarnings', 'totalOrders', 'totalProducts', 'totalUsers', 'webUsersCount', 'tgUsersCount', 'recentOrders',
            'deliveredOrders', 'cancelledOrders', 'chartLabels', 'chartData', 'days', 'period', 'periodLabel',
            'revenueStats', 'commissionStats', 'adminEarningsStats', 'sellerEarningsStats', 'ordersStats', 'productsStats', 'usersStats', 'products', 'productId'
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
        return view('admin.stock.index', compact(
            'stockUnits', 'status', 'totalStock', 'readyStock', 'awaitingStock', 'savedStock', 'soldStock'
        ));
    }

    public function exportStock(Request $request)
    {
        // Validate admin password before export
        if ($request->isMethod('post') || $request->has('password')) {
            $request->validate(['password' => 'required|string']);
            if (!\Illuminate\Support\Facades\Hash::check($request->password, \Illuminate\Support\Facades\Auth::user()->password)) {
                return redirect()->back()->with('error', 'Password salah. Ekspor data dibatalkan.');
            }
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
            $query->where('status', $request->status);
        }

        if ($request->filled('product_id')) {
            $query->whereHas('items', function ($iq) use ($request) {
                $iq->where('product_id', $request->product_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
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
            });
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
            return redirect()->back()->with('error', 'Tidak dapat menghapus pengguna karena memiliki riwayat pesanan. Silakan gunakan fitur Suspend sebagai gantinya.');
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
        return redirect()->back()->with('success', 'Pengguna berhasil dihapus.');
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

        return redirect()->back()->with('success', 'Akun pengguna berhasil ditangguhkan (suspended). Akses bot telah dicabut.');
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

        return redirect()->back()->with('success', 'Penangguhan akun telah dicabut. Pengguna dapat menggunakan bot kembali.');
    }

    public function impersonate(Request $request, $id)
    {
        $targetUser = User::findOrFail($id);
        
        if ($targetUser->id === \Illuminate\Support\Facades\Auth::id()) {
            return redirect()->back()->with('error', 'Tidak dapat login sebagai diri sendiri.');
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
        return redirect()->route('dashboard')->with('success', "Berhasil masuk sebagai " . $name . ".");
    }

    public function stopImpersonating(Request $request)
    {
        if (!session()->has('admin_impersonator_id')) {
            return redirect()->route('dashboard')->with('error', 'Sesi impersonasi tidak ditemukan.');
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

        return redirect()->route('admin.users.index')->with('success', 'Kembali ke sesi Admin.');
    }

    // --- CRUD Products ---
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'warranty_days' => 'required_if:enable_warranty,1|nullable|integer|min:1',
            'vpn_protocol' => 'required_if:is_vpn,1|nullable|string',
            'vpn_duration_days' => 'required_if:is_vpn,1|nullable|integer|min:1',
        ]);
        $data = $request->only(['name', 'description', 'price', 'vpn_protocol', 'vpn_duration_days']);
        $data['description'] = $data['description'] ?? '';
        $data['is_suspended'] = false;
        $data['is_vpn'] = $request->has('is_vpn');
        $data['warranty_days'] = $request->has('enable_warranty') ? $request->warranty_days : 0;
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
            'is_suspended' => 'boolean',
            'warranty_days' => $request->has('enable_warranty') ? 'required|integer|min:1' : 'nullable',
            'vpn_protocol' => $request->has('is_vpn') ? 'required|string' : 'nullable',
            'vpn_duration_days' => $request->has('is_vpn') ? 'required|integer|min:1' : 'nullable',
        ]);
        $data = $request->only(['name', 'description', 'price', 'vpn_protocol', 'vpn_duration_days']);
        $data['is_suspended'] = $request->has('is_suspended');
        $data['is_vpn'] = $request->has('is_vpn');
        $data['warranty_days'] = $request->has('enable_warranty') ? $request->warranty_days : 0;
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
                
                return redirect()->back()->with('success', 'Produk buatan seller dan sisa stoknya berhasil diambil alih oleh Admin Utama.');
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

            return redirect()->back()->with('success', 'Produk berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus produk: ' . $e->getMessage());
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
            return redirect()->back()->with('error', 'Tidak ada stok belum terjual yang tersedia untuk diekspor.');
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

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_bulk_delete_stock',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'stock_unit',
            'entity_id' => 0,
            'detail' => "Bulk deleted {$count} stock units (IDs: " . implode(',', $ids) . ")",
            'created_at' => now(),
        ]);

        return back()->with('success', "$count stok berhasil dihapus secara masal.");
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
            'allowed_tools' => 'nullable|array',
            'allowed_tools.*' => 'string|in:github_checker,gmail_checker',
        ]);

        $oldRole = $user->role;
        $user->role = $request->role;
        $user->wallet_balance = $request->wallet_balance;
        $user->platform_fee_percent = $request->platform_fee_percent;
        $user->seller_save_hours = $request->seller_save_hours;
        $user->allowed_tools = $request->role === 'seller' ? ($request->allowed_tools ?? []) : null;
        $user->save();

        // Audit log
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'action' => 'admin_update_user',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'detail' => "Updated user: {$user->username}; role: {$oldRole}->{$request->role}; balance: {$request->wallet_balance}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Informasi pengguna berhasil diperbarui.');
    }

    public function exportUsers(Request $request)
    {
        // Validate admin password before export
        if ($request->isMethod('post') || $request->has('password')) {
            $request->validate(['password' => 'required|string']);
            if (!\Illuminate\Support\Facades\Hash::check($request->password, \Illuminate\Support\Facades\Auth::user()->password)) {
                return redirect()->back()->with('error', 'Password salah. Ekspor data dibatalkan.');
            }
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

    public function showComplaint($id)
    {
        $complaint = \App\Models\ComplaintCase::with(['customer', 'order.items.product', 'order.stockUnits'])->findOrFail($id);
        return view('admin.complaints.show', compact('complaint'));
    }

    public function updateComplaintStatus(Request $request, $id)
    {
        $complaint = \App\Models\ComplaintCase::findOrFail($id);
        
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
            'action' => 'complaint_update_status',
            'actor_id' => \Illuminate\Support\Facades\Auth::id(),
            'entity_type' => 'complaint_case',
            'entity_id' => $complaint->id,
            'detail' => "status={$request->status}; ref={$complaint->complaint_ref}",
            'created_at' => now(),
        ]);

        return redirect()->route('admin.complaints.index')->with('success', 'Status komplain berhasil diperbarui.');
    }

    // ==========================================
    // BROADCAST
    // ==========================================
    public function broadcast()
    {
        return view('admin.broadcast.index');
    }

    public function startBroadcast(Request $request)
    {
        $request->validate([
            'message' => 'nullable|string',
            'media_file' => 'nullable|file|max:51200' // max 50MB
        ]);

        if (empty($request->message) && !$request->hasFile('media_file')) {
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
        }

        // Create the background job record
        $job = \App\Models\BroadcastJob::create([
            'message' => $request->message ?? '',
            'media_type' => $mediaType,
            'media_path' => $mediaPath,
            'total_targets' => count($targets),
            'sent_count' => 0,
            'failed_count' => 0,
            'status' => 'pending',
            'admin_id' => \Illuminate\Support\Facades\Auth::id(),
            'is_read' => false,
        ]);

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

    public function runBroadcastBackground(Request $request, $jobId)
    {
        $expectedToken = hash_hmac('sha256', $jobId, config('app.key'));
        if ($request->query('token') !== $expectedToken) {
            abort(403, 'Unauthorized');
        }

        // Run the broadcast logic here in the background
        ignore_user_abort(true);
        set_time_limit(0);

        $job = \App\Models\BroadcastJob::find($jobId);
        if (!$job) {
            return response()->json(['status' => 'not_found'], 404);
        }

        // Atomic update check to avoid duplicate running
        $updated = \App\Models\BroadcastJob::where('id', $jobId)
            ->where('status', 'pending')
            ->update(['status' => 'processing']);

        if (!$updated) {
            return response()->json(['status' => 'already_started']);
        }

        $targets = \App\Models\User::whereNotNull('telegram_id')
            ->where('role', 'customer')
            ->pluck('telegram_id')
            ->toArray();

        $token = config('telegram.bot_token');
        if (!$token) {
            \App\Models\BroadcastJob::where('id', $jobId)->update(['status' => 'failed']);
            return response()->json(['status' => 'failed', 'reason' => 'no_token'], 422);
        }

        $success = 0;
        $failed = 0;

        foreach ($targets as $targetId) {
            // Check if job was manually updated/cancelled
            $currentJob = \App\Models\BroadcastJob::find($jobId);
            if (!$currentJob || in_array($currentJob->status, ['failed', 'completed'])) {
                break;
            }

            $retry = 0;
            $maxRetries = 3;
            $sentSuccessfully = false;

            while ($retry < $maxRetries) {
                try {
                    if ($job->media_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($job->media_path)) {
                        $mediaFullPath = storage_path('app/public/' . $job->media_path);
                        $mediaData = fopen($mediaFullPath, 'r');
                        $filename = basename($mediaFullPath);
                        
                        if ($job->media_type === 'photo') {
                            $response = \Illuminate\Support\Facades\Http::timeout(15)
                                ->attach('photo', $mediaData, $filename)
                                ->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                                    'chat_id' => $targetId,
                                    'caption' => $job->message,
                                    'parse_mode' => 'HTML'
                                ]);
                        } elseif ($job->media_type === 'video') {
                            $response = \Illuminate\Support\Facades\Http::timeout(15)
                                ->attach('video', $mediaData, $filename)
                                ->post("https://api.telegram.org/bot{$token}/sendVideo", [
                                    'chat_id' => $targetId,
                                    'caption' => $job->message,
                                    'parse_mode' => 'HTML'
                                ]);
                        } else {
                            $response = \Illuminate\Support\Facades\Http::timeout(15)
                                ->attach('document', $mediaData, $filename)
                                ->post("https://api.telegram.org/bot{$token}/sendDocument", [
                                    'chat_id' => $targetId,
                                    'caption' => $job->message,
                                    'parse_mode' => 'HTML'
                                ]);
                        }
                    } else {
                        $response = \Illuminate\Support\Facades\Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                            'chat_id' => $targetId,
                            'text' => $job->message,
                            'parse_mode' => 'HTML'
                        ]);
                    }

                    if ($response->status() === 429) {
                        $retryAfter = $response->json('parameters.retry_after') ?? 2;
                        sleep($retryAfter);
                        $retry++;
                        continue;
                    }

                    if ($response->successful()) {
                        $success++;
                        $sentSuccessfully = true;
                    } else {
                        $failed++;
                    }
                    break;
                } catch (\Exception $e) {
                    sleep(1);
                    $retry++;
                }
            }

            if (!$sentSuccessfully && $retry === $maxRetries) {
                $failed++;
            }

            // Batch update database progress every 5 targets
            if (($success + $failed) % 5 === 0) {
                \App\Models\BroadcastJob::where('id', $jobId)->update([
                    'sent_count' => $success,
                    'failed_count' => $failed,
                ]);
            }

            // Small delay to prevent hitting rate limits aggressively
            usleep(25000); // 25ms
        }

        \App\Models\BroadcastJob::where('id', $jobId)->update([
            'sent_count' => $success,
            'failed_count' => $failed,
            'status' => 'completed'
        ]);
        return response()->json(['status' => 'success']);
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
                  ->orWhereHas('actor', function($aq) use ($search) {
                      $aq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->paginate(20);

        return view('admin.audit_logs.index', compact('logs'));
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
            return redirect()->back()->with('error', 'Stok pengganti dari seller ini tidak tersedia. Silakan gunakan opsi refund.');
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

            return redirect()->back()->with('success', 'Akun berhasil diganti dengan stok baru dari seller.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Gagal mengganti akun: ' . $e->getMessage());
        }
    }

    public function refundOrder($orderId)
    {
        $order = \App\Models\Order::findOrFail($orderId);

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // 1. Update Order & Payment
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancel_reason = 'Refunded & Dibatalkan oleh Admin';
            $order->save();

            $payment = $order->payment;
            if ($payment) {
                $payment->status = 'cancelled';
                $payment->save();
            }

            // 2. Pull all stock units back to saved_for_verification (karantina)
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

            // 3. Cancel held funds
            \Illuminate\Support\Facades\DB::table('held_funds')
                ->where('order_id', $orderId)
                ->where('status', 'held')
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now()
                ]);

            // 4. Audit Log
            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'action' => 'order_refunded',
                'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                'entity_type' => 'order',
                'entity_id' => $orderId,
                'detail' => "order_ref={$order->order_ref}; held_funds_cancelled",
                'created_at' => now(),
            ]);

            try {
                \App\Services\TelegramService::notifyCustomerOrderCancelled($order, 'Refunded & Dibatalkan oleh Admin');
                \App\Services\TelegramService::updateAdminOrderMessage($order);
            } catch (\Exception $e) {
                \Log::error("Gagal kirim notifikasi Telegram untuk refund order {$order->order_ref}: " . $e->getMessage());
            }

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->back()->with('success', 'Pesanan berhasil direfund. Semua stok dikembalikan ke karantina dan saldo tertahan seller dibatalkan.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses refund: ' . $e->getMessage());
        }
    }

    public function replaceStockBulk(Request $request, $orderId)
    {
        $order = \App\Models\Order::findOrFail($orderId);
        $stockUnitIds = json_decode($request->input('stock_unit_ids', '[]'), true);

        if (!is_array($stockUnitIds) || empty($stockUnitIds)) {
            return redirect()->back()->with('error', 'Tidak ada akun terpilih.');
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
                    return redirect()->back()->with('error', 'Stok pengganti tidak mencukupi untuk semua akun terpilih.');
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

            return redirect()->back()->with('success', "Berhasil mengganti {$replacedCount} akun terpilih.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Gagal mengganti akun: ' . $e->getMessage());
        }
    }

    public function refundBulk(Request $request, $orderId)
    {
        $order = \App\Models\Order::findOrFail($orderId);
        $stockUnitIds = json_decode($request->input('stock_unit_ids', '[]'), true);

        if (!is_array($stockUnitIds) || empty($stockUnitIds)) {
            return redirect()->back()->with('error', 'Tidak ada akun terpilih.');
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $refundedCount = 0;

            foreach ($stockUnitIds as $stockUnitId) {
                $unit = \App\Models\StockUnit::where('id', $stockUnitId)
                    ->where('sold_order_id', $orderId)
                    ->where('is_sold', true)
                    ->first();

                if (!$unit) continue;

                $seller = \App\Models\User::find($unit->seller_id);
                $saveHours = $seller ? ($seller->seller_save_hours ?? 80) : 80;

                // Pull back stock unit to saved_for_verification (karantina)
                $unit->is_sold = false;
                $unit->sold_order_id = null;
                $unit->stock_status = 'saved_for_verification';
                $unit->available_at = now()->addHours($saveHours);
                $unit->save();

                $refundedCount++;
            }

            if ($refundedCount > 0) {
                // Cancel matching number of held funds
                $heldFunds = \App\Models\HeldFund::where('order_id', $orderId)
                    ->where('status', 'held')
                    ->limit($refundedCount)
                    ->get();

                foreach ($heldFunds as $fund) {
                    $fund->status = 'cancelled';
                    $fund->save();
                }

                // Check if there are any remaining stock units sold in this order
                $remainingUnitsCount = \App\Models\StockUnit::where('sold_order_id', $orderId)
                    ->where('is_sold', true)
                    ->count();

                if ($remainingUnitsCount === 0) {
                    $order->status = 'cancelled';
                    $order->cancelled_at = now();
                    $order->cancel_reason = 'Refunded & Dibatalkan penuh oleh Admin (Bulk)';
                    $order->save();

                    $payment = $order->payment;
                    if ($payment) {
                        $payment->status = 'cancelled';
                        $payment->save();
                    }

                    try {
                        \App\Services\TelegramService::notifyCustomerOrderCancelled($order, 'Refunded & Dibatalkan penuh oleh Admin (Bulk)');
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
                    'detail' => "refunded_count={$refundedCount}; remaining_units={$remainingUnitsCount}",
                    'created_at' => now(),
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->back()->with('success', "Berhasil me-refund {$refundedCount} akun terpilih.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses refund sebagian: ' . $e->getMessage());
        }
    }
}
