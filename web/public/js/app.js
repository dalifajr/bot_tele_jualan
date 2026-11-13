/**
 * Jualan Web — Application JavaScript
 * ====================================
 * Handles: theme toggle, sidebar, page loader, CSRF, WebApp login
 */

function initAll() {
    initThemeToggle();
    initSidebar();
    initPageLoader();
    initCsrfToken();
    initTelegramWebApp();
    initSweetAlertConfirms();
    initStockDetailNavigator();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
} else {
    initAll();
}

/* === THEME TOGGLE (Dark/Light) === */
function initThemeToggle() {
    const toggle = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    const html = document.documentElement;

    if (!toggle || !icon) return;

    // Load saved theme
    const saved = localStorage.getItem('jualan-theme') || (document.cookie.match(/(?:^|; )jualan-theme=([^;]*)/) || [])[1] || 'light';
    html.setAttribute('data-bs-theme', saved);
    html.style.colorScheme = saved;
    updateThemeIcon(icon, saved);

    toggle.addEventListener('click', () => {
        const current = html.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';

        html.classList.add('theme-transitioning');
        html.setAttribute('data-bs-theme', next);
        html.style.colorScheme = next;
        localStorage.setItem('jualan-theme', next);
        document.cookie = "jualan-theme=" + next + ";path=/;max-age=31536000;SameSite=Lax";
        
        const themeColorMeta = document.getElementById('themeColorMeta');
        if (themeColorMeta) {
            themeColorMeta.setAttribute('content', next === 'dark' ? '#1f1f1f' : '#3367d6');
        }

        updateThemeIcon(icon, next);
        
        const userPalette = localStorage.getItem('user_primary_palette');
        if (userPalette && typeof window.applyMaterialYouTheme === 'function') {
            window.applyMaterialYouTheme(userPalette, next === 'dark');
        }

        setTimeout(() => html.classList.remove('theme-transitioning'), 300);
    });
}

function updateThemeIcon(icon, theme) {
    icon.className = theme === 'dark' ? 'fas fa-sun fs-5' : 'fas fa-moon fs-5';
}

/* === SIDEBAR & BOTTOM SHEET (Mobile Bottom Sheet + Interactive Realtime Drag Up/Down) === */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const bottomNavToggle = document.getElementById('bottomNavMenuToggle');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const handleArea = document.getElementById('sheetHandleArea');
    const headerMobile = document.querySelector('.sheet-header-mobile');
    const overlay = document.getElementById('sidebarOverlay');
    const container = document.querySelector('.app-container');

    if (!sidebar) return;

    // States: 'closed' | 'normal' | 'expanded'
    let sheetState = 'closed';

    const getNormalHeight = () => Math.round(window.innerHeight * 0.74);
    const getExpandedHeight = () => Math.round(window.innerHeight * 0.94);

    const applySheetState = (state, animate = true) => {
        sheetState = state;
        sidebar.classList.remove('is-dragging');

        if (animate) {
            sidebar.style.transition = 'transform 0.35s cubic-bezier(0.2, 0, 0, 1), height 0.35s cubic-bezier(0.2, 0, 0, 1)';
            if (overlay) overlay.style.transition = 'opacity 0.3s cubic-bezier(0.2, 0, 0, 1)';
        } else {
            sidebar.style.transition = 'none';
            if (overlay) overlay.style.transition = 'none';
        }

        if (state === 'closed') {
            sidebar.style.transform = 'translateY(105%)';
            sidebar.classList.remove('show');
            if (overlay) {
                overlay.style.opacity = '0';
                setTimeout(() => {
                    if (sheetState === 'closed') {
                        overlay.classList.remove('show');
                        overlay.style.opacity = '';
                    }
                }, 300);
            }
            document.body.classList.remove('sidebar-open');
            if (bottomNavToggle) bottomNavToggle.classList.remove('active');
        } else if (state === 'normal') {
            const h = getNormalHeight();
            sidebar.style.height = h + 'px';
            sidebar.style.maxHeight = h + 'px';
            sidebar.style.transform = 'translateY(0)';
            sidebar.classList.add('show');
            if (overlay) {
                overlay.classList.add('show');
                overlay.style.opacity = '1';
            }
            document.body.classList.add('sidebar-open');
            if (bottomNavToggle) bottomNavToggle.classList.add('active');
        } else if (state === 'expanded') {
            const h = getExpandedHeight();
            sidebar.style.height = h + 'px';
            sidebar.style.maxHeight = h + 'px';
            sidebar.style.transform = 'translateY(0)';
            sidebar.classList.add('show');
            if (overlay) {
                overlay.classList.add('show');
                overlay.style.opacity = '1';
            }
            document.body.classList.add('sidebar-open');
            if (bottomNavToggle) bottomNavToggle.classList.add('active');
        }
    };

    const closeMobileSheet = () => applySheetState('closed', true);
    const openMobileSheet = () => applySheetState('normal', true);

    const toggleMobileSheet = () => {
        if (sidebar.classList.contains('show') && sheetState !== 'closed') {
            closeMobileSheet();
        } else {
            openMobileSheet();
        }
    };

    // Toggle triggers
    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.innerWidth >= 992) {
                if (container) {
                    container.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('jualan-sidebar-collapsed', container.classList.contains('sidebar-collapsed'));
                }
            } else {
                toggleMobileSheet();
            }
        });
    }

    if (bottomNavToggle) {
        bottomNavToggle.addEventListener('click', (e) => {
            e.preventDefault();
            toggleMobileSheet();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeMobileSheet();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileSheet);
    }

    // Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('show') && window.innerWidth < 992) {
            closeMobileSheet();
        }
    });

    // Close when clicking internal menu links on mobile
    const menuLinks = sidebar.querySelectorAll('a.menu-item');
    menuLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                closeMobileSheet();
            }
        });
    });

    // ==============================================================
    // === INTERACTIVE REALTIME DRAG GESTURE (SWIPE UP & DOWN) ===
    // ==============================================================
    let isDragging = false;
    let startY = 0;
    let startX = 0;
    let currentY = 0;
    let startTime = 0;
    let startHeight = 0;
    let isHorizontalGesture = false;

    const onDragStart = (clientY, clientX, target) => {
        if (window.innerWidth >= 992) return;
        if (!sidebar.classList.contains('show') || sheetState === 'closed') return;

        // Jangan drag jika menekan tombol close atau link di header
        if (target && (target.closest('#sidebarCloseBtn') || target.closest('a'))) {
            return;
        }

        isDragging = true;
        isHorizontalGesture = false;
        startY = clientY;
        startX = clientX;
        currentY = clientY;
        startTime = performance.now();
        startHeight = sidebar.offsetHeight || (sheetState === 'expanded' ? getExpandedHeight() : getNormalHeight());

        sidebar.classList.add('is-dragging');
        sidebar.style.transition = 'none';
        if (overlay) overlay.style.transition = 'none';
    };

    const onDragMove = (clientY, clientX, e) => {
        if (!isDragging || window.innerWidth >= 992) return;

        const deltaX = clientX - startX;
        const deltaY = clientY - startY;

        // Abaikan jika gesture horizontal (scroll)
        if (!isHorizontalGesture && Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 10) {
            isHorizontalGesture = true;
            return;
        }
        if (isHorizontalGesture) return;

        if (e && e.cancelable) {
            e.preventDefault();
        }

        currentY = clientY;
        const normalH = getNormalHeight();
        const expandedH = getExpandedHeight();

        if (sheetState === 'normal') {
            if (deltaY < 0) {
                // GESER NAIK (Expand secara realtime)
                const pullUp = -deltaY;
                const targetH = startHeight + pullUp;
                if (targetH <= expandedH) {
                    sidebar.style.height = targetH + 'px';
                    sidebar.style.maxHeight = targetH + 'px';
                    sidebar.style.transform = 'translateY(0)';
                } else {
                    // Rubber band overscroll ke atas
                    const overflow = targetH - expandedH;
                    const damped = overflow * 0.22;
                    sidebar.style.height = expandedH + 'px';
                    sidebar.style.maxHeight = expandedH + 'px';
                    sidebar.style.transform = `translateY(-${damped}px)`;
                }
                if (overlay) overlay.style.opacity = '1';
            } else {
                // GESER TURUN (Menuju ditutup secara realtime)
                sidebar.style.height = startHeight + 'px';
                sidebar.style.maxHeight = startHeight + 'px';
                sidebar.style.transform = `translateY(${deltaY}px)`;

                const progress = Math.max(0, 1 - (deltaY / (startHeight * 0.75)));
                if (overlay) overlay.style.opacity = progress.toString();
            }
        } else { // sheetState === 'expanded'
            if (deltaY < 0) {
                // Di posisi expanded, geser naik -> rubber band
                const damped = Math.abs(deltaY) * 0.22;
                sidebar.style.height = startHeight + 'px';
                sidebar.style.maxHeight = startHeight + 'px';
                sidebar.style.transform = `translateY(-${damped}px)`;
                if (overlay) overlay.style.opacity = '1';
            } else {
                // GESER TURUN dari Expanded
                const pullDown = deltaY;
                const targetH = startHeight - pullDown;
                if (targetH >= normalH) {
                    // Bergerak turun menuju normal secara realtime
                    sidebar.style.height = targetH + 'px';
                    sidebar.style.maxHeight = targetH + 'px';
                    sidebar.style.transform = 'translateY(0)';
                    if (overlay) overlay.style.opacity = '1';
                } else {
                    // Ditarik lebih jauh dari normal menuju penutupan
                    sidebar.style.height = normalH + 'px';
                    sidebar.style.maxHeight = normalH + 'px';
                    const offsetDown = pullDown - (startHeight - normalH);
                    sidebar.style.transform = `translateY(${offsetDown}px)`;
                    const progress = Math.max(0, 1 - (offsetDown / (normalH * 0.75)));
                    if (overlay) overlay.style.opacity = progress.toString();
                }
            }
        }
    };

    const onDragEnd = () => {
        if (!isDragging || window.innerWidth >= 992) return;
        isDragging = false;
        sidebar.classList.remove('is-dragging');

        const deltaY = currentY - startY;
        const elapsed = Math.max(1, performance.now() - startTime);
        const velocity = deltaY / elapsed; // px per ms (negatif: swipe up, positif: swipe down)
        const moveDist = Math.abs(deltaY);

        // Jika hanya tap tanpa pergeseran (< 6px) -> toggle normal / expanded
        if (moveDist < 6) {
            if (sheetState === 'normal') {
                applySheetState('expanded', true);
            } else if (sheetState === 'expanded') {
                applySheetState('normal', true);
            }
            return;
        }

        if (sheetState === 'normal') {
            if (velocity < -0.32 || deltaY < -55) {
                // Swipe cepat naik atau ditarik naik > 55px -> Expand
                applySheetState('expanded', true);
            } else if (velocity > 0.4 || deltaY > 90) {
                // Swipe cepat turun atau ditarik turun > 90px -> Tutup
                applySheetState('closed', true);
            } else {
                // Balik ke Normal
                applySheetState('normal', true);
            }
        } else { // sheetState === 'expanded'
            if (velocity > 0.75 || deltaY > (startHeight * 0.55)) {
                // Lemparan kuat ke bawah -> Tutup langsung
                applySheetState('closed', true);
            } else if (velocity > 0.3 || deltaY > 65) {
                // Tarikan turun terukur -> Snap ke Normal
                applySheetState('normal', true);
            } else {
                // Balik ke Expanded
                applySheetState('expanded', true);
            }
        }
    };

    // Attach Touch Listeners
    const targetAreas = [handleArea, headerMobile].filter(Boolean);
    targetAreas.forEach(area => {
        area.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                onDragStart(e.touches[0].clientY, e.touches[0].clientX, e.target);
            }
        }, { passive: true });

        // Mouse drag support (Simulator/DevTools/PC)
        area.addEventListener('mousedown', (e) => {
            if (e.button === 0) {
                onDragStart(e.clientY, e.clientX, e.target);
            }
        });
    });

    window.addEventListener('touchmove', (e) => {
        if (isDragging && e.touches.length === 1) {
            onDragMove(e.touches[0].clientY, e.touches[0].clientX, e);
        }
    }, { passive: false });

    window.addEventListener('touchend', onDragEnd, { passive: true });
    window.addEventListener('touchcancel', onDragEnd, { passive: true });

    window.addEventListener('mousemove', (e) => {
        if (isDragging) {
            onDragMove(e.clientY, e.clientX, e);
        }
    });

    window.addEventListener('mouseup', () => {
        if (isDragging) {
            onDragEnd();
        }
    });

    // Restore saved sidebar collapsed state on desktop
    if (window.innerWidth >= 992 && container) {
        const isCollapsed = localStorage.getItem('jualan-sidebar-collapsed') === 'true';
        if (isCollapsed) {
            container.classList.add('sidebar-collapsed');
        }
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

/* === SWEETALERT CONFIRM (M3 ALERT DIALOG SPECIFICATION) === */
function initSweetAlertConfirms() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-confirm');
            Swal.fire({
                title: 'Konfirmasi Aksi',
                text: message,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Konfirmasi',
                cancelButtonText: 'Batal',
                reverseButtons: false,
                focusConfirm: true,
                allowOutsideClick: true,
                allowEscapeKey: true,
                customClass: {
                    popup: 'm3-dialog-popup',
                    title: 'm3-dialog-title',
                    htmlContainer: 'm3-dialog-content',
                    actions: 'm3-dialog-actions',
                    confirmButton: 'm3-dialog-btn-confirm',
                    cancelButton: 'm3-dialog-btn-cancel'
                }
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

/* === DEVICE FINGERPRINT & SECURITY COOKIE === */
function initDeviceSecurity() {
    try {
        // 1. Persistent Security Device ID
        let deviceId = localStorage.getItem('_sec_device_id');
        if (!deviceId) {
            deviceId = 'dev_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
            localStorage.setItem('_sec_device_id', deviceId);
        }
        document.cookie = "_sec_device_id=" + encodeURIComponent(deviceId) + "; path=/; max-age=315360000; SameSite=Lax";

        // 2. Hardware / Canvas Fingerprint
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        let canvasHash = 'c0';
        if (ctx) {
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('JualanSaya_Security_Fingerprint', 2, 15);
            const dataUrl = canvas.toDataURL();
            let hash = 0;
            for (let i = 0; i < dataUrl.length; i++) {
                hash = ((hash << 5) - hash) + dataUrl.charCodeAt(i);
                hash |= 0;
            }
            canvasHash = 'c' + Math.abs(hash).toString(36);
        }

        const screenInfo = `${window.screen.width}x${window.screen.height}x${window.screen.colorDepth}`;
        const timezone = new Date().getTimezoneOffset();
        const hardware = navigator.hardwareConcurrency || 1;
        const rawFpString = `${navigator.userAgent}|${screenInfo}|${timezone}|${hardware}|${canvasHash}`;

        let fpHash = 2166136261;
        for (let i = 0; i < rawFpString.length; i++) {
            fpHash ^= rawFpString.charCodeAt(i);
            fpHash += (fpHash << 1) + (fpHash << 4) + (fpHash << 7) + (fpHash << 8) + (fpHash << 24);
        }
        const deviceFp = 'fp_' + Math.abs(fpHash).toString(36) + '_' + canvasHash;

        document.cookie = "_sec_device_fp=" + encodeURIComponent(deviceFp) + "; path=/; max-age=315360000; SameSite=Lax";

        // Auto populate form inputs
        document.querySelectorAll('form').forEach(form => {
            let fpInput = form.querySelector('input[name="device_fingerprint"]');
            if (!fpInput) {
                fpInput = document.createElement('input');
                fpInput.type = 'hidden';
                fpInput.name = 'device_fingerprint';
                form.appendChild(fpInput);
            }
            fpInput.value = deviceFp;

            let devIdInput = form.querySelector('input[name="device_id"]');
            if (!devIdInput) {
                devIdInput = document.createElement('input');
                devIdInput.type = 'hidden';
                devIdInput.name = 'device_id';
                form.appendChild(devIdInput);
            }
            devIdInput.value = deviceId;
        });
    } catch (e) {
        console.warn('Device security initialization failed:', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initDeviceSecurity();
});

/* === DETAIL DATA AKUN POPUP NAVIGATOR (Prev / Next Without Closing Popup) === */
window.stockDetailSources = [];
window.currentStockDetailIndex = 0;

window.initStockDetailNavigation = function () {
    window.stockDetailSources = [];
    const items = document.querySelectorAll('.stock-detail-source-item');
    items.forEach((item) => {
        window.stockDetailSources.push({
            id: item.dataset.id,
            product: item.dataset.product || '-',
            html: item.innerHTML
        });
    });
};

window.openStockDetailModal = function (index) {
    if (!window.stockDetailSources || window.stockDetailSources.length === 0) {
        window.initStockDetailNavigation();
    }
    window.renderStockDetail(index);
};

window.renderStockDetail = function (index) {
    if (!window.stockDetailSources || window.stockDetailSources.length === 0) {
        window.initStockDetailNavigation();
    }
    const sources = window.stockDetailSources;
    if (!sources || sources.length === 0) return;

    let targetIdx = parseInt(index, 10);
    if (isNaN(targetIdx) || targetIdx < 0) targetIdx = 0;
    if (targetIdx >= sources.length) targetIdx = sources.length - 1;

    window.currentStockDetailIndex = targetIdx;
    const item = sources[targetIdx];

    const prodEl = document.getElementById('stockDetailProductName');
    const containerEl = document.getElementById('stockDetailViewerContainer');
    const badgeEl = document.getElementById('stockDetailCounterBadge');
    const prevBtn = document.getElementById('stockDetailPrevBtn');
    const nextBtn = document.getElementById('stockDetailNextBtn');

    if (prodEl) {
        prodEl.textContent = item.product;
    }

    if (containerEl) {
        containerEl.style.opacity = '0.35';
        setTimeout(() => {
            containerEl.innerHTML = item.html;
            containerEl.style.opacity = '1';
            if (window.TotpEngine && typeof window.TotpEngine.updateAllTotpElements === 'function') {
                window.TotpEngine.updateAllTotpElements();
            }
        }, 40);
    }

    if (badgeEl) {
        badgeEl.textContent = (targetIdx + 1) + ' / ' + sources.length;
    }

    if (prevBtn) {
        prevBtn.disabled = (targetIdx === 0);
    }
    if (nextBtn) {
        nextBtn.disabled = (targetIdx === sources.length - 1);
    }
};

window.navigateStockDetail = function (step) {
    window.renderStockDetail((window.currentStockDetailIndex || 0) + step);
};

function initStockDetailNavigator() {
    window.initStockDetailNavigation();

    const detailModalEl = document.getElementById('detailStockModal');
    if (!detailModalEl) return;

    let keyHandler = null;

    detailModalEl.addEventListener('show.bs.modal', function () {
        if (!keyHandler) {
            keyHandler = function (e) {
                if (['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    window.navigateStockDetail(-1);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    window.navigateStockDetail(1);
                }
            };
            document.addEventListener('keydown', keyHandler);
        }
    });

    detailModalEl.addEventListener('hidden.bs.modal', function () {
        if (keyHandler) {
            document.removeEventListener('keydown', keyHandler);
            keyHandler = null;
        }
    });

    // Delegated click on any detail trigger
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-stock-detail-index], .btn-view-stock-detail');
        if (btn) {
            const index = parseInt(btn.getAttribute('data-stock-detail-index'), 10);
            if (!isNaN(index)) {
                window.openStockDetailModal(index);
            }
        }
    });
}