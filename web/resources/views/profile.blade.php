@extends('layouts.app')

@section('title', __('Profil Saya'))
@section('page_subtitle', __('Profil'))

@push('styles')
<style>
    .hero-profile-banner {
        background: var(--primary-container);
        color: var(--on-primary-container);
        min-height: 160px;
        border-radius: var(--radius-lg);
        border: 1px solid var(--outline-variant);
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    .hero-pattern {
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        opacity: 0.08;
        background-image: radial-gradient(var(--on-primary-container) 1.5px, transparent 1.5px);
        background-size: 20px 20px;
    }
    .floating-profile-container {
        margin-top: -44px;
    }
    .avatar-wrapper {
        position: relative;
        display: inline-block;
    }
    .avatar-circle {
        width: 104px;
        height: 104px;
        border-radius: 50%;
        border: 4px solid var(--surface-container-lowest);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.4rem;
        font-weight: 700;
        color: var(--on-primary);
        background: var(--primary);
        margin: 0 auto;
    }
    
    /* Horizontal scrollable pills for tab navigation */
    .profile-nav-scroll {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .profile-nav-scroll::-webkit-scrollbar {
        display: none;
    }
    .profile-nav-scroll .nav-item {
        flex: 0 0 auto;
    }
    .profile-nav-scroll .nav-link {
        white-space: nowrap;
        min-height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.45rem 1rem;
        border-radius: var(--radius-pill) !important;
    }

    @media (min-width: 768px) {
        .profile-nav-scroll .nav-item {
            flex: 1 1 0px;
        }
    }

    @media (max-width: 767.98px) {
        .avatar-circle {
            width: 80px;
            height: 80px;
            font-size: 1.9rem;
            border-width: 3px;
        }
        .hero-profile-banner {
            min-height: 125px;
            padding: 1.25rem !important;
            border-radius: var(--radius-md);
        }
        .floating-profile-container {
            margin-top: -24px;
        }
    }
</style>
@endpush

@section('content')
<!-- Hero Section (Flat Material You Tonal Surface) -->
<div class="position-relative mb-4">
    <div class="hero-profile-banner p-4 overflow-hidden position-relative">
        <div class="hero-pattern"></div>
        <div class="position-relative z-1">
            <h1 class="fw-bold mb-1 h3" style="color: var(--on-primary-container);">
                {{ __('Profil Saya') }}
            </h1>
            <p class="mb-0 small opacity-85" style="color: var(--on-primary-container);">
                {{ __('Kelola data akun, keamanan kata sandi, dan integrasi Telegram Bot Anda') }}
            </p>
        </div>
    </div>

    <!-- Floating Container Split (4 cols left, 8 cols right) -->
    <div class="container-fluid px-0 floating-profile-container">
        <!-- Alert Flash Notifications -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-3" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ __('Terdapat kesalahan pada inputan Anda:') }}
                <ul class="mb-0 ps-3 mt-1 small">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-3">
            <!-- Left Column: Profile Summary Card -->
            <div class="col-lg-4">
                <div class="card text-center p-4 h-100">
                    <div class="avatar-wrapper mx-auto mb-3">
                        <div class="avatar-circle overflow-hidden">
                            @if(Auth::user()->avatar)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url(Auth::user()->avatar) }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                {{ strtoupper(substr(Auth::user()->full_name ?? Auth::user()->username ?? 'U', 0, 1)) }}
                            @endif
                        </div>
                    </div>

                    <h4 class="fw-bold mb-1 text-body">
                        {{ Auth::user()->full_name ?? Auth::user()->username ?? 'User' }}
                    </h4>
                    
                    @if(Auth::user()->username)
                        <p class="text-secondary small mb-3">@_{{ Auth::user()->username }}</p>
                    @endif

                    <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
                        <span class="badge bg-primary-subtle text-primary px-2.5 py-1.5 fw-semibold">
                            <i class="fas fa-user-tag me-1"></i> {{ strtoupper(Auth::user()->role ?? 'CUSTOMER') }}
                        </span>
                        
                        @if(Auth::user()->two_factor_enabled)
                            <span class="badge bg-success-subtle text-success px-2.5 py-1.5 fw-semibold">
                                <i class="fas fa-shield-alt me-1"></i> {{ __('2FA Aktif') }}
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary px-2.5 py-1.5 fw-semibold">
                                <i class="fas fa-shield-alt me-1"></i> {{ __('2FA Nonaktif') }}
                            </span>
                        @endif
                    </div>

                    <div class="bg-body-tertiary rounded-3 p-3 mb-3 border text-start">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <span class="small text-muted fw-semibold">{{ __('Telegram Link:') }}</span>
                            @if(Auth::user()->telegram_id)
                                <span class="badge bg-success-subtle text-success"><i class="fas fa-check me-1"></i>Terhubung</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning"><i class="fas fa-exclamation me-1"></i>Belum</span>
                            @endif
                        </div>
                        <div class="small fw-semibold text-body">
                            ID: {{ Auth::user()->telegram_id ?: 'Tidak Terhubung' }}
                        </div>
                    </div>

                    <div class="mt-auto pt-2">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger rounded-pill w-100 fw-semibold py-2">
                                <i class="fas fa-sign-out-alt me-1.5"></i>{{ __('Keluar dari Akun') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Details & Tabbed Card -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header bg-transparent border-0 pt-3 px-3 px-md-4 pb-1">
                        <ul class="nav nav-pills card-header-pills profile-nav-scroll gap-1" id="profile-tab" role="tablist">
                            <li class="nav-item text-center" role="presentation">
                                <button class="nav-link active fw-semibold small w-100" id="tab-informasi-btn" data-bs-toggle="pill" data-bs-target="#tab-informasi" type="button" role="tab">
                                    <i class="fas fa-user-edit me-1"></i> {{ __('Data Profil') }}
                                </button>
                            </li>
                            <li class="nav-item text-center" role="presentation">
                                <button class="nav-link fw-semibold small w-100" id="tab-keamanan-btn" data-bs-toggle="pill" data-bs-target="#tab-keamanan" type="button" role="tab">
                                    <i class="fas fa-key me-1"></i> {{ __('Sandi & 2FA') }}
                                </button>
                            </li>
                            <li class="nav-item text-center" role="presentation">
                                <button class="nav-link fw-semibold small w-100" id="tab-telegram-btn" data-bs-toggle="pill" data-bs-target="#tab-telegram" type="button" role="tab">
                                    <i class="fab fa-telegram me-1"></i> {{ __('Integrasi Bot') }}
                                </button>
                            </li>
                            <li class="nav-item text-center" role="presentation">
                                <button class="nav-link fw-semibold small w-100" id="tab-geolokasi-btn" data-bs-toggle="pill" data-bs-target="#tab-geolokasi" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-1"></i> {{ __('Keamanan IP') }}
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-3 p-md-4 pt-3">
                        <div class="tab-content" id="profile-tabContent">
                            
                            <!-- Tab 1: Informasi Profil -->
                            <div class="tab-pane fade show active" id="tab-informasi" role="tabpanel">
                                <!-- Summary View Container -->
                                <div id="profile-summary-view" style="{{ $errors->any() ? 'display: none;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold text-primary mb-0">
                                            <i class="fas fa-id-card me-2"></i>{{ __('Informasi Akun') }}
                                        </h6>
                                        <button type="button" id="btn-toggle-edit-profile" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">
                                            <i class="fas fa-user-edit me-1"></i> {{ __('Edit Profil') }}
                                        </button>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="p-3 bg-body-tertiary rounded-3 border">
                                                <div class="small text-muted fw-semibold mb-1"><i class="fas fa-user me-1.5 text-primary"></i>{{ __('Nama Lengkap') }}</div>
                                                <div class="fw-semibold text-body">{{ Auth::user()->full_name }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 bg-body-tertiary rounded-3 border">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <div class="small text-muted fw-semibold"><i class="fas fa-at me-1.5 text-primary"></i>{{ __('Username') }}</div>
                                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.68rem;">
                                                        <i class="fas fa-lock me-1"></i>Permanen
                                                    </span>
                                                </div>
                                                <div class="fw-semibold text-body">{{ Auth::user()->username }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 bg-body-tertiary rounded-3 border">
                                                <div class="small text-muted fw-semibold mb-1"><i class="fas fa-envelope me-1.5 text-primary"></i>{{ __('Alamat Email') }}</div>
                                                <div class="fw-semibold text-body">{{ Auth::user()->email ?: '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 bg-body-tertiary rounded-3 border">
                                                <div class="small text-muted fw-semibold mb-1"><i class="fab fa-telegram me-1.5 text-primary"></i>{{ __('Telegram ID') }}</div>
                                                <div class="fw-semibold text-body d-flex align-items-center gap-2">
                                                    <span>{{ Auth::user()->telegram_id ?: '-' }}</span>
                                                    @if(Auth::user()->telegram_id)
                                                        <span class="badge bg-success-subtle text-success"><i class="fas fa-check-circle me-1"></i>Tertaut</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Form Container -->
                                <div id="profile-edit-form-container" style="{{ $errors->any() ? '' : 'display: none;' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold text-primary mb-0">
                                            <i class="fas fa-user-edit me-2"></i>{{ __('Form Pengeditan Profil') }}
                                        </h6>
                                        <button type="button" id="btn-cancel-edit-profile" class="btn btn-outline-secondary btn-sm px-3 fw-semibold">
                                            <i class="fas fa-times me-1"></i> {{ __('Batal') }}
                                        </button>
                                    </div>
                                    
                                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold text-muted">{{ __('Foto Profil (Avatar)') }}</label>
                                                <input type="file" name="avatar" class="form-control form-control-sm" accept="image/*">
                                                <div class="form-text small text-muted"><i class="fas fa-info-circle me-1"></i>Format: JPG, PNG, WEBP, GIF. Maks: 2MB.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-muted">{{ __('Nama Lengkap') }}</label>
                                                <input type="text" name="full_name" class="form-control form-control-sm" required value="{{ old('full_name', Auth::user()->full_name) }}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-muted">
                                                    {{ __('Username') }} 
                                                    <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size: 0.65rem;">
                                                        <i class="fas fa-lock me-1"></i>Permanen
                                                    </span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm bg-body-tertiary text-muted" value="{{ Auth::user()->username }}" readonly disabled>
                                                <div class="form-text small text-muted"><i class="fas fa-info-circle me-1"></i>Username tidak dapat diubah setelah pendaftaran.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-muted">{{ __('Alamat Email') }}</label>
                                                <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email', Auth::user()->email) }}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold text-muted">{{ __('Telegram ID') }}</label>
                                                <input type="number" name="telegram_id" id="telegram_id_input" class="form-control form-control-sm" value="{{ old('telegram_id', Auth::user()->telegram_id) }}">
                                                <div id="telegram_id_feedback" class="form-text small mt-1"></div>
                                            </div>
                                            <div class="col-12 mt-4 d-flex gap-2">
                                                <button type="submit" id="btn-save-profile" class="btn btn-primary px-4 fw-semibold">
                                                    <i class="fas fa-save me-1"></i> {{ __('Simpan Perubahan Profil') }}
                                                </button>
                                                <button type="button" id="btn-cancel-edit-profile-bottom" class="btn btn-outline-secondary px-4 fw-semibold">
                                                    {{ __('Batal') }}
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Tab 2: Keamanan & Kata Sandi -->
                            <div class="tab-pane fade" id="tab-keamanan" role="tabpanel">
                                <h6 class="fw-bold text-primary mb-2">
                                    <i class="fas fa-lock me-2"></i>{{ __('Pengaturan Kata Sandi (Password)') }}
                                </h6>
                                <p class="small text-muted mb-3">
                                    {{ __('Atur kata sandi agar Anda bisa melakukan') }} <strong>{{ __('Login Konvensional') }}</strong> {{ __('menggunakan Username dan Password.') }}
                                </p>
                                <form action="{{ route('profile.password.update') }}" method="POST" class="mb-4">
                                    @csrf
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-muted">{{ __('Kata Sandi Baru') }}</label>
                                            <input type="password" name="password" class="form-control form-control-sm" required placeholder="{{ __('Minimal 6 karakter') }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-muted">{{ __('Ulangi Kata Sandi Baru') }}</label>
                                            <input type="password" name="password_confirmation" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-12 mt-3">
                                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">
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
                                    <div class="p-3 bg-body-tertiary rounded-3 border d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                                        <div>
                                            <div class="fw-semibold text-body mb-1">
                                                {{ __('Status OTP Telegram:') }} 
                                                <span class="badge {{ Auth::user()->two_factor_enabled ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} ms-1">
                                                    {{ Auth::user()->two_factor_enabled ? __('Aktif') : __('Nonaktif') }}
                                                </span>
                                            </div>
                                            <small class="text-muted d-block">
                                                {{ __('Kirim kode OTP keamanan via Telegram setiap kali terjadi aktivitas login baru.') }}
                                            </small>
                                        </div>
                                        <button type="submit" class="btn btn-sm rounded-pill {{ Auth::user()->two_factor_enabled ? 'btn-outline-danger' : 'btn-success' }} px-3 fw-semibold text-nowrap">
                                            {{ Auth::user()->two_factor_enabled ? __('Nonaktifkan 2FA') : __('Aktifkan 2FA') }}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Tab 3: Integrasi Telegram -->
                            <div class="tab-pane fade" id="tab-telegram" role="tabpanel">
                                <h6 class="fw-bold text-primary mb-2">
                                    <i class="fab fa-telegram me-2"></i>{{ __('Kaitan Akun Telegram Bot') }}
                                </h6>
                                <p class="small text-muted mb-3">
                                    {{ __('Menautkan akun Telegram memungkinkan Anda menerima notifikasi transaksi, lisensi akun, dan akses pesan otomatis langsung di Telegram.') }}
                                </p>

                                @if(Auth::user()->telegram_id)
                                    <div class="p-3 bg-success-subtle rounded-3 mb-3">
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
                                        <button type="button" class="btn btn-outline-danger rounded-pill px-4 fw-semibold" onclick="confirmAction(event, 'Apakah Anda yakin ingin melepas kaitan akun Telegram Anda?')">
                                            <i class="fas fa-unlink me-1"></i> {{ __('Lepas Kaitan Telegram') }}
                                        </button>
                                    </form>
                                @else
                                    <div class="p-3 bg-warning-subtle rounded-3 mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                            <div>
                                                <div class="fw-bold text-warning">{{ __('Akun Belum Terhubung ke Telegram') }}</div>
                                                <small class="text-secondary">{{ __('Klik tombol di bawah untuk membuka Telegram Bot dan menautkan akun secara otomatis.') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                    <form action="{{ route('profile.telegram.link') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
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
                                <div class="row g-3 mb-3">
                                    <div class="col-sm-6">
                                        <div class="p-3 bg-body-tertiary rounded-3 border">
                                            <small class="text-muted d-block fw-semibold mb-1">{{ __('IP Pendaftaran:') }}</small>
                                            <strong class="text-body">{{ Auth::user()->registration_ip ?: 'Tidak tercatat' }}</strong>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="p-3 bg-body-tertiary rounded-3 border">
                                            <small class="text-muted d-block fw-semibold mb-1">{{ __('Negara Asal (IP Terakhir):') }}</small>
                                            <strong class="text-primary">{{ Auth::user()->last_login_country ?: 'Indonesia (Lokal)' }}</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center p-3 bg-body-tertiary rounded-3 border">
                                    <div>
                                        <div class="fw-semibold text-body mb-1">{{ __('Riwayat Login Sesi') }}</div>
                                        <small class="text-muted">{{ __('Lihat daftar perangkat dan log percobaan masuk ke akun Anda.') }}</small>
                                    </div>
                                    <a href="{{ route('profile.logins') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold text-nowrap">
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

        // Auto-activate tab based on URL hash or query param (e.g., #tab-keamanan or ?tab=password)
        const hash = window.location.hash;
        const urlParams = new URLSearchParams(window.location.search);
        if (hash === '#tab-keamanan' || hash === '#password' || urlParams.get('tab') === 'password') {
            const passwordTabBtn = document.getElementById('tab-keamanan-btn');
            if (passwordTabBtn) {
                const tab = new bootstrap.Tab(passwordTabBtn);
                tab.show();
            }
        }

        // Profile Edit Form Toggle Handler
        const btnToggleEdit = document.getElementById('btn-toggle-edit-profile');
        const btnCancelEdit = document.getElementById('btn-cancel-edit-profile');
        const btnCancelEditBottom = document.getElementById('btn-cancel-edit-profile-bottom');
        const summaryView = document.getElementById('profile-summary-view');
        const editFormContainer = document.getElementById('profile-edit-form-container');

        if (btnToggleEdit && summaryView && editFormContainer) {
            btnToggleEdit.addEventListener('click', function() {
                summaryView.style.display = 'none';
                editFormContainer.style.display = 'block';
            });
        }

        function cancelEditProfile() {
            if (summaryView && editFormContainer) {
                editFormContainer.style.display = 'none';
                summaryView.style.display = 'block';
            }
        }

        if (btnCancelEdit) btnCancelEdit.addEventListener('click', cancelEditProfile);
        if (btnCancelEditBottom) btnCancelEditBottom.addEventListener('click', cancelEditProfile);
    });
</script>
@endsection
