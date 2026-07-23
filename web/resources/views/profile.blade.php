@extends('layouts.app')

@section('title', 'Profil Saya')
@section('page_subtitle', 'Profil')

@push('styles')
<style>
    .lift-hover {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .lift-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 14px 28px rgba(0, 0, 0, 0.1) !important;
    }
    .hero-profile-banner {
        background: linear-gradient(135deg, #0d47a1 0%, #1565c0 50%, #1e88e5 100%);
        min-height: 200px;
        border-radius: 24px;
    }
    .hero-pattern {
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        opacity: 0.12;
        background-image: radial-gradient(#ffffff 1.5px, transparent 1.5px);
        background-size: 22px 22px;
    }
    .floating-profile-container {
        margin-top: -65px;
    }
    @media (max-width: 767.98px) {
        .floating-profile-container {
            margin-top: 1rem;
        }
    }
    .avatar-wrapper {
        position: relative;
        display: inline-block;
    }
    .avatar-circle {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        border: 4px solid #ffffff;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 700;
        color: #ffffff;
        background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
        margin: 0 auto;
    }
    .nav-pills .nav-link {
        color: var(--bs-body-color);
        transition: all 0.2s ease;
    }
    .nav-pills .nav-link.active {
        background-color: var(--bs-primary) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
    }
</style>
@endpush

@section('content')
<!-- Hero Section (Gradient & Radial Pattern) -->
<div class="position-relative mb-5">
    <div class="hero-profile-banner p-4 p-md-5 text-white shadow-sm overflow-hidden position-relative">
        <div class="hero-pattern"></div>
        <div class="position-relative z-1">
            <h1 class="fw-bold mb-1 text-white fs-2 fs-md-1">
                {{ __('Profil Saya') }}
            </h1>
            <p class="mb-0 fs-6 opacity-85 fw-light text-white">
                {{ __('Kelola data akun, keamanan kata sandi, dan integrasi Telegram Bot Anda') }}
            </p>
        </div>
    </div>

    <!-- Floating Container Split (4 cols left, 8 cols right) -->
    <div class="container-fluid px-2 px-md-3 floating-profile-container">
        <!-- Alert Flash Notifications -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ __('Terdapat kesalahan pada inputan Anda:') }}
                <ul class="mb-0 ps-3 mt-1 small">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4">
            <!-- Left Column: Profile Summary Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 text-center p-4 h-100 lift-hover">
                    <div class="avatar-wrapper mx-auto mb-3">
                        <div class="avatar-circle">
                            {{ strtoupper(substr(Auth::user()->full_name ?? Auth::user()->username ?? 'U', 0, 1)) }}
                        </div>
                    </div>

                    <h4 class="fw-bold mb-1 text-body">
                        {{ Auth::user()->full_name ?? Auth::user()->username ?? 'User' }}
                    </h4>
                    
                    @if(Auth::user()->username)
                        <p class="text-secondary small mb-2">@_{{ Auth::user()->username }}</p>
                    @endif

                    <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold">
                            <i class="fas fa-user-tag me-1"></i> {{ strtoupper(Auth::user()->role ?? 'CUSTOMER') }}
                        </span>
                        
                        @if(Auth::user()->two_factor_enabled)
                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 fw-semibold">
                                <i class="fas fa-shield-alt me-1"></i> {{ __('2FA Aktif') }}
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 fw-semibold">
                                <i class="fas fa-shield-alt me-1"></i> {{ __('2FA Nonaktif') }}
                            </span>
                        @endif
                    </div>

                    <div class="bg-light rounded-4 p-3 mb-3 border-0 text-start">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted fw-bold">{{ __('Telegram Link:') }}</span>
                            @if(Auth::user()->telegram_id)
                                <span class="badge bg-success rounded-pill px-2 py-1 small"><i class="fas fa-check me-1"></i>Terhubung</span>
                            @else
                                <span class="badge bg-warning text-dark rounded-pill px-2 py-1 small"><i class="fas fa-exclamation me-1"></i>Belum</span>
                            @endif
                        </div>
                        <div class="small fw-semibold text-body">
                            ID: {{ Auth::user()->telegram_id ?: 'Tidak Terhubung' }}
                        </div>
                    </div>

                    <div class="mt-auto pt-2">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100 rounded-pill fw-bold py-2">
                                <i class="fas fa-sign-out-alt me-2"></i>{{ __('Keluar dari Akun') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Details & Tabbed Card -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
                        <ul class="nav nav-pills card-header-pills bg-light rounded-pill p-1 flex-column flex-sm-row gap-1" id="profile-tab" role="tablist">
                            <li class="nav-item flex-fill text-center" role="presentation">
                                <button class="nav-link active rounded-pill fw-bold small w-100 py-2" id="tab-informasi-btn" data-bs-toggle="pill" data-bs-target="#tab-informasi" type="button" role="tab">
                                    <i class="fas fa-user-edit me-1"></i> {{ __('Data Profil') }}
                                </button>
                            </li>
                            <li class="nav-item flex-fill text-center" role="presentation">
                                <button class="nav-link rounded-pill fw-bold small w-100 py-2" id="tab-keamanan-btn" data-bs-toggle="pill" data-bs-target="#tab-keamanan" type="button" role="tab">
                                    <i class="fas fa-key me-1"></i> {{ __('Sandi & 2FA') }}
                                </button>
                            </li>
                            <li class="nav-item flex-fill text-center" role="presentation">
                                <button class="nav-link rounded-pill fw-bold small w-100 py-2" id="tab-telegram-btn" data-bs-toggle="pill" data-bs-target="#tab-telegram" type="button" role="tab">
                                    <i class="fab fa-telegram me-1"></i> {{ __('Integrasi Bot') }}
                                </button>
                            </li>
                            <li class="nav-item flex-fill text-center" role="presentation">
                                <button class="nav-link rounded-pill fw-bold small w-100 py-2" id="tab-geolokasi-btn" data-bs-toggle="pill" data-bs-target="#tab-geolokasi" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-1"></i> {{ __('Keamanan IP') }}
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-4 pt-3">
                        <div class="tab-content" id="profile-tabContent">
                            
                            <!-- Tab 1: Informasi Profil -->
                            <div class="tab-pane fade show active" id="tab-informasi" role="tabpanel">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="fas fa-id-card me-2"></i>{{ __('Informasi Akun & Pengeditan') }}
                                </h6>
                                <form action="{{ route('profile.update') }}" method="POST">
                                    @csrf
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">{{ __('Nama Lengkap') }}</label>
                                            <input type="text" name="full_name" class="form-control form-control-sm rounded-3" required value="{{ old('full_name', Auth::user()->full_name) }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">{{ __('Username') }}</label>
                                            <input type="text" name="username" class="form-control form-control-sm rounded-3" required value="{{ old('username', Auth::user()->username) }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">{{ __('Alamat Email') }}</label>
                                            <input type="email" name="email" class="form-control form-control-sm rounded-3" value="{{ old('email', Auth::user()->email) }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">{{ __('Telegram ID') }}</label>
                                            <input type="number" name="telegram_id" id="telegram_id_input" class="form-control form-control-sm rounded-3" value="{{ old('telegram_id', Auth::user()->telegram_id) }}">
                                            <div id="telegram_id_feedback" class="form-text small mt-1"></div>
                                        </div>
                                        <div class="col-12 mt-4">
                                            <button type="submit" id="btn-save-profile" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                                <i class="fas fa-save me-1"></i> {{ __('Simpan Perubahan Profil') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Tab 2: Keamanan & Kata Sandi -->
                            <div class="tab-pane fade" id="tab-keamanan" role="tabpanel">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="fas fa-lock me-2"></i>{{ __('Pengaturan Kata Sandi (Password)') }}
                                </h6>
                                <p class="small text-muted mb-3">
                                    {{ __('Atur kata sandi agar Anda bisa melakukan') }} <strong>{{ __('Login Konvensional') }}</strong> {{ __('menggunakan Username dan Password.') }}
                                </p>
                                <form action="{{ route('profile.password.update') }}" method="POST" class="mb-4">
                                    @csrf
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">{{ __('Kata Sandi Baru') }}</label>
                                            <input type="password" name="password" class="form-control form-control-sm rounded-3" required placeholder="{{ __('Minimal 6 karakter') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-muted">{{ __('Ulangi Kata Sandi Baru') }}</label>
                                            <input type="password" name="password_confirmation" class="form-control form-control-sm rounded-3" required>
                                        </div>
                                        <div class="col-12 mt-3">
                                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">
                                                <i class="fas fa-key me-1"></i> {{ __('Perbarui Kata Sandi') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>

                                <hr class="my-4">

                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="fas fa-shield-alt me-2"></i>{{ __('Verifikasi Dua Langkah (2FA)') }}
                                </h6>
                                <form action="{{ route('profile.2fa.toggle') }}" method="POST">
                                    @csrf
                                    <div class="p-3 bg-light rounded-4 border d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                                        <div>
                                            <div class="fw-bold text-body mb-1">
                                                {{ __('Status OTP Telegram:') }} 
                                                <span class="badge {{ Auth::user()->two_factor_enabled ? 'bg-success' : 'bg-secondary' }} rounded-pill px-2 py-1 ms-1">
                                                    {{ Auth::user()->two_factor_enabled ? __('Aktif') : __('Nonaktif') }}
                                                </span>
                                            </div>
                                            <small class="text-muted d-block">
                                                {{ __('Kirim kode OTP keamanan via Telegram setiap kali terjadi aktivitas login baru.') }}
                                            </small>
                                        </div>
                                        <button type="submit" class="btn btn-sm {{ Auth::user()->two_factor_enabled ? 'btn-outline-danger' : 'btn-success' }} rounded-pill px-4 fw-bold text-nowrap">
                                            {{ Auth::user()->two_factor_enabled ? __('Nonaktifkan 2FA') : __('Aktifkan 2FA') }}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Tab 3: Integrasi Telegram -->
                            <div class="tab-pane fade" id="tab-telegram" role="tabpanel">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="fab fa-telegram me-2"></i>{{ __('Kaitan Akun Telegram Bot') }}
                                </h6>
                                <p class="small text-muted mb-3">
                                    {{ __('Menautkan akun Telegram memungkinkan Anda menerima notifikasi transaksi, lisensi akun, dan akses pesan otomatis langsung di Telegram.') }}
                                </p>

                                @if(Auth::user()->telegram_id)
                                    <div class="p-3 bg-success-subtle border border-success border-opacity-25 rounded-4 mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fab fa-telegram fa-2x text-success"></i>
                                            <div>
                                                <div class="fw-bold text-success">{{ __('Akun Telegram Terhubung') }}</div>
                                                <small class="text-secondary">ID Telegram: <strong>{{ Auth::user()->telegram_id }}</strong></small>
                                            </div>
                                        </div>
                                    </div>
                                    <form action="{{ route('profile.telegram.unlink') }}" method="POST">
                                        @csrf
                                        <button type="button" class="btn btn-outline-danger rounded-pill px-4 fw-bold shadow-sm" onclick="confirmAction(event, 'Apakah Anda yakin ingin melepas kaitan akun Telegram Anda?')">
                                            <i class="fas fa-unlink me-1"></i> {{ __('Lepas Kaitan Telegram') }}
                                        </button>
                                    </form>
                                @else
                                    <div class="p-3 bg-warning-subtle border border-warning border-opacity-25 rounded-4 mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fas fa-exclamation-triangle fa-2x text-warning-emphasis"></i>
                                            <div>
                                                <div class="fw-bold text-warning-emphasis">{{ __('Akun Belum Terhubung ke Telegram') }}</div>
                                                <small class="text-secondary">{{ __('Klik tombol di bawah untuk membuka Telegram Bot dan menautkan akun secara otomatis.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                    <form action="{{ route('profile.telegram.link') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                            <i class="fab fa-telegram me-1"></i> {{ __('Tautkan Akun Telegram Sekarang') }}
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <!-- Tab 4: Geolokasi & Log Keamanan -->
                            <div class="tab-pane fade" id="tab-geolokasi" role="tabpanel">
                                <h6 class="fw-bold text-primary mb-3">
                                    <i class="fas fa-globe me-2"></i>{{ __('Informasi Keamanan & Geolokasi Login') }}
                                </h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-sm-6">
                                        <div class="p-3 bg-light rounded-4 border">
                                            <small class="text-muted d-block fw-bold mb-1">{{ __('IP Pendaftaran:') }}</small>
                                            <strong class="text-body">{{ Auth::user()->registration_ip ?: 'Tidak tercatat' }}</strong>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="p-3 bg-light rounded-4 border">
                                            <small class="text-muted d-block fw-bold mb-1">{{ __('Negara Asal (IP Terakhir):') }}</small>
                                            <strong class="text-primary">{{ Auth::user()->last_login_country ?: 'Indonesia (Lokal)' }}</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded-4 border">
                                    <div>
                                        <div class="fw-bold text-body mb-1">{{ __('Riwayat Login Sesi') }}</div>
                                        <small class="text-muted">{{ __('Lihat daftar perangkat dan log percobaan masuk ke akun Anda.') }}</small>
                                    </div>
                                    <a href="{{ route('profile.logins') }}" class="btn btn-sm btn-outline-primary rounded-pill px-4 fw-bold text-nowrap">
                                        <i class="fas fa-history me-1"></i> {{ __('Lihat Log Sesi') }}
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let telegramInput = document.getElementById('telegram_id_input');
        let feedbackElem = document.getElementById('telegram_id_feedback');
        let saveBtn = document.getElementById('btn-save-profile');
        let checkTimeout;

        const currentTelegramId = "{{ Auth::user()->telegram_id }}";

        if (telegramInput) {
            telegramInput.addEventListener('input', function() {
                clearTimeout(checkTimeout);
                let val = this.value.trim();

                if (!val || val === currentTelegramId) {
                    feedbackElem.innerHTML = '';
                    saveBtn.disabled = false;
                    return;
                }

                feedbackElem.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>{{ __('Mengecek...') }}</span>';
                saveBtn.disabled = true;

                checkTimeout = setTimeout(() => {
                    fetch('{{ route("api.check.telegram") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ telegram_id: val })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            feedbackElem.innerHTML = `<span class="text-success"><i class="fas fa-check-circle me-1"></i>${data.message}</span>`;
                            saveBtn.disabled = false;
                        } else {
                            feedbackElem.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i>${data.message}</span>`;
                            saveBtn.disabled = true;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        feedbackElem.innerHTML = '<span class="text-danger">{{ __('Gagal mengecek ID Telegram.') }}</span>';
                        saveBtn.disabled = false;
                    });
                }, 500);
            });
        }
    });
</script>
@endsection
