<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="{{ request()->cookie('jualan-theme', 'light') }}">
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

                // Helper: Convert HEX to HSL
                window.hexToHsl = function(hex) {
                    var c = hex.replace('#', '');
                    if (c.length === 3) c = c[0]+c[0]+c[1]+c[1]+c[2]+c[2];
                    var r = parseInt(c.substring(0, 2), 16) / 255;
                    var g = parseInt(c.substring(2, 4), 16) / 255;
                    var b = parseInt(c.substring(4, 6), 16) / 255;
                    var max = Math.max(r, g, b), min = Math.min(r, g, b);
                    var h, s, l = (max + min) / 2;
                    if (max === min) {
                        h = s = 0;
                    } else {
                        var d = max - min;
                        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                        switch (max) {
                            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                            case g: h = (b - r) / d + 2; break;
                            case b: h = (r - g) / d + 4; break;
                        }
                        h /= 6;
                    }
                    return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
                };

                // Full Material You Dynamic Tonal Surface & Component System
                window.applyMaterialYouTheme = function(hexColor, isDark) {
                    if (!hexColor || !/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/.test(hexColor)) return;
                    
                    var c = hexColor;
                    if (c.length === 4) c = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
                    var r = parseInt(c.slice(1, 3), 16);
                    var g = parseInt(c.slice(3, 5), 16);
                    var b = parseInt(c.slice(5, 7), 16);
                    var hsl = window.hexToHsl(c);
                    var h = hsl.h;

                    var root = document.documentElement;
                    if (typeof isDark === 'undefined') {
                        isDark = root.getAttribute('data-bs-theme') === 'dark';
                    }

                    // 1. Primary Tokens
                    root.style.setProperty('--primary', c);
                    root.style.setProperty('--bs-primary', c);
                    root.style.setProperty('--primary-rgb', r + ', ' + g + ', ' + b);
                    root.style.setProperty('--bs-primary-rgb', r + ', ' + g + ', ' + b);
                    root.style.setProperty('--cm-sys-color-primary', c);

                    var tertH = (h + 50) % 360;

                    if (isDark) {
                        // Dark Mode M3 Tonal System (Tinted Surfaces)
                        var bgVal = 'hsl(' + h + ', 18%, 8.5%)';
                        var cardBg = 'hsl(' + h + ', 18%, 11%)';
                        var onSurface = 'hsl(' + h + ', 25%, 92%)';

                        root.style.setProperty('--primary-container', 'hsl(' + h + ', 55%, 30%)');
                        root.style.setProperty('--on-primary-container', 'hsl(' + h + ', 85%, 90%)');
                        root.style.setProperty('--secondary-container', 'hsl(' + h + ', 26%, 22%)');
                        root.style.setProperty('--on-secondary-container', 'hsl(' + h + ', 35%, 88%)');

                        root.style.setProperty('--tertiary', 'hsl(' + tertH + ', 50%, 75%)');
                        root.style.setProperty('--on-tertiary', 'hsl(' + tertH + ', 60%, 15%)');
                        root.style.setProperty('--tertiary-container', 'hsl(' + tertH + ', 50%, 28%)');
                        root.style.setProperty('--on-tertiary-container', 'hsl(' + tertH + ', 85%, 92%)');

                        root.style.setProperty('--background', bgVal);
                        root.style.setProperty('--surface', bgVal);
                        root.style.setProperty('--surface-bg', bgVal);
                        root.style.setProperty('--surface-container-lowest', 'hsl(' + h + ', 18%, 6.5%)');
                        root.style.setProperty('--surface-container-low', cardBg);
                        root.style.setProperty('--surface-container', 'hsl(' + h + ', 18%, 14%)');
                        root.style.setProperty('--surface-container-high', 'hsl(' + h + ', 18%, 17%)');
                        root.style.setProperty('--surface-container-highest', 'hsl(' + h + ', 18%, 21%)');
                        root.style.setProperty('--surface-variant', 'hsl(' + h + ', 18%, 26%)');
                        root.style.setProperty('--outline', 'hsl(' + h + ', 10%, 58%)');
                        root.style.setProperty('--outline-variant', 'hsl(' + h + ', 12%, 27%)');
                        root.style.setProperty('--on-surface', onSurface);
                        root.style.setProperty('--on-surface-variant', 'hsl(' + h + ', 18%, 78%)');

                        // Bootstrap Variables
                        root.style.setProperty('--bs-body-bg', bgVal);
                        root.style.setProperty('--bs-body-color', onSurface);
                        root.style.setProperty('--bs-card-bg', cardBg);
                        root.style.setProperty('--bs-tertiary-bg', 'hsl(' + h + ', 18%, 14%)');
                        root.style.setProperty('--bs-secondary-bg', cardBg);
                        root.style.setProperty('--bs-border-color', 'hsl(' + h + ', 12%, 27%)');
                    } else {
                        // Light Mode M3 Tonal System (Tinted Surfaces)
                        var bgVal = 'hsl(' + h + ', 35%, 97.2%)';
                        var cardBg = 'hsl(' + h + ', 30%, 99.4%)';
                        var onSurface = 'hsl(' + h + ', 35%, 12%)';

                        root.style.setProperty('--primary-container', 'hsl(' + h + ', 70%, 92%)');
                        root.style.setProperty('--on-primary-container', 'hsl(' + h + ', 85%, 22%)');
                        root.style.setProperty('--secondary-container', 'hsl(' + h + ', 42%, 91%)');
                        root.style.setProperty('--on-secondary-container', 'hsl(' + h + ', 50%, 18%)');

                        root.style.setProperty('--tertiary', 'hsl(' + tertH + ', 50%, 42%)');
                        root.style.setProperty('--on-tertiary', '#ffffff');
                        root.style.setProperty('--tertiary-container', 'hsl(' + tertH + ', 65%, 90%)');
                        root.style.setProperty('--on-tertiary-container', 'hsl(' + tertH + ', 85%, 16%)');

                        root.style.setProperty('--background', bgVal);
                        root.style.setProperty('--surface', bgVal);
                        root.style.setProperty('--surface-bg', bgVal);
                        root.style.setProperty('--surface-container-lowest', cardBg);
                        root.style.setProperty('--surface-container-low', 'hsl(' + h + ', 30%, 95.2%)');
                        root.style.setProperty('--surface-container', 'hsl(' + h + ', 30%, 92.5%)');
                        root.style.setProperty('--surface-container-high', 'hsl(' + h + ', 30%, 88.5%)');
                        root.style.setProperty('--surface-container-highest', 'hsl(' + h + ', 30%, 84.5%)');
                        root.style.setProperty('--surface-variant', 'hsl(' + h + ', 25%, 90.5%)');
                        root.style.setProperty('--outline', 'hsl(' + h + ', 18%, 52%)');
                        root.style.setProperty('--outline-variant', 'hsl(' + h + ', 22%, 82%)');
                        root.style.setProperty('--on-surface', onSurface);
                        root.style.setProperty('--on-surface-variant', 'hsl(' + h + ', 20%, 30%)');

                        // Bootstrap Variables
                        root.style.setProperty('--bs-body-bg', bgVal);
                        root.style.setProperty('--bs-body-color', onSurface);
                        root.style.setProperty('--bs-card-bg', cardBg);
                        root.style.setProperty('--bs-tertiary-bg', 'hsl(' + h + ', 30%, 92.5%)');
                        root.style.setProperty('--bs-secondary-bg', 'hsl(' + h + ', 30%, 95.2%)');
                        root.style.setProperty('--bs-border-color', 'hsl(' + h + ', 22%, 82%)');
                    }

                    if (document.body) {
                        document.body.style.backgroundColor = isDark ? 'hsl(' + h + ', 18%, 8.5%)' : 'hsl(' + h + ', 35%, 97.2%)';
                        document.body.style.color = isDark ? 'hsl(' + h + ', 25%, 92%)' : 'hsl(' + h + ', 35%, 12%)';
                    }

                    // 2. Legacy / Derivative Tokens
                    root.style.setProperty('--cm-sys-color-surface', root.style.getPropertyValue('--surface-container-lowest'));
                    root.style.setProperty('--cm-sys-color-surface-variant', root.style.getPropertyValue('--surface-container-low'));
                    root.style.setProperty('--cm-sys-color-container', root.style.getPropertyValue('--surface-container-low'));
                    root.style.setProperty('--cm-sys-color-container-high', root.style.getPropertyValue('--surface-container'));
                    root.style.setProperty('--cm-sys-color-hairline', root.style.getPropertyValue('--outline-variant'));
                    root.style.setProperty('--cm-sys-color-outline', root.style.getPropertyValue('--outline'));
                };

                // Apply saved user palette immediately
                var userPalette = @json(Auth::check() && Auth::user()->theme_color ? Auth::user()->theme_color : null) || localStorage.getItem('user_primary_palette');
                if (userPalette) {
                    window.applyMaterialYouTheme(userPalette, savedTheme === 'dark');
                }

                document.addEventListener('DOMContentLoaded', function() {
                    var p = @json(Auth::check() && Auth::user()->theme_color ? Auth::user()->theme_color : null) || localStorage.getItem('user_primary_palette');
                    if (p && typeof window.applyMaterialYouTheme === 'function') {
                        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                        window.applyMaterialYouTheme(p, dark);
                    }
                });
            } catch (e) {}
        })();
    </script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="server-time" content="{{ time() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    <meta name="description" content="@yield('meta_description', 'Platform jual beli produk digital terpercaya')">

    {{-- PWA Manifest & Mobile Meta Tags --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="{{ request()->cookie('jualan-theme') === 'dark' ? '#1f1f1f' : '#3367d6' }}" id="themeColorMeta">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Dzulfikrialifajri Store') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icons/icon-192x192.png') }}">

    {{-- Google Fonts: Poppins (Clean Modern Material 3 Display & Body) & Roboto Mono --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Roboto+Mono:wght@400;500;600&display=swap" rel="stylesheet">

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
                            window.location.reload();
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
        /* Ensure modals always stay above backdrops and stacking contexts */
        .modal {
            z-index: 1060 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }

        /* Horizontal category/status chips smooth scroll */
        .category-scroll-container {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            white-space: nowrap;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding-bottom: 0.25rem;
        }
        .category-scroll-container::-webkit-scrollbar {
            display: none;
        }
        .category-scroll-container .btn {
            white-space: nowrap !important;
            flex-shrink: 0 !important;
            height: 34px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Mobile Bottom Wrapper: Perfectly stacks Sticky Action Bar on top of Bottom Nav with ZERO gap */
        /* Mobile Bottom Dock: Perfectly unites Sticky Action Bar & Bottom Nav as ONE solid dock */
        @media (max-width: 991.98px) {
            .mobile-bottom-wrapper {
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                z-index: 1045 !important;
                display: flex !important;
                flex-direction: column !important;
                pointer-events: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                background: var(--surface-bg) !important;
                border-top: 1px solid var(--outline-variant) !important;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08) !important;
            }
            .mobile-bottom-wrapper .mobile-sticky-action-bar {
                position: static !important;
                bottom: auto !important;
                left: auto !important;
                right: auto !important;
                width: 100% !important;
                margin: 0 !important;
                background: var(--surface-container-low) !important;
                border: none !important;
                border-bottom: 1px solid var(--outline-variant) !important;
                box-shadow: none !important;
                padding: 0.75rem 1rem !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
                z-index: 1046 !important;
                box-sizing: border-box !important;
            }
            .mobile-bottom-wrapper .mobile-bottom-nav {
                position: static !important;
                bottom: auto !important;
                left: auto !important;
                right: auto !important;
                width: 100% !important;
                margin: 0 !important;
                height: auto !important;
                min-height: 58px !important;
                border: none !important;
                border-top: none !important;
                box-shadow: none !important;
                background: var(--surface-bg) !important;
                padding-bottom: env(safe-area-inset-bottom) !important;
            }

            @if(request()->routeIs('catalog.show') || request()->routeIs('checkout.*') || request()->routeIs('cart.*') || request()->routeIs('orders.show'))
            body {
                padding-bottom: calc(140px + env(safe-area-inset-bottom)) !important;
            }
            @endif
        }

        @media (max-width: 576px) {
            .navbar {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            .navbar-brand {
                font-size: 0.9rem !important;
            }
            .main-content .container-fluid {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
                padding-top: 1rem !important;
                padding-bottom: 1rem !important;
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
<body class="cm-gm2 cm-cm3">

<div id="pageLoader" class="fade-out">
    <div class="m3-loader-card d-inline-flex align-items-center gap-3">
        <div class="m3-spinner-circle" role="status" aria-label="{{ __('Memuat...') }}"></div>
        <span class="m3-loader-text">{{ __('Memuat...') }}</span>
    </div>
</div>

@if(session()->has('admin_impersonator_id'))
<div class="bg-warning text-warning-emphasis py-2 px-4 d-flex justify-content-between align-items-center position-fixed w-100 shadow-sm" style="z-index: 1060; top: 0; left: 0; font-size: 0.9rem; height: 40px;">
    <div class="d-flex align-items-center gap-2">
        <i class="fas fa-user-secret"></i> 
        <span>{{ __('Anda sedang login sebagai') }} <strong>{{ Auth::user()->full_name ?? Auth::user()->username }}</strong> ({{ __('Sesi Admin') }})</span>
    </div>
    <form action="{{ route('admin.users.stop-impersonating') }}" method="POST" class="m-0">
        @csrf
        <button type="submit" class="btn btn-xs btn-outline-dark rounded-pill px-3 py-0 font-weight-bold" style="font-size: 0.8rem; border-width: 2px; line-height: 1.5;">
            <i class="fas fa-sign-out-alt me-1"></i> {{ __('Kembali ke Admin') }}
        </button>
    </form>
</div>
<style>
    :root {
        --banner-height: 40px !important;
    }
</style>
@endif

@php
    $globalMaintenanceActive = \App\Models\BotSetting::where('key', 'maintenance_mode')->value('value') === '1';
    $hideSidebar = View::hasSection('no_sidebar') || ($noSidebar ?? false);
    $hideBottomNav = View::hasSection('no_bottom_nav') || ($noBottomNav ?? false);

    $auth = Auth::user();
    $unreadChatsCount = 0;
    $adminComplaintsCount = 0;
    $sellerComplaintsCount = 0;
    $customerComplaintsCount = 0;
    $adminOrdersCount = 0;
    $sellerOrdersCount = 0;
    $pendingPayoutsCount = 0;
    $pendingLoginsCount = 0;
    $customerLoginBadgeCount = 0;
    $canAccess2fa = false;

    if ($auth) {
        $unreadChatsCount = \App\Models\ChatMessage::where('receiver_id', $auth->id)->where('is_read', false)->count();
        
        if ($auth->role === 'admin') {
            $adminComplaintsCount = \App\Models\ComplaintCase::whereIn('status', ['open', 'customer_replied'])->count();
            $adminOrdersCount = \App\Models\Order::whereIn('status', ['pending_payment', 'paid'])->count();
            $pendingPayoutsCount = \App\Models\WithdrawalRequest::where('status', 'pending')->count();
            $pendingLoginsCount = \App\Models\TelegramLoginToken::where('status', 'pending')->count();
        } elseif ($auth->role === 'seller') {
            $sellerComplaintsCount = \App\Models\ComplaintCase::whereIn('status', ['open', 'customer_replied'])
                ->whereHas('order.items.product', function($q) use ($auth) {
                    $q->where('creator_id', $auth->id);
                })->count();
            $sellerOrdersCount = \App\Models\Order::whereIn('status', ['pending_payment', 'paid'])
                ->whereHas('items.product', function($q) use ($auth) {
                    $q->where('creator_id', $auth->id);
                })->count();
        } else {
            $customerComplaintsCount = \App\Models\ComplaintCase::where('customer_id', $auth->id)
                ->whereIn('status', ['seller_replied'])
                ->count();
                
            $customerPendingLogins = 0;
            if ($auth->telegram_id) {
                $customerPendingLogins = \App\Models\TelegramLoginToken::where('telegram_id', $auth->telegram_id)
                    ->where('status', 'pending')
                    ->count();
            }
            
            $userIps = \App\Models\LoginLog::where(function($q) use ($auth) {
                $q->where('user_id', $auth->id);
                if (!empty($auth->username)) {
                    $q->orWhere('username_or_email', $auth->username);
                }
                if (!empty($auth->email)) {
                    $q->orWhere('username_or_email', $auth->email);
                }
            })->pluck('ip_address')->unique();
            
            $blockedUserIpsCount = 0;
            foreach ($userIps as $ip) {
                if (\Illuminate\Support\Facades\Cache::has('blocked_ip:' . $ip)) {
                    $blockedUserIpsCount++;
                }
            }
            
            $customerLoginBadgeCount = $customerPendingLogins + $blockedUserIpsCount;
        }

        $tool2faAccessMode = \App\Models\BotSetting::where('key', 'tool_2fa_access_mode')->value('value') ?? 'all';
        $canAccess2fa = $auth->role === 'admin' 
            || $tool2faAccessMode === 'all' 
            || (is_array($auth->allowed_tools) && in_array('2fa_generator', $auth->allowed_tools));
    }
@endphp

@if($globalMaintenanceActive && Auth::check() && Auth::user()->role === 'admin' && !session()->has('admin_impersonator_id'))
<div class="bg-danger text-white py-1 px-4 d-flex justify-content-between align-items-center position-fixed w-100 shadow-sm" style="z-index: 1060; top: 0; left: 0; font-size: 0.85rem; height: 38px;">
    <div class="d-flex align-items-center gap-2">
        <i class="fas fa-exclamation-triangle"></i> 
        <span><strong>{{ __('MODE MAINTENANCE AKTIF:') }}</strong> {{ __('Pengguna non-admin diblokir dari login dan akses web.') }}</span>
    </div>
    <a href="{{ route('admin.settings.index') }}" class="btn btn-xs btn-light text-danger rounded-pill px-3 py-0 fw-bold" style="font-size: 0.75rem; line-height: 1.6;">
        <i class="fas fa-cog me-1"></i> {{ __('Kelola Maintenance') }}
    </a>
</div>
<style>
    :root {
        --banner-height: 38px !important;
    }
</style>
@endif

<nav class="navbar navbar-expand fixed-top px-3 px-md-4 pcc-platform-bar" style="z-index: 1030;">
    {{-- Mobile Brand: Only visible on mobile (< 992px) because desktop has brand in sidebar drawer --}}
    <div class="d-flex align-items-center gap-2 d-lg-none">
        <a href="{{ route('dashboard') }}" class="navbar-brand d-flex align-items-center gap-2 m-0 text-decoration-none" style="font-size: 0.95rem;">
            @include('partials.brand-logo', ['style' => 'width: 32px; height: 32px;'])
            <span class="fw-bold" style="color: var(--on-surface); letter-spacing: -0.01em;">
                {{ config('app.name', "dzulfikrialifajri's store") }}
            </span>
        </a>
    </div>

    <div class="ms-auto d-flex align-items-center gap-1">
        @unless($hideSidebar)
        {{-- Notification Bell (All Authenticated Users) --}}
        @php
            $unreadNotifications = Auth::check() ? Auth::user()->unreadNotifications()->take(5)->get() : collect();
            $totalUnreadCount = Auth::check() ? Auth::user()->unreadNotifications()->count() : 0;
        @endphp
        {{-- Notification Bell for Mobile (< 768px): Direct Link to Notifications Page (Poin 10) --}}
        <div class="d-md-none">
            <a href="{{ Auth::check() ? route('notifications.index') : route('login') }}" 
               @guest data-guest-modal="true" data-feature-name="notifikasi" @endguest 
               class="btn btn-link link-body-emphasis m3-icon-btn position-relative" 
               title="{{ __('Notifikasi') }}">
                <i class="fas fa-bell" style="font-size: 0.95rem;"></i>
                @if($totalUnreadCount > 0)
                <span class="m3-badge-count">
                    {{ $totalUnreadCount > 99 ? '99+' : $totalUnreadCount }}
                </span>
                @endif
            </a>
        </div>

        {{-- Notification Dropdown for Desktop (>= 768px): Material You M3 Floating Popover --}}
        <div class="dropdown d-none d-md-block">
            <button class="btn btn-link link-body-emphasis m3-icon-btn position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Notifikasi') }}" data-pex="xrpax4o-0">
                <i class="fas fa-bell" style="font-size: 0.95rem;" data-pex="xrpax4o-1"></i>
                @if($totalUnreadCount > 0)
                <span class="m3-badge-count" data-pex="xrpax4o-2">
                    {{ $totalUnreadCount > 99 ? '99+' : $totalUnreadCount }}
                </span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end mt-2 p-0" style="width: 360px; z-index: 1050; border-radius: var(--radius-xl, 24px); border: 1px solid var(--outline-variant); background: var(--surface-container-low); box-shadow: 0 12px 36px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.04); overflow: hidden;">
                {{-- M3 Popover Header --}}
                <div class="px-3.5 py-3 border-bottom d-flex justify-content-between align-items-center" style="border-color: var(--outline-variant) !important; background: var(--surface-container-low);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-bell text-primary" style="font-size: 0.95rem;"></i>
                        <span class="fw-bold" style="font-family: var(--font-poppins); font-size: 0.95rem; color: var(--on-surface);">{{ __('Notifikasi') }}</span>
                    </div>
                    @if($totalUnreadCount > 0)
                    <span class="badge rounded-pill fw-semibold" style="background: var(--primary); color: #ffffff; font-size: 0.72rem; padding: 4px 10px;">
                        {{ $totalUnreadCount }} {{ __('Baru') }}
                    </span>
                    @endif
                </div>
                
                {{-- Notification List --}}
                <div class="notification-list p-1" style="max-height: 380px; overflow-y: auto;">
                    @forelse($unreadNotifications as $notification)
                    <div class="mb-1">
                        <a class="dropdown-item py-2.5 px-3 d-flex align-items-start gap-2.5 rounded-3" href="{{ $notification->data['url'] ?? '#' }}" style="white-space: normal; transition: background 0.15s ease;" onclick="markSingleNotificationAsRead('{{ $notification->id }}')">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-0.5" style="width: 36px; height: 36px; background: var(--primary-container); color: var(--primary); font-size: 0.9rem;">
                                <i class="{{ $notification->data['icon'] ?? 'fas fa-bell' }}"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-semibold mb-0.5" style="font-size: 0.85rem; color: var(--on-surface);">{{ $notification->data['title'] ?? __('Notifikasi') }}</div>
                                <div class="text-secondary small" style="font-size: 0.8rem; line-height: 1.35;">{{ $notification->data['message'] ?? '' }}</div>
                                
                                @if(($notification->data['type'] ?? '') === 'login_gagal' && isset($notification->data['ip_address']))
                                <div class="mt-2" onclick="event.stopPropagation(); event.preventDefault();">
                                    <form action="{{ route('profile.logins.block-ip') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="ip_address" value="{{ $notification->data['ip_address'] }}">
                                        <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 py-1" style="font-size: 0.72rem; font-weight: 600;">
                                            <i class="fas fa-ban me-1"></i>{{ __('Bukan Saya (Blokir)') }}
                                        </button>
                                    </form>
                                </div>
                                @endif

                                <div class="text-secondary mt-1.5 opacity-75" style="font-size: 0.7rem;"><i class="far fa-clock me-1"></i>{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </a>
                    </div>
                    @empty
                    <div class="py-4 px-3 text-center d-flex flex-column align-items-center justify-content-center">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px; background: var(--secondary-container); color: var(--on-secondary-container); font-size: 1.25rem;">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="fw-semibold text-body" style="font-size: 0.9rem;">{{ __('Semua bersih!') }}</div>
                        <small class="text-secondary" style="font-size: 0.8rem;">{{ __('Tidak ada notifikasi baru saat ini.') }}</small>
                    </div>
                    @endforelse
                </div>
                
                {{-- M3 Popover Footer --}}
                <div class="d-flex justify-content-between align-items-center px-3.5 py-2.5 border-top" style="border-color: var(--outline-variant) !important; background: var(--surface-container-low);">
                    <button type="button" class="btn btn-sm btn-link text-decoration-none text-secondary p-0 fw-semibold d-inline-flex align-items-center gap-1.5" onclick="markAllNotificationsRead()" style="font-size: 0.8125rem;">
                        <i class="fas fa-check-double"></i>
                        <span>{{ __('Tandai Dibaca') }}</span>
                    </button>
                    <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 text-decoration-none" style="font-size: 0.8125rem;">
                        <span>{{ __('Lihat Semua') }}</span>
                        <i class="fas fa-arrow-right" style="font-size: 0.7rem;"></i>
                    </a>
                </div>
            </div>
        </div>

        {{-- Shopping Cart Icon --}}
        <a href="{{ Auth::check() ? route('cart.index') : route('login') }}" 
           @guest data-guest-modal="true" data-feature-name="keranjang belanja" @endguest 
           class="btn btn-link link-body-emphasis m3-icon-btn position-relative" 
           title="{{ __('Keranjang Belanja') }}" 
           id="headerCartBtn">
            <i class="fas fa-shopping-cart" style="font-size: 0.95rem;"></i>
            @php
                $cartCount = Auth::check() ? \App\Models\CartItem::where('user_id', Auth::id())->sum('quantity') : 0;
            @endphp
            <span class="m3-badge-count {{ $cartCount > 0 ? '' : 'd-none' }}" id="cart-badge-count">
                {{ $cartCount > 99 ? '99+' : $cartCount }}
            </span>
        </a>
        @endunless

        {{-- Theme Toggle --}}
        <button class="btn btn-link link-body-emphasis m3-icon-btn me-1" id="themeToggle" title="{{ __('Toggle Theme') }}" aria-label="{{ __('Ganti Tema') }}">
            <i class="fas fa-moon" style="font-size: 0.95rem;" id="themeIcon"></i>
        </button>

        {{-- User Avatar Dropdown (Desktop >= 992px) - Figma M3 Style --}}
        @auth
        <div class="dropdown d-none d-lg-block ms-1">
            <button class="m3-user-avatar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ Auth::user()->full_name ?? Auth::user()->username }}">
                <div class="m3-user-avatar-img">
                    @if(Auth::user()->avatar)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url(Auth::user()->avatar) }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    @else
                        {{ strtoupper(substr(Auth::user()->full_name ?? Auth::user()->username ?? 'U', 0, 1)) }}
                    @endif
                </div>
                <i class="fas fa-chevron-down" style="font-size: 0.72rem; color: var(--on-surface-variant);"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2" style="border-radius: var(--radius-md); min-width: 220px; background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                <li class="px-3 py-2 border-bottom" style="border-color: var(--outline-variant) !important;">
                    <div class="fw-bold text-truncate" style="color: var(--on-surface);">{{ Auth::user()->full_name ?? Auth::user()->username }}</div>
                    <small class="text-muted text-truncate d-block">{{ Auth::user()->email ?: (Auth::user()->telegram_id ? 'TG: ' . Auth::user()->telegram_id : 'Member') }}</small>
                </li>
                <li><a class="dropdown-item py-2" href="{{ route('profile') }}"><i class="fas fa-user-circle me-2 text-primary"></i>{{ __('Profil Saya') }}</a></li>
                <li><a class="dropdown-item py-2" href="{{ route('profile.logins') }}"><i class="fas fa-shield-halved me-2 text-info"></i>{{ __('Riwayat Login') }}</a></li>
                @if(Auth::user()->role === 'admin')
                <li><a class="dropdown-item py-2" href="{{ route('admin.dashboard') }}"><i class="fas fa-chart-line me-2 text-warning"></i>{{ __('Admin Panel') }}</a></li>
                @elseif(Auth::user()->role === 'seller')
                <li><a class="dropdown-item py-2" href="{{ route('seller.dashboard') }}"><i class="fas fa-store me-2 text-warning"></i>{{ __('Seller Portal') }}</a></li>
                @endif
                <li><hr class="dropdown-divider" style="border-color: var(--outline-variant);"></li>
                <li>
                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger py-2">
                            <i class="fas fa-sign-out-alt me-2"></i>{{ __('Keluar dari akun') }}
                        </button>
                    </form>
                </li>
            </ul>
        </div>
        @else
        <a href="{{ route('login') }}" class="btn btn-sm btn-primary rounded-pill px-3 d-none d-lg-flex align-items-center gap-1.5 ms-1">
            <i class="fas fa-sign-in-alt"></i> <span>{{ __('Masuk') }}</span>
        </a>
        @endauth
    </div>
</nav>

<div class="app-container">
    @unless($hideSidebar)
    {{-- Sidebar (Desktop Column / Mobile Card Sheet) --}}
    <div id="sidebar" class="sidebar" style="z-index: 1040; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: none; -ms-overflow-style: none;">
        {{-- Mobile Bottom Sheet Grab Handle & Header (Mobile Only) --}}
        <div class="sheet-header-mobile d-lg-none sticky-top bg-body pt-2 pb-1 border-bottom" style="border-top-left-radius: var(--radius-xl); border-top-right-radius: var(--radius-xl); z-index: 10;">
            <div class="sheet-handle-area" id="sheetHandleArea" title="{{ __('Geser ke bawah untuk menutup') }}">
                <div class="sheet-handle-bar"></div>
            </div>
            <div class="d-flex align-items-center justify-content-between px-3 pb-2 pt-1">
                <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                    @include('partials.brand-logo', ['style' => 'width: 32px; height: 32px;'])
                    <div class="d-flex flex-column text-start" style="line-height: 1.15;">
                        <span class="fw-bold text-body" style="font-size: 0.92rem; letter-spacing: -0.01em;">{{ config('app.name', 'dzulfikrialifajri') }}</span>
                        <small class="text-secondary" style="font-size: 0.72rem; font-weight: 500;">{{ __('digital store') }}</small>
                    </div>
                </a>
                <button type="button" class="btn btn-sm btn-icon btn-light rounded-pill text-secondary" id="sidebarCloseBtn" aria-label="{{ __('Tutup') }}" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; padding: 0;">
                    <i class="fas fa-times" style="font-size: 0.9rem;"></i>
                </button>
            </div>
        </div>
        {{-- Desktop Top Brand Header & Segmented Belanja/Admin Switcher (Figma M3 Style) --}}
        <div class="d-none d-lg-block sticky-top" style="position: sticky; top: 0; z-index: 10; background: var(--surface-container-low); padding-bottom: 4px;">
            <a href="{{ route('dashboard') }}" class="m3-brand-header">
                @include('partials.brand-logo')
                <div class="m3-brand-text">
                    <span class="m3-brand-title">{{ config('app.name', 'dzulfikrialifajri') }}</span>
                    <span class="m3-brand-subtitle">{{ __('digital store') }}</span>
                </div>
            </a>

            @if(Auth::check() && in_array(Auth::user()->role, ['admin', 'seller']))
            @php
                $userRole = Auth::user()->role;
                $isAdmin = $userRole === 'admin';
                $isSeller = $userRole === 'seller';
                $isManagementMode = ($isAdmin && (request()->routeIs('admin.*') || request('view') === 'admin')) || 
                                   ($isSeller && (request()->routeIs('seller.*') || request('view') === 'seller'));
            @endphp
            <div class="m3-segmented-switch mb-3 mx-3">
                <button type="button" class="m3-segmented-item tab-btn-belanja {{ !$isManagementMode ? 'active' : '' }}" onclick="switchSidebarRoleTab('belanja')" title="{{ __('Mode Belanja') }}">
                    {{ __('Belanja') }}
                </button>
                <button type="button" class="m3-segmented-item tab-btn-management {{ $isManagementMode ? 'active' : '' }}" onclick="switchSidebarRoleTab('management')" title="{{ $isAdmin ? __('Panel Admin') : __('Panel Seller') }}">
                    {{ $isAdmin ? __('Admin') : __('Seller') }}
                </button>
            </div>
            @endif
        </div>

        @auth
        {{-- User Header on Mobile Sheet Only (< 992px) --}}
        <div class="sidebar-header d-flex align-items-center gap-3 d-lg-none">
            <div class="user-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold text-white bg-primary overflow-hidden" style="width: 40px; height: 40px; flex-shrink: 0;">
                @if(Auth::user()->avatar)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url(Auth::user()->avatar) }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                @else
                    {{ strtoupper(substr(Auth::user()->full_name ?? Auth::user()->username ?? 'U', 0, 1)) }}
                @endif
            </div>
            <div class="d-flex flex-column text-truncate">
                <span class="fw-bold text-body text-truncate" style="font-size: 0.9rem;">{{ Str::limit(Auth::user()->full_name ?? Auth::user()->username ?? 'User', 20) }}</span>
                <small class="text-secondary" style="font-size: 0.75rem;">ID: {{ Auth::user()->telegram_id ?: 'Web User' }}</small>
            </div>
            {{-- Logout Button on Mobile (#sidebar > div:nth-of-type(2)) --}}
            <div class="ms-auto d-lg-none">
                <form action="{{ route('logout') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1 d-flex align-items-center gap-1.5" aria-label="{{ __('Logout') }}" title="{{ __('Logout') }}">
                        <i class="fas fa-sign-out-alt"></i> <span style="font-size: 0.78rem;" class="fw-semibold">{{ __('Logout') }}</span>
                    </button>
                </form>
            </div>
        </div>

        @if(Auth::check() && in_array(Auth::user()->role, ['admin', 'seller']))
        <div class="m3-segmented-switch my-2 mx-3 d-lg-none">
            <button type="button" class="m3-segmented-item tab-btn-belanja {{ !$isManagementMode ? 'active' : '' }}" onclick="switchSidebarRoleTab('belanja')" title="{{ __('Mode Belanja') }}">
                {{ __('Belanja') }}
            </button>
            <button type="button" class="m3-segmented-item tab-btn-management {{ $isManagementMode ? 'active' : '' }}" onclick="switchSidebarRoleTab('management')" title="{{ $isAdmin ? __('Panel Admin') : __('Panel Seller') }}">
                {{ $isAdmin ? __('Admin') : __('Seller') }}
            </button>
        </div>
        @endif
        @else
        <div class="sidebar-header d-flex align-items-center gap-3 bg-body-tertiary rounded-3 p-3 m-2 border d-lg-none">
            <div class="user-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold text-white bg-secondary" style="width: 40px; height: 40px; flex-shrink: 0;">
                <i class="fas fa-user"></i>
            </div>
            <div class="d-flex flex-column text-truncate">
                <span class="fw-bold text-body text-truncate" style="font-size: 0.9rem;">{{ __('Tamu (Guest)') }}</span>
                <small class="text-muted" style="font-size: 0.75rem;">{{ __('Belum masuk akun') }}</small>
            </div>
            <div class="ms-auto">
                <a href="{{ route('login') }}" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold" style="font-size: 0.78rem;">
                    {{ __('Login') }}
                </a>
            </div>
        </div>
        @endauth

        <div class="py-2">
            @php
                $userRole = Auth::check() ? Auth::user()->role : 'guest';
                $isAdmin = $userRole === 'admin';
                $isSeller = $userRole === 'seller';
                $isManagementMode = ($isAdmin && (request()->routeIs('admin.*') || request('view') === 'admin')) || 
                                   ($isSeller && (request()->routeIs('seller.*') || request('view') === 'seller'));
            @endphp

            {{-- 1. MODE BELANJA (Untuk Customer, Tamu, atau Admin/Seller di tab Belanja) --}}
            <div id="sidebarMenuBelanja" class="{{ (in_array($userRole, ['admin', 'seller']) && $isManagementMode) ? 'd-none' : '' }}">
                <div class="menu-group">
                    <div class="menu-header px-3"><i class="fas fa-compass me-1 text-primary"></i> {{ __('MENU UTAMA') }}</div>
                    <div class="menu-items-grid">
                        <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-home text-primary"></i></div>
                            <span class="menu-label-text">{{ __('Home') }}</span>
                        </a>
                        <a href="{{ route('catalog.index') }}" class="menu-item {{ request()->routeIs('catalog.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-store text-primary"></i></div>
                            <span class="menu-label-text">{{ __('Katalog Produk') }}</span>
                        </a>
                        <a href="{{ route('orders.index') }}" @guest data-guest-modal="true" data-feature-name="riwayat pesanan" @endguest class="menu-item {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-receipt text-success"></i></div>
                            <span class="menu-label-text">{{ __('Riwayat Pesanan') }}</span>
                        </a>
                        <a href="{{ route('customer.complaints.index') }}" @guest data-guest-modal="true" data-feature-name="kelola komplain" @endguest class="menu-item {{ request()->routeIs('customer.complaints.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-headset text-warning"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Komplain') }}</span>
                            @if($customerComplaintsCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $customerComplaintsCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('chat.index') }}" @guest data-guest-modal="true" data-feature-name="pusat chat" @endguest class="menu-item {{ request()->routeIs('chat.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-comments text-info"></i></div>
                            <span class="menu-label-text">{{ __('Pusat Chat') }}</span>
                            @if($unreadChatsCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $unreadChatsCount }}</span>
                            @endif
                        </a>
                        @if(Auth::check() && Auth::user()->canUseRekber())
                        <a href="{{ route('escrow.index') }}" class="menu-item {{ request()->routeIs('escrow.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-handshake text-info"></i></div>
                            <span class="menu-label-text">{{ __('Rekber (Escrow)') }}</span>
                            @if(isset($activeEscrowCount) && $activeEscrowCount > 0)
                                <span class="badge bg-primary rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $activeEscrowCount }}</span>
                            @endif
                        </a>
                        @endif
                    </div>
                </div>

                <div class="menu-group mt-2">
                    <div class="menu-header px-3"><i class="fas fa-user-circle me-1 text-primary"></i> {{ __('AKUN') }}</div>
                    <div class="menu-items-grid">
                        <a href="{{ route('profile') }}" @guest data-guest-modal="true" data-feature-name="profil saya" @endguest class="menu-item {{ request()->routeIs('profile') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-user-circle text-primary"></i></div>
                            <span class="menu-label-text">{{ __('Profil Saya') }}</span>
                        </a>
                        <a href="{{ route('profile.logins') }}" @guest data-guest-modal="true" data-feature-name="riwayat login" @endguest class="menu-item {{ request()->routeIs('profile.logins') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-shield-halved text-danger"></i></div>
                            <span class="menu-label-text">{{ __('Riwayat Login') }}</span>
                            @if($customerLoginBadgeCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $customerLoginBadgeCount }}</span>
                            @endif
                        </a>
                    </div>
                </div>

                @if(Auth::check() && $canAccess2fa && Auth::user()->role === 'customer')
                <div class="menu-group mt-2">
                    <div class="menu-header px-3 text-success"><i class="fas fa-tools me-1"></i> {{ __('TOOL') }}</div>
                    <div class="menu-items-grid">
                        <a href="{{ route('tools.2fa-generator') }}" class="menu-item {{ request()->routeIs('tools.2fa-generator*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-key text-success"></i></div>
                            <span class="menu-label-text">{{ __('Generator Kode 2FA') }}</span>
                        </a>
                    </div>
                </div>
                @endif
            </div>

            {{-- 2. MODE MANAGEMENT UNTUK ADMIN --}}
            @if($isAdmin)
            <div id="sidebarMenuManagement" class="{{ !$isManagementMode ? 'd-none' : '' }}">
                <div class="menu-group">
                    <div class="menu-header px-3 text-primary"><i class="fas fa-shield-alt me-1"></i> {{ __('PANEL ADMIN') }}</div>
                    <div class="menu-items-grid">
                        <a href="{{ route('admin.dashboard') }}" class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-chart-line text-primary"></i></div>
                            <span class="menu-label-text">{{ __('Dashboard Admin') }}</span>
                        </a>
                        <a href="{{ route('admin.products.index') }}" class="menu-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-boxes-stacked text-info"></i></div>
                            <span class="menu-label-text">{{ __('Katalog Admin') }}</span>
                        </a>
                        <a href="{{ route('admin.stock.index') }}" class="menu-item {{ request()->routeIs('admin.stock.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-cubes text-warning"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Stok') }}</span>
                        </a>
                        <a href="{{ route('admin.orders.index') }}" class="menu-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-cart-shopping text-success"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Pesanan') }}</span>
                            @if($adminOrdersCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $adminOrdersCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.complaints.index') }}" class="menu-item {{ request()->routeIs('admin.complaints.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-headset text-danger"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Komplain') }}</span>
                            @if($adminComplaintsCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $adminComplaintsCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.broadcast.index') }}" class="menu-item {{ request()->routeIs('admin.broadcast.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-bullhorn text-warning"></i></div>
                            <span class="menu-label-text">{{ __('Broadcast') }}</span>
                        </a>
                        <a href="{{ route('admin.users.index') }}" class="menu-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-users text-primary"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Pelanggan') }}</span>
                        </a>
                        <a href="{{ route('admin.sellers.index') }}" class="menu-item {{ request()->routeIs('admin.sellers.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-store text-info"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Seller') }}</span>
                        </a>
                        <a href="{{ route('admin.withdrawals.index') }}" class="menu-item {{ request()->routeIs('admin.withdrawals.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-money-bill-wave text-success"></i></div>
                            <span class="menu-label-text">{{ __('Permintaan Payout') }}</span>
                            @if($pendingPayoutsCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $pendingPayoutsCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.coupons.index') }}" class="menu-item {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-ticket text-danger"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Kupon') }}</span>
                        </a>
                        <a href="{{ route('admin.promos.index') }}" class="menu-item {{ request()->routeIs('admin.promos.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-bullhorn text-warning"></i></div>
                            <span class="menu-label-text">{{ __('Promo Dashboard') }}</span>
                        </a>
                        <a href="{{ route('admin.logins.index') }}" class="menu-item {{ request()->routeIs('admin.logins.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-right-to-bracket text-secondary"></i></div>
                            <span class="menu-label-text">{{ __('Percobaan Login') }}</span>
                            @if($pendingLoginsCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $pendingLoginsCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.escrow.index') }}" class="menu-item {{ request()->routeIs('admin.escrow.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-handshake text-info"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Rekber') }}</span>
                            @if(isset($adminEscrowCount) && $adminEscrowCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $adminEscrowCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.remote-control.index') }}" class="menu-item {{ request()->routeIs('admin.remote-control*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-mobile-screen-button text-primary"></i></div>
                            <span class="menu-label-text">{{ __('Remote Control HP') }}</span>
                            <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.65rem;">ROOT</span>
                        </a>
                    </div>
                </div>

                <div class="menu-group mt-2">
                    <div class="menu-header px-3 text-success"><i class="fas fa-tools me-1"></i> {{ __('TOOL') }}</div>
                    <div class="menu-items-grid">
                        <a href="{{ route('admin.tools.github-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.github-checker*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fab fa-github text-body"></i></div>
                            <span class="menu-label-text">{{ __('GitHub Live Checker') }}</span>
                        </a>
                        <a href="{{ route('admin.tools.gmail-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.gmail-checker*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-envelope text-danger"></i></div>
                            <span class="menu-label-text">{{ __('Gmail Live Checker') }}</span>
                        </a>
                        <a href="{{ route('tools.2fa-generator') }}" class="menu-item {{ request()->routeIs('tools.2fa-generator*') || request()->routeIs('admin.tools.2fa-generator*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-key text-success"></i></div>
                            <span class="menu-label-text">{{ __('Generator Kode 2FA') }}</span>
                        </a>
                    </div>
                </div>

                <div class="menu-group mt-2">
                    <div class="menu-header px-3 text-danger"><i class="fas fa-cogs me-1"></i> {{ __('SISTEM & KONFIGURASI') }}</div>
                    <div class="menu-items-grid">
                        <a href="{{ route('admin.settings.index') }}" class="menu-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-sliders text-danger"></i></div>
                            <span class="menu-label-text">{{ __('Konfigurasi Sistem') }}</span>
                        </a>
                        <a href="{{ route('admin.audit-logs.index') }}" class="menu-item {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-clock-rotate-left text-warning"></i></div>
                            <span class="menu-label-text">{{ __('Log Audit') }}</span>
                        </a>
                        <a href="{{ route('admin.website.settings') }}" class="menu-item {{ request()->routeIs('admin.website.settings') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-globe text-primary"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Website') }}</span>
                        </a>
                        <a href="{{ route('admin.backup.index') }}" class="menu-item {{ request()->routeIs('admin.backup.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-database text-info"></i></div>
                            <span class="menu-label-text">{{ __('Backup & Restore') }}</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- 3. MODE MANAGEMENT UNTUK SELLER --}}
            @elseif($isSeller)
            <div id="sidebarMenuManagement" class="{{ !$isManagementMode ? 'd-none' : '' }}">
                <div class="menu-group">
                    <div class="menu-header px-3 text-info"><i class="fas fa-store me-1"></i> {{ __('PANEL MITRA') }}</div>
                    <div class="menu-items-grid">
                        <a href="{{ route('seller.dashboard') }}" class="menu-item {{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-chart-pie text-info"></i></div>
                            <span class="menu-label-text">{{ __('Dashboard Seller') }}</span>
                        </a>
                        <a href="{{ route('seller.products.index') }}" class="menu-item {{ request()->routeIs('seller.products.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-box-open text-primary"></i></div>
                            <span class="menu-label-text">{{ __('Produk Saya') }}</span>
                        </a>
                        <a href="{{ route('seller.stock.index') }}" class="menu-item {{ request()->routeIs('seller.stock.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-cubes text-warning"></i></div>
                            <span class="menu-label-text">{{ __('Stok Akun') }}</span>
                        </a>
                        <a href="{{ route('seller.orders.index') }}" class="menu-item {{ request()->routeIs('seller.orders.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-receipt text-success"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Pesanan') }}</span>
                            @if($sellerOrdersCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $sellerOrdersCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('seller.complaints.index') }}" class="menu-item {{ request()->routeIs('seller.complaints.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-headset text-danger"></i></div>
                            <span class="menu-label-text">{{ __('Kelola Komplain') }}</span>
                            @if($sellerComplaintsCount > 0)
                                <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $sellerComplaintsCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('seller.finance.index') }}" class="menu-item {{ request()->routeIs('seller.finance.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-wallet text-success"></i></div>
                            <span class="menu-label-text">{{ __('Dompet & Keuangan') }}</span>
                        </a>
                        <a href="{{ route('seller.coupons.index') }}" class="menu-item {{ request()->routeIs('seller.coupons.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-tags text-warning"></i></div>
                            <span class="menu-label-text">{{ __('Kupon & Promosi') }}</span>
                        </a>
                        <a href="{{ route('seller.settings.index') }}" class="menu-item {{ request()->routeIs('seller.settings.*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-user-gear text-secondary"></i></div>
                            <span class="menu-label-text">{{ __('Pengaturan Karantina') }}</span>
                        </a>
                    </div>
                </div>

                @php
                    $sellerHasTools = (is_array(Auth::user()->allowed_tools) && count(Auth::user()->allowed_tools) > 0) || $canAccess2fa;
                @endphp
                @if($sellerHasTools)
                <div class="menu-group mt-2">
                    <div class="menu-header px-3 text-success"><i class="fas fa-tools me-1"></i> {{ __('TOOL') }}</div>
                    <div class="menu-items-grid">
                        @if(is_array(Auth::user()->allowed_tools) && in_array('github_checker', Auth::user()->allowed_tools))
                        <a href="{{ route('admin.tools.github-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.github-checker*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fab fa-github text-body"></i></div>
                            <span class="menu-label-text">{{ __('GitHub Live Checker') }}</span>
                        </a>
                        @endif
                        @if(is_array(Auth::user()->allowed_tools) && in_array('gmail_checker', Auth::user()->allowed_tools))
                        <a href="{{ route('admin.tools.gmail-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.gmail-checker*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-envelope text-danger"></i></div>
                            <span class="menu-label-text">{{ __('Gmail Live Checker') }}</span>
                        </a>
                        @endif
                        @if($canAccess2fa)
                        <a href="{{ route('tools.2fa-generator') }}" class="menu-item {{ request()->routeIs('tools.2fa-generator*') ? 'active' : '' }}">
                            <div class="menu-icon-box"><i class="fas fa-key text-success"></i></div>
                            <span class="menu-label-text">{{ __('Generator Kode 2FA') }}</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Hidden tags for support card backward compatibility tests --}}
        @if(config('telegram.bot_username'))
        <div class="sidebar-help-card d-none">
            <span>{{ __('Pusat Bantuan') }}</span>
            <a href="https://t.me/{{ config('telegram.bot_username') }}"></a>
        </div>
        @endif

        {{-- Sidebar Footer (Figma Navigasi.svg Spec: Telegram Help Card & Language) --}}
        <div class="m3-sidebar-footer mt-auto pt-3 border-top" style="border-color: var(--outline-variant) !important;">
            <div class="m3-help-card">
                <div class="m3-help-title">
                    <i class="far fa-question-circle text-primary"></i> {{ __('Butuh bantuan?') }}
                </div>
                <div class="m3-help-sub">
                    {{ __('Hubungi admin via Telegram.') }}
                </div>
                <a href="https://t.me/{{ config('telegram.bot_username', 'dzulfikrialifajri_bot') }}" target="_blank" rel="noopener noreferrer" class="m3-help-link">
                    {{ __('Chat Telegram') }} <i class="fas fa-arrow-up-right-from-square" style="font-size: 0.72rem;"></i>
                </a>
            </div>

            @php
                $currentLocale = App::getLocale();
            @endphp
            <div class="d-flex align-items-center justify-content-between px-2 pt-2">
                <div class="m3-lang-footer">
                    <span>{{ $currentLocale === 'id' ? 'Bahasa Indonesia' : 'English' }}</span>
                </div>
                <a href="{{ route('lang.switch', $currentLocale === 'id' ? 'en' : 'id') }}" 
                   class="btn btn-xs btn-outline-secondary rounded-pill py-0.5 px-2 text-decoration-none d-flex align-items-center gap-1"
                   style="font-size: 0.72rem;"
                   title="{{ $currentLocale === 'id' ? __('Switch to English') : __('Ubah ke Bahasa Indonesia') }}">
                    <i class="fas fa-globe"></i> <span>{{ $currentLocale === 'id' ? 'EN' : 'ID' }}</span>
                </a>
            </div>
        </div>
    </div>
    @endunless

    {{-- Main Content --}}
    <main class="main-content position-relative" @if($hideSidebar) style="margin-left: 0 !important;" @endif>
        <div class="main-background"></div>

        <div class="container-fluid position-relative px-4 pb-4 pt-4">
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

    @unless($hideSidebar)
    {{-- Sidebar overlay (mobile) --}}
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    @endunless

</div>

{{-- Mobile Bottom Stack: Sticky Action Bar + Bottom Navigation with ZERO gap --}}
<div class="mobile-bottom-wrapper d-lg-none" id="mobileBottomWrapper">
    @yield('mobile_bottom_action_bar')

    @unless($hideSidebar || $hideBottomNav)
    {{-- Mobile Bottom Navigation Bar (Mobile Only) --}}
    <nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="{{ __('Navigasi Bawah') }}">
    @if(Auth::check())
        @php
            $currentRole = Auth::user()->role;
        @endphp

        @if($currentRole === 'admin')
            {{-- Admin Bottom Nav --}}
            <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="{{ __('Home') }}">
                <i class="fas fa-home"></i>
                <span>{{ __('Home') }}</span>
            </a>
            <a href="{{ route('admin.products.index') }}" class="mobile-nav-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" title="{{ __('Katalog') }}">
                <i class="fas fa-box"></i>
                <span>{{ __('Katalog') }}</span>
            </a>
            <a href="{{ route('admin.orders.index') }}" class="mobile-nav-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" title="{{ __('Pesanan') }}">
                <i class="fas fa-shopping-cart"></i>
                <span>{{ __('Pesanan') }}</span>
                @if($adminOrdersCount > 0)
                    <span class="badge bg-danger mobile-nav-badge">{{ $adminOrdersCount > 99 ? '99+' : $adminOrdersCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.complaints.index') }}" class="mobile-nav-item {{ request()->routeIs('admin.complaints.*') ? 'active' : '' }}" title="{{ __('Komplain') }}">
                <i class="fas fa-toolbox"></i>
                <span>{{ __('Komplain') }}</span>
                @if($adminComplaintsCount > 0)
                    <span class="badge bg-danger mobile-nav-badge">{{ $adminComplaintsCount > 99 ? '99+' : $adminComplaintsCount }}</span>
                @endif
            </a>
            <button type="button" class="mobile-nav-item" id="bottomNavMenuToggle" aria-label="{{ __('Menu Lainnya') }}">
                <i class="fas fa-bars"></i>
                <span>{{ __('Lainnya') }}</span>
            </button>

        @elseif($currentRole === 'seller')
            {{-- Seller Bottom Nav --}}
            <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="{{ __('Home') }}">
                <i class="fas fa-home"></i>
                <span>{{ __('Home') }}</span>
            </a>
            <a href="{{ route('seller.products.index') }}" class="mobile-nav-item {{ request()->routeIs('seller.products.*') ? 'active' : '' }}" title="{{ __('Produk') }}">
                <i class="fas fa-box-open"></i>
                <span>{{ __('Produk') }}</span>
            </a>
            <a href="{{ route('seller.orders.index') }}" class="mobile-nav-item {{ request()->routeIs('seller.orders.*') ? 'active' : '' }}" title="{{ __('Pesanan') }}">
                <i class="fas fa-receipt"></i>
                <span>{{ __('Pesanan') }}</span>
                @if($sellerOrdersCount > 0)
                    <span class="badge bg-danger mobile-nav-badge">{{ $sellerOrdersCount > 99 ? '99+' : $sellerOrdersCount }}</span>
                @endif
            </a>
            <a href="{{ route('seller.finance.index') }}" class="mobile-nav-item {{ request()->routeIs('seller.finance.*') ? 'active' : '' }}" title="{{ __('Dompet') }}">
                <i class="fas fa-wallet"></i>
                <span>{{ __('Dompet') }}</span>
            </a>
            <button type="button" class="mobile-nav-item" id="bottomNavMenuToggle" aria-label="{{ __('Menu Lainnya') }}">
                <i class="fas fa-bars"></i>
                <span>{{ __('Lainnya') }}</span>
            </button>

        @else
            {{-- Customer Bottom Nav --}}
            <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="{{ __('Home') }}">
                <i class="fas fa-home"></i>
                <span>{{ __('Home') }}</span>
            </a>
            <a href="{{ route('catalog.index') }}" class="mobile-nav-item {{ request()->routeIs('catalog.*') ? 'active' : '' }}" title="{{ __('Katalog') }}">
                <i class="fas fa-shopping-bag"></i>
                <span>{{ __('Katalog') }}</span>
            </a>
            <a href="{{ route('orders.index') }}" class="mobile-nav-item {{ request()->routeIs('orders.*') ? 'active' : '' }}" title="{{ __('Pesanan') }}">
                <i class="fas fa-receipt"></i>
                <span>{{ __('Pesanan') }}</span>
            </a>
            <a href="{{ route('chat.index') }}" class="mobile-nav-item {{ request()->routeIs('chat.*') ? 'active' : '' }}" title="{{ __('Chat') }}">
                <i class="fas fa-comments"></i>
                <span>{{ __('Chat') }}</span>
                @if($unreadChatsCount > 0)
                    <span class="badge bg-danger mobile-nav-badge">{{ $unreadChatsCount > 99 ? '99+' : $unreadChatsCount }}</span>
                @endif
            </a>
            <button type="button" class="mobile-nav-item" id="bottomNavMenuToggle" aria-label="{{ __('Menu Lainnya') }}">
                <i class="fas fa-bars"></i>
                <span>{{ __('Lainnya') }}</span>
            </button>
        @endif
    @else
        {{-- Guest Bottom Nav --}}
        <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="{{ __('Home') }}">
            <i class="fas fa-home"></i>
            <span>{{ __('Home') }}</span>
        </a>
        <a href="{{ route('catalog.index') }}" class="mobile-nav-item {{ request()->routeIs('catalog.*') ? 'active' : '' }}" title="{{ __('Katalog') }}">
            <i class="fas fa-shopping-bag"></i>
            <span>{{ __('Katalog') }}</span>
        </a>
        <button type="button" class="mobile-nav-item" onclick="openGuestModal('Keranjang Belanja')" title="{{ __('Keranjang') }}">
            <i class="fas fa-shopping-cart"></i>
            <span>{{ __('Keranjang') }}</span>
        </button>
        <button type="button" class="mobile-nav-item" onclick="openGuestModal('Riwayat Pesanan')" title="{{ __('Pesanan') }}">
            <i class="fas fa-receipt"></i>
            <span>{{ __('Pesanan') }}</span>
        </button>
        <a href="{{ route('login') }}" class="mobile-nav-item {{ request()->routeIs('login') ? 'active' : '' }}" title="{{ __('Masuk') }}">
            <i class="fas fa-sign-in-alt"></i>
            <span>{{ __('Masuk') }}</span>
        </a>
    @endif
</nav>
    @endunless
</div>

{{-- Floating Help Button moved into Sidebar --}}

{{-- Bootstrap JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

{{-- SweetAlert2 --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- Custom JS --}}
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
<script src="{{ asset('js/totp-engine.js') }}?v={{ filemtime(public_path('js/totp-engine.js')) }}"></script>

<script>
    window.copyFullCredential = function (btn, text) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            const span = btn.querySelector('span');
            const icon = btn.querySelector('i');
            const originalText = span ? span.textContent : '';
            if (span) span.textContent = '{{ __("Tersalin!") }}';
            if (icon) icon.className = 'fas fa-check text-success';
            setTimeout(() => {
                if (span) span.textContent = originalText;
                if (icon) icon.className = 'fas fa-copy';
            }, 1500);
        }).catch(() => {
            prompt('Salin data kredensial:', text);
        });
    };

    window.updateCartBadgeCount = function (qty) {
        const badge = document.getElementById('cart-badge-count');
        if (!badge) return;
        const count = parseInt(qty, 10) || 0;
        badge.textContent = count > 99 ? '99+' : count;
        if (count > 0) {
            badge.classList.remove('d-none');
            badge.style.display = 'inline-block';
            badge.classList.remove('badge-pop-anim');
            void badge.offsetWidth;
            badge.classList.add('badge-pop-anim');
        } else {
            badge.classList.add('d-none');
        }
    };

    // PWA Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function (err) {
                console.log('PWA ServiceWorker registration failed: ', err);
            });
        });
    }

    // Google Cloud Console Desktop Navigation Drawer Collapse & Global Search (Ctrl+/)
    document.addEventListener('DOMContentLoaded', function () {
        const appContainer = document.querySelector('.app-container');
        const collapseBtn = document.getElementById('cfcNavCollapseBtn');

        if (appContainer && localStorage.getItem('cfc_sidebar_collapsed') === '1') {
            appContainer.classList.add('sidebar-collapsed');
        }

        if (collapseBtn && appContainer) {
            collapseBtn.addEventListener('click', function () {
                appContainer.classList.toggle('sidebar-collapsed');
                const isCollapsed = appContainer.classList.contains('sidebar-collapsed');
                localStorage.setItem('cfc_sidebar_collapsed', isCollapsed ? '1' : '0');
            });
        }
    });
</script>

@stack('scripts')

{{-- Global SweetAlert2 Session Flash --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if(session('swal_error'))
            Swal.fire({
                icon: 'error',
                title: '{{ __('Gagal') }}',
                text: "{{ session('swal_error') }}",
                confirmButtonText: '{{ __('Tutup') }}',
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
                title: '{{ __('Berhasil') }}',
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
                    title: '{{ __('Berhasil') }}',
                    text: '{{ __('Semua notifikasi telah ditandai dibaca.') }}',
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

@auth
    @if(
        Auth::user()->telegram_id &&
        !Auth::user()->dismiss_set_password_prompt &&
        !Auth::user()->has_custom_password &&
        (session('show_telegram_welcome_modal') || session('logged_in_via_telegram'))
    )
        @php
            session()->forget('logged_in_via_telegram');
        @endphp
        <!-- Telegram Welcome Password Prompt Modal -->
        <div class="modal fade" id="telegramWelcomePasswordModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="telegramWelcomePasswordModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header border-0 text-white p-4" style="background: linear-gradient(135deg, #0d47a1 0%, #1565c0 50%, #1e88e5 100%);">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-white bg-opacity-20 p-3 d-flex align-items-center justify-content-center text-warning" style="width: 48px; height: 48px; font-size: 1.5rem;">
                                <i class="fas fa-key"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0 text-white" id="telegramWelcomePasswordModalLabel">
                                    {{ __('Selamat Datang!') }} 👋
                                </h5>
                                <p class="small text-white-50 mb-0">{{ __('Atur kata sandi untuk kemudahan akses akun Anda') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-body mb-3">
                            {{ __('Anda berhasil masuk menggunakan akun Telegram') }} <strong>({{ Auth::user()->full_name ?? Auth::user()->username }})</strong>.
                        </p>
                        <div class="alert alert-info-subtle border-0 rounded-3 p-3 mb-3" style="font-size: 0.9rem;">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            {{ __('Agar Anda dapat masuk secara langsung menggunakan Username & Kata Sandi tanpa harus via Telegram di lain waktu, silakan atur kata sandi akun Anda.') }}
                        </div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="chkDismissPasswordPrompt">
                            <label class="form-check-label small text-muted cursor-pointer" for="chkDismissPasswordPrompt">
                                {{ __('Jangan tampilkan peringatan ini lagi') }}
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-3 d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" id="btnClosePasswordPrompt">
                            {{ __('Nanti Saja') }}
                        </button>
                        <a href="{{ route('profile') }}?tab=password#tab-keamanan" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnGoToSetPassword">
                            <i class="fas fa-key me-1"></i> {{ __('Atur Kata Sandi Sekarang') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modalElem = document.getElementById('telegramWelcomePasswordModal');
                if (modalElem) {
                    const bsModal = new bootstrap.Modal(modalElem);
                    bsModal.show();

                    const chkDismiss = document.getElementById('chkDismissPasswordPrompt');
                    const btnClose = document.getElementById('btnClosePasswordPrompt');
                    const btnGo = document.getElementById('btnGoToSetPassword');

                    function handleDismissIfChecked() {
                        if (chkDismiss && chkDismiss.checked) {
                            fetch('{{ route("profile.password.dismiss-prompt") }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                }
                            });
                        }
                    }

                    if (btnClose) {
                        btnClose.addEventListener('click', function() {
                            handleDismissIfChecked();
                            bsModal.hide();
                        });
                    }

                    if (btnGo) {
                        btnGo.addEventListener('click', function() {
                            handleDismissIfChecked();
                        });
                    }
                }
            });
        </script>
    @endif

    @if(Auth::user()->role === 'seller' && empty(Auth::user()->avatar))
        <!-- Force Avatar Modal untuk Seller -->
        <div class="modal fade" id="forceAvatarModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="forceAvatarModalLabel" aria-hidden="true" style="z-index: 1070;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header border-0 text-white p-4" style="background: linear-gradient(135deg, #0d47a1 0%, #1565c0 50%, #1e88e5 100%);">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-white bg-opacity-20 p-3 d-flex align-items-center justify-content-center text-warning" style="width: 48px; height: 48px; font-size: 1.5rem;">
                                <i class="fas fa-camera"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0 text-white" id="forceAvatarModalLabel">
                                    {{ __('Atur Foto Profil Toko') }}
                                </h5>
                                <p class="small text-white-50 mb-0">{{ __('Tingkatkan kepercayaan pelanggan Anda') }}</p>
                            </div>
                        </div>
                    </div>
                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="full_name" value="{{ Auth::user()->full_name ?? Auth::user()->username }}">
                        <input type="hidden" name="email" value="{{ Auth::user()->email }}">
                        <input type="hidden" name="telegram_id" value="{{ Auth::user()->telegram_id }}">
                        
                        <div class="modal-body p-4 text-center">
                            <p class="text-body mb-3">
                                {{ __('Sebagai penjual mitra, Anda diwajibkan untuk mengunggah foto profil toko sebelum dapat melanjutkan aktivitas. Hal ini bertujuan untuk meningkatkan kepercayaan pelanggan dan keamanan platform.') }}
                            </p>
                            <div class="mb-3 text-start">
                                <label class="form-label fw-semibold small text-muted">{{ __('Unggah Foto Profil Baru') }} <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" required>
                                <div class="form-text" style="font-size: 0.75rem;">Maksimal 2MB. Format: JPG, PNG, WEBP, GIF.</div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 bg-light p-3">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm w-100">
                                <i class="fas fa-upload me-1"></i> {{ __('Simpan Foto Profil') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modalElem = document.getElementById('forceAvatarModal');
                if (modalElem) {
                    const bsModal = new bootstrap.Modal(modalElem);
                    bsModal.show();
                }
            });
        </script>
    @endif
@endauth

{{-- Guest Authentication Modal --}}
<div class="modal fade" id="guestAuthModal" tabindex="-1" aria-labelledby="guestAuthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-2 overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-4 px-4 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center px-4 pt-2 pb-4">
                <div class="guest-modal-icon-wrapper mb-3 mx-auto d-flex align-items-center justify-content-center">
                    <div class="avatar-circle-lg bg-primary-subtle text-primary shadow-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 72px; height: 72px; font-size: 2rem;">
                        <i class="fas fa-user-lock"></i>
                    </div>
                </div>
                <h4 class="modal-title fw-bold text-body mb-2" id="guestAuthModalLabel">{{ __('Masuk untuk Melanjutkan') }}</h4>
                <p class="text-muted small mb-4 px-2" id="guestAuthModalDesc">
                    {{ __('Fitur ini memerlukan akun. Nikmati riwayat transaksi yang tersimpan rapi, notifikasi pesanan real-time, serta kemudahan klaim garansi.') }}
                </p>

                <div class="bg-body-tertiary rounded-2 p-3 mb-4 text-start border border-dashed">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <span class="small fw-semibold text-secondary">{{ __('Riwayat belanja & serial key tersimpan aman') }}</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <span class="small fw-semibold text-secondary">{{ __('Notifikasi otomatis update pesanan via Telegram & Web') }}</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        <span class="small fw-semibold text-secondary">{{ __('Layanan garansi & komplain 1-klik terintegrasi') }}</span>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="{{ route('login') }}" id="guestModalLoginBtn" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm">
                        <i class="fas fa-sign-in-alt me-2"></i>{{ __('Masuk Sekarang') }}
                    </a>
                    <a href="{{ route('register') }}" class="btn btn-outline-primary btn-lg rounded-pill fw-semibold">
                        <i class="fas fa-user-plus me-2"></i>{{ __('Daftar Akun Baru') }}
                    </a>
                    <button type="button" class="btn btn-link text-muted text-decoration-none btn-sm mt-1" data-bs-dismiss="modal">
                        {{ __('Nanti Saja') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.openGuestModal = function(featureName, targetUrl) {
        const modalEl = document.getElementById('guestAuthModal');
        if (!modalEl) return;
        const descEl = document.getElementById('guestAuthModalDesc');
        const loginBtn = document.getElementById('guestModalLoginBtn');
        
        if (featureName && descEl) {
            descEl.innerHTML = `Fitur <strong>${featureName}</strong> memerlukan akun. Silakan masuk atau daftar untuk menikmati kemudahan transaksi, serial key otomatis, dan garansi cepat.`;
        }
        if (loginBtn) {
            let redirectUrl = targetUrl || window.location.href;
            loginBtn.href = "{{ route('login') }}?redirect=" + encodeURIComponent(redirectUrl);
        }
        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    };

    window.switchSidebarRoleTab = function(mode) {
        const belanjaMenu = document.getElementById('sidebarMenuBelanja');
        const managementMenu = document.getElementById('sidebarMenuManagement');
        const tabBelanjaBtns = document.querySelectorAll('.tab-btn-belanja');
        const tabMgmtBtns = document.querySelectorAll('.tab-btn-management');

        if (mode === 'belanja') {
            if (belanjaMenu) belanjaMenu.classList.remove('d-none');
            if (managementMenu) managementMenu.classList.add('d-none');
            tabBelanjaBtns.forEach(b => b.classList.add('active'));
            tabMgmtBtns.forEach(b => b.classList.remove('active'));
            try { localStorage.setItem('sidebarRoleTab', 'belanja'); } catch(e) {}
        } else {
            if (belanjaMenu) belanjaMenu.classList.add('d-none');
            if (managementMenu) managementMenu.classList.remove('d-none');
            tabBelanjaBtns.forEach(b => b.classList.remove('active'));
            tabMgmtBtns.forEach(b => b.classList.add('active'));
            try { localStorage.setItem('sidebarRoleTab', 'management'); } catch(e) {}
        }
    };
</script>

@stack('modals')

<script>
    // Global fix for Bootstrap modals: ensure modal is never trapped inside parent stacking context (e.g. z-index, backdrop-filter, overflow)
    document.addEventListener('show.bs.modal', function(event) {
        if (event.target && event.target.parentElement !== document.body) {
            document.body.appendChild(event.target);
        }
    });
</script>

</body>
</html>
