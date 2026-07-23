@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_subtitle', 'Dashboard')
@section('meta_description', 'Dashboard akun Anda — lihat ringkasan pesanan dan aktivitas terbaru')

@push('styles')
<style>
    .lift-hover {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .lift-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 14px 28px rgba(0, 0, 0, 0.1) !important;
    }
    .hero-banner {
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
    .hero-circle-1 {
        position: absolute;
        top: -60px; right: -60px;
        width: 320px; height: 320px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
    }
    .hero-circle-2 {
        position: absolute;
        bottom: -80px; right: 120px;
        width: 200px; height: 200px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }
    .floating-stats-container {
        margin-top: -55px;
    }
    @media (max-width: 767.98px) {
        .floating-stats-container {
            margin-top: 1rem;
        }
    }
    .quick-action-card {
        transition: all 0.2s ease;
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: 16px;
    }
    .quick-action-card:hover {
        background-color: var(--bs-light);
        transform: translateX(4px);
    }
</style>
@endpush

@section('content')
<!-- Hero Section (Gradient & Abstract Pattern) -->
<div class="position-relative mb-5">
    <div class="hero-banner p-4 p-md-5 text-white shadow-sm overflow-hidden position-relative">
        <!-- Abstract Patterns & Glowing Orbs -->
        <div class="hero-pattern"></div>
        <div class="hero-circle-1"></div>
        <div class="hero-circle-2"></div>
        
        <div class="position-relative z-1 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-white text-primary rounded-pill px-3 py-2 mb-2 text-uppercase tracking-wider fw-bold shadow-sm" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <i class="fas fa-sparkles me-1 text-warning"></i> {{ __('Selamat Datang Kembali') }}
                </span>
                <h1 class="fw-bold mb-2 text-white fs-2 fs-md-1">
                    {{ Str::limit(Auth::user()->full_name ?? Auth::user()->username ?? 'Pelanggan Setia', 30) }} 👋
                </h1>
                <p class="mb-0 fs-6 opacity-85 fw-light text-white max-w-2xl">
                    {{ __('Kelola pesanan produk digital Anda, cek status transaksi terbaru, atau nikmati belanja instan dari katalog kami.') }}
                </p>
                <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-white bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-1 text-white small">
                        <i class="fab fa-telegram me-1 text-info"></i> {{ Auth::user()->telegram_id ? 'Telegram Connected: ' . Auth::user()->telegram_id : 'Telegram: Belum Terhubung' }}
                    </span>
                    <span class="badge bg-white bg-opacity-10 border border-white border-opacity-25 rounded-pill px-3 py-1 text-white small">
                        <i class="fas fa-shield-alt me-1 text-success"></i> {{ __('Status Akun: Aktif') }}
                    </span>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('catalog.index') }}" class="btn btn-light text-primary fw-bold rounded-pill px-4 py-2 shadow-sm lift-hover">
                    <i class="fas fa-shopping-bag me-2"></i>{{ __('Jelajahi Katalog') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Floating Stats Cards -->
    <div class="container-fluid px-2 px-md-3 floating-stats-container">
        <div class="row g-3 g-md-4">
            <!-- Card 1: Total Orders -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="bg-primary-subtle rounded-3 p-3 text-primary">
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 fw-bold">{{ __('Akun') }}</span>
                        </div>
                        <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: 0.5px;">{{ __('Total Pesanan') }}</div>
                        <h3 class="h2 fw-bold text-body mb-0">{{ $totalOrders ?? 0 }}</h3>
                    </div>
                </div>
            </div>

            <!-- Card 2: Completed Orders -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="bg-success-subtle rounded-3 p-3 text-success">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 fw-bold">{{ __('Berhasil') }}</span>
                        </div>
                        <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: 0.5px;">{{ __('Pesanan Selesai') }}</div>
                        <h3 class="h2 fw-bold text-body mb-0">{{ $completedOrders ?? 0 }}</h3>
                    </div>
                </div>
            </div>

            <!-- Card 3: Pending Orders -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="bg-warning-subtle rounded-3 p-3 text-warning-emphasis">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-1 fw-bold">{{ __('Pending') }}</span>
                        </div>
                        <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: 0.5px;">{{ __('Menunggu Pembayaran') }}</div>
                        <h3 class="h2 fw-bold text-body mb-0">{{ $pendingOrders ?? 0 }}</h3>
                    </div>
                </div>
            </div>

            <!-- Card 4: Total Spent -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="bg-info-subtle rounded-3 p-3 text-info-emphasis">
                                <i class="fas fa-wallet fa-2x"></i>
                            </div>
                            <span class="badge bg-info-subtle text-info-emphasis rounded-pill px-3 py-1 fw-bold">{{ __('Total') }}</span>
                        </div>
                        <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: 0.5px;">{{ __('Total Belanja') }}</div>
                        <h3 class="h3 fw-bold text-body mb-0">Rp {{ number_format($totalSpent ?? 0, 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Split (Recent Orders & Quick Actions) -->
<div class="row g-4">
    <!-- Left Column: Recent Orders Table -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-4 px-4 pb-2">
                <h5 class="fw-bold mb-0 text-body">
                    <i class="fas fa-history text-primary me-2"></i>{{ __('Pesanan Terbaru') }}
                </h5>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                    {{ __('Lihat Semua') }} <i class="fas fa-arrow-right ms-1 small"></i>
                </a>
            </div>
            <div class="card-body px-4 pb-4">
                @if(isset($recentOrders) && count($recentOrders) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                                    <th class="border-0 ps-3">{{ __('No. Ref') }}</th>
                                    <th class="border-0">{{ __('Produk') }}</th>
                                    <th class="border-0">{{ __('Total Harga') }}</th>
                                    <th class="border-0 text-center">{{ __('Status') }}</th>
                                    <th class="border-0 pe-3 text-end">{{ __('Tanggal') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentOrders as $order)
                                <tr>
                                    <td class="ps-3 fw-bold text-primary">
                                        <a href="{{ route('orders.show', $order->id) }}" class="text-decoration-none fw-bold">
                                            {{ $order->reference }}
                                        </a>
                                    </td>
                                    <td class="fw-semibold text-body">
                                        {{ Str::limit($order->product->name ?? '-', 28) }}
                                    </td>
                                    <td class="fw-bold">
                                        Rp {{ number_format($order->total_price ?? 0, 0, ',', '.') }}
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $statusMap = [
                                                'pending_payment' => [__('Menunggu'), 'warning'],
                                                'paid' => [__('Dibayar'), 'info'],
                                                'delivered' => [__('Selesai'), 'success'],
                                                'cancelled' => [__('Dibatalkan'), 'danger'],
                                                'expired' => [__('Kedaluwarsa'), 'secondary'],
                                            ];
                                            [$label, $color] = $statusMap[$order->status] ?? [$order->status, 'secondary'];
                                        @endphp
                                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} rounded-pill px-3 py-1 fw-bold">
                                            {{ $label }}
                                        </span>
                                    </td>
                                    <td class="pe-3 text-end text-secondary small">
                                        {{ $order->created_at->format('d M Y') }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-box-open text-muted" style="font-size: 2.5rem;"></i>
                        </div>
                        <h6 class="fw-bold text-body mb-1">{{ __('Belum Ada Pesanan') }}</h6>
                        <p class="text-muted small mb-3">{{ __('Anda belum pernah melakukan pemesanan produk digital.') }}</p>
                        <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
                            <i class="fas fa-store me-2"></i>{{ __('Mulai Belanja') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Quick Actions & Support Widget -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h5 class="fw-bold mb-0 text-body">
                    <i class="fas fa-bolt text-warning me-2"></i>{{ __('Akses Cepat') }}
                </h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="d-flex flex-column gap-3">
                    <a href="{{ route('catalog.index') }}" class="quick-action-card p-3 d-flex align-items-center text-decoration-none">
                        <div class="bg-primary-subtle rounded-3 p-3 text-primary me-3">
                            <i class="fas fa-store fa-lg"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-body">{{ __('Katalog Produk') }}</div>
                            <small class="text-muted">{{ __('Jelajahi produk digital terlaris') }}</small>
                        </div>
                        <i class="fas fa-chevron-right text-secondary ms-auto small"></i>
                    </a>

                    <a href="{{ route('orders.index') }}" class="quick-action-card p-3 d-flex align-items-center text-decoration-none">
                        <div class="bg-success-subtle rounded-3 p-3 text-success me-3">
                            <i class="fas fa-receipt fa-lg"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-body">{{ __('Riwayat Pesanan') }}</div>
                            <small class="text-muted">{{ __('Cek detail & lisensi akun Anda') }}</small>
                        </div>
                        <i class="fas fa-chevron-right text-secondary ms-auto small"></i>
                    </a>

                    <a href="{{ route('chat.index') }}" class="quick-action-card p-3 d-flex align-items-center text-decoration-none">
                        <div class="bg-info-subtle rounded-3 p-3 text-info me-3">
                            <i class="fas fa-comments fa-lg"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-body">{{ __('Pusat Bantuan Chat') }}</div>
                            <small class="text-muted">{{ __('Diskusi langsung dengan tim support') }}</small>
                        </div>
                        <i class="fas fa-chevron-right text-secondary ms-auto small"></i>
                    </a>

                    @if(config('telegram.bot_username'))
                    <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank" class="quick-action-card p-3 d-flex align-items-center text-decoration-none">
                        <div class="bg-primary-subtle rounded-3 p-3 text-primary me-3" style="background: rgba(13, 110, 253, 0.12) !important;">
                            <i class="fab fa-telegram fa-lg text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-body">{{ __('Bot Telegram') }}</div>
                            <small class="text-muted">{{ __('Transaksi & notifikasi otomatis') }}</small>
                        </div>
                        <i class="fas fa-external-link-alt text-secondary ms-auto small"></i>
                    </a>
                    @endif
                </div>

                <!-- Info Box Widget -->
                <div class="bg-light rounded-4 p-3 mt-4 border border-0">
                    <div class="d-flex align-items-center gap-2 mb-1 text-primary fw-bold small">
                        <i class="fas fa-info-circle"></i> {{ __('Butuh Pertolongan?') }}
                    </div>
                    <p class="text-muted small mb-0" style="font-size: 0.825rem; line-height: 1.4;">
                        {{ __('Jika Anda mengalami kendala transaksi atau garansi produk, gunakan fitur Kelola Komplain atau hubungi admin via Telegram.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
