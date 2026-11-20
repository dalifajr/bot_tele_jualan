@extends('layouts.app')

@section('title', __('Akun Ditangguhkan'))
@section('no_sidebar', true)

@section('content')
<div class="row justify-content-center align-items-center py-4" style="min-height: 72vh;">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="card border" style="border-radius: 28px; background: var(--surface-container-lowest); border-color: var(--outline-variant) !important;">
            <div class="card-body p-4 p-sm-5 text-center">
                {{-- Semantic Error Squircle Emblem (76x76px, 24px radius) --}}
                <div class="d-inline-flex align-items-center justify-content-center mb-3"
                     aria-hidden="true"
                     style="width: 76px; height: 76px; border-radius: 24px; background: var(--error-container, #ffdad6); color: var(--on-error-container, #410002); font-size: 2rem;">
                    <i class="fas fa-user-slash"></i>
                </div>

                {{-- Status Pill Badge --}}
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill fw-semibold"
                          style="background: var(--error-container, #ffdad6); color: var(--on-error-container, #410002); font-size: 0.76rem; letter-spacing: 0.04em; text-transform: uppercase;">
                        <i class="fas fa-ban"></i>
                        <span>{{ __('AKUN DITANGGUHKAN') }}</span>
                    </span>
                </div>

                <h1 class="h3 fw-bold mb-2" style="letter-spacing: -0.02em; color: var(--on-surface); text-wrap: balance;">
                    {{ __('Akun Anda Ditangguhkan') }}
                </h1>

                <p class="mb-4 mx-auto" style="color: var(--on-surface-variant); max-width: 42ch; font-size: 0.92rem; text-wrap: pretty;">
                    {{ __('Mohon maaf, akses akun Anda ke platform ini telah ditangguhkan sementara waktu oleh administrator kami.') }}
                </p>

                @if(Auth::check() && Auth::user()->suspension_reason)
                <div class="p-3 mb-3 text-start"
                     style="border-radius: 14px; background: var(--error-container, #ffdad6); color: var(--on-error-container, #410002); border: 1px solid var(--outline-variant);">
                    <div class="fw-semibold mb-1 small d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>{{ __('Alasan Penangguhan:') }}</span>
                    </div>
                    <div class="small" style="line-height: 1.55;">{{ Auth::user()->suspension_reason }}</div>
                </div>
                @endif

                <div class="p-3 mb-4 text-start"
                     style="border-radius: 14px; background: var(--surface-container); color: var(--on-surface); border: 1px solid var(--outline-variant);">
                    <div class="d-flex align-items-start gap-2 small" style="line-height: 1.55;">
                        <i class="fas fa-circle-info mt-1 flex-shrink-0" style="color: var(--cm-sys-color-status-warning, #9a5b00);"></i>
                        <div>
                            <strong>{{ __('Penting:') }}</strong>
                            <span style="color: var(--on-surface-variant);">{{ __('Anda tidak dapat melakukan transaksi, menghubungi bot Telegram, maupun mengakses katalog.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="https://t.me/{{ ltrim((string) config('telegram.bot_username', 'Admin'), '@') }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-primary rounded-pill py-2 fw-semibold">
                        <i class="fab fa-telegram-plane me-2"></i>{{ __('Hubungi Admin') }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-light rounded-pill py-2 w-100 fw-semibold">
                            <i class="fas fa-sign-out-alt me-2"></i>{{ __('Keluar') }}
                        </button>
                    </form>
                </div>

                <div class="mt-4 pt-3 border-top" style="border-color: var(--outline-variant) !important;">
                    <small style="font-size: 0.76rem; color: var(--on-surface-variant);">
                        {{ config('app.name', 'Digital Store') }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
