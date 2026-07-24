@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page_subtitle', 'Dashboard')

@push('styles')
<style>
    .lift-hover {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .lift-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 14px 28px rgba(0, 0, 0, 0.1) !important;
    }
    .hero-admin-banner {
        background: linear-gradient(135deg, #0d47a1 0%, #1565c0 50%, #1e88e5 100%);
        min-height: 220px;
        border-radius: 24px;
    }
    .hero-pattern {
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        opacity: 0.12;
        background-image: radial-gradient(#ffffff 1.5px, transparent 1.5px);
        background-size: 22px 22px;
    }
    .floating-stats-container {
        margin-top: -55px;
    }
    @media (max-width: 767.98px) {
        .floating-stats-container {
            margin-top: 1rem;
        }
    }
</style>
@endpush

@section('content')
<!-- Hero Section (Gradient & Abstract Pattern) -->
<div class="position-relative mb-5">
    <div class="hero-admin-banner p-4 p-md-5 text-white shadow-sm overflow-hidden position-relative">
        <div class="hero-pattern"></div>
        
        <div class="position-relative z-1 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-white text-primary rounded-pill px-3 py-2 mb-2 text-uppercase tracking-wider fw-bold shadow-sm" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <i class="fas fa-user-shield me-1 text-warning"></i> {{ __('Admin Portal Overview') }}
                </span>
                <h1 class="fw-bold mb-2 text-white fs-2 fs-md-1">
                    {{ __('Admin Dashboard') }}
                </h1>
                <p class="mb-0 fs-6 opacity-85 fw-light text-white">
                    {{ __('Ringkasan aktivitas toko Anda (:period)', ['period' => $periodLabel]) }}
                </p>
            </div>
            
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('admin.products.index') }}" class="btn btn-light text-primary fw-bold rounded-pill px-4 py-2 shadow-sm lift-hover">
                    <i class="fas fa-plus me-2"></i>{{ __('Kelola Produk') }}
                </a>
                <a href="{{ route('admin.stock.index') }}" class="btn btn-outline-light rounded-pill px-4 py-2 fw-semibold">
                    <i class="fas fa-cubes me-2"></i>{{ __('Kelola Stok') }}
                </a>
            </div>
        </div>

        <!-- Filter Form Integrated in Banner Footer -->
        <div class="mt-4 pt-3 border-top border-white border-opacity-25 position-relative z-1">
            <form action="{{ route('admin.dashboard') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white bg-opacity-10 text-white border-white border-opacity-25"><i class="fas fa-box"></i></span>
                        <select name="product_id" class="form-select form-select-sm bg-white bg-opacity-20 text-white border-white border-opacity-25 shadow-none">
                            <option value="" class="text-dark">{{ __('Semua Produk') }}</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ $productId == $p->id ? 'selected' : '' }} class="text-dark">
                                    {{ $p->is_suspended ? '🔴' : '✅' }} {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white bg-opacity-10 text-white border-white border-opacity-25"><i class="fas fa-calendar-alt"></i></span>
                        <select name="period" class="form-select form-select-sm bg-white bg-opacity-20 text-white border-white border-opacity-25 shadow-none">
                            <option value="24_hours" {{ $period == '24_hours' ? 'selected' : '' }} class="text-dark">{{ __('24 Jam Terakhir') }}</option>
                            <option value="7_days" {{ $period == '7_days' ? 'selected' : '' }} class="text-dark">{{ __('7 Hari Terakhir') }}</option>
                            <option value="30_days" {{ $period == '30_days' ? 'selected' : '' }} class="text-dark">{{ __('30 Hari Terakhir') }}</option>
                            <option value="6_months" {{ $period == '6_months' ? 'selected' : '' }} class="text-dark">{{ __('6 Bulan Terakhir') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-light text-primary fw-bold rounded-pill px-3 flex-fill">
                        <i class="fas fa-search me-1"></i> {{ __('Filter') }}
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-light rounded-pill px-3 flex-fill">
                        <i class="fas fa-sync me-1"></i> {{ __('Reset') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Stat Cards -->
    <div class="container-fluid px-2 px-md-3 floating-stats-container">
        <div class="row g-3">
            <!-- 1. Total Revenue -->
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="stat-icon bg-primary-subtle text-primary rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.3rem;">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="text-truncate flex-grow-1">
                            <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">{{ __('Total Pendapatan') }}</div>
                            <h5 class="fw-bold mb-0 text-nowrap text-body">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h5>
                            <div class="mt-1" style="font-size: 0.75rem;">
                                <span class="{{ $revenueStats['class'] }} fw-bold">
                                    <i class="fas {{ $revenueStats['icon'] }} me-1"></i>{{ $revenueStats['formatted_percent'] }}
                                </span>
                                <span class="text-muted">{{ __('vs periode lalu') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Platform Commission -->
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="stat-icon rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.3rem; background-color: rgba(111, 66, 193, 0.12); color: #6f42c1;">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="text-truncate flex-grow-1">
                            <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">{{ __('Komisi Platform') }}</div>
                            <h5 class="fw-bold mb-0 text-nowrap text-body">Rp {{ number_format($platformCommission, 0, ',', '.') }}</h5>
                            <div class="mt-1" style="font-size: 0.75rem;">
                                <span class="{{ $commissionStats['class'] }} fw-bold">
                                    <i class="fas {{ $commissionStats['icon'] }} me-1"></i>{{ $commissionStats['formatted_percent'] }}
                                </span>
                                <span class="text-muted">{{ __('vs periode lalu') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Admin Earnings -->
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="stat-icon bg-danger-subtle text-danger rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.3rem;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="text-truncate flex-grow-1">
                            <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">{{ __('Pendapatan Admin') }}</div>
                            <h5 class="fw-bold mb-0 text-nowrap text-body">Rp {{ number_format($adminEarnings, 0, ',', '.') }}</h5>
                            <div class="mt-1" style="font-size: 0.75rem;">
                                <span class="{{ $adminEarningsStats['class'] }} fw-bold">
                                    <i class="fas {{ $adminEarningsStats['icon'] }} me-1"></i>{{ $adminEarningsStats['formatted_percent'] }}
                                </span>
                                <span class="text-muted">{{ __('vs periode lalu') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Seller Earnings -->
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between w-100">
                        <div class="d-flex align-items-center text-truncate me-2">
                            <div class="stat-icon rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.3rem; background-color: rgba(32, 201, 151, 0.12); color: #20c997;">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="text-truncate">
                                <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">{{ __('Pendapatan Seller') }}</div>
                                <h5 class="fw-bold mb-0 text-nowrap text-body">Rp {{ number_format($totalSellerEarnings, 0, ',', '.') }}</h5>
                                <div class="mt-1" style="font-size: 0.75rem;">
                                    <span class="{{ $sellerEarningsStats['class'] }} fw-bold">
                                        <i class="fas {{ $sellerEarningsStats['icon'] }} me-1"></i>{{ $sellerEarningsStats['formatted_percent'] }}
                                    </span>
                                    <span class="text-muted">{{ __('vs periode lalu') }}</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <a href="{{ route('admin.sellers.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-bold">{{ __('Detail') }}</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Total Orders -->
            <div class="col-sm-6 col-lg-4 col-xl-4">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="stat-icon bg-success-subtle text-success rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.3rem;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="text-truncate flex-grow-1">
                            <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">{{ __('Total Order') }}</div>
                            <h4 class="fw-bold mb-0 text-nowrap text-body">{{ number_format($totalOrders, 0, ',', '.') }}</h4>
                            <div class="mt-1" style="font-size: 0.75rem;">
                                <span class="{{ $ordersStats['class'] }} fw-bold">
                                    <i class="fas {{ $ordersStats['icon'] }} me-1"></i>{{ $ordersStats['formatted_percent'] }}
                                </span>
                                <span class="text-muted">{{ __('vs periode lalu') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Total Products -->
            <div class="col-sm-6 col-lg-4 col-xl-4">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="stat-icon bg-info-subtle text-info rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.3rem;">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="text-truncate flex-grow-1">
                            <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">{{ __('Total Produk') }}</div>
                            <h4 class="fw-bold mb-0 text-nowrap text-body">{{ number_format($totalProducts, 0, ',', '.') }}</h4>
                            <div class="mt-1" style="font-size: 0.75rem;">
                                <span class="{{ $productsStats['class'] }} fw-bold">
                                    <i class="fas {{ $productsStats['icon'] }} me-1"></i>{{ $productsStats['formatted_percent'] }}
                                </span>
                                <span class="text-muted">{{ __('vs periode lalu') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. Total Users -->
            <div class="col-sm-6 col-lg-4 col-xl-4">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="stat-icon bg-warning-subtle text-warning-emphasis rounded-3 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 1.3rem;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="text-truncate flex-grow-1">
                            <div class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.5px;">{{ __('Total User') }}</div>
                            <h4 class="fw-bold mb-0 text-nowrap text-body">{{ number_format($totalUsers, 0, ',', '.') }}</h4>
                            <div class="d-flex gap-2 mt-1" style="font-size: 0.75rem;">
                                <span class="badge bg-primary-subtle text-primary rounded-pill"><i class="fas fa-desktop me-1"></i>Web: {{ $webUsersCount }}</span>
                                <span class="badge bg-success-subtle text-success rounded-pill"><i class="fab fa-telegram-plane me-1"></i>TG: {{ $tgUsersCount }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Operational Analytics -->
<div class="row g-4 mb-5">
    <!-- Chart: Sales Trend -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
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
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
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
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-body"><i class="fas fa-history text-primary me-2"></i>{{ __('Pesanan Terbaru') }}</h5>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">{{ __('Lihat Semua') }}</a>
    </div>
    <div class="card-body p-0">
        @if($recentOrders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                        <th class="px-4 py-3 border-0">{{ __('No. Order') }}</th>
                        <th class="py-3 border-0">{{ __('Pelanggan') }}</th>
                        <th class="py-3 border-0">{{ __('Produk') }}</th>
                        <th class="py-3 border-0">{{ __('Total') }}</th>
                        <th class="py-3 border-0 text-center">{{ __('Status') }}</th>
                        <th class="py-3 border-0 text-end pe-4">{{ __('Tanggal') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentOrders as $order)
                    <tr>
                        <td class="px-4 fw-bold text-primary">{{ $order->reference }}</td>
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
