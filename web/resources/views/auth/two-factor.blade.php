<!DOCTYPE html>
<html lang="id" data-bs-theme="{{ request()->cookie('jualan-theme', 'light') }}">
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
    <title>Verifikasi Dua Langkah (2FA) — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    
    {{-- Poppins Font Family from Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">
    
    {{-- Bootstrap 5.3 & Font Awesome --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    {{-- Design System Stylesheets --}}
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="cursor: default;">

  {{-- Theme Toggle Button --}}
  <div class="auth-theme-toggle">
    <button class="btn btn-link link-body-emphasis p-0" id="themeToggle" title="{{ __('Ganti Tema') }}" aria-label="{{ __('Ganti Tema') }}">
        <i class="fas fa-moon fs-5" id="themeIcon"></i>
    </button>
  </div>

  {{-- Loader Overlay: Material You Android 17 Spinning Card --}}
  <div id="pageLoader" class="fade-out">
    <div class="m3-loader-card d-inline-flex align-items-center gap-3">
      <div class="m3-spinner-circle" role="status" aria-label="{{ __('Memuat...') }}"></div>
      <span class="m3-loader-text">{{ __('Memuat...') }}</span>
    </div>
  </div>

  <div class="login-wrapper" style="max-width: 480px;">
    <div class="login-section text-center p-4 p-sm-5">
      {{-- Logo & Brand --}}
      <div class="brand-header text-center mb-4">
        <div class="d-inline-flex align-items-center gap-3">
          @include('partials.brand-logo', ['style' => 'width: 44px; height: 44px;'])
          <div class="text-start">
            <h4 class="fw-bold text-primary m-0" style="line-height: 1.15;">{{ __('Verifikasi 2FA') }}</h4>
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
          <div class="alert alert-danger py-2 small bg-danger bg-opacity-10 text-danger rounded-3 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
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

      <form action="{{ route('auth.two-factor.verify') }}" method="POST">
          @csrf
          <div class="mb-4 text-start">
              <label class="form-label text-muted small fw-semibold">{{ __('Kode Verifikasi 6-Digit') }}</label>
              <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-key"></i></span>
                  <input type="text" name="code" class="form-control text-center fw-bold fs-4 tracking-widest" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus />
              </div>
          </div>

          <button type="submit" class="btn btn-primary py-2.5 w-100 fw-bold mb-2">
              <span>{{ __('Verifikasi & Masuk') }}</span>
              <i class="fas fa-arrow-right ms-1"></i>
          </button>
          
          <a href="{{ route('login') }}" class="btn btn-tonal py-2.5 w-100 fw-semibold text-decoration-none">
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
