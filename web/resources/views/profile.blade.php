@extends('layouts.app')

@section('title', 'Profil Saya')
@section('page_subtitle', 'Profil')

@section('content')
<div class="row g-4">
    <div class="col-lg-8 mx-auto">
        {{-- Profile Header --}}
        <div class="profile-header">
            <div class="profile-avatar">
                {{ strtoupper(substr(Auth::user()->full_name ?? Auth::user()->username ?? 'U', 0, 1)) }}
            </div>
            <h4 class="fw-bold mb-1">{{ Auth::user()->full_name ?? Auth::user()->username ?? 'User' }}</h4>
            <p class="opacity-75 mb-0">
                @if(Auth::user()->username
                    <span>@{{ Auth::user()->username }}</span>
                @endif
            </p>
        </div>

        {{-- Notifikasi Umum --}}
        @if(session('success'))
            <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger small py-2 mb-4">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Edit Profile Form --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-user-edit text-primary me-2"></i>{{ __('Edit Profil') }}</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <form action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Nama Lengkap') }}</label>
                            <input type="text" name="full_name" class="form-control form-control-sm" required value="{{ old('full_name', Auth::user()->full_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Username') }}</label>
                            <input type="text" name="username" class="form-control form-control-sm" required value="{{ old('username', Auth::user()->username) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Email') }}</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email', Auth::user()->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Telegram ID') }}</label>
                            <input type="number" name="telegram_id" id="telegram_id_input" class="form-control form-control-sm" value="{{ old('telegram_id', Auth::user()->telegram_id) }}">
                            <div id="telegram_id_feedback" class="form-text small mt-1"></div>
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" id="btn-save-profile" class="btn btn-primary btn-sm rounded-pill px-4">
                                {{ __('Simpan Profil') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Set / Update Password --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-lock text-primary me-2"></i>{{ __('Atur Sandi (Password)') }}</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <p class="small text-muted mb-3">
                    {{ __('Atur kata sandi agar Anda bisa melakukan') }} <strong>{{ __('Login Konvensional') }}</strong> {{ __('menggunakan Username dan Password tanpa harus bergantung pada Telegram.') }}
                </p>
                <form action="{{ route('profile.password.update') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('Kata Sandi Baru') }}</label>
                            <input type="password" name="password" class="form-control form-control-sm" required placeholder="{{ __('Minimal 6 karakter') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('Ulangi Sandi Baru') }}</label>
                            <input type="password" name="password_confirmation" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">
                                {{ __('Simpan Sandi') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Metadata / Security Info --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-shield-alt text-primary me-2"></i>{{ __('Informasi Keamanan & Geolokasi') }}</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row g-3 small">
                    <div class="col-sm-6">
                        <span class="text-muted d-block">{{ __('IP Pendaftaran:') }}</span>
                        <strong class="text-dark">{{ Auth::user()->registration_ip ?: 'Tidak tercatat' }}</strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted d-block">{{ __('Negara Asal (IP Terakhir):') }}</span>
                        <strong class="text-primary">{{ Auth::user()->last_login_country ?: 'Indonesia (Lokal)' }}</strong>
                    </div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <div class="d-flex flex-column gap-3">
                    
                    {{-- Tombol Tautkan / Lepas Telegram --}}
                    @if(Auth::user()->telegram_id)
                        <form action="{{ route('profile.telegram.unlink') }}" method="POST">
                            @csrf
                            <button type="button" class="quick-action-btn w-100 border-warning text-start" onclick="confirmAction(event, 'Apakah Anda yakin ingin melepas kaitan akun Telegram Anda?')">
                                <div class="qa-icon" style="background: #fff8e1;">
                                    <i class="fas fa-unlink text-warning"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-warning">{{ __('Lepas Kaitan Telegram') }}</div>
                                    <small class="text-muted">{{ __('Putus sinkronisasi dengan Bot') }}</small>
                                </div>
                            </button>
                        </form>
                    @else
                        <form action="{{ route('profile.telegram.link') }}" method="POST">
                            @csrf
                            <button type="submit" class="quick-action-btn w-100 border-info text-start">
                                <div class="qa-icon" style="background: #e1f5fe;">
                                    <i class="fab fa-telegram text-info"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-info">{{ __('Tautkan Akun Telegram') }}</div>
                                    <small class="text-muted">{{ __('Buka bot untuk menautkan akun ini secara otomatis') }}</small>
                                </div>
                                <i class="fas fa-arrow-right text-info ms-auto"></i>
                            </button>
                        </form>
                    @endif

                    {{-- Tombol Toggle 2FA --}}
                    <form action="{{ route('profile.2fa.toggle') }}" method="POST">
                        @csrf
                        <button type="submit" class="quick-action-btn w-100 {{ Auth::user()->two_factor_enabled ? 'border-success' : 'border-secondary' }} text-start">
                            <div class="qa-icon" style="{{ Auth::user()->two_factor_enabled ? 'background: #e8f5e9;' : 'background: #f5f5f5;' }}">
                                <i class="fas fa-shield-alt {{ Auth::user()->two_factor_enabled ? 'text-success' : 'text-secondary' }}"></i>
                            </div>
                            <div>
                                <div class="fw-bold {{ Auth::user()->two_factor_enabled ? 'text-success' : 'text-secondary' }}">{{ __('Verifikasi Dua Langkah (2FA)') }}</div>
                                <small class="text-muted">{{ __('Kirim OTP via Telegram saat login (Status:') }} <strong>{{ Auth::user()->two_factor_enabled ? 'Aktif' : 'Nonaktif' }}</strong>)</small>
                            </div>
                            <span class="badge {{ Auth::user()->two_factor_enabled ? 'bg-success' : 'bg-secondary' }} rounded-pill ms-auto">
                                {{ Auth::user()->two_factor_enabled ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </button>
                    </form>

                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="quick-action-btn w-100 border-danger text-start">
                            <div class="qa-icon" style="background: #ffebee;">
                                <i class="fas fa-sign-out-alt text-danger"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-danger">{{ __('Logout') }}</div>
                                <small class="text-muted">{{ __('Keluar dari akun ini') }}</small>
                            </div>
                        </button>
                    </form>
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

        // Current user telegram ID to ignore validation if unchanged
        const currentTelegramId = "{{ Auth::user()->telegram_id }}";

        telegramInput.addEventListener('input', function() {
            clearTimeout(checkTimeout);
            let val = this.value.trim();

            if (!val || val === currentTelegramId) {
                feedbackElem.innerHTML = '';
                saveBtn.disabled = false;
                return;
            }

            // Tampilkan loading state
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
                    saveBtn.disabled = false; // fallback allow
                });
            }, 500); // 500ms debounce
        });
    });
</script>
@endsection
