@extends('layouts.app')

@section('title', __('Top-Up Dompet Pelanggan'))
@section('page_subtitle', __('Verifikasi otomatis QRIS, konfirmasi manual kendala QRIS & kredit saldo langsung'))

@section('content')
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Menunggu QRIS') }}</small>
                <div class="fs-3 fw-bold text-warning mt-1">{{ $pendingCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Kedaluwarsa (Bisa Manual)') }}</small>
                <div class="fs-3 fw-bold text-danger mt-1">{{ $expiredCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Total Saldo Pelanggan') }}</small>
                <div class="fs-4 fw-bold text-primary mt-1">Rp {{ number_format($totalCustomerBalance, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Total Top-Up Terkonfirmasi') }}</small>
                <div class="fs-4 fw-bold text-success mt-1">Rp {{ number_format($totalPaidTopup, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Left: Direct Manual Balance Credit Form --}}
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-1">
                    <i class="fas fa-hand-holding-dollar text-primary me-1.5"></i>{{ __('Tambah Saldo Manual (Kendala QRIS)') }}
                </h6>
                <p class="small text-secondary mb-3">
                    {{ __('Gunakan formulir ini jika pelanggan terkendala QRIS dan melakukan transfer manual langsung ke Admin.') }}
                </p>

                <form action="{{ route('admin.wallet.manual-credit') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Pilih Pelanggan') }}</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">{{ __('-- Pilih Akun Pelanggan --') }}</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}">
                                    {{ $cust->full_name ?: $cust->username }} ({{ '@' . ($cust->username ?: 'id:' . $cust->id) }}) — Rp {{ number_format((int) ($cust->customer_wallet_balance ?? 0), 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Nominal Saldo (Rp)') }}</label>
                        <input type="number" name="amount" class="form-control fw-bold" min="1000" max="50000000" step="1000" placeholder="50000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Catatan / Bukti Transfer') }}</label>
                        <input type="text" name="note" class="form-control" placeholder="{{ __('Transfer manual BCA / kendala QRIS') }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">
                        <i class="fas fa-plus-circle me-1.5"></i>{{ __('Tambahkan Saldo Sekarang') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Right: Top-Up Requests Table with Manual Confirmation for Pending / Expired / Cancelled --}}
    <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="fw-bold mb-0 text-body"><i class="fas fa-coins text-primary me-1.5"></i>{{ __('Daftar Permintaan Top-Up Saldo') }}</h5>
                    <form action="{{ route('admin.wallet.index') }}" method="GET" class="d-flex flex-wrap gap-1.5 align-items-center">
                        <select name="status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>{{ __('Semua Status') }}</option>
                            <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>{{ __('Menunggu') }}</option>
                            <option value="expired" {{ $statusFilter === 'expired' ? 'selected' : '' }}>{{ __('Kedaluwarsa') }}</option>
                            <option value="paid" {{ $statusFilter === 'paid' ? 'selected' : '' }}>{{ __('Terkonfirmasi') }}</option>
                            <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>{{ __('Dibatalkan') }}</option>
                        </select>
                        <input type="text" name="search" class="form-control form-control-sm" style="width: 170px;" placeholder="{{ __('Cari ref / username...') }}" value="{{ $search }}">
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">{{ __('Filter') }}</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th>{{ __('Referensi') }}</th>
                                <th>{{ __('Pelanggan') }}</th>
                                <th class="text-end">{{ __('Saldo / Transfer') }}</th>
                                <th>{{ __('Status & Sumber') }}</th>
                                <th class="text-end">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topups as $topup)
                                <tr>
                                    <td>
                                        <div class="fw-bold font-monospace small text-body">{{ $topup->topup_ref }}</div>
                                        <small class="text-muted">{{ $topup->created_at->format('d M Y H:i') }}</small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small text-body">{{ $topup->user->full_name ?? $topup->user->username ?? ('User #' . $topup->user_id) }}</div>
                                        <small class="text-muted">{{ '@' . ($topup->user->username ?? '-') }}</small>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <div class="fw-semibold small text-body">Rp {{ number_format($topup->amount, 0, ',', '.') }}</div>
                                        <small class="fw-bold text-primary">{{ __('Bayar:') }} Rp {{ number_format($topup->total_pay, 0, ',', '.') }}</small>
                                    </td>
                                    <td>
                                        @if($topup->status === 'paid')
                                            <span class="badge bg-primary-subtle text-primary rounded-pill">{{ __('Terkonfirmasi') }}</span>
                                            @if($topup->verified_source)
                                                <div class="small text-muted mt-0.5 font-monospace" style="font-size: 0.68rem;">{{ $topup->verified_source }}</div>
                                            @endif
                                            @if($topup->admin_note)
                                                <div class="small text-secondary" style="font-size: 0.72rem;">{{ $topup->admin_note }}</div>
                                            @endif
                                        @elseif($topup->status === 'pending')
                                            <span class="badge bg-warning-subtle text-warning rounded-pill">{{ __('Menunggu') }}</span>
                                        @elseif($topup->status === 'expired')
                                            <span class="badge bg-danger-subtle text-danger rounded-pill">{{ __('Kedaluwarsa') }}</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill">{{ __('Dibatalkan') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if($topup->status !== 'paid')
                                            <div class="d-inline-flex gap-1">
                                                <button type="button"
                                                        class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold btn-open-manual-confirm"
                                                        data-action="{{ route('admin.wallet.topup.confirm', $topup->id) }}"
                                                        data-ref="{{ $topup->topup_ref }}"
                                                        data-user="{{ $topup->user->username ?? $topup->user->full_name ?? ('User #' . $topup->user_id) }}"
                                                        data-amount="Rp {{ number_format($topup->amount, 0, ',', '.') }}"
                                                        data-total="Rp {{ number_format($topup->total_pay, 0, ',', '.') }}"
                                                        data-status="{{ $topup->status }}">
                                                    <i class="fas fa-check me-1"></i>{{ __('Konfirmasi Manual') }}
                                                </button>
                                                @if($topup->status === 'pending')
                                                    <form action="{{ route('admin.wallet.topup.reject', $topup->id) }}" method="POST" class="m-0">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="{{ __('Batalkan') }}">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted small"><i class="fas fa-check-double text-primary"></i></span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4 small">{{ __('Belum ada permintaan top-up saldo.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($topups->hasPages())
                    <div class="mt-3">{{ $topups->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Manual Top-Up (Mendukung Pending, Expired, maupun Cancelled) --}}
<div class="modal fade" id="manualConfirmTopupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-check-circle text-primary me-1.5"></i>{{ __('Konfirmasi Manual Top-Up') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <form method="POST" id="manualConfirmTopupForm">
                @csrf
                <div class="modal-body">
                    <div class="p-3 rounded-4 border mb-3 small" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">{{ __('Referensi') }}</span>
                            <strong class="font-monospace text-body" id="mcRef">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">{{ __('Pelanggan') }}</span>
                            <strong class="text-body" id="mcUser">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">{{ __('Nominal Saldo Masuk') }}</span>
                            <strong class="text-primary" id="mcAmount">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">{{ __('Total Bayar QRIS') }}</span>
                            <strong class="text-body" id="mcTotal">-</strong>
                        </div>
                    </div>

                    <div id="mcOverrideWarning" class="alert alert-warning small rounded-3 py-2 px-3 d-none">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        {{ __('Permintaan ini sebelumnya berstatus kedaluwarsa/dibatalkan. Pastikan dana transfer sudah benar-benar masuk sebelum mengonfirmasi.') }}
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted">{{ __('Catatan Admin (Opsional)') }}</label>
                        <input type="text" name="admin_note" class="form-control" placeholder="{{ __('Misal: Dana QRIS masuk manual / webhook tertunda') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-tonal rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-check me-1"></i>{{ __('Setujui & Tambah Saldo') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('manualConfirmTopupModal');
    const form = document.getElementById('manualConfirmTopupForm');
    const refEl = document.getElementById('mcRef');
    const userEl = document.getElementById('mcUser');
    const amountEl = document.getElementById('mcAmount');
    const totalEl = document.getElementById('mcTotal');
    const warnEl = document.getElementById('mcOverrideWarning');

    document.querySelectorAll('.btn-open-manual-confirm').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!modalEl || !form) return;
            form.action = this.dataset.action;
            if (refEl) refEl.textContent = this.dataset.ref;
            if (userEl) userEl.textContent = this.dataset.user;
            if (amountEl) amountEl.textContent = this.dataset.amount;
            if (totalEl) totalEl.textContent = this.dataset.total;
            if (warnEl) {
                warnEl.classList.toggle('d-none', this.dataset.status === 'pending');
            }
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });
});
</script>
@endsection
