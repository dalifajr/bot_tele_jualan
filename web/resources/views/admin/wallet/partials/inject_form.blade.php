@php
    $idPrefix = $idPrefix ?? 'desktop';
@endphp

<form action="{{ route('admin.wallet.manual-credit') }}" method="POST" id="adminInjectWalletForm_{{ $idPrefix }}" class="admin-inject-form">
    @csrf

    {{-- Mode Injeksi vs Potong --}}
    <div class="mb-3">
        <label class="form-label small fw-semibold text-muted mb-1.5">{{ __('Jenis Aksi') }}</label>
        <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check inject-action-radio" name="action_type" id="injectActionCredit_{{ $idPrefix }}" value="credit" checked autocomplete="off">
            <label class="btn btn-sm btn-outline-primary fw-semibold py-2" for="injectActionCredit_{{ $idPrefix }}">
                <i class="fas fa-plus-circle me-1"></i>{{ __('Injeksi Saldo (+)') }}
            </label>
            <input type="radio" class="btn-check inject-action-radio" name="action_type" id="injectActionDebit_{{ $idPrefix }}" value="debit" autocomplete="off">
            <label class="btn btn-sm btn-outline-danger fw-semibold py-2" for="injectActionDebit_{{ $idPrefix }}">
                <i class="fas fa-minus-circle me-1"></i>{{ __('Potong Saldo (-)') }}
            </label>
        </div>
    </div>

    {{-- Filter & Pilih User --}}
    <div class="mb-3">
        <label class="form-label small fw-semibold text-muted mb-1.5">{{ __('Pilih Akun User') }}</label>
        <div class="input-group input-group-sm mb-1.5">
            <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
            <input type="text" class="form-control inject-user-filter" placeholder="{{ __('Cari nama, @username, atau email...') }}">
        </div>
        <select name="user_id" class="form-select inject-user-select" required>
            <option value="">{{ __('-- Pilih Akun User --') }}</option>
            @foreach($customers as $cust)
                <option value="{{ $cust->id }}"
                        data-balance="{{ (int) ($cust->customer_wallet_balance ?? 0) }}"
                        data-search="{{ strtolower(trim(($cust->full_name ?? '') . ' ' . ($cust->username ?? '') . ' ' . ($cust->email ?? ''))) }}">
                    {{ $cust->full_name ?: $cust->username }} ({{ '@' . ($cust->username ?: 'id:' . $cust->id) }}) — Rp {{ number_format((int) ($cust->customer_wallet_balance ?? 0), 0, ',', '.') }}
                </option>
            @endforeach
        </select>
        <div class="mt-1.5 d-none inject-selected-balance-wrap">
            <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-semibold" style="font-size: 0.74rem;">
                <i class="fas fa-wallet me-1"></i>{{ __('Saldo saat ini:') }} <span class="inject-selected-balance">Rp 0</span>
            </span>
        </div>
    </div>

    {{-- Preset Nominal Cepat --}}
    <div class="mb-3">
        <label class="form-label small fw-semibold text-muted mb-1.5">{{ __('Nominal Saldo (Rp)') }}</label>
        <div class="d-flex flex-wrap gap-1.5 mb-2">
            @foreach([10000, 25000, 50000, 100000, 250000, 500000] as $preset)
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0.5 fw-semibold btn-inject-preset" style="font-size: 0.74rem;" data-amount="{{ $preset }}">
                    Rp {{ number_format($preset, 0, ',', '.') }}
                </button>
            @endforeach
        </div>
        <input type="number" name="amount" class="form-control fw-bold m3-tabular-nums inject-amount-input" min="1000" max="50000000" step="1000" placeholder="50000" inputmode="numeric" required>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold text-muted mb-1.5">{{ __('Catatan / Alasan') }}</label>
        <input type="text" name="note" class="form-control" placeholder="{{ __('Bonus / Kompensasi / Transfer manual BCA') }}" required>
    </div>

    <div class="form-check mb-3">
        <input type="hidden" name="notify_telegram" value="0">
        <input class="form-check-input" type="checkbox" name="notify_telegram" value="1" id="injectNotifyTg_{{ $idPrefix }}" checked>
        <label class="form-check-label small text-secondary" for="injectNotifyTg_{{ $idPrefix }}">
            {{ __('Kirim notifikasi Telegram ke user') }}
        </label>
    </div>

    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold inject-submit-btn py-2.5 d-inline-flex align-items-center justify-content-center gap-2" style="min-height: 46px;">
        <i class="fas fa-bolt"></i>
        <span class="inject-submit-text">{{ __('Injeksi Saldo Sekarang') }}</span>
    </button>
</form>
