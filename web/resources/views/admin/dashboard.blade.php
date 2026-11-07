@extends('layouts.app')

@section('title', __('Admin Dashboard'))
@section('page_subtitle', __('Dashboard'))

@section('content')
<!-- Google Cloud / Material 3 Flat Console Header & Filter Bar -->
<div class="m3-console-header mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 pb-3 border-bottom" style="border-color: var(--outline-color) !important;">
        <div>
            <div class="d-inline-flex align-items-center gap-1.5 px-2.5 py-1 rounded-pill mb-2 fw-semibold" style="background: var(--primary-container); color: var(--on-primary-container); font-size: 0.72rem; letter-spacing: 0.04em; text-transform: uppercase;">
                <i class="fas fa-user-shield"></i>
                <span>{{ __('Admin Portal Overview') }}</span>
            </div>
            <h1 class="h4 fw-bold mb-1" style="color: var(--on-surface);">
                {{ __('Admin Dashboard') }}
            </h1>
            <p class="mb-0 small" style="color: var(--on-surface-variant);">
                {{ __('Ringkasan aktivitas toko Anda (:period)', ['period' => $periodLabel]) }}
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('admin.products.index') }}" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold">
                <i class="fas fa-plus me-1.5"></i>{{ __('Kelola Produk') }}
            </a>
            <a href="{{ route('admin.stock.index') }}" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-semibold">
                <i class="fas fa-cubes me-1.5"></i>{{ __('Kelola Stok') }}
            </a>
        </div>
    </div>

    <!-- Filter Form Integrated in Flat Console Surface -->
    <div class="pt-3">
        <form action="{{ route('admin.dashboard') }}" method="GET" class="row g-2 g-md-3 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-box"></i></span>
                    <select name="product_id" class="form-select form-select-sm">
                        <option value="">{{ __('Semua Produk') }}</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" {{ $productId == $p->id ? 'selected' : '' }}>
                                {{ $p->is_suspended ? '🔴' : '✅' }} {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                    <select name="period" class="form-select form-select-sm">
                        <option value="24_hours" {{ $period == '24_hours' ? 'selected' : '' }}>{{ __('24 Jam Terakhir') }}</option>
                        <option value="7_days" {{ $period == '7_days' ? 'selected' : '' }}>{{ __('7 Hari Terakhir') }}</option>
                        <option value="30_days" {{ $period == '30_days' ? 'selected' : '' }}>{{ __('30 Hari Terakhir') }}</option>
                        <option value="6_months" {{ $period == '6_months' ? 'selected' : '' }}>{{ __('6 Bulan Terakhir') }}</option>
                        <option value="all_time" {{ $period == 'all_time' ? 'selected' : '' }}>{{ __('Sepanjang Waktu') }}</option>
                    </select>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 flex-fill fw-semibold">
                    <i class="fas fa-filter me-1"></i> {{ __('Filter') }}
                </button>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-light rounded-pill px-3 flex-fill fw-semibold">
                    <i class="fas fa-sync me-1"></i> {{ __('Reset') }}
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Flat Material 3 KPI Metric Grid -->
<div class="row g-3 mb-4">
    <!-- 1. Total Revenue -->
    <div class="col-6 col-md-4 col-xl-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="m3-metric-icon bg-primary-subtle text-primary me-3">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="text-truncate flex-grow-1">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.04em; font-size: 0.7rem;">{{ __('Total Pendapatan') }}</div>
                    <h6 class="fw-bold mb-0 text-nowrap text-body fs-6 fs-md-5">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h6>
                    <div class="mt-1" style="font-size: 0.72rem;">
                        <span class="{{ $revenueStats['class'] }} fw-bold">
                            <i class="fas {{ $revenueStats['icon'] }} me-1"></i>{{ $revenueStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted d-none d-sm-inline">{{ __('vs periode lalu') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Platform Commission -->
    <div class="col-6 col-md-4 col-xl-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="m3-metric-icon me-3" style="background-color: rgba(111, 66, 193, 0.12); color: #6f42c1;">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="text-truncate flex-grow-1">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.04em; font-size: 0.7rem;">{{ __('Komisi Platform') }}</div>
                    <h6 class="fw-bold mb-0 text-nowrap text-body fs-6 fs-md-5">Rp {{ number_format($platformCommission, 0, ',', '.') }}</h6>
                    <div class="mt-1" style="font-size: 0.72rem;">
                        <span class="{{ $commissionStats['class'] }} fw-bold">
                            <i class="fas {{ $commissionStats['icon'] }} me-1"></i>{{ $commissionStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted d-none d-sm-inline">{{ __('vs periode lalu') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Admin Earnings -->
    <div class="col-6 col-md-4 col-xl-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="m3-metric-icon bg-danger-subtle text-danger me-3">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="text-truncate flex-grow-1">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.04em; font-size: 0.7rem;">{{ __('Pendapatan Admin') }}</div>
                    <h6 class="fw-bold mb-0 text-nowrap text-body fs-6 fs-md-5">Rp {{ number_format($adminEarnings, 0, ',', '.') }}</h6>
                    <div class="mt-1" style="font-size: 0.72rem;">
                        <span class="{{ $adminEarningsStats['class'] }} fw-bold">
                            <i class="fas {{ $adminEarningsStats['icon'] }} me-1"></i>{{ $adminEarningsStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted d-none d-sm-inline">{{ __('vs periode lalu') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Seller Earnings -->
    <div class="col-6 col-md-4 col-xl-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center text-truncate me-1 me-md-2">
                    <div class="m3-metric-icon bg-success-subtle text-success me-3">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="text-truncate">
                        <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.04em; font-size: 0.7rem;">{{ __('Pendapatan Seller') }}</div>
                        <h6 class="fw-bold mb-0 text-nowrap text-body fs-6 fs-md-5">Rp {{ number_format($totalSellerEarnings, 0, ',', '.') }}</h6>
                        <div class="mt-1" style="font-size: 0.72rem;">
                            <span class="{{ $sellerEarningsStats['class'] }} fw-bold">
                                <i class="fas {{ $sellerEarningsStats['icon'] }} me-1"></i>{{ $sellerEarningsStats['formatted_percent'] }}
                            </span>
                            <span class="text-muted d-none d-sm-inline">{{ __('vs periode lalu') }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-none d-md-block">
                    <a href="{{ route('admin.sellers.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold">{{ __('Detail') }}</a>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Total Orders -->
    <div class="col-6 col-md-4 col-xl-4">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="m3-metric-icon bg-success-subtle text-success me-3">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="text-truncate flex-grow-1">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.04em; font-size: 0.7rem;">{{ __('Total Order') }}</div>
                    <h4 class="fw-bold mb-0 text-nowrap text-body fs-5 fs-md-4">{{ number_format($totalOrders, 0, ',', '.') }}</h4>
                    <div class="mt-1" style="font-size: 0.72rem;">
                        <span class="{{ $ordersStats['class'] }} fw-bold">
                            <i class="fas {{ $ordersStats['icon'] }} me-1"></i>{{ $ordersStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted d-none d-sm-inline">{{ __('vs periode lalu') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Total Products -->
    <div class="col-6 col-md-4 col-xl-4">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="m3-metric-icon bg-info-subtle text-info me-3">
                    <i class="fas fa-box"></i>
                </div>
                <div class="text-truncate flex-grow-1">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.04em; font-size: 0.7rem;">{{ __('Total Produk') }}</div>
                    <h4 class="fw-bold mb-0 text-nowrap text-body fs-5 fs-md-4">{{ number_format($totalProducts, 0, ',', '.') }}</h4>
                    <div class="mt-1" style="font-size: 0.72rem;">
                        <span class="{{ $productsStats['class'] }} fw-bold">
                            <i class="fas {{ $productsStats['icon'] }} me-1"></i>{{ $productsStats['formatted_percent'] }}
                        </span>
                        <span class="text-muted d-none d-sm-inline">{{ __('vs periode lalu') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Total Users -->
    <div class="col-12 col-md-4 col-xl-4">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="m3-metric-icon bg-warning-subtle text-warning-emphasis me-3">
                    <i class="fas fa-users"></i>
                </div>
                <div class="text-truncate flex-grow-1">
                    <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.04em; font-size: 0.7rem;">{{ __('Total User') }}</div>
                    <h4 class="fw-bold mb-0 text-nowrap text-body fs-5 fs-md-4">{{ number_format($totalUsers, 0, ',', '.') }}</h4>
                    <div class="d-flex gap-2 mt-1" style="font-size: 0.72rem;">
                        <span class="badge bg-primary-subtle text-primary rounded-pill"><i class="fas fa-desktop me-1"></i>Web: {{ $webUsersCount }}</span>
                        <span class="badge bg-success-subtle text-success rounded-pill"><i class="fab fa-telegram-plane me-1"></i>TG: {{ $tgUsersCount }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Operational Analytics -->
<div class="row g-4 mb-4">
    <!-- Chart: Sales Trend -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 px-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-line text-primary me-2"></i>{{ __('Tren Pendapatan Harian') }}</h5>
                    <p class="text-muted small mb-0">{{ __('Statistik omzet penjualan dalam :period', ['period' => $periodLabel]) }}</p>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div id="salesChart" style="min-height: 350px;">
                    <!-- Skeleton Chart Loading Placeholder -->
                </div>
            </div>
        </div>
    </div>

    <!-- Chart: Order Status Distribution -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 px-4 pb-2">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-pie text-success me-2"></i>{{ __('Rasio Status Order') }}</h5>
                <p class="text-muted small mb-0">{{ __('Persentase sukses vs batal/expired') }}</p>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center px-4 pb-4">
                <div id="completionChart" style="min-height: 250px; display: flex; align-items: center; justify-content: center; width: 100%;">
                    <div class="skeleton-donut skeleton-shimmer rounded-circle" style="width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; position: relative;">
                        <div class="bg-body rounded-circle" style="width: 120px; height: 120px; position: absolute; top: 30px; left: 30px;"></div>
                    </div>
                </div>
                <div class="d-flex justify-content-around w-100 mt-3 border-top pt-3">
                    <div class="text-center">
                        <span class="d-block text-muted small">{{ __('Sukses') }}</span>
                        <strong class="text-success">{{ $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100) : 0 }}%</strong>
                    </div>
                    <div class="text-center">
                        <span class="d-block text-muted small">{{ __('Batal') }}</span>
                        <strong class="text-danger">{{ $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100) : 0 }}%</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="card overflow-hidden mb-4">
    <div class="card-header bg-transparent pt-3 px-4 pb-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-body"><i class="fas fa-history text-primary me-2"></i>{{ __('Pesanan Terbaru') }}</h5>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">{{ __('Lihat Semua') }}</a>
    </div>
    <div class="card-body p-0">
        @if($recentOrders->count() > 0)
        <!-- Desktop Table View -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="px-4 py-3">{{ __('No. Order') }}</th>
                        <th class="py-3">{{ __('Pelanggan') }}</th>
                        <th class="py-3">{{ __('Produk') }}</th>
                        <th class="py-3">{{ __('Total') }}</th>
                        <th class="py-3 text-center">{{ __('Status') }}</th>
                        <th class="py-3 text-end pe-4">{{ __('Tanggal') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentOrders as $order)
                    <tr>
                        <td class="px-4 fw-bold text-primary font-monospace">{{ $order->reference }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-body">{{ $order->user->full_name ?? $order->user->username ?? 'User' }}</span>
                                <span class="small text-muted">TG: {{ $order->user->telegram_id ?: '-' }}</span>
                            </div>
                        </td>
                        <td class="fw-semibold text-body">{{ Str::limit($order->product->name ?? '-', 25) }}</td>
                        <td class="fw-bold text-body">{{ $order->formatted_total }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-1 fw-bold">
                                {{ $order->status_label }}
                            </span>
                        </td>
                        <td class="text-secondary small text-end pe-4">{{ $order->created_at->format('d M Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="d-md-none p-3 d-flex flex-column gap-2">
            @foreach($recentOrders as $order)
            <div class="mobile-activity-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold font-monospace text-primary small">{{ $order->reference }}</span>
                    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-2 py-1 small fw-bold">
                        {{ $order->status_label }}
                    </span>
                </div>
                <div class="mb-2">
                    <div class="fw-bold text-body">{{ Str::limit($order->product->name ?? '-', 35) }}</div>
                    <div class="small text-muted">
                        <i class="fas fa-user me-1"></i>{{ $order->user->full_name ?? $order->user->username ?? 'User' }}
                        @if($order->user && $order->user->telegram_id)
                            <span class="ms-1">(TG: {{ $order->user->telegram_id }})</span>
                        @endif
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <span class="small text-muted">{{ $order->created_at->format('d M Y H:i') }}</span>
                    <span class="fw-bold text-body">{{ $order->formatted_total }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-receipt text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">{{ __('Belum ada pesanan.') }}</p>
        </div>
        @endif
    </div>
</div>

@push('scripts')
{{-- Load ApexCharts --}}
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const chartTheme = {
            mode: isDark ? 'dark' : 'light'
        };

        // 1. Sales Trend Line/Area Chart (Material 3 / GCP Palette)
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
                background: 'transparent',
                fontFamily: 'inherit'
            },
            theme: chartTheme,
            colors: [isDark ? '#a8c7fa' : '#0b57d0'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2.5 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.28,
                    opacityTo: 0.02,
                    stops: [0, 95, 100]
                }
            },
            grid: {
                borderColor: isDark ? '#30363d' : '#dadce0',
                strokeDashArray: 4
            },
            xaxis: {
                categories: @json($chartLabels),
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: {
                    style: { colors: isDark ? '#9aa0a6' : '#5f6368' }
                }
            },
            yaxis: {
                labels: {
                    formatter: function (value) {
                        return "Rp " + value.toLocaleString('id-ID');
                    },
                    style: { colors: isDark ? '#9aa0a6' : '#5f6368' }
                }
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                y: {
                    formatter: function (val) {
                        return "Rp " + val.toLocaleString('id-ID');
                    }
                }
            }
        };

        const salesChart = new ApexCharts(document.querySelector("#salesChart"), salesOptions);
        salesChart.render();

        // 2. Status Distribution Donut Chart (Material 3 Semantic Colors)
        const completionOptions = {
            series: [{{ $deliveredOrders }}, {{ $cancelledOrders }}],
            chart: {
                type: 'donut',
                height: 250,
                background: 'transparent',
                fontFamily: 'inherit'
            },
            labels: ['Sukses', 'Batal/Expired'],
            colors: [isDark ? '#75b798' : '#146c2e', isDark ? '#ea868f' : '#ba1a1a'],
            theme: chartTheme,
            legend: { show: false },
            dataLabels: { enabled: false },
            stroke: {
                width: 2,
                colors: [isDark ? '#161b22' : '#ffffff']
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '74%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                color: isDark ? '#9aa0a6' : '#5f6368'
                            },
                            value: {
                                show: true,
                                color: isDark ? '#e3e3e3' : '#1f1f1f',
                                formatter: function (val) {
                                    return val;
                                }
                            },
                            total: {
                                show: true,
                                label: 'Total Pesanan',
                                color: isDark ? '#9aa0a6' : '#5f6368',
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

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-bs-theme') {
                    const newTheme = document.documentElement.getAttribute('data-bs-theme');
                    const isNewDark = newTheme === 'dark';

                    salesChart.updateOptions({
                        theme: { mode: newTheme },
                        colors: [isNewDark ? '#a8c7fa' : '#0b57d0'],
                        grid: { borderColor: isNewDark ? '#30363d' : '#dadce0' },
                        xaxis: { labels: { style: { colors: isNewDark ? '#9aa0a6' : '#5f6368' } } },
                        yaxis: { labels: { style: { colors: isNewDark ? '#9aa0a6' : '#5f6368' } } },
                        tooltip: { theme: isNewDark ? 'dark' : 'light' }
                    });

                    completionChart.updateOptions({
                        theme: { mode: newTheme },
                        colors: [isNewDark ? '#75b798' : '#146c2e', isNewDark ? '#ea868f' : '#ba1a1a'],
                        stroke: { colors: [isNewDark ? '#161b22' : '#ffffff'] },
                        plotOptions: {
                            pie: {
                                donut: {
                                    labels: {
                                        name: { color: isNewDark ? '#9aa0a6' : '#5f6368' },
                                        value: { color: isNewDark ? '#e3e3e3' : '#1f1f1f' },
                                        total: { color: isNewDark ? '#9aa0a6' : '#5f6368' }
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
