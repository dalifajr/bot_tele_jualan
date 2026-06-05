/**
 * Jualan Web — Application JavaScript
 * ====================================
 * Handles: theme toggle, sidebar, page loader, CSRF, WebApp login
 */

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initSidebar();
    initPageLoader();
    initCsrfToken();
    initTelegramWebApp();
    initSweetAlertConfirms();
});

/* === THEME TOGGLE (Dark/Light) === */
function initThemeToggle() {
    const toggle = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    const html = document.documentElement;

    if (!toggle || !icon) return;

    // Load saved theme
    const saved = localStorage.getItem('jualan-theme') || 'light';
    html.setAttribute('data-bs-theme', saved);
    updateThemeIcon(icon, saved);

    toggle.addEventListener('click', () => {
        const current = html.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', next);
        localStorage.setItem('jualan-theme', next);
        updateThemeIcon(icon, next);
    });
}

function updateThemeIcon(icon, theme) {
    icon.className = theme === 'dark' ? 'fas fa-sun fs-5' : 'fas fa-moon fs-5';
}

/* === SIDEBAR (Mobile & Desktop Toggle) === */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const container = document.querySelector('.app-container');

    if (!sidebar || !toggleBtn) return;

    toggleBtn.addEventListener('click', () => {
        if (window.innerWidth >= 768) {
            // Desktop Collapse
            if (container) {
                container.classList.toggle('sidebar-collapsed');
                localStorage.setItem('jualan-sidebar-collapsed', container.classList.contains('sidebar-collapsed'));
            }
        } else {
            // Mobile Drawer
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        }
    });

    // Restore saved sidebar collapsed state on desktop
    if (window.innerWidth >= 768 && container) {
        const isCollapsed = localStorage.getItem('jualan-sidebar-collapsed') === 'true';
        if (isCollapsed) {
            container.classList.add('sidebar-collapsed');
        }
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
}

/* === TOP PROGRESS BAR LOADER === */
let topBarEl = null;

function getTopLoadingBar() {
    if (!topBarEl) {
        topBarEl = document.createElement('div');
        topBarEl.className = 'top-loading-bar';
        document.body.appendChild(topBarEl);
    }
    return topBarEl;
}

function startTopLoadingBar() {
    const bar = getTopLoadingBar();
    bar.className = 'top-loading-bar start';
    setTimeout(() => {
        if (bar.classList.contains('start')) {
            bar.className = 'top-loading-bar middle';
        }
    }, 400);
}

function finishTopLoadingBar() {
    const bar = getTopLoadingBar();
    bar.className = 'top-loading-bar finish';
    setTimeout(() => {
        bar.className = 'top-loading-bar';
        bar.style.opacity = '0';
    }, 300);
}

/* === PAGE LOADER === */
function initPageLoader() {
    const pageLoader = document.getElementById('pageLoader');

    // Hide loader immediately on DOMContentLoaded (which is now)
    if (pageLoader) {
        pageLoader.classList.add('fade-out');
    }

    // Show loader on navigation
    document.querySelectorAll('a[href]:not([target="_blank"]):not([href^="#"]):not([href^="javascript"])').forEach(link => {
        link.addEventListener('click', (e) => {
            if (e.ctrlKey || e.metaKey || e.shiftKey) return;
            startTopLoadingBar();
            if (pageLoader) {
                pageLoader.classList.remove('fade-out');
            }
        });
    });

    // Show loader on form submit
    document.querySelectorAll('form:not(.no-loader)').forEach(form => {
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) return;
            startTopLoadingBar();
            if (pageLoader) {
                pageLoader.classList.remove('fade-out');
            }
        });
    });

    // Hide on page load (back/forward cache)
    window.addEventListener('pageshow', () => {
        finishTopLoadingBar();
        if (pageLoader) {
            pageLoader.classList.add('fade-out');
        }
    });
}

/* === CSRF Token for AJAX === */
function initCsrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    if (!token) return;

    // Auto-attach CSRF to fetch requests
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        if (typeof url === 'string' && !url.startsWith('http')) {
            options.headers = {
                ...options.headers,
                'X-CSRF-TOKEN': token.content,
            };
        }
        return originalFetch.call(this, url, options);
    };
}

/* === SWEETALERT CONFIRM === */
function initSweetAlertConfirms() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-confirm');
            Swal.fire({
                title: 'Konfirmasi',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal',
                customClass: {
                    popup: 'rounded-4',
                    confirmButton: 'btn btn-primary rounded-pill px-4',
                    cancelButton: 'btn btn-danger rounded-pill px-4 ms-2'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    const pageLoader = document.getElementById('pageLoader');
                    if (pageLoader) {
                        pageLoader.classList.remove('fade-out');
                    }
                    startTopLoadingBar();
                    if (this.tagName === 'FORM') {
                        this.submit();
                    } else if (this.closest('form')) {
                        this.closest('form').submit();
                    } else {
                        window.location.href = this.href;
                    }
                }
            });
        });
    });
}

/* === TELEGRAM WEBAPP AUTO-LOGIN === */
function initTelegramWebApp() {
    if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData) {
        const webapp = window.Telegram.WebApp;
        webapp.ready();
        webapp.expand();
        
        // If not authenticated, request login via AJAX
        if (!window.isAuthenticated) {
            const pageLoader = document.getElementById('pageLoader');
            if (pageLoader) {
                pageLoader.classList.remove('fade-out');
            }
            
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            const token = tokenMeta ? tokenMeta.content : '';

            fetch('/auth/telegram/webapp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ init_data: webapp.initData })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    console.error('Telegram WebApp login failed:', data.message);
                    if (pageLoader) pageLoader.classList.add('fade-out');
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Login',
                            text: data.message || 'Gagal masuk secara otomatis via Telegram.',
                            confirmButtonText: 'Tutup'
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Telegram WebApp login error:', error);
                if (pageLoader) pageLoader.classList.add('fade-out');
            });
        }
    }
}