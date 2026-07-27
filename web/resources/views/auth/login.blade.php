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
    
    {{-- SweetAlert2 & Turnstile & Telegram WebApp --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    {{-- Design System Stylesheets --}}
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}" rel="stylesheet">

    <script>
        // Init theme from localStorage
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
<body class="auth-page">

    {{-- Page Loader --}}
    <div id="pageLoader">
        <div class="spinner"></div>
    </div>

    {{-- Ambient Background Glows --}}
    <div class="auth-glow-1"></div>
    <div class="auth-glow-2"></div>

    {{-- Main Auth Card Wrapper --}}
    <div class="auth-wrapper shadow-lg">
        
        {{-- Left Section: Form --}}
        <div class="auth-section-left">
            
            {{-- Top Header with Brand & Theme Toggle --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center gap-2 text-primary fw-bold fs-4">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                        <i class="fas fa-shopping-bag fs-5"></i>
                    </div>
                    <div>
                        <div class="lh-1 fw-extrabold text-primary">{{ config('app.name', 'Dzulfikrialifajri Store') }}</div>
                        <span class="text-muted fw-normal small" style="font-size: 0.78rem;">Digital Store Portal</span>
                    </div>
                </div>

                {{-- Quick Theme Toggle --}}
                <button class="btn btn-link link-body-emphasis p-2 text-decoration-none" id="authThemeToggle" title="{{ __('Toggle Theme') }}" aria-label="{{ __('Ganti Tema') }}">
                    <i class="fas fa-moon fs-5" id="authThemeIcon"></i>
                </button>
            </div>

            <div class="mb-3">
                <h4 class="fw-bold mb-1" id="auth-title">{{ __('Selamat Datang') }}</h4>
                <p class="text-muted small mb-0" id="auth-subtitle">{{ __('Silakan masuk untuk mengakses portal pelanggan Anda.') }}</p>
            </div>

            {{-- Auth Nav Tabs (Pills) --}}
            <div class="auth-nav-pills mb-4">
                <button type="button" class="auth-nav-pill-btn active" id="tab-btn-login" onclick="switchAuthTab('login')">
                    <i class="fas fa-sign-in-alt me-1"></i>{{ __('Masuk') }}
                </button>
                <button type="button" class="auth-nav-pill-btn" id="tab-btn-register" onclick="switchAuthTab('register')">
                    <i class="fas fa-user-plus me-1"></i>{{ __('Daftar') }}
                </button>
            </div>

            {{-- Alert Flash Messages --}}
            @if(session('error'))
                <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3 shadow-sm">
                    <i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}
                </div>
            @endif
            @if(session('success'))
                <div class="alert alert-success py-2 small border-0 rounded-3 mb-3 shadow-sm">
                    <i class="fas fa-check-circle me-1"></i>{{ session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger py-2 small border-0 rounded-3 mb-3 shadow-sm">
                    <div class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('Terdapat kesalahan pada input:') }}</div>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Hidden Bootstrap Tab Trigger --}}
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
                        <div class="mb-3 auth-input-group">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="login" class="form-control" placeholder="{{ __('Username atau Email') }}" value="{{ old('login') }}" required autofocus />
                        </div>

                        <div class="mb-3 auth-input-group">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}" required />
                        </div>

                        <div class="d-flex justify-between align-items-center mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                                <label class="form-check-label small text-muted" for="rememberCheck">{{ __('Ingat Saya') }}</label>
                            </div>
                        </div>

                        {{-- Cloudflare Turnstile --}}
                        <div class="d-flex justify-content-center my-3">
                            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                        </div>

                        <button type="submit" class="btn btn-primary rounded-pill w-100 py-2.5 fw-bold shadow-sm lift-hover">
                            <span>{{ __('Masuk Sistem') }}</span>
                            <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>

                    <div class="position-relative my-4 text-center">
                        <hr class="text-secondary opacity-25">
                        <span class="position-absolute top-50 start-50 translate-middle bg-body px-3 small text-muted fw-bold">{{ __('ATAU') }}</span>
                    </div>

                    <form action="{{ route('auth.telegram.request') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary rounded-pill w-100 py-2.5 fw-bold lift-hover">
                            <i class="fab fa-telegram text-info me-2 fs-5"></i> {{ __('Login dengan Telegram') }}
                        </button>
                    </form>
                </div>

                {{-- TAB REGISTER --}}
                <div class="tab-pane fade" id="register-pane" role="tabpanel">
                    <form action="{{ route('register.post') }}" method="POST">
                        @csrf
                        <div class="mb-3 auth-input-group">
                            <i class="fas fa-id-card input-icon"></i>
                            <input type="text" name="full_name" class="form-control" placeholder="{{ __('Nama Lengkap') }}" value="{{ old('full_name') }}" required />
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="auth-input-group">
                                    <i class="fas fa-user-circle input-icon"></i>
                                    <input type="text" name="username" class="form-control" placeholder="{{ __('Username') }}" value="{{ old('username') }}" required />
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="auth-input-group">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" name="email" class="form-control" placeholder="{{ __('Email') }}" value="{{ old('email') }}" />
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 auth-input-group">
                            <i class="fab fa-telegram-plane input-icon"></i>
                            <input type="number" name="telegram_id" id="telegram_id_reg" class="form-control" placeholder="ID Telegram (Opsional)" value="{{ old('telegram_id') }}" />
                            <div id="telegram_id_feedback" class="form-text ms-1 mt-1" style="font-size: 0.75rem;">{{ __('Agar bisa otomatis login dengan Telegram nantinya.') }}</div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="auth-input-group">
                                    <i class="fas fa-key input-icon"></i>
                                    <input type="password" name="password" class="form-control" placeholder="{{ __('Kata Sandi') }}" required />
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="auth-input-group">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('Ulangi Sandi') }}" required />
                                </div>
                            </div>
                        </div>

                        {{-- Cloudflare Turnstile --}}
                        <div class="d-flex justify-content-center my-3">
                            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                        </div>

                        <button type="submit" id="btn-register" class="btn btn-primary rounded-pill w-100 py-2.5 fw-bold shadow-sm lift-hover">
                            <span>{{ __('Daftar Sekarang') }}</span>
                            <i class="fas fa-user-plus ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Footer Info --}}
            <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center text-muted small">
                <span>© 2026 <strong>{{ config('app.name', 'Dzulfikrialifajri Store') }}</strong></span>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1">v2.0</span>
            </div>
        </div>

        {{-- Right Section: Hero Showcase Banner --}}
        <div class="auth-section-right">
            <div>
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill bg-white bg-opacity-10 border border-white border-opacity-10 mb-4">
                    <i class="fas fa-sparkles text-warning"></i>
                    <span class="small fw-bold text-white">{{ __('Platform Jualan Produk Digital #1') }}</span>
                </div>
                <h2 class="fw-extrabold display-6 mb-3 lh-sm">{{ __('Transaksi Otomatis & Garansi Instan') }}</h2>
                <p class="text-white text-opacity-75 lead fs-6 mb-4">
                    {!! strip_tags($announcement ?? 'Temukan produk akun premium, VPN, dan garansi resmi dengan pengiriman stok otomatis 24/7.') !!}
                </p>

                {{-- Contact Box --}}
                <div class="p-3 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-10 mb-4">
                    <div class="fw-bold mb-2 text-white"><i class="fas fa-headset me-1 text-info"></i> {{ __('Bantuan & Layanan Admin:') }}</div>
                    <a href="https://wa.me/6282269245660" target="_blank" class="text-white text-decoration-none d-block small mb-1 hover-opacity"><i class="fab fa-whatsapp text-success me-1 fs-6"></i> 082269245660 (WhatsApp)</a>
                    <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-white text-decoration-none d-block small hover-opacity"><i class="fab fa-telegram text-info me-1 fs-6"></i> @dzulfikrialifajri (Telegram)</a>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center border-top border-white border-opacity-10 pt-3">
                <span class="badge bg-white bg-opacity-15 text-white rounded-pill px-3 py-2 fw-semibold">
                    <i class="fas fa-users me-1 text-warning"></i> {{ __('Pengunjung Hari Ini:') }} {{ $todayVisitors ?? 0 }}
                </span>
                <span class="small text-white text-opacity-50 fw-bold tracking-wider">SECURE 256-BIT SSL</span>
            </div>
        </div>
    </div>

    {{-- Bootstrap & Auth Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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
                document.getElementById('auth-subtitle').innerText = '{{ __("Silakan masuk untuk mengakses portal pelanggan Anda.") }}';
                
                registerBtn.classList.remove('active');
                loginBtn.classList.add('active');
                
                var tab = new bootstrap.Tab(document.getElementById('login-tab'));
                tab.show();
            }
        }

        // Theme Toggle Script
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggleBtn = document.getElementById('authThemeToggle');
            const themeIcon = document.getElementById('authThemeIcon');

            function updateThemeIcon(theme) {
                if (themeIcon) {
                    themeIcon.className = theme === 'dark' ? 'fas fa-sun fs-5 text-warning' : 'fas fa-moon fs-5';
                }
            }

            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            updateThemeIcon(currentTheme);

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const activeTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-bs-theme', activeTheme);
                    localStorage.setItem('theme', activeTheme);
                    updateThemeIcon(activeTheme);
                });
            }

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

                feedbackElem.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>{{ __("Mengecek...") }}</span>';
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
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
