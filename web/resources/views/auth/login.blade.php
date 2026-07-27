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
    
    {{-- Bootstrap 5.3 & Font Awesome 6 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    {{-- Custom Design System & Auth Styles --}}
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}" rel="stylesheet">
    
    {{-- Scripts: SweetAlert2, Cloudflare Turnstile, Telegram WebApp --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    
    {{-- Prevent Theme Flash Preloader --}}
    <script>
        (function() {
            const saved = localStorage.getItem('jualan-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', saved);
        })();
    </script>
    
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
</head>
<body class="auth-body" style="cursor: default;">

  <!-- Ambient Backdrops -->
  <div class="ambient-glow-1"></div>
  <div class="ambient-glow-2"></div>

  <!-- Page Loader Overlay -->
  <div id="pageLoader">
    <div class="spinner"></div>
  </div>

  <div class="login-wrapper">
    <!-- Left Section: Form -->
    <div class="login-section">
      <!-- Floating Theme Toggle (Light/Dark Mode) -->
      <button type="button" class="theme-toggle-auth" id="themeToggle" title="{{ __('Ganti Tema') }}" aria-label="{{ __('Ganti Tema') }}">
        <i class="fas fa-moon fs-5" id="themeIcon"></i>
      </button>

      <!-- Logo & Brand -->
      <div class="brand-header">
        <div class="d-flex align-items-center gap-3">
          <div class="d-flex align-items-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 44px; height: 44px; background: linear-gradient(135deg, #1b4ab2 0%, #1565c0 100%); z-index: 2;">
              <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 44px; height: 44px; background: linear-gradient(135deg, #00d2ff 0%, #00a8cc 100%); margin-left: -12px; border: 3px solid var(--auth-card-bg); z-index: 1;">
              <span class="fw-bold" style="font-size: 0.65rem;">{{ __('Mitra') }}</span>
            </div>
          </div>
          <div>
            <h1 class="brand-title">{{ __('Jualan') }}</h1>
            <div class="brand-subtitle">{{ config('app.name', 'Dzulfikrialifajri Store') }}</div>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <h2 class="fs-4 fw-extrabold tracking-tight mb-1" id="auth-title">{{ __('Selamat Datang') }}</h2>
        <p class="small text-muted fw-medium mb-0" id="auth-subtitle">{{ __('Silakan masuk untuk mengakses portal Anda.') }}</p>
      </div>

      <!-- Auth Tabs Navigation (Pill Selector) -->
      <div class="auth-tabs-container">
          <button type="button" class="auth-tab-btn active" id="tab-btn-login" onclick="switchAuthTab('login')">{{ __('Masuk') }}</button>
          <button type="button" class="auth-tab-btn" id="tab-btn-register" onclick="switchAuthTab('register')">{{ __('Daftar') }}</button>
      </div>

      <!-- Alerts Block -->
      @if(session('error'))
          <div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
      @endif
      @if(session('success'))
          <div class="alert alert-success py-2 small border-0 bg-success bg-opacity-10 text-success rounded-3 mb-3"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
      @endif
      @if($errors->any())
          <div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-3">
              <ul class="mb-0 ps-3">
                  @foreach($errors->all() as $err)
                      <li>{{ $err }}</li>
                  @endforeach
              </ul>
          </div>
      @endif

      <!-- Hidden Tab Pillars -->
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
              <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                  @csrf
                  <div class="form-group">
                      <input type="text" name="login" class="form-control" placeholder="{{ __('Username atau Email') }}" value="{{ old('login') }}" required autofocus />
                      <i class="fas fa-user form-icon"></i>
                  </div>
                  <div class="form-group">
                      <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}" required />
                      <i class="fas fa-lock form-icon"></i>
                  </div>

                  <div class="d-flex justify-content-between align-items-center mb-2">
                      <div class="form-check ms-1">
                          <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                          <label class="form-check-label small text-muted" for="rememberCheck">{{ __('Ingat Saya') }}</label>
                      </div>
                  </div>

                  <!-- Cloudflare Turnstile -->
                  <div class="d-flex justify-content-center my-3">
                      <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                  </div>

                  <button type="submit" class="btn-primary mt-2">
                      <span>{{ __('Masuk Sistem') }}</span>
                      <i class="fas fa-arrow-right small"></i>
                  </button>
              </form>

              <div class="auth-divider">
                  <span>{{ __('ATAU') }}</span>
              </div>

              <form action="{{ route('auth.telegram.request') }}" method="POST" class="d-grid">
                  @csrf
                  <button type="submit" class="btn-outline">
                      <i class="fab fa-telegram text-info fs-5"></i> {{ __('Login dengan Telegram') }}
                  </button>
              </form>
          </div>

          {{-- TAB DAFTAR (REGISTER) --}}
          <div class="tab-pane fade" id="register-pane" role="tabpanel">
              <form action="{{ route('register.post') }}" method="POST">
                  @csrf
                  <div class="form-group">
                      <input type="text" name="full_name" class="form-control" placeholder="{{ __('Nama Lengkap') }}" value="{{ old('full_name') }}" required />
                      <i class="fas fa-id-card form-icon"></i>
                  </div>

                  <div class="row g-2">
                      <div class="col-6">
                          <div class="form-group">
                              <input type="text" name="username" class="form-control" placeholder="{{ __('Username') }}" value="{{ old('username') }}" required />
                              <i class="fas fa-user-circle form-icon"></i>
                          </div>
                      </div>
                      <div class="col-6">
                          <div class="form-group">
                              <input type="email" name="email" class="form-control" placeholder="{{ __('Email') }}" value="{{ old('email') }}" />
                              <i class="fas fa-envelope form-icon"></i>
                          </div>
                      </div>
                  </div>

                  <div class="form-group">
                      <input type="number" name="telegram_id" id="telegram_id_reg" class="form-control" placeholder="ID Telegram (Opsional)" value="{{ old('telegram_id') }}" />
                      <i class="fab fa-telegram-plane form-icon"></i>
                      <div id="telegram_id_feedback" class="small text-muted ms-2 mt-1" style="font-size: 0.72rem;">{{ __('Agar bisa otomatis login dengan Telegram nantinya.') }}</div>
                  </div>

                  <div class="row g-2">
                      <div class="col-6">
                          <div class="form-group">
                              <input type="password" name="password" class="form-control" placeholder="{{ __('Kata Sandi') }}" required />
                              <i class="fas fa-key form-icon"></i>
                          </div>
                      </div>
                      <div class="col-6">
                          <div class="form-group">
                              <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('Ulangi Sandi') }}" required />
                              <i class="fas fa-lock form-icon"></i>
                          </div>
                      </div>
                  </div>

                  <!-- Cloudflare Turnstile -->
                  <div class="d-flex justify-content-center my-3">
                      <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                  </div>

                  <button type="submit" id="btn-register" class="btn-primary mt-2">
                      <span>{{ __('Daftar Sekarang') }}</span>
                      <i class="fas fa-user-plus small"></i>
                  </button>
              </form>
          </div>
      </div>

      <!-- Footer / Supported Info -->
      <div class="auth-footer">
        <div>
          <span class="fw-semibold">© 2026</span>
          <span class="fw-bold ms-1">{{ __('dzulfikrialifajri_store') }}</span>
        </div>
      </div>
    </div>

    <!-- Right Section: Info & Showcase -->
    <div class="info-section">
      <!-- Floating Glass Announcement Card -->
      <div class="glass-card w-full">
        <div class="glass-header">
          <i class="fas fa-bullhorn"></i>
          <span>{{ __('Informasi Store') }}</span>
        </div>
        
        <p class="small fw-medium leading-relaxed mb-4" style="color: rgba(255, 255, 255, 0.9);">
          {!! $announcement !!}
        </p>

        <!-- Kontak Admin Box -->
        <div class="small mb-4 p-3 rounded-4 border" style="background: rgba(255, 255, 255, 0.08); border-color: rgba(255, 255, 255, 0.15) !important; color: #fff;">
            <strong class="d-block mb-2 text-white">{{ __('Kontak Admin:') }}</strong>
            <a href="https://wa.me/6282269245660" target="_blank" class="text-white text-decoration-none mt-1 d-block"><i class="fab fa-whatsapp text-success me-1"></i> 082269245660 - WA</a>
            <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-white text-decoration-none mt-1 d-block"><i class="fab fa-telegram text-info me-1"></i> @dzulfikrialifajri - Telegram</a>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <span class="badge bg-white bg-opacity-10 text-white rounded-pill py-2 px-3 shadow-sm border border-white border-opacity-10" style="font-size: 0.72rem;">
                <i class="fas fa-users me-1 text-info"></i> Pengunjung Hari Ini: {{ $todayVisitors ?? 0 }}
            </span>
        </div>
      </div>
      <div class="position-absolute bottom-0 end-0 p-3 small fw-bold tracking-widest text-white-50 text-uppercase" style="font-size: 0.7rem;">{{ __('Jualan v2.0') }}</div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
      function switchAuthTab(tabName) {
          const loginBtn = document.getElementById('tab-btn-login');
          const registerBtn = document.getElementById('tab-btn-register');
          
          if (tabName === 'register') {
              document.getElementById('auth-title').innerText = 'Daftar Akun Baru';
              document.getElementById('auth-subtitle').innerText = 'Silakan isi formulir untuk mendaftar sebagai pelanggan.';
              
              loginBtn.classList.remove('active');
              registerBtn.classList.add('active');
              
              var tab = new bootstrap.Tab(document.getElementById('register-tab'));
              tab.show();
          } else {
              document.getElementById('auth-title').innerText = 'Selamat Datang';
              document.getElementById('auth-subtitle').innerText = 'Silakan masuk untuk mengakses portal Anda.';
              
              registerBtn.classList.remove('active');
              loginBtn.classList.add('active');
              
              var tab = new bootstrap.Tab(document.getElementById('login-tab'));
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
          let telegramInput = document.getElementById('telegram_id_reg');
          if (!telegramInput) return;
          
          let feedbackElem = document.getElementById('telegram_id_feedback');
          let defaultFeedback = 'Agar bisa otomatis login dengan Telegram nantinya.';
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
      });
  </script>
  <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
