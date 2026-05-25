/**
 * Jualan Web — Application JavaScript
 * ====================================
 * Handles: theme toggle, sidebar, page loader, CSRF
 */

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initSidebar();
    initPageLoader();
    initCsrfToken();
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

/* === SIDEBAR (Mobile Toggle) === */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !toggleBtn) return;

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    });

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
}

/* === PAGE LOADER === */
function initPageLoader() {
    const loader = document.getElementById('pageLoader');
    if (!loader) return;

    // Show loader on navigation
    document.querySelectorAll('a[href]:not([target="_blank"]):not([href^="#"]):not([href^="javascript"])').forEach(link => {
        link.addEventListener('click', (e) => {
            if (e.ctrlKey || e.metaKey || e.shiftKey) return;
            loader.classList.add('show');
        });
    });

    // Show loader on form submit
    document.querySelectorAll('form:not(.no-loader)').forEach(form => {
        form.addEventListener('submit', () => {
            loader.classList.add('show');
        });
    });

    // Hide on page load (back/forward cache)
    window.addEventListener('pageshow', () => {
        loader.classList.remove('show');
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
