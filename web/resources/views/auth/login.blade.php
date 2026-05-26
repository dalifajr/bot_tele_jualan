<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
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
            max-width: 480px;
            width: 100%;
            padding: 2.5rem;
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
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 10px 30px rgba(13, 71, 161, 0.3);
        }

        .brand-icon i {
            font-size: 2rem;
            color: white;
        }

        .btn-telegram {
            background: #0088cc;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-size: 1.05rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            width: 100%;
            justify-content: center;
        }

        .btn-telegram:hover {
            background: #006da3;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 136, 204, 0.4);
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 16px;
        }

        .nav-pills .nav-link {
            border-radius: 10px;
            color: var(--primary-color);
            font-weight: 500;
            padding: 10px 20px;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
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

        @media (max-width: 480px) {
            .login-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>

    <div class="login-card">
        <div class="text-center">
            <div class="brand-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <h1 class="fw-bold mb-1" style="font-size: 1.6rem;">{{ config('app.name', 'Dzulfikrialifajri Store') }}</h1>
            <p class="text-muted mb-4 small">Masuk atau daftar untuk berbelanja</p>
        </div>

        {{-- Flash Messages --}}
        @if(session('error'))
            <div class="alert alert-danger mb-3 py-2 small">
                <i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}
            </div>
        @endif
        @if(session('success'))
            <div class="alert alert-success mb-3 py-2 small">
                <i class="fas fa-check-circle me-1"></i>{{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger mb-3 py-2 small">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <ul class="nav nav-pills nav-fill mb-4" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">Masuk</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane" type="button" role="tab">Daftar Baru</button>
            </li>
        </ul>

        <div class="tab-content" id="authTabsContent">
            {{-- Tab Login --}}
            <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
                <form action="{{ route('login.post') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Username / Email</label>
                        <input type="text" name="login" class="form-control" required placeholder="Masukkan username atau email" value="{{ old('login') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember" class="form-check-input" id="rememberCheck">
                        <label class="form-check-label small" for="rememberCheck">Ingat Saya</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2" style="border-radius: 12px; font-size: 1.05rem;">Login Konvensional</button>
                </form>

                <div class="position-relative my-4 text-center">
                    <hr class="text-secondary opacity-25">
                    <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 small text-muted">ATAU</span>
                </div>

                <form action="{{ route('auth.telegram.request') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-telegram">
                        <i class="fab fa-telegram"></i> Login dengan Telegram
                    </button>
                </form>
            </div>

            {{-- Tab Register --}}
            <div class="tab-pane fade" id="register-pane" role="tabpanel">
                <form action="{{ route('register.post') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control form-control-sm" required value="{{ old('full_name') }}">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control form-control-sm" required value="{{ old('username') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email') }}">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">ID Telegram (Opsional)</label>
                        <input type="number" name="telegram_id" class="form-control form-control-sm" placeholder="Contoh: 12345678" value="{{ old('telegram_id') }}">
                        <div class="form-text" style="font-size: 0.75rem;">Isi ID Telegram agar kelak bisa langsung Login via Telegram.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-secondary">Ulangi Password <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control form-control-sm" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mt-2" style="border-radius: 12px;">Daftar Akun Baru</button>
                </form>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check if there are registration errors to keep the register tab active
        @if(old('username') && $errors->any())
            var registerTab = new bootstrap.Tab(document.querySelector('#register-tab'));
            registerTab.show();
        @endif
    </script>
</body>
</html>
