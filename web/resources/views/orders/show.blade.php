@extends('layouts.app')

@section('title', 'Detail Pesanan ' . $order->reference)
@section('page_subtitle', 'Detail Pesanan')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-receipt text-primary me-2"></i>
                        Pesanan {{ $order->reference }}
                    </h5>
                    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-2">
                        {{ $order->status_label }}
                    </span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <span class="text-muted small">Produk</span>
                        <div class="fw-bold">{{ $order->product->name ?? '-' }}</div>
                    </div>
                    <div class="col-sm-3">
                        <span class="text-muted small">Jumlah</span>
                        <div class="fw-bold">{{ $order->quantity }}</div>
                    </div>
                    <div class="col-sm-3">
                        <span class="text-muted small">Total</span>
                        <div class="fw-bold product-price">{{ $order->formatted_total }}</div>
                    </div>
                </div>

                <hr>

                <h6 class="fw-bold mb-3">Timeline</h6>
                <div class="order-timeline">
                    <div class="timeline-step completed">
                        <div class="fw-bold small">Pesanan Dibuat</div>
                        <div class="text-muted small">{{ $order->created_at->format('d M Y H:i:s') }}</div>
                    </div>

                    @if($order->status === 'delivered')
                    <div class="timeline-step completed">
                        <div class="fw-bold small">Pembayaran Diterima</div>
                        <div class="text-muted small">Pembayaran telah diverifikasi</div>
                    </div>
                    <div class="timeline-step completed">
                        <div class="fw-bold small">Produk Dikirim</div>
                        <div class="text-muted small">
                            {{ $order->delivered_at ? $order->delivered_at->format('d M Y H:i:s') : '-' }}
                        </div>
                    </div>
                    @elseif($order->status === 'paid')
                    <div class="timeline-step completed">
                        <div class="fw-bold small">Pembayaran Diterima</div>
                        <div class="text-muted small">Menunggu pengiriman produk</div>
                    </div>
                    <div class="timeline-step active">
                        <div class="fw-bold small">Menunggu Pengiriman</div>
                        <div class="text-muted small">Produk sedang diproses</div>
                    </div>
                    @elseif($order->status === 'pending_payment')
                    <div class="timeline-step active">
                        <div class="fw-bold small">Menunggu Pembayaran</div>
                        <div class="text-muted small">
                            @if($order->expires_at)
                                Batas waktu: {{ $order->expires_at->format('d M Y H:i:s') }}
                            @else
                                Segera lakukan pembayaran
                            @endif
                        </div>
                    </div>
                    @elseif(in_array($order->status, ['cancelled', 'expired']))
                    <div class="timeline-step">
                        <div class="fw-bold small text-danger">
                            {{ $order->status === 'cancelled' ? 'Dibatalkan' : 'Kedaluwarsa' }}
                        </div>
                        <div class="text-muted small">
                            {{ $order->cancel_reason ?: 'Tidak ada alasan' }}
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Stock content for delivered orders --}}
                @if($order->status === 'delivered' && $order->stockUnits && $order->stockUnits->count() > 0)
                <hr>
                <h6 class="fw-bold mb-3"><i class="fas fa-key text-success me-2"></i>Produk Anda</h6>
                <div class="bg-body-secondary rounded-3 p-3">
                    @foreach($order->stockUnits as $unit)
                    <div class="mb-2 p-2 bg-body rounded border">
                        <code class="text-break">{{ $unit->content }}</code>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">Ringkasan</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Harga Satuan</span>
                    <span>Rp {{ number_format(($order->total_price / max(1, $order->quantity)), 0, ',', '.') }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Jumlah</span>
                    <span>× {{ $order->quantity }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Total</span>
                    <span class="fw-bold product-price">{{ $order->formatted_total }}</span>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary w-100 rounded-pill">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Riwayat
            </a>
        </div>
    </div>
</div>
@endsection
