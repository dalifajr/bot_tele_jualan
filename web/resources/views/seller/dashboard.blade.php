@extends('layouts.app')

@section('title', 'Dashboard Seller')
@section('page_subtitle', 'Dashboard')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-1">Selamat Datang di Portal Seller, {{ $user->full_name ?? $user->username }}!</h4>
    <p class="text-muted mb-0">Kelola produk Anda, kelola stok akun, dan pantau penghasilan penjualan Anda secara langsung.</p>
</div>

{{-- Stats Row --}}
<div class="row g-4 mb-4">
    {{-- Card Wallet Balance --}}
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm text-white overflow-hidden" style="border-radius: 20px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
            <div class="card-body p-4 position-relative">
                <div class="position-absolute end-0 bottom-0 text-white" style="font-size: 8rem; transform: translate(20px, 20px); opacity: 0.1; pointer-events: none; z-index: 0;">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="position-relative" style="z-index: 1;">
                    <p class="small text-white-50 fw-bold mb-2">SALDO DOMPET SAYA</p>
                    <h2 class="fw-bold mb-3">Rp {{ number_format($user->wallet_balance, 0, ',', '.') }}</h2>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white-10">
                        <span class="small text-white-50">Komisi: <strong>{{ $user->platform_fee_percent }}%</strong></span>
                        <a href="{{ route('seller.finance.index') }}" class="btn btn-light btn-sm rounded-pill px-3 fw-bold text-primary">Tarik Saldo <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Card Held Balance --}}
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm text-white overflow-hidden" style="border-radius: 20px; background: linear-gradient(135deg, hsl(35, 90%, 50%) 0%, hsl(45, 95%, 55%) 100%);">
            <div class="card-body p-4 position-relative">
                <div class="position-absolute end-0 bottom-0 text-white" style="font-size: 8rem; transform: translate(20px, 20px); opacity: 0.15; pointer-events: none; z-index: 0;">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="position-relative" style="z-index: 1;">
                    <p class="small text-white-50 fw-bold mb-2">SALDO TERTAHAN (GARANSI)</p>
                    <h2 class="fw-bold mb-3">Rp {{ number_format($heldBalance, 0, ',', '.') }}</h2>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white-10">
                        <span class="small text-white-50">Menunggu Garansi</span>
                        <a href="{{ route('seller.finance.index') }}" class="btn btn-light btn-sm rounded-pill px-3 fw-bold text-warning-emphasis">Rincian <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Card Monthly Earnings --}}
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 20px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px);">
            <div class="card-body p-4 position-relative">
                <div class="position-absolute end-0 bottom-0 text-success" style="font-size: 8rem; transform: translate(20px, 20px); opacity: 0.1; pointer-events: none; z-index: 0;">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="position-relative" style="z-index: 1;">
                    <p class="small text-muted fw-bold mb-2">PENDAPATAN KOTOR</p>
                    <h2 class="fw-bold text-success mb-3">Rp {{ number_format($monthlyEarnings, 0, ',', '.') }}</h2>
                    <span class="small text-muted"><i class="fas fa-info-circle text-primary me-1"></i>Akumulasi pendapatan kotor produk Anda.</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Card Active Stock --}}
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 20px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px);">
            <div class="card-body p-4 position-relative">
                <div class="position-absolute end-0 bottom-0 text-info" style="font-size: 8rem; transform: translate(20px, 20px); opacity: 0.1; pointer-events: none; z-index: 0;">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="position-relative" style="z-index: 1;">
                    <p class="small text-muted fw-bold mb-2">STATUS STOK PENJUALAN</p>
                    <h2 class="fw-bold text-info mb-3">{{ $readyStockCount }} <span class="fs-6 text-muted fw-normal">ready</span></h2>
                    <div class="d-flex gap-2">
                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2 small">{{ $savedStockCount }} karantina</span>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 small">{{ $soldStockCount }} terjual</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts & Operational Analytics --}}
<div class="row g-4 mb-4">
    {{-- Chart --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-line text-primary me-2"></i>Tren Pendapatan Harian</h5>
                    <p class="text-muted small mb-0">Statistik omzet penjualan dalam {{ $days == 180 ? '6 bulan' : ($days == 365 ? '1 tahun' : $days . ' hari') }} terakhir</p>
                </div>
                <div>
                    <select class="form-select form-select-sm rounded-pill px-3" style="width: auto;" onchange="window.location.href = '{{ route('seller.dashboard') }}?days=' + this.value">
                        <option value="7" {{ $days == 7 ? 'selected' : '' }}>7 Hari Terakhir</option>
                        <option value="14" {{ $days == 14 ? 'selected' : '' }}>14 Hari Terakhir</option>
                        <option value="30" {{ $days == 30 ? 'selected' : '' }}>30 Hari Terakhir</option>
                        <option value="180" {{ $days == 180 ? 'selected' : '' }}>6 Bulan Terakhir</option>
                        <option value="365" {{ $days == 365 ? 'selected' : '' }}>1 Tahun Terakhir</option>
                    </select>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div id="salesChart" style="min-height: 350px;"></div>
            </div>
        </div>
    </div>

    {{-- Order Completion Rate --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-pie text-success me-2"></i>Rasio Status Order</h5>
                <p class="text-muted small mb-0">Persentase sukses vs batal/expired</p>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center px-4 pb-4">
                <div id="completionChart" style="min-height: 250px;"></div>
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

<div class="row g-4">
    {{-- Quick Start Guide --}}
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
            <h5 class="fw-bold mb-3"><i class="fas fa-compass text-primary me-2"></i>Panduan Cepat Operasional Seller</h5>
            <div class="d-flex flex-column gap-3 mt-2">
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">1</div>
                    <div>
                        <h6 class="fw-bold mb-1">Miliki atau Ikut Serta dalam Katalog Produk</h6>
                        <p class="text-muted small mb-0">Anda dapat membuat produk baru secara mandiri di halaman <strong>Produk Saya</strong>, atau ditambahkan sebagai <strong>Worker</strong> oleh Admin pada produk global.</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">2</div>
                    <div>
                        <h6 class="fw-bold mb-1">Unggah Stok Akun Digital Anda</h6>
                        <p class="text-muted small mb-0">Unggah akun/stok Anda secara massal di menu <strong>Stok Akun</strong>. Akun yang baru diunggah akan masuk ke status <strong>Karantina (*Simpan Akun*)</strong>.</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">3</div>
                    <div>
                        <h6 class="fw-bold mb-1">Pindah Otomatis ke Status Ready</h6>
                        <p class="text-muted small mb-0">Stok Anda akan otomatis berpindah ke status <strong>Ready</strong> setelah jam cooldown karantina habis. Anda dapat mengatur jam karantina tersendiri di menu <strong>Pengaturan</strong>.</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">4</div>
                    <div>
                        <h6 class="fw-bold mb-1">Terima Komisi & Tarik Saldo Ke Rekening</h6>
                        <p class="text-muted small mb-0">Setiap pembeli membeli stok Anda, Anda menerima notifikasi Telegram dan dana masuk ke saldo Dompet setelah dipotong platform fee. Anda dapat mengajukan pencairan dana di menu <strong>Dompet & Keuangan</strong>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Finance Overview --}}
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
            <h5 class="fw-bold mb-3"><i class="fas fa-hand-holding-usd text-primary me-2"></i>Status Payout Terakhir</h5>
            <div class="text-center py-4 bg-light rounded-4 mb-3">
                <i class="fas fa-history text-muted mb-2 fs-3"></i>
                <div class="small text-muted">Total Pengajuan Penarikan:</div>
                <h5 class="fw-bold m-0 text-primary">{{ $pendingWithdrawalCount + $approvedWithdrawalCount }} Permintaan</h5>
            </div>
            <div class="d-flex flex-column gap-2 small">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted"><i class="fas fa-spinner fa-spin text-warning me-1"></i> Menunggu Verifikasi Admin</span>
                    <span class="fw-bold">{{ $pendingWithdrawalCount }} Permintaan</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted"><i class="fas fa-check-circle text-success me-1"></i> Penarikan Disetujui</span>
                    <span class="fw-bold text-success">{{ $approvedWithdrawalCount }} Selesai</span>
                </div>
            </div>
            <div class="mt-4 text-center">
                <a href="{{ route('seller.finance.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4">Lihat Dompet Saya</a>
            </div>
        </div>
    </div>
</div>

{{-- Recent Orders --}}
<div class="card border-0 shadow-sm overflow-hidden mb-4 mt-4" style="border-radius: 20px;">
    <div class="card-header bg-transparent border-0 pt-4 px-4">
        <h5 class="fw-bold mb-0 text-body"><i class="fas fa-history text-secondary me-2"></i>5 Pesanan Sukses Terakhir (Stok Anda)</h5>
    </div>
    <div class="card-body p-0">
        @if($latestOrders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">No Pesanan</th>
                        <th class="py-3 border-0">Pelanggan</th>
                        <th class="py-3 border-0">Pendapatan Anda</th>
                        <th class="py-3 border-0">Tanggal Selesai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($latestOrders as $order)
                    @php
                        $myEarningsInOrder = $order->stockUnits->where('seller_id', Auth::id())->sum(function($unit) {
                            return $unit->product->price ?? 0;
                        });
                    @endphp
                    <tr>
                        <td class="px-4 fw-bold text-primary">{{ $order->order_ref }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold">{{ $order->customer->full_name ?? 'Unknown' }}</span>
                                <span class="small text-muted">{{ $order->customer->username ?? '' }}</span>
                            </div>
                        </td>
                        <td class="text-success fw-bold">Rp {{ number_format($myEarningsInOrder, 0, ',', '.') }}</td>
                        <td class="text-secondary small">{{ $order->delivered_at ? $order->delivered_at->format('d M Y H:i') : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-receipt text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada pesanan sukses dengan stok Anda.</p>
        </div>
        @endif
    </div>
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
                name: 'Pendapatan Seller (Rp)',
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
                            total: {
                                show: true,
                                label: 'Total Order',
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
                        theme: { mode: newTheme }
                    });
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });
    });
</script>
@endpush
@endsection
