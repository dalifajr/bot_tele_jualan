@extends('layouts.app')

@section('title', 'Akun Ditangguhkan')

@section('content')
<div class="row justify-content-center py-5">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0" style="border-radius: 16px;">
            <div class="card-body p-5 text-center">
                <div class="mb-4">
                    <i class="fas fa-ban text-danger" style="font-size: 5rem;"></i>
                </div>
                
                <h3 class="fw-bold mb-3">{{ __('Akun Anda Ditangguhkan') }}</h3>
                
                <p class="text-muted mb-4">
                    {{ __('Mohon maaf, akses akun Anda ke platform ini telah ditangguhkan sementara waktu oleh administrator kami.') }}
                </p>

                @if(Auth::check() && Auth::user()->suspension_reason)
                <div class="alert alert-danger-subtle text-danger border border-danger-subtle rounded-4 p-3 mb-4 text-start">
                    <div class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Alasan Penangguhan:') }}</div>
                    <div class="small">{{ Auth::user()->suspension_reason }}</div>
                </div>
                @endif
                
                <div class="alert alert-warning mb-4 text-start small">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>{{ __('Penting:') }}</strong> {{ __('Anda tidak dapat melakukan transaksi, menghubungi bot Telegram, maupun mengakses katalog.') }}
                </div>

                <div class="d-grid gap-2">
                    <a href="https://t.me/{{ config('telegram.bot_username', 'Admin') }}" target="_blank" class="btn btn-primary rounded-pill py-2 fw-bold">
                        <i class="fab fa-telegram-plane me-2"></i> {{ __('Hubungi Admin') }}
                    </a>
                    
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-light rounded-pill py-2 w-100 fw-bold">
                            <i class="fas fa-sign-out-alt me-2"></i> {{ __('Keluar') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
