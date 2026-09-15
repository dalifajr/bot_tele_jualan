@extends('layouts.app')

@section('title', __('Detail Pesanan ') . $order->reference)
@section('page_subtitle', __('Pesanan'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('seller.orders.index') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Daftar Pesanan') }}">
            <i class="fas fa-arrow-left text-body"></i>
        </a>
        <div>
            <h5 class="fw-bold m-0 text-body" style="font-size: 1.15rem;">{{ __('Detail Pesanan Toko') }}</h5>
            <small class="text-muted" style="font-size: 0.75rem;">{{ $order->reference }} &bull; {{ $order->created_at->format('d M Y, H:i') }}</small>
        </div>
    </div>
    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-1.5 fw-bold fs-6">
        {{ $order->status_label }}
    </span>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

<div class="row g-4">
    {{-- Main Left Column --}}
    <div class="col-lg-8">
        {{-- Product Details Card --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-box text-primary me-2"></i>{{ __('Item yang Dipesan') }}
                </h6>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-3 mt-2">
                    <div class="rounded-3 bg-white border p-2 d-flex align-items-center justify-content-center text-primary flex-shrink-0" style="width: 60px; height: 60px;">
                        @if($order->product && $order->product->image_url)
                            <img src="{{ $order->product->image_url }}" alt="{{ $order->product->name }}" class="img-fluid rounded-2" style="max-height: 48px; object-fit: contain;">
                        @else
                            <i class="fas fa-cube fa-2x opacity-75"></i>
                        @endif
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <h6 class="fw-bold text-dark mb-1 text-truncate">{{ $order->product->name ?? '-' }}</h6>
                        <div class="text-muted small">
                            {{ $order->quantity }} x Rp {{ number_format($order->subtotal / max(1, $order->quantity), 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-dark">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</div>
                    </div>
                </div>

                <div class="border-top mt-3 pt-3">
                    <div class="d-flex justify-content-between mb-1 small text-muted">
                        <span>{{ __('Subtotal Produk') }}</span>
                        <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($order->unique_code)
                    <div class="d-flex justify-content-between mb-1 small text-muted">
                        <span>{{ __('Kode Unik') }}</span>
                        <span>Rp {{ $order->unique_code }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between pt-2 border-top fw-bold fs-6">
                        <span>{{ __('Total Pendapatan Pesanan') }}</span>
                        <span class="text-primary">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sent Stock / Credentials Card --}}
        @if($order->stockUnits && $order->stockUnits->count() > 0)
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-key text-success me-2"></i>{{ __('Data Akun / Lisensi Terkirim') }} ({{ $order->stockUnits->count() }} unit)
                </h6>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="d-flex flex-column gap-3 mt-2">
                    @foreach($order->stockUnits as $unit)
                        <div>
                            @if($order->stockUnits->count() > 1)
                                <div class="badge bg-secondary-subtle text-secondary mb-1">Unit #{{ $loop->iteration }}</div>
                            @endif
                            <x-account-credential-viewer :rawText="$unit->raw_text" />
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Complaint Case Card if any --}}
        @if($order->complaintCase)
        <div class="card border-0 shadow-sm mb-4 border-start border-warning border-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-warning-emphasis mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>{{ __('Pusat Resolusi / Komplain Aktif') }}
                    </h6>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-1">
                        {{ ucfirst($order->complaintCase->status) }}
                    </span>
                </div>
                <p class="text-muted small mb-3">{{ $order->complaintCase->reason }}</p>
                <a href="{{ route('seller.complaints.show', $order->complaintCase->id) }}" class="btn btn-sm btn-outline-warning rounded-pill px-3">
                    <i class="fas fa-comments me-1"></i>{{ __('Lihat Ruang Mediasi') }}
                </a>
            </div>
        </div>
        @endif
    </div>

    {{-- Right Side Column --}}
    <div class="col-lg-4">
        {{-- Customer Info Card --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-user text-primary me-2"></i>{{ __('Informasi Pembeli') }}
                </h6>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="d-flex align-items-center gap-3 mb-3 mt-2">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 46px; height: 46px;">
                        {{ strtoupper(substr($order->user->full_name ?? $order->user->username ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">{{ $order->user->full_name ?? '-' }}</h6>
                        <small class="text-muted">{{ $order->user->username ? '@'.$order->user->username : '-' }}</small>
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
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-history text-secondary me-2"></i>{{ __('Log Transaksi') }}
                </h6>
            </div>
            <div class="card-body px-4 pb-4">
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
                            <div class="fw-semibold text-success">{{ \Carbon\Carbon::parse($order->paid_at)->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($order->delivered_at)
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-paper-plane text-primary mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dikirim:') }}</span>
                            <div class="fw-semibold text-primary">{{ \Carbon\Carbon::parse($order->delivered_at)->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($order->cancelled_at)
                    <li class="d-flex align-items-start gap-2">
                        <i class="fas fa-times-circle text-danger mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibatalkan:') }}</span>
                            <div class="fw-semibold text-danger">{{ \Carbon\Carbon::parse($order->cancelled_at)->format('d M Y H:i:s') }}</div>
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

        {{-- Action Buttons --}}
        @if($order->status === 'pending_payment')
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-3">
                <form action="{{ route('seller.orders.cancel', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini?');">
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
