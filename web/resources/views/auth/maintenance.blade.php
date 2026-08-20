@extends('layouts.app')

@section('title', __('Sistem Dalam Pemeliharaan'))
@section('page_subtitle', __('Pemeliharaan'))
@section('no_sidebar', true)

@section('content')
<div class="row justify-content-center align-items-center py-4" style="min-height: 70vh;">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="card border-0 shadow-lg" style="border-radius: 24px; overflow: hidden; backdrop-filter: blur(10px);">
            {{-- Top colored accent bar --}}
            <div style="height: 6px; background: linear-gradient(90deg, #ffc107, #fd7e14, #dc3545);"></div>

            <div class="card-body p-4 p-sm-5 text-center">
                {{-- Icon Graphic --}}
                <div class="mb-4 position-relative d-inline-block">
                    <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center mx-auto shadow-sm" style="width: 100px; height: 100px;">
                        <i class="fas fa-tools" style="font-size: 3rem;"></i>
                    </div>
                    <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-danger border border-2 border-white p-2">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                </div>

                {{-- Status Badge --}}
                <div class="mb-3">
                    <span class="badge bg-warning-subtle text-warning-emphasis px-3 py-2 rounded-pill fw-bold border border-warning-subtle" style="font-size: 0.85rem; letter-spacing: 0.05em;">
                        <i class="fas fa-clock me-1"></i> {{ __('MODE MAINTENANCE AKTIF') }}
                    </span>
                </div>

                {{-- Heading --}}
                <h3 class="fw-bold mb-3 text-body" style="letter-spacing: -0.02em;">{{ __('Sistem Sedang Dalam Pemeliharaan') }}</h3>

                {{-- Custom Maintenance Message Box --}}
                <div class="p-3 mb-4 rounded-4 text-start" style="background: var(--bs-tertiary-bg); border: 1px dashed var(--bs-border-color);">
                    <div class="d-flex align-items-start gap-2 text-body">
                        <i class="fas fa-info-circle text-primary mt-1 flex-shrink-0"></i>
                        <div class="small fw-medium" style="line-height: 1.6;">
                            {!! nl2br(e($maintenanceMessage ?? 'Website sedang dalam pemeliharaan sistem (Maintenance). Silakan kembali beberapa saat lagi.')) !!}
                        </div>
                    </div>
                </div>

                {{-- User Session Info --}}
                @if(Auth::check())
                <div class="d-flex align-items-center justify-content-center gap-2 mb-4 p-2 rounded-pill bg-body border small text-muted">
                    <i class="fas fa-user-circle text-secondary"></i>
                    <span>{{ __('Masuk sebagai:') }} <strong>{{ Auth::user()->full_name ?? Auth::user()->username }}</strong> ({{ ucfirst(Auth::user()->role) }})</span>
                </div>
                @endif

                {{-- Actions --}}
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary rounded-pill py-2.5 fw-bold shadow-sm" onclick="window.location.reload();">
                        <i class="fas fa-sync-alt me-2"></i> {{ __('Muat Ulang Halaman') }}
                    </button>

                    @if(config('telegram.bot_username'))
                    <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank" class="btn btn-outline-primary rounded-pill py-2.5 fw-bold">
                        <i class="fab fa-telegram-plane me-2 text-info"></i> {{ __('Buka Bot Telegram') }}
                    </a>
                    @endif

                    @if(Auth::check())
                    <form method="POST" action="{{ route('logout') }}" class="m-0 mt-2">
                        @csrf
                        <button type="submit" class="btn btn-light rounded-pill py-2 w-100 fw-semibold text-danger border">
                            <i class="fas fa-sign-out-alt me-2"></i> {{ __('Keluar (Logout)') }}
                        </button>
                    </form>
                    @else
                    <a href="{{ route('login') }}" class="btn btn-link text-decoration-none small text-muted mt-2">
                        <i class="fas fa-lock me-1"></i> {{ __('Login Administrator') }}
                    </a>
                    @endif
                </div>

                {{-- Footer note --}}
                <div class="mt-4 pt-2 border-top">
                    <small class="text-secondary" style="font-size: 0.78rem;">
                        {{ config('app.name', 'Dzulfikrialifajri Store') }} &bull; {{ __('Terima kasih atas kesabaran Anda.') }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
