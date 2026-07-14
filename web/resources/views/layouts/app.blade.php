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
    <link href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">

    {{-- Telegram WebApp SDK --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script>
        window.isAuthenticated = @json(auth()->check());
        
        document.addEventListener('DOMContentLoaded', function() {
            if (window.Telegram && window.Telegram.WebApp) {
                const tg = window.Telegram.WebApp;
                tg.ready();
                tg.expand();
                
                if (!window.isAuthenticated && tg.initData) {
                    fetch('/auth/telegram/webapp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ init_data: tg.initData })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = '/dashboard';
                        }
                    }).catch(err => console.error("WebApp Login Error:", err));
                }
            }
        });
    </script>

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
                    let loader = document.getElementById('pageLoader');
                    if (loader) {
                        loader.classList.remove('fade-out');
                    }
                    if (typeof startTopLoadingBar === 'function') {
                        startTopLoadingBar();
                    }
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
    <style>
        @media (max-width: 576px) {
            .navbar {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            .navbar-brand {
                font-size: 0.9rem !important;
            }
        }
        @media (max-width: 375px) {
            .navbar {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            .navbar-brand {
                font-size: 0.8rem !important;
                gap: 0.25rem !important;
            }
        }
    </style>
</head>
<body class="bg-body-tertiary">

<div id="pageLoader">
    <div class="spinner"></div>
</div>

@if(session()->has('admin_impersonator_id'))
<div class="bg-warning text-warning-emphasis py-2 px-4 d-flex justify-content-between align-items-center position-fixed w-100 shadow-sm" style="z-index: 1060; top: 0; left: 0; font-size: 0.9rem; height: 40px;">
    <div class="d-flex align-items-center gap-2">
        <i class="fas fa-user-secret"></i> 
        <span>Anda sedang login sebagai <strong>{{ Auth::user()->full_name ?? Auth::user()->username }}</strong> (Sesi Admin)</span>
    </div>
    <form action="{{ route('admin.users.stop-impersonating') }}" method="POST" class="m-0">
        @csrf
        <button type="submit" class="btn btn-xs btn-outline-dark rounded-pill px-3 py-0 font-weight-bold" style="font-size: 0.8rem; border-width: 2px; line-height: 1.5;">
            <i class="fas fa-sign-out-alt me-1"></i> Kembali ke Admin
        </button>
    </form>
</div>
<style>
    body {
        padding-top: 40px !important;
    }
    .navbar {
        top: 40px !important;
    }
    #sidebar {
        top: 97px !important;
        height: calc(100vh - 97px) !important;
    }
</style>
@endif

{{-- Top Navbar --}}
<nav class="navbar navbar-expand fixed-top shadow-sm px-4 bg-body border-bottom" style="z-index: 1030; top: 0;">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link link-body-emphasis text-decoration-none p-0 d-md-none" id="sidebarToggle" aria-label="Buka/Tutup Menu Navigasi">
            <i class="fas fa-bars fs-4"></i>
        </button>
        <div class="navbar-brand d-flex align-items-center gap-2 text-primary fw-bold m-0" style="font-size: 1.05rem;">
            <i class="fas fa-shopping-bag fs-5"></i>
            <span class="fw-semibold">{{ config('app.name', 'Dzulfikrialifajri Store') }} <span class="fw-normal text-secondary d-none d-sm-inline" style="font-size: 0.85rem; opacity: 0.8;">| @yield('page_subtitle', 'Dashboard')</span></span>
        </div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-3">
        {{-- Notification Bell (All Authenticated Users) --}}
        @php
            $unreadNotifications = Auth::user()->unreadNotifications()->take(5)->get();
            $totalUnreadCount = Auth::user()->unreadNotifications()->count();
        @endphp
        <div class="dropdown">
            <button class="btn btn-link link-body-emphasis p-0 position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifikasi" data-pex="xrpax4o-0">
                <i class="fas fa-bell fs-5" data-pex="xrpax4o-1"></i>
                @if($totalUnreadCount > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;" data-pex="xrpax4o-2">
                    {{ $totalUnreadCount > 99 ? '99+' : $totalUnreadCount }}
                </span>
                @endif
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0" style="width: 350px; border-radius: 12px; z-index: 1050; overflow: hidden;">
                <li class="bg-light px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary" data-pex="9c28hsh-7">Notifikasi</h6>
                    @if($totalUnreadCount > 0)
                    <span class="badge bg-primary rounded-pill">{{ $totalUnreadCount }} Baru</span>
                    @endif
                </li>
                
                <div class="notification-list" style="max-height: 400px; overflow-y: auto;">
                    @forelse($unreadNotifications as $notification)
                    <li>
                        <a class="dropdown-item py-3 px-3 d-flex align-items-start gap-3 border-bottom" href="{{ $notification->data['url'] ?? '#' }}" style="white-space: normal;" onclick="markSingleNotificationAsRead('{{ $notification->id }}')">
                            <div class="mt-1">
                                <i class="{{ $notification->data['icon'] ?? 'fas fa-bell text-secondary' }} fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold mb-1" style="font-size: 0.9rem; color: var(--bs-heading-color);" data-pex="9c28hsh-21">{{ $notification->data['title'] ?? 'Notifikasi' }}</div>
                                <div class="text-muted small" style="line-height: 1.4;" data-pex="9c28hsh-22">{{ $notification->data['message'] ?? '' }}</div>
                                
                                @if(($notification->data['type'] ?? '') === 'login_gagal' && isset($notification->data['ip_address']))
                                <div class="mt-2" onclick="event.stopPropagation(); event.preventDefault();">
                                    <form action="{{ route('admin.logins.block-ip') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="ip_address" value="{{ $notification->data['ip_address'] }}">
                                        <button type="submit" class="btn btn-sm btn-danger rounded-pill" style="font-size: 0.75rem;">
                                            <i class="fas fa-ban me-1"></i>Bukan Saya (Blokir)
                                        </button>
                                    </form>
                                </div>
                                @endif

                                <div class="text-muted mt-1" style="font-size: 0.75rem;" data-pex="9c28hsh-23"><i class="far fa-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </a>
                    </li>
                    @empty
                    <li>
                        <div class="dropdown-item py-4 text-center text-muted">
                            <i class="fas fa-check-circle fs-3 mb-2 text-success"></i><br>
                            <small>Tidak ada notifikasi baru.</small>
                        </div>
                    </li>
                    @endforelse
                </div>
                
                <li class="d-flex justify-content-between px-3 py-2 bg-light border-top">
                    <a href="javascript:void(0)" class="text-decoration-none small text-muted hover-primary" onclick="markAllNotificationsRead()"><i class="fas fa-check-double me-1"></i>Tandai Dibaca</a>
                    <a href="{{ route('notifications.index') }}" class="text-decoration-none small fw-bold text-primary"><i class="fas fa-list me-1"></i>Lihat Semua</a>
                </li>
            </ul>
        </div>

        {{-- Shopping Cart Icon --}}
        <a href="{{ route('cart.index') }}" class="btn btn-link link-body-emphasis p-0 position-relative me-2" title="Keranjang Belanja">
            <i class="fas fa-shopping-cart fs-5"></i>
            @php
                $cartCount = \App\Models\CartItem::where('user_id', Auth::id())->sum('quantity');
            @endphp
            @if($cartCount > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;" id="cart-badge-count">
                {{ $cartCount }}
            </span>
            @endif
        </a>

        

        {{-- Theme Toggle --}}
        <button class="btn btn-link link-body-emphasis p-0 me-2" id="themeToggle" title="{{ __('Toggle Theme') }}" aria-label="{{ __('Ganti Tema') }}">
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
                    <i class="fas fa-home"></i> <span>{{ __('Dashboard') }}</span>
                </a>
            </div>

            <div class="menu-group">
                <div class="menu-header">{{ __('Belanja') }}</div>
                <a href="{{ route('catalog.index') }}" class="menu-item {{ request()->routeIs('catalog.*') ? 'active' : '' }}">
                    <i class="fas fa-circle" style="font-size: 0.4rem; opacity: 0.6;"></i>
                    {{ __('Katalog Produk') }}
                </a>
                <a href="{{ route('orders.index') }}" class="menu-item {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                    <i class="fas fa-circle" style="font-size: 0.4rem; opacity: 0.6;"></i>
                    {{ __('Riwayat Pesanan') }}
                </a>
                <a href="{{ route('chat.index') }}" class="menu-item {{ request()->routeIs('chat.*') ? 'active' : '' }}">
                    <i class="fas fa-circle" style="font-size: 0.4rem; opacity: 0.6;"></i>
                    {{ __('Pusat Chat') }}
                </a>
            </div>

            <div class="menu-group">
                <div class="menu-header">{{ __('Akun') }}</div>
                <a href="{{ route('profile') }}" class="menu-item {{ request()->routeIs('profile') ? 'active' : '' }}">
                    <i class="fas fa-circle" style="font-size: 0.4rem; opacity: 0.6;"></i>
                    {{ __('Profil Saya') }}
                </a>
                <a href="{{ route('profile.logins') }}" class="menu-item {{ request()->routeIs('profile.logins') ? 'active' : '' }}">
                    <i class="fas fa-circle" style="font-size: 0.4rem; opacity: 0.6;"></i>
                    {{ __('Riwayat Login') }}
                </a>
            </div>

            @if(Auth::user()->role === 'admin')
            <div class="menu-group">
                <div class="menu-header text-primary"><i class="fas fa-shield-alt me-1"></i> {{ __('Admin Panel') }}</div>
                <a href="{{ route('admin.dashboard') }}" class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-chart-line"></i> {{ __('Dashboard Admin') }}
                </a>
                <a href="{{ route('admin.products.index') }}" class="menu-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                    <i class="fas fa-box"></i> {{ __('Katalog Admin') }}
                </a>
                
                <a href="{{ route('admin.stock.index') }}" class="menu-item {{ request()->routeIs('admin.stock.*') ? 'active' : '' }}">
                    <i class="fas fa-cubes"></i> {{ __('Kelola Stok') }}
                </a>
                <a href="{{ route('admin.orders.index') }}" class="menu-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                    <i class="fas fa-shopping-cart"></i> {{ __('Kelola Pesanan') }}
                </a>
                <a href="{{ route('admin.complaints.index') }}" class="menu-item {{ request()->routeIs('admin.complaints.*') ? 'active' : '' }}">
                    <i class="fas fa-toolbox"></i> {{ __('Kelola Komplain') }}
                </a>
                <a href="{{ route('admin.broadcast.index') }}" class="menu-item {{ request()->routeIs('admin.broadcast.*') ? 'active' : '' }}">
                    <i class="fas fa-bullhorn"></i> {{ __('Broadcast') }}
                </a>
                <a href="{{ route('admin.users.index') }}" class="menu-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="fas fa-users"></i> {{ __('Kelola Pelanggan') }}
                </a>
                <a href="{{ route('admin.sellers.index') }}" class="menu-item {{ request()->routeIs('admin.sellers.*') ? 'active' : '' }}">
                    <i class="fas fa-store"></i> {{ __('Kelola Seller') }}
                </a>
                <a href="{{ route('admin.withdrawals.index') }}" class="menu-item {{ request()->routeIs('admin.withdrawals.*') ? 'active' : '' }}">
                    <i class="fas fa-hand-holding-usd"></i> {{ __('Permintaan Payout') }}
                </a>
                <a href="{{ route('admin.coupons.index') }}" class="menu-item {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}">
                    <i class="fas fa-ticket-alt"></i> {{ __('Kelola Kupon') }}
                </a>
                <a href="{{ route('admin.logins.index') }}" class="menu-item {{ request()->routeIs('admin.logins.*') ? 'active' : '' }}">
                    <i class="fas fa-sign-in-alt"></i> {{ __('Percobaan Login') }}
                </a>
            </div>

            <div class="menu-group">
                <div class="menu-header text-success"><i class="fas fa-tools me-1"></i> {{ __('Tool') }}</div>
                <a href="{{ route('admin.tools.github-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.github-checker*') ? 'active' : '' }}">
                    <i class="fab fa-github"></i> {{ __('GitHub Live Checker') }}
                </a>
                <a href="{{ route('admin.tools.gmail-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.gmail-checker*') ? 'active' : '' }}">
                    <i class="fas fa-envelope"></i> {{ __('Gmail Live Checker') }}
                </a>
            </div>

            <div class="menu-group">
                <div class="menu-header text-danger"><i class="fas fa-cogs me-1"></i> {{ __('Sistem & Konfigurasi') }}</div>
                <a href="{{ route('admin.settings.index') }}" class="menu-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                    <i class="fas fa-sliders-h"></i> {{ __('Konfigurasi Sistem') }}
                </a>
                <a href="{{ route('admin.audit-logs.index') }}" class="menu-item {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                    <i class="fas fa-history"></i> {{ __('Log Audit') }}
                </a>
                <a href="{{ route('admin.website.settings') }}" class="menu-item {{ request()->routeIs('admin.website.settings') ? 'active' : '' }}">
                    <i class="fas fa-globe"></i> {{ __('Kelola Website') }}
                </a>
                <a href="{{ route('admin.backup.index') }}" class="menu-item {{ request()->routeIs('admin.backup.*') ? 'active' : '' }}">
                    <i class="fas fa-database"></i> {{ __('Backup & Restore') }}
                </a>
            </div>
            @endif

            @if(Auth::user()->role === 'seller')
            <div class="menu-group">
                <div class="menu-header text-info"><i class="fas fa-store me-1"></i> {{ __('Seller Portal') }}</div>
                <a href="{{ route('seller.dashboard') }}" class="menu-item {{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-chart-pie"></i> {{ __('Dashboard Seller') }}
                </a>
                <a href="{{ route('seller.products.index') }}" class="menu-item {{ request()->routeIs('seller.products.*') ? 'active' : '' }}">
                    <i class="fas fa-box-open"></i> {{ __('Produk Saya') }}
                </a>
                <a href="{{ route('seller.stock.index') }}" class="menu-item {{ request()->routeIs('seller.stock.*') ? 'active' : '' }}">
                    <i class="fas fa-cubes"></i> {{ __('Stok Akun') }}
                </a>
                <a href="{{ route('seller.orders.index') }}" class="menu-item {{ request()->routeIs('seller.orders.*') ? 'active' : '' }}">
                    <i class="fas fa-receipt"></i> {{ __('Kelola Pesanan') }}
                </a>
                <a href="{{ route('seller.complaints.index') }}" class="menu-item {{ request()->routeIs('seller.complaints.*') ? 'active' : '' }}">
                    <i class="fas fa-toolbox"></i> {{ __('Kelola Komplain') }}
                </a>
                <a href="{{ route('seller.finance.index') }}" class="menu-item {{ request()->routeIs('seller.finance.*') ? 'active' : '' }}">
                    <i class="fas fa-wallet"></i> {{ __('Dompet & Keuangan') }}
                </a>
                <a href="{{ route('seller.settings.index') }}" class="menu-item {{ request()->routeIs('seller.settings.*') ? 'active' : '' }}">
                    <i class="fas fa-user-cog"></i> {{ __('Pengaturan Karantina') }}
                </a>
            </div>
            @if(is_array(Auth::user()->allowed_tools) && count(Auth::user()->allowed_tools) > 0)
            <div class="menu-group">
                <div class="menu-header text-success"><i class="fas fa-tools me-1"></i> Tool</div>
                @if(in_array('github_checker', Auth::user()->allowed_tools))
                <a href="{{ route('admin.tools.github-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.github-checker*') ? 'active' : '' }}">
                    <i class="fab fa-github"></i> GitHub Live Checker
                </a>
                @endif
                @if(in_array('gmail_checker', Auth::user()->allowed_tools))
                <a href="{{ route('admin.tools.gmail-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.gmail-checker*') ? 'active' : '' }}">
                    <i class="fas fa-envelope"></i> Gmail Live Checker
                </a>
                @endif
            </div>
            @endif
            @endif

            <div class="menu-group mt-3 border-top pt-3 px-3">
                <div class="menu-header text-secondary"><i class="fas fa-language me-1"></i> {{ __('Bahasa / Language') }}</div>
                @php
                    $currentLocale = App::getLocale();
                @endphp
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <span class="small text-secondary">{{ __('Bahasa Aktif:') }} <strong class="text-uppercase">{{ $currentLocale }}</strong></span>
                    <a href="{{ route('lang.switch', $currentLocale === 'id' ? 'en' : 'id') }}" 
                       class="btn btn-xs btn-outline-secondary d-flex align-items-center gap-1 py-1 px-2 text-decoration-none"
                       style="font-size: 0.78rem;"
                       title="{{ $currentLocale === 'id' ? 'Switch to English' : 'Ubah ke Bahasa Indonesia' }}">
                        <i class="fas fa-globe text-secondary"></i>
                        <span>{{ $currentLocale === 'id' ? 'English' : 'Indonesia' }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <main class="main-content position-relative">
        <div class="main-background"></div>
        <div class="container-fluid position-relative px-4 pb-4 pt-4" style="z-index: 1;">
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
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>

@stack('scripts')

{{-- Global SweetAlert2 Session Flash --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if(session('swal_error'))
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: "{{ session('swal_error') }}",
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#dc3545',
                customClass: {
                    popup: 'rounded-4 border-0 shadow-lg',
                    confirmButton: 'btn btn-danger rounded-pill px-4'
                },
                buttonsStyling: false
            });
        @endif
        @if(session('swal_success'))
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: "{{ session('swal_success') }}",
                confirmButtonText: 'OK',
                confirmButtonColor: '#198754',
                customClass: {
                    popup: 'rounded-4 border-0 shadow-lg',
                    confirmButton: 'btn btn-success rounded-pill px-4'
                },
                buttonsStyling: false
            });
        @endif
    });
</script>

{{-- Modals Container --}}
<script>
    function markAllNotificationsRead() {
        fetch('{{ route("notifications.markAllRead") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.querySelector('.badge.bg-danger.rounded-pill');
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
                setTimeout(() => window.location.reload(), 1000);
            }
        });
    }

    function markSingleNotificationAsRead(id) {
        fetch('/notifications/mark-read/' + id, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        // Let the default navigation happen
    }

    function markBroadcastRead(jobId, redirectUrl) {
        fetch('/admin/broadcast/mark-read/' + jobId, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(() => {
            window.location.href = redirectUrl;
        })
        .catch(() => {
            window.location.href = redirectUrl;
        });
    }
</script>

@stack('modals')

</body>
</html>
