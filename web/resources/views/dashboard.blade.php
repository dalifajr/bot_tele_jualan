@extends('layouts.app')

@section('title', __('Dashboard'))
@section('page_subtitle', __('Ruang Belanja Anda'))
@section('meta_description', 'Dashboard akun Anda — jelajahi katalog produk digital dan pantau pesanan Anda')

@section('content')
<div class="d-flex flex-column gap-4">
    {{-- Row 1: Greeting Hero Banner & Total Belanja Card (Figma M3 Style) --}}
    <div class="row g-3 align-items-stretch">
        {{-- Left: Greeting Hero --}}
        <div class="col-lg-8 col-12 d-flex flex-column justify-content-center">
            <div class="m3-dash-hero pe-lg-3 py-2">
                <div class="m3-dash-overline">{{ __('RUANG BELANJA ANDA') }}</div>
                <h1 class="m3-dash-headline">
                    {{ __('Halo,') }}<br>
                    <span>{{ Auth::check() ? (Auth::user()->username ?: (Auth::user()->full_name ?: 'dzulfikrialifajri')) : 'dzulfikrialifajri' }}</span>
                </h1>
                <p class="m3-dash-subtitle">
                    {{ __('Semua kebutuhan digital, dalam satu tempat.') }}
                </p>
                <div>
                    <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4 py-2.5 d-inline-flex align-items-center gap-2 fw-semibold" style="font-size: 0.92rem;">
                        <i class="fas fa-store"></i>
                        <span>{{ __('Jelajahi katalog') }}</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Right: Total Belanja Card --}}
        <div class="col-lg-4 col-12">
            <a href="{{ Auth::check() ? route('orders.index', ['status' => 'delivered']) : route('login') }}" @guest data-guest-modal="true" data-feature-name="total belanja" @endguest class="m3-balance-card d-block">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <i class="far fa-credit-card text-primary" style="font-size: 1.35rem;"></i>
                    </div>
                    <div class="text-secondary small fw-medium">{{ __('Total belanja') }}</div>
                    <div class="m3-balance-val">
                        Rp {{ number_format($totalSpent ?? 0, 0, ',', '.') }}
                    </div>
                </div>
                <div class="d-flex justify-content-end text-primary">
                    <i class="fas fa-arrow-right" style="font-size: 0.95rem;"></i>
                </div>
            </a>
        </div>
    </div>

    {{-- Row 2: Metric Mini Cards (3 Columns Desktop / 2x2 Mobile) --}}
    <div class="row g-3">
        {{-- Card 1: Total Pesanan --}}
        <div class="col-md-4 col-6">
            <a href="{{ Auth::check() ? route('orders.index') : route('login') }}" @guest data-guest-modal="true" data-feature-name="riwayat pesanan" @endguest class="m3-dash-metric-card d-block">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary small fw-medium">{{ __('Total pesanan') }}</span>
                    <i class="fas fa-shopping-bag text-primary opacity-75"></i>
                </div>
                <div class="d-flex align-items-baseline justify-content-between mt-3">
                    <div class="m3-dash-metric-val">{{ $totalOrders ?? 0 }}</div>
                    <i class="fas fa-arrow-right text-secondary small"></i>
                </div>
            </a>
        </div>

        {{-- Card 2: Selesai --}}
        <div class="col-md-4 col-6">
            <a href="{{ Auth::check() ? route('orders.index', ['status' => 'delivered']) : route('login') }}" @guest data-guest-modal="true" data-feature-name="pesanan selesai" @endguest class="m3-dash-metric-card d-block">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary small fw-medium">{{ __('Selesai') }}</span>
                    <i class="fas fa-check-circle text-success opacity-75"></i>
                </div>
                <div class="d-flex align-items-baseline justify-content-between mt-3">
                    <div class="m3-dash-metric-val">{{ $completedOrders ?? 0 }}</div>
                    <i class="fas fa-arrow-right text-secondary small"></i>
                </div>
            </a>
        </div>

        {{-- Card 3: Menunggu --}}
        <div class="col-md-4 col-12">
            <a href="{{ Auth::check() ? route('orders.index', ['status' => 'pending_payment']) : route('login') }}" @guest data-guest-modal="true" data-feature-name="pesanan menunggu" @endguest class="m3-dash-metric-card d-block" style="background: var(--surface-container-high);">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary small fw-medium">{{ __('Menunggu') }}</span>
                    <i class="far fa-clock text-danger opacity-75"></i>
                </div>
                <div class="d-flex align-items-baseline justify-content-between mt-3">
                    <div class="m3-dash-metric-val">{{ $pendingOrders ?? 0 }}</div>
                    <i class="fas fa-arrow-right text-secondary small"></i>
                </div>
            </a>
        </div>
    </div>

    {{-- Row 3: Split Section (Pesanan Terbaru & Akses Cepat) --}}
    <div class="row g-4">
        {{-- Left: Pesanan Terbaru --}}
        <div class="col-lg-8 col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 fw-bold mb-0 text-body" style="font-family: var(--font-google-sans-display); font-size: 1.25rem;">
                    {{ __('Pesanan terbaru') }}
                </h2>
                <a href="{{ route('orders.index') }}" @guest data-guest-modal="true" data-feature-name="riwayat pesanan" @endguest class="text-decoration-none small fw-semibold text-primary">
                    {{ __('Lihat semua') }}
                </a>
            </div>

            @guest
                <div class="m3-order-row-card p-4 text-center d-flex flex-column align-items-center justify-content-center">
                    <div class="m3-cloud-icon mb-3" style="width: 52px; height: 52px; font-size: 1.35rem;">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h6 class="fw-bold mb-1">{{ __('Masuk untuk Melihat Riwayat Pesanan') }}</h6>
                    <p class="text-secondary small mb-3" style="max-width: 400px;">
                        {{ __('Silakan masuk dengan akun Anda untuk memantau status transaksi dan mengakses produk digital yang Anda beli.') }}
                    </p>
                    <a href="{{ route('login') }}" class="btn btn-primary rounded-pill px-4 btn-sm fw-semibold">
                        {{ __('Masuk Sekarang') }}
                    </a>
                </div>
            @else
                @if(isset($recentOrders) && count($recentOrders) > 0)
                    @foreach($recentOrders as $order)
                        @php
                            $statusMap = [
                                'pending_payment' => [__('Menunggu'), 'warning', 'style="background: #ffd8e4; color: #633b48;"'],
                                'paid' => [__('Dibayar'), 'info', 'style="background: #eaddff; color: #21005d;"'],
                                'delivered' => [__('Selesai'), 'success', 'style="background: #c4eed0; color: #072711;"'],
                                'cancelled' => [__('Dibatalkan'), 'danger', 'style="background: #ffdad6; color: #410002;"'],
                                'expired' => [__('Kedaluwarsa'), 'secondary', 'style="background: #e6e0e9; color: #49454f;"'],
                            ];
                            [$label, $badgeClass, $inlineStyle] = $statusMap[$order->status] ?? [$order->status, 'secondary', ''];
                        @endphp
                        <a href="{{ route('orders.show', $order->id) }}" class="m3-order-row-card">
                            <div class="d-flex flex-column pe-3">
                                <span class="fw-bold text-body" style="font-size: 0.92rem; line-height: 1.3;">
                                    {{ Str::limit($order->product->name ?? '-', 35) }}
                                </span>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="text-secondary font-monospace" style="font-size: 0.75rem;">{{ $order->reference }}</span>
                                    <span class="text-secondary small">•</span>
                                    <span class="text-secondary small" style="font-size: 0.75rem;">{{ $order->created_at->format('d M Y') }}</span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3 ms-auto text-end">
                                <span class="fw-bold text-body" style="font-size: 0.95rem;">
                                    Rp {{ number_format($order->total_price ?? 0, 0, ',', '.') }}
                                </span>
                                <span class="badge rounded-pill px-3 py-1.5 fw-semibold" {!! $inlineStyle !!} style="font-size: 0.75rem;">
                                    {{ $label }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                @else
                    <div class="m3-order-row-card p-4 text-center d-flex flex-column align-items-center justify-content-center">
                        <i class="fas fa-box-open text-secondary mb-2" style="font-size: 2rem;"></i>
                        <div class="fw-bold text-body mb-1">{{ __('Belum Ada Pesanan') }}</div>
                        <p class="text-secondary small mb-3">{{ __('Mulai belanja produk digital pilihan Anda dari katalog kami.') }}</p>
                        <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4 btn-sm fw-semibold">
                            {{ __('Mulai Belanja') }}
                        </a>
                    </div>
                @endif
            @endguest
        </div>

        {{-- Right: Akses Cepat --}}
        <div class="col-lg-4 col-12">
            <h2 class="h5 fw-bold mb-3 text-body" style="font-family: var(--font-google-sans-display); font-size: 1.25rem;">
                {{ __('Akses cepat') }}
            </h2>

            <div class="d-flex flex-column gap-2">
                {{-- 1. Katalog Produk --}}
                <a href="{{ route('catalog.index') }}" class="m3-quick-card">
                    <div class="m3-cloud-icon" style="width: 36px; height: 36px; min-width: 36px; border-radius: 10px; font-size: 1rem;">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                        <span class="fw-bold text-body" style="font-size: 0.88rem;">{{ __('Katalog produk') }}</span>
                        <small class="text-secondary text-truncate" style="font-size: 0.75rem;">{{ __('Temukan kebutuhan digital') }}</small>
                    </div>
                    <i class="fas fa-chevron-right text-secondary small"></i>
                </a>

                {{-- 2. Riwayat Pesanan --}}
                <a href="{{ route('orders.index') }}" @guest data-guest-modal="true" data-feature-name="riwayat pesanan" @endguest class="m3-quick-card">
                    <div class="m3-cloud-icon" style="width: 36px; height: 36px; min-width: 36px; border-radius: 10px; font-size: 1rem;">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                        <span class="fw-bold text-body" style="font-size: 0.88rem;">{{ __('Riwayat pesanan') }}</span>
                        <small class="text-secondary text-truncate" style="font-size: 0.75rem;">{{ __('Cek transaksi dan lisensi') }}</small>
                    </div>
                    <i class="fas fa-chevron-right text-secondary small"></i>
                </a>

                {{-- 3. Pusat Bantuan Chat --}}
                <a href="{{ route('chat.index') }}" @guest data-guest-modal="true" data-feature-name="pusat chat" @endguest class="m3-quick-card">
                    <div class="m3-cloud-icon" style="width: 36px; height: 36px; min-width: 36px; border-radius: 10px; font-size: 1rem;">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                        <span class="fw-bold text-body" style="font-size: 0.88rem;">{{ __('Pusat bantuan chat') }}</span>
                        <small class="text-secondary text-truncate" style="font-size: 0.75rem;">{{ __('Diskusi dengan tim support') }}</small>
                    </div>
                    <i class="fas fa-chevron-right text-secondary small"></i>
                </a>

                {{-- 4. Bot Telegram --}}
                @if(config('telegram.bot_username'))
                <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank" rel="noopener noreferrer" class="m3-quick-card">
                    <div class="m3-cloud-icon" style="width: 36px; height: 36px; min-width: 36px; border-radius: 10px; font-size: 1rem;">
                        <i class="fab fa-telegram"></i>
                    </div>
                    <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                        <span class="fw-bold text-body" style="font-size: 0.88rem;">{{ __('Bot Telegram') }}</span>
                        <small class="text-secondary text-truncate" style="font-size: 0.75rem;">{{ __('Transaksi & notifikasi otomatis') }}</small>
                    </div>
                    <i class="fas fa-arrow-up-right-from-square text-secondary" style="font-size: 0.75rem;"></i>
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Row 4: Complaint Support Banner (Full Width Bottom - Figma M3 Style) --}}
    <div class="m3-complaint-banner">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-white text-primary flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.15rem;">
                <i class="fas fa-headset"></i>
            </div>
            <div class="d-flex flex-column">
                <span class="fw-bold" style="font-size: 1rem;">{{ __('Ada kendala dengan pesanan Anda?') }}</span>
                <span class="small opacity-85">{{ __('Buka Kelola Komplain untuk bantuan transaksi atau garansi produk.') }}</span>
            </div>
        </div>
        <div>
            <a href="{{ Auth::check() ? route('customer.complaints.index') : route('login') }}" @guest data-guest-modal="true" data-feature-name="kelola komplain" @endguest class="btn btn-light rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2" style="color: var(--primary); font-size: 0.875rem;">
                <i class="fas fa-headset"></i>
                <span>{{ __('Kelola komplain') }}</span>
            </a>
        </div>
    </div>
</div>
@endsection
