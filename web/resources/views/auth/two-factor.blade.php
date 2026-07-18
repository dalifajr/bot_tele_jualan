<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dua Langkah (2FA) — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
          --primary-dark: #070d19;
          --text-dark: #0f172a;
          --text-muted: #475569;
        }

        body {
          padding: 1.5rem;
          font-family: "Plus Jakarta Sans", Outfit, sans-serif;
          background-color: var(--primary-dark);
          background-image: radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 45%), radial-gradient(circle at 90% 80%, rgba(217, 119, 6, 0.1) 0%, transparent 45%), radial-gradient(circle, rgba(15, 23, 42, 0.95) 0%, rgb(7, 13, 25) 100%);
          background-attachment: fixed;
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          overflow-x: hidden;
        }

        .ambient-glow-1 {
          position: absolute;
          width: 500px;
          height: 500px;
          background-image: radial-gradient(circle, rgba(59, 130, 246, 0.08) 0%, transparent 70%);
          top: -150px;
          left: -100px;
          pointer-events: none;
          z-index: 1;
        }

        .ambient-glow-2 {
          position: absolute;
          width: 500px;
          height: 500px;
          background-image: radial-gradient(circle, rgba(217, 119, 6, 0.05) 0%, transparent 70%);
          bottom: -150px;
          right: -100px;
          pointer-events: none;
          z-index: 1;
        }

        .login-wrapper {
          border-radius: 32px;
          border: 1px solid rgba(255, 255, 255, 0.08);
          background: rgba(255, 255, 255, 0.03);
          position: relative;
          z-index: 10;
          width: 100%;
          max-width: 550px;
          min-height: 450px;
          backdrop-filter: blur(24px) saturate(120%);
          box-shadow: rgba(0, 0, 0, 0.4) 0px 30px 60px -15px, rgba(59, 130, 246, 0.1) 0px 0px 100px -30px, rgba(255, 255, 255, 0.1) 0px 1px 0px 0px inset;
          display: flex;
          flex-direction: column;
          overflow: hidden;
        }

        .login-section {
          padding: 3.5rem 3rem;
          background: rgba(255, 255, 255, 0.85);
          backdrop-filter: blur(10px);
          display: flex;
          flex-direction: column;
          justify-content: center;
          flex-grow: 1;
          z-index: 2;
        }

        .brand-header {
          margin-bottom: 2rem;
        }

        .form-control {
          padding: 0.95rem 1rem 0.95rem 3rem;
          border-radius: 16px;
          border: 1.5px solid rgba(226, 232, 240, 0.8);
          background: rgba(248, 250, 252, 0.65);
          width: 100%;
          font-size: 1.25rem;
          font-weight: 700;
          letter-spacing: 0.35em;
          text-align: center;
          color: var(--text-dark);
          font-family: inherit;
          transition: all 0.2s;
        }

        .form-control::placeholder {
          font-weight: 500;
          letter-spacing: normal;
          font-size: 0.95rem;
        }

        .form-control:focus {
          background: rgb(255, 255, 255);
          outline-style: none;
          border-color: rgb(37, 99, 235);
          box-shadow: rgba(37, 99, 235, 0.12) 0px 0px 0px 4px, rgba(37, 99, 235, 0.05) 0px 4px 12px -2px;
        }

        .form-icon {
          position: absolute;
          left: 1.1rem;
          top: 50%;
          transform: translateY(-50%);
          color: rgb(148, 163, 184);
          font-size: 1.05rem;
          z-index: 10;
        }

        .relative {
          position: relative;
        }

        .btn-primary {
          padding: 0.95rem;
          border-radius: 16px;
          width: 100%;
          background-image: linear-gradient(135deg, rgb(29, 78, 216) 0%, rgb(30, 64, 175) 50%, rgb(30, 58, 138) 100%);
          background-size: 200%;
          color: rgb(255, 255, 255);
          font-size: 1rem;
          font-weight: 600;
          cursor: pointer;
          box-shadow: rgba(29, 78, 216, 0.3) 0px 10px 20px -5px, rgba(255, 255, 255, 0.1) 0px 0px 0px 1px inset;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 0.5rem;
          border: none;
          transition: all 0.2s;
        }

        .btn-primary:hover {
          background-position-x: right;
          background-position-y: center;
          transform: translateY(-2px);
          box-shadow: rgba(29, 78, 216, 0.4) 0px 15px 25px -5px, rgba(59, 130, 246, 0.2) 0px 2px 10px;
        }

        .btn-outline {
          padding: 0.85rem;
          border-radius: 16px;
          border: 1.5px solid rgba(226, 232, 240, 0.8);
          background: rgba(255, 255, 255, 0.4);
          width: 100%;
          color: var(--text-muted);
          font-weight: 600;
          font-size: 0.95rem;
          text-decoration: none;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 0.5rem;
          transition: all 0.2s;
        }

        .btn-outline:hover {
          background: rgba(220, 38, 38, 0.03);
          border-color: rgb(220, 38, 38);
          color: rgb(220, 38, 38);
          transform: translateY(-1px);
        }

        /* ===== Responsive: Smartphones (425px and below) ===== */
        @media (max-width: 480px) {
            body {
                padding: 0.5rem;
                align-items: flex-start;
                padding-top: 2rem;
            }

            .login-wrapper {
                border-radius: 20px;
                box-shadow: rgba(0, 0, 0, 0.3) 0px 15px 30px -10px, rgba(59, 130, 246, 0.08) 0px 0px 60px -20px;
            }

            .login-section {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }
        }

        #pageLoader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.85);
            opacity: 1;
            visibility: visible;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            backdrop-filter: blur(5px);
        }

        #pageLoader.fade-out {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e5e7eb;
            border-top: 4px solid #1b4ab2;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

  <!-- Ambient Backdrops -->
  <div class="ambient-glow-1"></div>
  <div class="ambient-glow-2"></div>

  <div id="pageLoader">
    <div class="spinner"></div>
  </div>

  <div class="login-wrapper">
    <div class="login-section">
      <!-- Logo & Brand -->
      <div class="brand-header text-center">
        <div class="d-inline-flex align-items-center gap-3">
          <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 44px; height: 44px; background: linear-gradient(135deg, #1b4ab2 0%, #1565c0 100%);">
            <i class="fas fa-key"></i>
          </div>
          <div class="text-start">
            <h1 class="fs-4 fw-extrabold text-slate-900 font-outfit m-0">{{ __('Verifikasi 2FA') }}</h1>
            <div class="text-slate-400 font-bold uppercase tracking-wider" style="font-size: 0.65rem;">{{ config('app.name', 'Dzulfikrialifajri Store') }}</div>
          </div>
        </div>
      </div>

      <div class="text-center mb-4">
        <p class="text-sm text-slate-500 font-medium">
          {{ __('Kami telah mengirimkan kode verifikasi 6 digit ke akun Telegram Anda yang tertaut. Silakan masukkan kode di bawah ini.') }}
        </p>
      </div>

      <!-- Alerts Block -->
      @if(session('error'))
          <div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
      @endif
      @if($errors->{{ __('any())') }}
          <div class="alert alert-danger py-2 small border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-3">
              <ul class="mb-0 ps-3">
                  @foreach($errors->{{ __('all() as $err)') }}
                      <li>{{ $err }}</li>
                  @endforeach
              </ul>
          </div>
      @endif

      <form action="{{ route('auth.two-factor.verify') }}" method="POST" class="space-y-4">
          @csrf
          <div class="form-group position-relative mb-4">
              <input type="text" name="code" class="form-control" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus />
              <i class="fas fa-shield-alt form-icon"></i>
          </div>

          <button type="submit" class="btn-primary w-100 mb-3">
              <span>{{ __('Verifikasi & Masuk') }}</span>
              <i class="fas fa-sign-in-alt text-xs"></i>
          </button>
          
          <a href="{{ route('login') }}" class="btn-outline w-100">
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
</body>
</html>
