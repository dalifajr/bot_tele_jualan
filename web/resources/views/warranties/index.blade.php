@extends('layouts.app')

@section('title', __('Pusat Garansi & Lisensi'))
@section('page_subtitle', __('Pantau sisa masa aktif garansi produk digital Anda'))

@section('content')
<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3">
                <small class="text-secondary fw-semibold">{{ __('Garansi Aktif') }}</small>
                <div class="fs-3 fw-bold text-primary mt-1">{{ $activeCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3">
                <small class="text-secondary fw-semibold">{{ __('Segera Berakhir') }}</small>
                <div class="fs-3 fw-bold text-warning mt-1">{{ $expiringSoonCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3">
                <small class="text-secondary fw-semibold">{{ __('Kedaluwarsa') }}</small>
                <div class="fs-3 fw-bold text-secondary mt-1">{{ $expiredCount }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    @forelse($entries as $entry)
        @php
            $order = $entry['order'];
            $product = $entry['product'];
            $status = $entry['status'];
            $badgeClass = $status === 'active'
                ? 'bg-primary-subtle text-primary'
                : ($status === 'expiring_soon' ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary');
            $statusLabel = $status === 'active'
                ? __('Aktif · Sisa :days hari', ['days' => $entry['days_remaining']])
                : ($status === 'expiring_soon'
                    ? __('Segera Berakhir (:days hari)', ['days' => $entry['days_remaining']])
                    : __('Masa Garansi Berakhir'));
        @endphp
        <div class="col-md-6">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-4 d-flex flex-column justify-content-between gap-3">
                    <div>
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h6 class="fw-bold text-body mb-0.5">{{ $product->name }}</h6>
                                <small class="font-monospace text-muted">#{{ $order->order_ref }}</small>
                            </div>
                            <span class="badge {{ $badgeClass }} rounded-pill px-2.5 py-1 fw-bold text-nowrap">{{ $statusLabel }}</span>
                        </div>

                        <div class="row g-2 small text-secondary mt-2">
                            <div class="col-6">
                                <span class="d-block text-muted" style="font-size: 0.72rem;">{{ __('Tanggal Aktif') }}</span>
                                <strong class="text-body">{{ optional($entry['started_at'])->format('d M Y') ?: '-' }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="d-block text-muted" style="font-size: 0.72rem;">{{ __('Berakhir Pada') }}</span>
                                <strong class="text-body">{{ optional($entry['expires_at'])->format('d M Y') ?: '-' }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                        <a href="{{ route('orders.show', $order->id) }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                            <i class="fas fa-key me-1"></i>{{ __('Lihat Kredensial & 2FA') }}
                        </a>
                        @if($status !== 'expired')
                            <a href="{{ route('orders.show', $order->id) }}#complaintSection" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                <i class="fas fa-shield-halved me-1"></i>{{ __('Klaim Garansi') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-body py-5 text-center">
                    <i class="fas fa-shield-halved fa-3x text-muted opacity-50 mb-3"></i>
                    <h6 class="fw-bold text-body">{{ __('Belum Ada Produk Bergaransi Aktif') }}</h6>
                    <p class="small text-secondary mb-3">{{ __('Produk digital dengan masa garansi atau akun VPN aktif Anda akan terpantau otomatis di sini.') }}</p>
                    <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Belanja Sekarang') }}</a>
                </div>
            </div>
        </div>
    @endforelse
</div>
@endsection
