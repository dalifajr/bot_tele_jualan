@extends('layouts.app')

@section('title', 'Dashboard Seller')
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
    .hero-seller-banner {
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
    <div class="hero-seller-banner p-4 p-md-5 text-white shadow-sm overflow-hidden position-relative">
        <div class="hero-pattern"></div>
        
        <div class="position-relative z-1 d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
                <span class="badge bg-white text-primary rounded-pill px-3 py-2 mb-2 text-uppercase tracking-wider fw-bold shadow-sm" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <i class="fas fa-store me-1 text-warning"></i> {{ __('Seller Portal') }}
                </span>
                <h1 class="fw-bold mb-2 text-white fs-2 fs-md-1">
                    {{ __('Selamat Datang,') }} {{ $user->full_name ?? $user->username }} 👋
                </h1>
                <p class="mb-0 fs-6 opacity-85 fw-light text-white">
                    {{ __('Kelola produk Anda, kelola stok akun, dan pantau penghasilan penjualan Anda secara langsung.') }}
                </p>
            </div>
            
            <div class="d-flex align-items-center gap-2 flex-wrap flex-md-nowrap align-self-start ms-md-auto pt-1">
                <select class="form-select form-select-sm rounded-pill px-3 py-2 bg-white text-primary fw-bold border-0 shadow-sm" style="width: auto; min-width: 140px;" onchange="let params = new URLSearchParams(window.location.search); if(this.value) { params.set('product_id', this.value); } else { params.delete('product_id'); } window.location.href = '{{ route('seller.dashboard') }}?' + params.toString()">
                    <option value="" class="text-dark">{{ __('Semua Produk') }}</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ $productId == $p->id ? 'selected' : '' }} class="text-dark">
                            {{ $p->is_suspended ? '🔴' : '✅' }} {{ $p->name }}
                        </option>
                    @endforeach
                </select>

                <a href="{{ route('seller.stock.index') }}" class="btn btn-outline-light rounded-pill px-3 py-2 text-nowrap fw-bold">
                    <i class="fas fa-plus me-1"></i>{{ __('Tambah Stok') }}
                </a>
                
                <a href="{{ route('seller.products.index') }}" class="btn btn-light text-primary fw-bold rounded-pill px-3 py-2 shadow-sm lift-hover text-nowrap">
                    <i class="fas fa-box-open me-1"></i>{{ __('Produk Saya') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Floating Stat Cards -->
    <div class="container-fluid px-2 px-md-3 floating-stats-container">
        <div class="row g-3">
            <!-- 1. Wallet Balance Card -->
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 text-white overflow-hidden lift-hover" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
                    <div class="card-body p-4 position-relative">
                        <div class="position-absolute end-0 bottom-0 text-white" style="font-size: 7rem; transform: translate(15px, 15px); opacity: 0.12; pointer-events: none;">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="position-relative z-1">
                            <span class="badge bg-white text-primary rounded-pill px-3 py-1 mb-2 small fw-bold text-uppercase shadow-sm">{{ __('Saldo Dompet Saya') }}</span>
                            <h2 class="fw-bold mb-3 text-white">Rp {{ number_format($user->wallet_balance, 0, ',', '.') }}</h2>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white border-opacity-25">
                                <span class="small text-white-50">{{ __('Komisi:') }} <strong>{{ $user->platform_fee_percent }}%</strong></span>
                                <a href="{{ route('seller.finance.index') }}" class="btn btn-light btn-sm rounded-pill px-3 fw-bold text-primary shadow-sm">{{ __('Tarik Saldo') }} <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Held Balance Card -->
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 text-white overflow-hidden lift-hover" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);">
                    <div class="card-body p-4 position-relative">
                        <div class="position-absolute end-0 bottom-0 text-white" style="font-size: 7rem; transform: translate(15px, 15px); opacity: 0.15; pointer-events: none;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="position-relative z-1">
                            <span class="badge bg-white text-warning-emphasis rounded-pill px-3 py-1 mb-2 small fw-bold text-uppercase shadow-sm">{{ __('Saldo Tertahan (Garansi)') }}</span>
                            <h2 class="fw-bold mb-3 text-white">Rp {{ number_format($heldBalance, 0, ',', '.') }}</h2>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white border-opacity-25">
                                <span class="small text-white-50">{{ __('Menunggu Garansi') }}</span>
                                <a href="{{ route('seller.finance.index') }}" class="btn btn-light btn-sm rounded-pill px-3 fw-bold text-warning-emphasis shadow-sm">{{ __('Rincian') }} <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Monthly Earnings Card -->
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 small fw-bold text-uppercase">{{ __('Pendapatan Kotor') }}</span>
                            <select onchange="let params = new URLSearchParams(window.location.search); params.set('earnings_days', this.value); window.location.href = window.location.pathname + '?' + params.toString();" class="form-select form-select-sm py-0 px-2 border-0 shadow-sm bg-light" style="width: auto; font-size: 0.7rem; border-radius: 8px; cursor: pointer;">
                                <option value="all" {{ $earningsDays == 'all' ? 'selected' : '' }}>{{ __('Semua') }}</option>
                                <option value="7" {{ $earningsDays == '7' ? 'selected' : '' }}>{{ __('7 Hari') }}</option>
                                <option value="30" {{ $earningsDays == '30' ? 'selected' : '' }}>{{ __('30 Hari') }}</option>
                                <option value="90" {{ $earningsDays == '90' ? 'selected' : '' }}>{{ __('90 Hari') }}</option>
                                <option value="365" {{ $earningsDays == '365' ? 'selected' : '' }}>{{ __('1 Tahun') }}</option>
                            </select>
                        </div>
                        <h2 class="fw-bold text-success mb-2">Rp {{ number_format($monthlyEarnings, 0, ',', '.') }}</h2>
                        <div class="d-flex flex-column gap-1 pt-2 border-top" style="font-size: 0.78rem;">
                            <div class="d-flex justify-content-between text-muted">
                                <span>{{ __('Pend. Bersih:') }}</span>
                                <span class="fw-bold text-body">Rp {{ number_format($monthlyNet, 0, ',', '.') }}</span>
                            </div>
                            <div class="d-flex justify-content-between text-muted">
                                <span>{{ __('Komisi') }} ({{ $user->platform_fee_percent ?? 10 }}%):</span>
                                <span class="fw-bold text-danger">Rp {{ number_format($monthlyCommission, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Active Stock & Review Card -->
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1 small fw-bold text-uppercase">{{ __('Status Stok & Rating') }}</span>
                            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2 py-1 fw-bold">
                                <i class="fas fa-star me-1 text-warning"></i>{{ $avgRating ? number_format($avgRating, 1) : '0.0' }}
                            </span>
                        </div>
                        <h2 class="fw-bold text-info mb-2">{{ $readyStockCount }} <span class="fs-6 text-muted fw-normal">{{ __('ready') }}</span></h2>
                        <div class="d-flex gap-2 mb-2">
                            <span class="badge bg-warning-subtle text-warning rounded-pill px-2 small">{{ $savedStockCount }} {{ __('karantina') }}</span>
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 small">{{ $soldStockCount }} {{ __('terjual') }}</span>
                        </div>
                        <div class="pt-2 border-top small text-muted">
                            <i class="fas fa-comments text-primary me-1"></i>{{ __('Berdasarkan :count ulasan pembeli.', ['count' => $totalReviews]) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Operational Analytics -->
<div class="row g-4 mb-4">
    <!-- Chart: Daily Revenue Trend -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-line text-primary me-2"></i>{{ __('Tren Pendapatan Harian') }}</h5>
                    <p class="text-muted small mb-0">{{ __('Statistik omzet penjualan dalam :days terakhir', ['days' => $days == 180 ? '6 bulan' : ($days == 365 ? '1 tahun' : $days . ' hari')]) }}</p>
                </div>
                <div>
                    <select class="form-select form-select-sm rounded-pill px-3 bg-light border-0 fw-bold" style="width: auto;" onchange="let params = new URLSearchParams(window.location.search); params.set('days', this.value); window.location.href = '{{ route('seller.dashboard') }}?' + params.toString()">
                        <option value="7" {{ $days == 7 ? 'selected' : '' }}>{{ __('7 Hari Terakhir') }}</option>
                        <option value="14" {{ $days == 14 ? 'selected' : '' }}>{{ __('14 Hari Terakhir') }}</option>
                        <option value="30" {{ $days == 30 ? 'selected' : '' }}>{{ __('30 Hari Terakhir') }}</option>
                        <option value="180" {{ $days == 180 ? 'selected' : '' }}>{{ __('6 Bulan Terakhir') }}</option>
                        <option value="365" {{ $days == 365 ? 'selected' : '' }}>{{ __('1 Tahun Terakhir') }}</option>
                    </select>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div id="salesChart" style="min-height: 350px;"></div>
            </div>
        </div>
    </div>

    <!-- Chart: Order Status Ratio -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-pie text-success me-2"></i>{{ __('Rasio Status Order') }}</h5>
                <p class="text-muted small mb-0">{{ __('Persentase sukses vs batal/expired') }}</p>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center px-4 pb-4">
                <div id="completionChart" style="min-height: 250px;"></div>
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

<!-- Advanced Analytics Row (Top Products & Share Donut) -->
<div class="row g-4 mb-4">
    <!-- Top Performing Products Table -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-trophy text-warning me-2"></i>{{ __('Produk Terlaris') }}</h5>
                <p class="text-muted small mb-0">{{ __('5 produk dengan jumlah penjualan unit terbanyak') }}</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                                <th class="px-4 py-3 border-0">{{ __('Nama Produk') }}</th>
                                <th class="py-3 border-0">{{ __('Harga') }}</th>
                                <th class="py-3 border-0 text-center">{{ __('Unit Terjual') }}</th>
                                <th class="px-4 py-3 border-0 text-end">{{ __('Total Omset') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topProducts as $prod)
                            <tr>
                                <td class="px-4 fw-bold text-body">{{ $prod->name }}</td>
                                <td class="text-body">Rp {{ number_format($prod->price, 0, ',', '.') }}</td>
                                <td class="text-center fw-bold text-primary">{{ $prod->units_sold }}</td>
                                <td class="px-4 text-end fw-bold text-success">Rp {{ number_format($prod->total_earnings, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted px-4">{{ __('Belum ada produk yang terjual.') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Share Donut Chart -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-pie text-info me-2"></i>{{ __('Porsi Penjualan per Produk') }}</h5>
                <p class="text-muted small mb-0">{{ __('Distribusi jumlah unit terjual untuk setiap produk') }}</p>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center px-4 pb-4">
                <div id="shareChart" style="min-height: 250px; width: 100%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Operational Guide & Finance Summary Row -->
<div class="row g-4 mb-4">
    <!-- Quick Start Guide -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <h5 class="fw-bold mb-3 text-body"><i class="fas fa-compass text-primary me-2"></i>{{ __('Panduan Cepat Operasional Seller') }}</h5>
            <div class="d-flex flex-column gap-3 mt-2">
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">1</div>
                    <div>
                        <h6 class="fw-bold mb-1 text-body">{{ __('Miliki atau Ikut Serta dalam Katalog Produk') }}</h6>
                        <p class="text-muted small mb-0">{!! 'Anda dapat membuat produk baru secara mandiri di halaman <strong>{{ __(\'Produk Saya\') }}</strong>{{ __(\', atau ditambahkan sebagai\') }} <strong>{{ __(\'Worker\') }}</strong> oleh Admin pada produk global.' !!}</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">2</div>
                    <div>
                        <h6 class="fw-bold mb-1 text-body">{{ __('Unggah Stok Akun Digital Anda') }}</h6>
                        <p class="text-muted small mb-0">{!! 'Unggah akun/stok Anda secara massal di menu <strong>{{ __(\'Stok Akun\') }}</strong>{{ __(\'. Akun yang baru diunggah akan masuk ke status\') }} <strong>{{ __(\'Karantina (*Simpan Akun*)\') }}</strong>.' !!}</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">3</div>
                    <div>
                        <h6 class="fw-bold mb-1 text-body">{{ __('Pindah Otomatis ke Status Ready') }}</h6>
                        <p class="text-muted small mb-0">{!! 'Stok Anda akan otomatis berpindah ke status <strong>{{ __(\'Ready\') }}</strong> {{ __(\'setelah jam cooldown karantina habis. Anda dapat mengatur jam karantina tersendiri di menu\') }} <strong>{{ __(\'Pengaturan\') }}</strong>.' !!}</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">4</div>
                    <div>
                        <h6 class="fw-bold mb-1 text-body">{{ __('Terima Komisi & Tarik Saldo Ke Rekening') }}</h6>
                        <p class="text-muted small mb-0">{!! 'Setiap pembeli membeli stok Anda, Anda menerima notifikasi Telegram dan dana masuk ke saldo Dompet setelah dipotong platform fee. Anda dapat mengajukan pencairan dana di menu <strong>{{ __(\'Dompet & Keuangan\') }}</strong>.' !!}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Finance Overview -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
            <h5 class="fw-bold mb-3 text-body"><i class="fas fa-hand-holding-usd text-primary me-2"></i>{{ __('Status Payout Terakhir') }}</h5>
            <div class="text-center py-4 bg-light rounded-4 mb-3">
                <i class="fas fa-history text-muted mb-2 fs-3"></i>
                <div class="small text-muted">{{ __('Total Pengajuan Penarikan:') }}</div>
                <h5 class="fw-bold m-0 text-primary">{{ $pendingWithdrawalCount + $approvedWithdrawalCount }} {{ __('Permintaan') }}</h5>
            </div>
            <div class="d-flex flex-column gap-2 small">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted"><i class="fas fa-spinner fa-spin text-warning me-1"></i> {{ __('Menunggu Verifikasi Admin') }}</span>
                    <span class="fw-bold text-body">{{ $pendingWithdrawalCount }} {{ __('Permintaan') }}</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted"><i class="fas fa-check-circle text-success me-1"></i> {{ __('Penarikan Disetujui') }}</span>
                    <span class="fw-bold text-success">{{ $approvedWithdrawalCount }} {{ __('Selesai') }}</span>
                </div>
            </div>
            <div class="mt-4 text-center">
                <a href="{{ route('seller.finance.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold">{{ __('Lihat Dompet Saya') }}</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
        <h5 class="fw-bold mb-0 text-body"><i class="fas fa-history text-secondary me-2"></i>5 Pesanan Sukses Terakhir (Stok Anda)</h5>
    </div>
    <div class="card-body p-0">
        @if($latestOrders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                        <th class="px-4 py-3 border-0">{{ __('No Pesanan') }}</th>
                        <th class="py-3 border-0">{{ __('Pelanggan') }}</th>
                        <th class="py-3 border-0">{{ __('Pendapatan Anda') }}</th>
                        <th class="py-3 border-0 text-end pe-4">{{ __('Tanggal Selesai') }}</th>
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
                                <span class="fw-bold text-body">{{ $order->customer->full_name ?? 'Unknown' }}</span>
                                <span class="small text-muted">{{ $order->customer->username ?? '' }}</span>
                            </div>
                        </td>
                        <td class="text-success fw-bold">Rp {{ number_format($myEarningsInOrder, 0, ',', '.') }}</td>
                        <td class="text-secondary small text-end pe-4">{{ $order->delivered_at ? $order->delivered_at->format('d M Y H:i') : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-receipt text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">{{ __('Belum ada pesanan sukses dengan stok Anda.') }}</p>
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

        // 3. Product Share Pie Chart
        const shareOptions = {
            series: @json($shareData),
            chart: {
                type: 'donut',
                height: 250,
                background: 'transparent'
            },
            labels: @json($shareLabels),
            theme: chartTheme,
            legend: {
                position: 'bottom',
                labels: { colors: isDark ? '#a0aec0' : '#4a5568' }
            },
            dataLabels: { enabled: true }
        };

        const shareChart = new ApexCharts(document.querySelector("#shareChart"), shareOptions);
        shareChart.render();

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

                    shareChart.updateOptions({
                        theme: { mode: newTheme },
                        legend: { labels: { colors: isNewDark ? '#a0aec0' : '#4a5568' } }
                    });
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });
    });
</script>
@endpush
@endsection
