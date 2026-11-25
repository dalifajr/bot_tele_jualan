@extends('layouts.app')

@section('title', __('Sistem Dalam Pemeliharaan'))
@section('page_subtitle', __('Pemeliharaan'))
@section('no_sidebar', true)

@section('content')
<div class="row justify-content-center align-items-center py-4" style="min-height: 72vh;">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="card border" style="border-radius: 28px; background: var(--surface-container-lowest); border-color: var(--outline-variant) !important;">
            <div class="card-body p-4 p-sm-5 text-center">
                {{-- Semantic Warning Squircle Emblem (76x76px, 24px radius) --}}
                <div class="d-inline-flex align-items-center justify-content-center mb-3"
                     aria-hidden="true"
                     style="width: 76px; height: 76px; border-radius: 24px; background: var(--cm-sys-color-status-warning-container, #ffdf9e); color: var(--cm-sys-color-status-warning, #9a5b00); font-size: 2rem;">
                    <i class="fas fa-screwdriver-wrench"></i>
                </div>

                {{-- Status Pill Badge --}}
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill fw-semibold"
                          style="background: var(--cm-sys-color-status-warning-container, #ffdf9e); color: var(--cm-sys-color-status-warning, #9a5b00); font-size: 0.76rem; letter-spacing: 0.04em; text-transform: uppercase;">
                        <i class="fas fa-clock"></i>
                        <span>{{ __('MODE MAINTENANCE AKTIF') }}</span>
                    </span>
                </div>

                {{-- Heading --}}
                <h1 class="h3 fw-bold mb-3" style="letter-spacing: -0.02em; color: var(--on-surface); text-wrap: balance;">
                    {{ __('Sistem Sedang Dalam Pemeliharaan') }}
                </h1>

                {{-- Concentric Tonal Maintenance Message Panel (14px radius) --}}
                <div class="p-3 mb-4 text-start"
                     style="border-radius: 14px; background: var(--surface-container); border: 1px solid var(--outline-variant);">
                    <div class="d-flex align-items-start gap-2" style="color: var(--on-surface);">
                        <i class="fas fa-circle-info mt-1 flex-shrink-0" style="color: var(--primary);"></i>
                        <div class="small fw-medium" style="line-height: 1.6; text-wrap: pretty;">
                            {!! nl2br(e(__($maintenanceMessage ?? 'Website sedang dalam pemeliharaan sistem (Maintenance). Silakan kembali beberapa saat lagi.'))) !!}
                        </div>
                    </div>
                </div>

                {{-- User Session Pill --}}
                @if(Auth::check())
                <div class="d-inline-flex align-items-center justify-content-center gap-2 mb-4 px-3 py-2 rounded-pill small"
                     style="background: var(--surface-container); color: var(--on-surface-variant); border: 1px solid var(--outline-variant);">
                    <i class="fas fa-user-circle" style="color: var(--primary);"></i>
                    <span>{{ __('Masuk sebagai:') }} <strong style="color: var(--on-surface);">{{ Auth::user()->full_name ?? Auth::user()->username }}</strong> ({{ ucfirst(Auth::user()->role) }})</span>
                </div>
                @endif

                {{-- Expressive M3 Pill Actions --}}
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary rounded-pill py-2 fw-semibold" onclick="window.location.reload();">
                        <i class="fas fa-rotate-right me-2"></i>{{ __('Muat Ulang Halaman') }}
                    </button>

                    @if(config('telegram.bot_username'))
                    <a href="https://t.me/{{ ltrim((string) config('telegram.bot_username'), '@') }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-light rounded-pill py-2 fw-semibold">
                        <i class="fab fa-telegram-plane me-2"></i>{{ __('Buka Bot Telegram') }}
                    </a>
                    @endif

                    @if(session()->has('admin_impersonator_id'))
                    <form method="POST" action="{{ route('admin.users.stop-impersonating') }}" class="m-0 mt-1">
                        @csrf
                        <button type="submit" class="btn btn-warning rounded-pill py-2 w-100 fw-semibold">
                            <i class="fas fa-user-shield me-2"></i>{{ __('Kembali ke Akun Admin') }}
                        </button>
                    </form>
                    @endif

                    @if(Auth::check())
                    <form method="POST" action="{{ route('logout') }}" class="m-0 mt-1">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger rounded-pill py-2 w-100 fw-semibold">
                            <i class="fas fa-sign-out-alt me-2"></i>{{ __('Keluar (Logout)') }}
                        </button>
                    </form>
                    @else
                    <a href="{{ route('login') }}" class="btn btn-link text-decoration-none small mt-1" style="color: var(--on-surface-variant);">
                        <i class="fas fa-lock me-1"></i>{{ __('Login Administrator') }}
                    </a>
                    @endif
                </div>

                {{-- Footer Note --}}
                <div class="mt-4 pt-3 border-top" style="border-color: var(--outline-variant) !important;">
                    <small style="font-size: 0.76rem; color: var(--on-surface-variant);">
                        {{ config('app.name', 'Dzulfikrialifajri Store') }} &bull; {{ __('Terima kasih atas kesabaran Anda.') }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
