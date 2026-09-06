@props(['rawText' => '', 'maxHeight' => '300px', 'showCopyAll' => true])

@php
    $renderedHtml = \App\Services\TwoFactorService::renderWith2fa($rawText, true);
    $has2fa = \App\Services\TwoFactorService::has2faSecret($rawText);
@endphp

<div class="account-credential-viewer position-relative">
    <div class="bg-body-secondary rounded-3 p-3 text-break border" style="max-height: {{ $maxHeight }}; overflow-y: auto; font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; white-space: pre-wrap; font-size: 0.88rem; line-height: 1.6;">
        {!! $renderedHtml !!}
    </div>
    @if($showCopyAll)
    <div class="d-flex justify-content-between align-items-center mt-2 px-1">
        <div>
            @if($has2fa)
                <span class="badge bg-success-subtle text-success border border-success-subtle small py-1 px-2 rounded-pill" style="font-size: 0.75rem;">
                    <i class="fas fa-shield-alt me-1"></i>{{ __('2FA Terdeteksi & Live') }}
                </span>
            @endif
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-3 d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;" onclick="copyFullCredential(this, {{ json_encode($rawText) }})">
            <i class="fas fa-copy"></i>
            <span>{{ __('Salin Seluruh Data') }}</span>
        </button>
    </div>
    @endif
</div>
