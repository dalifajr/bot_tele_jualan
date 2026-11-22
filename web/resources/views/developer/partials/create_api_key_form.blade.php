@php
    $idPrefix = $idPrefix ?? 'desktop';
@endphp

<form action="{{ route('developer.api.keys.store') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="apiKeyName_{{ $idPrefix }}" class="form-label small fw-semibold text-muted mb-1.5">{{ __('Nama Integrasi / Label') }}</label>
        <input type="text" id="apiKeyName_{{ $idPrefix }}" name="name" class="form-control" placeholder="{{ __('Contoh: Bot Telegram Reseller') }}" required maxlength="100" value="{{ old('name') }}">
    </div>
    <div class="mb-3">
        <label for="apiKeyAllowedIps_{{ $idPrefix }}" class="form-label small fw-semibold text-muted mb-1.5">{{ __('IP Whitelist (Opsional)') }}</label>
        <textarea id="apiKeyAllowedIps_{{ $idPrefix }}" name="allowed_ips" class="form-control font-monospace small" rows="2" placeholder="103.10.20.30, 103.10.21.0/24">{{ old('allowed_ips') }}</textarea>
        <div class="form-text small">{{ __('Pisahkan dengan koma atau baris baru. Kosongkan untuk mengizinkan semua IP.') }}</div>
    </div>
    <div class="mb-3">
        <label for="apiKeyWebhookUrl_{{ $idPrefix }}" class="form-label small fw-semibold text-muted mb-1.5">{{ __('Webhook URL (Opsional)') }}</label>
        <input type="url" id="apiKeyWebhookUrl_{{ $idPrefix }}" name="webhook_url" class="form-control font-monospace small" placeholder="https://bot-anda.com/webhook/order" value="{{ old('webhook_url') }}">
        <div class="form-text small">{{ __('Menerima POST JSON bertanda tangan HMAC-SHA256 saat pesanan selesai.') }}</div>
    </div>
    <div class="mb-3">
        <label for="apiKeyRateLimit_{{ $idPrefix }}" class="form-label small fw-semibold text-muted mb-1.5">{{ __('Rate Limit (Request / Menit)') }}</label>
        <input type="number" id="apiKeyRateLimit_{{ $idPrefix }}" name="rate_limit_per_minute" class="form-control" min="5" max="{{ $isAdmin ? 300 : 120 }}" value="{{ old('rate_limit_per_minute', 60) }}">
    </div>
    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold py-2.5 d-inline-flex align-items-center justify-content-center gap-2" style="min-height: 46px;">
        <i class="fas fa-key"></i>
        <span>{{ __('Generate API Key') }}</span>
    </button>
</form>
