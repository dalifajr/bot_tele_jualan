@extends('layouts.app')

@section('title', __('Manajemen Penarikan Dana (Payouts)'))
@section('page_subtitle', __('Penarikan Dana'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 mb-md-4">
    <div>
        <h4 class="fw-bold mb-1 text-body fs-5 fs-md-4">{{ __('Manajemen Penarikan Dana (Payouts)') }}</h4>
        <p class="text-muted small mb-0">{{ __('Verifikasi dan proses penarikan dana dari dompet saldo para seller mitra') }}</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success rounded-4 small py-2.5 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fas fa-check-circle flex-shrink-0"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger rounded-4 small py-2.5 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fas fa-exclamation-circle flex-shrink-0"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: var(--radius-lg, 20px);">
    <div class="card-body p-0">
        @if($withdrawals->count() > 0)
        {{-- Desktop Table View --}}
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">{{ __('Tanggal') }}</th>
                        <th class="py-3 border-0">{{ __('Seller') }}</th>
                        <th class="py-3 border-0">{{ __('Tujuan Rekening / Bank') }}</th>
                        <th class="py-3 border-0 text-end">{{ __('Jumlah Penarikan') }}</th>
                        <th class="py-3 border-0 text-center">{{ __('Status') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($withdrawals as $withdrawal)
                    <tr>
                        <td class="px-4 text-muted small text-nowrap">{{ $withdrawal->created_at->format('d M Y H:i') }}</td>
                        <td>
                            <div class="fw-bold text-primary small">{{ $withdrawal->seller->full_name ?? $withdrawal->seller->username ?? 'Unknown Seller' }}</div>
                            <div class="small text-muted font-monospace" style="font-size: 0.72rem;">ID: {{ $withdrawal->seller->telegram_id }}</div>
                        </td>
                        <td>
                            <div class="fw-bold text-body small">{{ strtoupper($withdrawal->bank_name) }}</div>
                            <div class="small text-muted font-monospace">{{ $withdrawal->account_number }} a.n. {{ $withdrawal->account_holder }}</div>
                        </td>
                        <td class="fw-bold text-success text-end text-nowrap m3-tabular-nums">
                            Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}
                        </td>
                        <td class="text-center">
                            @if($withdrawal->status === 'pending')
                                <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-1 fw-semibold"><i class="fas fa-spinner fa-spin me-1"></i>{{ __('Menunggu') }}</span>
                            @elseif($withdrawal->status === 'approved')
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 fw-semibold"><i class="fas fa-check-circle me-1"></i>{{ __('Selesai') }}</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1 fw-semibold"><i class="fas fa-times-circle me-1"></i>{{ __('Ditolak') }}</span>
                            @endif
                        </td>
                        <td class="text-end px-4">
                            @if($withdrawal->status === 'pending')
                                <button class="btn btn-sm btn-primary rounded-pill px-3 me-1 fw-semibold" data-bs-toggle="modal" data-bs-target="#approveModal{{ $withdrawal->id }}">
                                    <i class="fas fa-check me-1"></i> {{ __('Setujui') }}
                                </button>
                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $withdrawal->id }}">
                                    <i class="fas fa-ban me-1"></i> {{ __('Tolak') }}
                                </button>
                            @else
                                @if($withdrawal->status === 'approved')
                                    <button class="btn btn-sm btn-tonal rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#viewProofModal{{ $withdrawal->id }}">
                                        <i class="fas fa-receipt me-1"></i> {{ __('Bukti Transfer') }}
                                    </button>
                                @else
                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#viewRejectionModal{{ $withdrawal->id }}">
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

        {{-- Mobile Stacked Card List --}}
        <div class="d-md-none p-3 d-flex flex-column gap-2.5">
            @foreach($withdrawals as $withdrawal)
            <div class="m3-mobile-item-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="min-w-0">
                        <div class="fw-bold text-primary small">{{ $withdrawal->seller->full_name ?? $withdrawal->seller->username ?? 'Unknown Seller' }}</div>
                        <div class="fw-semibold text-body small mt-0.5">{{ strtoupper($withdrawal->bank_name) }} &bull; <span class="font-monospace">{{ $withdrawal->account_number }}</span></div>
                        <div class="text-muted small" style="font-size: 0.74rem;">a.n. {{ $withdrawal->account_holder }}</div>
                    </div>
                    <div>
                        @if($withdrawal->status === 'pending')
                            <span class="badge bg-warning-subtle text-warning rounded-pill px-2.5 py-1 fw-semibold">{{ __('Menunggu') }}</span>
                        @elseif($withdrawal->status === 'approved')
                            <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-semibold">{{ __('Selesai') }}</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fw-semibold">{{ __('Ditolak') }}</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-2 mt-1" style="border-top: 1px solid var(--outline-variant);">
                    <div>
                        <div class="fw-bold text-success fs-6 m3-tabular-nums">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</div>
                        <small class="text-muted" style="font-size: 0.72rem;"><i class="far fa-clock me-1"></i>{{ $withdrawal->created_at->format('d M Y, H:i') }}</small>
                    </div>

                    <div class="d-flex gap-1.5">
                        @if($withdrawal->status === 'pending')
                            <button class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#approveModal{{ $withdrawal->id }}">
                                <i class="fas fa-check"></i><span>{{ __('Setujui') }}</span>
                            </button>
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $withdrawal->id }}">
                                <i class="fas fa-ban"></i><span>{{ __('Tolak') }}</span>
                            </button>
                        @elseif($withdrawal->status === 'approved')
                            <button class="btn btn-sm btn-tonal rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#viewProofModal{{ $withdrawal->id }}">
                                <i class="fas fa-receipt"></i><span>{{ __('Bukti') }}</span>
                            </button>
                        @else
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#viewRejectionModal{{ $withdrawal->id }}">
                                <i class="fas fa-comment-slash"></i><span>{{ __('Alasan') }}</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="px-3 px-sm-4 py-3 border-top">
            {{ $withdrawals->links() }}
        </div>
        @else
        <div class="text-center py-5 px-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--on-surface-variant); font-size: 1.4rem;">
                <i class="fas fa-hand-holding-usd"></i>
            </span>
            <p class="text-muted mb-0 small">{{ __('Tidak ada pengajuan penarikan dana saat ini.') }}</p>
        </div>
        @endif
    </div>
</div>

@push('modals')
@foreach($withdrawals as $withdrawal)
@if($withdrawal->status === 'pending')
{{-- Approve Modal --}}
<div class="modal fade m3-sheet-modal" id="approveModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: var(--radius-xl, 28px); border: 1px solid var(--outline-variant);">
            <div class="d-md-none m3-sheet-handle" aria-hidden="true"></div>
            <div class="modal-header border-0 px-4 pt-3 pt-md-4 pb-2">
                <h5 class="fw-bold mb-0 text-body">{{ __('Persetujuan & Unggah Bukti Payout') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <form action="{{ route('admin.withdrawals.approve', $withdrawal->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body px-4 py-3">
                    <div class="alert alert-info border-0 rounded-4 small py-2.5 px-3 mb-3 d-flex align-items-start gap-2">
                        <i class="fas fa-info-circle mt-0.5 flex-shrink-0"></i>
                        <span>{{ __('Silakan lakukan transfer manual terlebih dahulu sesuai dengan rekening tujuan di bawah ini sebesar nominal penarikan, kemudian unggah struk bukti transfer.') }}</span>
                    </div>
                    <div class="mb-3 p-3 rounded-4" style="background-color: var(--surface-container-low); border: 1px solid var(--outline-variant);">
                        <p class="mb-1 text-muted small">{{ __('Tujuan Rekening') }}</p>
                        <h6 class="fw-bold mb-2 text-primary font-monospace">{{ strtoupper($withdrawal->bank_name) }} — {{ $withdrawal->account_number }}</h6>
                        <p class="mb-1 text-muted small">{{ __('Nama Pemilik Rekening') }}</p>
                        <h6 class="fw-bold mb-2 text-body">{{ $withdrawal->account_holder }}</h6>
                        <p class="mb-1 text-muted small">{{ __('Jumlah Transfer') }}</p>
                        <h5 class="fw-bold text-success m-0 m3-tabular-nums">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</h5>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold mb-1.5">{{ __('Pilih File Bukti Transfer (Gambar)') }}</label>
                        <input type="file" name="proof_image" class="form-control" accept="image/*" required>
                        <div class="form-text small">{{ __('Maksimal ukuran file 2MB dengan tipe JPEG, PNG, JPG.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-between gap-2">
                    <button type="button" class="btn btn-tonal rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Setujui & Selesaikan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade m3-sheet-modal" id="rejectModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: var(--radius-xl, 28px); border: 1px solid var(--outline-variant);">
            <div class="d-md-none m3-sheet-handle" aria-hidden="true"></div>
            <div class="modal-header border-0 px-4 pt-3 pt-md-4 pb-2">
                <h5 class="fw-bold mb-0 text-danger">{{ __('Tolak Pengajuan Penarikan') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <form action="{{ route('admin.withdrawals.reject', $withdrawal->id) }}" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">
                    <div class="mb-3 p-3 rounded-4" style="background-color: var(--surface-container-low); border: 1px solid var(--outline-variant);">
                        <p class="mb-1 text-muted small">{{ __('Seller') }}</p>
                        <h6 class="fw-bold mb-2 text-body">{{ $withdrawal->seller->full_name ?? $withdrawal->seller->username }}</h6>
                        <p class="mb-1 text-muted small">{{ __('Jumlah Pengajuan') }}</p>
                        <h5 class="fw-bold text-danger m-0 m3-tabular-nums">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</h5>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold mb-1.5">{{ __('Alasan Penolakan') }}</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="{{ __('Masukkan alasan mengapa permintaan penarikan ini ditolak...') }}" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-between gap-2">
                    <button type="button" class="btn btn-tonal rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-semibold">{{ __('Tolak Permintaan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@else
{{-- View Proof Modal --}}
<div class="modal fade m3-sheet-modal" id="viewProofModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: var(--radius-xl, 28px); border: 1px solid var(--outline-variant);">
            <div class="d-md-none m3-sheet-handle" aria-hidden="true"></div>
            <div class="modal-header border-0 px-4 pt-3 pt-md-4 pb-2">
                <h5 class="fw-bold mb-0 text-body">{{ __('Struk Bukti Transfer Resmi') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="modal-body px-4 py-3 text-center">
                @if($withdrawal->proof_image_path)
                    <img src="{{ asset($withdrawal->proof_image_path) }}" alt="{{ __('Bukti Transfer Payout') }}" class="img-fluid rounded-4 border mb-3" style="max-height: 380px; object-fit: contain; border-color: var(--outline-variant) !important;">
                @else
                    <div class="p-4 rounded-4 mb-3 text-muted small" style="background: var(--surface-container-low); border: 1px solid var(--outline-variant);">
                        <i class="fas fa-image fa-2x mb-2 opacity-50 d-block"></i>
                        {{ __('Bukti transfer tidak tersedia.') }}
                    </div>
                @endif
                <div class="p-3 rounded-4 text-start mt-2" style="background-color: var(--surface-container-low); border: 1px solid var(--outline-variant);">
                    <div class="d-flex justify-content-between gap-2 small py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <span class="text-muted">{{ __('Dikirim Ke') }}</span>
                        <span class="fw-bold text-end text-body">{{ strtoupper($withdrawal->bank_name) }} — {{ $withdrawal->account_number }} ({{ $withdrawal->account_holder }})</span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 small py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <span class="text-muted">{{ __('Jumlah') }}</span>
                        <span class="fw-bold text-success text-end m3-tabular-nums">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 small pt-1.5">
                        <span class="text-muted">{{ __('Tanggal Diproses') }}</span>
                        <span class="fw-bold text-end text-body">{{ $withdrawal->processed_at ? $withdrawal->processed_at->format('d M Y, H:i') : '-' }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button type="button" class="btn btn-tonal rounded-pill px-4 fw-semibold w-100 w-md-auto" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- View Rejection Modal --}}
<div class="modal fade m3-sheet-modal" id="viewRejectionModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: var(--radius-xl, 28px); border: 1px solid var(--outline-variant);">
            <div class="d-md-none m3-sheet-handle" aria-hidden="true"></div>
            <div class="modal-header border-0 px-4 pt-3 pt-md-4 pb-2">
                <h5 class="fw-bold mb-0 text-danger">{{ __('Detail Penolakan Penarikan') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="p-3 rounded-4 mb-3" style="background-color: var(--error-container); color: var(--on-error-container);">
                    <div class="fw-bold mb-1 small">{{ __('Alasan Ditolak:') }}</div>
                    <div class="small lh-base">{{ $withdrawal->rejection_reason ?? __('Tidak ada alasan penolakan yang dicantumkan.') }}</div>
                </div>
                <div class="p-3 rounded-4 text-start" style="background-color: var(--surface-container-low); border: 1px solid var(--outline-variant);">
                    <div class="d-flex justify-content-between gap-2 small py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <span class="text-muted">{{ __('Tujuan Awal') }}</span>
                        <span class="fw-bold text-end text-body">{{ strtoupper($withdrawal->bank_name) }} — {{ $withdrawal->account_number }} ({{ $withdrawal->account_holder }})</span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 small py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <span class="text-muted">{{ __('Jumlah Awal') }}</span>
                        <span class="fw-bold text-danger text-end m3-tabular-nums">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 small pt-1.5">
                        <span class="text-muted">{{ __('Waktu Penolakan') }}</span>
                        <span class="fw-bold text-end text-body">{{ $withdrawal->processed_at ? $withdrawal->processed_at->format('d M Y, H:i') : '-' }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button type="button" class="btn btn-tonal rounded-pill px-4 fw-semibold w-100 w-md-auto" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@endif
@endforeach
@endpush
@endsection
