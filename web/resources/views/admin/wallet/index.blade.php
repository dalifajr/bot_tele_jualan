@extends('layouts.app')

@section('title', __('Top-Up & Injeksi Saldo Dompet'))
@section('page_subtitle', __('Verifikasi top-up QRIS, injeksi saldo langsung ke pengguna & riwayat penyesuaian admin'))

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
                <small class="text-secondary fw-semibold">{{ __('Kedaluwarsa') }}</small>
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
    {{-- Left: Injeksi & Penyesuaian Saldo User (Desktop Only) --}}
    <div class="col-lg-4 d-none d-lg-block">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-1">
                    <i class="fas fa-bolt text-primary me-1.5"></i>{{ __('Injeksi Saldo ke User') }}
                </h6>
                <p class="small text-secondary mb-3">
                    {{ __('Tambahkan (injeksi) atau koreksi/potong saldo dompet belanja user secara instan — cocok untuk bonus, kompensasi, maupun transfer manual.') }}
                </p>

                @include('admin.wallet.partials.inject_form', ['idPrefix' => 'desktop'])
            </div>
        </div>
    </div>

    {{-- Right: Daftar Permintaan Top-Up & Riwayat Injeksi --}}
    <div class="col-12 col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold mb-0 text-body"><i class="fas fa-coins text-primary me-1.5"></i>{{ __('Daftar Permintaan Top-Up Saldo') }}</h5>
                        {{-- Mobile Trigger Button --}}
                        <button type="button" class="btn btn-sm btn-primary rounded-pill d-lg-none fw-semibold" data-bs-toggle="modal" data-bs-target="#injectWalletModal">
                            <i class="fas fa-bolt me-1"></i>{{ __('Injeksi') }}
                        </button>
                    </div>
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

                {{-- Desktop Table View --}}
                <div class="table-responsive d-none d-md-block">
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
                                        @if($topup->status === 'pending')
                                            <div class="d-inline-flex gap-1">
                                                <button type="button"
                                                        class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold btn-open-manual-confirm"
                                                        data-action="{{ route('admin.wallet.topup.confirm', $topup->id) }}"
                                                        data-ref="{{ $topup->topup_ref }}"
                                                        data-user="{{ $topup->user->username ?? $topup->user->full_name ?? ('User #' . $topup->user_id) }}"
                                                        data-amount="Rp {{ number_format($topup->amount, 0, ',', '.') }}"
                                                        data-total="Rp {{ number_format($topup->total_pay, 0, ',', '.') }}">
                                                    <i class="fas fa-check me-1"></i>{{ __('Konfirmasi Manual') }}
                                                </button>
                                                <form action="{{ route('admin.wallet.topup.reject', $topup->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="{{ __('Batalkan') }}">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @elseif($topup->status === 'paid')
                                            <span class="text-muted small"><i class="fas fa-check-double text-primary"></i></span>
                                        @else
                                            <span class="text-muted small">—</span>
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

                {{-- Mobile Stacked Card View (< 768px) --}}
                <div class="d-md-none d-flex flex-column gap-2.5">
                    @forelse($topups as $topup)
                        <div class="m3-mobile-item-card p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-bold font-monospace text-body" style="font-size: 0.88rem;">{{ $topup->topup_ref }}</div>
                                    <div class="small text-secondary">{{ $topup->created_at->format('d M Y, H:i') }}</div>
                                </div>
                                <div>
                                    @if($topup->status === 'paid')
                                        <span class="badge bg-primary-subtle text-primary rounded-pill">{{ __('Terkonfirmasi') }}</span>
                                    @elseif($topup->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning rounded-pill">{{ __('Menunggu') }}</span>
                                    @elseif($topup->status === 'expired')
                                        <span class="badge bg-danger-subtle text-danger rounded-pill">{{ __('Kedaluwarsa') }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">{{ __('Dibatalkan') }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="small text-body mb-2">
                                <span class="text-secondary">{{ __('User:') }}</span>
                                <strong>{{ $topup->user->full_name ?? $topup->user->username ?? ('User #' . $topup->user_id) }}</strong>
                                <span class="text-secondary font-monospace">({{ '@' . ($topup->user->username ?? '-') }})</span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center py-2 px-2.5 rounded-3 mb-2" style="background: var(--surface-container-high);">
                                <div>
                                    <div class="text-secondary" style="font-size: 0.72rem;">{{ __('Nominal Saldo') }}</div>
                                    <div class="fw-bold text-body m3-tabular-nums">Rp {{ number_format($topup->amount, 0, ',', '.') }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="text-secondary" style="font-size: 0.72rem;">{{ __('Bayar QRIS') }}</div>
                                    <div class="fw-bold text-primary m3-tabular-nums">Rp {{ number_format($topup->total_pay, 0, ',', '.') }}</div>
                                </div>
                            </div>

                            @if($topup->status === 'pending')
                                <div class="d-flex gap-2 mt-2 pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                                    <button type="button"
                                            class="btn btn-sm btn-primary rounded-pill flex-grow-1 fw-bold btn-open-manual-confirm py-2"
                                            data-action="{{ route('admin.wallet.topup.confirm', $topup->id) }}"
                                            data-ref="{{ $topup->topup_ref }}"
                                            data-user="{{ $topup->user->username ?? $topup->user->full_name ?? ('User #' . $topup->user_id) }}"
                                            data-amount="Rp {{ number_format($topup->amount, 0, ',', '.') }}"
                                            data-total="Rp {{ number_format($topup->total_pay, 0, ',', '.') }}">
                                        <i class="fas fa-check me-1"></i>{{ __('Konfirmasi Manual') }}
                                    </button>
                                    <form action="{{ route('admin.wallet.topup.reject', $topup->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-2" title="{{ __('Batalkan') }}">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                            @elseif($topup->verified_source || $topup->admin_note)
                                <div class="small text-secondary mt-1">
                                    @if($topup->verified_source)
                                        <span class="badge bg-secondary-subtle text-body font-monospace">{{ $topup->verified_source }}</span>
                                    @endif
                                    @if($topup->admin_note)
                                        <span class="ms-1">{{ $topup->admin_note }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">{{ __('Belum ada permintaan top-up saldo.') }}</div>
                    @endforelse
                </div>

                @if($topups->hasPages())
                    <div class="mt-3">{{ $topups->links() }}</div>
                @endif
            </div>
        </div>

        {{-- Riwayat Injeksi & Penyesuaian Saldo Admin --}}
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 text-body">
                    <i class="fas fa-clock-rotate-left text-primary me-1.5"></i>{{ __('Riwayat Injeksi & Penyesuaian Saldo Terbaru') }}
                </h6>
                {{-- Desktop Table --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th>{{ __('Waktu') }}</th>
                                <th>{{ __('User') }}</th>
                                <th>{{ __('Keterangan') }}</th>
                                <th class="text-end">{{ __('Nominal') }}</th>
                                <th class="text-end">{{ __('Saldo Akhir') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentAdjustments as $adj)
                                <tr>
                                    <td class="small text-secondary text-nowrap">{{ $adj->created_at->format('d M Y H:i') }}</td>
                                    <td class="small fw-semibold text-body">
                                        {{ $adj->user->full_name ?? $adj->user->username ?? ('User #' . $adj->user_id) }}
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ '@' . ($adj->user->username ?? '-') }}</div>
                                    </td>
                                    <td class="small">
                                        <div class="text-body">{{ $adj->description ?: '-' }}</div>
                                        @if($adj->reference)
                                            <span class="font-monospace text-muted" style="font-size: 0.68rem;">{{ $adj->reference }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold small text-nowrap {{ $adj->type === 'credit' ? 'text-success' : 'text-danger' }}">
                                        {{ $adj->type === 'credit' ? '+' : '-' }}Rp {{ number_format($adj->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="text-end small fw-semibold text-body text-nowrap">
                                        Rp {{ number_format($adj->balance_after, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3 small">{{ __('Belum ada riwayat injeksi saldo admin.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Stacked Cards for Adjustments --}}
                <div class="d-md-none d-flex flex-column gap-2">
                    @forelse($recentAdjustments as $adj)
                        <div class="m3-mobile-item-card p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small {{ $adj->type === 'credit' ? 'text-success' : 'text-danger' }} m3-tabular-nums">
                                    {{ $adj->type === 'credit' ? '+' : '-' }}Rp {{ number_format($adj->amount, 0, ',', '.') }}
                                </span>
                                <span class="small text-secondary">{{ $adj->created_at->format('d M Y H:i') }}</span>
                            </div>
                            <div class="small fw-semibold text-body">
                                {{ $adj->user->full_name ?? $adj->user->username ?? ('User #' . $adj->user_id) }}
                                <span class="text-muted">({{ '@' . ($adj->user->username ?? '-') }})</span>
                            </div>
                            <div class="small text-secondary mt-1">
                                {{ $adj->description ?: '-' }}
                            </div>
                            <div class="d-flex justify-content-between align-items-center small text-secondary mt-2 pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                                <span>{{ __('Saldo Akhir:') }}</span>
                                <strong class="text-body m3-tabular-nums">Rp {{ number_format($adj->balance_after, 0, ',', '.') }}</strong>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-3 small">{{ __('Belum ada riwayat injeksi saldo admin.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Injeksi Saldo ke User (M3 Bottom Sheet pada Mobile) --}}
<div class="modal fade" id="injectWalletModal" tabindex="-1" aria-labelledby="injectWalletModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="injectWalletModalLabel">
                    <i class="fas fa-bolt text-primary me-1.5"></i>{{ __('Injeksi Saldo ke User') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-secondary mb-3">
                    {{ __('Tambahkan (injeksi) atau koreksi/potong saldo dompet belanja user secara instan.') }}
                </p>
                @include('admin.wallet.partials.inject_form', ['idPrefix' => 'modal'])
            </div>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Manual Top-Up (Khusus Status Menunggu) --}}
<div class="modal fade" id="manualConfirmTopupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-check-circle text-primary me-1.5"></i>{{ __('Konfirmasi Manual Top-Up') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <form method="POST" id="manualConfirmTopupForm">
                @csrf
                <div class="modal-body p-4">
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
                            <strong class="text-primary m3-tabular-nums" id="mcAmount">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">{{ __('Total Bayar QRIS') }}</span>
                            <strong class="text-body m3-tabular-nums" id="mcTotal">-</strong>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted">{{ __('Catatan Admin (Opsional)') }}</label>
                        <input type="text" name="admin_note" class="form-control" placeholder="{{ __('Misal: Dana QRIS masuk manual / webhook tertunda') }}">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
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
    // 1. Modal Konfirmasi Manual Top-Up Pending
    const modalEl = document.getElementById('manualConfirmTopupModal');
    const form = document.getElementById('manualConfirmTopupForm');
    const refEl = document.getElementById('mcRef');
    const userEl = document.getElementById('mcUser');
    const amountEl = document.getElementById('mcAmount');
    const totalEl = document.getElementById('mcTotal');

    document.querySelectorAll('.btn-open-manual-confirm').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!modalEl || !form) return;
            form.action = this.dataset.action;
            if (refEl) refEl.textContent = this.dataset.ref;
            if (userEl) userEl.textContent = this.dataset.user;
            if (amountEl) amountEl.textContent = this.dataset.amount;
            if (totalEl) totalEl.textContent = this.dataset.total;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });

    // 2. Setup handler untuk form injeksi (baik desktop maupun modal)
    ['desktop', 'modal'].forEach(prefix => {
        const amountInput = document.getElementById(prefix + 'InjectAmountInput');
        document.querySelectorAll('.' + prefix + '-btn-inject-preset').forEach(btn => {
            btn.addEventListener('click', function() {
                if (amountInput) amountInput.value = this.dataset.amount;
            });
        });

        const creditRadio = document.getElementById(prefix + 'InjectActionCredit');
        const debitRadio = document.getElementById(prefix + 'InjectActionDebit');
        const submitBtn = document.getElementById(prefix + 'InjectSubmitBtn');
        const syncActionBtn = () => {
            if (!submitBtn) return;
            if (debitRadio && debitRadio.checked) {
                submitBtn.className = 'btn btn-danger w-100 rounded-pill fw-bold';
                submitBtn.innerHTML = '<i class="fas fa-minus-circle me-1.5"></i>{{ __("Potong Saldo Sekarang") }}';
            } else {
                submitBtn.className = 'btn btn-primary w-100 rounded-pill fw-bold';
                submitBtn.innerHTML = '<i class="fas fa-bolt me-1.5"></i>{{ __("Injeksi Saldo Sekarang") }}';
            }
        };
        if (creditRadio) creditRadio.addEventListener('change', syncActionBtn);
        if (debitRadio) debitRadio.addEventListener('change', syncActionBtn);

        const filterInput = document.getElementById(prefix + 'InjectUserFilter');
        const userSelect = document.getElementById(prefix + 'InjectUserSelect');
        const balWrap = document.getElementById(prefix + 'InjectSelectedBalanceWrap');
        const balText = document.getElementById(prefix + 'InjectSelectedBalance');

        if (userSelect) {
            const allOptions = Array.from(userSelect.options);
            if (filterInput) {
                filterInput.addEventListener('input', function() {
                    const q = this.value.trim().toLowerCase();
                    allOptions.forEach((opt, idx) => {
                        if (idx === 0) return;
                        const hay = opt.dataset.search || opt.textContent.toLowerCase();
                        opt.hidden = q !== '' && !hay.includes(q);
                    });
                });
            }

            userSelect.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                if (opt && opt.value && balWrap && balText) {
                    const bal = Number(opt.dataset.balance || 0);
                    balText.textContent = 'Rp ' + bal.toLocaleString('id-ID');
                    balWrap.classList.remove('d-none');
                } else if (balWrap) {
                    balWrap.classList.add('d-none');
                }
            });
        }
    });

    @if($errors->hasAny(['user_id', 'amount', 'action_type', 'note']))
        if (window.innerWidth < 992) {
            const injModal = document.getElementById('injectWalletModal');
            if (injModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(injModal).show();
            }
        }
    @endif
});
</script>
@endsection
