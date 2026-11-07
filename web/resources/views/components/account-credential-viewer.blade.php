@props(['rawText' => '', 'maxHeight' => '300px', 'showCopyAll' => true])

@php
    $cleanedRawText = trim($rawText);
    $renderedHtml = \App\Services\TwoFactorService::renderWith2fa($cleanedRawText, true);
    $has2fa = \App\Services\TwoFactorService::has2faSecret($cleanedRawText);
@endphp

<div class="account-credential-viewer position-relative m3-tonal-item overflow-hidden">
    <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: var(--surface-container-high); border-color: var(--outline-color) !important;">
        <div class="d-flex align-items-center gap-2">
            <span class="small fw-semibold font-monospace" style="color: var(--on-surface-variant); font-size: 0.75rem;">
                <i class="fas fa-terminal me-1 text-primary"></i>KREDENSIAL AKUN
            </span>
            @if($has2fa)
                <span class="badge bg-success-subtle text-success small py-1 px-2 rounded-pill" style="font-size: 0.7rem;">
                    <i class="fas fa-shield-alt me-1"></i>{{ __('2FA Terdeteksi & Live') }}
                </span>
            @endif
        </div>
        @if($showCopyAll)
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-1 px-3 d-inline-flex align-items-center gap-1.5 fw-semibold" style="font-size: 0.75rem;" onclick="copyFullCredential(this, {{ json_encode($cleanedRawText) }})">
            <i class="fas fa-copy"></i>
            <span>{{ __('Salin Seluruh Data') }}</span>
        </button>
        @endif
    </div>
    <div class="p-3 text-break" style="max-height: {{ $maxHeight }}; overflow-y: auto; font-family: 'JetBrains Mono', SFMono-Regular, Menlo, Monaco, Consolas, monospace; white-space: pre-wrap; font-size: 0.86rem; line-height: 1.65; color: var(--on-surface);">{!! trim($renderedHtml) !!}</div>
</div>

