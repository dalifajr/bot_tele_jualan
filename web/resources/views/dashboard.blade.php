@extends('layouts.app')

@section('title', __('Dashboard'))
@section('page_subtitle', __('Dashboard'))
@section('meta_description', 'Dashboard akun Anda — lihat ringkasan pesanan dan aktivitas terbaru')

@section('content')
<!-- Flat Material 3 Customer Welcome Header -->
<div class="m3-console-header mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            @auth
            <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill mb-2 fw-semibold" style="background: var(--primary-container); color: var(--on-primary-container); font-size: 0.72rem; letter-spacing: 0.04em; text-transform: uppercase;">
                <i class="fas fa-sparkles"></i>
                <span>{{ __('Selamat Datang Kembali') }}</span>
            </div>
            <h1 class="h3 fw-bold mb-1" style="color: var(--on-surface);">
                {{ Str::limit(Auth::user()->full_name ?? Auth::user()->username ?? 'Pelanggan Setia', 30) }} 👋
            </h1>
            <p class="mb-0 small" style="color: var(--on-surface-variant); max-width: 640px;">
                {{ __('Kelola pesanan produk digital Anda, cek status transaksi terbaru, atau nikmati belanja instan dari katalog kami.') }}
            </p>
            <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1.5">
                    <i class="fab fa-telegram me-1"></i> {{ Auth::user()->telegram_id ? 'Telegram Connected: ' . Auth::user()->telegram_id : 'Telegram: Belum Terhubung' }}
                </span>
                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1.5">
                    <i class="fas fa-shield-alt me-1"></i> {{ __('Status Akun: Aktif') }}
                </span>
            </div>
            @else
            <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill mb-2 fw-semibold" style="background: var(--primary-container); color: var(--on-primary-container); font-size: 0.72rem; letter-spacing: 0.04em; text-transform: uppercase;">
                <i class="fas fa-sparkles"></i>
                <span>{{ __('Selamat Datang') }}</span>
            </div>
            <h1 class="h3 fw-bold mb-1" style="color: var(--on-surface);">
                {{ __('Tamu (Guest)') }} 👋
            </h1>
            <p class="mb-0 small" style="color: var(--on-surface-variant); max-width: 640px;">
                {{ __('Jelajahi berbagai produk digital terbaik kami dengan harga bersahabat dan pengiriman otomatis instan.') }}
            </p>
            <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('login') }}" class="badge bg-primary-subtle text-primary text-decoration-none rounded-pill px-3 py-1.5 fw-bold">
                    <i class="fas fa-sign-in-alt me-1"></i> {{ __('Masuk / Daftar Akun') }}
                </a>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-1.5">
                    <i class="fas fa-shield-alt me-1"></i> {{ __('Akses: Tamu') }}
                </span>
            </div>
            @endauth
        </div>
        
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('catalog.index') }}" class="btn btn-primary fw-semibold rounded-pill px-4 py-2">
                <i class="fas fa-shopping-bag me-2"></i>{{ __('Jelajahi Katalog') }}
            </a>
            @guest
            <a href="{{ route('login') }}" class="btn btn-outline-primary fw-semibold rounded-pill px-3 py-2">
                <i class="fas fa-arrow-right-to-bracket me-1"></i>{{ __('Login') }}
            </a>
            @endguest
        </div>
    </div>
</div>

<!-- Flat Material 3 Stats Cards (2x2 Grid on Mobile, 4x1 on Desktop - All Clickable) -->
<div class="row g-3 mb-4">
    <!-- Card 1: Total Orders -->
    <div class="col-6 col-xl-3">
        <a href="{{ Auth::check() ? route('orders.index') : route('login') }}" @guest data-guest-modal="true" data-feature-name="riwayat pesanan" @endguest class="text-decoration-none d-block h-100">
            <div class="card m3-metric-card h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                        <div class="m3-metric-icon bg-primary-subtle text-primary">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.7rem;">{{ __('Akun') }}</span>
                    </div>
                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: 0.04em; font-size: 0.72rem;">{{ __('Total Pesanan') }}</div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h3 class="h3 fw-bold text-body mb-0">{{ $totalOrders ?? 0 }}</h3>
                        <i class="fas fa-chevron-right text-muted small opacity-50 d-none d-sm-inline"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 2: Completed Orders -->
    <div class="col-6 col-xl-3">
        <a href="{{ Auth::check() ? route('orders.index', ['status' => 'delivered']) : route('login') }}" @guest data-guest-modal="true" data-feature-name="pesanan selesai" @endguest class="text-decoration-none d-block h-100">
            <div class="card m3-metric-card h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                        <div class="m3-metric-icon bg-success-subtle text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.7rem;">{{ __('Berhasil') }}</span>
                    </div>
                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: 0.04em; font-size: 0.72rem;">{{ __('Selesai') }}</div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h3 class="h3 fw-bold text-body mb-0">{{ $completedOrders ?? 0 }}</h3>
                        <i class="fas fa-chevron-right text-muted small opacity-50 d-none d-sm-inline"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 3: Pending Orders -->
    <div class="col-6 col-xl-3">
        <a href="{{ Auth::check() ? route('orders.index', ['status' => 'pending_payment']) : route('login') }}" @guest data-guest-modal="true" data-feature-name="pesanan menunggu" @endguest class="text-decoration-none d-block h-100">
            <div class="card m3-metric-card h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                        <div class="m3-metric-icon bg-warning-subtle text-warning-emphasis">
                            <i class="fas fa-clock"></i>
                        </div>
                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.7rem;">{{ __('Pending') }}</span>
                    </div>
                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: 0.04em; font-size: 0.72rem;">{{ __('Menunggu') }}</div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h3 class="h3 fw-bold text-body mb-0">{{ $pendingOrders ?? 0 }}</h3>
                        <i class="fas fa-chevron-right text-muted small opacity-50 d-none d-sm-inline"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Card 4: Total Spent -->
    <div class="col-6 col-xl-3">
        <a href="{{ Auth::check() ? route('orders.index', ['status' => 'delivered']) : route('login') }}" @guest data-guest-modal="true" data-feature-name="total belanja" @endguest class="text-decoration-none d-block h-100">
            <div class="card m3-metric-card h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                        <div class="m3-metric-icon bg-info-subtle text-info-emphasis">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <span class="badge bg-info-subtle text-info-emphasis rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.7rem;">{{ __('Total') }}</span>
                    </div>
                    <div class="text-muted small fw-semibold text-uppercase mb-1" style="letter-spacing: 0.04em; font-size: 0.72rem;">{{ __('Total Belanja') }}</div>
                    <div class="d-flex align-items-baseline justify-content-between">
                        <h3 class="h4 fw-bold text-body mb-0">Rp {{ number_format($totalSpent ?? 0, 0, ',', '.') }}</h3>
                        <i class="fas fa-chevron-right text-muted small opacity-50 d-none d-sm-inline"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Main Content Split (Recent Orders & Quick Actions) -->
<div class="row g-4">
    <!-- Left Column: Recent Orders Table -->
    <div class="col-lg-8">
        <div class="card overflow-hidden h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center pt-3 px-4 pb-3">
                <h5 class="fw-bold mb-0 text-body">
                    <i class="fas fa-history text-primary me-2"></i>{{ __('Pesanan Terbaru') }}
                </h5>
                <a href="{{ route('orders.index') }}" @guest data-guest-modal="true" data-feature-name="riwayat pesanan" @endguest class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                    {{ __('Lihat Semua') }} <i class="fas fa-arrow-right ms-1 small"></i>
                </a>
            </div>
            <div class="card-body p-0">
                @guest
                    <div class="text-center py-5 px-3">
                        <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                            <i class="fas fa-user-lock" style="font-size: 1.85rem;"></i>
                        </div>
                        <h6 class="fw-bold text-body mb-1">{{ __('Masuk untuk Melihat Riwayat Transaksi') }}</h6>
                        <p class="text-muted small mb-3 mx-auto" style="max-width: 420px;">{{ __('Masuk atau buat akun baru untuk melihat status pesanan, mengakses lisensi dan akun digital Anda, serta mendapatkan bantuan garansi.') }}</p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="{{ route('login') }}" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="fas fa-sign-in-alt me-1"></i> {{ __('Masuk Akun') }}
                            </a>
                            <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                                <i class="fas fa-shopping-bag me-1"></i> {{ __('Lihat Katalog') }}
                            </a>
                        </div>
                    </div>
                @else
                    @if(isset($recentOrders) && count($recentOrders) > 0)
                    {{-- Desktop Table View --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">{{ __('No. Ref') }}</th>
                                    <th>{{ __('Produk') }}</th>
                                    <th>{{ __('Total Harga') }}</th>
                                    <th class="text-center">{{ __('Status') }}</th>
                                    <th class="pe-4 text-end">{{ __('Tanggal') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentOrders as $order)
                                <tr>
                                    <td class="ps-4 fw-bold text-primary font-monospace">
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
                                    <td class="pe-4 text-end text-secondary small">
                                        {{ $order->created_at->format('d M Y') }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile Activity Cards View --}}
                    <div class="d-md-none p-3">
                        @foreach($recentOrders as $order)
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
                            <a href="{{ route('orders.show', $order->id) }}" class="text-decoration-none text-body">
                                <div class="mobile-activity-card">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-primary font-monospace" style="font-size: 0.85rem;">{{ $order->reference }}</span>
                                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} rounded-pill px-2.5 py-1" style="font-size: 0.68rem;">
                                            {{ $label }}
                                        </span>
                                    </div>
                                    <div class="fw-semibold text-body mb-2" style="font-size: 0.88rem;">
                                        {{ Str::limit($order->product->name ?? '-', 35) }}
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center text-muted small" style="font-size: 0.78rem;">
                                        <span class="fw-bold text-body">Rp {{ number_format($order->total_price ?? 0, 0, ',', '.') }}</span>
                                        <span><i class="far fa-calendar-alt me-1"></i>{{ $order->created_at->format('d M Y') }}</span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5 px-3">
                        <div class="m3-tonal-item rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-box-open text-muted" style="font-size: 2.25rem;"></i>
                        </div>
                        <h6 class="fw-bold text-body mb-1">{{ __('Belum Ada Pesanan') }}</h6>
                        <p class="text-muted small mb-3">{{ __('Anda belum pernah melakukan pemesanan produk digital.') }}</p>
                        <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4 fw-bold">
                            <i class="fas fa-store me-2"></i>{{ __('Mulai Belanja') }}
                        </a>
                    </div>
                @endif
                @endguest
            </div>
        </div>
    </div>

    <!-- Right Column: Quick Actions & Support Widget -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 px-4 pb-3">
                <h5 class="fw-bold mb-0 text-body">
                    <i class="fas fa-bolt text-warning me-2"></i>{{ __('Akses Cepat') }}
                </h5>
            </div>
            <div class="card-body px-4 pb-4 pt-3">
                <div class="d-flex flex-column gap-2.5">
                    <a href="{{ route('catalog.index') }}" class="quick-action-btn">
                        <div class="qa-icon bg-primary-subtle text-primary">
                            <i class="fas fa-store"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-body">{{ __('Katalog Produk') }}</div>
                            <small class="text-muted">{{ __('Jelajahi produk digital terlaris') }}</small>
                        </div>
                        <i class="fas fa-chevron-right text-secondary ms-auto small"></i>
                    </a>

                    <a href="{{ route('orders.index') }}" @guest data-guest-modal="true" data-feature-name="riwayat pesanan" @endguest class="quick-action-btn">
                        <div class="qa-icon bg-success-subtle text-success">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-body">{{ __('Riwayat Pesanan') }}</div>
                            <small class="text-muted">{{ __('Cek detail & lisensi akun Anda') }}</small>
                        </div>
                        <i class="fas fa-chevron-right text-secondary ms-auto small"></i>
                    </a>

                    <a href="{{ route('chat.index') }}" @guest data-guest-modal="true" data-feature-name="pusat chat" @endguest class="quick-action-btn">
                        <div class="qa-icon bg-info-subtle text-info">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-body">{{ __('Pusat Bantuan Chat') }}</div>
                            <small class="text-muted">{{ __('Diskusi langsung dengan tim support') }}</small>
                        </div>
                        <i class="fas fa-chevron-right text-secondary ms-auto small"></i>
                    </a>

                    @if(config('telegram.bot_username'))
                    <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank" class="quick-action-btn">
                        <div class="qa-icon bg-primary-subtle text-primary">
                            <i class="fab fa-telegram"></i>
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
                <div class="m3-tonal-item p-3 mt-4">
                    <div class="d-flex align-items-center gap-2 mb-1 text-primary fw-bold small">
                        <i class="fas fa-info-circle"></i> {{ __('Butuh Pertolongan?') }}
                    </div>
                    <p class="text-muted small mb-0" style="font-size: 0.825rem; line-height: 1.45;">
                        {{ __('Jika Anda mengalami kendala transaksi atau garansi produk, gunakan fitur Kelola Komplain atau hubungi admin via Telegram.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
