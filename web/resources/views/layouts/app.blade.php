<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'Jualan') }}</title>
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

    @stack('styles')
</head>
<body class="bg-body-tertiary">

{{-- Top Navbar --}}
<nav class="navbar navbar-expand fixed-top shadow-sm px-4 bg-body border-bottom" style="z-index: 1030; top: 0;">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link link-body-emphasis d-md-none text-decoration-none p-0" id="sidebarToggle">
            <i class="fas fa-bars fs-4"></i>
        </button>
        <div class="navbar-brand d-flex align-items-center gap-2 text-primary fw-bold m-0">
            <i class="fas fa-shopping-bag"></i>
            <span>{{ config('app.name', 'Jualan') }} <span class="fw-light text-secondary fs-6 d-none d-sm-inline">| @yield('page_subtitle', 'Dashboard')</span></span>
        </div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-3">
        {{-- Theme Toggle --}}
        <button class="btn btn-link link-body-emphasis p-0 me-2" id="themeToggle" title="Toggle Theme">
            <i class="fas fa-moon fs-5" id="themeIcon"></i>
        </button>

        {{-- Logout --}}
        <form action="{{ route('logout') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-2">
                <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
            </button>
        </form>
    </div>
</nav>

<div class="app-container" style="padding-top: 0;">
    {{-- Sidebar --}}
    <div class="sidebar" id="sidebar">
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
                    <i class="fas fa-box"></i> Kelola Produk
                </a>
                <a href="{{ route('admin.stock.index') }}" class="menu-item {{ request()->routeIs('admin.stock.*') ? 'active' : '' }}">
                    <i class="fas fa-cubes"></i> Kelola Stok
                </a>
                <a href="{{ route('admin.orders.index') }}" class="menu-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                    <i class="fas fa-shopping-cart"></i> Kelola Pesanan
                </a>
                <a href="{{ route('admin.users.index') }}" class="menu-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="fas fa-users"></i> Kelola Pelanggan
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- Main Content --}}
    <div class="main-content position-relative">
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
    </div>

    {{-- Sidebar overlay (mobile) --}}
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    {{-- Page Loader --}}
    <div class="page-loader" id="pageLoader">
        <div class="spinner"></div>
    </div>
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

{{-- Custom JS --}}
<script src="{{ asset('js/app.js') }}"></script>

@stack('scripts')
</body>
</html>
