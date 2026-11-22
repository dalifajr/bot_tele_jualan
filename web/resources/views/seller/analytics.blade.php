@extends('layouts.app')

@section('title', __('Analitik Penjualan Seller'))
@section('page_subtitle', __('Tren omzet, kualitas stok & jam transaksi teramai'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 mb-md-4">
    <div>
        <h4 class="fw-bold mb-0 text-body fs-5 fs-md-4"><i class="fas fa-chart-area text-primary me-1.5"></i>{{ __('Analitik & Wawasan Toko') }}</h4>
        <small class="text-secondary">{{ __('Pantau performa produk dan skor kesehatan garansi toko Anda.') }}</small>
    </div>
    <div class="d-flex gap-1.5">
        @foreach([7, 30, 90] as $range)
            <a href="{{ route('seller.analytics', ['days' => $range]) }}" class="btn btn-sm rounded-pill px-3 fw-semibold {{ $days === $range ? 'btn-primary' : 'btn-tonal' }}">
                {{ $range }} {{ __('Hari') }}
            </a>
        @endforeach
    </div>
</div>

<div class="row g-2.5 g-md-3 mb-3 mb-md-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 p-md-3.5">
                <small class="text-secondary fw-semibold">{{ __('Omzet Periode') }}</small>
                <div class="fs-4 fw-bold text-primary mt-1 m3-tabular-nums">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 p-md-3.5">
                <small class="text-secondary fw-semibold">{{ __('Pesanan Selesai') }}</small>
                <div class="fs-4 fw-bold text-body mt-1 m3-tabular-nums">{{ $totalOrdersCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 p-md-3.5">
                <small class="text-secondary fw-semibold">{{ __('Unit Terjual') }}</small>
                <div class="fs-4 fw-bold text-body mt-1 m3-tabular-nums">{{ $totalUnitsSold }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 p-md-3.5">
                <small class="text-secondary fw-semibold">{{ __('Skor Kualitas Stok') }}</small>
                <div class="fs-4 fw-bold m3-tabular-nums {{ $overallHealthScore >= 90 ? 'text-success' : ($overallHealthScore >= 75 ? 'text-warning' : 'text-danger') }} mt-1">{{ $overallHealthScore }}%</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 g-md-4 mb-3 mb-md-4">
    <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 p-md-4">
                <h6 class="fw-bold text-body mb-3 fs-6">{{ __('Tren Pendapatan Harian') }}</h6>
                <div style="height: 240px;"><canvas id="sellerRevenueChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 p-md-4">
                <h6 class="fw-bold text-body mb-3 fs-6">{{ __('Pola Jam Ramai Belanja') }}</h6>
                <div style="height: 240px;"><canvas id="sellerPeakHoursChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 rounded-4 shadow-sm overflow-hidden">
    <div class="card-body p-3 p-md-4">
        <h6 class="fw-bold text-body mb-3 fs-6">{{ __('Performa & Kualitas Per Produk') }}</h6>
        
        {{-- Desktop Table View --}}
        <div class="table-responsive d-none d-md-block">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th>{{ __('Produk') }}</th>
                        <th class="text-end">{{ __('Unit Terjual') }}</th>
                        <th class="text-end">{{ __('Omzet') }}</th>
                        <th class="text-end">{{ __('Komplain') }}</th>
                        <th class="text-end">{{ __('Skor Kualitas') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topProducts as $prod)
                        <tr>
                            <td class="fw-semibold text-body small">{{ $prod['name'] }}</td>
                            <td class="text-end small m3-tabular-nums">{{ $prod['units_sold'] }}</td>
                            <td class="text-end fw-bold text-primary small m3-tabular-nums">Rp {{ number_format($prod['revenue'], 0, ',', '.') }}</td>
                            <td class="text-end small m3-tabular-nums">{{ $prod['complaints'] }}</td>
                            <td class="text-end">
                                <span class="badge rounded-pill m3-tabular-nums {{ $prod['health_score'] >= 90 ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }}">
                                    {{ $prod['health_score'] }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4 small">{{ __('Belum ada data penjualan pada rentang waktu ini.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Stacked Card List --}}
        <div class="d-md-none d-flex flex-column gap-2.5">
            @forelse($topProducts as $prod)
                <div class="m3-mobile-item-card">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="fw-bold text-body small">{{ $prod['name'] }}</div>
                        <span class="badge rounded-pill flex-shrink-0 m3-tabular-nums {{ $prod['health_score'] >= 90 ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }}">
                            {{ $prod['health_score'] }}%
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 mt-1" style="border-top: 1px solid var(--outline-variant); font-size: 0.76rem;">
                        <span class="text-muted">{{ __('Terjual:') }} <strong class="text-body m3-tabular-nums">{{ $prod['units_sold'] }}</strong> &bull; {{ __('Komplain:') }} <strong class="text-danger m3-tabular-nums">{{ $prod['complaints'] }}</strong></span>
                        <span class="fw-bold text-primary m3-tabular-nums fs-6">Rp {{ number_format($prod['revenue'], 0, ',', '.') }}</span>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-4 small">{{ __('Belum ada data penjualan pada rentang waktu ini.') }}</div>
            @endforelse
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;
    const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#0288d1';

    new Chart(document.getElementById('sellerRevenueChart'), {
        type: 'line',
        data: {
            labels: @json($dailyLabels),
            datasets: [{
                label: @json(__('Omzet (Rp)')),
                data: @json($dailyRevenueValues),
                borderColor: primary,
                backgroundColor: primary + '22',
                fill: true,
                tension: 0.35
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    new Chart(document.getElementById('sellerPeakHoursChart'), {
        type: 'bar',
        data: {
            labels: @json($hourBucketLabels),
            datasets: [{
                label: @json(__('Pesanan')),
                data: @json($hourBucketValues),
                backgroundColor: primary,
                borderRadius: 8
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
});
</script>
@endsection
