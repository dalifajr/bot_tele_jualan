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
    
    {{-- Universal Store Design System Stylesheet --}}
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        // Theme initialization to avoid flash of wrong theme
        (function() {
            const savedTheme = localStorage.getItem('jualan-theme') || 'light';
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
<body class="auth-page-wrapper">

  {{-- Ambient Glow Elements --}}
  <div class="auth-ambient-glow auth-ambient-glow-1"></div>
  <div class="auth-ambient-glow auth-ambient-glow-2"></div>

  {{-- Theme Toggle Button --}}
  <div class="auth-theme-toggle">
    <button class="btn btn-link link-body-emphasis p-0 text-decoration-none" id="themeToggle" title="{{ __('Ganti Tema') }}" aria-label="{{ __('Ganti Tema') }}">
        <i class="fas fa-moon fs-5" id="themeIcon"></i>
    </button>
  </div>

  {{-- Page Loader Overlay --}}
  <div id="pageLoader">
    <div class="spinner"></div>
  </div>

  <div class="auth-card">
    <div class="row g-0">
      {{-- Left Section: Auth Form --}}
      <div class="col-12 col-lg-7 auth-card-body d-flex flex-column justify-content-between border-end-lg border-secondary border-opacity-10">
        <div>
          {{-- Brand Header --}}
          <div class="brand-header mb-4 d-flex align-items-center gap-3">
            <div class="d-flex align-items-center">
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm" style="width: 44px; height: 44px; background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); z-index: 2;">
                <i class="fas fa-shopping-bag fs-5"></i>
              </div>
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm" style="width: 44px; height: 44px; background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%); margin-left: -12px; border: 2.5px solid var(--glass-bg); z-index: 1;">
                <span class="fw-bold" style="font-size: 0.65rem;">{{ __('Mitra') }}</span>
              </div>
            </div>
            <div>
              <h4 class="fw-bold text-body m-0" style="letter-spacing: -0.5px;">{{ __('Jualan') }}</h4>
              <div class="text-muted fw-bold text-uppercase mt-0.5" style="font-size: 0.7rem; letter-spacing: 1px;">{{ config('app.name', 'Dzulfikrialifajri Store') }}</div>
            </div>
          </div>

          <div class="mb-4">
            <h4 class="fw-bold text-body mb-1" id="auth-title">{{ __('Selamat Datang') }}</h4>
            <p class="small text-secondary mb-0" id="auth-subtitle">{{ __('Silakan masuk untuk mengakses portal Anda.') }}</p>
          </div>

          {{-- Auth Tabs Navigation --}}
          <div class="auth-pill-nav mb-4" role="tablist">
              <button type="button" class="nav-link active" id="tab-btn-login" onclick="switchAuthTab('login')"><i class="fas fa-sign-in-alt me-1.5"></i>{{ __('Masuk') }}</button>
              <button type="button" class="nav-link" id="tab-btn-register" onclick="switchAuthTab('register')"><i class="fas fa-user-plus me-1.5"></i>{{ __('Daftar') }}</button>
          </div>

          {{-- Alerts Block --}}
          @if(session('error'))
              <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 border-0 bg-danger bg-opacity-10 text-danger"><i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}</div>
          @endif
          @if(session('success'))
              <div class="alert alert-success py-2 px-3 small rounded-3 mb-3 border-0 bg-success bg-opacity-10 text-success"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
          @endif
          @if($errors->any())
              <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 border-0 bg-danger bg-opacity-10 text-danger">
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
              
              {{-- TAB MASUK (LOGIN) --}}
              <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
                  <form action="{{ route('login.post') }}" method="POST" class="d-flex flex-column gap-3">
                      @csrf
                      <div class="auth-input-group">
                          <i class="fas fa-user auth-input-icon"></i>
                          <input type="text" name="login" class="form-control" placeholder="{{ __('Username atau Email') }}" value="{{ old('login') }}" required autofocus />
                      </div>
                      <div class="auth-input-group">
                          <i class="fas fa-lock auth-input-icon"></i>
                          <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}" required />
                      </div>

                      <div class="d-flex justify-content-between align-items-center">
                          <div class="form-check mb-0">
                              <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                              <label class="form-check-label small text-secondary" for="rememberCheck">{{ __('Ingat Saya') }}</label>
                          </div>
                      </div>

                      {{-- Cloudflare Turnstile --}}
                      <div class="d-flex justify-content-center my-2">
                          <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                      </div>

                      <button type="submit" class="btn btn-primary rounded-pill py-2.5 fw-bold d-flex justify-content-center align-items-center gap-2 shadow-sm">
                          <span>{{ __('Masuk Sistem') }}</span>
                          <i class="fas fa-arrow-right"></i>
                      </button>
                  </form>

                  <div class="position-relative my-4 text-center">
                      <hr class="text-secondary opacity-25 m-0">
                      <span class="position-absolute top-50 start-50 translate-middle bg-body px-3 small text-secondary fw-bold" style="font-size: 0.75rem;">{{ __('ATAU') }}</span>
                  </div>

                  <form action="{{ route('auth.telegram.request') }}" method="POST" class="d-grid">
                      @csrf
                      <button type="submit" class="btn btn-outline-info rounded-pill py-2.5 fw-bold d-flex justify-content-center align-items-center gap-2">
                          <i class="fab fa-telegram fs-5"></i> {{ __('Login via Telegram') }}
                      </button>
                  </form>
              </div>

              {{-- TAB DAFTAR (REGISTER) --}}
              <div class="tab-pane fade" id="register-pane" role="tabpanel">
                  <form action="{{ route('register.post') }}" method="POST" class="d-flex flex-column gap-3">
                      @csrf
                      <div class="auth-input-group">
                          <i class="fas fa-id-card auth-input-icon"></i>
                          <input type="text" name="full_name" class="form-control" placeholder="{{ __('Nama Lengkap') }}" value="{{ old('full_name') }}" required />
                      </div>

                      <div class="row g-2">
                          <div class="col-12 col-sm-6">
                              <div class="auth-input-group">
                                  <i class="fas fa-user-circle auth-input-icon"></i>
                                  <input type="text" name="username" class="form-control" placeholder="{{ __('Username') }}" value="{{ old('username') }}" required />
                              </div>
                          </div>
                          <div class="col-12 col-sm-6">
                              <div class="auth-input-group">
                                  <i class="fas fa-envelope auth-input-icon"></i>
                                  <input type="email" name="email" class="form-control" placeholder="{{ __('Email') }}" value="{{ old('email') }}" />
                              </div>
                          </div>
                      </div>

                      <div class="auth-input-group">
                          <i class="fab fa-telegram-plane auth-input-icon"></i>
                          <input type="number" name="telegram_id" id="telegram_id_reg" class="form-control" placeholder="ID Telegram (Opsional)" value="{{ old('telegram_id') }}" />
                      </div>
                      <div id="telegram_id_feedback" class="text-secondary small mt-n1 ms-2" style="font-size: 0.75rem;">{{ __('Agar bisa otomatis login dengan Telegram nantinya.') }}</div>

                      <div class="row g-2">
                          <div class="col-12 col-sm-6">
                              <div class="auth-input-group">
                                  <i class="fas fa-key auth-input-icon"></i>
                                  <input type="password" name="password" class="form-control" placeholder="{{ __('Kata Sandi') }}" required />
                              </div>
                          </div>
                          <div class="col-12 col-sm-6">
                              <div class="auth-input-group">
                                  <i class="fas fa-lock auth-input-icon"></i>
                                  <input type="password" name="password_confirmation" class="form-control" placeholder="{{ __('Ulangi Sandi') }}" required />
                              </div>
                          </div>
                      </div>

                      {{-- Cloudflare Turnstile --}}
                      <div class="d-flex justify-content-center my-2">
                          <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                      </div>

                      <button type="submit" id="btn-register" class="btn btn-primary rounded-pill py-2.5 fw-bold d-flex justify-content-center align-items-center gap-2 shadow-sm">
                          <span>{{ __('Daftar Sekarang') }}</span>
                          <i class="fas fa-user-plus"></i>
                      </button>
                  </form>
              </div>
          </div>
        </div>

        {{-- Card Footer --}}
        <div class="mt-4 pt-3 border-top border-secondary border-opacity-10 text-center text-sm-start">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">
              <span>© 2026</span>
              <span class="fw-bold ms-1 text-secondary">{{ __('dzulfikrialifajri_store') }}</span>
            </div>
          </div>
        </div>
      </div>

      {{-- Right Section: Store Info Showcase --}}
      <div class="col-lg-5 d-none d-lg-flex auth-card-body bg-body-tertiary bg-opacity-50 flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center gap-2 text-warning mb-3">
            <i class="fas fa-bullhorn fs-5"></i>
            <h6 class="fw-bold m-0 text-body">{{ __('Informasi Store') }}</h6>
          </div>
          
          <div class="small text-secondary mb-4" style="line-height: 1.6;">
            {!! $announcement !!}
          </div>

          {{-- Admin Contact Box --}}
          <div class="small mb-4 p-3 rounded-4 border bg-body" style="border-color: var(--glass-border) !important;">
              <strong class="d-block mb-2 text-body fw-bold"><i class="fas fa-headset me-1.5 text-primary"></i>{{ __('Kontak Bantuan Admin:') }}</strong>
              <a href="https://wa.me/6282269245660" target="_blank" class="text-body text-decoration-none mt-1.5 d-flex align-items-center gap-2">
                  <i class="fab fa-whatsapp text-success fs-5"></i> <span>082269245660 (WhatsApp)</span>
              </a>
              <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-body text-decoration-none mt-2 d-flex align-items-center gap-2">
                  <i class="fab fa-telegram text-info fs-5"></i> <span>@dzulfikrialifajri (Telegram)</span>
              </a>
          </div>
        </div>

        <div>
            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill py-2 px-3 border border-primary border-opacity-20 d-inline-flex align-items-center gap-1.5" style="font-size: 0.75rem;">
                <i class="fas fa-users text-info"></i> {{ __('Pengunjung Hari Ini:') }} <strong>{{ $todayVisitors ?? 0 }}</strong>
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
