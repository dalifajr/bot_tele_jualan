@extends('layouts.app')

@section('title', __('Review & Pembayaran'))
@section('page_subtitle', __('Review Pesanan'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="{{ __('Kembali ke Detail Produk') }}">
            <i class="fas fa-arrow-left text-body"></i>
        </a>
        <div>
            <h4 class="fw-bold m-0 text-body" style="font-size: 1.25rem;">{{ __('Review & Pembayaran') }}</h4>
            <small class="text-muted" style="font-size: 0.8rem;">{{ __('Periksa rincian pesanan Anda sebelum menyelesaikan transaksi') }}</small>
        </div>
    </div>
</div>

<div class="row g-4 mb-5 pb-4">
    {{-- Left Column: Product Summary & Coupon Form --}}
    <div class="col-lg-8">
        {{-- Product Card --}}
        <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0 text-body d-flex align-items-center gap-2">
                    <i class="fas fa-box-open text-primary"></i>
                    <span>{{ __('Rincian Produk') }}</span>
                </h5>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                    {{ __('Beli Langsung') }}
                </span>
            </div>

            <div class="card-body p-4">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-3 rounded-4 bg-light border mb-3">
                    <div class="d-flex align-items-center gap-3">
                        @if($product->image_url)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="rounded-3 object-fit-cover shadow-sm" style="width: 58px; height: 58px;">
                        @else
                            <div class="d-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-3 shadow-sm" style="width: 58px; height: 58px;">
                                <i class="fas fa-cubes fs-4"></i>
                            </div>
                        @endif
                        <div>
                            <h6 class="fw-bold mb-1 text-body">{{ $product->name }}</h6>
                            <div class="text-muted small">
                                <span class="fw-semibold text-primary">{{ $product->formatted_price }}</span> / unit
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between justify-content-sm-end gap-3 pt-2 pt-sm-0 border-top border-sm-0">
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 fw-bold">
                            {{ $quantity }} Unit
                        </span>
                        <div class="text-end">
                            <span class="text-muted small d-block" style="font-size: 0.72rem;">{{ __('Subtotal Item') }}</span>
                            <span class="fw-bold text-body fs-6">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                {{-- VPN Configurations Preview (If applicable) --}}
                @if($product->is_vpn)
                <div class="bg-primary-subtle p-3.5 rounded-4 border border-primary-subtle mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold">
                        <i class="fas fa-shield-halved"></i>
                        <span>{{ __('Konfigurasi Akun VPN') }}</span>
                    </div>
                    <div class="row g-2 small">
                        <div class="col-sm-6">
                            <div class="bg-white bg-opacity-75 rounded-3 p-2 border border-primary-subtle">
                                <span class="text-muted d-block" style="font-size: 0.72rem;">{{ __('Username') }}</span>
                                <strong class="text-body font-monospace">{{ $vpnUsername ?: '-' }}</strong>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="bg-white bg-opacity-75 rounded-3 p-2 border border-primary-subtle">
                                <span class="text-muted d-block" style="font-size: 0.72rem;">{{ __('Masa Aktif') }}</span>
                                <strong class="text-body">{{ $product->vpn_duration_days }} {{ __('Hari') }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Guarantee / Service Feature Badges --}}
                <div class="row g-2 pt-2">
                    <div class="col-6 col-md-4">
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <i class="fas fa-bolt text-warning"></i>
                            <span>{{ __('Pengiriman Otomatis') }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <i class="fas fa-shield-alt text-success"></i>
                            <span>{{ __('Garansi Transaksi') }}</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <i class="fas fa-headset text-primary"></i>
                            <span>{{ __('Bantuan Tim CS') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Coupon / Promo Code Card --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2 text-body d-flex align-items-center gap-2">
                    <i class="fas fa-ticket text-primary"></i>
                    <span>{{ __('Gunakan Kode Promo / Kupon') }}</span>
                </h5>
                <p class="text-muted small mb-3">{{ __('Masukkan kode kupon diskon Anda untuk mendapatkan potongan harga.') }}</p>

                <form action="{{ route('checkout.review', $product->id) }}" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="quantity" value="{{ $quantity }}">
                    @if($vpnUsername)<input type="hidden" name="vpn_username" value="{{ $vpnUsername }}">@endif
                    @if($vpnPassword)<input type="hidden" name="vpn_password" value="{{ $vpnPassword }}">@endif

                    <div class="col-sm-8 col-md-9">
                        <input type="text" name="coupon_code" class="form-control form-control-lg font-monospace text-uppercase rounded-3" placeholder="{{ __('CONTOH: DISKON10') }}" value="{{ $couponCode }}">
                    </div>
                    <div class="col-sm-4 col-md-3 d-grid">
                        <button type="submit" class="btn btn-outline-primary btn-lg rounded-pill fw-bold">
                            <i class="fas fa-check-circle me-1"></i> {{ __('Terapkan') }}
                        </button>
                    </div>
                </form>

                @if($coupon && $discount > 0)
                    <div class="alert alert-success d-flex align-items-center gap-2 rounded-3 mt-3 mb-0 py-2.5 px-3">
                        <i class="fas fa-check-circle fs-5 flex-shrink-0"></i>
                        <div class="small">
                            <strong>{{ __('Kupon Berhasil Diterapkan!') }}</strong> Potongan harga sebesar <strong>Rp {{ number_format($discount, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                @elseif($couponError)
                    <div class="alert alert-danger d-flex align-items-center gap-2 rounded-3 mt-3 mb-0 py-2.5 px-3">
                        <i class="fas fa-exclamation-circle fs-5 flex-shrink-0"></i>
                        <div class="small">{{ $couponError }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Right Column: Payment Summary Card (Sticky) --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 85px; z-index: 10;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                <h5 class="fw-bold m-0 text-body d-flex align-items-center gap-2">
                    <i class="fas fa-receipt text-primary"></i>
                    <span>{{ __('Ringkasan Pembayaran') }}</span>
                </h5>
            </div>

            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2 text-muted small">
                    <span>{{ __('Subtotal (:qty unit)', ['qty' => $quantity]) }}</span>
                    <span class="fw-semibold text-body">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>

                @if($discount > 0)
                <div class="d-flex justify-content-between align-items-center mb-2 text-success small">
                    <span><i class="fas fa-tag me-1"></i>{{ __('Diskon Kupon') }}</span>
                    <span class="fw-bold">-Rp {{ number_format($discount, 0, ',', '.') }}</span>
                </div>
                @endif

                <div class="d-flex justify-content-between align-items-center mb-3 text-muted small">
                    <span>
                        {{ __('Kode Unik') }} 
                        <i class="fas fa-circle-question text-secondary ms-0.5" title="{{ __('Kode unik untuk mempermudah verifikasi pembayaran otomatis') }}"></i>
                    </span>
                    <span class="fw-semibold text-body">+Rp {{ $uniqueCode }}</span>
                </div>

                <hr class="my-3 opacity-25">

                <div class="d-flex justify-content-between align-items-baseline mb-4">
                    <div>
                        <span class="text-secondary fw-semibold small d-block">{{ __('Total Pembayaran') }}</span>
                        <small class="text-muted" style="font-size: 0.7rem;">{{ __('Sudah termasuk kode unik') }}</small>
                    </div>
                    <span class="fw-bold text-primary fs-4">
                        Rp {{ number_format($totalAmount, 0, ',', '.') }}
                    </span>
                </div>

                {{-- Final Process Order Form --}}
                <form action="{{ route('checkout.store', $product->id) }}" method="POST" id="finalCheckoutForm">
                    @csrf
                    <input type="hidden" name="quantity" value="{{ $quantity }}">
                    @if($couponCode && $discount > 0)<input type="hidden" name="coupon_code" value="{{ $couponCode }}">@endif
                    @if($vpnUsername)<input type="hidden" name="vpn_username" value="{{ $vpnUsername }}">@endif
                    @if($vpnPassword)<input type="hidden" name="vpn_password" value="{{ $vpnPassword }}">@endif

                    <button type="submit" id="btnConfirmPay" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm py-3 d-flex align-items-center justify-content-center gap-2 lift-hover">
                        <i class="fas fa-lock"></i>
                        <span>{{ __('Konfirmasi & Bayar Sekarang') }}</span>
                    </button>
                </form>

                <div class="mt-3 text-center">
                    <a href="{{ route('catalog.show', $product->id) }}" class="text-decoration-none text-muted small hover-primary">
                        <i class="fas fa-arrow-left me-1"></i>{{ __('Ubah Pilihan Pembelian') }}
                    </a>
                </div>

                <div class="p-3 bg-light rounded-4 mt-4 text-center">
                    <div class="d-flex justify-content-center align-items-center gap-2 text-success small fw-semibold mb-1">
                        <i class="fas fa-shield-check"></i> {{ __('Transaksi Aman & Terverifikasi') }}
                    </div>
                    <p class="text-muted mb-0" style="font-size: 0.72rem; line-height: 1.4;">
                        {{ __('Metode pembayaran QRIS instan & transfer otomatis tersedia di langkah selanjutnya.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Mobile Sticky Action Bar for Checkout Review --}}
<div class="d-md-none bg-body border-top shadow-lg py-2.5 px-3 position-fixed bottom-0 start-0 end-0" style="z-index: 1045; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
    <div class="d-flex align-items-center justify-content-between gap-3">
        <div>
            <span class="text-muted d-block" style="font-size: 0.68rem;">{{ __('Total Pembayaran') }}</span>
            <span class="fw-bold text-primary" style="font-size: 1.15rem;">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
        </div>
        <button type="button" class="btn btn-primary rounded-pill px-4 py-2.5 fw-bold shadow-sm d-flex align-items-center gap-2" onclick="document.getElementById('finalCheckoutForm').submit();">
            <i class="fas fa-lock small"></i>
            <span>{{ __('Bayar Sekarang') }}</span>
        </button>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const finalForm = document.getElementById('finalCheckoutForm');
        const btnConfirm = document.getElementById('btnConfirmPay');

        if (finalForm && btnConfirm) {
            finalForm.addEventListener('submit', function() {
                btnConfirm.disabled = true;
                btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __("Menyiapkan Pembayaran...") }}';
            });
        }
    });
</script>
@endpush
@endsection
