@php
    $idPrefix = $idPrefix ?? 'desktop';
@endphp

<form action="{{ route('wallet.topup.store') }}" method="POST" id="walletTopupForm_{{ $idPrefix }}">
    @csrf
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach([25000, 50000, 100000, 250000, 500000] as $preset)
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold wallet-preset-btn" data-amount="{{ $preset }}" data-target="#walletTopupAmount_{{ $idPrefix }}">
                Rp {{ number_format($preset, 0, ',', '.') }}
            </button>
        @endforeach
    </div>

    <div class="mb-3">
        <label for="walletTopupAmount_{{ $idPrefix }}" class="form-label small fw-semibold text-muted">{{ __('Nominal Top-Up (Rp)') }}</label>
        <input type="number" name="amount" id="walletTopupAmount_{{ $idPrefix }}" class="form-control form-control-lg fw-bold m3-tabular-nums" min="10000" max="50000000" step="1000" inputmode="numeric" value="{{ old('amount', 50000) }}" required>
        <small class="text-muted">{{ __('Minimal top-up Rp 10.000') }}</small>
    </div>

    <button type="submit" class="btn btn-primary w-100 rounded-pill py-2.5 fw-bold d-inline-flex align-items-center justify-content-center gap-2" style="min-height: 46px;">
        <i class="fas fa-bolt"></i>
        <span>{{ __('Buat Kode QRIS Top-Up') }}</span>
    </button>
</form>
