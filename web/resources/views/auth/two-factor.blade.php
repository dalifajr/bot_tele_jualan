<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dua Langkah (2FA) — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    
    {{-- Google Fonts: Outfit --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    {{-- Bootstrap 5.3 & Font Awesome --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    {{-- Design System Stylesheets --}}
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('jualan-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body style="cursor: default;">

  {{-- Theme Toggle Button --}}
  <div class="auth-theme-toggle">
    <button class="btn btn-link link-body-emphasis p-0" id="themeToggle" title="{{ __('Ganti Tema') }}" aria-label="{{ __('Ganti Tema') }}">
        <i class="fas fa-moon fs-5" id="themeIcon"></i>
    </button>
  </div>

  <div id="pageLoader">
    <div class="spinner"></div>
  </div>

  <div class="login-wrapper" style="max-width: 500px;">
    <div class="login-section text-center p-4 p-sm-5">
      {{-- Logo & Brand --}}
      <div class="brand-header text-center mb-4">
        <div class="d-inline-flex align-items-center gap-3">
          <div class="user-avatar rounded-circle d-flex align-items-center justify-content-center text-white bg-primary shadow-sm" style="width: 44px; height: 44px; font-size: 1.2rem;">
            <i class="fas fa-shield-alt"></i>
          </div>
          <div class="text-start">
            <h4 class="fw-bold text-primary m-0" style="line-height: 1.1;">{{ __('Verifikasi 2FA') }}</h4>
            <span class="text-muted small">{{ config('app.name', 'Dzulfikrialifajri Store') }}</span>
          </div>
        </div>
      </div>

      <div class="text-center mb-4">
        <p class="small text-secondary mb-0">
          {{ __('Kami telah mengirimkan kode verifikasi 6 digit ke akun Telegram Anda yang tertaut. Silakan masukkan kode di bawah ini.') }}
        </p>
      </div>

      {{-- Alerts Block --}}
      @if(session('error'))
          <div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
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

      <form action="{{ route('auth.two-factor.verify') }}" method="POST">
          @csrf
          <div class="mb-4">
              <label class="form-label text-muted small fw-bold">{{ __('Kode Verifikasi 6-Digit') }}</label>
              <div class="input-group">
                  <span class="input-group-text bg-body-tertiary border-0 text-muted"><i class="fas fa-key"></i></span>
                  <input type="text" name="code" class="form-control bg-body-tertiary border-0 text-center fw-bold fs-4 tracking-widest" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus />
              </div>
          </div>

          <button type="submit" class="btn btn-primary rounded-pill py-2.5 w-100 fw-bold shadow-sm mb-3">
              <span>{{ __('Verifikasi & Masuk') }}</span>
              <i class="fas fa-arrow-right ms-1"></i>
          </button>
          
          <a href="{{ route('login') }}" class="btn btn-outline-secondary rounded-pill py-2 w-100 fw-semibold text-decoration-none">
              <i class="fas fa-arrow-left me-1"></i> {{ __('Kembali ke Login') }}
          </a>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
  <script>
      window.addEventListener('load', function() {
          const loader = document.getElementById('pageLoader');
          if (loader) {
              loader.classList.add('fade-out');
          }
      });
  </script>
</body>
</html>
