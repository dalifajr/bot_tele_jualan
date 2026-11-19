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
    {{-- Left: Injeksi & Penyesuaian Saldo User --}}
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-1">
                    <i class="fas fa-bolt text-primary me-1.5"></i>{{ __('Injeksi Saldo ke User') }}
                </h6>
                <p class="small text-secondary mb-3">
                    {{ __('Tambahkan (injeksi) atau koreksi/potong saldo dompet belanja user secara instan — cocok untuk bonus, kompensasi, maupun transfer manual.') }}
                </p>

                <form action="{{ route('admin.wallet.manual-credit') }}" method="POST" id="adminInjectWalletForm">
                    @csrf

                    {{-- Mode Injeksi vs Potong --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Jenis Aksi') }}</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="action_type" id="injectActionCredit" value="credit" checked autocomplete="off">
                            <label class="btn btn-sm btn-outline-primary fw-semibold py-2" for="injectActionCredit">
                                <i class="fas fa-plus-circle me-1"></i>{{ __('Injeksi Saldo (+)') }}
                            </label>
                            <input type="radio" class="btn-check" name="action_type" id="injectActionDebit" value="debit" autocomplete="off">
                            <label class="btn btn-sm btn-outline-danger fw-semibold py-2" for="injectActionDebit">
                                <i class="fas fa-minus-circle me-1"></i>{{ __('Potong Saldo (-)') }}
                            </label>
                        </div>
                    </div>

                    {{-- Filter & Pilih User --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Pilih Akun User') }}</label>
                        <div class="input-group input-group-sm mb-1.5">
                            <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="injectUserFilter" class="form-control" placeholder="{{ __('Cari nama, @username, atau email...') }}">
                        </div>
                        <select name="user_id" id="injectUserSelect" class="form-select" required>
                            <option value="">{{ __('-- Pilih Akun User --') }}</option>
                            @foreach($customers as $cust)
                                <option value="{{ $cust->id }}"
                                        data-balance="{{ (int) ($cust->customer_wallet_balance ?? 0) }}"
                                        data-search="{{ strtolower(trim(($cust->full_name ?? '') . ' ' . ($cust->username ?? '') . ' ' . ($cust->email ?? ''))) }}">
                                    {{ $cust->full_name ?: $cust->username }} ({{ '@' . ($cust->username ?: 'id:' . $cust->id) }}) — Rp {{ number_format((int) ($cust->customer_wallet_balance ?? 0), 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                        <div id="injectSelectedBalanceWrap" class="mt-1.5 d-none">
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-semibold" style="font-size: 0.74rem;">
                                <i class="fas fa-wallet me-1"></i>{{ __('Saldo saat ini:') }} <span id="injectSelectedBalance">Rp 0</span>
                            </span>
                        </div>
                    </div>

                    {{-- Preset Nominal Cepat --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Nominal Saldo (Rp)') }}</label>
                        <div class="d-flex flex-wrap gap-1.5 mb-2">
                            @foreach([10000, 25000, 50000, 100000, 250000, 500000] as $preset)
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0.5 fw-semibold btn-inject-preset" style="font-size: 0.74rem;" data-amount="{{ $preset }}">
                                    Rp {{ number_format($preset, 0, ',', '.') }}
                                </button>
                            @endforeach
                        </div>
                        <input type="number" name="amount" id="injectAmountInput" class="form-control fw-bold" min="1000" max="50000000" step="1000" placeholder="50000" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Catatan / Alasan') }}</label>
                        <input type="text" name="note" class="form-control" placeholder="{{ __('Bonus / Kompensasi / Transfer manual BCA') }}" required>
                    </div>

                    <div class="form-check mb-3">
                        <input type="hidden" name="notify_telegram" value="0">
                        <input class="form-check-input" type="checkbox" name="notify_telegram" value="1" id="injectNotifyTg" checked>
                        <label class="form-check-label small text-secondary" for="injectNotifyTg">
                            {{ __('Kirim notifikasi Telegram ke user') }}
                        </label>
                    </div>

                    <button type="submit" id="injectSubmitBtn" class="btn btn-primary w-100 rounded-pill fw-bold">
                        <i class="fas fa-bolt me-1.5"></i>{{ __('Injeksi Saldo Sekarang') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Right: Daftar Permintaan Top-Up & Riwayat Injeksi --}}
    <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm mb-4">
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
                <div class="table-responsive">
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
            </div>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Manual Top-Up (Khusus Status Menunggu) --}}
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

    // 2. Preset nominal injeksi saldo
    const amountInput = document.getElementById('injectAmountInput');
    document.querySelectorAll('.btn-inject-preset').forEach(btn => {
        btn.addEventListener('click', function() {
            if (amountInput) amountInput.value = this.dataset.amount;
        });
    });

    // 3. Toggle gaya tombol Injeksi (+) vs Potong (-)
    const creditRadio = document.getElementById('injectActionCredit');
    const debitRadio = document.getElementById('injectActionDebit');
    const submitBtn = document.getElementById('injectSubmitBtn');
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

    // 4. Filter pencarian user + preview saldo saat ini
    const filterInput = document.getElementById('injectUserFilter');
    const userSelect = document.getElementById('injectUserSelect');
    const balWrap = document.getElementById('injectSelectedBalanceWrap');
    const balText = document.getElementById('injectSelectedBalance');

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
</script>
@endsection
