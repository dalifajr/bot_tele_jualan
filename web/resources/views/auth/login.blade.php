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
    
    {{-- SweetAlert2 & Turnstile --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    {{-- Design System Stylesheet --}}
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .hero-banner-gradient {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            position: relative;
        }

        .hero-banner-gradient::before {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.15) 1px, transparent 1px);
            background-size: 16px 16px;
            opacity: 0.6;
            pointer-events: none;
        }

        .auth-card-wrapper {
            max-width: 980px;
            width: 100%;
            border-radius: 20px;
            backdrop-filter: blur(12px);
        }

        /* Page Loader Overlay */
        #pageLoader {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.85);
            opacity: 1; visibility: visible;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            backdrop-filter: blur(5px);
        }
        [data-bs-theme="dark"] #pageLoader {
            background-color: rgba(15, 23, 42, 0.9);
        }
        #pageLoader.fade-out {
            opacity: 0; visibility: hidden; pointer-events: none;
        }
        .spinner {
            width: 46px; height: 46px;
            border: 4px solid var(--bs-border-color);
            border-top: 4px solid #0d6efd;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <script>
        // Init theme before render to prevent white flash
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
<body class="bg-body-tertiary">

    <!-- Page Loader -->
    <div id="pageLoader">
        <div class="spinner"></div>
    </div>

    <!-- Universal Header Navbar -->
    <header class="py-3 px-4 bg-body border-bottom shadow-sm">
        <div class="container-fluid d-flex justify-content-between align-items-center" style="max-width: 1100px;">
            <a href="/" class="navbar-brand d-flex align-items-center gap-2 text-primary fw-bold text-decoration-none">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                    <i class="fas fa-shopping-bag fs-6"></i>
                </div>
                <span class="fs-5">{{ config('app.name', 'Dzulfikrialifajri Store') }}</span>
            </a>

            <div class="d-flex align-items-center gap-2">
                {{-- Quick Theme Switcher Button --}}
                <button class="btn btn-link link-body-emphasis p-2 text-decoration-none" id="themeToggleLogin" title="{{ __('Ganti Tema') }}" aria-label="{{ __('Ganti Tema') }}">
                    <i class="fas fa-moon fs-5" id="themeIconLogin"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-grow-1 d-flex align-items-center justify-content-center p-3 p-md-4">
        <div class="card border-0 shadow-lg overflow-hidden auth-card-wrapper">
            <div class="row g-0">
                
                {{-- Left Section: Hero & Announcement Showcase --}}
                <div class="col-12 col-lg-5 hero-banner-gradient p-4 p-md-5 text-white d-flex flex-column justify-content-between">
                    <div class="position-relative style-z2">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                                <i class="fas fa-shopping-bag fs-4"></i>
                            </div>
                            <div>
                                <h4 class="fw-extrabold mb-0 text-white" style="line-height: 1.1;">{{ __('Jualan') }}</h4>
                                <span class="badge bg-white text-primary rounded-pill small fw-bold mt-1 px-2.5 py-1">{{ config('app.name', 'Dzulfikrialifajri Store') }}</span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="fw-bold mb-2 text-white"><i class="fas fa-bullhorn text-warning me-2"></i>{{ __('Informasi Store') }}</h5>
                            <div class="small text-white-50 leading-relaxed p-3 rounded-4" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.15);">
                                {!! $announcement !!}
                            </div>
                        </div>

                        {{-- Kontak Admin --}}
                        <div class="small p-3 rounded-4 mb-3" style="background: rgba(0, 0, 0, 0.18); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.12);">
                            <strong class="d-block mb-2 text-white"><i class="fas fa-headset me-1 text-info"></i> {{ __('Kontak Bantuan Admin:') }}</strong>
                            <a href="https://wa.me/6282269245660" target="_blank" class="text-white text-decoration-none d-block mb-1"><i class="fab fa-whatsapp text-success me-1"></i> 082269245660 - WhatsApp</a>
                            <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-white text-decoration-none d-block"><i class="fab fa-telegram text-info me-1"></i> @dzulfikrialifajri - Telegram</a>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top border-white border-opacity-10 position-relative style-z2">
                        <span class="badge bg-white bg-opacity-20 text-white rounded-pill py-2 px-3 shadow-sm border border-white border-opacity-10" style="font-size: 0.75rem;">
                            <i class="fas fa-users me-1 text-info"></i> {{ __('Pengunjung Hari Ini:') }} <strong>{{ $todayVisitors ?? 0 }}</strong>
                        </span>
                        <span class="small text-white-50 font-monospace" style="font-size: 0.7rem;">v2.0</span>
                    </div>
                </div>

                {{-- Right Section: Auth Form Container --}}
                <div class="col-12 col-lg-7 p-4 p-md-5 bg-body d-flex flex-column justify-content-center">
                    
                    <div class="mb-4">
                        <h3 class="fw-bold mb-1" id="auth-title">{{ __('Selamat Datang') }}</h3>
                        <p class="text-muted small mb-0" id="auth-subtitle">{{ __('Silakan masuk untuk mengakses akun Anda.') }}</p>
                    </div>

                    {{-- Auth Pills Tab Switcher --}}
                    <div class="bg-body-tertiary p-1.5 rounded-pill mb-4 d-flex gap-1" id="auth-tab-container">
                        <button type="button" class="btn btn-sm rounded-pill flex-grow-1 fw-bold py-2 btn-primary" id="tab-btn-login" onclick="switchAuthTab('login')">
                            <i class="fas fa-sign-in-alt me-1.5"></i>{{ __('Masuk') }}
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill flex-grow-1 fw-bold py-2 btn-light text-secondary" id="tab-btn-register" onclick="switchAuthTab('register')">
                            <i class="fas fa-user-plus me-1.5"></i>{{ __('Daftar') }}
                        </button>
                    </div>

                    {{-- Session Alerts --}}
                    @if(session('error'))
                        <div class="alert alert-danger shadow-sm rounded-4 mb-3 small"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
                    @endif
                    @if(session('success'))
                        <div class="alert alert-success shadow-sm rounded-4 mb-3 small"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger shadow-sm rounded-4 mb-3 small">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Hidden Bootstrap Nav Tabs --}}
                    <ul class="nav nav-pills d-none" id="authTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">{{ __('Masuk') }}</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane" type="button" role="tab">{{ __('Daftar') }}</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="authTabsContent">
                        
                        {{-- TAB LOGIN --}}
                        <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
                            <form action="{{ route('login.post') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">{{ __('Username atau Email') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-body-tertiary border-0 text-muted"><i class="fas fa-user"></i></span>
                                        <input type="text" name="login" class="form-control bg-body-tertiary border-0 px-3" placeholder="{{ __('Username atau Email Anda') }}" value="{{ old('login') }}" required autofocus />
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">{{ __('Kata Sandi') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-body-tertiary border-0 text-muted"><i class="fas fa-lock"></i></span>
                                        <input type="password" name="password" class="form-control bg-body-tertiary border-0 px-3" placeholder="{{ __('Masukkan password...') }}" required />
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="form-check mb-0">
                                        <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                                        <label class="form-check-label small text-muted" for="rememberCheck">{{ __('Ingat Saya') }}</label>
                                    </div>
                                </div>

                                {{-- Cloudflare Turnstile Captcha --}}
                                <div class="d-flex justify-center my-3">
                                    <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                                </div>

                                <button type="submit" class="btn btn-primary rounded-pill py-2.5 w-100 fw-bold shadow-sm mb-3">
                                    <span>{{ __('Masuk Sistem') }}</span> <i class="fas fa-arrow-right ms-1"></i>
                                </button>
                            </form>

                            <div class="position-relative my-3 text-center">
                                <hr class="opacity-25">
                                <span class="position-absolute top-50 start-50 translate-middle bg-body px-3 small text-muted fw-bold" style="font-size: 0.75rem;">{{ __('ATAU') }}</span>
                            </div>

                            <form action="{{ route('auth.telegram.request') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-info rounded-pill py-2.5 w-100 fw-bold">
                                    <i class="fab fa-telegram me-1"></i> {{ __('Login dengan Telegram') }}
                                </button>
                            </form>
                        </div>

                        {{-- TAB REGISTER --}}
                        <div class="tab-pane fade" id="register-pane" role="tabpanel">
                            <form action="{{ route('register.post') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">{{ __('Nama Lengkap') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-body-tertiary border-0 text-muted"><i class="fas fa-id-card"></i></span>
                                        <input type="text" name="full_name" class="form-control bg-body-tertiary border-0 px-3" placeholder="{{ __('Nama Lengkap Anda') }}" value="{{ old('full_name') }}" required />
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label text-muted small fw-bold">{{ __('Username') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-body-tertiary border-0 text-muted"><i class="fas fa-user-circle"></i></span>
                                            <input type="text" name="username" class="form-control bg-body-tertiary border-0 px-3" placeholder="{{ __('Username unik') }}" value="{{ old('username') }}" required />
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label text-muted small fw-bold">{{ __('Email') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-body-tertiary border-0 text-muted"><i class="fas fa-envelope"></i></span>
                                            <input type="email" name="email" class="form-control bg-body-tertiary border-0 px-3" placeholder="{{ __('email@domain.com') }}" value="{{ old('email') }}" />
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">{{ __('ID Telegram (Opsional)') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-body-tertiary border-0 text-muted"><i class="fab fa-telegram-plane"></i></span>
                                        <input type="number" name="telegram_id" id="telegram_id_reg" class="form-control bg-body-tertiary border-0 px-3" placeholder="Contoh: 12345678" value="{{ old('telegram_id') }}" />
                                    </div>
                                    <div id="telegram_id_feedback" class="form-text small mt-1" style="font-size: 0.72rem;">{{ __('Agar bisa otomatis login dengan Telegram nantinya.') }}</div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label text-muted small fw-bold">{{ __('Kata Sandi') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-body-tertiary border-0 text-muted"><i class="fas fa-key"></i></span>
                                            <input type="password" name="password" class="form-control bg-body-tertiary border-0 px-3" placeholder="{{ __('Password...') }}" required />
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label text-muted small fw-bold">{{ __('Ulangi Sandi') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-body-tertiary border-0 text-muted"><i class="fas fa-lock"></i></span>
                                            <input type="password" name="password_confirmation" class="form-control bg-body-tertiary border-0 px-3" placeholder="{{ __('Konfirmasi...') }}" required />
                                        </div>
                                    </div>
                                </div>

                                {{-- Cloudflare Turnstile --}}
                                <div class="d-flex justify-center my-3">
                                    <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                                </div>

                                <button type="submit" id="btn-register" class="btn btn-primary rounded-pill py-2.5 w-100 fw-bold shadow-sm">
                                    <span>{{ __('Daftar Sekarang') }}</span> <i class="fas fa-user-plus ms-1"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Universal Footer -->
    <footer class="py-3 px-4 bg-body border-top text-center text-muted small">
        <div class="container-fluid" style="max-width: 1100px;">
            <span>© 2026 <strong>{{ config('app.name', 'Dzulfikrialifajri Store') }}</strong>. {{ __('Seluruh hak cipta dilindungi.') }}</span>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switcher logic
        function switchAuthTab(tabName) {
            const loginBtn = document.getElementById('tab-btn-login');
            const registerBtn = document.getElementById('tab-btn-register');
            
            if (tabName === 'register') {
                document.getElementById('auth-title').innerText = 'Daftar Akun Baru';
                document.getElementById('auth-subtitle').innerText = 'Silakan isi formulir untuk mendaftar sebagai pelanggan.';
                
                loginBtn.className = 'btn btn-sm rounded-pill flex-grow-1 fw-bold py-2 btn-light text-secondary';
                registerBtn.className = 'btn btn-sm rounded-pill flex-grow-1 fw-bold py-2 btn-primary';
                
                var tab = new bootstrap.Tab(document.getElementById('register-tab'));
                tab.show();
            } else {
                document.getElementById('auth-title').innerText = 'Selamat Datang';
                document.getElementById('auth-subtitle').innerText = 'Silakan masuk untuk mengakses akun Anda.';
                
                registerBtn.className = 'btn btn-sm rounded-pill flex-grow-1 fw-bold py-2 btn-light text-secondary';
                loginBtn.className = 'btn btn-sm rounded-pill flex-grow-1 fw-bold py-2 btn-primary';
                
                var tab = new bootstrap.Tab(document.getElementById('login-tab'));
                tab.show();
            }
        }

        // Auto open register tab on error with register input
        document.addEventListener('DOMContentLoaded', function() {
            @if(old('username') && $errors->any())
                switchAuthTab('register');
            @endif

            // Hide Page Loader
            const loader = document.getElementById('pageLoader');
            if (loader) {
                setTimeout(() => loader.classList.add('fade-out'), 150);
            }

            // Theme Toggle Logic
            const themeToggleBtn = document.getElementById('themeToggleLogin');
            const themeIcon = document.getElementById('themeIconLogin');

            function updateThemeUI(theme) {
                document.documentElement.setAttribute('data-bs-theme', theme);
                if (themeIcon) {
                    themeIcon.className = theme === 'dark' ? 'fas fa-sun fs-5 text-warning' : 'fas fa-moon fs-5';
                }
            }

            const currentTheme = localStorage.getItem('theme') || 'light';
            updateThemeUI(currentTheme);

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const activeTheme = document.documentElement.getAttribute('data-bs-theme');
                    const newTheme = activeTheme === 'dark' ? 'light' : 'dark';
                    localStorage.setItem('theme', newTheme);
                    updateThemeUI(newTheme);
                });
            }
        });

        // Real-time Telegram ID availability check
        document.addEventListener('DOMContentLoaded', function() {
            let telegramInput = document.getElementById('telegram_id_reg');
            if (!telegramInput) return;
            
            let feedbackElem = document.getElementById('telegram_id_feedback');
            let defaultFeedback = '{{ __("Agar bisa otomatis login dengan Telegram nantinya.") }}';
            let saveBtn = document.getElementById('btn-register');
            let checkTimeout;

            telegramInput.addEventListener('input', function() {
                clearTimeout(checkTimeout);
                let val = this.value.trim();

                if (!val) {
                    feedbackElem.className = 'form-text small mt-1';
                    feedbackElem.innerHTML = defaultFeedback;
                    saveBtn.disabled = false;
                    return;
                }

                feedbackElem.className = 'form-text small mt-1 text-muted';
                feedbackElem.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>{{ __("Mengecek...") }}';
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
                            feedbackElem.className = 'form-text small mt-1 text-success';
                            feedbackElem.innerHTML = `<i class="fas fa-check-circle me-1"></i>${data.message}`;
                            saveBtn.disabled = false;
                        } else {
                            feedbackElem.className = 'form-text small mt-1 text-danger';
                            feedbackElem.innerHTML = `<i class="fas fa-times-circle me-1"></i>${data.message}`;
                            saveBtn.disabled = true;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        feedbackElem.className = 'form-text small mt-1 text-danger';
                        feedbackElem.innerHTML = '{{ __("Gagal mengecek ID Telegram.") }}';
                        saveBtn.disabled = false; 
                    });
                }, 500);
            });
        });
    </script>
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
