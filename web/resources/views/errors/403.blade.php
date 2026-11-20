@extends('errors.layout')

@php
    $rawMessage = trim((string) (isset($exception) ? $exception->getMessage() : ''));
    $isFirewallBlock = $rawMessage !== '' && (
        stripos($rawMessage, 'blokir') !== false ||
        stripos($rawMessage, 'banned') !== false ||
        stripos($rawMessage, 'blocked') !== false ||
        stripos($rawMessage, 'firewall') !== false ||
        stripos($rawMessage, 'suspend') !== false
    );
    $clientIp = request()->ip() ?: '-';
    $reqPath = request()->getPathInfo() ?: '/';
    $botUsername = config('telegram.bot_username');
    $diagnosticText = "HTTP 403 | IP: {$clientIp} | Path: {$reqPath} | Reason: " . ($rawMessage ?: 'Forbidden') . ' | Time: ' . now()->toIso8601String();
@endphp

@section('code', '403')
@section('tone_class', 'm3-tone-error')
@section('icon', $isFirewallBlock ? 'fas fa-shield-halved' : 'fas fa-lock')
@section('badge_label', $isFirewallBlock ? __('Akses Diblokir') : __('Akses Ditolak'))
@section('title', $isFirewallBlock ? __('Kamu Diblokir') : __('Akses Ditolak'))
@section('heading', $isFirewallBlock ? __('Akses Kamu Diblokir') : __('Akses Ditolak'))

@section('message')
    @if($isFirewallBlock)
        {{ __('Sistem keamanan & firewall kami membatasi akses dari perangkat atau alamat IP Anda. Jika Anda merasa ini adalah kekeliruan, hubungi Administrator dengan menyertakan informasi diagnostik di bawah.') }}
    @else
        {{ __('Anda tidak memiliki izin yang diperlukan untuk membuka halaman atau menjalankan tindakan ini.') }}
    @endif
@endsection

@section('detail_panel')
    <div class="m3-err-detail" aria-label="{{ __('Informasi Diagnostik Blokir') }}">
        <div class="m3-err-detail-row">
            <span class="m3-err-detail-label">{{ __('Status / Alasan') }}</span>
            <span class="m3-err-detail-val">{{ $rawMessage !== '' ? $rawMessage : ($isFirewallBlock ? __('Kamu diblokir') : __('Akses ditolak oleh server')) }}</span>
        </div>
        <div class="m3-err-detail-row">
            <span class="m3-err-detail-label">{{ __('Alamat IP') }}</span>
            <span class="m3-err-detail-val m3-mono">{{ $clientIp }}</span>
        </div>
        <div class="m3-err-detail-row">
            <span class="m3-err-detail-label">{{ __('Halaman') }}</span>
            <span class="m3-err-detail-val m3-mono">{{ \Illuminate\Support\Str::limit($reqPath, 42) }}</span>
        </div>
    </div>
@endsection

@section('actions')
    @if($botUsername)
        <a href="https://t.me/{{ ltrim($botUsername, '@') }}" target="_blank" rel="noopener noreferrer" class="m3-btn m3-btn-primary">
            <i class="fab fa-telegram-plane"></i>
            <span>{{ __('Hubungi Admin Telegram') }}</span>
        </a>
    @endif
    <button type="button" class="m3-btn m3-btn-tonal" id="btnCopyBlockedDiag" data-diag="{{ $diagnosticText }}">
        <i class="fas fa-copy"></i>
        <span>{{ __('Salin Info Diagnostik') }}</span>
    </button>
    @if(!$isFirewallBlock)
        <a href="{{ url('/') }}" class="m3-btn m3-btn-outlined">
            <i class="fas fa-home"></i>
            <span>{{ __('Kembali ke Beranda') }}</span>
        </a>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var btn = document.getElementById('btnCopyBlockedDiag');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-diag') || '';
            if (!text || !navigator.clipboard) return;
            navigator.clipboard.writeText(text).then(function () {
                var span = btn.querySelector('span');
                var orig = span ? span.textContent : '';
                if (span) span.textContent = @json(__('Info Tersalin!'));
                setTimeout(function () {
                    if (span) span.textContent = orig;
                }, 1800);
            }).catch(function () {});
        });
    })();
</script>
@endpush
