<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dua Langkah (2FA) — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    
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
    <script>
        (function() {
            const savedTheme = localStorage.getItem('jualan-theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
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

  <div class="auth-card" style="max-width: 520px;">
    <div class="auth-card-body p-4 p-sm-5 text-center">
      {{-- Brand Header --}}
      <div class="brand-header mb-4 d-inline-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm" style="width: 48px; height: 48px; background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
          <i class="fas fa-key fs-5"></i>
        </div>
        <div class="text-start">
          <h4 class="fw-bold text-body m-0" style="letter-spacing: -0.5px;">{{ __('Verifikasi 2FA') }}</h4>
          <div class="text-muted fw-bold text-uppercase mt-0.5" style="font-size: 0.7rem; letter-spacing: 1px;">{{ config('app.name', 'Dzulfikrialifajri Store') }}</div>
        </div>
      </div>

      <div class="mb-4">
        <p class="small text-secondary mb-0" style="line-height: 1.6;">
          {{ __('Kami telah mengirimkan kode verifikasi 6 digit ke akun Telegram Anda yang tertaut. Silakan masukkan kode di bawah ini.') }}
        </p>
      </div>

      {{-- Alerts Block --}}
      @if(session('error'))
          <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 border-0 bg-danger bg-opacity-10 text-danger"><i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}</div>
      @endif
      @if($errors->any())
          <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 border-0 bg-danger bg-opacity-10 text-danger">
              <ul class="mb-0 ps-3 text-start">
                  @foreach($errors->all() as $err)
                      <li>{{ $err }}</li>
                  @endforeach
              </ul>
          </div>
      @endif

      <form action="{{ route('auth.two-factor.verify') }}" method="POST" class="d-flex flex-column gap-3">
          @csrf
          <div class="auth-input-group">
              <i class="fas fa-shield-alt auth-input-icon"></i>
              <input type="text" name="code" class="form-control text-center fw-bold fs-4 tracking-widest" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus />
          </div>

          <button type="submit" class="btn btn-primary rounded-pill py-2.5 fw-bold d-flex justify-content-center align-items-center gap-2 shadow-sm">
              <span>{{ __('Verifikasi & Masuk') }}</span>
              <i class="fas fa-sign-in-alt"></i>
          </button>
          
          <a href="{{ route('login') }}" class="btn btn-outline-secondary rounded-pill py-2.5 fw-bold d-flex justify-content-center align-items-center gap-2">
              <i class="fas fa-arrow-left"></i> {{ __('Kembali ke Login') }}
          </a>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
      // Hide loader
      window.addEventListener('load', function() {
          const loader = document.getElementById('pageLoader');
          if (loader) {
              loader.classList.add('fade-out');
          }
      });
  </script>
  <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
