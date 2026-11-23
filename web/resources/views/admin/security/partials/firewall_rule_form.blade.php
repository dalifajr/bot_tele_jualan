@php
    $idPrefix = $idPrefix ?? 'desktop';
@endphp

<form action="{{ route('admin.security.firewall.store') }}" method="POST" id="firewallRuleForm_{{ $idPrefix }}">
    @csrf
    <input type="hidden" name="active_tab" value="firewall">
    <div class="mb-3">
        <label for="fwType_{{ $idPrefix }}" class="form-label small fw-semibold text-muted">{{ __('Tipe Target') }}</label>
        <select name="type" id="fwType_{{ $idPrefix }}" class="form-select" required>
            <option value="ip" {{ old('type', 'ip') === 'ip' ? 'selected' : '' }}>{{ __('Alamat IP Tunggal (IPv4/IPv6)') }}</option>
            <option value="subnet" {{ old('type') === 'subnet' ? 'selected' : '' }}>{{ __('Rentang Subnet CIDR (contoh: 103.10.20.0/24)') }}</option>
            <option value="telegram_id" {{ old('type') === 'telegram_id' ? 'selected' : '' }}>{{ __('ID Telegram') }}</option>
        </select>
    </div>
    <div class="mb-3">
        <label for="fwValue_{{ $idPrefix }}" class="form-label small fw-semibold text-muted">{{ __('Nilai Target') }}</label>
        <input type="text" name="value" id="fwValue_{{ $idPrefix }}" class="form-control font-monospace" placeholder="192.168.1.50 / 103.10.20.0/24" value="{{ old('value') }}" required>
    </div>
    <div class="mb-3">
        <label for="fwDuration_{{ $idPrefix }}" class="form-label small fw-semibold text-muted">{{ __('Durasi Blokir') }}</label>
        <select name="duration_days" id="fwDuration_{{ $idPrefix }}" class="form-select">
            <option value="1" {{ old('duration_days') == '1' ? 'selected' : '' }}>{{ __('1 Hari') }}</option>
            <option value="7" {{ old('duration_days', '7') == '7' ? 'selected' : '' }}>{{ __('7 Hari') }}</option>
            <option value="30" {{ old('duration_days') == '30' ? 'selected' : '' }}>{{ __('30 Hari') }}</option>
            <option value="365" {{ old('duration_days') == '365' ? 'selected' : '' }}>{{ __('1 Tahun') }}</option>
            <option value="0" {{ old('duration_days') === '0' ? 'selected' : '' }}>{{ __('Permanen') }}</option>
        </select>
    </div>
    <div class="mb-3">
        <label for="fwReason_{{ $idPrefix }}" class="form-label small fw-semibold text-muted">{{ __('Alasan') }}</label>
        <input type="text" name="reason" id="fwReason_{{ $idPrefix }}" class="form-control" placeholder="{{ __('Brute-force / Spam / Scraper') }}" value="{{ old('reason') }}">
    </div>
    <button type="submit" class="btn btn-danger w-100 rounded-pill fw-bold py-2.5 d-inline-flex align-items-center justify-content-center gap-2" style="min-height: 46px;">
        <i class="fas fa-ban"></i>
        <span>{{ __('Terapkan Blokir Firewall') }}</span>
    </button>
</form>
