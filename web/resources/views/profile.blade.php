@extends('layouts.app')

@section('title', __('Profil Saya'))
@section('page_subtitle', __('Profil'))

@section('content')
<div class="container-fluid px-0">
    <!-- Alert Flash Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 mb-3 d-flex align-items-center" role="alert">
            <i class="fas fa-check-circle me-2 fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-3" role="alert">
            <div class="d-flex align-items-center mb-1">
                <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                <strong>{{ __('Terdapat kesalahan pada inputan Anda:') }}</strong>
            </div>
            <ul class="mb-0 ps-3 mt-1 small">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Profile Header Card (Figma M3 Squircle 28px) -->
    <div class="m3-profile-header-card">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-3 gap-md-4">
                    <div class="m3-profile-avatar-circle overflow-hidden">
                        @if(Auth::user()->avatar)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url(Auth::user()->avatar) }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            {{ strtoupper(substr(Auth::user()->full_name ?? Auth::user()->username ?? 'U', 0, 1)) }}
                        @endif
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h2 class="h4 fw-bold mb-0 text-body">
                                {{ Auth::user()->full_name ?? Auth::user()->username ?? 'Pengguna' }}
                            </h2>
                            <span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2.5 py-1" style="font-size: 0.72rem;">
                                <i class="fas fa-user-tag me-1"></i>{{ strtoupper(Auth::user()->role ?? 'CUSTOMER') }}
                            </span>
                            @if(Auth::user()->two_factor_enabled)
                                <span class="badge rounded-pill bg-success-subtle text-success fw-semibold px-2.5 py-1" style="font-size: 0.72rem;">
                                    <i class="fas fa-shield-alt me-1"></i>{{ __('2FA Aktif') }}
                                </span>
                            @else
                                <span class="badge rounded-pill bg-secondary-subtle text-secondary fw-semibold px-2.5 py-1" style="font-size: 0.72rem;">
                                    <i class="fas fa-shield-alt me-1"></i>{{ __('2FA Nonaktif') }}
                                </span>
                            @endif
                        </div>
                        @if(Auth::user()->username)
                            <div class="text-secondary small mb-2">@_{{ Auth::user()->username }}</div>
                        @endif
                        <div class="d-flex align-items-center gap-2">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                    <i class="fas fa-sign-out-alt me-1"></i>{{ __('Keluar Akun') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Telegram Integration Quick Card -->
            <div class="col-lg-4">
                <div class="m3-data-row-card">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted fw-semibold d-flex align-items-center gap-1.5">
                            <i class="fab fa-telegram text-primary"></i> {{ __('Koneksi Telegram') }}
                        </span>
                        @if(Auth::user()->telegram_id)
                            <span class="badge rounded-pill bg-success-subtle text-success px-2 py-0.5" style="font-size: 0.72rem;">
                                <i class="fas fa-check me-1"></i>Terhubung
                            </span>
                        @else
                            <span class="badge rounded-pill bg-warning-subtle text-warning px-2 py-0.5" style="font-size: 0.72rem;">
                                <i class="fas fa-exclamation me-1"></i>Belum
                            </span>
                        @endif
                    </div>
                    <div class="fw-semibold text-body fs-6">
                        ID: {{ Auth::user()->telegram_id ?: __('Tidak Terhubung') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- M3 Subnav Tabs (Figma Expressive Horizontal Flat Navigation) -->
    <div class="m3-profile-tabs-nav" id="profile-tab" role="tablist">
        <button class="m3-profile-tab-btn active" id="tab-informasi-btn" data-bs-toggle="pill" data-bs-target="#tab-informasi" type="button" role="tab">
            <i class="fas fa-user-edit"></i> {{ __('Data Profil') }}
        </button>
        <button class="m3-profile-tab-btn" id="tab-keamanan-btn" data-bs-toggle="pill" data-bs-target="#tab-keamanan" type="button" role="tab">
            <i class="fas fa-key"></i> {{ __('Sandi & 2FA') }}
        </button>
        <button class="m3-profile-tab-btn" id="tab-telegram-btn" data-bs-toggle="pill" data-bs-target="#tab-telegram" type="button" role="tab">
            <i class="fab fa-telegram"></i> {{ __('Integrasi Bot') }}
        </button>
        <button class="m3-profile-tab-btn" id="tab-geolokasi-btn" data-bs-toggle="pill" data-bs-target="#tab-geolokasi" type="button" role="tab">
            <i class="fas fa-shield-alt"></i> {{ __('Keamanan IP') }}
        </button>
        <button class="m3-profile-tab-btn" id="tab-tema-btn" data-bs-toggle="pill" data-bs-target="#tab-tema" type="button" role="tab">
            <i class="fas fa-palette"></i> {{ __('Tema & Warna') }}
        </button>
    </div>

    <!-- Tab Content Panes -->
    <div class="tab-content" id="profile-tabContent">
        <!-- Tab 1: Data Profil -->
        <div class="tab-pane fade show active" id="tab-informasi" role="tabpanel">
            <div class="m3-profile-section-card">
                <!-- Summary View Container -->
                <div id="profile-summary-view" style="{{ $errors->any() ? 'display: none;' : '' }}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-0 text-body">{{ __('Informasi Akun') }}</h5>
                            <small class="text-secondary">{{ __('Data identitas akun terdaftar Anda') }}</small>
                        </div>
                        <button type="button" id="btn-toggle-edit-profile" class="btn btn-primary rounded-pill px-3 py-1.5 fw-semibold btn-sm">
                            <i class="fas fa-user-edit me-1"></i> {{ __('Edit Profil') }}
                        </button>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="m3-data-row-card">
                                <div class="small text-muted fw-semibold mb-1">
                                    <i class="fas fa-user me-1.5 text-primary"></i>{{ __('Nama Lengkap') }}
                                </div>
                                <div class="fw-semibold text-body">{{ Auth::user()->full_name }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="m3-data-row-card">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="small text-muted fw-semibold">
                                        <i class="fas fa-at me-1.5 text-primary"></i>{{ __('Username') }}
                                    </div>
                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary" style="font-size: 0.68rem;">
                                        <i class="fas fa-lock me-1"></i>Permanen
                                    </span>
                                </div>
                                <div class="fw-semibold text-body">{{ Auth::user()->username }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="m3-data-row-card">
                                <div class="small text-muted fw-semibold mb-1">
                                    <i class="fas fa-envelope me-1.5 text-primary"></i>{{ __('Alamat Email') }}
                                </div>
                                <div class="fw-semibold text-body">{{ Auth::user()->email ?: '-' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="m3-data-row-card">
                                <div class="small text-muted fw-semibold mb-1">
                                    <i class="fab fa-telegram me-1.5 text-primary"></i>{{ __('Telegram ID') }}
                                </div>
                                <div class="fw-semibold text-body d-flex align-items-center gap-2">
                                    <span>{{ Auth::user()->telegram_id ?: '-' }}</span>
                                    @if(Auth::user()->telegram_id)
                                        <span class="badge rounded-pill bg-success-subtle text-success" style="font-size: 0.72rem;">
                                            <i class="fas fa-check-circle me-1"></i>Tertaut
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Form Container -->
                <div id="profile-edit-form-container" style="{{ $errors->any() ? '' : 'display: none;' }}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-0 text-body">{{ __('Edit Informasi Akun') }}</h5>
                            <small class="text-secondary">{{ __('Perbarui nama lengkap, email, atau tautkan akun Telegram Anda') }}</small>
                        </div>
                        <button type="button" id="btn-cancel-edit-profile" class="btn btn-outline-secondary rounded-pill btn-sm px-3 fw-semibold">
                            <i class="fas fa-times me-1"></i> {{ __('Batal') }}
                        </button>
                    </div>

                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-muted">{{ __('Foto Profil (Avatar)') }}</label>
                                <input type="file" name="avatar" class="form-control rounded-3" accept="image/*">
                                <div class="form-text small text-muted"><i class="fas fa-info-circle me-1"></i>Format: JPG, PNG, WEBP, GIF. Maks: 2MB.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">{{ __('Nama Lengkap') }}</label>
                                <input type="text" name="full_name" class="form-control rounded-3" required value="{{ old('full_name', Auth::user()->full_name) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">
                                    {{ __('Username') }}
                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary ms-1" style="font-size: 0.65rem;">
                                        <i class="fas fa-lock me-1"></i>Permanen
                                    </span>
                                </label>
                                <input type="text" class="form-control rounded-3 bg-body-tertiary text-muted" value="{{ Auth::user()->username }}" readonly disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">{{ __('Alamat Email') }}</label>
                                <input type="email" name="email" class="form-control rounded-3" value="{{ old('email', Auth::user()->email) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">{{ __('Telegram ID') }}</label>
                                <input type="number" name="telegram_id" id="telegram_id_input" class="form-control rounded-3" value="{{ old('telegram_id', Auth::user()->telegram_id) }}">
                                <div id="telegram_id_feedback" class="form-text small mt-1"></div>
                            </div>
                            <div class="col-12 mt-4 d-flex gap-2">
                                <button type="submit" id="btn-save-profile" class="btn btn-primary rounded-pill px-4 fw-semibold">
                                    <i class="fas fa-save me-1"></i> {{ __('Simpan Perubahan') }}
                                </button>
                                <button type="button" id="btn-cancel-edit-profile-bottom" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
                                    {{ __('Batal') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab 2: Sandi & 2FA -->
        <div class="tab-pane fade" id="tab-keamanan" role="tabpanel">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="m3-profile-section-card h-100">
                        <h5 class="fw-bold mb-1 text-body">
                            <i class="fas fa-lock me-2 text-primary"></i>{{ __('Pengaturan Kata Sandi') }}
                        </h5>
                        <p class="small text-secondary mb-3">
                            {{ __('Atur kata sandi untuk login konvensional menggunakan Username dan Password.') }}
                        </p>

                        <form action="{{ route('profile.password.update') }}" method="POST">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">{{ __('Kata Sandi Baru') }}</label>
                                    <input type="password" name="password" class="form-control rounded-3" required placeholder="{{ __('Minimal 6 karakter') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-muted">{{ __('Ulangi Kata Sandi') }}</label>
                                    <input type="password" name="password_confirmation" class="form-control rounded-3" required>
                                </div>
                                <div class="col-12 mt-3">
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                                        <i class="fas fa-key me-1"></i> {{ __('Perbarui Kata Sandi') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="m3-security-banner h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-shield-alt fa-lg"></i>
                                <h5 class="fw-bold mb-0">{{ __('Verifikasi Dua Langkah (2FA)') }}</h5>
                            </div>
                            <p class="small mb-3 opacity-90">
                                {{ __('Kirim kode OTP konfirmasi keamanan langsung ke Telegram Anda setiap kali mendeteksi aktivitas masuk baru.') }}
                            </p>
                            <div class="p-2.5 rounded-3 bg-white bg-opacity-25 mb-3">
                                <small class="fw-semibold d-block">
                                    {{ __('Status OTP:') }} 
                                    <span class="badge {{ Auth::user()->two_factor_enabled ? 'bg-success text-white' : 'bg-secondary text-white' }} ms-1">
                                        {{ Auth::user()->two_factor_enabled ? __('Aktif') : __('Nonaktif') }}
                                    </span>
                                </small>
                            </div>
                        </div>

                        <form action="{{ route('profile.2fa.toggle') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn {{ Auth::user()->two_factor_enabled ? 'btn-light text-danger' : 'btn-dark' }} rounded-pill w-100 fw-semibold py-2">
                                {{ Auth::user()->two_factor_enabled ? __('Nonaktifkan 2FA') : __('Aktifkan 2FA Sekarang') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Integrasi Telegram -->
        <div class="tab-pane fade" id="tab-telegram" role="tabpanel">
            <div class="m3-profile-section-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fab fa-telegram text-primary fa-lg"></i>
                    <h5 class="fw-bold mb-0 text-body">{{ __('Kaitan Akun Telegram Bot') }}</h5>
                </div>
                <p class="small text-secondary mb-3">
                    {{ __('Menautkan akun Telegram memungkinkan Anda menerima notifikasi transaksi, lisensi akun otomatis, dan akses obrolan instan.') }}
                </p>

                @if(Auth::user()->telegram_id)
                    <div class="m3-data-row-card mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fab fa-telegram fa-2x text-primary"></i>
                            <div>
                                <div class="fw-bold text-success">{{ __('Akun Telegram Terhubung') }}</div>
                                <div class="small text-secondary">ID Telegram: <strong>{{ Auth::user()->telegram_id }}</strong></div>
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
                    <div class="m3-data-row-card mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                            <div>
                                <div class="fw-bold text-warning">{{ __('Akun Belum Terhubung ke Telegram') }}</div>
                                <small class="text-secondary">{{ __('Klik tombol di bawah untuk membuka bot Telegram dan mengaitkan akun secara instan.') }}</small>
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
        </div>

        <!-- Tab 4: Keamanan IP -->
        <div class="tab-pane fade" id="tab-geolokasi" role="tabpanel">
            <div class="m3-profile-section-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fas fa-globe text-primary fa-lg"></i>
                    <h5 class="fw-bold mb-0 text-body">{{ __('Informasi Keamanan & Sesi') }}</h5>
                </div>
                <p class="small text-secondary mb-3">
                    {{ __('Pencatatan alamat IP dan wilayah untuk memantau keamanan aktivitas login akun Anda.') }}
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <div class="m3-data-row-card">
                            <small class="text-muted d-block fw-semibold mb-1">{{ __('IP Pendaftaran:') }}</small>
                            <strong class="text-body">{{ Auth::user()->registration_ip ?: 'Tidak tercatat' }}</strong>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="m3-data-row-card">
                            <small class="text-muted d-block fw-semibold mb-1">{{ __('Negara Asal (IP Terakhir):') }}</small>
                            <strong class="text-primary">{{ Auth::user()->last_login_country ?: 'Indonesia (Lokal)' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="m3-data-row-card d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold text-body mb-0.5">{{ __('Riwayat Sesi Login') }}</div>
                        <small class="text-muted">{{ __('Lihat daftar perangkat aktif dan log akses akun Anda.') }}</small>
                    </div>
                    <a href="{{ route('profile.logins') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold text-nowrap">
                        <i class="fas fa-history me-1"></i> {{ __('Lihat Log Sesi') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Tab 5: Personalisasi Tema & Palet Warna -->
        <div class="tab-pane fade" id="tab-tema" role="tabpanel">
            <div class="m3-profile-section-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fas fa-palette text-primary fa-lg"></i>
                    <h5 class="fw-bold mb-0 text-body">{{ __('Personalisasi Warna Palet (Material You)') }}</h5>
                </div>
                <p class="small text-secondary mb-4">
                    {{ __('Pilih warna aksen utama yang Anda sukai. Warna pilihan Anda akan langsung diterapkan ke seluruh elemen tombol, badge, dan kontainer website.') }}
                </p>

                @php
                    $currentPalette = Auth::user()->theme_color ?: '#65558f';
                    $presets = [
                        ['name' => 'Lavender & Purple (Default M3)', 'color' => '#65558f'],
                        ['name' => 'Google Android Blue', 'color' => '#0b57d0'],
                        ['name' => 'Emerald Mint (Hijau Segar)', 'color' => '#00875a'],
                        ['name' => 'Ocean Cyan (Biru Langit)', 'color' => '#0288d1'],
                        ['name' => 'Sunset Amber (Oranye Senja)', 'color' => '#e65100'],
                        ['name' => 'Rose Crimson (Merah Elegan)', 'color' => '#b3261e'],
                    ];
                @endphp

                {{-- Preset Palettes Grid --}}
                <div class="row g-3 mb-4" id="palettePresetGrid">
                    @foreach($presets as $p)
                    <div class="col-sm-6 col-lg-4">
                        <div class="palette-option-card p-3 rounded-4 border d-flex align-items-center gap-3 cursor-pointer {{ strtolower($currentPalette) === strtolower($p['color']) ? 'active-palette' : '' }}" 
                             data-color="{{ $p['color'] }}" 
                             style="cursor: pointer; transition: all 0.2s ease; background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                            <div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 44px; height: 44px; background: {{ $p['color'] }}; font-size: 1.1rem;">
                                <i class="fas fa-check palette-check-icon {{ strtolower($currentPalette) === strtolower($p['color']) ? '' : 'd-none' }}"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-semibold text-body text-truncate" style="font-size: 0.88rem;">{{ $p['name'] }}</div>
                                <code class="small text-secondary text-uppercase">{{ $p['color'] }}</code>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Custom Color Picker Box --}}
                <div class="p-3.5 rounded-4 border mb-4" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-7">
                            <div class="fw-semibold text-body mb-0.5" style="font-size: 0.9rem;">
                                <i class="fas fa-eye-dropper text-primary me-1.5"></i>{{ __('Pilih Warna Kustom') }}
                            </div>
                            <small class="text-secondary">{{ __('Gunakan pemilih warna bebas untuk menyesuaikan palet sesuai selera pribadi Anda.') }}</small>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" id="customColorPicker" class="form-control form-control-color rounded-circle p-1 border-0 shadow-sm" value="{{ $currentPalette }}" style="width: 46px; height: 46px; cursor: pointer;">
                                <input type="text" id="customColorHex" class="form-control rounded-pill text-uppercase fw-semibold" value="{{ $currentPalette }}" readonly style="font-size: 0.88rem; max-width: 140px;">
                                <button type="button" id="btnApplyCustomColor" class="btn btn-sm btn-primary rounded-pill px-3 py-2 fw-semibold text-nowrap">
                                    {{ __('Terapkan') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Live Theme Preview Showcase --}}
                <div class="p-3.5 rounded-4 border mb-4" style="background: var(--surface-container-lowest); border-color: var(--outline-variant) !important;">
                    <div class="fw-semibold text-body mb-2" style="font-size: 0.88rem;">
                        <i class="fas fa-magic text-primary me-1.5"></i>{{ __('Pratinjau Komponen dengan Tema Pilihan Anda') }}
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold btn-sm">
                            <i class="fas fa-check me-1"></i>{{ __('Tombol Utama') }}
                        </button>
                        <button type="button" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-semibold btn-sm">
                            {{ __('Tombol Outline') }}
                        </button>
                        <span class="badge rounded-pill bg-primary px-3 py-1.5 fw-semibold" style="font-size: 0.78rem;">Badge Solid</span>
                        <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-1.5 fw-semibold" style="font-size: 0.78rem;">Badge Tonal</span>
                        <a href="javascript:void(0)" class="fw-semibold ms-2 text-decoration-none" style="font-size: 0.88rem; color: var(--primary);">{{ __('Tautan Berwarna') }}</a>
                    </div>
                </div>

                {{-- Reset Button --}}
                <div class="d-flex justify-content-end">
                    <button type="button" id="btnResetDefaultColor" class="btn btn-sm btn-outline-secondary rounded-pill px-4 py-2 fw-semibold">
                        <i class="fas fa-undo me-1.5"></i>{{ __('Reset ke Default M3') }}
                    </button>
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

        // === MATERIAL YOU PALETTE PICKER ENGINE ===
        const paletteCards = document.querySelectorAll('.palette-option-card');
        const customColorPicker = document.getElementById('customColorPicker');
        const customColorHex = document.getElementById('customColorHex');
        const btnApplyCustomColor = document.getElementById('btnApplyCustomColor');
        const btnResetDefaultColor = document.getElementById('btnResetDefaultColor');

        function applyAndSaveColor(hexColor) {
            if (!hexColor || !/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/.test(hexColor)) return;

            let c = hexColor;
            if (c.length === 4) {
                c = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
            }
            const r = parseInt(c.slice(1, 3), 16);
            const g = parseInt(c.slice(3, 5), 16);
            if (window.applyMaterialYouTheme) {
                window.applyMaterialYouTheme(c);
            }

            localStorage.setItem('user_primary_palette', c);
            if (customColorPicker) customColorPicker.value = c;
            if (customColorHex) customColorHex.value = c;

            // Update active state in UI
            paletteCards.forEach(card => {
                const cardColor = card.getAttribute('data-color');
                const checkIcon = card.querySelector('.palette-check-icon');
                if (cardColor.toLowerCase() === c.toLowerCase()) {
                    card.classList.add('active-palette');
                    if (checkIcon) checkIcon.classList.remove('d-none');
                } else {
                    card.classList.remove('active-palette');
                    if (checkIcon) checkIcon.classList.add('d-none');
                }
            });

            // Send AJAX to server
            fetch("{{ route('profile.theme-color') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ theme_color: c })
            }).then(res => res.json()).then(data => {
                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'bottom-end',
                        showConfirmButton: false,
                        timer: 2500,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: data.message || '{{ __('Warna tema berhasil disimpan!') }}'
                    });
                }
            }).catch(err => {
                console.error('Failed to save theme color on server:', err);
            });
        }

        paletteCards.forEach(card => {
            card.addEventListener('click', function() {
                const color = this.getAttribute('data-color');
                applyAndSaveColor(color);
            });
        });

        if (customColorPicker) {
            customColorPicker.addEventListener('input', function() {
                if (customColorHex) customColorHex.value = this.value;
                if (window.applyMaterialYouTheme) {
                    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                    window.applyMaterialYouTheme(this.value, isDark);
                }
            });
        }

        if (btnApplyCustomColor && customColorPicker) {
            btnApplyCustomColor.addEventListener('click', function() {
                applyAndSaveColor(customColorPicker.value);
            });
        }

        if (btnResetDefaultColor) {
            btnResetDefaultColor.addEventListener('click', function() {
                applyAndSaveColor('#65558f');
            });
        }

        if (hash === '#tab-tema' || hash === '#tema' || urlParams.get('tab') === 'tema') {
            const temaTabBtn = document.getElementById('tab-tema-btn');
            if (temaTabBtn) {
                const tab = new bootstrap.Tab(temaTabBtn);
                tab.show();
            }
        }
    });
</script>
@endsection
