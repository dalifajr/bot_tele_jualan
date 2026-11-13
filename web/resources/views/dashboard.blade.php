@extends('layouts.app')

@section('title', __('Dashboard'))
@section('page_subtitle', __('Ruang Belanja Anda'))
@section('meta_description', 'Dashboard akun Anda — jelajahi katalog produk digital dan pantau pesanan Anda')

@section('content')
<div class="d-flex flex-column gap-4">
    {{-- Row 1: Greeting Hero Banner --}}
    <div class="row g-3">
        <div class="col-12">
            <div class="m3-dash-hero py-2">
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
    </div>

    {{-- Row 2: Unified Metric Cards (Total Belanja + 3 Metric Cards) --}}
    <div class="row g-3">
        {{-- Card 1: Total Belanja --}}
        <div class="col-xl-3 col-md-6 col-6">
            <a href="{{ Auth::check() ? route('orders.index', ['status' => 'delivered']) : route('login') }}" @guest data-guest-modal="true" data-feature-name="total belanja" @endguest class="m3-dash-metric-card d-block">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary small fw-medium">{{ __('Total belanja') }}</span>
                    <i class="far fa-credit-card text-primary opacity-75"></i>
                </div>
                <div class="d-flex align-items-baseline justify-content-between mt-3">
                    <div class="m3-dash-metric-val" style="font-size: 1.15rem;">Rp {{ number_format($totalSpent ?? 0, 0, ',', '.') }}</div>
                    <i class="fas fa-arrow-right text-secondary small"></i>
                </div>
            </a>
        </div>

        {{-- Card 2: Total Pesanan --}}
        <div class="col-xl-3 col-md-6 col-6">
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

        {{-- Card 3: Selesai --}}
        <div class="col-xl-3 col-md-6 col-6">
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

        {{-- Card 4: Menunggu --}}
        <div class="col-xl-3 col-md-6 col-6">
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
                <h2 class="m3-section-title mb-0">
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

        {{-- Right: Informasi & Promosi Pilihan (Scrollable Stack / Carousel M3) --}}
        <div class="col-lg-4 col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="m3-section-title mb-0">
                    {{ __('Promo & info terkini') }}
                </h2>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" id="promoScrollPrev" class="btn btn-sm btn-icon rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 32px; height: 32px; background: var(--surface-container-high); border: 1px solid var(--outline-variant); color: var(--on-surface);" aria-label="Geser ke kiri" title="Sebelumnya">
                        <i class="fas fa-chevron-left" style="font-size: 0.72rem;"></i>
                    </button>
                    <button type="button" id="promoScrollNext" class="btn btn-sm btn-icon rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 32px; height: 32px; background: var(--surface-container-high); border: 1px solid var(--outline-variant); color: var(--on-surface);" aria-label="Geser ke kanan" title="Berikutnya">
                        <i class="fas fa-chevron-right" style="font-size: 0.72rem;"></i>
                    </button>
                </div>
            </div>

            <div class="m3-promo-carousel-container">
                <div class="m3-promo-track" id="promoTrack">
                    {{-- 1. Promo Diskon Lisensi --}}
                    <div class="m3-promo-card" style="background: linear-gradient(135deg, rgba(11, 87, 208, 0.08), var(--surface-container));">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge rounded-pill fw-bold" style="background: var(--primary); color: #ffffff; font-size: 0.72rem; letter-spacing: 0.03em;">PROMO SPESIAL</span>
                            <i class="fas fa-tags text-primary opacity-50 fs-5"></i>
                        </div>
                        <h3 class="fw-bold text-body mb-1" style="font-size: 1rem; line-height: 1.35;">{{ __('Diskon Lisensi s/d 30%') }}</h3>
                        <p class="text-secondary small mb-3" style="font-size: 0.8125rem; line-height: 1.4;">{{ __('Gunakan kupon promo spesial saat checkout untuk menikmati potongan harga langsung produk digital.') }}</p>
                        <a href="{{ route('catalog.index') }}" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.8125rem;">
                            <span>{{ __('Klaim promo') }}</span>
                            <i class="fas fa-arrow-right" style="font-size: 0.7rem;"></i>
                        </a>
                    </div>

                    {{-- 2. Keamanan Akun 2FA --}}
                    <div class="m3-promo-card" style="background: linear-gradient(135deg, rgba(56, 106, 33, 0.08), var(--surface-container));">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge rounded-pill fw-bold" style="background: #2e7d32; color: #ffffff; font-size: 0.72rem; letter-spacing: 0.03em;">KEAMANAN AKUN</span>
                            <i class="fas fa-shield-alt text-success opacity-50 fs-5"></i>
                        </div>
                        <h3 class="fw-bold text-body mb-1" style="font-size: 1rem; line-height: 1.35;">{{ __('Lindungi Akun dengan 2FA') }}</h3>
                        <p class="text-secondary small mb-3" style="font-size: 0.8125rem; line-height: 1.4;">{{ __('Aktifkan Two-Factor Authentication via Google Authenticator untuk keamanan berlapis saat transaksi.') }}</p>
                        <a href="{{ route('profile') }}" @guest data-guest-modal="true" data-feature-name="profil" @endguest class="btn btn-sm btn-outline-success rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.8125rem;">
                            <span>{{ __('Atur keamanan') }}</span>
                            <i class="fas fa-lock" style="font-size: 0.7rem;"></i>
                        </a>
                    </div>

                    {{-- 3. Notifikasi Bot Telegram --}}
                    <div class="m3-promo-card" style="background: linear-gradient(135deg, rgba(34, 158, 217, 0.1), var(--surface-container));">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge rounded-pill fw-bold" style="background: #0288d1; color: #ffffff; font-size: 0.72rem; letter-spacing: 0.03em;">NOTIFIKASI INSTAN</span>
                            <i class="fab fa-telegram text-info opacity-50 fs-5"></i>
                        </div>
                        <h3 class="fw-bold text-body mb-1" style="font-size: 1rem; line-height: 1.35;">{{ __('Update Cepat via Telegram') }}</h3>
                        <p class="text-secondary small mb-3" style="font-size: 0.8125rem; line-height: 1.4;">{{ __('Dapatkan pembaruan status pengiriman lisensi akun dan bukti transaksi secara otomatis di Telegram.') }}</p>
                        @if(config('telegram.bot_username'))
                        <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 text-white" style="background: #229ed9; font-size: 0.8125rem;">
                            <span>{{ __('Buka Telegram') }}</span>
                            <i class="fas fa-arrow-up-right-from-square" style="font-size: 0.7rem;"></i>
                        </a>
                        @else
                        <a href="{{ route('catalog.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold" style="font-size: 0.8125rem;">
                            <span>{{ __('Lihat katalog') }}</span>
                        </a>
                        @endif
                    </div>

                    {{-- 4. Bantuan & Garansi Toko --}}
                    <div class="m3-promo-card" style="background: linear-gradient(135deg, rgba(142, 68, 173, 0.08), var(--surface-container));">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge rounded-pill fw-bold" style="background: #8e44ad; color: #ffffff; font-size: 0.72rem; letter-spacing: 0.03em;">GARANSI RESMI</span>
                            <i class="fas fa-headset text-purple opacity-50 fs-5"></i>
                        </div>
                        <h3 class="fw-bold text-body mb-1" style="font-size: 1rem; line-height: 1.35;">{{ __('Pusat Komplain & Garansi') }}</h3>
                        <p class="text-secondary small mb-3" style="font-size: 0.8125rem; line-height: 1.4;">{{ __('Kendala akun atau lisensi? Buka tiket komplain kapan saja untuk penanganan cepat dalam 1x24 jam.') }}</p>
                        <a href="{{ Auth::check() ? route('customer.complaints.index') : route('login') }}" @guest data-guest-modal="true" data-feature-name="kelola komplain" @endguest class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.8125rem;">
                            <span>{{ __('Buka bantuan') }}</span>
                            <i class="fas fa-life-ring" style="font-size: 0.7rem;"></i>
                        </a>
                    </div>
                </div>

                {{-- Dots Navigation Indicators --}}
                <div class="d-flex justify-content-center align-items-center gap-1.5 mt-2" id="promoDots">
                    <span class="m3-promo-dot active" data-index="0"></span>
                    <span class="m3-promo-dot" data-index="1"></span>
                    <span class="m3-promo-dot" data-index="2"></span>
                    <span class="m3-promo-dot" data-index="3"></span>
                </div>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const track = document.getElementById('promoTrack');
    const prevBtn = document.getElementById('promoScrollPrev');
    const nextBtn = document.getElementById('promoScrollNext');
    const dots = document.querySelectorAll('#promoDots .m3-promo-dot');

    if (!track) return;

    function getCardWidth() {
        const firstCard = track.querySelector('.m3-promo-card');
        return firstCard ? firstCard.offsetWidth + 12 : 280;
    }

    function updateDots() {
        const cardW = getCardWidth();
        const activeIdx = Math.round(track.scrollLeft / cardW);
        dots.forEach((dot, idx) => {
            if (idx === activeIdx) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            track.scrollBy({ left: -getCardWidth(), behavior: 'smooth' });
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            track.scrollBy({ left: getCardWidth(), behavior: 'smooth' });
        });
    }

    dots.forEach((dot, idx) => {
        dot.addEventListener('click', function () {
            const cardW = getCardWidth();
            track.scrollTo({ left: idx * cardW, behavior: 'smooth' });
        });
    });

    track.addEventListener('scroll', updateDots, { passive: true });
});
</script>
@endpush
