@extends('layouts.app')

@section('title', __('Manajemen Pesanan'))
@section('page_subtitle', __('Pesanan'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Manajemen Pesanan') }}</h4>
        <p class="text-muted mb-0 small">{{ __('Daftar seluruh transaksi pesanan platform') }}</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-3"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

{{-- Simplified Filter Chips --}}
<div class="mb-3 category-scroll-container pb-1">
    <div class="d-inline-flex gap-2">
        <a href="{{ route('admin.orders.index', array_filter(['search' => request('search'), 'product_id' => request('product_id')])) }}"
           class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold {{ is_null($status) ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('Semua') }}
        </a>
        <a href="{{ route('admin.orders.index', array_filter(['status' => 'pending_payment', 'search' => request('search'), 'product_id' => request('product_id')])) }}"
           class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold {{ $status === 'pending_payment' ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('Pending') }}
        </a>
        <a href="{{ route('admin.orders.index', array_filter(['status' => 'delivered', 'search' => request('search'), 'product_id' => request('product_id')])) }}"
           class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold {{ $status === 'delivered' || $status === 'paid' ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('Selesai') }}
        </a>
        <a href="{{ route('admin.orders.index', array_filter(['status' => 'cancelled_expired', 'search' => request('search'), 'product_id' => request('product_id')])) }}"
           class="btn btn-sm rounded-pill px-3 py-1.5 fw-semibold {{ $status === 'cancelled_expired' || in_array($status, ['cancelled', 'expired']) ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('Dibatalkan & Kedaluwarsa') }}
        </a>
    </div>
</div>

{{-- Filters Row --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 14px;">
    <div class="card-body p-2 p-md-3">
        <form action="{{ route('admin.orders.index') }}" method="GET" class="row g-2 align-items-center">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-0 ps-3"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-0 bg-light py-2" placeholder="{{ __('Cari No. Order, nama, Telegram ID, nominal...') }}" value="{{ request('search') }}">
                </div>
            </div>

            {{-- Product Filter --}}
            <div class="col-md-3 col-6">
                <select name="product_id" class="form-select form-select-sm border-0 bg-light py-2">
                    <option value="">{{ __('Semua Produk') }}</option>
                    @php
                        $filterProducts = \App\Models\Product::orderBy('name')->get();
                    @endphp
                    @foreach($filterProducts as $fp)
                        <option value="{{ $fp->id }}" {{ request('product_id') == $fp->id ? 'selected' : '' }}>
                            {{ $fp->is_suspended ? '🔴' : '✅' }} {{ $fp->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Buttons --}}
            <div class="col-md-3 col-6 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill flex-fill py-1.5 fw-semibold">{{ __('Cari & Filter') }}</button>
                @if(request('search') || request('product_id'))
                    <a href="{{ route('admin.orders.index', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-sm btn-light px-3 rounded-pill py-1.5">{{ __('Reset') }}</a>
                @endif
            </div>
        </form>
    </div>
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
                        $orderUrl = route('admin.orders.show', $order->id);
                    @endphp
                    <tr style="cursor: pointer;" onclick="window.location.href='{{ $orderUrl }}'">
                        <td class="px-4 fw-bold text-primary font-monospace">{{ $order->reference }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark">{{ $order->user->full_name ?? $order->user->username ?? 'User' }}</span>
                                @if($order->user && $order->user->telegram_id)
                                    <small class="text-muted">TG: {{ $order->user->telegram_id }}</small>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="text-dark fw-medium">{{ Str::limit($order->product->name ?? '-', 26) }}</span>
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
                                <form action="{{ route('admin.orders.accept', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Konfirmasi terima pembayaran untuk pesanan ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light text-success rounded-circle border-success" title="{{ __('Terima Pembayaran') }}" style="width: 32px; height: 32px;">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.orders.reject', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Tolak dan batalkan pesanan ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle border-danger" title="{{ __('Tolak Pesanan') }}" style="width: 32px; height: 32px;">
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
                $orderUrl = route('admin.orders.show', $order->id);
            @endphp
            <div class="card border border-subtle shadow-sm rounded-3 p-3 shopee-admin-card"
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
                        @if($order->user && $order->user->telegram_id)
                            <span class="ms-1">(TG: {{ $order->user->telegram_id }})</span>
                        @endif
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <div>
                        <div class="fw-bold text-dark">{{ $order->formatted_total }}</div>
                        <div class="text-secondary" style="font-size: 0.72rem;">{{ $order->created_at->format('d M Y H:i') }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-1.5" onclick="event.stopPropagation();">
                        @if($order->status === 'pending_payment')
                        <form action="{{ route('admin.orders.accept', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Konfirmasi terima pembayaran?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2.5 py-1 small" title="{{ __('Terima') }}">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <form action="{{ route('admin.orders.reject', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Tolak pesanan?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1 small" title="{{ __('Tolak') }}">
                                <i class="fas fa-times"></i>
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
                    {{ __('Tidak ada pesanan dengan pencarian ":search".', ['search' => request('search')]) }}
                @elseif($status)
                    {{ __('Tidak ada pesanan dengan filter status ini.') }}
                @else
                    {{ __('Tidak ada pesanan di platform.') }}
                @endif
            </p>
            @if(request('search') || request('status') || request('product_id'))
                <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1.5">
                    {{ __('Hapus Filter') }}
                </a>
            @endif
        </div>
        @endif
    </div>
</div>

<style>
.shopee-admin-card:hover {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
}
</style>
@endsection
