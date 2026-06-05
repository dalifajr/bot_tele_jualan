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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        /* Page Loader Overlay */
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
            background-color: rgba(255, 255, 255, 0.8);
            opacity: 1;
            visibility: visible;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        #pageLoader.fade-out {
            opacity: 0;
            visibility: hidden;
        }

        /* Spinner Element */
        .spinner {
            width: 50px;
            height: 50px;
            border: 4.44px solid #e0e0e0;
            border-top: 4.44px solid var(--primary-color, #1976d2);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            will-change: transform;
        }

        /* Spin Rotation Animation */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div id="pageLoader">
    <div class="spinner"></div>
</div>

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

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Selamat Datang</h5>
                    <button type="button" class="btn btn-sm btn-light text-primary d-md-none rounded-pill border border-primary-subtle" onclick="showMobileInfo()">
                        <i class="fas fa-bullhorn me-1"></i> Info
                    </button>
                </div>

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
                                <input type="number" name="telegram_id" id="telegram_id_reg" class="form-control form-control-sm" placeholder="ID Telegram (Opsional)" value="{{ old('telegram_id') }}">
                                <div id="telegram_id_feedback" class="form-text mt-1" style="font-size: 0.7rem; color: #adb5bd;">Agar bisa otomatis login dengan Telegram nantinya.</div>
                            </div>
                            <div class="row g-2 mb-4">
                                <div class="col-6">
                                    <input type="password" name="password" class="form-control form-control-sm" required placeholder="Kata Sandi">
                                </div>
                                <div class="col-6">
                                    <input type="password" name="password_confirmation" class="form-control form-control-sm" required placeholder="Ulangi Sandi">
                                </div>
                            </div>

                            <button type="submit" id="btn-register" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                Daftar Sekarang <i class="fas fa-user-plus"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="footer-text">
                    <div class="text-uppercase fw-bold mb-1" style="letter-spacing: 1px; font-size: 0.65rem;">Supported By</div>
                    <div class="fw-bold fs-6 mb-3">Antigravity</div>
                    <div class="mt-3">&copy; {{ date('Y') }} dzulfikrialifajri_store</div>
                </div>
            </div>

            {{-- Kolom Kanan: Informasi / Banner (Hanya di layar besar) --}}
            <div class="col-md-6 auth-right d-none d-md-flex">
                <div class="info-box">
                    <h5 class="fw-bold mb-2"><i class="fas fa-bullhorn me-2"></i>Informasi Store</h5>
                    <p class="small mb-3 opacity-75" style="line-height: 1.6;">
                        {!! $announcement !!}
                    </p>
                    <div class="small opacity-75 mb-3">
                        <strong>Kontak Admin:</strong><br>
                        <a href="https://wa.me/6282269245660" target="_blank" class="text-white text-decoration-none mt-1 d-inline-block"><i class="fab fa-whatsapp"></i> 082269245660 - WA</a><br>
                        <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-white text-decoration-none mt-1 d-inline-block"><i class="fab fa-telegram"></i> @dzulfikrialifajri - Telegram</a>
                    </div>
                    <div class="mt-auto">
                        <span class="badge bg-light text-primary rounded-pill py-2 px-3 shadow-sm">
                            <i class="fas fa-users me-1"></i> Pengunjung Hari Ini: {{ $todayVisitors ?? 0 }}
                        </span>
                    </div>
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

    // Validasi Telegram ID secara real-time
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

            feedbackElem.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Mengecek...</span>';
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
                    feedbackElem.innerHTML = '<span class="text-danger">Gagal mengecek ID Telegram.</span>';
                    saveBtn.disabled = false; 
                });
            }, 500);
        });
    });
    function showMobileInfo() {
        Swal.fire({
            title: '<i class="fas fa-bullhorn text-primary me-2"></i>Informasi Store',
            html: `
                <div class="text-start">
                    <p class="small mb-3 opacity-75" style="line-height: 1.6;">
                        {!! addslashes($announcement) !!}
                    </p>
                    <div class="small opacity-75 mb-3 bg-light p-3 rounded border">
                        <strong class="d-block mb-2">Kontak Admin:</strong>
                        <a href="https://wa.me/6282269245660" target="_blank" class="text-decoration-none mb-2 d-block text-dark"><i class="fab fa-whatsapp text-success me-1"></i> 082269245660 - WA</a>
                        <a href="https://t.me/dzulfikrialifajri" target="_blank" class="text-decoration-none d-block text-dark"><i class="fab fa-telegram text-primary me-1"></i> @dzulfikrialifajri - Telegram</a>
                    </div>
                    <div class="text-center mt-3">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill py-2 px-3 shadow-sm border border-primary-subtle">
                            <i class="fas fa-users me-1"></i> Pengunjung Hari Ini: {{ $todayVisitors ?? 0 }}
                        </span>
                    </div>
                </div>
            `,
            confirmButtonText: 'Tutup',
            confirmButtonColor: '#1976d2',
            customClass: {
                popup: 'rounded-4'
            }
        });
    }

    // Auto-show popup di smartphone pada kunjungan pertama sesi ini
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth < 768) {
            if (!sessionStorage.getItem('announcement_shown')) {
                showMobileInfo();
                sessionStorage.setItem('announcement_shown', 'true');
            }
        }
    });
</script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
