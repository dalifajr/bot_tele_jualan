@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page_subtitle', 'Dashboard')

@section('content')
<style>
.main-background {
  position: absolute;
  top: 0px;
  left: 0px;
  right: 0px;
  height: 240px;
  z-index: 0;
  background-image: radial-gradient(at 0% 0%, rgba(13, 110, 253, 0.2) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(10, 88, 202, 0.2) 0px, transparent 50%);
}
.lift-hover { transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease; }
.lift-hover:hover { transform: translateY(-8px); box-shadow: 0 1rem 3rem rgba(0,0,0,.1) !important; z-index: 10; }
.transition-hover { transition: all 0.2s ease; }
.transition-hover:hover { background-color: #f8f9fa; transform: translateX(5px); }
</style>

<div class="position-relative">
    <div class="main-background"></div>
    <div class="container-fluid position-relative px-0 py-2" style="z-index: 1;">
        
        <!-- Hero Section -->
        <div class="position-relative mb-5">
            <!-- Background Banner -->
            <div class="rounded-4 p-4 p-md-5 text-white shadow-sm overflow-hidden position-relative mb-4" style="background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%); min-height: 220px;">
                <!-- Abstract Pattern -->
                <div style="position: absolute; top: 0; right: 0; bottom: 0; left: 0; opacity: 0.1; background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;"></div>
                <div style="position: absolute; top: -50px; right: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                
                <div class="position-relative z-1 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-4">
                    <div>
                        <h1 class="fw-bold mb-2 text-white">Selamat Datang, Admin</h1>
                        <p class="mb-0 fs-5 opacity-75 fw-light text-white">
                            Ringkasan aktivitas toko Anda
                            <br />
                            <span class="fs-6 opacity-75">{{ $periodLabel }}</span>
                        </p>
                    </div>

                    <!-- Header Button -->
                    <div class="d-none d-md-block">
                        <a href="{{ route('admin.products.index') }}" class="btn btn-light rounded-pill px-4 text-primary fw-bold shadow-sm">
                            <i class="fas fa-plus me-2"></i>Kelola Produk
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Floating Stats Cards & Filter -->
            <div class="container-fluid px-0" style="margin-top: -60px;">
                
                <!-- Filter Card -->
                <div class="card border-0 shadow-sm mb-4 lift-hover" style="border-radius: 16px;">
                    <div class="card-body">
                        <form action="{{ route('admin.dashboard') }}" method="GET" class="row g-3 align-items-center">
                            <div class="col-md-4">
                                <select name="product_id" class="form-select rounded-pill px-3">
                                    <option value="">Semua Produk</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}" {{ $productId == $p->id ? 'selected' : '' }}>
                                            {{ $p->is_suspended ? '🔴' : '✅' }} {{ $p->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="period" class="form-select rounded-pill px-3">
                                    <option value="24_hours" {{ $period == '24_hours' ? 'selected' : '' }}>24 Jam Terakhir</option>
                                    <option value="7_days" {{ $period == '7_days' ? 'selected' : '' }}>7 Hari Terakhir</option>
                                    <option value="30_days" {{ $period == '30_days' ? 'selected' : '' }}>30 Hari Terakhir</option>
                                    <option value="6_months" {{ $period == '6_months' ? 'selected' : '' }}>6 Bulan Terakhir</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex">
                                <button type="submit" class="btn btn-primary rounded-pill px-4 flex-fill me-2">
                                    <i class="fas fa-search me-1"></i> Cari
                                </button>
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary rounded-pill px-4 flex-fill">
                                    <i class="fas fa-sync me-1"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4 mb-5">
                    
                    <!-- Total Pendapatan -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100 lift-hover overflow-hidden" style="border-radius: 16px;">
                            <div class="card-body p-4 position-relative">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="bg-primary-subtle rounded-3 p-3 text-primary">
                                        <i class="fas fa-wallet fa-2x"></i>
                                    </div>
                                    <span class="badge {{ str_replace('text-', 'bg-', $revenueStats['class']) }} rounded-pill px-3 py-2">
                                        <i class="fas {{ $revenueStats['icon'] }} me-1"></i>{{ $revenueStats['formatted_percent'] }}
                                    </span>
                                </div>
                                <h3 class="h5 fw-bold text-body mb-1">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h3>
                                <p class="text-muted small mb-0">Total Pendapatan</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Komisi Platform -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100 lift-hover overflow-hidden" style="border-radius: 16px;">
                            <div class="card-body p-4 position-relative">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="rounded-3 p-3" style="background-color: rgba(111, 66, 193, 0.15); color: var(--bs-purple, #6f42c1);">
                                        <i class="fas fa-coins fa-2x"></i>
                                    </div>
                                    <span class="badge {{ str_replace('text-', 'bg-', $commissionStats['class']) }} rounded-pill px-3 py-2">
                                        <i class="fas {{ $commissionStats['icon'] }} me-1"></i>{{ $commissionStats['formatted_percent'] }}
                                    </span>
                                </div>
                                <h3 class="h5 fw-bold text-body mb-1">Rp {{ number_format($platformCommission, 0, ',', '.') }}</h3>
                                <p class="text-muted small mb-0">Komisi Platform</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pendapatan Admin -->
                    <div class="col-md-6 col-xl-3">
                        <div class="card border-0 shadow-sm h-100 lift-hover overflow-hidden" style="border-radius: 16px;">
                            <div class="card-body p-4 position-relative">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="bg-danger-subtle rounded-3 p-3 text-danger">
                                        <i class="fas fa-user-shield fa-2x"></i>
                                    </div>
                                    <span class="badge {{ str_replace('text-', 'bg-', $adminEarningsStats['class']) }} rounded-pill px-3 py-2">
                                        <i class="fas {{ $adminEarningsStats['icon'] }} me-1"></i>{{ $adminEarningsStats['formatted_percent'] }}
                                    </span>
                                </div>
                                <h3 class="h5 fw-bold text-body mb-1">Rp {{ number_format($adminEarnings, 0, ',', '.') }}</h3>
                                <p class="text-muted small mb-0">Pendapatan Admin</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pendapatan Seller -->
                    <div class="col-md-6 col-xl-3">
                        <a href="{{ route('admin.sellers.index') }}" class="card border-0 shadow-sm h-100 lift-hover text-decoration-none overflow-hidden" style="border-radius: 16px;">
                            <div class="card-body p-4 position-relative">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="rounded-3 p-3" style="background-color: rgba(32, 201, 151, 0.15); color: #20c997;">
                                        <i class="fas fa-store fa-2x"></i>
                                    </div>
                                    <span class="badge {{ str_replace('text-', 'bg-', $sellerEarningsStats['class']) }} rounded-pill px-3 py-2">
                                        <i class="fas {{ $sellerEarningsStats['icon'] }} me-1"></i>{{ $sellerEarningsStats['formatted_percent'] }}
                                    </span>
                                </div>
                                <h3 class="h5 fw-bold text-body mb-1">Rp {{ number_format($totalSellerEarnings, 0, ',', '.') }}</h3>
                                <p class="text-muted small mb-0">Pendapatan Seller</p>
                            </div>
                        </a>
                    </div>

                    <!-- Total Order -->
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100 lift-hover overflow-hidden" style="border-radius: 16px;">
                            <div class="card-body p-4 position-relative">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="bg-success-subtle rounded-3 p-3 text-success">
                                        <i class="fas fa-shopping-cart fa-2x"></i>
                                    </div>
                                    <span class="badge {{ str_replace('text-', 'bg-', $ordersStats['class']) }} rounded-pill px-3 py-2">
                                        <i class="fas {{ $ordersStats['icon'] }} me-1"></i>{{ $ordersStats['formatted_percent'] }}
                                    </span>
                                </div>
                                <h3 class="h5 fw-bold text-body mb-1">{{ number_format($totalOrders, 0, ',', '.') }}</h3>
                                <p class="text-muted small mb-0">Total Order</p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Produk -->
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100 lift-hover overflow-hidden" style="border-radius: 16px;">
                            <div class="card-body p-4 position-relative">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="bg-info-subtle rounded-3 p-3 text-info">
                                        <i class="fas fa-box fa-2x"></i>
                                    </div>
                                    <span class="badge {{ str_replace('text-', 'bg-', $productsStats['class']) }} rounded-pill px-3 py-2">
                                        <i class="fas {{ $productsStats['icon'] }} me-1"></i>{{ $productsStats['formatted_percent'] }}
                                    </span>
                                </div>
                                <h3 class="h5 fw-bold text-body mb-1">{{ number_format($totalProducts, 0, ',', '.') }}</h3>
                                <p class="text-muted small mb-0">Total Produk</p>
                            </div>
                        </div>
                    </div>

                    <!-- Total User -->
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100 lift-hover overflow-hidden" style="border-radius: 16px;">
                            <div class="card-body p-4 position-relative">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="bg-warning-subtle rounded-3 p-3 text-warning">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                    <span class="badge {{ str_replace('text-', 'bg-', $usersStats['class']) }} rounded-pill px-3 py-2">
                                        <i class="fas {{ $usersStats['icon'] }} me-1"></i>{{ $usersStats['formatted_percent'] }}
                                    </span>
                                </div>
                                <h3 class="h5 fw-bold text-body mb-1">{{ number_format($totalUsers, 0, ',', '.') }}</h3>
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-muted"><i class="fas fa-desktop me-1"></i> Web: {{ $webUsersCount }}</small>
                                    <small class="text-muted"><i class="fab fa-telegram-plane me-1"></i> TG: {{ $tgUsersCount }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- End row g-4 -->
            </div> <!-- End Floating Stats Cards -->
        </div> <!-- End Hero Section mb-5 -->
    </div> <!-- End container-fluid -->
</div> <!-- End position-relative -->

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
