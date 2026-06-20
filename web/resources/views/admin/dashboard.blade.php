@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page_subtitle', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1">Admin Dashboard</h1>
        <p class="text-muted mb-0">Ringkasan aktivitas toko Anda ({{ $periodLabel }})</p>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm rounded-pill px-3" style="width: auto;" onchange="window.location.href = '{{ route('admin.dashboard') }}?period=' + this.value">
            <option value="24_hours" {{ $period == '24_hours' ? 'selected' : '' }}>24 Jam Terakhir</option>
            <option value="7_days" {{ $period == '7_days' ? 'selected' : '' }}>7 Hari Terakhir</option>
            <option value="30_days" {{ $period == '30_days' ? 'selected' : '' }}>30 Hari Terakhir</option>
            <option value="6_months" {{ $period == '6_months' ? 'selected' : '' }}>6 Bulan Terakhir</option>
        </select>
        <a href="{{ route('admin.products.index') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-2"></i>Kelola Produk
        </a>
    </div>
</div>

{{-- Stat Cards --}}
<div class="row g-3 mb-5">
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="stat-icon bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 42px; height: 42px; font-size: 1.2rem; flex-shrink: 0; margin-bottom: 0 !important;">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="text-truncate">
                    <h6 class="text-muted mb-1 small text-nowrap">Total Pendapatan</h6>
                    <h5 class="fw-bold mb-0 text-nowrap">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h5>
                    <div class="mt-1" style="font-size: 0.75rem;">
                        <span class="{{ $revenueStats['class'] }} fw-bold">
                            <i class="fas {{ $revenueStats['icon'] }} me-1"></i>{{ $revenueStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted">vs periode lalu</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="stat-icon rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 42px; height: 42px; font-size: 1.2rem; flex-shrink: 0; background-color: rgba(111, 66, 193, 0.15) !important; color: var(--bs-purple, #6f42c1) !important; margin-bottom: 0 !important;">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="text-truncate">
                    <h6 class="text-muted mb-1 small text-nowrap">Komisi Platform</h6>
                    <h5 class="fw-bold mb-0 text-nowrap">Rp {{ number_format($platformCommission, 0, ',', '.') }}</h5>
                    <div class="mt-1" style="font-size: 0.75rem;">
                        <span class="{{ $commissionStats['class'] }} fw-bold">
                            <i class="fas {{ $commissionStats['icon'] }} me-1"></i>{{ $commissionStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted">vs periode lalu</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="stat-icon bg-danger-subtle text-danger rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 42px; height: 42px; font-size: 1.2rem; flex-shrink: 0; margin-bottom: 0 !important;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="text-truncate">
                    <h6 class="text-muted mb-1 small text-nowrap">Pendapatan Admin</h6>
                    <h5 class="fw-bold mb-0 text-nowrap">Rp {{ number_format($adminEarnings, 0, ',', '.') }}</h5>
                    <div class="mt-1" style="font-size: 0.75rem;">
                        <span class="{{ $adminEarningsStats['class'] }} fw-bold">
                            <i class="fas {{ $adminEarningsStats['icon'] }} me-1"></i>{{ $adminEarningsStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted">vs periode lalu</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center text-truncate">
                    <div class="stat-icon rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 42px; height: 42px; font-size: 1.2rem; flex-shrink: 0; background-color: rgba(32, 201, 151, 0.15) !important; color: #20c997 !important; margin-bottom: 0 !important;">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="text-truncate">
                        <h6 class="text-muted mb-1 small text-nowrap">Pendapatan Seller</h6>
                        <h5 class="fw-bold mb-0 text-nowrap">Rp {{ number_format($totalSellerEarnings, 0, ',', '.') }}</h5>
                        <div class="mt-1" style="font-size: 0.75rem;">
                            <span class="{{ $sellerEarningsStats['class'] }} fw-bold">
                                <i class="fas {{ $sellerEarningsStats['icon'] }} me-1"></i>{{ $sellerEarningsStats['formatted_percent'] }}
                            </span>
                            <span class="text-muted">vs periode lalu</span>
                        </div>
                    </div>
                </div>
                <div class="ms-2">
                    <a href="{{ route('admin.sellers.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1">Detail</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="stat-icon bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 42px; height: 42px; font-size: 1.2rem; flex-shrink: 0; margin-bottom: 0 !important;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="text-truncate">
                    <h6 class="text-muted mb-1 small text-nowrap">Total Order</h6>
                    <h5 class="fw-bold mb-0 text-nowrap">{{ number_format($totalOrders, 0, ',', '.') }}</h5>
                    <div class="mt-1" style="font-size: 0.75rem;">
                        <span class="{{ $ordersStats['class'] }} fw-bold">
                            <i class="fas {{ $ordersStats['icon'] }} me-1"></i>{{ $ordersStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted">vs periode lalu</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="stat-icon bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 42px; height: 42px; font-size: 1.2rem; flex-shrink: 0; margin-bottom: 0 !important;">
                    <i class="fas fa-box"></i>
                </div>
                <div class="text-truncate">
                    <h6 class="text-muted mb-1 small text-nowrap">Total Produk</h6>
                    <h5 class="fw-bold mb-0 text-nowrap">{{ number_format($totalProducts, 0, ',', '.') }}</h5>
                    <div class="mt-1" style="font-size: 0.75rem;">
                        <span class="{{ $productsStats['class'] }} fw-bold">
                            <i class="fas {{ $productsStats['icon'] }} me-1"></i>{{ $productsStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted">vs periode lalu</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="stat-icon bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 42px; height: 42px; font-size: 1.2rem; flex-shrink: 0; margin-bottom: 0 !important;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="text-truncate">
                    <h6 class="text-muted mb-1 small text-nowrap">Total User</h6>
                    <h5 class="fw-bold mb-0 text-nowrap">{{ number_format($totalUsers, 0, ',', '.') }}</h5>
                    <div class="d-flex gap-2 mt-1" style="font-size: 0.75rem;">
                        <span class="badge bg-primary-subtle text-primary rounded-pill"><i class="fas fa-desktop me-1"></i>Web: {{ $webUsersCount }}</span>
                        <span class="badge bg-success-subtle text-success rounded-pill"><i class="fab fa-telegram-plane me-1"></i>TG: {{ $tgUsersCount }}</span>
                    </div>
                    <div class="mt-1" style="font-size: 0.75rem;">
                        <span class="{{ $usersStats['class'] }} fw-bold">
                            <i class="fas {{ $usersStats['icon'] }} me-1"></i>{{ $usersStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted">vs periode lalu</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts & Operational Analytics --}}
<div class="row g-4 mb-5">
    {{-- Chart --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-line text-primary me-2"></i>Tren Pendapatan Harian</h5>
                    <p class="text-muted small mb-0">Statistik omzet penjualan dalam {{ $periodLabel }}</p>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div id="salesChart" style="min-height: 350px;">
                    <!-- Skeleton Chart Loading Placeholder -->
                    <div class="skeleton-chart d-flex flex-column justify-content-between h-100 p-3" style="min-height: 350px;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="skeleton-shimmer" style="width: 120px; height: 16px;"></div>
                            <div class="skeleton-shimmer" style="width: 80px; height: 16px;"></div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between flex-grow-1 mb-3" style="height: 200px; gap: 8px;">
                            <div class="skeleton-shimmer flex-grow-1" style="height: 40%; border-radius: 4px;"></div>
                            <div class="skeleton-shimmer flex-grow-1" style="height: 60%; border-radius: 4px;"></div>
                            <div class="skeleton-shimmer flex-grow-1" style="height: 35%; border-radius: 4px;"></div>
                            <div class="skeleton-shimmer flex-grow-1" style="height: 75%; border-radius: 4px;"></div>
                            <div class="skeleton-shimmer flex-grow-1" style="height: 50%; border-radius: 4px;"></div>
                            <div class="skeleton-shimmer flex-grow-1" style="height: 90%; border-radius: 4px;"></div>
                            <div class="skeleton-shimmer flex-grow-1" style="height: 45%; border-radius: 4px;"></div>
                            <div class="skeleton-shimmer flex-grow-1" style="height: 80%; border-radius: 4px;"></div>
                            <div class="skeleton-shimmer flex-grow-1" style="height: 65%; border-radius: 4px;"></div>
                            <div class="skeleton-shimmer flex-grow-1" style="height: 70%; border-radius: 4px;"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div class="skeleton-shimmer" style="width: 40px; height: 12px;"></div>
                            <div class="skeleton-shimmer" style="width: 40px; height: 12px;"></div>
                            <div class="skeleton-shimmer" style="width: 40px; height: 12px;"></div>
                            <div class="skeleton-shimmer" style="width: 40px; height: 12px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Order Completion Rate --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-pie text-success me-2"></i>Rasio Status Order</h5>
                <p class="text-muted small mb-0">Persentase sukses vs batal/expired</p>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center px-4 pb-4">
                <div id="completionChart" style="min-height: 250px; display: flex; align-items: center; justify-content: center; width: 100%;">
                    <!-- Skeleton Donut Loading Placeholder -->
                    <div class="skeleton-donut skeleton-shimmer rounded-circle" style="width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; position: relative;">
                        <div class="bg-body rounded-circle" style="width: 120px; height: 120px; position: absolute; top: 30px; left: 30px;"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-around w-100 mt-3 border-top pt-3">
                    <div class="text-center">
                        <span class="d-block text-muted small">Sukses</span>
                        <strong class="text-success">{{ $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100) : 0 }}%</strong>
                    </div>
                    <div class="text-center">
                        <span class="d-block text-muted small">Batal</span>
                        <strong class="text-danger">{{ $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100) : 0 }}%</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Recent Orders --}}
<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
        <h2 class="h5 fw-bold mb-0">Pesanan Terbaru</h2>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Lihat Semua</a>
    </div>
    <div class="card-body p-0">
        @if($recentOrders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">No. Order</th>
                        <th class="py-3 border-0">Pelanggan</th>
                        <th class="py-3 border-0">Produk</th>
                        <th class="py-3 border-0">Total</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentOrders as $order)
                    <tr>
                        <td class="px-4 fw-bold text-primary">{{ $order->reference }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold">{{ $order->user->full_name ?? $order->user->username ?? 'User' }}</span>
                                <span class="small text-muted">{{ $order->user->telegram_id }}</span>
                            </div>
                        </td>
                        <td>{{ Str::limit($order->product->name ?? '-', 25) }}</td>
                        <td class="fw-bold">{{ $order->formatted_total }}</td>
                        <td>
                            <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3">
                                {{ $order->status_label }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $order->created_at->format('d M Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-receipt text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada pesanan.</p>
        </div>
        @endif
</div>
@push('scripts')
{{-- Load ApexCharts --}}
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Theme config
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const chartTheme = {
            mode: isDark ? 'dark' : 'light'
        };

        // 1. Sales Trend Line/Area Chart
        const salesOptions = {
            series: [{
                name: 'Pendapatan (Rp)',
                data: @json($chartData)
            }],
            chart: {
                height: 350,
                type: 'area',
                toolbar: { show: false },
                zoom: { enabled: false },
                background: 'transparent'
            },
            theme: chartTheme,
            colors: ['#0d6efd'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            grid: {
                borderColor: isDark ? '#2d3748' : '#e2e8f0',
                strokeDashArray: 4
            },
            xaxis: {
                categories: @json($chartLabels),
                labels: {
                    style: { colors: isDark ? '#a0aec0' : '#4a5568' }
                }
            },
            yaxis: {
                labels: {
                    formatter: function (value) {
                        return "Rp " + value.toLocaleString('id-ID');
                    },
                    style: { colors: isDark ? '#a0aec0' : '#4a5568' }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return "Rp " + val.toLocaleString('id-ID');
                    }
                }
            }
        };

        const salesChart = new ApexCharts(document.querySelector("#salesChart"), salesOptions);
        salesChart.render();

        // 2. Status Distribution Donut Chart
        const completionOptions = {
            series: [{{ $deliveredOrders }}, {{ $cancelledOrders }}],
            chart: {
                type: 'donut',
                height: 250,
                background: 'transparent'
            },
            labels: ['Sukses', 'Batal/Expired'],
            colors: ['#198754', '#dc3545'],
            theme: chartTheme,
            legend: { show: false },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '75%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                color: isDark ? '#a0aec0' : '#4a5568'
                            },
                            value: {
                                show: true,
                                color: isDark ? '#ffffff' : '#0f172a',
                                formatter: function (val) {
                                    return val;
                                }
                            },
                            total: {
                                show: true,
                                label: 'Total Pesanan',
                                color: isDark ? '#a0aec0' : '#4a5568',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            }
        };

        const completionChart = new ApexCharts(document.querySelector("#completionChart"), completionOptions);
        completionChart.render();

        // Respond to theme toggle events
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-bs-theme') {
                    const newTheme = document.documentElement.getAttribute('data-bs-theme');
                    const isNewDark = newTheme === 'dark';
                    
                    salesChart.updateOptions({
                        theme: { mode: newTheme },
                        grid: { borderColor: isNewDark ? '#2d3748' : '#e2e8f0' },
                        xaxis: { labels: { style: { colors: isNewDark ? '#a0aec0' : '#4a5568' } } },
                        yaxis: { labels: { style: { colors: isNewDark ? '#a0aec0' : '#4a5568' } } }
                    });
                    
                    completionChart.updateOptions({
                        theme: { mode: newTheme },
                        plotOptions: {
                            pie: {
                                donut: {
                                    labels: {
                                        name: { color: isNewDark ? '#a0aec0' : '#4a5568' },
                                        value: { color: isNewDark ? '#ffffff' : '#0f172a' },
                                        total: { color: isNewDark ? '#a0aec0' : '#4a5568' }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });
    });
</script>
@endpush
@endsection
