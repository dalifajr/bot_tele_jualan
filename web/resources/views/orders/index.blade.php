@extends('layouts.app')

@section('title', 'Riwayat Pesanan')
@section('page_subtitle', 'Pesanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Riwayat Pesanan') }}</h4>
        <p class="text-muted mb-0">{{ __('Daftar seluruh pesanan Anda') }}</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

{{-- Status Filter --}}
<div class="mb-4 d-flex flex-wrap gap-2">
    <a href="{{ route('orders.index') }}"
       class="btn btn-sm rounded-pill px-3 {{ is_null($status) ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ __('Semua') }}
    </a>
    @foreach(['pending_payment' => __('Menunggu'), 'paid' => __('Dibayar'), 'delivered' => __('Selesai'), 'cancelled' => __('Dibatalkan'), 'expired' => __('Kedaluwarsa')] as $key => $label)
    <a href="{{ route('orders.index', ['status' => $key]) }}"
       class="btn btn-sm rounded-pill px-3 {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($orders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">{{ __('No. Order') }}</th>
                        <th class="py-3 border-0">{{ __('Produk') }}</th>
                        <th class="py-3 border-0">{{ __('Qty') }}</th>
                        <th class="py-3 border-0">{{ __('Total') }}</th>
                        <th class="py-3 border-0">{{ __('Status') }}</th>
                        <th class="py-3 border-0">{{ __('Tanggal') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    <tr>
                        <td class="px-4 fw-bold text-primary">{{ $order->reference }}</td>
                        <td>{{ Str::limit($order->product->name ?? '-', 25) }}</td>
                        <td>{{ $order->quantity }}</td>
                        <td class="fw-bold">{{ $order->formatted_total }}</td>
                        <td>
                            <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3">
                                {{ $order->status_label }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $order->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end px-4">
                            <div class="d-flex gap-2 justify-content-end">
                                @if($order->status === 'pending_payment')
                                <form action="{{ route('orders.cancel', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle border-danger" title="{{ __('Batalkan Pesanan') }}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                @endif
                                <button class="btn btn-sm btn-light text-info rounded-circle" data-bs-toggle="modal" data-bs-target="#detailOrderModal{{ $order->id }}" title="{{ __('Lihat Detail') }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-4 py-3 border-top">
            {{ $orders->withQueryString()->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-box-open text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-3">
                @if($status)
                    {{ __('Tidak ada pesanan dengan status ":status".', ['status' => $status]) }}
                @else
                    {{ __('Belum ada pesanan.') }}
                @endif
            </p>
            <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-store me-2"></i>{{ __('Mulai Belanja') }}
            </a>
        </div>
        @endif
    </div>
</div>

@push('modals')
@foreach($orders as $order)
{{-- Detail Order Modal --}}
<div class="modal fade" id="detailOrderModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Detail Pesanan #:ref', ['ref' => $order->reference]) }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">{{ __('Informasi Pesanan') }}</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted" style="width: 120px;">{{ __('Produk') }}</td><td class="fw-bold">{{ $order->product->name ?? '-' }}</td></tr>
                            <tr><td class="text-muted">{{ __('Kuantitas (QTY)') }}</td><td>{{ $order->quantity }} unit</td></tr>
                            <tr><td class="text-muted">{{ __('Status') }}</td>
                                <td>
                                    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3">
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                            </tr>
                            <tr><td class="text-muted">{{ __('Tanggal Transaksi') }}</td><td>{{ $order->created_at->format('d M Y H:i:s') }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">{{ __('Rincian Pembayaran') }}</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted" style="width: 120px;">{{ __('Subtotal') }}</td><td>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td></tr>
                            <tr><td class="text-muted">{{ __('Kode Unik') }}</td><td>Rp {{ $order->unique_code }}</td></tr>
                            <tr><td class="text-muted">{{ __('Total Bayar') }}</td><td class="fw-bold text-primary">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td></tr>
                        </table>
                    </div>

                    @if($order->status === 'delivered' && $order->stockUnits && $order->stockUnits->count() > 0)
                    <div class="col-12 mt-2">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3"><i class="fas fa-key text-success me-2"></i>{{ __('Data Akun yang Dikirim (:count unit)', ['count' => $order->stockUnits->count()]) }}</h6>
                        <div class="bg-light rounded-3 p-3 text-break" style="max-height: 250px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; font-size: 0.85rem;">
@foreach($order->stockUnits as $unit)
{{ $unit->raw_text }}
@if(!$loop->last)

----------------------------------------

@endif
@endforeach
</div>
                    </div>
                    @endif

                    <div class="col-12 mt-2">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">{{ __('Timeline Transaksi') }}</h6>
                        <div class="d-flex flex-wrap gap-3 small">
                            <div><span class="text-muted">{{ __('Dibuat:') }}</span> <br><b>{{ $order->created_at->format('d M Y H:i:s') }}</b></div>
                            @if($order->paid_at)
                            <div><span class="text-muted">{{ __('Dibayar:') }}</span> <br><b class="text-success">{{ \Carbon\Carbon::parse($order->paid_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                            @if($order->delivered_at)
                            <div><span class="text-muted">{{ __('Dikirim:') }}</span> <br><b class="text-primary">{{ \Carbon\Carbon::parse($order->delivered_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                            @if($order->cancelled_at)
                            <div><span class="text-muted">{{ __('Dibatalkan:') }}</span> <br><b class="text-danger">{{ \Carbon\Carbon::parse($order->cancelled_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                        </div>
                        @if($order->cancel_reason)
                        <div class="alert alert-danger mt-3 small mb-0">
                            <b>{{ __('Alasan Batal:') }}</b> {{ $order->cancel_reason }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                @if($order->status === 'pending_payment')
                <a href="{{ route('checkout.success', ['order_ref' => $order->order_ref]) }}" class="btn btn-success rounded-pill px-4">
                    <i class="fas fa-qrcode me-1"></i>{{ __('Bayar Sekarang (QRIS)') }}
                </a>
                <form action="{{ route('orders.cancel', $order->id) }}" method="POST" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini?');" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger rounded-pill px-4">
                        <i class="fas fa-times-circle me-1"></i>{{ __('Batalkan Pesanan') }}
                    </button>
                </form>
                @endif
                <a href="{{ route('orders.show', $order->id) }}" class="btn btn-outline-primary rounded-pill px-4">
                    <i class="fas fa-external-link-alt me-1"></i>{{ __('Lihat Detail') }}
                </a>
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endpush
@endsection
