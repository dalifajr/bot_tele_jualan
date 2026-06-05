@extends('layouts.app')

@section('title', 'Detail Komplain #' . $complaint->complaint_ref)
@section('page_subtitle', 'Detail Komplain')

@section('content')
<div class="mb-4">
    <a href="{{ route('seller.complaints.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke List Komplain
    </a>
</div>

<div class="row g-4">
    {{-- Left: Details & Stock info --}}
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
                        <span class="text-muted small">Pelanggan</span>
                        <div class="fw-bold text-primary">{{ $complaint->customer->full_name ?? 'Unknown User' }}</div>
                        <small class="text-muted">Username: {{ '@' . ($complaint->customer->username ?? $complaint->customer_username_snapshot) }}</small>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small">Tanggal Keluhan</span>
                        <div class="fw-bold">{{ $complaint->created_at ? $complaint->created_at->format('d M Y H:i:s') : '-' }}</div>
                    </div>
                </div>

                <hr>

                <div class="mb-4">
                    <span class="text-muted small fw-bold d-block mb-1">Rincian Keluhan Pelanggan:</span>
                    <div class="bg-body-secondary rounded-3 p-3 text-dark text-wrap small" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.5;">{{ $complaint->complaint_text }}</div>
                </div>

                {{-- Associated Order --}}
                @if($complaint->order)
                <hr>
                <div class="mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-shopping-bag text-primary me-2"></i>Pesanan Terkait: {{ $complaint->order->order_ref }}</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <span class="text-muted small">Produk</span>
                            <div class="fw-semibold">{{ $complaint->order->product->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">Kuantitas</span>
                            <div class="fw-semibold">{{ $complaint->order->quantity }} Pcs</div>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small">Total Pembayaran</span>
                            <div class="fw-semibold text-success">{{ $complaint->order->formatted_total }}</div>
                        </div>
                    </div>
                </div>

                {{-- Digital Stock details --}}
                @if($complaint->order->stockUnits && $complaint->order->stockUnits->count() > 0)
                <hr>
                <div class="mb-0">
                    <h6 class="fw-bold mb-3 text-success"><i class="fas fa-key me-2"></i>Unit Stok Anda yang Dibeli:</h6>
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
                @endif
            </div>
        </div>
    </div>

    {{-- Right: Update Resolution card --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-gavel text-primary me-2"></i>Resolusi Tiket (Seller)</h6>
                
                <form action="{{ route('seller.complaints.updateStatus', $complaint->id) }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Pilih Status Baru</label>
                        <select name="status" id="status-select" class="form-select" required>
                            <option value="review" {{ $complaint->status === 'review' ? 'selected' : '' }}>Ditinjau (In Review)</option>
                            <option value="done" {{ $complaint->status === 'done' ? 'selected' : '' }}>Selesai / Terima Klaim (Done)</option>
                            <option value="rejected" {{ $complaint->status === 'rejected' ? 'selected' : '' }}>Tolak Klaim (Rejected)</option>
                        </select>
                    </div>

                    {{-- Rejected Reason block --}}
                    <div id="rejected-reason-block" class="mb-3 d-none">
                        <label for="rejected_reason" class="form-label text-muted small fw-bold text-danger">Alasan Penolakan</label>
                        <textarea class="form-control" name="rejected_reason" id="rejected_reason" rows="4" placeholder="Jelaskan alasan penolakan klaim garansi...">{{ $complaint->rejected_reason }}</textarea>
                        <div class="form-text">Alasan ini akan tampil di web panel pelanggan.</div>
                    </div>

                    {{-- Done/Refund block --}}
                    <div id="refund-note-block" class="mb-3 d-none">
                        <label for="refund_note" class="form-label text-muted small fw-bold text-success">Catatan Resolusi / Ganti Rugi</label>
                        <textarea class="form-control" name="refund_note" id="refund_note" rows="4" placeholder="Tuliskan catatan penyelesaian (misal: dana dikembalikan / akun diganti)...">{{ $complaint->refund_note }}</textarea>
                        <div class="form-text">Catatan penyelesaian garansi akan diinformasikan ke pelanggan.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 rounded-pill mt-3">
                        <i class="fas fa-save me-2"></i>Simpan Resolusi
                    </button>
                </form>
            </div>
        </div>

        {{-- Audit & Date Info --}}
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4 small">
                <h6 class="fw-bold mb-3">Informasi Lain</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Tanggal Dibuat</span>
                    <span class="text-dark">{{ $complaint->created_at ? $complaint->created_at->format('d M Y H:i') : '-' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Pembaruan Terakhir</span>
                    <span class="text-dark">{{ $complaint->updated_at ? $complaint->updated_at->format('d M Y H:i') : '-' }}</span>
                </div>
                @if($complaint->closed_at)
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Ditutup Pada</span>
                    <span class="text-dark">{{ $complaint->closed_at->format('d M Y H:i') }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('status-select');
        const rejectedBlock = document.getElementById('rejected-reason-block');
        const refundBlock = document.getElementById('refund-note-block');
        const rejectedInput = document.getElementById('rejected_reason');
        const refundInput = document.getElementById('refund_note');

        function toggleBlocks() {
            const val = select.value;
            if (val === 'rejected') {
                rejectedBlock.classList.remove('d-none');
                refundBlock.classList.add('d-none');
                rejectedInput.setAttribute('required', 'true');
                refundInput.removeAttribute('required');
            } else if (val === 'done') {
                refundBlock.classList.remove('d-none');
                rejectedBlock.classList.add('d-none');
                refundInput.setAttribute('required', 'true');
                rejectedInput.removeAttribute('required');
            } else {
                rejectedBlock.classList.add('d-none');
                refundBlock.classList.add('d-none');
                rejectedInput.removeAttribute('required');
                refundInput.removeAttribute('required');
            }
        }

        select.addEventListener('change', toggleBlocks);
        toggleBlocks(); // init state
    });
</script>
@endpush
@endsection
