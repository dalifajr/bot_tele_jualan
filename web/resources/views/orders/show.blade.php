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
                <h6 class="fw-bold mb-3"><i class="fas fa-key text-success me-2"></i>Detail Akun yang Dibeli</h6>
                <div class="bg-body-secondary rounded-3 p-3 text-break" style="max-height: 300px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; font-size: 0.9rem;">
@foreach($order->stockUnits as $unit)
{{ $unit->raw_text }}
@if(!$loop->last)

----------------------------------------

@endif
@endforeach
                </div>
                @endif

                {{-- Modul Komplain / Garansi --}}
                @if($order->status === 'delivered')
                <hr>
                <div class="mt-4">
                    @if($order->complaintCase)
                        <div class="card bg-body-tertiary border-0 shadow-sm" style="border-radius: 12px;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0 text-dark">
                                        <i class="fas fa-toolbox text-warning me-2"></i>
                                        Tiket Komplain & Garansi
                                    </h6>
                                    @php
                                        $statusBadge = match($order->complaintCase->status) {
                                            'new' => ['bg' => 'danger-subtle', 'text' => 'danger', 'label' => 'Baru'],
                                            'review' => ['bg' => 'warning-subtle', 'text' => 'warning', 'label' => 'Ditinjau'],
                                            'done' => ['bg' => 'success-subtle', 'text' => 'success', 'label' => 'Selesai'],
                                            'rejected' => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => 'Ditolak'],
                                            default => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => $order->complaintCase->status]
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusBadge['bg'] }} text-{{ $statusBadge['text'] }} rounded-pill px-3 py-1.5 small">
                                        {{ $statusBadge['label'] }}
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted small d-block">No Tiket:</span>
                                    <span class="fw-bold text-secondary">#{{ $order->complaintCase->complaint_ref }}</span>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted small d-block">Detail Masalah:</span>
                                    <p class="mb-0 text-dark small bg-body p-2.5 rounded border border-light-subtle">{{ $order->complaintCase->complaint_text }}</p>
                                </div>
                                @if($order->complaintCase->status === 'rejected' && $order->complaintCase->rejected_reason)
                                <div class="alert alert-danger mb-0 py-2.5 px-3 rounded-3 mt-2">
                                    <h6 class="fw-bold mb-1 small"><i class="fas fa-times-circle me-1"></i>Alasan Penolakan Admin:</h6>
                                    <p class="mb-0 small">{{ $order->complaintCase->rejected_reason }}</p>
                                </div>
                                @endif
                                @if($order->complaintCase->status === 'done' && $order->complaintCase->refund_note)
                                <div class="alert alert-success mb-0 py-2.5 px-3 rounded-3 mt-2">
                                    <h6 class="fw-bold mb-1 small"><i class="fas fa-check-circle me-1"></i>Catatan Resolusi Admin:</h6>
                                    <p class="mb-0 small">{{ $order->complaintCase->refund_note }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="d-flex align-items-center justify-content-between bg-light-subtle border border-dashed border-secondary-subtle rounded-3 p-3 mt-3">
                            <div>
                                <h6 class="fw-bold mb-1"><i class="fas fa-shield-alt text-primary me-2"></i>Garansi Toko Aktif</h6>
                                <p class="text-muted small mb-0">Apakah akun digital yang Anda beli bermasalah? Ajukan klaim garansi di sini.</p>
                            </div>
                            <button type="button" class="btn btn-warning rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#complaintModal">
                                <i class="fas fa-toolbox me-2"></i>Ajukan Komplain
                            </button>
                        </div>
                    @endif
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
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary w-100 rounded-pill mb-2">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Riwayat
            </a>
            @if($order->status === 'pending_payment')
            <form action="{{ route('orders.cancel', $order->id) }}" method="POST" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini?');">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100 rounded-pill">
                    <i class="fas fa-times-circle me-2"></i>Batalkan Pesanan
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

@if($order->status === 'delivered' && !$order->complaintCase)
@push('modals')
<div class="modal fade" id="complaintModal" tabindex="-1" aria-labelledby="complaintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold" id="complaintModalLabel"><i class="fas fa-toolbox text-warning me-2"></i>Ajukan Klaim Garansi</h5>
                <button type="button" class="btn-close" data-bs-toggle="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('orders.complaint', $order->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 small border-0 mb-3" style="border-radius: 8px;">
                        <i class="fas fa-info-circle me-1"></i> <b>Kebijakan Garansi:</b> Harap jelaskan kendala yang dialami secara rinci (misal: kredensial salah, pack belum diklaim, dsb).
                    </div>
                    <div class="mb-0">
                        <label for="complaint_text" class="form-label text-muted small fw-bold">Deskripsi Masalah / Keluhan</label>
                        <textarea class="form-control" id="complaint_text" name="complaint_text" rows="5" required minlength="10" maxlength="1000" placeholder="Tuliskan keluhan Anda di sini (minimal 10 karakter)..."></textarea>
                        <div class="form-text">Jelaskan secara jelas kendala login atau kegagalan akun agar admin/seller dapat segera membantu.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4">Kirim Pengaduan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush
@endif
@endsection
