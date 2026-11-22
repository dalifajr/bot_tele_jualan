@extends('layouts.app')

@section('title', __('Detail Pesanan ') . $order->reference)
@section('page_subtitle', __('Pesanan'))

@section('content')
{{-- Header --}}
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-3">
    <div class="d-flex align-items-center gap-2 min-w-0">
        <a href="{{ route('seller.orders.index') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Daftar Pesanan') }}">
            <i class="fas fa-arrow-left text-body"></i>
        </a>
        <div class="min-w-0">
            <h5 class="fw-bold m-0 text-body text-truncate" style="font-size: 1.15rem;">{{ __('Detail Pesanan Toko') }}</h5>
            <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;">{{ $order->reference }} &bull; {{ $order->created_at->format('d M Y, H:i') }}</small>
        </div>
    </div>
    <div class="ms-auto ms-sm-0 flex-shrink-0">
        <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-1.5 fw-bold" style="font-size: 0.8rem;">
            {{ $order->status_label }}
        </span>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-3"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

<div class="row g-3 g-md-4">
    {{-- Main Left Column --}}
    <div class="col-lg-8">
        {{-- Multi-Seller Isolation Notice --}}
        @if(!empty($isMultiSellerOrder))
        <div class="alert alert-info rounded-4 border-0 mb-3 d-flex align-items-start gap-2 small">
            <i class="fas fa-info-circle mt-1 flex-shrink-0"></i>
            <div>
                <strong>{{ __('Pesanan Keranjang Gabungan:') }}</strong>
                {{ __('Pembeli memesan produk dari beberapa toko dalam satu transaksi. Demi privasi, halaman ini hanya menampilkan item, kredensial stok, dan rincian pendapatan milik toko Anda.') }}
            </div>
        </div>
        @endif

        {{-- Product Details Card --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-body mb-0">
                    <i class="fas fa-box text-primary me-2"></i>{{ __('Item Toko Anda yang Dipesan') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                @php
                    $itemsToRender = $sellerItems ?? $order->items;
                    $stocksToRender = $sellerStockUnits ?? $order->stockUnits;
                    $complaintsToRender = $sellerComplaints ?? ($order->complaintCase ? collect([$order->complaintCase]) : collect());
                    $displaySubtotal = isset($sellerSubtotal)
                        ? (int) $sellerSubtotal
                        : (($order->subtotal > 0) ? (int) $order->subtotal : (int) $itemsToRender->sum(fn($i) => $i->unit_price * $i->quantity));
                    $displayDiscount = isset($sellerDiscount) ? (int) $sellerDiscount : (int) ($order->discount_amount ?? 0);
                    $hasVoucher = ($displayDiscount > 0) || (!empty($order->coupon_code) && empty($isMultiSellerOrder));
                    $displayNetTotal = isset($sellerNetTotal) ? (int) $sellerNetTotal : (int) $order->total_amount;
                @endphp
                <div class="list-group list-group-flush border rounded-3 overflow-hidden mt-1">
                    @foreach($itemsToRender as $item)
                    <div class="list-group-item p-2.5 p-md-3 bg-light d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2.5 border-bottom">
                        <div class="d-flex align-items-center gap-2.5 min-w-0 flex-grow-1">
                            <div class="rounded-3 bg-white border p-1.5 d-flex align-items-center justify-content-center text-primary flex-shrink-0" style="width: 50px; height: 50px;">
                                @if($item->product && $item->product->image_url)
                                    <img src="{{ $item->product->image_url }}" alt="{{ $item->product->name }}" class="img-fluid rounded-2" style="max-height: 42px; object-fit: contain;">
                                @else
                                    <i class="fas fa-cube fa-lg opacity-75"></i>
                                @endif
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="fw-bold text-body mb-1 text-truncate" style="font-size: 0.92rem;">{{ $item->product->name ?? '-' }}</h6>
                                <div class="text-muted small" style="font-size: 0.78rem;">
                                    {{ $item->quantity }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }}
                                </div>
                            </div>
                        </div>
                        <div class="text-end d-flex justify-content-between justify-content-sm-end align-items-center pt-2 pt-sm-0 border-top border-sm-top-0 flex-shrink-0">
                            <span class="text-muted small d-sm-none">{{ __('Subtotal:') }}</span>
                            <span class="fw-bold text-body fs-6">Rp {{ number_format($item->unit_price * $item->quantity, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="border-top mt-3 pt-3">
                    <div class="d-flex justify-content-between mb-1.5 small text-muted">
                        <span>{{ __('Subtotal Produk') }}</span>
                        <span class="fw-semibold text-body">Rp {{ number_format($displaySubtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($hasVoucher)
                    <div class="d-flex justify-content-between align-items-center mb-1.5 small gap-2">
                        <span class="text-muted d-inline-flex align-items-center flex-wrap gap-1.5">
                            <span>{{ __('Diskon Voucher') }}</span>
                            @if($order->coupon_code)
                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 font-monospace" style="font-size: 0.72rem;">
                                <i class="fas fa-tag me-1"></i>{{ $order->coupon_code }}
                            </span>
                            @endif
                        </span>
                        <span class="fw-semibold text-success text-nowrap">- Rp {{ number_format($displayDiscount, 0, ',', '.') }}</span>
                    </div>
                    @endif
                    @if(empty($isMultiSellerOrder) && $order->unique_code)
                    <div class="d-flex justify-content-between mb-1.5 small text-muted">
                        <span>{{ __('Kode Unik') }}</span>
                        <span class="fw-semibold text-body">+ Rp {{ number_format((int) $order->unique_code, 0, ',', '.') }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between pt-2 border-top fw-bold fs-6">
                        <span>{{ !empty($isMultiSellerOrder) ? __('Nilai Porsi Toko Anda') : __('Total Pendapatan Pesanan') }}</span>
                        <span class="text-primary">Rp {{ number_format(!empty($isMultiSellerOrder) ? $displayNetTotal : (int) $order->total_amount, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sent Stock / Credentials Card (Strictly Isolated to This Seller) --}}
        @if($stocksToRender && $stocksToRender->count() > 0)
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-body mb-0">
                    <i class="fas fa-key text-success me-2"></i>{{ __('Data Akun / Lisensi Terkirim (Toko Anda)') }} ({{ $stocksToRender->count() }} unit)
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column gap-3 mt-1">
                    @php $groupedUnits = $stocksToRender->groupBy('product_id'); @endphp
                    @foreach($groupedUnits as $productId => $units)
                        @php $productName = $units->first()->product->name ?? __('Produk'); @endphp
                        <div class="mb-1">
                            <span class="badge bg-primary-subtle text-primary mb-2 fw-bold" style="font-size: 0.85rem;">{{ $productName }}</span>
                            <div class="d-flex flex-column gap-2">
                                @foreach($units as $unit)
                                <div>
                                    @if($units->count() > 1)
                                        <div class="badge bg-secondary-subtle text-secondary mb-1">Unit #{{ $loop->iteration }}</div>
                                    @endif
                                    <x-account-credential-viewer :rawText="$unit->raw_text" />
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Complaint Cases Scoped Strictly to This Seller --}}
        @foreach($complaintsToRender as $sellerCmp)
        <div class="card border-0 shadow-sm mb-3 border-start border-warning border-4" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-warning-emphasis mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>{{ __('Pusat Resolusi / Komplain Aktif') }}
                    </h6>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-1">
                        {{ ucfirst($sellerCmp->status) }}
                    </span>
                </div>
                @if($sellerCmp->resolveTargetProduct())
                    <div class="small fw-semibold text-body mb-1">{{ __('Produk:') }} {{ $sellerCmp->resolveTargetProduct()->name }}</div>
                @endif
                <p class="text-muted small mb-3">{{ $sellerCmp->complaint_text }}</p>
                <a href="{{ route('seller.complaints.show', $sellerCmp->id) }}" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                    <i class="fas fa-comments me-1"></i>{{ __('Lihat Ruang Mediasi') }}
                </a>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Right Side Column --}}
    <div class="col-lg-4">
        {{-- Customer Info Card --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-user text-primary me-2"></i>{{ __('Informasi Pembeli') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center gap-3 mb-3 mt-1">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-5 flex-shrink-0 overflow-hidden" style="width: 44px; height: 44px;">
                        @if($order->user && $order->user->avatar)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($order->user->avatar) }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            {{ strtoupper(substr($order->user->full_name ?? $order->user->username ?? 'U', 0, 1)) }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h6 class="fw-bold mb-0 text-dark text-truncate">{{ $order->user->full_name ?? '-' }}</h6>
                        <small class="text-muted text-truncate d-block">{{ $order->user->username ? '@'.$order->user->username : '-' }}</small>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">{{ __('Telegram ID:') }}</span>
                        <code>{{ $order->user->telegram_id ?? '-' }}</code>
                    </div>
                    @if($order->user && $order->user->telegram_username)
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">{{ __('Telegram:') }}</span>
                        <a href="https://t.me/{{ $order->user->telegram_username }}" target="_blank" class="text-decoration-none fw-semibold">
                            <i class="fab fa-telegram me-1"></i>Chat
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Transaction Log & Timeline Card --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-history text-secondary me-2"></i>{{ __('Log Transaksi') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <ul class="list-unstyled mb-0 small">
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-clock text-muted mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibuat:') }}</span>
                            <div class="fw-semibold">{{ $order->created_at->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @if($order->paid_at)
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-check-circle text-success mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibayar:') }}</span>
                            <div class="fw-semibold text-success">{{ \App\Support\SystemTimezone::toSystem($order->paid_at)?->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($order->delivered_at)
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-paper-plane text-primary mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dikirim:') }}</span>
                            <div class="fw-semibold text-primary">{{ \App\Support\SystemTimezone::toSystem($order->delivered_at)?->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($order->cancelled_at)
                    <li class="d-flex align-items-start gap-2">
                        <i class="fas fa-times-circle text-danger mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibatalkan:') }}</span>
                            <div class="fw-semibold text-danger">{{ \App\Support\SystemTimezone::toSystem($order->cancelled_at)?->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                </ul>

                @if($order->cancel_reason)
                <div class="alert alert-danger mt-3 small mb-0 py-2">
                    <b>{{ __('Alasan Batal:') }}</b> {{ $order->cancel_reason }}
                </div>
                @endif
            </div>
        </div>

        {{-- Action Buttons (Disabled for Multi-Seller orders to prevent cancelling other sellers' items) --}}
        @if($order->status === 'pending_payment' && empty($isMultiSellerOrder))
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-3">
                <form action="{{ route('seller.orders.cancel', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, '{{ __('Apakah Anda yakin ingin membatalkan pesanan ini?') }}');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100 rounded-pill py-2 fw-semibold">
                        <i class="fas fa-times me-1"></i>{{ __('Batalkan Pesanan Ini') }}
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
