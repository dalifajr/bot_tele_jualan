<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="text-scale" content="scale">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code', 'Error') — @yield('title', __('Terjadi Kesalahan')) | {{ config('app.name', 'Digital Store') }}</title>

    <script>
        (function () {
            try {
                var cookieMatch = document.cookie.match(/(?:^|; )jualan-theme=([^;]*)/);
                var savedTheme = localStorage.getItem('jualan-theme')
                    || (cookieMatch ? decodeURIComponent(cookieMatch[1]) : null)
                    || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

                document.documentElement.setAttribute('data-theme', savedTheme);
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
                document.documentElement.style.colorScheme = savedTheme;

                window.__applyErrorPalette = function (hexColor, isDark) {
                    if (!hexColor || !/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/.test(hexColor)) return;
                    var c = hexColor;
                    if (c.length === 4) c = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
                    var r = parseInt(c.slice(1, 3), 16), g = parseInt(c.slice(3, 5), 16), b = parseInt(c.slice(5, 7), 16);
                    var rN = r / 255, gN = g / 255, bN = b / 255;
                    var max = Math.max(rN, gN, bN), min = Math.min(rN, gN, bN), h = 0;
                    if (max !== min) {
                        var d = max - min;
                        switch (max) {
                            case rN: h = (gN - bN) / d + (gN < bN ? 6 : 0); break;
                            case gN: h = (bN - rN) / d + 2; break;
                            case bN: h = (rN - gN) / d + 4; break;
                        }
                        h = Math.round((h / 6) * 360);
                    }
                    var lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                    var root = document.documentElement;
                    root.style.setProperty('--primary', c);
                    root.style.setProperty('--on-primary', lum > 0.62 ? '#111318' : '#ffffff');
                    root.style.setProperty('--primary-rgb', r + ', ' + g + ', ' + b);
                    if (isDark) {
                        root.style.setProperty('--surface', 'hsl(' + h + ', 18%, 8.5%)');
                        root.style.setProperty('--surface-container-lowest', 'hsl(' + h + ', 18%, 11%)');
                        root.style.setProperty('--surface-container', 'hsl(' + h + ', 18%, 14.5%)');
                        root.style.setProperty('--surface-container-high', 'hsl(' + h + ', 18%, 18%)');
                        root.style.setProperty('--primary-container', 'hsl(' + h + ', 52%, 28%)');
                        root.style.setProperty('--on-primary-container', 'hsl(' + h + ', 85%, 92%)');
                        root.style.setProperty('--secondary-container', 'hsl(' + h + ', 26%, 22%)');
                        root.style.setProperty('--on-secondary-container', 'hsl(' + h + ', 35%, 90%)');
                        root.style.setProperty('--outline-variant', 'hsl(' + h + ', 12%, 27%)');
                    } else {
                        root.style.setProperty('--surface', 'hsl(' + h + ', 36%, 96.8%)');
                        root.style.setProperty('--surface-container-lowest', '#ffffff');
                        root.style.setProperty('--surface-container', 'hsl(' + h + ', 30%, 92.4%)');
                        root.style.setProperty('--surface-container-high', 'hsl(' + h + ', 28%, 88.8%)');
                        root.style.setProperty('--primary-container', 'hsl(' + h + ', 72%, 91%)');
                        root.style.setProperty('--on-primary-container', 'hsl(' + h + ', 82%, 20%)');
                        root.style.setProperty('--secondary-container', 'hsl(' + h + ', 44%, 90%)');
                        root.style.setProperty('--on-secondary-container', 'hsl(' + h + ', 52%, 17%)');
                        root.style.setProperty('--outline-variant', 'hsl(' + h + ', 20%, 82%)');
                    }
                };

                var userPalette = localStorage.getItem('user_primary_palette');
                if (userPalette) {
                    window.__applyErrorPalette(userPalette, savedTheme === 'dark');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <style>
        :root,
        [data-theme="light"] {
            --primary: #65558f;
            --on-primary: #ffffff;
            --primary-rgb: 101, 85, 143;
            --primary-container: #eaddff;
            --on-primary-container: #21005d;

            --secondary-container: #e8def8;
            --on-secondary-container: #1d192b;

            --tertiary-container: #c4eed0;
            --on-tertiary-container: #072711;

            --error: #ba1a1a;
            --on-error: #ffffff;
            --error-container: #ffdad6;
            --on-error-container: #410002;

            --warning: #9a5b00;
            --warning-container: #ffdf9e;
            --on-warning-container: #2f1500;

            --surface: #fef7ff;
            --surface-container-lowest: #ffffff;
            --surface-container: #ece6f0;
            --surface-container-high: #e6e0e9;
            --on-surface: #1d1b20;
            --on-surface-variant: #49454f;
            --outline-variant: #cac4d0;
        }

        [data-theme="dark"] {
            --primary: #d0bcff;
            --on-primary: #381e72;
            --primary-rgb: 208, 188, 255;
            --primary-container: #4f378b;
            --on-primary-container: #eaddff;

            --secondary-container: #4a4458;
            --on-secondary-container: #e8def8;

            --tertiary-container: #0f5223;
            --on-tertiary-container: #c4eed0;

            --error: #f2b8b5;
            --on-error: #601410;
            --error-container: #8c1d18;
            --on-error-container: #f9dedc;

            --warning: #fdd663;
            --warning-container: #573e00;
            --on-warning-container: #ffdf9e;

            --surface: #141218;
            --surface-container-lowest: #1d1b20;
            --surface-container: #24222a;
            --surface-container-high: #2e2c35;
            --on-surface: #e6e0e9;
            --on-surface-variant: #cac4d0;
            --outline-variant: #49454f;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100svh;
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.55;
            background-color: var(--surface);
            color: var(--on-surface);
            accent-color: var(--primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle Ambient Tonal Wash (Flat Material You — zero heavy blur) */
        body::before,
        body::after {
            content: '';
            position: fixed;
            width: 32rem;
            height: 32rem;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        body::before {
            top: -12rem;
            left: -12rem;
            background: radial-gradient(circle, rgba(var(--primary-rgb), 0.1) 0%, transparent 70%);
        }
        body::after {
            bottom: -12rem;
            right: -12rem;
            background: radial-gradient(circle, rgba(var(--primary-rgb), 0.08) 0%, transparent 70%);
        }

        /* Top Utility Controls */
        .m3-err-topbar {
            position: fixed;
            top: 1rem;
            left: 1rem;
            right: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10;
            pointer-events: none;
        }

        .m3-err-brand,
        .m3-err-theme-btn {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            text-decoration: none;
            background: var(--surface-container-lowest);
            color: var(--on-surface);
            border: 1px solid var(--outline-variant);
            border-radius: 9999px;
            transition: background-color 180ms cubic-bezier(0.2, 0, 0, 1),
                        border-color 180ms cubic-bezier(0.2, 0, 0, 1),
                        transform 120ms cubic-bezier(0.2, 0, 0, 1);
        }

        .m3-err-brand {
            padding: 0.45rem 0.95rem 0.45rem 0.6rem;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .m3-err-brand-dot {
            width: 1.65rem;
            height: 1.65rem;
            border-radius: 9999px;
            background: var(--primary-container);
            color: var(--on-primary-container);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.78rem;
        }

        .m3-err-theme-btn {
            width: 2.65rem;
            height: 2.65rem;
            justify-content: center;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .m3-err-brand:hover,
        .m3-err-theme-btn:hover {
            background: var(--surface-container);
            border-color: var(--primary);
        }

        .m3-err-theme-btn:active,
        .m3-btn:active {
            transform: scale(0.97);
        }

        /* Main Flat M3 Error Card (28px Squircle) */
        .m3-err-card {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 34rem;
            background: var(--surface-container-lowest);
            border: 1px solid var(--outline-variant);
            border-radius: 1.75rem;
            padding: clamp(1.5rem, 1rem + 2.5vw, 2.5rem);
            text-align: center;
            margin-top: 2.75rem;
            animation: m3ErrEnter 280ms cubic-bezier(0.05, 0.7, 0.1, 1) backwards;
        }

        @keyframes m3ErrEnter {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.985);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        /* Semantic Tonal Squircle Emblem (76x76px, 24px radius) */
        .m3-err-emblem {
            width: 4.75rem;
            height: 4.75rem;
            border-radius: 1.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.95rem;
            margin-bottom: 1.15rem;
            border: 1px solid transparent;
        }

        .m3-tone-error {
            background: var(--error-container);
            color: var(--on-error-container);
        }

        .m3-tone-primary {
            background: var(--primary-container);
            color: var(--on-primary-container);
        }

        .m3-tone-warning {
            background: var(--warning-container);
            color: var(--on-warning-container);
        }

        .m3-tone-secondary {
            background: var(--secondary-container);
            color: var(--on-secondary-container);
        }

        .m3-err-code-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.28rem 0.85rem;
            border-radius: 9999px;
            font-family: 'Roboto Mono', ui-monospace, monospace;
            font-size: 0.76rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 0.85rem;
        }

        .m3-err-title {
            margin: 0 0 0.65rem;
            font-size: clamp(1.35rem, 1.15rem + 1vw, 1.75rem);
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: -0.02em;
            color: var(--on-surface);
            text-wrap: balance;
        }

        .m3-err-desc {
            margin: 0 auto 1.35rem;
            font-size: 0.92rem;
            color: var(--on-surface-variant);
            max-width: 42ch;
            text-wrap: pretty;
        }

        /* Concentric Inner Tonal Detail Panel (14px radius) */
        .m3-err-detail {
            background: var(--surface-container);
            border: 1px solid var(--outline-variant);
            border-radius: 0.875rem;
            padding: 0.95rem 1.05rem;
            text-align: left;
            margin-bottom: 1.35rem;
            font-size: 0.84rem;
        }

        .m3-err-detail-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 0.35rem 0.75rem;
            padding: 0.35rem 0;
            border-bottom: 1px solid var(--outline-variant);
        }

        .m3-err-detail-row:first-child { padding-top: 0; }
        .m3-err-detail-row:last-child { padding-bottom: 0; border-bottom: none; }

        .m3-err-detail-label {
            color: var(--on-surface-variant);
            font-weight: 500;
        }

        .m3-err-detail-val {
            color: var(--on-surface);
            font-weight: 600;
            word-break: break-word;
            text-align: right;
        }

        .m3-mono {
            font-family: 'Roboto Mono', ui-monospace, monospace;
            font-size: 0.8rem;
        }

        /* Expressive M3 Pill Buttons */
        .m3-err-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            justify-content: center;
        }

        .m3-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 2.75rem;
            padding: 0.6rem 1.25rem;
            border-radius: 9999px;
            font-family: inherit;
            font-size: 0.86rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: background-color 180ms cubic-bezier(0.2, 0, 0, 1),
                        border-color 180ms cubic-bezier(0.2, 0, 0, 1),
                        color 180ms cubic-bezier(0.2, 0, 0, 1),
                        transform 120ms cubic-bezier(0.2, 0, 0, 1),
                        opacity 180ms ease;
        }

        .m3-btn:focus-visible,
        .m3-err-theme-btn:focus-visible,
        .m3-err-brand:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .m3-btn-primary {
            background: var(--primary);
            color: var(--on-primary);
        }

        .m3-btn-primary:hover {
            opacity: 0.92;
        }

        .m3-btn-tonal {
            background: var(--secondary-container);
            color: var(--on-secondary-container);
        }

        .m3-btn-tonal:hover {
            background: var(--primary-container);
            color: var(--on-primary-container);
        }

        .m3-btn-outlined {
            background: transparent;
            color: var(--on-surface);
            border-color: var(--outline-variant);
        }

        .m3-btn-outlined:hover {
            background: var(--surface-container);
            border-color: var(--primary);
        }

        .m3-btn:disabled {
            opacity: 0.58;
            cursor: not-allowed;
        }

        .m3-err-footer {
            margin-top: 1.4rem;
            padding-top: 1rem;
            border-top: 1px solid var(--outline-variant);
            font-size: 0.76rem;
            color: var(--on-surface-variant);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        @media (max-width: 480px) {
            .m3-err-actions {
                flex-direction: column;
            }
            .m3-btn {
                width: 100%;
            }
            .m3-err-footer {
                justify-content: center;
                text-align: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="m3-err-topbar">
        <a href="{{ url('/') }}" class="m3-err-brand" aria-label="{{ config('app.name', 'Digital Store') }}">
            <span class="m3-err-brand-dot"><i class="fas fa-store"></i></span>
            <span>{{ config('app.name', 'Digital Store') }}</span>
        </a>
        <button type="button" id="m3ErrThemeToggle" class="m3-err-theme-btn" title="{{ __('Ganti Tema') }}" aria-label="{{ __('Ganti Tema') }}">
            <i class="fas fa-moon" id="m3ErrThemeIcon"></i>
        </button>
    </header>

    <main class="m3-err-card" role="main">
        <div class="m3-err-emblem @yield('tone_class', 'm3-tone-primary')" aria-hidden="true">
            <i class="@yield('icon', 'fas fa-circle-exclamation')"></i>
        </div>

        <div>
            <span class="m3-err-code-pill @yield('tone_class', 'm3-tone-primary')">
                <span>HTTP @yield('code', 'Error')</span>
                @hasSection('badge_label')
                    <span>&bull;</span>
                    <span>@yield('badge_label')</span>
                @endif
            </span>
        </div>

        <h1 class="m3-err-title">@yield('heading', __('Terjadi Kesalahan'))</h1>
        <p class="m3-err-desc">@yield('message')</p>

        @yield('detail_panel')

        <div class="m3-err-actions">
            @yield('actions')
        </div>

        <footer class="m3-err-footer">
            <span>{{ config('app.name', 'Digital Store') }}</span>
            <span class="m3-mono">{{ \App\Support\SystemTimezone::now()->format('Y-m-d H:i:s') }} {{ \App\Support\SystemTimezone::abbr() }}</span>
        </footer>
    </main>

    <script>
        (function () {
            var btn = document.getElementById('m3ErrThemeToggle');
            var icon = document.getElementById('m3ErrThemeIcon');
            var html = document.documentElement;

            function syncIcon() {
                if (!icon) return;
                var dark = html.getAttribute('data-theme') === 'dark';
                icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
            }

            syncIcon();

            if (btn) {
                btn.addEventListener('click', function () {
                    var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    html.setAttribute('data-theme', next);
                    html.setAttribute('data-bs-theme', next);
                    html.style.colorScheme = next;
                    try {
                        localStorage.setItem('jualan-theme', next);
                        document.cookie = 'jualan-theme=' + next + ';path=/;max-age=31536000;SameSite=Lax';
                        var p = localStorage.getItem('user_primary_palette');
                        if (p && typeof window.__applyErrorPalette === 'function') {
                            window.__applyErrorPalette(p, next === 'dark');
                        }
                    } catch (e) {}
                    syncIcon();
                });
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
