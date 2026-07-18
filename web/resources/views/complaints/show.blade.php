@extends('layouts.app')

@section('title', 'Detail Komplain #' . $complaint->complaint_ref)
@section('page_subtitle', 'Detail Komplain')

@section('content')
<div class="mb-4">
    <a href="{{ route('customer.complaints.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> {{ __('Kembali ke List Komplain') }}
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-toolbox text-warning me-2"></i>
                        Tiket Komplain #{{ $complaint->complaint_ref }}
                    </h5>
                    @php
                        $statusBadge = match($complaint->status) {
                            'new' => ['bg' => 'danger-subtle', 'text' => 'danger', 'label' => 'Baru'],
                            'review' => ['bg' => 'warning-subtle', 'text' => 'warning', 'label' => 'Ditinjau'],
                            'done' => ['bg' => 'success-subtle', 'text' => 'success', 'label' => 'Selesai'],
                            'rejected' => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => 'Ditolak'],
                            'refund_requested' => ['bg' => 'info-subtle', 'text' => 'info', 'label' => 'Proses Refund'],
                            default => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => $complaint->status]
                        };
                    @endphp
                    <span class="badge bg-{{ $statusBadge['bg'] }} text-{{ $statusBadge['text'] }} rounded-pill px-3 py-1.5">
                        {{ $statusBadge['label'] }}
                    </span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <span class="text-muted small">{{ __('Tanggal Keluhan') }}</span>
                        <div class="fw-bold">{{ $complaint->created_at ? $complaint->created_at->format('d M Y H:i:s') : '-' }}</div>
                    </div>
                </div>

                <hr>

                <div class="mb-4">
                    <span class="text-muted small fw-bold d-block mb-1">{{ __('Rincian Keluhan Anda:') }}</span>
                    <div class="bg-body-secondary rounded-3 p-3 text-dark text-wrap small" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.5;">{{ $complaint->complaint_text }}</div>
                </div>

                @if($complaint->attachment_path)
                <div class="mb-4">
                    <span class="text-muted small fw-bold d-block mb-1">{{ __('Lampiran Foto Bukti:') }}</span>
                    <a href="{{ asset('storage/' . $complaint->attachment_path) }}" target="_blank">
                        <img src="{{ asset('storage/' . $complaint->attachment_path) }}" alt="Bukti Komplain" class="img-fluid rounded-3 border" style="max-height: 300px; object-fit: contain;">
                    </a>
                </div>
                @endif

                @if($complaint->order)
                <hr>
                <div class="mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-shopping-bag text-primary me-2"></i>Pesanan Terkait: {{ $complaint->order->order_ref }}</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <span class="text-muted small">{{ __('Produk') }}</span>
                            <div class="fw-semibold">{{ $complaint->order->product->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">{{ __('Kuantitas') }}</span>
                            <div class="fw-semibold">{{ $complaint->order->quantity }} Pcs</div>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">{{ __('Total Pembayaran') }}</span>
                            <div class="fw-semibold text-success">{{ $complaint->order->formatted_total }}</div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Digital Stock details --}}
                @if($complaint->order && $complaint->order->stockUnits && $complaint->order->stockUnits->count() > 0)
                <hr>
                <div class="mb-0">
                    <h6 class="fw-bold mb-3 text-success"><i class="fas fa-key me-2"></i>{{ __('Kredensial Akun (Unit Stok):') }}</h6>
                    <div class="bg-light text-dark rounded-3 p-3 text-break" style="max-height: 300px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; font-size: 0.85rem; border: 1px solid rgba(0,0,0,0.05);">
@foreach($complaint->order->stockUnits as $unit)
<b>Unit #{{ $unit->id }} (Status: {{ $unit->stock_status }}):</b>
{{ $unit->raw_text }}
@if(!$loop->last)

----------------------------------------

@endif
@endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-gavel text-primary me-2"></i>{{ __('Resolusi Tiket') }}</h6>
                
                @if($complaint->status === 'new' || $complaint->{{ __('status === \'review\')') }}
                    <div class="alert alert-warning small">
                        {{ __('Komplain Anda sedang ditinjau oleh penjual. Harap menunggu tanggapan.') }}
                    </div>
                @endif

                @if($complaint->status === 'rejected' && $complaint->rejected_reason
                    <div class="alert alert-danger small mb-3">
                        <strong>{{ __('Klaim Ditolak:') }}</strong><br>
                        {{ $complaint->rejected_reason }}
                    </div>
                @endif

                @if(($complaint->status === 'done' || $complaint->status === 'refund_requested') && $complaint->refund_note
                    <div class="alert alert-success small mb-3">
                        <strong>{{ __('Penyelesaian:') }}</strong><br>
                        {{ $complaint->refund_note }}
                    </div>
                @endif

                @if(in_array($complaint->{{ __('status, [\'done\', \'rejected\', \'refund_requested\']))') }}
                    <hr>
                    @if($complaint->{{ __('reopen_count') }} < 3)
                        <div class="text-center mt-3">
                            <p class="small text-muted mb-2">Masih memiliki kendala dengan pesanan ini? (Sisa kesempatan: {{ 3 - $complaint->reopen_count }})</p>
                            <form action="{{ route('customer.complaints.reopen', $complaint->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning w-100 rounded-pill" onclick="return confirm('Apakah Anda yakin ingin membuka kembali komplain ini?')">
                                    <i class="fas fa-redo me-1"></i> {{ __('Buka Kembali Komplain') }}
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="alert alert-secondary small mb-0 text-center">
                            {{ __('Anda telah mencapai batas maksimal pembukaan ulang (3 kali) untuk komplain ini.') }}
                        </div>
                    @endif
                @endif
            </div>
        </div>
        
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4 small">
                <h6 class="fw-bold mb-3">{{ __('Informasi Lain') }}</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ __('Tanggal Dibuat') }}</span>
                    <span class="text-dark">{{ $complaint->created_at ? $complaint->created_at->format('d M Y H:i') : '-' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ __('Pembaruan Terakhir') }}</span>
                    <span class="text-dark">{{ $complaint->updated_at ? $complaint->updated_at->format('d M Y H:i') : '-' }}</span>
                </div>
                @if($complaint->closed_at
                <div class="d-flex justify-content-between">
                    <span class="text-muted">{{ __('Ditutup Pada') }}</span>
                    <span class="text-dark">{{ $complaint->closed_at->format('d M Y H:i') }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
