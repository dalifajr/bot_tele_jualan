<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — {{ config('app.name', 'Jualan') }}</title>
    <meta name="description" content="Login ke platform jual beli produk digital menggunakan akun Telegram">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #0d47a1;
            --primary-light: #1976d2;
            --accent-gold: #ffd700;
        }

        * { font-family: 'Outfit', sans-serif; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 50%, #42a5f5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            max-width: 440px;
            width: 100%;
            padding: 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-gold), var(--primary-light));
        }

        .brand-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(13, 71, 161, 0.3);
        }

        .brand-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .btn-telegram {
            background: #0088cc;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px 32px;
            font-size: 1.1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            justify-content: center;
        }

        .btn-telegram:hover {
            background: #006da3;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 136, 204, 0.4);
        }

        .btn-telegram:active {
            transform: translateY(-1px);
        }

        .btn-telegram i {
            font-size: 1.4rem;
        }

        .remember-info {
            background: #f0f7ff;
            border-radius: 12px;
            padding: 12px 16px;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .remember-info i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .bg-pattern {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            opacity: 0.05;
            background-image: radial-gradient(#fff 1px, transparent 1px);
            background-size: 25px 25px;
            pointer-events: none;
            z-index: 0;
        }

        .login-card { position: relative; z-index: 1; }

        .alert { border-radius: 12px; text-align: left; }

        @media (max-width: 480px) {
            .login-card { padding: 2rem 1.5rem; }
        }

        [data-bs-theme="dark"] body {
            background: linear-gradient(135deg, #0a1628 0%, #162447 50%, #1f3c6b 100%);
        }

        [data-bs-theme="dark"] .login-card {
            background: rgba(30, 41, 59, 0.95);
            color: #e1e1e1;
        }

        [data-bs-theme="dark"] .remember-info {
            background: rgba(13, 71, 161, 0.15);
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>

    <div class="login-card">
        <div class="brand-icon">
            <i class="fas fa-shopping-bag"></i>
        </div>

        <h1 class="fw-bold mb-2" style="font-size: 1.8rem;">{{ config('app.name', 'Jualan') }}</h1>
        <p class="text-muted mb-4">Platform jual beli produk digital terpercaya</p>

        {{-- Flash Messages --}}
        @if(session('error'))
            <div class="alert alert-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            </div>
        @endif
        @if(session('success'))
            <div class="alert alert-success mb-4">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            </div>
        @endif

        <form action="{{ route('auth.telegram.request') }}" method="POST">
            @csrf
            <button type="submit" class="btn-telegram" id="loginBtn">
                <i class="fab fa-telegram"></i>
                Login dengan Telegram
            </button>
        </form>

        <div class="remember-info">
            <i class="fas fa-shield-halved"></i>
            <small class="text-muted text-start">
                Sesi login berlaku <strong>30 hari</strong>. Anda akan diarahkan ke Telegram untuk verifikasi.
            </small>
        </div>

        <div class="mt-4">
            <small class="text-muted">
                <i class="fas fa-lock me-1"></i>
                Login aman menggunakan akun Telegram Anda
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
