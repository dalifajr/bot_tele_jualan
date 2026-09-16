@extends('layouts.app')

@section('title', __('Manajemen Pesanan Saya'))
@section('page_subtitle', __('Pesanan'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Manajemen Pesanan') }}</h4>
        <p class="text-muted mb-0 small">{{ __('Daftar transaksi pesanan produk toko Anda') }}</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-3"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

{{-- Search Bar --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 14px;">
    <div class="card-body p-2 p-md-3">
        <form action="{{ route('seller.orders.index') }}" method="GET" class="row g-2 align-items-center">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="col">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-0 ps-3"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-0 bg-light py-2" placeholder="{{ __('Cari no. order, produk, atau pelanggan...') }}" value="{{ request('search') }}">
                    @if(request('search'))
                        <a href="{{ route('seller.orders.index', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-light border-0 px-2.5 text-muted d-flex align-items-center" title="{{ __('Reset') }}">
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

{{-- Simplified Filter Chips --}}
<div class="category-scroll-container mb-3">
    <a href="{{ route('seller.orders.index', array_filter(['search' => request('search')])) }}"
       class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold text-nowrap flex-shrink-0 {{ is_null($status) ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ __('Semua') }}
    </a>
    <a href="{{ route('seller.orders.index', array_filter(['status' => 'pending_payment', 'search' => request('search')])) }}"
       class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold text-nowrap flex-shrink-0 {{ $status === 'pending_payment' ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ __('Pending') }}
    </a>
    <a href="{{ route('seller.orders.index', array_filter(['status' => 'delivered', 'search' => request('search')])) }}"
       class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold text-nowrap flex-shrink-0 {{ $status === 'delivered' || $status === 'paid' ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ __('Selesai') }}
    </a>
    <a href="{{ route('seller.orders.index', array_filter(['status' => 'cancelled_expired', 'search' => request('search')])) }}"
       class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold text-nowrap flex-shrink-0 {{ $status === 'cancelled_expired' || in_array($status, ['cancelled', 'expired']) ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ __('Dibatalkan & Kedaluwarsa') }}
    </a>
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($orders->count() > 0)
        <!-- Desktop Table View -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">{{ __('No. Order') }}</th>
                        <th class="py-3 border-0">{{ __('Pelanggan') }}</th>
                        <th class="py-3 border-0">{{ __('Produk') }}</th>
                        <th class="py-3 border-0">{{ __('Total') }}</th>
                        <th class="py-3 border-0">{{ __('Status') }}</th>
                        <th class="py-3 border-0">{{ __('Tanggal') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    @php
                        $orderUrl = route('seller.orders.show', $order->id);
                    @endphp
                    <tr style="cursor: pointer;" onclick="window.location.href='{{ $orderUrl }}'">
                        <td class="px-4 fw-bold text-primary font-monospace">{{ $order->reference }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark">{{ $order->user->full_name ?? $order->user->username ?? 'User' }}</span>
                                <small class="text-muted">{{ $order->user->username ? '@'.$order->user->username : '-' }}</small>
                            </div>
                        </td>
                        <td>
                            <span class="text-dark fw-medium">{{ Str::limit($order->product->name ?? '-', 28) }}</span>
                            <small class="text-muted d-block">{{ $order->quantity }} unit</small>
                        </td>
                        <td class="fw-bold text-dark">{{ $order->formatted_total }}</td>
                        <td>
                            <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-2.5 py-1 small fw-bold">
                                {{ $order->status_label }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $order->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end px-4" onclick="event.stopPropagation();">
                            <div class="d-flex gap-1.5 justify-content-end align-items-center">
                                @if($order->status === 'pending_payment')
                                <form action="{{ route('seller.orders.cancel', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Batalkan pesanan ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle border-danger" title="{{ __('Batalkan Pesanan') }}" style="width: 32px; height: 32px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                @endif
                                <a href="{{ $orderUrl }}" class="btn btn-sm btn-light text-primary rounded-circle border" title="{{ __('Detail Pesanan') }}" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-chevron-right small"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="d-md-none p-2.5 d-flex flex-column gap-2">
            @foreach($orders as $order)
            @php
                $orderUrl = route('seller.orders.show', $order->id);
            @endphp
            <div class="card border border-subtle shadow-sm rounded-3 p-3 shopee-seller-card"
                 role="button"
                 tabindex="0"
                 onclick="window.location.href='{{ $orderUrl }}'"
                 onkeydown="if(event.key==='Enter') window.location.href='{{ $orderUrl }}'"
                 style="cursor: pointer; transition: all 0.15s ease-in-out;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold font-monospace text-primary small">#{{ $order->reference }}</span>
                    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-2.5 py-1 small fw-bold">
                        {{ $order->status_label }}
                    </span>
                </div>
                <div class="mb-2">
                    <div class="fw-semibold text-dark mb-0.5">{{ Str::limit($order->product->name ?? '-', 35) }}</div>
                    <div class="small text-muted">
                        <i class="fas fa-user me-1"></i>{{ $order->user->full_name ?? $order->user->username ?? 'User' }}
                        <span class="mx-1">&bull;</span>
                        <span>{{ $order->quantity }} unit</span>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <div>
                        <div class="fw-bold text-dark">{{ $order->formatted_total }}</div>
                        <div class="text-secondary" style="font-size: 0.72rem;">{{ $order->created_at->format('d M Y H:i') }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-1.5" onclick="event.stopPropagation();">
                        @if($order->status === 'pending_payment')
                        <form action="{{ route('seller.orders.cancel', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Batalkan pesanan ini?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1 small" title="{{ __('Batalkan') }}">
                                <i class="fas fa-times me-1"></i>{{ __('Batal') }}
                            </button>
                        </form>
                        @endif
                        <a href="{{ $orderUrl }}" class="btn btn-sm btn-light text-secondary rounded-circle border d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                            <i class="fas fa-chevron-right small"></i>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="px-3 px-md-4 py-3 border-top">
            {{ $orders->withQueryString()->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-receipt text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-3">
                @if(request('search'))
                    {{ __('Tidak ada pesanan toko dengan pencarian ":search".', ['search' => request('search')]) }}
                @elseif($status)
                    {{ __('Tidak ada pesanan dengan filter status ini.') }}
                @else
                    {{ __('Belum ada pesanan masuk untuk toko Anda.') }}
                @endif
            </p>
            @if(request('search') || request('status'))
                <a href="{{ route('seller.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1.5">
                    {{ __('Hapus Filter') }}
                </a>
            @endif
        </div>
        @endif
    </div>
</div>

<style>
.shopee-seller-card:hover {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}
</style>
@endsection
