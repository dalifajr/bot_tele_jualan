@extends('layouts.app')

@section('title', 'Keranjang Belanja')
@section('page_subtitle', 'Keranjang')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-shopping-cart text-primary me-2"></i>{{ __('Keranjang Belanja Anda') }}</h5>

                {{-- Flash Messages --}}
                @if(session('success'))
                <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2 mb-4" style="border-radius: 12px;">
                    <i class="fas fa-check-circle fs-5"></i>
                    <div>{{ session('success') }}</div>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-2 mb-4" style="border-radius: 12px;">
                    <i class="fas fa-exclamation-circle fs-5"></i>
                    <div>{{ session('error') }}</div>
                </div>
                @endif

                @if($cartItems->isEmpty()
                <div class="text-center py-5 text-muted">
                    <div class="mb-3">
                        <i class="fas fa-shopping-basket fs-1 text-secondary opacity-50"></i>
                    </div>
                    <h6 class="fw-bold">{{ __('Keranjang Anda Masih Kosong') }}</h6>
                    <p class="small text-muted mb-4">{{ __('Temukan produk digital berkualitas di katalog kami.') }}</p>
                    <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-search me-1"></i> {{ __('Telusuri Produk') }}
                    </a>
                </div>
                @else
                <div class="table-responsive">
                    <table class="table align-middle border-0 mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th scope="col" class="border-0 pb-3" style="width: 50%;">{{ __('PRODUK') }}</th>
                                <th scope="col" class="border-0 pb-3 text-center" style="width: 25%;">{{ __('JUMLAH') }}</th>
                                <th scope="col" class="border-0 pb-3 text-end" style="width: 25%;">{{ __('SUBTOTAL') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cartItems as $item)
                            @if($item->{{ __('product)') }}
                            <tr class="align-middle">
                                <td class="border-secondary-subtle py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center justify-content-center bg-light rounded-3 text-primary" style="width: 50px; height: 50px; border: 1px solid var(--bs-border-color);">
                                            <i class="fas fa-box-open fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-1"><a href="{{ route('catalog.show', $item->product->id) }}" class="text-decoration-none text-body">{{ $item->product->name }}</a></h6>
                                            <span class="text-primary fw-bold small">{{ $item->product->formatted_price }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="border-secondary-subtle py-3 text-center">
                                    <div class="d-inline-flex align-items-center bg-light rounded-pill border p-1" style="height: 38px;">
                                        <form action="{{ route('cart.update', $item->id) }}" method="POST" class="d-inline m-0">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="quantity" value="{{ $item->quantity - 1 }}">
                                            <button type="submit" class="btn btn-sm btn-link text-decoration-none px-2 py-0 text-muted" {{ $item->{{ __('quantity') }} <= 1 ? 'disabled' : '' }}>
                                                <i class="fas fa-minus fs-6"></i>
                                            </button>
                                        </form>
                                        <span class="px-2 fw-bold text-dark" style="min-width: 24px;">{{ $item->quantity }}</span>
                                        <form action="{{ route('cart.update', $item->id) }}" method="POST" class="d-inline m-0">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="quantity" value="{{ $item->quantity + 1 }}">
                                            <button type="submit" class="btn btn-sm btn-link text-decoration-none px-2 py-0 text-muted">
                                                <i class="fas fa-plus fs-6"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <div class="mt-1">
                                        <form action="{{ route('cart.remove', $item->id) }}" method="POST" class="d-inline m-0" onsubmit="confirmAction(event, 'Hapus produk ini dari keranjang?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link text-danger text-decoration-none p-0 small" style="font-size: 0.8rem;">
                                                <i class="fas fa-trash-alt me-1"></i> {{ __('Hapus') }}
                                            </button>
                                        </form>
                                    </div>
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
                @endif
            </div>
        </div>
    </div>

    @if(!$cartItems->isEmpty()
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">{{ __('Ringkasan Belanja') }}</h5>
                
                <div class="d-flex justify-content-between mb-3 text-muted">
                    <span>Total Barang ({{ $cartItems->sum('quantity') }} unit)</span>
                    <span>Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>
                
                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="fw-bold">{{ __('Estimasi Total') }}</span>
                    <span class="fs-4 fw-bold text-primary">Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('cart.checkout') }}" class="btn btn-success rounded-pill py-3 fw-bold">
                        <i class="fas fa-shopping-bag me-2"></i>{{ __('Lanjut ke Checkout') }}
                    </a>
                    <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary rounded-pill py-2">
                        {{ __('Kembali Belanja') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
