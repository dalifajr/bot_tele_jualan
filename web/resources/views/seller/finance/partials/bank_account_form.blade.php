@php
    $idPrefix = $idPrefix ?? 'desktop';
@endphp

<form action="{{ route('seller.bank-accounts.store') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="bankNameInput_{{ $idPrefix }}" class="form-label text-muted small fw-semibold mb-1.5">{{ __('Nama Bank / E-Wallet Tujuan') }}</label>
        <input
            id="bankNameInput_{{ $idPrefix }}"
            type="text"
            name="bank_name"
            class="form-control @error('bank_name') is-invalid @enderror"
            placeholder="{{ __('Contoh: BCA, Mandiri, DANA, GoPay') }}"
            value="{{ old('bank_name') }}"
            required
        >
        @error('bank_name')
            <div class="invalid-feedback small">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="accountNumberInput_{{ $idPrefix }}" class="form-label text-muted small fw-semibold mb-1.5">{{ __('Nomor Rekening / Akun E-Wallet') }}</label>
        <input
            id="accountNumberInput_{{ $idPrefix }}"
            type="text"
            name="account_number"
            class="form-control font-monospace @error('account_number') is-invalid @enderror"
            inputmode="numeric"
            placeholder="{{ __('Contoh: 8012345678') }}"
            value="{{ old('account_number') }}"
            required
        >
        @error('account_number')
            <div class="invalid-feedback small">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="accountHolderInput_{{ $idPrefix }}" class="form-label text-muted small fw-semibold mb-1.5">{{ __('Nama Pemilik Rekening (Sesuai Bank)') }}</label>
        <input
            id="accountHolderInput_{{ $idPrefix }}"
            type="text"
            name="account_holder"
            class="form-control @error('account_holder') is-invalid @enderror"
            placeholder="{{ __('Contoh: Dzulfikri Alifajri') }}"
            value="{{ old('account_holder') }}"
            required
        >
        @error('account_holder')
            <div class="invalid-feedback small">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill d-inline-flex align-items-center justify-content-center gap-2" style="min-height: 46px;">
        <i class="fas fa-save"></i>
        <span>{{ __('Simpan Rekening') }}</span>
    </button>
</form>
