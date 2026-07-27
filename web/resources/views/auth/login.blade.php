<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Login / Daftar') }} — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    
    {{-- Google Fonts: Outfit --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    {{-- Bootstrap 5.3 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    {{-- Font Awesome 6 --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    {{-- Design System Stylesheets --}}
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}" rel="stylesheet">
    
    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    {{-- Cloudflare Turnstile --}}
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    
    {{-- Telegram WebApp SDK --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    
    {{-- Theme Initialization & WebApp Auth --}}
    <script>
        // Init saved theme from localStorage
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();

        document.addEventListener('DOMContentLoaded', function() {
            if (window.Telegram && window.Telegram.WebApp) {
                const tg = window.Telegram.WebApp;
                tg.ready();
                if (tg.initData) {
                    const loader = document.getElementById('pageLoader');
                    if(loader) loader.classList.remove('fade-out');

                    fetch('/auth/telegram/webapp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ init_data: tg.initData })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '/dashboard';
                        } else {
                            if(loader) loader.classList.add('fade-out');
                        }
                    }).catch(err => {
                        console.error("WebApp Login Error:", err);
                        if(loader) loader.classList.add('fade-out');
                    });
                }
            }
        });
    </script>
</head>
<body class="auth-body">

    {{-- Atmospheric Brand Gradient --}}
    <div class="auth-background"></div>

    {{-- Loading Overlay --}}
    <div id="pageLoader" class="fade-out">
        <div class="spinner"></div>
    </div>

    {{-- Theme Toggle Button --}}
    <button class="auth-theme-toggle" id="themeToggle" title="{{ __('Ganti Tema') }}" aria-label="{{ __('Ganti Tema') }}">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <div class="auth-wrapper">
        {{-- Left Section: Form --}}
        <div class="auth-form-section">
            {{-- Brand Header --}}
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="auth-brand-badge">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div>
                    <h1 class="fw-bold mb-0 text-body" style="font-size: 1.6rem; line-height: 1.1;">{{ __('Jualan') }}</h1>
                    <div class="text-secondary fw-bold text-uppercase tracking-wider" style="font-size: 0.72rem; letter-spacing: 0.08em;">{{ config('app.name', 'Dzulfikrialifajri Store') }}</div>
                </div>
            </div>

            <div class="mb-4">
                <h2 class="fw-bold mb-1 text-body fs-4" id="auth-title">{{ __('Selamat Datang') }}</h2>
                <p class="text-muted small mb-0" id="auth-subtitle">{{ __('Silakan masuk untuk mengakses portal Anda.') }}</p>
            </div>

            {{-- Pill Navigation Tabs --}}
            <div class="auth-pill-container">
                <button type="button" class="auth-pill-btn active" id="tab-btn-login" onclick="switchAuthTab('login')">{{ __('Masuk') }}</button>
                <button type="button" class="auth-pill-btn" id="tab-btn-register" onclick="switchAuthTab('register')">{{ __('Daftar') }}</button>
            </div>

            {{-- Alerts Block --}}
            @if(session('error'))
                <div class="alert alert-danger py-2 px-3 small border-0 bg-danger-subtle text-danger rounded-3 mb-3 d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif
            @if(session('success'))
                <div class="alert alert-success py-2 px-3 small border-0 bg-success-subtle text-success rounded-3 mb-3 d-flex align-items-center">
                    <i class="fas fa-check-circle me-2"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger py-2 px-3 small border-0 bg-danger-subtle text-danger rounded-3 mb-3">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Hidden Bootstrap Tabs --}}
            <ul class="nav nav-pills d-none" id="authTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">{{ __('Masuk') }}</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane" type="button" role="tab">{{ __('Daftar') }}</button>
                </li>
            </ul>

            <div class="tab-content" id="authTabsContent">
                {{-- TAB MASUK (LOGIN) --}}
                <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
                    <form action="{{ route('login.post') }}" method="POST">
                        @csrf
                        <div class="auth-input-group">
                            <input type="text" name="login" class="form-control" placeholder="{{ __('Username atau Email') }}" value="{{ old('login') }}" required autofocus />
                            <i class="fas fa-user auth-input-icon"></i>
                        </div>
                        <div class="auth-input-group">
                            <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}" required />
                            <i class="fas fa-lock auth-input-icon"></i>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check ms-1">
                                <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                                <label class="form-check-label small text-muted cursor-pointer" for="rememberCheck">{{ __('Ingat Saya') }}</label>
                            </div>
                        </div>

                        {{-- Cloudflare Turnstile --}}
                        <div class="d-flex justify-content-center my-3">
                            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                        </div>

                        <button type="submit" class="auth-btn-primary">
                            <span>{{ __('Masuk Sistem') }}</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="position-relative my-4 text-center">
                        <hr class="text-secondary opacity-25">
                        <span class="position-absolute top-50 start-50 translate-middle px-3 small text-muted fw-bold bg-body" style="font-size: 0.75rem;">{{ __('ATAU') }}</span>
                    </div>

                    <form action="{{ route('auth.telegram.request') }}" method="POST" class="d-grid">
                        @csrf
                        <button type="submit" class="auth-btn-telegram">
                            <i class="fab fa-telegram text-info fs-5"></i> {{ __('Login dengan Telegram') }}
                        </button>
                    </form>
                </div>

                {{-- TAB DAFTAR (REGISTER) --}}
                <div class="tab-pane fade" id="register-pane" role="tabpanel">
                    <form action="{{ route('register.post') }}" method="POST">
                        @csrf
                        <div class="auth-input-group">
                            <input type="text" name="full_name" class="form-control" placeholder="{{ __('Nama Lengkap') }}" value="{{ old('full_name') }}" required />
                            <i class="fas fa-id-card auth-input-icon"></i>
                        </div>

                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <input type="text" name="username" class="form-control" placeholder="{{ __('Username') }}" value="{{ old('username') }}" required />
                                    <i class="fas fa-user-circle auth-input-icon"></i>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <input type="email" name="email" class="form-control" placeholder="{{ __('Email') }}" value="{{ old('email') }}" />
                                    <i class="fas fa-envelope auth-input-icon"></i>
                                </div>
                            </div>
                        </div>

                        <div class="auth-input-group">
                            <input type="number" name="telegram_id" id="telegram_id_reg" class="form-control" placeholder="ID Telegram (Opsional)" value="{{ old('telegram_id') }}" />
                            <i class="fab fa-telegram-plane auth-input-icon"></i>
                            <div id="telegram_id_feedback" class="text-muted fw-medium ms-2 mt-1" style="font-size: 0.72rem;">{{ __('Agar bisa otomatis login dengan Telegram nantinya.') }}</div>
                        </div>

                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <input type="password" name="password" class="form-control" placeholder="{{ __('Kata Sandi') }}" required />
                                    <i class="fas fa-key auth-input-icon"></i>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('Ulangi Sandi') }}" required />
                                    <i class="fas fa-lock auth-input-icon"></i>
                                </div>
                            </div>
                        </div>

                        {{-- Cloudflare Turnstile --}}
                        <div class="d-flex justify-content-center my-3">
                            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                        </div>

                        <button type="submit" id="btn-register" class="auth-btn-primary">
                            <span>{{ __('Daftar Sekarang') }}</span>
                            <i class="fas fa-user-plus"></i>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Footer / Store Info --}}
            <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                <div>
                    <span class="small text-muted fw-medium">© 2026</span>
                    <strong class="small text-secondary ms-1">{{ __('dzulfikrialifajri_store') }}</strong>
                </div>
            </div>
        </div>

        {{-- Right Section: Info & Showcase (Hero Pattern) --}}
        <div class="auth-info-section">
            <div class="auth-info-pattern"></div>
            <div class="auth-info-circle-1"></div>
            <div class="auth-info-circle-2"></div>

            <div class="position-relative z-1">
                {{-- Store Announcement Card --}}
                <div class="auth-announcement-card">
                    <div class="d-flex align-items-center gap-2 text-warning fw-bold mb-3" style="font-size: 0.95rem;">
                        <i class="fas fa-bullhorn"></i>
                        <span>{{ __('Informasi Store') }}</span>
                    </div>
                    
                    <div class="small text-white opacity-90 mb-0" style="line-height: 1.6;">
                        {!! $announcement !!}
                    </div>
                </div>

                {{-- Contact Admin Box --}}
                <div class="auth-contact-box">
                    <strong class="d-block small text-white mb-2">{{ __('Kontak Admin:') }}</strong>
                    <div class="d-flex flex-wrap">
                        <a href="https://wa.me/6282269245660" target="_blank" class="auth-contact-link">
                            <i class="fab fa-whatsapp text-success"></i> <span>082269245660 - WA</span>
                        </a>
                        <a href="https://t.me/dzulfikrialifajri" target="_blank" class="auth-contact-link">
                            <i class="fab fa-telegram text-info"></i> <span>@dzulfikrialifajri - Telegram</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="position-relative z-1 d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-white border-opacity-10">
                <span class="badge bg-white bg-opacity-10 text-white rounded-pill py-2 px-3 fw-medium" style="font-size: 0.75rem;">
                    <i class="fas fa-users me-1 text-info"></i> {{ __('Pengunjung Hari Ini:') }} <strong>{{ $todayVisitors ?? 0 }}</strong>
                </span>
                <span class="small fw-bold text-white opacity-50 text-uppercase tracking-wider" style="font-size: 0.7rem;">{{ __('Jualan v2.0') }}</span>
            </div>
        </div>
    </div>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Tab Switch Logic --}}
    <script>
        function switchAuthTab(tabName) {
            const loginBtn = document.getElementById('tab-btn-login');
            const registerBtn = document.getElementById('tab-btn-register');
            
            if (tabName === 'register') {
                document.getElementById('auth-title').innerText = '{{ __("Daftar Akun Baru") }}';
                document.getElementById('auth-subtitle').innerText = '{{ __("Silakan isi formulir untuk mendaftar sebagai pelanggan.") }}';
                
                loginBtn.classList.remove('active');
                registerBtn.classList.add('active');
                
                const tab = new bootstrap.Tab(document.getElementById('register-tab'));
                tab.show();
            } else {
                document.getElementById('auth-title').innerText = '{{ __("Selamat Datang") }}';
                document.getElementById('auth-subtitle').innerText = '{{ __("Silakan masuk untuk mengakses portal Anda.") }}';
                
                registerBtn.classList.remove('active');
                loginBtn.classList.add('active');
                
                const tab = new bootstrap.Tab(document.getElementById('login-tab'));
                tab.show();
            }
        }

        // Auto open register tab on error with register input
        document.addEventListener('DOMContentLoaded', function() {
            @if(old('username') && $errors->any())
                switchAuthTab('register');
            @endif
        });

        // Real-time Telegram ID availability check
        document.addEventListener('DOMContentLoaded', function() {
            const telegramInput = document.getElementById('telegram_id_reg');
            const feedbackElem = document.getElementById('telegram_id_feedback');
            const submitBtn = document.getElementById('btn-register');
            let checkTimeout = null;

            if (telegramInput) {
                telegramInput.addEventListener('input', function() {
                    const value = this.value.trim();
                    clearTimeout(checkTimeout);

                    if (!value) {
                        feedbackElem.innerHTML = '{{ __("Agar bisa otomatis login dengan Telegram nantinya.") }}';
                        feedbackElem.className = 'text-muted fw-medium ms-2 mt-1';
                        telegramInput.classList.remove('is-invalid', 'is-valid');
                        if (submitBtn) submitBtn.disabled = false;
                        return;
                    }

                    feedbackElem.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memeriksa ketersediaan ID...';
                    feedbackElem.className = 'text-info fw-medium ms-2 mt-1';
                    if (submitBtn) submitBtn.disabled = true;

                    checkTimeout = setTimeout(() => {
                        fetch(`{{ route('register.check-telegram') }}?telegram_id=${encodeURIComponent(value)}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.available) {
                                    feedbackElem.innerHTML = '<i class="fas fa-check-circle me-1"></i> ' + data.message;
                                    feedbackElem.className = 'text-success fw-bold ms-2 mt-1';
                                    telegramInput.classList.remove('is-invalid');
                                    telegramInput.classList.add('is-valid');
                                    if (submitBtn) submitBtn.disabled = false;
                                } else {
                                    feedbackElem.innerHTML = '<i class="fas fa-times-circle me-1"></i> ' + data.message;
                                    feedbackElem.className = 'text-danger fw-bold ms-2 mt-1';
                                    telegramInput.classList.remove('is-valid');
                                    telegramInput.classList.add('is-invalid');
                                    if (submitBtn) submitBtn.disabled = true;
                                }
                            })
                            .catch(err => {
                                console.error('Error checking telegram ID:', err);
                                feedbackElem.innerHTML = 'Gagal memeriksa koneksi.';
                                feedbackElem.className = 'text-warning fw-medium ms-2 mt-1';
                                if (submitBtn) submitBtn.disabled = false;
                            });
                    }, 500);
                });
            }
        });

        // Theme Toggle Handler
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggleBtn = document.getElementById('themeToggle');
            const themeIcon = document.getElementById('themeIcon');
            
            function updateThemeIcon(theme) {
                if (!themeIcon) return;
                if (theme === 'dark') {
                    themeIcon.className = 'fas fa-sun text-warning';
                } else {
                    themeIcon.className = 'fas fa-moon';
                }
            }

            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            updateThemeIcon(currentTheme);

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const existingTheme = document.documentElement.getAttribute('data-bs-theme');
                    const newTheme = existingTheme === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    updateThemeIcon(newTheme);
                });
            }
        });
    </script>
</body>
</html>
