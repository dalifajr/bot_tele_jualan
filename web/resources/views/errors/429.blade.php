@extends('errors.layout')

@php
    $retryAfter = 30;
    if (isset($exception) && method_exists($exception, 'getHeaders')) {
        $headers = $exception->getHeaders();
        if (!empty($headers['Retry-After']) && is_numeric($headers['Retry-After'])) {
            $retryAfter = max(5, min(300, (int) $headers['Retry-After']));
        }
    }
@endphp

@section('code', '429')
@section('tone_class', 'm3-tone-warning')
@section('icon', 'fas fa-gauge-high')
@section('badge_label', __('Batas Permintaan'))
@section('title', __('Terlalu Banyak Permintaan'))
@section('heading', __('Terlalu Banyak Permintaan'))

@section('message')
    {{ __('Sistem mendeteksi terlalu banyak permintaan dalam waktu singkat dari koneksi Anda. Tunggu beberapa detik sebelum mencoba lagi.') }}
@endsection

@section('detail_panel')
    <div class="m3-err-detail">
        <div class="m3-err-detail-row">
            <span class="m3-err-detail-label">{{ __('Coba lagi dalam') }}</span>
            <span class="m3-err-detail-val m3-mono"><span id="m3RetryCountdown">{{ $retryAfter }}</span> {{ __('detik') }}</span>
        </div>
    </div>
@endsection

@section('actions')
    <button type="button" class="m3-btn m3-btn-primary" id="m3RetryBtn" disabled onclick="window.location.reload();">
        <i class="fas fa-rotate-right"></i>
        <span id="m3RetryBtnLabel">{{ __('Tunggu...') }}</span>
    </button>
    <a href="{{ url('/') }}" class="m3-btn m3-btn-tonal">
        <i class="fas fa-home"></i>
        <span>{{ __('Kembali ke Beranda') }}</span>
    </a>
@endsection

@push('scripts')
<script>
    (function () {
        var remaining = {{ $retryAfter }};
        var countEl = document.getElementById('m3RetryCountdown');
        var btn = document.getElementById('m3RetryBtn');
        var label = document.getElementById('m3RetryBtnLabel');
        var readyText = @json(__('Coba Muat Ulang'));

        var timer = setInterval(function () {
            remaining--;
            if (countEl) countEl.textContent = String(Math.max(0, remaining));
            if (remaining <= 0) {
                clearInterval(timer);
                if (btn) btn.disabled = false;
                if (label) label.textContent = readyText;
            }
        }, 1000);
    })();
</script>
@endpush
