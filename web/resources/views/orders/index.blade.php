@extends('layouts.app')

@section('title', __('Riwayat Pesanan'))
@section('page_subtitle', __('Pesanan'))

@section('content')
{{-- Header --}}
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('dashboard') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Home') }}">
            <i class="fas fa-arrow-left text-body"></i>
        </a>
        <div>
            <h5 class="fw-bold m-0 text-body" style="font-size: 1.15rem;">{{ __('Riwayat Pesanan') }}</h5>
            <small class="text-muted" style="font-size: 0.75rem;">{{ __('Daftar transaksi dan status pembelian Anda') }}</small>
        </div>
    </div>
    <a href="{{ route('catalog.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold d-flex align-items-center gap-1.5" style="font-size: 0.8rem;">
        <i class="fas fa-shopping-bag"></i>
        <span>{{ __('Belanja') }}</span>
    </a>
</div>

{{-- Search & Filter Controls --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 14px;">
    <div class="card-body p-2 p-md-3">
        <form action="{{ route('orders.index') }}" method="GET" class="row g-2 align-items-center">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="col">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-0 ps-3"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-0 bg-light py-2" placeholder="{{ __('Cari nama produk, no. pesanan, atau nominal...') }}" value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ route('orders.index', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-light border-0 px-2.5 text-muted d-flex align-items-center" title="{{ __('Hapus Pencarian') }}">
                            <i class="fas fa-times-circle"></i>
                        </a>
                    @endif
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-semibold">
                    {{ __('Cari') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Simplified Filter Tabs (Shopee Style) --}}
<div class="category-scroll-container pb-1 mb-3">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('orders.index', array_filter(['search' => request('search')])) }}"
           class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold {{ is_null($status) ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('Semua') }}
        </a>
        <a href="{{ route('orders.index', array_filter(['status' => 'pending_payment', 'search' => request('search')])) }}"
           class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold {{ $status === 'pending_payment' ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('Menunggu') }}
        </a>
        <a href="{{ route('orders.index', array_filter(['status' => 'delivered', 'search' => request('search')])) }}"
           class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold {{ $status === 'delivered' ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('Selesai') }}
        </a>
        <a href="{{ route('orders.index', array_filter(['status' => 'cancelled_expired', 'search' => request('search')])) }}"
           class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold {{ $status === 'cancelled_expired' || in_array($status, ['cancelled', 'expired']) ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('Dibatalkan & Kedaluwarsa') }}
        </a>
    </div>
</div>

{{-- Orders List Container --}}
<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0" id="ordersMainCardBody">
        {{-- Skeleton Shimmer Placeholder for Order Transitions --}}
        <div class="d-none" id="ordersSkeletonContainer">
            <div class="p-3">
                @for($sk = 0; $sk < 3; $sk++)
                <div class="card border-0 shadow-sm p-3 mb-2.5" style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="skeleton-shimmer" style="height: 14px; width: 100px;"></div>
                        <div class="skeleton-shimmer rounded-pill" style="height: 20px; width: 65px;"></div>
                    </div>
                    <div class="d-flex align-items-center gap-2.5 mb-2">
                        <div class="skeleton-shimmer rounded-3" style="width: 48px; height: 48px; flex-shrink: 0;"></div>
                        <div class="flex-grow-1">
                            <div class="skeleton-shimmer mb-1.5" style="height: 14px; width: 70%;"></div>
                            <div class="skeleton-shimmer" style="height: 11px; width: 40%;"></div>
                        </div>
                    </div>
                </div>
                @endfor
            </div>
        </div>

        <div id="ordersContentWrapper">
        @if($orders->count() > 0)
        {{-- Shopee-style Compact Card List --}}
        <div class="d-flex flex-column gap-2.5 p-2.5 p-md-3">
            @foreach($orders as $order)
            @php
                $product = $order->product;
                $seller = $product?->creator;
                $sellerId = $seller ? $seller->id : null;
                $sellerName = $seller ? ($seller->full_name ?? $seller->username ?? 'Official Store') : 'Official Store';
                $orderUrl = route('orders.show', $order->id);
            @endphp

            {{-- Compact Clickable Card --}}
            <div class="card border border-subtle shadow-sm rounded-3 overflow-hidden shopee-order-card order-transaction-card"
                 role="button"
                 tabindex="0"
                 onclick="window.location.href='{{ $orderUrl }}'"
                 onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); window.location.href='{{ $orderUrl }}'; }"
                 style="cursor: pointer; transition: all 0.15s ease-in-out;">

                {{-- Header Bar: Store Info & Status --}}
                <div class="card-header bg-light bg-opacity-75 border-bottom px-3 py-2 d-flex align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 text-truncate">
                        <i class="fas fa-store text-primary small"></i>
                        @if($sellerId)
                            <a href="{{ route('sellers.show', $sellerId) }}" 
                               class="fw-bold text-dark text-decoration-none hover-primary small text-truncate"
                               onclick="event.stopPropagation();"
                               title="{{ __('Kunjungi Toko') }}">
                                {{ $sellerName }}
                            </a>
                        @else
                            <span class="fw-bold text-dark small text-truncate">{{ $sellerName }}</span>
                        @endif
                        <span class="text-muted d-none d-sm-inline" style="font-size: 0.72rem;">|</span>
                        <span class="text-muted font-monospace small d-none d-sm-inline">#{{ $order->reference }}</span>
                    </div>

                    <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                        <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">
                            @if($order->status === 'delivered')
                                <i class="fas fa-check-circle me-1"></i>
                            @elseif($order->status === 'pending_payment')
                                <i class="fas fa-clock me-1"></i>
                            @elseif($order->status === 'cancelled' || $order->status === 'expired')
                                <i class="fas fa-times-circle me-1"></i>
                            @endif
                            {{ $order->status_label }}
                        </span>
                    </div>
                </div>

                {{-- Body: Compact Product Item --}}
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 bg-light border p-1.5 d-flex align-items-center justify-content-center text-primary flex-shrink-0" style="width: 52px; height: 52px;">
                            @if($product && $product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="img-fluid rounded-2" style="max-height: 42px; object-fit: contain;">
                            @else
                                <i class="fas fa-box-open fa-lg opacity-75"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="fw-semibold text-dark mb-1 text-truncate" style="font-size: 0.92rem;">
                                {{ $product->name ?? __('Produk Tidak Tersedia') }}
                            </h6>
                            <div class="text-muted small d-flex flex-wrap align-items-center gap-2">
                                <span>{{ $order->quantity }} &times; Rp {{ number_format($order->subtotal / max(1, $order->quantity), 0, ',', '.') }}</span>
                                @if($product && $product->warranty_days)
                                    <span class="badge bg-info-subtle text-info rounded-pill px-1.5 py-0.5" style="font-size: 0.65rem;">
                                        <i class="fas fa-shield-alt me-0.5"></i>{{ $product->warranty_days }} Hari
                                    </span>
                                @endif
                                @if($order->coupon_code)
                                    <span class="badge bg-success-subtle text-success rounded-pill px-1.5 py-0.5" style="font-size: 0.65rem;">
                                        <i class="fas fa-tag me-0.5"></i>{{ $order->coupon_code }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0 ps-2">
                            <div class="text-muted small d-none d-sm-block" style="font-size: 0.75rem;">{{ __('Total Pesanan') }}</div>
                            <div class="fw-bold text-primary" style="font-size: 1rem;">{{ $order->formatted_total }}</div>
                        </div>
                    </div>
                </div>

                {{-- Footer Bar: Contextual Actions --}}
                <div class="card-footer bg-white border-top px-3 py-2 d-flex align-items-center justify-content-between gap-2">
                    <div class="text-muted" style="font-size: 0.75rem;">
                        <span class="d-none d-sm-inline">{{ $order->created_at->format('d M Y, H:i') }} &bull; </span>
                        @if($order->status === 'pending_payment')
                            <span class="text-warning-emphasis"><i class="fas fa-exclamation-circle me-1"></i>{{ __('Menunggu pembayaran') }}</span>
                        @elseif($order->status === 'delivered')
                            <span class="text-success"><i class="fas fa-check-circle me-1"></i>{{ __('Pesanan selesai') }}</span>
                        @elseif($order->status === 'cancelled')
                            <span class="text-danger"><i class="fas fa-times-circle me-1"></i>{{ __('Pesanan dibatalkan') }}</span>
                        @elseif($order->status === 'expired')
                            <span class="text-secondary"><i class="fas fa-hourglass-end me-1"></i>{{ __('Pesanan kedaluwarsa') }}</span>
                        @else
                            <span>{{ $order->status_label }}</span>
                        @endif
                    </div>

                    <div class="d-flex align-items-center gap-2 ms-auto" onclick="event.stopPropagation();">
                        @if($order->status === 'pending_payment')
                            <form action="{{ route('orders.cancel', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1" style="font-size: 0.78rem;">
                                    <i class="fas fa-times me-1"></i>{{ __('Batal') }}
                                </button>
                            </form>
                            <a href="{{ route('checkout.success', ['order_ref' => $order->order_ref]) }}" class="btn btn-sm btn-success rounded-pill px-3 py-1 fw-bold shadow-sm" style="font-size: 0.78rem;">
                                <i class="fas fa-wallet me-1"></i>{{ __('Bayar Sekarang') }}
                            </a>
                        @elseif($order->status === 'delivered' || $order->status === 'paid')
                            @if($product)
                            <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.78rem;">
                                <i class="fas fa-redo me-1"></i>{{ __('Beli Lagi') }}
                            </a>
                            @endif
                        @elseif($order->status === 'cancelled' || $order->status === 'expired')
                            @if($product)
                            <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.78rem;">
                                <i class="fas fa-redo me-1"></i>{{ __('Beli Lagi') }}
                            </a>
                            @endif
                        @endif

                        <a href="{{ $orderUrl }}" class="btn btn-sm btn-light text-secondary rounded-circle border d-inline-flex align-items-center justify-content-center" style="width: 30px; height: 30px;" title="{{ __('Lihat Detail') }}">
                            <i class="fas fa-chevron-right small"></i>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="px-3 px-md-4 py-3 border-top">
            {{ $orders->withQueryString()->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-receipt text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-3">
                @if(request('search'))
                    {{ __('Tidak ada pesanan yang sesuai dengan pencarian ":search".', ['search' => request('search')]) }}
                @elseif($status)
                    {{ __('Tidak ada pesanan dengan filter status ini.') }}
                @else
                    {{ __('Belum ada riwayat pesanan.') }}
                @endif
            </p>
            @if(request('search') || request('status'))
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1.5 me-2">
                    {{ __('Hapus Filter') }}
                </a>
            @endif
            <a href="{{ route('catalog.index') }}" class="btn btn-sm btn-primary rounded-pill px-4 py-1.5">
                <i class="fas fa-store me-1"></i>{{ __('Mulai Belanja') }}
            </a>
        </div>
        @endif
        </div>{{-- /#ordersContentWrapper --}}
    </div>
</div>

<style>
.shopee-order-card:hover {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}
</style>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterLinks = document.querySelectorAll('.category-scroll-container a');
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
