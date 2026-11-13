@extends('layouts.app')

@section('title', __('Riwayat Pesanan'))
@section('page_subtitle', __('Daftar transaksi dan status pembelian Anda'))

@section('action_bar_buttons')
    <a href="{{ route('dashboard') }}" class="cm-btn-outline">
        <i class="fas fa-arrow-left"></i>
        <span>{{ __('Home') }}</span>
    </a>
@endsection

@section('content')
<div class="orders-page-container mx-auto" style="max-width: 1040px;">
{{-- Header Riwayat Pesanan (Flat Material 3 Style) --}}
<div class="mb-3">
    <h1 class="m3-page-title mb-1">
        {{ __('Riwayat pesanan') }}
    </h1>
    <p class="text-secondary small mb-0" style="font-size: 0.92rem;">
        {{ __('Daftar transaksi dan status pembelian produk digital Anda.') }}
    </p>
</div>

{{-- Orders Filter Bar (Material 3 Expressive Tonal Card) --}}
<div class="card border p-2.5 p-md-3 mb-3.5 shadow-sm" style="border-radius: var(--radius-lg, 18px); background: var(--surface-container-lowest); border-color: var(--outline-variant) !important;">
    <div class="d-flex align-items-center justify-content-between gap-2.5">
        {{-- Horizontal Scrollable Filter Chips --}}
        <div class="m3-filter-chip-row flex-grow-1">
            <a href="{{ route('orders.index', array_filter(['search' => request('search')])) }}"
               class="btn btn-sm rounded-pill px-3.5 py-1.5 fw-semibold text-nowrap flex-shrink-0 {{ is_null($status) ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('Semua') }}
            </a>
            <a href="{{ route('orders.index', array_filter(['status' => 'pending_payment', 'search' => request('search')])) }}"
               class="btn btn-sm rounded-pill px-3.5 py-1.5 fw-semibold text-nowrap flex-shrink-0 {{ $status === 'pending_payment' ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('Menunggu') }}
            </a>
            <a href="{{ route('orders.index', array_filter(['status' => 'delivered', 'search' => request('search')])) }}"
               class="btn btn-sm rounded-pill px-3.5 py-1.5 fw-semibold text-nowrap flex-shrink-0 {{ $status === 'delivered' ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('Selesai') }}
            </a>
            <a href="{{ route('orders.index', array_filter(['status' => 'cancelled_expired', 'search' => request('search')])) }}"
               class="btn btn-sm rounded-pill px-3.5 py-1.5 fw-semibold text-nowrap flex-shrink-0 {{ $status === 'cancelled_expired' || in_array($status, ['cancelled', 'expired']) ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('Dibatalkan & Kedaluwarsa') }}
            </a>
        </div>

        {{-- Desktop Search Form (d-none d-md-flex) --}}
        <form action="{{ route('orders.index') }}" method="GET" class="d-none d-md-flex align-items-center gap-2 m-0 flex-shrink-0" style="max-width: 320px; width: 100%;">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="input-group input-group-sm flex-grow-1">
                <span class="input-group-text ps-3 bg-transparent border-end-0" style="border-color: var(--outline-variant);"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('Cari pesanan...') }}" value="{{ request('search') }}" style="border-color: var(--outline-variant);">
                @if(request('search'))
                    <a href="{{ route('orders.index', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-light px-2 text-muted d-flex align-items-center border-start-0" style="border-color: var(--outline-variant);" title="{{ __('Hapus Pencarian') }}">
                        <i class="fas fa-times-circle"></i>
                    </a>
                @endif
            </div>
            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3.5 flex-shrink-0 fw-semibold">
                {{ __('Cari') }}
            </button>
        </form>

        {{-- Mobile Search Toggle Button (d-md-none) --}}
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill d-md-none flex-shrink-0 d-inline-flex align-items-center gap-1.5 px-3 py-1.5 fw-semibold {{ request('search') ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#ordersMobileSearchCollapse" aria-expanded="{{ request('search') ? 'true' : 'false' }}">
            <i class="fas fa-search"></i>
            <span style="font-size: 0.8125rem;">{{ __('Cari') }}</span>
        </button>
    </div>

    {{-- Mobile Collapsible Search Container (d-md-none) --}}
    <div class="collapse {{ request('search') ? 'show' : '' }} d-md-none pt-2.5" id="ordersMobileSearchCollapse">
        <form action="{{ route('orders.index') }}" method="GET" class="d-flex align-items-center gap-2 m-0 w-100">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="input-group input-group-sm flex-grow-1">
                <span class="input-group-text ps-3 bg-transparent border-end-0" style="border-color: var(--outline-variant);"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('Cari nama produk, no. pesanan...') }}" value="{{ request('search') }}" style="border-color: var(--outline-variant);">
                @if(request('search'))
                    <a href="{{ route('orders.index', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-light px-2 text-muted d-flex align-items-center" title="{{ __('Hapus Pencarian') }}">
                        <i class="fas fa-times-circle"></i>
                    </a>
                @endif
            </div>
            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 flex-shrink-0 fw-semibold">
                {{ __('Cari') }}
            </button>
        </form>
    </div>
</div>

{{-- Skeleton Shimmer Placeholder for Transitions --}}
<div class="d-none" id="ordersSkeletonContainer">
    <div class="d-flex flex-column gap-3 mb-3">
        @for($sk = 0; $sk < 3; $sk++)
        <div class="card p-3 rounded-4 border">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="skeleton-shimmer" style="height: 16px; width: 140px;"></div>
                <div class="skeleton-shimmer rounded-pill" style="height: 24px; width: 80px;"></div>
            </div>
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="skeleton-shimmer rounded-3" style="width: 56px; height: 56px; flex-shrink: 0;"></div>
                <div class="flex-grow-1">
                    <div class="skeleton-shimmer mb-2" style="height: 16px; width: 60%;"></div>
                    <div class="skeleton-shimmer" style="height: 12px; width: 35%;"></div>
                </div>
            </div>
        </div>
        @endfor
    </div>
</div>

<div id="ordersContentWrapper">
@if($orders->count() > 0)
    {{-- Flat Material 3 Expressive Order Cards Stack --}}
    <div class="d-flex flex-column gap-3">
        @foreach($orders as $order)
        @php
            $product = $order->product;
            $seller = $product?->creator;
            $sellerId = $seller ? $seller->id : null;
            $sellerName = $seller ? ($seller->full_name ?? $seller->username ?? 'Official Store') : 'Official Store';
            $orderUrl = route('orders.show', $order->id);
        @endphp

        {{-- M3 Expressive Order Card --}}
        <div class="card m3-order-card shadow-sm order-transaction-card"
             role="button"
             tabindex="0"
             onclick="window.location.href='{{ $orderUrl }}'"
             onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); window.location.href='{{ $orderUrl }}'; }"
             style="cursor: pointer;">

            {{-- Card Header: Store Identity, Reference & Tonal Status --}}
            <div class="card-header border-bottom px-3.5 py-2.5 d-flex align-items-center justify-content-between gap-2" style="background: var(--surface-container-low);">
                <div class="d-flex align-items-center gap-2 text-truncate">
                    <div class="m3-order-store-badge">
                        <i class="fas fa-store"></i>
                    </div>
                    @if($sellerId)
                        <a href="{{ route('sellers.show', $sellerId) }}" 
                           class="fw-bold text-body text-decoration-none hover-primary small text-truncate"
                           onclick="event.stopPropagation();"
                           title="{{ __('Kunjungi Toko') }}">
                            {{ $sellerName }}
                        </a>
                    @else
                        <span class="fw-bold text-body small text-truncate">{{ $sellerName }}</span>
                    @endif
                    <span class="text-secondary opacity-50 d-none d-sm-inline">&bull;</span>
                    <span class="badge font-monospace small px-2 py-1 d-none d-sm-inline" style="background: var(--surface-container); color: var(--on-surface-variant); font-size: 0.74rem; border-radius: 6px;">#{{ $order->reference }}</span>
                    <span class="text-secondary opacity-50 d-none d-md-inline">&bull;</span>
                    <span class="text-secondary small d-none d-md-inline" style="font-size: 0.78rem;">{{ $order->created_at->format('d M Y, H:i') }}</span>
                </div>

                {{-- Status Badge M3 Tonal --}}
                <div class="flex-shrink-0">
                    @if($order->status === 'delivered')
                        <span class="badge rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1.5" style="background: var(--tertiary-container); color: var(--on-tertiary-container); font-size: 0.74rem;">
                            <i class="fas fa-check-circle"></i>
                            <span>{{ $order->status_label }}</span>
                        </span>
                    @elseif($order->status === 'pending_payment')
                        <span class="badge rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1.5" style="background: hsl(38, 92%, 88%); color: hsl(35, 95%, 22%); font-size: 0.74rem;">
                            <i class="fas fa-clock"></i>
                            <span>{{ $order->status_label }}</span>
                        </span>
                    @elseif($order->status === 'cancelled')
                        <span class="badge rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1.5" style="background: hsl(350, 80%, 90%); color: hsl(350, 85%, 26%); font-size: 0.74rem;">
                            <i class="fas fa-times-circle"></i>
                            <span>{{ $order->status_label }}</span>
                        </span>
                    @elseif($order->status === 'expired')
                        <span class="badge rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1.5" style="background: var(--surface-container-high); color: var(--on-surface-variant); font-size: 0.74rem;">
                            <i class="fas fa-hourglass-end"></i>
                            <span>{{ $order->status_label }}</span>
                        </span>
                    @else
                        <span class="badge rounded-pill px-3 py-1.5 fw-bold bg-secondary-subtle text-secondary" style="font-size: 0.74rem;">
                            {{ $order->status_label }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- Card Body: Product Item & Total Block Grid --}}
            <div class="card-body p-3 p-md-3.5">
                <div class="row align-items-center g-3">
                    {{-- Product Column --}}
                    <div class="col-12 col-md-8">
                        <div class="d-flex align-items-center gap-3">
                            <div class="m3-order-thumb flex-shrink-0">
                                @if($product && $product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="img-fluid" style="max-height: 48px; object-fit: contain;">
                                @else
                                    <i class="fas fa-box-open fa-lg opacity-80"></i>
                                @endif
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="fw-semibold text-body mb-1 text-truncate" style="font-size: 0.96rem;" title="{{ $product->name ?? '-' }}">
                                    {{ $product->name ?? __('Produk Tidak Tersedia') }}
                                </h6>
                                <div class="text-secondary small d-flex flex-wrap align-items-center gap-2 mb-1" style="font-size: 0.8rem;">
                                    <span>{{ $order->quantity }} barang &times; Rp {{ number_format($order->subtotal / max(1, $order->quantity), 0, ',', '.') }}</span>
                                    @if($product && $product->warranty_days)
                                        <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                            <i class="fas fa-shield-alt me-0.5"></i>{{ $product->warranty_days }} Hari Garansi
                                        </span>
                                    @endif
                                    @if($order->coupon_code)
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                            <i class="fas fa-tag me-0.5"></i>{{ $order->coupon_code }}
                                        </span>
                                    @endif
                                    @if($order->items && $order->items->count() > 1)
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                            +{{ $order->items->count() - 1 }} produk lainnya
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Desktop Total Price Block --}}
                    <div class="col-12 col-md-4 text-md-end d-none d-md-block ps-md-4" style="border-left: 1px solid var(--outline-variant);">
                        <span class="text-secondary small d-block mb-0.5" style="font-size: 0.78rem;">{{ __('Total Belanja') }}</span>
                        <span class="fs-5 fw-bold text-primary d-block">{{ $order->formatted_total }}</span>
                        <small class="text-secondary" style="font-size: 0.72rem;">({{ $order->quantity }} {{ __('Produk') }})</small>
                    </div>

                    {{-- Mobile Total Row --}}
                    <div class="col-12 d-md-none pt-2 mt-1 border-top d-flex justify-content-between align-items-center">
                        <span class="text-secondary small" style="font-size: 0.78rem;">{{ $order->quantity }} {{ __('Produk') }}</span>
                        <div class="d-flex align-items-baseline gap-1.5">
                            <span class="text-secondary small" style="font-size: 0.78rem;">{{ __('Total:') }}</span>
                            <span class="fw-bold text-primary fs-6">{{ $order->formatted_total }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card Footer: Status Note & Action Buttons --}}
            <div class="card-footer px-3.5 py-2.5 d-flex flex-wrap align-items-center justify-content-between gap-2.5" style="background: var(--surface-container-low);">
                <div class="text-secondary small d-flex align-items-center gap-1.5" style="font-size: 0.78rem;">
                    @if($order->status === 'delivered')
                        <span class="text-success fw-medium"><i class="fas fa-check-circle me-1"></i>{{ __('Pesanan selesai. Akun digital aktif & siap digunakan.') }}</span>
                    @elseif($order->status === 'pending_payment')
                        <span class="text-warning-emphasis fw-medium"><i class="fas fa-clock me-1"></i>{{ __('Menunggu pembayaran sebelum batas waktu berakhir.') }}</span>
                    @elseif($order->status === 'cancelled')
                        <span class="text-danger fw-medium"><i class="fas fa-times-circle me-1"></i>{{ __('Pesanan dibatalkan.') }}</span>
                    @elseif($order->status === 'expired')
                        <span class="text-secondary fw-medium"><i class="fas fa-hourglass-end me-1"></i>{{ __('Batas waktu pembayaran habis.') }}</span>
                    @else
                        <span>{{ $order->status_label }}</span>
                    @endif
                </div>

                {{-- Action Buttons --}}
                <div class="d-flex align-items-center gap-2 ms-auto" onclick="event.stopPropagation();">
                    @if($order->status === 'pending_payment')
                        <form action="{{ route('orders.cancel', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1.5 fw-semibold" style="font-size: 0.8rem;">
                                <i class="fas fa-times me-1"></i>{{ __('Batal') }}
                            </button>
                        </form>
                        <a href="{{ route('checkout.success', ['order_ref' => $order->order_ref]) }}" class="btn btn-sm btn-primary rounded-pill px-3.5 py-1.5 fw-semibold shadow-sm" style="font-size: 0.8rem;">
                            <i class="fas fa-qrcode me-1"></i>{{ __('Bayar Sekarang') }}
                        </a>
                    @elseif($order->status === 'delivered' || $order->status === 'paid')
                        @if($product)
                        <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3.5 py-1.5 fw-semibold" style="font-size: 0.8rem;">
                            <i class="fas fa-redo me-1"></i>{{ __('Beli Lagi') }}
                        </a>
                        @endif
                        <a href="{{ $orderUrl }}" class="btn btn-sm btn-primary rounded-pill px-3.5 py-1.5 fw-semibold text-white d-inline-flex align-items-center gap-1.5 shadow-sm" style="font-size: 0.8rem;">
                            <span>{{ __('Rincian') }}</span>
                            <i class="fas fa-arrow-right small"></i>
                        </a>
                    @elseif($order->status === 'cancelled' || $order->status === 'expired')
                        @if($product)
                        <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3.5 py-1.5 fw-semibold" style="font-size: 0.8rem;">
                            <i class="fas fa-redo me-1"></i>{{ __('Beli Lagi') }}
                        </a>
                        @endif
                        <a href="{{ $orderUrl }}" class="btn btn-sm btn-light border rounded-pill px-3.5 py-1.5 fw-semibold text-secondary d-inline-flex align-items-center gap-1.5" style="font-size: 0.8rem; background: var(--surface-container-lowest); border-color: var(--outline-variant) !important;">
                            <span>{{ __('Rincian') }}</span>
                            <i class="fas fa-chevron-right small"></i>
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Pagination Card --}}
    <div class="card p-3 border mt-3.5 shadow-sm" style="border-radius: var(--radius-lg, 18px); background: var(--surface-container-lowest); border-color: var(--outline-variant) !important;">
        <div class="d-flex justify-content-center">
            {{ $orders->withQueryString()->links() }}
        </div>
    </div>
@else
    {{-- Empty State M3 Card --}}
    <div class="card p-5 border text-center shadow-sm" style="border-radius: var(--radius-lg, 18px); background: var(--surface-container-lowest); border-color: var(--outline-variant) !important;">
        <div class="mb-3">
            <div class="m3-order-thumb mx-auto" style="width: 72px; height: 72px; border-radius: 20px; font-size: 2rem;">
                <i class="fas fa-receipt opacity-75"></i>
            </div>
        </div>
        <h5 class="fw-bold text-body mb-2">{{ __('Belum Ada Riwayat Pesanan') }}</h5>
        <p class="text-secondary small mb-4" style="max-width: 420px; margin: 0 auto;">
            @if(request('search'))
                {{ __('Tidak ada pesanan yang sesuai dengan pencarian ":search". Silakan periksa kembali kata kunci Anda.', ['search' => request('search')]) }}
            @elseif($status)
                {{ __('Tidak ada pesanan pada kategori filter status ini.') }}
            @else
                {{ __('Semua pembelian produk digital yang Anda lakukan akan tersimpan rapi dan dapat diakses di sini.') }}
            @endif
        </p>
        <div class="d-flex align-items-center justify-content-center gap-2">
            @if(request('search') || request('status'))
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 py-2 fw-semibold">
                    {{ __('Hapus Filter') }}
                </a>
            @endif
            <a href="{{ route('catalog.index') }}" class="btn btn-sm btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm">
                <i class="fas fa-store me-1.5"></i>{{ __('Mulai Belanja') }}
            </a>
        </div>
    </div>
@endif
</div>{{-- /#ordersContentWrapper --}}
</div>{{-- /.orders-page-container --}}

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterLinks = document.querySelectorAll('.m3-filter-chip-row a');
        const skeleton = document.getElementById('ordersSkeletonContainer');
        const content = document.getElementById('ordersContentWrapper');

        filterLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (e.ctrlKey || e.metaKey || e.shiftKey) return;
                if (skeleton && content) {
                    skeleton.classList.remove('d-none');
                    content.classList.add('d-none');
                }
            });
        });
    });
</script>
@endpush
@endsection
