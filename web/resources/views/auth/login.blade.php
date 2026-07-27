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
    
    {{-- Custom SIMAK-style App CSS --}}
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    
    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    {{-- Turnstile Captcha --}}
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    
    {{-- Telegram WebApp SDK --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
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

    <style>
        .auth-hero-banner {
            background: linear-gradient(135deg, #0d47a1 0%, #1565c0 50%, #1e88e5 100%);
            position: relative;
            overflow: hidden;
        }
        .hero-pattern-dots {
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            opacity: 0.12;
            background-image: radial-gradient(#ffffff 1.5px, transparent 1.5px);
            background-size: 22px 22px;
        }
        .hero-orb-1 {
            position: absolute;
            top: -80px; right: -80px;
            width: 280px; height: 280px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }
        .hero-orb-2 {
            position: absolute;
            bottom: -60px; left: -60px;
            width: 220px; height: 220px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        .form-input-group .form-icon {
            position: absolute;
            left: 1.1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--bs-secondary-color);
            z-index: 5;
            font-size: 0.95rem;
        }
        .form-input-group .form-control {
            padding-left: 2.8rem;
            border-radius: 12px;
        }
        .auth-nav-pills {
            background-color: var(--bs-tertiary-bg);
            padding: 5px;
            border-radius: 14px;
            border: 1px solid var(--glass-border);
        }
        .auth-nav-pills .nav-link {
            border-radius: 10px;
            font-weight: 600;
            color: var(--bs-secondary-color);
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        .auth-nav-pills .nav-link.active {
            background-color: var(--primary-color) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
        }
    </style>
</head>
<body class="bg-body-tertiary min-vh-100 d-flex align-items-center justify-content-center py-4 position-relative">

    {{-- Page Loader Overlay --}}
    <div id="pageLoader">
        <div class="spinner"></div>
    </div>

    {{-- Top Utility Bar (Theme Toggle & Home Link) --}}
    <div class="position-absolute top-0 end-0 p-3 p-md-4 d-flex align-items-center gap-2" style="z-index: 1050;">
        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 d-flex align-items-center gap-2" id="themeToggle" title="{{ __('Toggle Theme') }}">
            <i class="fas fa-moon" id="themeIcon"></i>
            <span class="d-none d-sm-inline">{{ __('Tema') }}</span>
        </button>
    </div>

    <div class="container px-3 px-md-4 my-auto">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10 col-xl-9">
                <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 24px;">
                    <div class="row g-0">
                        {{-- Left Column: Form Section --}}
                        <div class="col-12 col-md-7 p-4 p-md-5 d-flex flex-column justify-content-center bg-body">
                            
                            {{-- Brand Logo --}}
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 46px; height: 46px; font-size: 1.25rem;">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold m-0 text-primary" style="letter-spacing: -0.5px;">{{ config('app.name', 'Dzulfikrialifajri Store') }}</h4>
                                    <span class="text-secondary small fw-semibold uppercase tracking-wider" style="font-size: 0.75rem;">{{ __('Digital Marketplace') }}</span>
                                </div>
                            </div>

                            {{-- Title & Subtitle --}}
                            <div class="mb-4">
                                <h4 class="fw-bold mb-1" id="auth-title">{{ __('Selamat Datang') }}</h4>
                                <p class="text-secondary small mb-0" id="auth-subtitle">{{ __('Silakan masuk untuk mengakses akun Anda.') }}</p>
                            </div>

                            {{-- Auth Tab Pills Switcher --}}
                            <ul class="nav nav-pills nav-justified auth-nav-pills mb-4" id="authPills" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="tab-btn-login" type="button" onclick="switchAuthTab('login')">
                                        <i class="fas fa-sign-in-alt me-1"></i> {{ __('Masuk') }}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tab-btn-register" type="button" onclick="switchAuthTab('register')">
                                        <i class="fas fa-user-plus me-1"></i> {{ __('Daftar') }}
                                    </button>
                                </li>
                            </ul>

                            {{-- Flash Alert Messages --}}
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2 px-3 small border-0 bg-danger-subtle text-danger mb-3" role="alert">
                                    <i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}
                                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                                </div>
                            @endif
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show rounded-3 py-2 px-3 small border-0 bg-success-subtle text-success mb-3" role="alert">
                                    <i class="fas fa-check-circle me-1"></i>{{ session('success') }}
                                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                                </div>
                            @endif
                            @if($errors->any())
                                <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2 px-3 small border-0 bg-danger-subtle text-danger mb-3">
                                    <ul class="mb-0 ps-3">
                                        @foreach($errors->all() as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            {{-- Hidden Bootstrap Tab Trigger Elements --}}
                            <ul class="nav nav-pills d-none" id="authTabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">{{ __('Masuk') }}</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane" type="button" role="tab">{{ __('Daftar') }}</button>
                                </li>
                            </ul>

                            {{-- Tab Content Panes --}}
                            <div class="tab-content" id="authTabsContent">
                                
                                {{-- TAB MASUK (LOGIN) --}}
                                <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
                                    <form action="{{ route('login.post') }}" method="POST" class="d-flex flex-column gap-3">
                                        @csrf
                                        <div class="form-input-group position-relative">
                                            <i class="fas fa-user form-icon"></i>
                                            <input type="text" name="login" class="form-control" placeholder="{{ __('Username atau Email') }}" value="{{ old('login') }}" required autofocus />
                                        </div>
                                        <div class="form-input-group position-relative">
                                            <i class="fas fa-lock form-icon"></i>
                                            <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}" required />
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="form-check m-0">
                                                <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                                                <label class="form-check-label small text-secondary" for="rememberCheck">{{ __('Ingat Saya') }}</label>
                                            </div>
                                        </div>

                                        {{-- Cloudflare Turnstile --}}
                                        <div class="d-flex justify-content-center my-1">
                                            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                                        </div>

                                        <button type="submit" class="btn btn-primary rounded-pill py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                                            <span>{{ __('Masuk Sistem') }}</span>
                                            <i class="fas fa-arrow-right small"></i>
                                        </button>
                                    </form>

                                    <div class="position-relative my-4 text-center">
                                        <hr class="text-secondary opacity-25 m-0">
                                        <span class="position-absolute top-50 start-50 translate-middle bg-body px-3 small text-secondary fw-semibold" style="font-size: 0.75rem;">{{ __('ATAU') }}</span>
                                    </div>

                                    <form action="{{ route('auth.telegram.request') }}" method="POST" class="d-grid">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary rounded-pill py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2">
                                            <i class="fab fa-telegram fs-5 text-info"></i> {{ __('Login dengan Telegram') }}
                                        </button>
                                    </form>
                                </div>

                                {{-- TAB DAFTAR (REGISTER) --}}
                                <div class="tab-pane fade" id="register-pane" role="tabpanel">
                                    <form action="{{ route('register.post') }}" method="POST" class="d-flex flex-column gap-3">
                                        @csrf
                                        <div class="form-input-group position-relative">
                                            <i class="fas fa-id-card form-icon"></i>
                                            <input type="text" name="full_name" class="form-control" placeholder="{{ __('Nama Lengkap') }}" value="{{ old('full_name') }}" required />
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="form-input-group position-relative">
                                                    <i class="fas fa-user-circle form-icon"></i>
                                                    <input type="text" name="username" class="form-control" placeholder="{{ __('Username') }}" value="{{ old('username') }}" required />
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-input-group position-relative">
                                                    <i class="fas fa-envelope form-icon"></i>
                                                    <input type="email" name="email" class="form-control" placeholder="{{ __('Email') }}" value="{{ old('email') }}" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-input-group position-relative">
                                            <i class="fab fa-telegram-plane form-icon"></i>
                                            <input type="number" name="telegram_id" id="telegram_id_reg" class="form-control" placeholder="ID Telegram (Opsional)" value="{{ old('telegram_id') }}" />
                                            <div id="telegram_id_feedback" class="text-secondary small mt-1 ms-1" style="font-size: 0.72rem;">
                                                {{ __('Agar bisa otomatis login dengan Telegram nantinya.') }}
                                            </div>
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="form-input-group position-relative">
                                                    <i class="fas fa-key form-icon"></i>
                                                    <input type="password" name="password" class="form-control" placeholder="{{ __('Kata Sandi') }}" required />
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-input-group position-relative">
                                                    <i class="fas fa-lock form-icon"></i>
                                                    <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('Ulangi Sandi') }}" required />
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Cloudflare Turnstile --}}
                                        <div class="d-flex justify-content-center my-1">
                                            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                                        </div>

                                        <button type="submit" id="btn-register" class="btn btn-primary rounded-pill py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                                            <span>{{ __('Daftar Sekarang') }}</span>
                                            <i class="fas fa-user-plus small"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Footer Copyright --}}
                            <div class="mt-4 pt-3 border-top text-center text-md-start">
                                <span class="text-secondary small">© 2026 <strong>{{ config('app.name', 'Dzulfikrialifajri Store') }}</strong>. {{ __('Hak Cipta Dilindungi.') }}</span>
                            </div>
                        </div>

                        {{-- Right Column: Showcase & Info Section (Hero Gradient Banner) --}}
                        <div class="col-12 col-md-5 auth-hero-banner p-4 p-md-5 text-white d-flex flex-column justify-content-between position-relative">
                            <div class="hero-pattern-dots"></div>
                            <div class="hero-orb-1"></div>
                            <div class="hero-orb-2"></div>

                            <div class="position-relative z-1 mb-4">
                                <span class="badge bg-white text-primary rounded-pill px-3 py-2 mb-3 text-uppercase tracking-wider fw-bold shadow-sm" style="font-size: 0.72rem;">
                                    <i class="fas fa-bullhorn text-warning me-1"></i> {{ __('Informasi Toko') }}
                                </span>
                                <div class="fs-6 fw-normal text-white-90 leading-relaxed mb-4">
                                    {!! $announcement !!}
                                </div>
                            </div>

                            <div class="position-relative z-1 mt-auto">
                                {{-- Contact Box Translucent Glass --}}
                                <div class="p-3 rounded-4 mb-3 border" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(10px); border-color: rgba(255, 255, 255, 0.2) !important;">
                                    <strong class="d-block mb-2 text-white small"><i class="fas fa-headset me-1 text-warning"></i> {{ __('Bantuan & Layanan Admin:') }}</strong>
                                    <a href="https://wa.me/6282269245660" target="_blank" class="text-white text-decoration-none small d-block mb-1">
                                        <i class="fab fa-whatsapp text-success me-1"></i> 082269245660 - WhatsApp
                                    </a>
                                    <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-white text-decoration-none small d-block">
                                        <i class="fab fa-telegram text-info me-1"></i> @dzulfikrialifajri - Telegram
                                    </a>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-white bg-opacity-20 text-white rounded-pill py-2 px-3 shadow-sm border border-white border-opacity-20" style="font-size: 0.72rem;">
                                        <i class="fas fa-users me-1 text-info"></i> {{ __('Pengunjung Hari Ini:') }} {{ $todayVisitors ?? 0 }}
                                    </span>
                                    <span class="text-white-50 small fw-semibold" style="font-size: 0.7rem;">v2.0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bootstrap Bundle JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Auth Tab Switching Script --}}
    <script>
        function switchAuthTab(tabName) {
            const loginBtn = document.getElementById('tab-btn-login');
            const registerBtn = document.getElementById('tab-btn-register');
            
            if (tabName === 'register') {
                document.getElementById('auth-title').innerText = '{{ __("Daftar Akun Baru") }}';
                document.getElementById('auth-subtitle').innerText = '{{ __("Silakan isi formulir untuk mendaftar sebagai pelanggan.") }}';
                
                loginBtn.classList.remove('active');
                registerBtn.classList.add('active');
                
                var tab = new bootstrap.Tab(document.getElementById('register-tab'));
                tab.show();
            } else {
                document.getElementById('auth-title').innerText = '{{ __("Selamat Datang") }}';
                document.getElementById('auth-subtitle').innerText = '{{ __("Silakan masuk untuk mengakses akun Anda.") }}';
                
                registerBtn.classList.remove('active');
                loginBtn.classList.add('active');
                
                var tab = new bootstrap.Tab(document.getElementById('login-tab'));
                tab.show();
            }
        }

        // Auto open register tab if register validation fails
        document.addEventListener('DOMContentLoaded', function() {
            @if(old('username') && $errors->any())
                switchAuthTab('register');
            @endif
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
                    feedbackElem.innerHTML = defaultFeedback;
                    saveBtn.disabled = false;
                    return;
                }

                feedbackElem.innerHTML = '<span class="text-secondary"><i class="fas fa-spinner fa-spin me-1"></i>{{ __("Mengecek...") }}</span>';
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
                        feedbackElem.innerHTML = '<span class="text-danger">{{ __("Gagal mengecek ID Telegram.") }}</span>';
                        saveBtn.disabled = false; 
                    });
                }, 500);
            });
        });
    </script>

    {{-- App Custom JS (Includes Theme Toggle, Top Loading Bar, etc) --}}
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
