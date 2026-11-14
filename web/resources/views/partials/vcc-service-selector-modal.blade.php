{{-- Material 3 VCC Service Selector: Desktop Popup Alert Dialog & Mobile Mini Card Drawer --}}
<div id="vccSelectorBackdrop" class="vcc-selector-backdrop" style="display: none;" aria-hidden="true">
    <div id="vccSelectorContainer" class="vcc-selector-container">
        {{-- Mobile Drag Handle Pill --}}
        <div class="vcc-drawer-handle d-md-none">
            <span class="vcc-drawer-pill"></span>
        </div>

        {{-- Dialog Header --}}
        <div class="vcc-dialog-header text-center mb-3">
            <div class="vcc-icon-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-sm">
                <i class="fas fa-credit-card text-primary" style="font-size: 1.4rem;"></i>
            </div>
            <h5 class="fw-bold text-body mb-1" id="vccModalTitle">{{ __('Pilih Layanan VCC') }}</h5>
            <p class="text-secondary small mb-0" id="vccModalSubtitle">
                <span id="vccProductName">VCC Virtual Card</span> &bull; <span class="fw-semibold text-primary" id="vccProductPrice">Rp 0</span> / {{ __('layanan') }}
            </p>
        </div>

        {{-- Dialog Body --}}
        <div class="vcc-dialog-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-secondary fw-semibold">
                    <i class="fas fa-list-check text-primary me-1"></i>{{ __('Pilih Layanan (1 Layanan = 1 Stok):') }}
                </span>
                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 fw-semibold" id="btnVccSelectAll" style="font-size: 0.75rem;">
                    {{ __('Pilih Semua') }}
                </button>
            </div>

            {{-- List Layanan Container (Scrollable) --}}
            <div id="vccServiceListContainer" class="vcc-service-list custom-scrollbar mb-3">
                {{-- Dynamic service items inserted by JS --}}
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                    <p class="small mb-0">{{ __('Memuat daftar layanan VCC...') }}</p>
                </div>
            </div>

            {{-- Dynamic Price & Service Summary Box --}}
            <div class="vcc-summary-box p-3 rounded-4 mb-3">
                <div class="d-flex justify-content-between align-items-center small text-secondary mb-1">
                    <span>{{ __('Layanan Terpilih') }}</span>
                    <span class="fw-bold text-body" id="vccSelectedCountText">0 {{ __('layanan') }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center small text-secondary mb-2">
                    <span>{{ __('Harga Satuan') }}</span>
                    <span class="fw-semibold text-body" id="vccUnitPriceText">Rp 0</span>
                </div>
                <div class="border-top pt-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-body small">{{ __('Total Harga Bayar:') }}</span>
                    <span class="fw-bold text-primary fs-5" id="vccTotalAmountText">Rp 0</span>
                </div>
            </div>
        </div>

        {{-- Dialog Footer Actions --}}
        <div class="vcc-dialog-footer d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill py-2.5 px-3 fw-bold flex-grow-1" id="btnVccCancel">
                {{ __('Batal') }}
            </button>
            <button type="button" class="btn btn-primary rounded-pill py-2.5 px-3 fw-bold flex-grow-1 d-flex align-items-center justify-content-center gap-1.5 shadow-sm" id="btnVccProceed" disabled>
                <i class="fas fa-bolt"></i>
                <span id="btnVccProceedText">{{ __('Lanjut Checkout') }}</span>
            </button>
        </div>
    </div>
</div>

<style>
/* Backdrop Overlay */
.vcc-selector-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 1060;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.25s cubic-bezier(0.2, 0, 0, 1), visibility 0.25s cubic-bezier(0.2, 0, 0, 1);
}

.vcc-selector-backdrop.show {
    opacity: 1;
    visibility: visible;
}

/* Modal Box - Desktop (Material 3 Alert Dialog) */
@media (min-width: 768px) {
    .vcc-selector-container {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.92);
        width: 92%;
        max-width: 480px;
        background: var(--surface-container-high, #f8fafc);
        border: 1px solid var(--outline-variant, #e2e8f0);
        border-radius: 28px;
        padding: 28px 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05);
        opacity: 0;
        transition: transform 0.25s cubic-bezier(0.2, 0, 0, 1), opacity 0.25s cubic-bezier(0.2, 0, 0, 1);
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    .vcc-selector-backdrop.show .vcc-selector-container {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
    }
}

/* Mini Card Drawer - Mobile (Material 3 Bottom Sheet) */
@media (max-width: 767.98px) {
    .vcc-selector-container {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        background: var(--surface-container-high, #f8fafc);
        border-top: 1px solid var(--outline-variant, #e2e8f0);
        border-radius: 28px 28px 0 0;
        padding: 12px 20px 24px;
        box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.2);
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.2, 0, 0, 1);
        max-height: 85vh;
        display: flex;
        flex-direction: column;
    }

    .vcc-selector-backdrop.show .vcc-selector-container {
        transform: translateY(0);
    }
}

/* Drawer Handle Pill */
.vcc-drawer-handle {
    display: flex;
    justify-content: center;
    padding: 6px 0 10px;
}

.vcc-drawer-pill {
    display: block;
    width: 38px;
    height: 4.5px;
    background: var(--outline-variant, #cbd5e1);
    border-radius: 9999px;
}

/* Icon Circle */
.vcc-icon-circle {
    width: 52px;
    height: 52px;
    border-radius: 18px;
    background: var(--primary-container, #dbeafe);
}

/* Scrollable Service List */
.vcc-service-list {
    max-height: 240px;
    overflow-y: auto;
    padding-right: 4px;
}

/* Service Item Card */
.vcc-service-tile {
    background: var(--surface-container-lowest, #ffffff);
    border: 1.5px solid var(--outline-variant, #e2e8f0);
    border-radius: 16px;
    padding: 10px 14px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    user-select: none;
}

.vcc-service-tile:hover {
    border-color: var(--primary, #0284c7);
    background: rgba(var(--primary-rgb, 2, 132, 199), 0.03);
}

.vcc-service-tile.is-selected {
    border-color: var(--primary, #0284c7);
    background: rgba(var(--primary-rgb, 2, 132, 199), 0.08);
}

.vcc-service-tile.is-disabled {
    opacity: 0.55;
    background: var(--surface-container-low, #f1f5f9);
    cursor: not-allowed;
    border-color: var(--outline-variant, #e2e8f0);
}

.vcc-custom-checkbox {
    width: 19px;
    height: 19px;
    cursor: pointer;
    accent-color: var(--primary, #0284c7);
    border-radius: 6px;
}

/* Summary Box */
.vcc-summary-box {
    background: var(--surface-container, #f1f5f9);
    border: 1px solid var(--outline-variant, #e2e8f0);
}

/* Dark Mode Adjustments */
[data-bs-theme="dark"] .vcc-selector-container {
    background: #1e293b;
    border-color: #334155;
}
[data-bs-theme="dark"] .vcc-service-tile {
    background: #0f172a;
    border-color: #334155;
}
[data-bs-theme="dark"] .vcc-summary-box {
    background: #0f172a;
    border-color: #334155;
}
[data-bs-theme="dark"] .vcc-service-tile.is-disabled {
    background: #1e293b;
}
</style>

<script>
(function() {
    let currentVccData = null;
    let selectedServices = [];
    let onProceedCallback = null;

    const backdrop = document.getElementById('vccSelectorBackdrop');
    const container = document.getElementById('vccSelectorContainer');
    const serviceList = document.getElementById('vccServiceListContainer');
    const btnCancel = document.getElementById('btnVccCancel');
    const btnProceed = document.getElementById('btnVccProceed');
    const btnProceedText = document.getElementById('btnVccProceedText');
    const btnSelectAll = document.getElementById('btnVccSelectAll');

    const productNameEl = document.getElementById('vccProductName');
    const productPriceEl = document.getElementById('vccProductPrice');
    const selectedCountEl = document.getElementById('vccSelectedCountText');
    const unitPriceEl = document.getElementById('vccUnitPriceText');
    const totalAmountEl = document.getElementById('vccTotalAmountText');

    function formatRupiah(val) {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    }

    function updateSummary() {
        const count = selectedServices.length;
        const unitPrice = currentVccData ? currentVccData.price : 0;
        const total = count * unitPrice;

        selectedCountEl.textContent = `${count} ${count > 1 ? 'layanan' : 'layanan'}`;
        unitPriceEl.textContent = formatRupiah(unitPrice);
        totalAmountEl.textContent = formatRupiah(total);

        if (count > 0) {
            btnProceed.disabled = false;
        } else {
            btnProceed.disabled = true;
        }
    }

    function renderServices(services) {
        if (!services || services.length === 0) {
            serviceList.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-exclamation-circle text-warning fs-3 mb-2"></i>
                    <p class="small mb-0">Belum ada layanan yang dikonfigurasi untuk VCC ini.</p>
                </div>
            `;
            return;
        }

        let html = '';
        services.forEach((srv, idx) => {
            const isAvailable = srv.available && srv.stock > 0;
            const isChecked = selectedServices.includes(srv.name);
            const disabledAttr = isAvailable ? '' : 'disabled';
            const disabledClass = isAvailable ? '' : 'is-disabled';
            const selectedClass = isChecked ? 'is-selected' : '';

            html += `
                <div class="vcc-service-tile ${disabledClass} ${selectedClass}" data-service="${encodeURIComponent(srv.name)}" data-available="${isAvailable ? '1' : '0'}">
                    <div class="d-flex align-items-center gap-2.5">
                        <input type="checkbox" class="vcc-custom-checkbox" id="vcc_srv_${idx}" value="${srv.name}" ${isChecked ? 'checked' : ''} ${disabledAttr}>
                        <label for="vcc_srv_${idx}" class="fw-bold text-body mb-0 small" style="cursor: ${isAvailable ? 'pointer' : 'not-allowed'};">
                            ${srv.name}
                        </label>
                    </div>
                    <div>
                        ${isAvailable 
                            ? `<span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">${srv.stock} stok</span>`
                            : `<span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">Habis</span>`
                        }
                    </div>
                </div>
            `;
        });

        serviceList.innerHTML = html;

        // Attach Click Listeners
        serviceList.querySelectorAll('.vcc-service-tile').forEach(tile => {
            tile.addEventListener('click', function(e) {
                if (tile.dataset.available !== '1') return;
                const checkbox = tile.querySelector('input[type="checkbox"]');
                if (e.target !== checkbox && e.target.tagName !== 'LABEL') {
                    checkbox.checked = !checkbox.checked;
                }
                const serviceName = decodeURIComponent(tile.dataset.service);
                if (checkbox.checked) {
                    if (!selectedServices.includes(serviceName)) selectedServices.push(serviceName);
                    tile.classList.add('is-selected');
                } else {
                    selectedServices = selectedServices.filter(s => s !== serviceName);
                    tile.classList.remove('is-selected');
                }
                updateSummary();
            });

            const checkbox = tile.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.addEventListener('change', function() {
                    const serviceName = decodeURIComponent(tile.dataset.service);
                    if (this.checked) {
                        if (!selectedServices.includes(serviceName)) selectedServices.push(serviceName);
                        tile.classList.add('is-selected');
                    } else {
                        selectedServices = selectedServices.filter(s => s !== serviceName);
                        tile.classList.remove('is-selected');
                    }
                    updateSummary();
                });
            }
        });

        updateSummary();
    }

    // Open Selector Modal/Drawer
    window.openVccSelector = async function(options) {
        currentVccData = {
            productId: options.productId,
            name: options.productName || 'VCC',
            price: options.price || 0,
            formattedPrice: options.formattedPrice || formatRupiah(options.price || 0),
            mode: options.mode || 'checkout' // 'checkout' or 'cart'
        };

        selectedServices = [];
        onProceedCallback = options.onProceed || null;

        if (btnProceedText) {
            btnProceedText.textContent = options.mode === 'cart' ? "{{ __('+ Keranjang') }}" : "{{ __('Lanjut Checkout') }}";
        }

        productNameEl.textContent = currentVccData.name;
        productPriceEl.textContent = currentVccData.formattedPrice;
        selectedCountEl.textContent = '0 layanan';
        unitPriceEl.textContent = currentVccData.formattedPrice;
        totalAmountEl.textContent = 'Rp 0';
        btnProceed.disabled = true;

        backdrop.style.display = 'block';
        // Force reflow
        backdrop.offsetHeight;
        backdrop.classList.add('show');
        document.body.style.overflow = 'hidden';

        if (options.services && Array.isArray(options.services) && options.services.length > 0) {
            renderServices(options.services);
        } else {
            // Fetch live info
            serviceList.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                    <p class="small mb-0">Memuat info layanan VCC...</p>
                </div>
            `;
            try {
                const res = await fetch(`/catalog/${options.productId}/vcc-info`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.product) {
                    currentVccData.price = data.product.price;
                    currentVccData.formattedPrice = data.product.formatted_price;
                    productPriceEl.textContent = currentVccData.formattedPrice;
                    unitPriceEl.textContent = currentVccData.formattedPrice;
                    renderServices(data.product.services);
                } else {
                    serviceList.innerHTML = `
                        <div class="text-center py-4 text-danger small">
                            ${data.message || 'Gagal memuat layanan VCC.'}
                        </div>
                    `;
                }
            } catch (err) {
                console.error('Fetch VCC info failed:', err);
                serviceList.innerHTML = `
                    <div class="text-center py-4 text-danger small">
                        Terjadi kesalahan jaringan saat memuat layanan VCC.
                    </div>
                `;
            }
        }
    };

    // Close Selector
    window.closeVccSelector = function() {
        backdrop.classList.remove('show');
        setTimeout(() => {
            backdrop.style.display = 'none';
            document.body.style.overflow = '';
            selectedServices = [];
            currentVccData = null;
        }, 250);
    };

    // Event Cancel Button
    if (btnCancel) {
        btnCancel.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.closeVccSelector();
        });
    }

    // Click outside container to close
    if (backdrop) {
        backdrop.addEventListener('click', function(e) {
            if (e.target === backdrop) {
                window.closeVccSelector();
            }
        });
    }

    // Select All
    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function(e) {
            e.preventDefault();
            const tiles = serviceList.querySelectorAll('.vcc-service-tile:not(.is-disabled)');
            const allChecked = Array.from(tiles).every(t => t.querySelector('input[type="checkbox"]').checked);

            selectedServices = [];
            tiles.forEach(tile => {
                const cb = tile.querySelector('input[type="checkbox"]');
                const srv = decodeURIComponent(tile.dataset.service);
                cb.checked = !allChecked;
                if (!allChecked) {
                    selectedServices.push(srv);
                    tile.classList.add('is-selected');
                } else {
                    tile.classList.remove('is-selected');
                }
            });
            btnSelectAll.textContent = allChecked ? "{{ __('Pilih Semua') }}" : "{{ __('Batal Semua') }}";
            updateSummary();
        });
    }

    // Proceed Button Action
    if (btnProceed) {
        btnProceed.addEventListener('click', function(e) {
            e.preventDefault();
            if (selectedServices.length === 0 || !currentVccData) return;

            const chosen = [...selectedServices];
            const pId = currentVccData.productId;
            const mode = currentVccData.mode;

            if (typeof onProceedCallback === 'function') {
                onProceedCallback(chosen, currentVccData);
                window.closeVccSelector();
                return;
            }

            // Default actions:
            if (mode === 'cart') {
                // Add to cart via AJAX
                const origHtml = btnProceed.innerHTML;
                btnProceed.disabled = true;
                btnProceed.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                fetch(`/cart/add/${pId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        quantity: chosen.length,
                        vcc_services: chosen
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.closeVccSelector();
                        if (typeof window.updateCartBadgeCount === 'function') {
                            window.updateCartBadgeCount(data.cart_total_qty);
                        } else {
                            const badge = document.getElementById('cart-badge-count');
                            if (badge) {
                                badge.textContent = data.cart_total_qty;
                                badge.classList.remove('d-none');
                                badge.style.display = 'inline-block';
                            }
                        }
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: data.message || 'VCC berhasil ditambahkan ke keranjang!',
                                showConfirmButton: false,
                                timer: 2000
                            });
                        }
                    } else {
                        alert(data.message || 'Gagal menambahkan ke keranjang.');
                    }
                })
                .catch(err => {
                    console.error('Cart add error:', err);
                    alert('Terjadi kesalahan saat menambahkan produk ke keranjang.');
                })
                .finally(() => {
                    btnProceed.disabled = false;
                    btnProceed.innerHTML = origHtml;
                });
            } else {
                // Direct Checkout review
                window.closeVccSelector();
                const params = new URLSearchParams();
                chosen.forEach(s => params.append('vcc_services[]', s));
                params.set('quantity', chosen.length);
                window.location.href = `/checkout/review/${pId}?` + params.toString();
            }
        });
    }
})();
</script>
