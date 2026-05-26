<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Daftar — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #1976d2; /* Biru terang seperti di gambar */
            --primary-dark: #1565c0; /* Biru tua untuk kolom kanan */
            --bg-color: #e3f2fd; /* Biru sangat muda untuk background luar */
            --input-bg: #f5f8fa; /* Abu-abu muda kebiruan untuk input */
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #a3cff8 0%, #e0f2fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .auth-container {
            max-width: 900px;
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .auth-left {
            background-color: #ffffff;
            padding: 3rem;
            position: relative;
        }

        .auth-right {
            background-color: var(--primary-dark);
            /* Pola titik-titik samar / cross pattern */
            background-image: 
                linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 30px 30px;
            position: relative;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        /* Glassmorphism Info Box */
        .info-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            color: #fff;
        }

        /* Form Elements */
        .form-control {
            background-color: var(--input-bg);
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 12px 15px 12px 40px; /* Padding kiri untuk icon */
            font-size: 0.95rem;
        }

        .form-control:focus {
            background-color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(25, 118, 210, 0.1);
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 1rem;
        }

        .input-group-custom i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 10;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #1565c0;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid #e1e8ed;
            color: #495057;
            border-radius: 8px;
            padding: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-outline:hover {
            background-color: #f8f9fa;
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 2.5rem;
        }

        .brand-icon {
            width: 45px;
            height: 45px;
            background: var(--primary-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .brand-text h4 {
            margin: 0;
            font-weight: 700;
            color: #212529;
            line-height: 1.2;
        }
        
        .brand-text span {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .footer-text {
            text-align: center;
            margin-top: 3rem;
            font-size: 0.75rem;
            color: #adb5bd;
        }

        /* Tabs Styling Override */
        .nav-pills {
            margin-bottom: 1.5rem;
            background: #f1f5f9;
            border-radius: 10px;
            padding: 4px;
        }
        .nav-pills .nav-link {
            border-radius: 8px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 8px 16px;
        }
        .nav-pills .nav-link.active {
            background-color: #fff;
            color: var(--primary-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Khusus untuk input di form register (tanpa icon melayang) */
        .register-form .form-control {
            padding-left: 15px; /* Kembalikan padding normal */
        }
        
        @media (max-width: 768px) {
            .auth-left { padding: 2rem; }
            .auth-right { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="auth-container">
        <div class="row g-0">
            
            {{-- Kolom Kiri: Form Login/Register --}}
            <div class="col-md-6 auth-left">
                <div class="brand-header">
                    <div class="brand-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="brand-text">
                        <h4>{{ config('app.name', 'Store') }}</h4>
                        <span>Digital Product Marketplace</span>
                    </div>
                </div>

                <h5 class="fw-bold mb-4">Selamat Datang</h5>

                {{-- Pesan Flash --}}
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

                {{-- Navigasi Tab --}}
                <ul class="nav nav-pills nav-fill" id="authTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">Masuk</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane" type="button" role="tab">Daftar</button>
                    </li>
                </ul>

                <div class="tab-content" id="authTabsContent">
                    
                    {{-- TAB MASUK (LOGIN) --}}
                    <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
                        <form action="{{ route('login.post') }}" method="POST">
                            @csrf
                            <div class="input-group-custom">
                                <i class="fas fa-user"></i>
                                <input type="text" name="login" class="form-control" required placeholder="Username atau Email" value="{{ old('login') }}">
                            </div>
                            
                            <div class="input-group-custom mb-3">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" class="form-control" required placeholder="••••••••">
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                                    <label class="form-check-label small text-muted" for="rememberCheck">Ingat Saya</label>
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                <button type="submit" class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
                                    Masuk Sistem <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </form>

                        <div class="position-relative my-4 text-center">
                            <hr class="text-secondary opacity-25">
                            <span class="position-absolute top-50 start-50 translate-middle bg-white px-2 small text-muted" style="font-size: 0.75rem;">ATAU</span>
                        </div>

                        <form action="{{ route('auth.telegram.request') }}" method="POST" class="d-grid">
                            @csrf
                            <button type="submit" class="btn-outline w-100 text-decoration-none text-body">
                                <i class="fab fa-telegram text-info fs-5"></i> Login dengan Telegram
                            </button>
                        </form>
                    </div>

                    {{-- TAB DAFTAR (REGISTER) --}}
                    <div class="tab-pane fade register-form" id="register-pane" role="tabpanel">
                        <form action="{{ route('register.post') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <input type="text" name="full_name" class="form-control form-control-sm" required placeholder="Nama Lengkap" value="{{ old('full_name') }}">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <input type="text" name="username" class="form-control form-control-sm" required placeholder="Username" value="{{ old('username') }}">
                                </div>
                                <div class="col-6">
                                    <input type="email" name="email" class="form-control form-control-sm" placeholder="Email (Opsional)" value="{{ old('email') }}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <input type="number" name="telegram_id" class="form-control form-control-sm" placeholder="ID Telegram (Opsional)" value="{{ old('telegram_id') }}">
                                <div class="form-text" style="font-size: 0.7rem; color: #adb5bd;">Agar bisa otomatis login dengan Telegram nantinya.</div>
                            </div>
                            <div class="row g-2 mb-4">
                                <div class="col-6">
                                    <input type="password" name="password" class="form-control form-control-sm" required placeholder="Kata Sandi">
                                </div>
                                <div class="col-6">
                                    <input type="password" name="password_confirmation" class="form-control form-control-sm" required placeholder="Ulangi Sandi">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                Daftar Sekarang <i class="fas fa-user-plus"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="footer-text">
                    <div class="text-uppercase fw-bold mb-1" style="letter-spacing: 1px; font-size: 0.65rem;">Supported By</div>
                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-shield-halved fs-5 text-secondary opacity-50"></i>
                        <div class="text-start" style="line-height: 1;">
                            <strong>Keamanan Data</strong><br>
                            <span style="font-size: 0.65rem;">Enkripsi & Proteksi</span>
                        </div>
                    </div>
                    <div class="mt-3">&copy; {{ date('Y') }} {{ config('app.name') }}</div>
                </div>
            </div>

            {{-- Kolom Kanan: Informasi / Banner (Hanya di layar besar) --}}
            <div class="col-md-6 auth-right d-none d-md-flex">
                <div class="info-box">
                    <h5 class="fw-bold mb-2"><i class="fas fa-bullhorn me-2"></i>Informasi Store</h5>
                    <p class="small mb-0 opacity-75" style="line-height: 1.6;">
                        Selamat datang di pusat layanan pembelian produk digital terbaik. Pantau terus katalog kami untuk penawaran terbaru. Proses checkout aman dan pengiriman dilakukan secara instan.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Buka tab register secara otomatis jika ada error pada isian pendaftaran
    @if(old('username') && $errors->any())
        var registerTab = new bootstrap.Tab(document.querySelector('#register-tab'));
        registerTab.show();
    @endif
</script>
</body>
</html>
