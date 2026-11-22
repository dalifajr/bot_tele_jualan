@php
    $idPrefix = $idPrefix ?? 'desktop';
    $q25 = (int) floor($walletBalance * 0.25);
    $q50 = (int) floor($walletBalance * 0.50);
@endphp

<form action="{{ route('seller.finance.withdraw') }}" method="POST" class="seller-withdraw-form">
    @csrf
    <div class="mb-3">
        <label for="withdrawAmountInput_{{ $idPrefix }}" class="form-label text-muted small fw-semibold mb-1.5">{{ __('Nominal Penarikan (Rp)') }}</label>
        <div class="input-group input-group-lg">
            <span class="input-group-text fw-bold fs-6">Rp</span>
            <input
                type="number"
                id="withdrawAmountInput_{{ $idPrefix }}"
                name="amount"
                data-withdraw-input
                class="form-control fw-bold m3-tabular-nums @error('amount') is-invalid @enderror"
                inputmode="numeric"
                placeholder="100000"
                min="10000"
                max="{{ $walletBalance }}"
                value="{{ old('amount') }}"
                required
                {{ $walletBalance < 10000 ? 'disabled' : '' }}
            >
        </div>
        @error('amount')
            <div class="invalid-feedback d-block small mt-1">{{ $message }}</div>
        @enderror

        {{-- Quick Amount Chips --}}
        <div class="d-flex flex-wrap gap-1.5 mt-2">
            <button type="button" class="btn btn-sm m3-quick-amount-chip" data-withdraw-amount="{{ $q25 }}" {{ $q25 < 10000 ? 'disabled' : '' }}>
                25%
            </button>
            <button type="button" class="btn btn-sm m3-quick-amount-chip" data-withdraw-amount="{{ $q50 }}" {{ $q50 < 10000 ? 'disabled' : '' }}>
                50%
            </button>
            <button type="button" class="btn btn-sm m3-quick-amount-chip" data-withdraw-amount="{{ $walletBalance }}" {{ $walletBalance < 10000 ? 'disabled' : '' }}>
                {{ __('Tarik Semua') }} (Rp {{ number_format($walletBalance, 0, ',', '.') }})
            </button>
        </div>
    </div>

    @if($bankAccounts->isEmpty())
        <div class="p-3 rounded-4 mb-3 small d-flex align-items-start gap-2.5" style="background: var(--cm-sys-color-status-warning-container); color: var(--cm-sys-color-status-warning); border: 1px solid rgba(180, 83, 9, 0.2);">
            <i class="fas fa-exclamation-triangle mt-0.5 flex-shrink-0"></i>
            <div>
                {{ __('Anda belum menyimpan rekening bank. Silakan simpan rekening tujuan terlebih dahulu di menu') }}
                <a href="{{ route('seller.bank-accounts.index') }}" class="fw-bold text-decoration-underline" style="color: inherit;">{{ __('Konfigurasi Rekening') }}</a>.
            </div>
        </div>
    @else
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1.5">
                <label for="withdrawBankAccount_{{ $idPrefix }}" class="form-label text-muted small fw-semibold mb-0">{{ __('Pilih Rekening Tujuan Payout') }}</label>
                <a href="{{ route('seller.bank-accounts.index') }}" class="small fw-semibold text-decoration-none">
                    <i class="fas fa-plus me-1"></i>{{ __('Tambah Rekening') }}
                </a>
            </div>
            <select id="withdrawBankAccount_{{ $idPrefix }}" name="bank_account_id" class="form-select @error('bank_account_id') is-invalid @enderror" required>
                <option value="" disabled {{ old('bank_account_id') ? '' : 'selected' }}>{{ __('-- Pilih Rekening Payout --') }}</option>
                @foreach($bankAccounts as $acc)
                    <option value="{{ $acc->id }}" {{ (string) old('bank_account_id') === (string) $acc->id ? 'selected' : '' }}>
                        {{ strtoupper($acc->bank_name) }} — {{ $acc->account_number }} (a.n. {{ $acc->account_holder }})
                    </option>
                @endforeach
            </select>
            @error('bank_account_id')
                <div class="invalid-feedback d-block small mt-1">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <button type="submit" class="btn btn-primary rounded-pill w-100 py-2.5 fw-bold d-inline-flex align-items-center justify-content-center gap-2" style="min-height: 46px;" {{ !$canWithdraw ? 'disabled' : '' }}>
        <i class="fas fa-paper-plane"></i>
        <span>{{ __('Ajukan Penarikan Dana') }}</span>
    </button>

    @if($walletBalance < 10000)
        <div class="mt-3 p-2.5 px-3 rounded-4 small d-flex align-items-center gap-2" style="background: var(--error-container); color: var(--on-error-container);">
            <i class="fas fa-circle-info flex-shrink-0"></i>
            <span>{{ __('Saldo Anda belum mencukupi minimal penarikan Rp 10.000.') }}</span>
        </div>
    @elseif($bankAccounts->isEmpty())
        <div class="mt-3 p-2.5 px-3 rounded-4 small d-flex align-items-center gap-2" style="background: var(--error-container); color: var(--on-error-container);">
            <i class="fas fa-circle-info flex-shrink-0"></i>
            <span>{{ __('Hubungkan rekening bank payout terlebih dahulu.') }}</span>
        </div>
    @endif
</form>
