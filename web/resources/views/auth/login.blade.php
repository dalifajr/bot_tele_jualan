<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="{{ request()->cookie('jualan-theme', 'light') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('jualan-theme') || (document.cookie.match(/(?:^|; )jualan-theme=([^;]*)/) || [])[1] || 'light';
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
                document.documentElement.style.colorScheme = savedTheme;
                if (!document.cookie.includes('jualan-theme=' + savedTheme)) {
                    document.cookie = "jualan-theme=" + savedTheme + ";path=/;max-age=31536000;SameSite=Lax";
                }
            } catch (e) {}
        })();
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Login / Daftar') }} — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    
    {{-- Google Sans Font System Setup --}}
    <style>
        @font-face {
            font-family: 'Google Sans';
            src: local('Google Sans Regular'), local('Google Sans'), local('Product Sans'),
                 url('https://fonts.gstatic.com/s/googlesans/v58/4Ua_rENHsxJlGDuGo1OIlJfC6l_24rlCK1Yo_Iqcsih3SAyH6cAwhX9RFD48TE63OOYKtrwEIJllpyw.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Google Sans';
            src: local('Google Sans Medium'), local('Google Sans-Medium'), local('Product Sans Medium'),
                 url('https://fonts.gstatic.com/s/googlesans/v58/4UabrENHsxJlGDuGo1OIlLU94YtzCwY.woff2') format('woff2');
            font-weight: 500 600;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Google Sans';
            src: local('Google Sans Bold'), local('Google Sans-Bold'), local('Product Sans Bold'),
                 url('https://fonts.gstatic.com/s/googlesans/v58/4UabrENHsxJlGDuGo1OIlLV154tzCwY.woff2') format('woff2');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Google Sans Display';
            src: local('Google Sans Display'), local('Google Sans'),
                 url('https://fonts.gstatic.com/s/googlesans/v58/4UabrENHsxJlGDuGo1OIlLU94YtzCwY.woff2') format('woff2');
            font-weight: 400 700;
            font-style: normal;
            font-display: swap;
        }
    </style>
    
    {{-- Bootstrap 5.3 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    {{-- Font Awesome 6 --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    {{-- Design System Stylesheets --}}
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.Telegram && window.Telegram.WebApp) {
                const tg = window.Telegram.WebApp;
                tg.ready();
                if (tg.initData) {
                    // Show loader overlay
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
                            window.location.href = data.redirect_url || '/dashboard';
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
<body style="cursor: default;">

  {{-- Theme Toggle Button --}}
  <div class="auth-theme-toggle">
    <button class="btn btn-link link-body-emphasis p-0" id="themeToggle" title="{{ __('Ganti Tema') }}" aria-label="{{ __('Ganti Tema') }}">
        <i class="fas fa-moon fs-5" id="themeIcon"></i>
    </button>
  </div>

  {{-- Loader Overlay: Skeleton Shimmer Form --}}
  <div id="pageLoader" class="fade-out">
    <div class="d-flex align-items-center justify-content-center h-100 p-4">
      <div class="card border p-4 w-100" style="max-width: 440px; border-radius: var(--radius-xl, 24px);">
        <div class="d-flex align-items-center gap-3 mb-4">
          <div class="skeleton-shimmer rounded-circle" style="width: 46px; height: 46px;"></div>
          <div class="w-50">
            <div class="skeleton-shimmer mb-2" style="height: 18px; width: 100%; border-radius: 6px;"></div>
            <div class="skeleton-shimmer" style="height: 12px; width: 60%; border-radius: 6px;"></div>
          </div>
        </div>
        <div class="skeleton-shimmer mb-3" style="height: 44px; border-radius: var(--radius-sm, 12px); width: 100%;"></div>
        <div class="skeleton-shimmer mb-3" style="height: 44px; border-radius: var(--radius-sm, 12px); width: 100%;"></div>
        <div class="skeleton-shimmer mb-4" style="height: 44px; border-radius: 9999px; width: 100%;"></div>
        <div class="d-flex justify-content-center">
          <div class="skeleton-shimmer" style="height: 14px; width: 50%; border-radius: 6px;"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="login-wrapper">
    {{-- Left Section: Form --}}
    <div class="login-section">
      {{-- Logo & Brand Header --}}
      <div class="brand-header mb-4 d-flex align-items-center gap-3">
        @include('partials.brand-logo', ['style' => 'width: 44px; height: 44px;'])
        <div>
          <h4 class="fw-bold text-primary m-0" style="line-height: 1.15;">{{ config('app.name', 'dzulfikrialifajri') }}</h4>
          <span class="text-muted small">{{ __('digital store') }}</span>
        </div>
      </div>

      <div class="mb-3">
        <h3 class="h4 fw-bold text-body mb-1" id="auth-title">{{ __('Selamat Datang') }}</h3>
        <p class="small text-secondary mb-0" id="auth-subtitle">{{ __('Silakan masuk untuk mengakses portal Anda.') }}</p>
      </div>

      @if($maintenanceMode ?? false)
      <div class="alert alert-warning py-2.5 px-3 small bg-warning bg-opacity-10 text-warning-emphasis rounded-3 mb-3">
          <div class="d-flex align-items-start gap-2">
              <i class="fas fa-tools text-warning fs-6 mt-0.5 flex-shrink-0"></i>
              <div>
                  <strong class="d-block mb-1 text-body">{{ __('Mode Pemeliharaan (Maintenance) Aktif') }}</strong>
                  <span class="text-secondary" style="font-size: 0.8125rem; line-height: 1.45;">{{ $maintenanceMessage ?? __('Website saat ini sedang dalam pemeliharaan sistem. Hanya Administrator yang dapat login.') }}</span>
              </div>
          </div>
      </div>
      @endif

      {{-- Auth Tabs Navigation (Concentric M3 Segmented Full Pill) --}}
      <ul class="nav nav-pills nav-fill mb-4" role="tablist">
          <li class="nav-item" role="presentation">
              <button class="nav-link active py-2 small fw-semibold" id="tab-btn-login" type="button" onclick="switchAuthTab('login')">
                  <i class="fas fa-sign-in-alt me-1.5"></i>{{ __('Masuk') }}
              </button>
          </li>
          <li class="nav-item" role="presentation">
              <button class="nav-link py-2 small fw-semibold text-muted" id="tab-btn-register" type="button" onclick="switchAuthTab('register')">
                  <i class="fas fa-user-plus me-1.5"></i>{{ __('Daftar') }}
              </button>
          </li>
      </ul>

      {{-- Alerts Block --}}
      @if(session('error'))
          <div class="alert alert-danger py-2 small bg-danger bg-opacity-10 text-danger rounded-3 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
      @endif
      @if(session('success'))
          <div class="alert alert-success py-2 small bg-success bg-opacity-10 text-success rounded-3 mb-3"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
      @endif
      @if($errors->any())
          <div class="alert alert-danger py-2 small bg-danger bg-opacity-10 text-danger rounded-3 mb-3">
              <ul class="mb-0 ps-3">
                  @foreach($errors->all() as $err)
                      <li>{{ $err }}</li>
                  @endforeach
              </ul>
          </div>
      @endif

      {{-- Hidden Tab Pillars for Bootstrap Tab JS --}}
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
                  <div class="mb-3">
                      <label class="form-label text-muted small fw-semibold">{{ __('Username atau Email') }}</label>
                      <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-user"></i></span>
                          <input type="text" name="login" class="form-control" placeholder="{{ __('Masukkan username atau email...') }}" value="{{ old('login') }}" required autofocus />
                      </div>
                  </div>
                  <div class="mb-3">
                      <label class="form-label text-muted small fw-semibold">{{ __('Password') }}</label>
                      <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-lock"></i></span>
                          <input type="password" name="password" class="form-control" placeholder="{{ __('Masukkan password...') }}" required />
                      </div>
                  </div>

                  <div class="d-flex justify-content-between align-items-center mb-3">
                      <div class="form-check">
                          <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                          <label class="form-check-label small text-secondary" for="rememberCheck">{{ __('Ingat Saya') }}</label>
                      </div>
                  </div>

                  {{-- Cloudflare Turnstile --}}
                  <div class="d-flex justify-content-center my-3">
                      <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                  </div>

                  <button type="submit" class="btn btn-primary rounded-pill py-2.5 w-100 fw-bold shadow-none">
                      <span>{{ __('Masuk Sistem') }}</span>
                      <i class="fas fa-arrow-right ms-1"></i>
                  </button>
              </form>

              <div class="position-relative my-4 text-center">
                  <hr class="text-secondary opacity-25 m-0">
                  <span class="position-absolute top-50 start-50 translate-middle bg-body px-3 small text-secondary fw-semibold" style="font-size: 0.75rem;">{{ __('ATAU') }}</span>
              </div>

              <form action="{{ route('auth.telegram.request') }}" method="POST" class="d-grid">
                  @csrf
                  <button type="submit" class="m3-auth-tg-btn py-2.5 w-100 fw-bold">
                      <i class="fab fa-telegram fs-5 me-1.5"></i>{{ __('Login dengan Telegram') }}
                  </button>
              </form>
          </div>

          {{-- TAB DAFTAR (REGISTER) --}}
          <div class="tab-pane fade" id="register-pane" role="tabpanel">
              <form action="{{ route('register.post') }}" method="POST">
                  @csrf
                  <div class="mb-3">
                      <label class="form-label text-muted small fw-semibold">{{ __('Nama Lengkap') }}</label>
                      <div class="input-group">
                          <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                          <input type="text" name="full_name" class="form-control" placeholder="{{ __('Nama Lengkap Anda') }}" value="{{ old('full_name') }}" required />
                      </div>
                  </div>

                  <div class="row g-2 mb-3">
                      <div class="col-6">
                          <label class="form-label text-muted small fw-semibold">{{ __('Username') }}</label>
                          <div class="input-group">
                              <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                              <input type="text" name="username" class="form-control" placeholder="{{ __('Username') }}" value="{{ old('username') }}" required />
                          </div>
                      </div>
                      <div class="col-6">
                          <label class="form-label text-muted small fw-semibold">{{ __('Email') }}</label>
                          <div class="input-group">
                              <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                              <input type="email" name="email" class="form-control" placeholder="{{ __('Email') }}" value="{{ old('email') }}" />
                          </div>
                      </div>
                  </div>

                  <div class="mb-3">
                      <label class="form-label text-muted small fw-semibold">{{ __('ID Telegram (Opsional)') }}</label>
                      <div class="input-group">
                          <span class="input-group-text"><i class="fab fa-telegram-plane"></i></span>
                          <input type="number" name="telegram_id" id="telegram_id_reg" class="form-control" placeholder="123456789" value="{{ old('telegram_id') }}" />
                      </div>
                      <div id="telegram_id_feedback" class="text-secondary mt-1 ms-1" style="font-size: 0.75rem;">{{ __('Agar bisa otomatis login dengan Telegram nantinya.') }}</div>
                  </div>

                  <div class="row g-2 mb-3">
                      <div class="col-6">
                          <label class="form-label text-muted small fw-semibold">{{ __('Kata Sandi') }}</label>
                          <div class="input-group">
                              <span class="input-group-text"><i class="fas fa-key"></i></span>
                              <input type="password" name="password" class="form-control" placeholder="{{ __('Kata Sandi') }}" required />
                          </div>
                      </div>
                      <div class="col-6">
                          <label class="form-label text-muted small fw-semibold">{{ __('Ulangi Sandi') }}</label>
                          <div class="input-group">
                              <span class="input-group-text"><i class="fas fa-lock"></i></span>
                              <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('Ulangi Sandi') }}" required />
                          </div>
                      </div>
                  </div>

                  {{-- Cloudflare Turnstile --}}
                  <div class="d-flex justify-content-center my-3">
                      <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                  </div>

                  <button type="submit" id="btn-register" class="btn btn-primary rounded-pill py-2.5 w-100 fw-bold shadow-none">
                      <span>{{ __('Daftar Sekarang') }}</span>
                      <i class="fas fa-user-plus ms-1"></i>
                  </button>
              </form>
          </div>
      </div>

      {{-- Footer --}}
      <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 text-center text-sm-start">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="text-muted small">
            <span>© 2026</span>
            <span class="fw-semibold ms-1 text-secondary">{{ config('app.name', "dzulfikrialifajri's store") }}</span>
            <span class="ms-1 text-secondary">- {{ $companyName ?? 'PT BANGUN JAYA KASIH' }}</span>
          </div>
        </div>
      </div>
    </div>

    {{-- Right Section: Info & Showcase --}}
    <div class="info-section d-none d-lg-flex">
      <div class="glass-card w-100">
        <div class="glass-header text-warning">
          <i class="fas fa-bullhorn me-1"></i>
          <span>{{ __('Informasi Store') }}</span>
        </div>
        
        <div class="small text-secondary mb-4" style="line-height: 1.6;">
          {!! $announcement !!}
        </div>

        {{-- Kontak Admin Box --}}
        <div class="small mb-4 p-3 rounded-2 border" style="background-color: var(--surface-container-low) !important; border-color: var(--outline-variant) !important;">
            <strong class="d-block mb-2 text-body">{{ __('Kontak Admin:') }}</strong>
            <a href="https://wa.me/6282269245660" target="_blank" class="text-body text-decoration-none mt-1 d-block"><i class="fab fa-whatsapp text-success me-1"></i> 082269245660 - WA</a>
            <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-body text-decoration-none mt-1 d-block"><i class="fab fa-telegram text-primary me-1"></i> @dzulfikrialifajri - Telegram</a>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <span class="badge bg-secondary-subtle text-secondary py-1.5 px-3" style="font-size: 0.75rem;">
                <i class="fas fa-users me-1 text-primary"></i> Pengunjung Hari Ini: {{ $todayVisitors ?? 0 }}
            </span>
        </div>
      </div>
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
              
              loginBtn.classList.remove('active', 'text-white');
              loginBtn.classList.add('text-muted');
              
              registerBtn.classList.add('active');
              registerBtn.classList.remove('text-muted');
              
              var tab = new bootstrap.Tab(document.getElementById('register-tab'));
              tab.show();
          } else {
              document.getElementById('auth-title').innerText = 'Selamat Datang';
              document.getElementById('auth-subtitle').innerText = 'Silakan masuk untuk mengakses portal Anda.';
              
              registerBtn.classList.remove('active', 'text-white');
              registerBtn.classList.add('text-muted');
              
              loginBtn.classList.add('active');
              loginBtn.classList.remove('text-muted');
              
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
