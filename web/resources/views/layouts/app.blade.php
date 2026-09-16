<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="server-time" content="{{ time() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'Dzulfikrialifajri Store') }}</title>
    <meta name="description" content="@yield('meta_description', 'Platform jual beli produk digital terpercaya')">

    {{-- PWA Manifest & Mobile Meta Tags --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0d6efd">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Dzulfikrialifajri Store') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icons/icon-192x192.png') }}">

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
                pointer-events: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .mobile-bottom-wrapper > * {
                pointer-events: auto !important;
            }
            .mobile-bottom-wrapper .mobile-bottom-nav {
                position: relative !important;
                bottom: auto !important;
                left: auto !important;
                right: auto !important;
                width: 100% !important;
                margin: 0 !important;
            }
            .mobile-bottom-wrapper .mobile-sticky-action-bar,
            .mobile-sticky-action-bar {
                position: relative !important;
                bottom: auto !important;
                left: auto !important;
                right: auto !important;
                width: 100% !important;
                margin: 0 !important;
                background: var(--bs-body-bg) !important;
                border-top: 1px solid var(--bs-border-color) !important;
                border-bottom: 1px solid var(--bs-border-color-translucent) !important;
                box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08) !important;
                padding: 0.5rem 0.75rem !important;
                backdrop-filter: blur(16px) !important;
                -webkit-backdrop-filter: blur(16px) !important;
                z-index: 1046 !important;
            }

            @if(request()->routeIs('catalog.show'))
            body {
                padding-bottom: calc(130px + env(safe-area-inset-bottom)) !important;
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
<body class="bg-body-tertiary">

<div id="pageLoader" class="fade-out">
    <div class="skeleton-page-placeholder container-fluid px-3 px-md-4 pt-3 pt-md-4">
        @if(View::hasSection('page_skeleton'))
            @yield('page_skeleton')
        @elseif(request()->routeIs('catalog.show'))
            {{-- Contextual Skeleton: Detail Produk --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="skeleton-shimmer rounded-circle" style="width: 38px; height: 38px;"></div>
                    <div class="skeleton-shimmer" style="height: 22px; width: 130px; border-radius: 8px;"></div>
                </div>
                <div class="skeleton-shimmer rounded-circle" style="width: 38px; height: 38px;"></div>
            </div>
            <div class="row g-3 g-lg-4">
                <div class="col-lg-7 col-xl-8">
                    <div class="card border-0 shadow-sm overflow-hidden mb-3" style="border-radius: 20px;">
                        <div class="skeleton-shimmer w-100" style="height: 230px; border-radius: 0;"></div>
                        <div class="card-body p-3 p-md-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="skeleton-shimmer" style="height: 28px; width: 140px; border-radius: 8px;"></div>
                                <div class="skeleton-shimmer rounded-pill" style="height: 24px; width: 90px;"></div>
                            </div>
                            <div class="skeleton-shimmer mb-2" style="height: 22px; width: 85%; border-radius: 6px;"></div>
                            <div class="skeleton-shimmer mb-3" style="height: 14px; width: 45%; border-radius: 4px;"></div>
                            <div class="row g-2 mb-4">
                                <div class="col-4"><div class="skeleton-shimmer w-100 rounded-3" style="height: 58px;"></div></div>
                                <div class="col-4"><div class="skeleton-shimmer w-100 rounded-3" style="height: 58px;"></div></div>
                                <div class="col-4"><div class="skeleton-shimmer w-100 rounded-3" style="height: 58px;"></div></div>
                            </div>
                            <div class="p-3 rounded-4 border d-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="skeleton-shimmer rounded-circle" style="width: 46px; height: 46px;"></div>
                                    <div>
                                        <div class="skeleton-shimmer mb-1.5" style="height: 16px; width: 120px;"></div>
                                        <div class="skeleton-shimmer" style="height: 12px; width: 80px;"></div>
                                    </div>
                                </div>
                                <div class="skeleton-shimmer rounded-circle" style="width: 38px; height: 38px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 col-xl-4 d-none d-lg-block">
                    <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
                        <div class="skeleton-shimmer mb-3" style="height: 20px; width: 130px;"></div>
                        <div class="skeleton-shimmer mb-3" style="height: 48px; width: 100%; border-radius: 12px;"></div>
                        <div class="skeleton-shimmer mb-2" style="height: 48px; width: 100%; border-radius: 24px;"></div>
                        <div class="skeleton-shimmer" style="height: 48px; width: 100%; border-radius: 24px;"></div>
                    </div>
                </div>
            </div>
        @elseif(request()->routeIs('sellers.show'))
            {{-- Contextual Skeleton: Profil Toko Seller --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="skeleton-shimmer rounded-circle" style="width: 38px; height: 38px;"></div>
                    <div class="skeleton-shimmer" style="height: 22px; width: 120px; border-radius: 8px;"></div>
                </div>
                <div class="skeleton-shimmer rounded-circle" style="width: 38px; height: 38px;"></div>
            </div>
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="skeleton-shimmer rounded-circle" style="width: 64px; height: 64px;"></div>
                    <div>
                        <div class="skeleton-shimmer mb-2" style="height: 22px; width: 160px; border-radius: 6px;"></div>
                        <div class="skeleton-shimmer" style="height: 14px; width: 110px; border-radius: 4px;"></div>
                    </div>
                </div>
                <div class="row g-2 mt-2 pt-2 border-top">
                    <div class="col-4"><div class="skeleton-shimmer w-100 rounded-3" style="height: 48px;"></div></div>
                    <div class="col-4"><div class="skeleton-shimmer w-100 rounded-3" style="height: 48px;"></div></div>
                    <div class="col-4"><div class="skeleton-shimmer w-100 rounded-3" style="height: 48px;"></div></div>
                </div>
            </div>
            <div class="row g-3 g-md-4">
                @for($i = 0; $i < 4; $i++)
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 16px;">
                        <div class="skeleton-shimmer w-100 mb-2.5" style="height: 120px; border-radius: 12px;"></div>
                        <div class="skeleton-shimmer mb-2" style="height: 16px; width: 80%;"></div>
                        <div class="skeleton-shimmer" style="height: 14px; width: 45%;"></div>
                    </div>
                </div>
                @endfor
            </div>
        @elseif(request()->routeIs('cart.*'))
            {{-- Contextual Skeleton: Keranjang Belanja --}}
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="skeleton-shimmer rounded-circle" style="width: 38px; height: 38px;"></div>
                <div class="skeleton-shimmer" style="height: 22px; width: 150px; border-radius: 8px;"></div>
            </div>
            <div class="row g-3 g-lg-4">
                <div class="col-lg-8">
                    @for($c = 0; $c < 3; $c++)
                    <div class="card border-0 shadow-sm p-3 mb-2" style="border-radius: 16px;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="skeleton-shimmer rounded-3" style="width: 50px; height: 50px;"></div>
                                <div>
                                    <div class="skeleton-shimmer mb-1.5" style="height: 16px; width: 140px;"></div>
                                    <div class="skeleton-shimmer" style="height: 13px; width: 75px;"></div>
                                </div>
                            </div>
                            <div class="skeleton-shimmer rounded-pill" style="height: 32px; width: 90px;"></div>
                        </div>
                    </div>
                    @endfor
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
                        <div class="skeleton-shimmer mb-3" style="height: 20px; width: 130px;"></div>
                        <div class="skeleton-shimmer mb-2" style="height: 16px; width: 100%;"></div>
                        <div class="skeleton-shimmer mb-4" style="height: 16px; width: 70%;"></div>
                        <div class="skeleton-shimmer rounded-pill" style="height: 44px; width: 100%;"></div>
                    </div>
                </div>
            </div>
        @else
            {{-- Skeleton Universal Default (Catalog, Dashboard, etc.) --}}
            <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="skeleton-shimmer rounded-circle" style="width: 38px; height: 38px;"></div>
                    <div class="skeleton-shimmer" style="height: 22px; width: 140px; border-radius: 8px;"></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="skeleton-shimmer rounded-pill" style="height: 32px; width: 90px;"></div>
                    <div class="skeleton-shimmer rounded-circle" style="width: 32px; height: 32px;"></div>
                </div>
            </div>
            <div class="row g-3 g-md-4">
                @for($i = 0; $i < 4; $i++)
                <div class="col-6 col-md-4 col-lg-3 {{ $i >= 2 ? 'd-none d-md-block' : '' }}">
                    <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 16px;">
                        <div class="skeleton-shimmer w-100 mb-3" style="height: 125px; border-radius: 12px;"></div>
                        <div class="skeleton-shimmer mb-2" style="height: 16px; width: 80%;"></div>
                        <div class="skeleton-shimmer mb-3" style="height: 12px; width: 50%;"></div>
                        <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                            <div class="skeleton-shimmer" style="height: 18px; width: 65px;"></div>
                            <div class="skeleton-shimmer rounded-pill" style="height: 26px; width: 36px;"></div>
                        </div>
                    </div>
                </div>
                @endfor
            </div>
            <div class="mt-4">
                <div class="skeleton-shimmer mb-3" style="height: 18px; width: 160px; border-radius: 6px;"></div>
                @for($j = 0; $j < 3; $j++)
                <div class="card border-0 shadow-sm p-3 mb-2" style="border-radius: 14px;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2.5 flex-grow-1">
                            <div class="skeleton-shimmer rounded-3" style="width: 44px; height: 44px; flex-shrink: 0;"></div>
                            <div class="w-75">
                                <div class="skeleton-shimmer mb-1.5" style="height: 14px; width: 65%;"></div>
                                <div class="skeleton-shimmer" style="height: 11px; width: 40%;"></div>
                            </div>
                        </div>
                        <div class="skeleton-shimmer rounded-pill" style="height: 24px; width: 70px;"></div>
                    </div>
                </div>
                @endfor
            </div>
        @endif
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

<nav class="navbar navbar-expand fixed-top shadow-sm px-3 px-md-4 bg-body border-bottom" style="z-index: 1030;">
    <div class="d-flex align-items-center gap-2 gap-sm-3">
        <div class="navbar-brand d-flex align-items-center gap-2 text-primary fw-bold m-0" style="font-size: 1.05rem;">
            <i class="fas fa-shopping-bag fs-5"></i>
            <span class="fw-semibold">{{ config('app.name', 'Dzulfikrialifajri Store') }} <span class="fw-normal text-secondary d-none d-sm-inline" style="font-size: 0.85rem; opacity: 0.8;">| @yield('page_subtitle', 'Dashboard')</span></span>
        </div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-3">
        @unless($hideSidebar)
        {{-- Notification Bell (All Authenticated Users) --}}
        @php
            $unreadNotifications = Auth::check() ? Auth::user()->unreadNotifications()->take(5)->get() : collect();
            $totalUnreadCount = Auth::check() ? Auth::user()->unreadNotifications()->count() : 0;
        @endphp
        {{-- Notification Bell for Mobile (< 768px): Direct Link to Notifications Page (Poin 10) --}}
        <a href="{{ Auth::check() ? route('notifications.index') : route('login') }}" 
           @guest data-guest-modal="true" data-feature-name="notifikasi" @endguest 
           class="btn btn-link link-body-emphasis p-0 position-relative d-md-none" 
           title="{{ __('Notifikasi') }}">
            <i class="fas fa-bell fs-5"></i>
            @if($totalUnreadCount > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                {{ $totalUnreadCount > 99 ? '99+' : $totalUnreadCount }}
            </span>
            @endif
        </a>

        {{-- Notification Dropdown for Desktop (>= 768px): Popover --}}
        <div class="dropdown d-none d-md-block">
            <button class="btn btn-link link-body-emphasis p-0 position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Notifikasi') }}" data-pex="xrpax4o-0">
                <i class="fas fa-bell fs-5" data-pex="xrpax4o-1"></i>
                @if($totalUnreadCount > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;" data-pex="xrpax4o-2">
                    {{ $totalUnreadCount > 99 ? '99+' : $totalUnreadCount }}
                </span>
                @endif
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 p-0" style="width: 350px; border-radius: 12px; z-index: 1050; overflow: hidden;">
                <li class="bg-light px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary" data-pex="9c28hsh-7">{{ __('Notifikasi') }}</h6>
                    @if($totalUnreadCount > 0)
                    <span class="badge bg-primary rounded-pill">{{ $totalUnreadCount }} {{ __('Baru') }}</span>
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
                                <div class="fw-bold mb-1" style="font-size: 0.9rem; color: var(--bs-heading-color);" data-pex="9c28hsh-21">{{ $notification->data['title'] ?? __('Notifikasi') }}</div>
                                <div class="text-muted small" style="line-height: 1.4;" data-pex="9c28hsh-22">{{ $notification->data['message'] ?? '' }}</div>
                                
                                @if(($notification->data['type'] ?? '') === 'login_gagal' && isset($notification->data['ip_address']))
                                <div class="mt-2" onclick="event.stopPropagation(); event.preventDefault();">
                                    <form action="{{ route('profile.logins.block-ip') }}" method="POST" class="d-inline">
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
                            <small>{{ __('Tidak ada notifikasi baru.') }}</small>
                        </div>
                    </li>
                    @endforelse
                </div>
                
                <li class="d-flex justify-content-between px-3 py-2 bg-light border-top">
                    <a href="javascript:void(0)" class="text-decoration-none small text-muted hover-primary" onclick="markAllNotificationsRead()"><i class="fas fa-check-double me-1"></i>{{ __('Tandai Dibaca') }}</a>
                    <a href="{{ route('notifications.index') }}" class="text-decoration-none small fw-bold text-primary"><i class="fas fa-list me-1"></i>{{ __('Lihat Semua') }}</a>
                </li>
            </ul>
        </div>

        {{-- Shopping Cart Icon --}}
        <a href="{{ Auth::check() ? route('cart.index') : route('login') }}" 
           @guest data-guest-modal="true" data-feature-name="keranjang belanja" @endguest 
           class="btn btn-link link-body-emphasis p-0 position-relative me-2" 
           title="{{ __('Keranjang Belanja') }}" 
           id="headerCartBtn">
            <i class="fas fa-shopping-cart fs-5"></i>
            @php
                $cartCount = Auth::check() ? \App\Models\CartItem::where('user_id', Auth::id())->sum('quantity') : 0;
            @endphp
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger {{ $cartCount > 0 ? '' : 'd-none' }}" style="font-size: 0.65rem;" id="cart-badge-count">
                {{ $cartCount > 99 ? '99+' : $cartCount }}
            </span>
        </a>
        @endunless

        {{-- Theme Toggle --}}
        <button class="btn btn-link link-body-emphasis p-0 me-2" id="themeToggle" title="{{ __('Toggle Theme') }}" aria-label="{{ __('Ganti Tema') }}">
            <i class="fas fa-moon fs-5" id="themeIcon"></i>
        </button>

        {{-- Logout or Login Button (Desktop Only) --}}
        @auth
        <form action="{{ route('logout') }}" method="POST" class="m-0 d-none d-lg-block">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-2 rounded-pill px-3" aria-label="{{ __('Logout') }}">
                <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">{{ __('Logout') }}</span>
            </button>
        </form>
        @else
        <a href="{{ route('login') }}" class="btn btn-sm btn-primary rounded-pill px-3 d-none d-lg-flex align-items-center gap-1.5 shadow-sm fw-bold">
            <i class="fas fa-sign-in-alt"></i> <span>{{ __('Masuk') }}</span>
        </a>
        @endauth
    </div>
</nav>

<div class="app-container">
    @unless($hideSidebar)
    {{-- Sidebar (Desktop Column / Mobile Card Sheet) --}}
    <div id="sidebar" class="sidebar" style="z-index: 1040; overflow-y: auto; overscroll-behavior: contain;">
        {{-- Mobile Bottom Sheet Grab Handle & Header (Mobile Only) --}}
        <div class="sheet-header-mobile d-lg-none sticky-top bg-body pt-2 pb-1 border-bottom" style="border-top-left-radius: 24px; border-top-right-radius: 24px; z-index: 10;">
            <div class="sheet-handle-area" id="sheetHandleArea" title="{{ __('Geser ke bawah untuk menutup') }}">
                <div class="sheet-handle-bar"></div>
            </div>
            <div class="d-flex align-items-center justify-content-between px-3 pb-2 pt-1">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-bars-staggered text-primary"></i>
                    <span class="fw-bold text-body" style="font-size: 0.95rem;">{{ __('Menu & Layanan Lainnya') }}</span>
                </div>
                <button type="button" class="btn btn-sm btn-icon btn-light rounded-circle text-secondary" id="sidebarCloseBtn" aria-label="{{ __('Tutup') }}" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; padding: 0;">
                    <i class="fas fa-times" style="font-size: 0.9rem;"></i>
                </button>
            </div>
        </div>
        @auth
        <div class="sidebar-header d-flex align-items-center gap-3">
            <div class="user-avatar rounded-circle d-flex align-items-center justify-content-center fw-bold text-white bg-primary" style="width: 40px; height: 40px; flex-shrink: 0;">
                {{ strtoupper(substr(Auth::user()->full_name ?? Auth::user()->username ?? 'U', 0, 1)) }}
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
        @else
        <div class="sidebar-header d-flex align-items-center gap-3 bg-light rounded-4 p-3 m-3 border">
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

        <div class="py-3">
            <div class="menu-group">
                <div class="menu-items-grid">
                    <a href="{{ route('dashboard') }}" class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <div class="menu-icon-box"><i class="fas fa-home text-primary"></i></div>
                        <span class="menu-label-text">{{ __('Home') }}</span>
                    </a>
                </div>
            </div>

            <div class="menu-group">
                <div class="menu-header"><i class="fas fa-shopping-bag me-1 text-primary"></i> {{ __('Belanja') }}</div>
                <div class="menu-items-grid">
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
                </div>
            </div>

            <div class="menu-group">
                <div class="menu-header"><i class="fas fa-user-circle me-1 text-primary"></i> {{ __('Akun') }}</div>
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
            <div class="menu-group">
                <div class="menu-header text-success"><i class="fas fa-tools me-1"></i> {{ __('Tool') }}</div>
                <div class="menu-items-grid">
                    <a href="{{ route('tools.2fa-generator') }}" class="menu-item {{ request()->routeIs('tools.2fa-generator*') ? 'active' : '' }}">
                        <div class="menu-icon-box"><i class="fas fa-key text-success"></i></div>
                        <span class="menu-label-text">{{ __('Generator Kode 2FA') }}</span>
                    </a>
                </div>
            </div>
            @endif

            @if(Auth::check() && Auth::user()->role === 'admin')
            <div class="menu-group">
                <div class="menu-header text-primary"><i class="fas fa-shield-alt me-1"></i> {{ __('Admin Panel') }}</div>
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
                    <a href="{{ route('admin.logins.index') }}" class="menu-item {{ request()->routeIs('admin.logins.*') ? 'active' : '' }}">
                        <div class="menu-icon-box"><i class="fas fa-right-to-bracket text-secondary"></i></div>
                        <span class="menu-label-text">{{ __('Percobaan Login') }}</span>
                        @if($pendingLoginsCount > 0)
                            <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 0.7rem;">{{ $pendingLoginsCount }}</span>
                        @endif
                    </a>
                </div>
            </div>

            <div class="menu-group">
                <div class="menu-header text-success"><i class="fas fa-tools me-1"></i> {{ __('Tool') }}</div>
                <div class="menu-items-grid">
                    <a href="{{ route('admin.tools.github-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.github-checker*') ? 'active' : '' }}">
                        <div class="menu-icon-box"><i class="fab fa-github text-dark"></i></div>
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

            <div class="menu-group">
                <div class="menu-header text-danger"><i class="fas fa-cogs me-1"></i> {{ __('Sistem & Konfigurasi') }}</div>
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
            @endif

            @if(Auth::check() && Auth::user()->role === 'seller')
            <div class="menu-group">
                <div class="menu-header text-info"><i class="fas fa-store me-1"></i> {{ __('Seller Portal') }}</div>
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
                    <a href="{{ route('seller.settings.index') }}" class="menu-item {{ request()->routeIs('seller.settings.*') ? 'active' : '' }}">
                        <div class="menu-icon-box"><i class="fas fa-user-gear text-secondary"></i></div>
                        <span class="menu-label-text">{{ __('Pengaturan Karantina') }}</span>
                    </a>
                </div>
            </div>
            @php
                $sellerHasTools = Auth::check() && ((is_array(Auth::user()->allowed_tools) && count(Auth::user()->allowed_tools) > 0) || $canAccess2fa);
            @endphp
            @if($sellerHasTools)
            <div class="menu-group">
                <div class="menu-header text-success"><i class="fas fa-tools me-1"></i> {{ __('Tool') }}</div>
                <div class="menu-items-grid">
                    @if(is_array(Auth::user()->allowed_tools) && in_array('github_checker', Auth::user()->allowed_tools))
                    <a href="{{ route('admin.tools.github-checker') }}" class="menu-item {{ request()->routeIs('admin.tools.github-checker*') ? 'active' : '' }}">
                        <div class="menu-icon-box"><i class="fab fa-github text-dark"></i></div>
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
            @endif

            @if(config('telegram.bot_username'))
            <div class="menu-group px-3 pt-3 pb-1 border-top">
                <div class="sidebar-help-card p-3 rounded-3 text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                            <i class="fab fa-telegram-plane fs-6"></i>
                        </div>
                    </div>
                    <div class="fw-bold text-body mb-1" style="font-size: 0.82rem;">{{ __('Pusat Bantuan') }}</div>
                    <p class="text-muted small mb-2" style="font-size: 0.72rem; line-height: 1.35;">{{ __('Butuh bantuan atau pertanyaan? Hubungi admin via Telegram.') }}</p>
                    <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank"
                       class="btn btn-primary btn-sm w-100 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 py-1.5"
                       style="font-size: 0.78rem;">
                        <i class="fab fa-telegram"></i>
                        <span>{{ __('Chat Telegram') }}</span>
                    </a>
                </div>
            </div>
            @endif

            <div class="menu-group border-top pt-3 px-3">
                <div class="menu-header text-secondary"><i class="fas fa-language me-1"></i> {{ __('Bahasa / Language') }}</div>
                @php
                    $currentLocale = App::getLocale();
                @endphp
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <span class="small text-secondary">{{ __('Bahasa Aktif:') }} <strong class="text-uppercase">{{ $currentLocale }}</strong></span>
                    <a href="{{ route('lang.switch', $currentLocale === 'id' ? 'en' : 'id') }}" 
                       class="btn btn-xs btn-outline-secondary d-flex align-items-center gap-1 py-1 px-2 text-decoration-none"
                       style="font-size: 0.78rem;"
                       title="{{ $currentLocale === 'id' ? __('Switch to English') : __('Ubah ke Bahasa Indonesia') }}">
                        <i class="fas fa-globe text-secondary"></i>
                        <span>{{ $currentLocale === 'id' ? __('English') : __('Indonesia') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endunless

    {{-- Main Content --}}
    <main class="main-content position-relative" @if($hideSidebar) style="margin-left: 0 !important;" @endif>
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
@endauth

{{-- Guest Authentication Modal --}}
<div class="modal fade" id="guestAuthModal" tabindex="-1" aria-labelledby="guestAuthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-4 px-4 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center px-4 pt-2 pb-4">
                <div class="guest-modal-icon-wrapper mb-3 mx-auto d-flex align-items-center justify-content-center">
                    <div class="avatar-circle-lg bg-primary-subtle text-primary shadow-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 72px; height: 72px; font-size: 2rem;">
                        <i class="fas fa-user-lock"></i>
                    </div>
                </div>
                <h4 class="modal-title fw-bold text-dark mb-2" id="guestAuthModalLabel">{{ __('Masuk untuk Melanjutkan') }}</h4>
                <p class="text-muted small mb-4 px-2" id="guestAuthModalDesc">
                    {{ __('Fitur ini memerlukan akun. Nikmati riwayat transaksi yang tersimpan rapi, notifikasi pesanan real-time, serta kemudahan klaim garansi.') }}
                </p>

                <div class="bg-light rounded-3 p-3 mb-4 text-start border border-dashed">
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
</script>

@stack('modals')

</body>
</html>
