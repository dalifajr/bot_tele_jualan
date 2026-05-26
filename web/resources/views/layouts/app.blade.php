<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    <meta name="description" content="@yield('meta_description', 'Platform jual beli produk digital terpercaya')">

    {{-- Google Fonts: Outfit --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Bootstrap 5.3 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Font Awesome 6 --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    {{-- Custom SIMAK-style CSS --}}
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmAction(event, message) {
            event.preventDefault();
            let element = event.currentTarget;
            let form = element.closest('form') || element;
            Swal.fire({
                title: 'Konfirmasi Aksi',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, lanjutkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (form.tagName === 'FORM') {
                        form.submit();
                    } else if (element.href) {
                        window.location.href = element.href;
                    }
                }
            });
        }
    </script>

    @stack('styles')
</head>
<body class="bg-body-tertiary">

{{-- Top Navbar --}}
<nav class="navbar navbar-expand fixed-top shadow-sm px-4 bg-body border-bottom" style="z-index: 1030; top: 0;">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link link-body-emphasis d-md-none text-decoration-none p-0" id="sidebarToggle" aria-label="Buka Menu Navigasi">
            <i class="fas fa-bars fs-4"></i>
        </button>
        <div class="navbar-brand d-flex align-items-center gap-2 text-primary fw-bold m-0">
            <i class="fas fa-shopping-bag"></i>
            <span>{{ config('app.name', 'Dzulfikrialifajri Store') }} <span class="fw-light text-secondary fs-6 d-none d-sm-inline">| @yield('page_subtitle', 'Dashboard')</span></span>
        </div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-3">
        @if(Auth::user()->role === 'admin')
        {{-- Notification Bell (Admin Only) --}}
        <div class="dropdown">
            <button class="btn btn-link link-body-emphasis p-0 position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifikasi">
                <i class="fas fa-bell fs-5"></i>
                @if(isset($totalNotifications) && $totalNotifications > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                    {{ $totalNotifications > 99 ? '99+' : $totalNotifications }}
                </span>
                @endif
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="width: 320px; border-radius: 12px; z-index: 1050;">
                <li><h6 class="dropdown-header fw-bold text-primary border-bottom pb-2 mb-2">Notifikasi Sistem</h6></li>
                
                @if(isset($pendingOrdersCount) && $pendingOrdersCount > 0)
                <li>
                    <a class="dropdown-item py-2 d-flex align-items-start gap-3" href="{{ route('admin.orders.index') }}">
                        <div class="text-warning mt-1"><i class="fas fa-shopping-cart"></i></div>
                        <div>
                            <div class="fw-bold">Pesanan Pending</div>
                            <small class="text-muted text-wrap">Ada {{ $pendingOrdersCount }} pesanan menunggu diproses.</small>
                        </div>
                    </a>
                </li>
                @endif
                
                @if(isset($pendingLoginsCount) && $pendingLoginsCount > 0)
                <li>
                    <a class="dropdown-item py-2 d-flex align-items-start gap-3" href="{{ route('admin.logins.index') }}">
                        <div class="text-info mt-1"><i class="fas fa-sign-in-alt"></i></div>
                        <div>
                            <div class="fw-bold">Percobaan Login</div>
                            <small class="text-muted text-wrap">Terdapat {{ $pendingLoginsCount }} permintaan login web belum terkonfirmasi.</small>
                        </div>
                    </a>
                </li>
                @endif

                @if(isset($readyStockCount))
                <li>
                    <a class="dropdown-item py-2 d-flex align-items-start gap-3" href="{{ route('admin.stock.index') }}">
                        <div class="text-success mt-1"><i class="fas fa-box-open"></i></div>
                        <div>
                            <div class="fw-bold">Stok Produk</div>
                            <small class="text-muted text-wrap">Total stok yang siap dijual: {{ $readyStockCount }} unit.</small>
                        </div>
                    </a>
                </li>
                @endif

                @if(!isset($totalNotifications) || $totalNotifications == 0)
                <li>
                    <div class="dropdown-item py-3 text-center text-muted">
                        <i class="fas fa-check-circle fs-4 mb-2 text-success"></i><br>
                        <small>Tidak ada notifikasi baru.</small>
                    </div>
                </li>
                @endif
                
                <li><hr class="dropdown-divider mb-0"></li>
                <li class="d-flex justify-content-between px-3 py-2 bg-light" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <a href="javascript:void(0)" class="text-decoration-none small text-muted hover-primary" onclick="markNotificationsRead()"><i class="fas fa-check-double me-1"></i>Tandai Dibaca</a>
                    <a href="{{ route('admin.logins.index') }}" class="text-decoration-none small fw-bold text-primary"><i class="fas fa-list me-1"></i>Lihat Semua</a>
                </li>
            </ul>
        </div>
        @endif

        {{-- Theme Toggle --}}
        <button class="btn btn-link link-body-emphasis p-0 me-2" id="themeToggle" title="Toggle Theme" aria-label="Ganti Tema">
            <i class="fas fa-moon fs-5" id="themeIcon"></i>
        </button>

        {{-- Logout --}}
        <form action="{{ route('logout') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-2" aria-label="Logout">
                <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
            </button>
        </form>
    </div>
</nav>

<div class="app-container">
    {{-- Sidebar --}}
    <div id="sidebar" class="sidebar" style="top: 57px; height: calc(100vh - 57px); z-index: 1040; overflow-y: auto; overscroll-behavior: contain;">
        <div class="sidebar-header d-flex align-items-center gap-3">
            <div class="user-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold text-white bg-primary" style="width: 40px; height: 40px;">
                {{ strtoupper(substr(Auth::user()->full_name ?? Auth::user()->username ?? 'U', 0, 1)) }}
            </div>
            <div class="d-flex flex-column">
                <span class="fw-bold text-body" style="font-size: 0.9rem;">{{ Str::limit(Auth::user()->full_name ?? Auth::user()->username ?? 'User', 20) }}</span>
                <small class="text-secondary" style="font-size: 0.75rem;">ID: {{ Auth::user()->telegram_id }}</small>
            </div>
        </div>

        <div class="py-3">
            <div class="menu-group">
                <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
            </div>

            <div class="menu-group">
                <div class="menu-header">Belanja</div>
                <a href="{{ route('catalog.index') }}" class="menu-item {{ request()->routeIs('catalog.*') ? 'active' : '' }}">
                    <i class="fas fa-circle" style="font-size: 0.4rem; opacity: 0.6;"></i>
                    Katalog Produk
                </a>
                <a href="{{ route('orders.index') }}" class="menu-item {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                    <i class="fas fa-circle" style="font-size: 0.4rem; opacity: 0.6;"></i>
                    Riwayat Pesanan
                </a>
            </div>

            <div class="menu-group">
                <div class="menu-header">Akun</div>
                <a href="{{ route('profile') }}" class="menu-item {{ request()->routeIs('profile') ? 'active' : '' }}">
                    <i class="fas fa-circle" style="font-size: 0.4rem; opacity: 0.6;"></i>
                    Profil Saya
                </a>
            </div>

            @if(Auth::user()->role === 'admin')
            <div class="menu-group">
                <div class="menu-header text-primary"><i class="fas fa-shield-alt me-1"></i> Admin Panel</div>
                <a href="{{ route('admin.dashboard') }}" class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-chart-line"></i> Dashboard Admin
                </a>
                <a href="{{ route('admin.products.index') }}" class="menu-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                    <i class="fas fa-box"></i> Katalog Admin
                </a>
                
                <a href="{{ route('admin.stock.index') }}" class="menu-item {{ request()->routeIs('admin.stock.*') ? 'active' : '' }}">
                    <i class="fas fa-cubes"></i> Kelola Stok
                </a>
                <a href="{{ route('admin.orders.index') }}" class="menu-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                    <i class="fas fa-shopping-cart"></i> Kelola Pesanan
                </a>
                <a href="{{ route('admin.complaints.index') }}" class="menu-item {{ request()->routeIs('admin.complaints.*') ? 'active' : '' }}">
                    <i class="fas fa-toolbox"></i> Kelola Komplain
                </a>
                <a href="{{ route('admin.broadcast.index') }}" class="menu-item {{ request()->routeIs('admin.broadcast.*') ? 'active' : '' }}">
                    <i class="fas fa-bullhorn"></i> Broadcast
                </a>
                <a href="{{ route('admin.users.index') }}" class="menu-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="fas fa-users"></i> Kelola Pelanggan
                </a>
            </div>

            <div class="menu-group">
                <div class="menu-header text-danger"><i class="fas fa-cogs me-1"></i> Sistem & Konfigurasi</div>
                <a href="{{ route('admin.settings.index') }}" class="menu-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                    <i class="fas fa-sliders-h"></i> Konfigurasi Sistem
                </a>
                <a href="{{ route('admin.reports.index') }}" class="menu-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                    <i class="fas fa-chart-bar"></i> Laporan Operasional
                </a>
                <a href="{{ route('admin.website.settings') }}" class="menu-item {{ request()->routeIs('admin.website.settings') ? 'active' : '' }}">
                    <i class="fas fa-globe"></i> Kelola Website
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- Main Content --}}
    <main class="main-content position-relative">
        <div class="main-background"></div>
        <div class="container-fluid position-relative px-4 py-4" style="z-index: 1;">
            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    {{-- Sidebar overlay (mobile) --}}
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

</div>

{{-- Floating Help Button (links to Telegram bot) --}}
@if(config('telegram.bot_username'))
<a href="https://t.me/{{ config('telegram.bot_username') }}"
   class="btn btn-primary rounded-pill shadow-lg position-fixed d-flex align-items-center justify-content-center gap-2 px-4"
   style="bottom: 30px; right: 30px; height: 50px; z-index: 1050; border: 2px solid rgba(255,255,255,0.2);"
   title="Hubungi via Telegram" target="_blank">
    <i class="fab fa-telegram fa-lg"></i>
    <span class="fw-bold">Bantuan</span>
</a>
@endif

{{-- Bootstrap JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

{{-- SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- Custom JS --}}
<script src="{{ asset('js/app.js') }}"></script>

@stack('scripts')

{{-- Modals Container --}}
<script>
    function markNotificationsRead() {
        fetch('{{ route("admin.notifications.markRead") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hide badge instantly
                const badge = document.querySelector('.badge.bg-danger.rounded-circle');
                if(badge) badge.style.display = 'none';
                
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Semua notifikasi telah ditandai dibaca.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });
    }
</script>

@stack('modals')

</body>
</html>
