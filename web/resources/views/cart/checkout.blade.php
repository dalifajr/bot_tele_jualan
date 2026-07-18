@extends('layouts.app')

@section('title', 'Review Pembelian')
@section('page_subtitle', 'Checkout')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        {{-- Ordered Items Review --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-receipt text-primary me-2"></i>{{ __('Review Pembelian') }}</h5>
                
                <div class="table-responsive">
                    <table class="table align-middle border-0 mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th scope="col" class="border-0 pb-3" style="width: 60%;">{{ __('PRODUK') }}</th>
                                <th scope="col" class="border-0 pb-3 text-center" style="width: 15%;">{{ __('JUMLAH') }}</th>
                                <th scope="col" class="border-0 pb-3 text-end" style="width: 25%;">{{ __('SUBTOTAL') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cartItems as $item)
                            @if($item->product)
                            <tr>
                                <td class="border-secondary-subtle py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center justify-content-center bg-light rounded-3 text-primary" style="width: 46px; height: 46px; border: 1px solid var(--bs-border-color);">
                                            <i class="fas fa-box-open fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-0">{{ $item->product->name }}</h6>
                                            <small class="text-muted">{{ $item->product->formatted_price }} / unit</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="border-secondary-subtle py-3 text-center fw-bold text-dark">
                                    {{ $item->quantity }}
                                </td>
                                <td class="border-secondary-subtle py-3 text-end fw-bold">
                                    Rp{{ number_format($item->product->price * $item->quantity, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Coupon/Promo Code Card --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="fas fa-ticket-alt text-primary me-2"></i>{{ __('Gunakan Kode Promo / Kupon') }}</h5>
                <p class="text-muted small mb-3">{{ __('Masukkan kode kupon diskon Anda untuk mendapatkan potongan harga spesial.') }}</p>
                
                <form action="{{ route('cart.checkout') }}" method="GET" class="row g-2 align-items-center">
                    <div class="col-sm-8 col-md-9">
                        <input type="text" name="coupon_code" class="form-control form-control-lg font-monospace text-uppercase" placeholder="{{ __('KODEPROMO') }}" value="{{ $couponCode }}" style="border-radius: 10px;">
                    </div>
                    <div class="col-sm-4 col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill">
                            <i class="fas fa-check-circle me-1"></i> {{ __('Terapkan') }}
                        </button>
                    </div>
                </form>

                @if($couponCode)
                    <div class="mt-3">
                        @if($coupon && !$couponError)
                            <div class="alert alert-success border-0 d-flex align-items-center gap-2 mb-0" style="border-radius: 12px;">
                                <i class="fas fa-check-circle fs-5"></i>
                                <div>
                                    {{ __('Kupon') }} <strong>{{ $coupon->code }}</strong> {{ __('berhasil diterapkan! Potongan harga sebesar') }} 
                                    <strong>
                                        @if($coupon->type === 'percent')
                                            {{ $coupon->value }}% (Rp{{ number_format($discount, 0, ',', '.') }})
                                        @else
                                            Rp{{ number_format($discount, 0, ',', '.') }}
                                        @endif
                                    </strong>.
                                </div>
                            </div>
                        @else
                            <div class="alert alert-danger border-0 d-flex align-items-center gap-2 mb-0" style="border-radius: 12px;">
                                <i class="fas fa-exclamation-circle fs-5"></i>
                                <div>{{ $couponError ?: 'Kupon tidak valid.' }}</div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Price Summary and Checkout Form --}}
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">{{ __('Total Pembayaran') }}</h5>
                
                <div class="d-flex justify-content-between mb-2 text-muted small">
                    <span>{{ __('Subtotal') }}</span>
                    <span>Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>

                @if($discount > 0)
                <div class="d-flex justify-content-between mb-2 text-success small">
                    <span>{{ __('Diskon Kupon') }}</span>
                    <span>-Rp{{ number_format($discount, 0, ',', '.') }}</span>
                </div>
                @endif

                <div class="d-flex justify-content-between mb-3 text-muted small">
                    <span>{{ __('Kode Unik Pembayaran') }}</span>
                    <span>+Rp{{ number_format($uniqueCode, 0, ',', '.') }}</span>
                </div>
                
                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="fw-bold">{{ __('Total Pembayaran') }}</span>
                    <span class="fs-4 fw-bold text-primary">Rp{{ number_format($total, 0, ',', '.') }}</span>
                </div>

                <div class="alert alert-warning border-0 rounded-3 mb-4 small p-2" role="alert">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    {{ __('Kode unik pembayaran digunakan untuk verifikasi otomatis. Pastikan transfer dalam nominal yang tepat.') }}
                </div>

                <form action="{{ route('cart.process') }}" method="POST" id="formProcessCheckout">
                    @csrf
                    @if($coupon && !$couponError)
                        <input type="hidden" name="coupon_code" value="{{ $coupon->code }}">
                    @endif
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success rounded-pill py-3 fw-bold btn-lg">
                            <i class="fas fa-check-double me-2"></i>{{ __('Buat Pesanan Sekarang') }}
                        </button>
                        <a href="{{ route('cart.index') }}" class="btn btn-light text-primary rounded-pill py-2">
                            {{ __('Kembali ke Keranjang') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('formProcessCheckout').addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Konfirmasi Pembuatan Pesanan',
            text: 'Apakah Anda yakin ingin memproses pesanan ini? Batas waktu pembayaran adalah 15 menit setelah pesanan dibuat.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Buat Pesanan!',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'rounded-4',
                confirmButton: 'btn btn-success rounded-pill px-4',
                cancelButton: 'btn btn-secondary rounded-pill px-4 ms-2'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                const pageLoader = document.getElementById('pageLoader');
                if (pageLoader) {
                    pageLoader.classList.remove('fade-out');
                }
                if (typeof startTopLoadingBar === 'function') {
                    startTopLoadingBar();
                }
                this.submit();
            }
        });
    });
</script>
@endpush
