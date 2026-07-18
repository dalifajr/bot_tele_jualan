@extends('layouts.app')

@section('title', 'Manajemen Penarikan Dana (Payouts)')
@section('page_subtitle', 'Penarikan Dana')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Penarikan Dana (Payouts)</h4>
        <p class="text-muted mb-0">{{ __('Verifikasi dan proses penarikan dana dari dompet saldo para seller mitra') }}</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($withdrawals->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">{{ __('Tanggal') }}</th>
                        <th class="py-3 border-0">{{ __('Seller') }}</th>
                        <th class="py-3 border-0">{{ __('Tujuan Rekening / Bank') }}</th>
                        <th class="py-3 border-0">{{ __('Jumlah Penarikan') }}</th>
                        <th class="py-3 border-0">{{ __('Status') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($withdrawals as $withdrawal)
                    <tr>
                        <td class="px-4 text-muted small">{{ $withdrawal->created_at->format('d M Y H:i') }}</td>
                        <td>
                            <div class="fw-bold text-primary">{{ $withdrawal->seller->full_name ?? $withdrawal->seller->username ?? 'Unknown Seller' }}</div>
                            <div class="small text-muted">ID: {{ $withdrawal->seller->telegram_id }}</div>
                        </td>
                        <td>
                            <div class="fw-bold">{{ strtoupper($withdrawal->bank_name) }}</div>
                            <div class="small text-muted">{{ $withdrawal->account_number }} a.n. {{ $withdrawal->account_holder }}</div>
                        </td>
                        <td class="fw-bold text-success">
                            Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}
                        </td>
                        <td>
                            @if($withdrawal->status === 'pending')
                                <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-1"><i class="fas fa-spinner fa-spin me-1"></i>{{ __('Menunggu') }}</span>
                            @elseif($withdrawal->status === 'approved')
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1"><i class="fas fa-check-circle me-1"></i>{{ __('Selesai') }}</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1"><i class="fas fa-times-circle me-1"></i>{{ __('Ditolak') }}</span>
                            @endif
                        </td>
                        <td class="text-end px-4">
                            @if($withdrawal->status === 'pending')
                                <button class="btn btn-sm btn-primary rounded-pill px-3 me-1" data-bs-toggle="modal" data-bs-target="#approveModal{{ $withdrawal->id }}">
                                    <i class="fas fa-check me-1"></i> {{ __('Setujui') }}
                                </button>
                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $withdrawal->id }}">
                                    <i class="fas fa-ban me-1"></i> {{ __('Tolak') }}
                                </button>
                            @else
                                @if($withdrawal->status === 'approved')
                                    <button class="btn btn-sm btn-light rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#viewProofModal{{ $withdrawal->id }}">
                                        <i class="fas fa-receipt me-1"></i> {{ __('Bukti Transfer') }}
                                    </button>
                                @else
                                    <button class="btn btn-sm btn-light rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#viewRejectionModal{{ $withdrawal->id }}">
                                        <i class="fas fa-comment-slash me-1"></i> {{ __('Alasan Ditolak') }}
                                    </button>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $withdrawals->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-hand-holding-usd text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">{{ __('Tidak ada pengajuan penarikan dana saat ini.') }}</p>
        </div>
        @endif
    </div>
</div>

@push('modals')
@foreach($withdrawals as $withdrawal)
@if($withdrawal->status === 'pending')
{{-- Approve Modal --}}
<div class="modal fade" id="approveModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Persetujuan & Unggah Bukti Payout') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.withdrawals.approve', $withdrawal->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-4 small py-2 mb-3">
                        <i class="fas fa-info-circle me-1"></i> {{ __('Silakan lakukan transfer manual terlebih dahulu sesuai dengan rekening tujuan di bawah ini sebesar nominal penarikan, kemudian unggah struk bukti transfer.') }}
                    </div>
                    <div class="mb-3 bg-light p-3 rounded-4">
                        <p class="mb-1 text-muted small">{{ __('Tujuan Rekening') }}</p>
                        <h6 class="fw-bold mb-2 text-primary">{{ strtoupper($withdrawal->bank_name) }} — {{ $withdrawal->account_number }}</h6>
                        <p class="mb-1 text-muted small">{{ __('Nama Pemilik Rekening') }}</p>
                        <h6 class="fw-bold mb-2">{{ $withdrawal->account_holder }}</h6>
                        <p class="mb-1 text-muted small">{{ __('Jumlah Transfer') }}</p>
                        <h5 class="fw-bold text-success m-0">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</h5>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Pilih File Bukti Transfer (Gambar)</label>
                        <input type="file" name="proof_image" class="form-control" accept="image/*" required>
                        <div class="form-text">{{ __('Maksimal ukuran file 2MB dengan tipe JPEG, PNG, JPG.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Setujui & Selesaikan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-danger">{{ __('Tolak Pengajuan Penarikan') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.withdrawals.reject', $withdrawal->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3 bg-light p-3 rounded-4">
                        <p class="mb-1 text-muted small">{{ __('Seller') }}</p>
                        <h6 class="fw-bold mb-2">{{ $withdrawal->seller->full_name ?? $withdrawal->seller->username }}</h6>
                        <p class="mb-1 text-muted small">{{ __('Jumlah Pengajuan') }}</p>
                        <h5 class="fw-bold text-danger m-0">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</h5>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Alasan Penolakan') }}</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="{{ __('Masukkan alasan mengapa permintaan penarikan ini ditolak...') }}" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">{{ __('Tolak Permintaan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@else
{{-- View Proof Modal --}}
<div class="modal fade" id="viewProofModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Struk Bukti Transfer Resmi') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                @if($withdrawal->proof_image_path)
                    <img src="{{ asset($withdrawal->proof_image_path) }}" alt="Bukti Transfer Payout" class="img-fluid rounded-4 shadow-sm mb-3" style="max-height: 400px; object-fit: contain;">
                @else
                    <p class="text-muted">{{ __('Bukti transfer tidak tersedia.') }}</p>
                @endif
                <div class="bg-light p-3 rounded-4 text-start mt-2">
                    <div class="row small py-1">
                        <div class="col-4 text-muted">{{ __('Dikirim Ke') }}</div>
                        <div class="col-8 fw-bold">{{ strtoupper($withdrawal->bank_name) }} — {{ $withdrawal->account_number }} ({{ $withdrawal->account_holder }})</div>
                    </div>
                    <div class="row small py-1">
                        <div class="col-4 text-muted">{{ __('Jumlah') }}</div>
                        <div class="col-8 fw-bold text-success">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</div>
                    </div>
                    <div class="row small py-1">
                        <div class="col-4 text-muted">{{ __('Tanggal Diproses') }}</div>
                        <div class="col-8 fw-bold">{{ $withdrawal->processed_at ? $withdrawal->processed_at->format('d M Y H:i') : '-' }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- View Rejection Modal --}}
<div class="modal fade" id="viewRejectionModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-danger">{{ __('Detail Penolakan Penarikan') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-danger-subtle text-danger p-3 rounded-4 mb-3">
                    <div class="fw-bold mb-1 small">{{ __('Alasan Ditolak:') }}</div>
                    <div class="lh-sm">{{ $withdrawal->rejection_reason ?? 'Tidak ada alasan penolakan yang dicantumkan.' }}</div>
                </div>
                <div class="bg-light p-3 rounded-4 text-start">
                    <div class="row small py-1">
                        <div class="col-4 text-muted">{{ __('Tujuan Awal') }}</div>
                        <div class="col-8 fw-bold">{{ strtoupper($withdrawal->bank_name) }} — {{ $withdrawal->account_number }} ({{ $withdrawal->account_holder }})</div>
                    </div>
                    <div class="row small py-1">
                        <div class="col-4 text-muted">{{ __('Jumlah Awal') }}</div>
                        <div class="col-8 fw-bold text-danger">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</div>
                    </div>
                    <div class="row small py-1">
                        <div class="col-4 text-muted">{{ __('Waktu Penolakan') }}</div>
                        <div class="col-8 fw-bold">{{ $withdrawal->processed_at ? $withdrawal->processed_at->format('d M Y H:i') : '-' }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@endif
@endforeach
@endpush
@endsection
