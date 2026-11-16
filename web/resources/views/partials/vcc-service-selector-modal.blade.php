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
                    <i class="fas fa-list-check text-primary me-1"></i>{{ __('Pilih Layanan & Atur Kuantitas:') }}
                </span>
                <span class="badge bg-light text-muted border small fw-normal" style="font-size: 0.7rem;">
                    {{ __('1 layanan = 1 stok') }}
                </span>
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
                    <span>{{ __('Total Kuantitas') }}</span>
                    <span class="fw-bold text-body" id="vccSelectedCountText">{{ __(':qty unit kartu', ['qty' => 0]) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center small text-secondary mb-1">
                    <span>{{ __('Harga Satuan') }}</span>
                    <span class="fw-semibold text-body" id="vccUnitPriceText">Rp 0</span>
                </div>
                <div id="vccSelectedBreakdownRow" class="small text-secondary mb-2 d-none">
                    <span class="d-block mb-1 text-muted" style="font-size: 0.72rem;">{{ __('Rincian Layanan:') }}</span>
                    <div id="vccSelectedBreakdownList" class="d-flex flex-wrap gap-1"></div>
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
/* Backdrop Overlay - z-index 10050 di atas loader global */
.vcc-selector-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    z-index: 10050;
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
        max-width: 500px;
        background: var(--surface-container-high, #f8fafc);
        border: 1px solid var(--outline-variant, #e2e8f0);
        border-radius: 28px;
        padding: 28px 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(0, 0, 0, 0.05);
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
        box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.25);
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
    max-height: 250px;
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
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.vcc-service-tile:hover {
    border-color: var(--primary, #0284c7);
}

.vcc-service-tile.is-selected {
    border-color: var(--primary, #0284c7);
    background: rgba(var(--primary-rgb, 2, 132, 199), 0.06);
}

.vcc-service-tile.is-disabled {
    opacity: 0.5;
    background: var(--surface-container-low, #f1f5f9);
    cursor: not-allowed;
    border-color: var(--outline-variant, #e2e8f0);
}

.vcc-custom-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: var(--primary, #0284c7);
    border-radius: 6px;
    flex-shrink: 0;
}

/* Stepper Kuantitas per Layanan */
.vcc-qty-stepper {
    display: flex;
    align-items: center;
    background: var(--surface-container-low, #f1f5f9);
    border: 1px solid var(--outline-variant, #cbd5e1);
    border-radius: 9999px;
    padding: 2px 4px;
}

.vcc-btn-step {
    width: 26px;
    height: 26px;
    padding: 0;
    border-radius: 50% !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
    color: var(--on-surface, #1e293b);
    background: #ffffff;
    border: 1px solid var(--outline-variant, #cbd5e1);
    transition: background 0.15s ease;
}

.vcc-btn-step:hover:not(:disabled) {
    background: var(--primary, #0284c7);
    color: #ffffff;
    border-color: var(--primary, #0284c7);
}

.vcc-btn-step:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.vcc-qty-number {
    min-width: 22px;
    text-align: center;
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--on-surface, #1e293b);
    user-select: none;
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
[data-bs-theme="dark"] .vcc-qty-stepper {
    background: #1e293b;
    border-color: #475569;
}
[data-bs-theme="dark"] .vcc-btn-step {
    background: #334155;
    color: #f8fafc;
    border-color: #475569;
}
[data-bs-theme="dark"] .vcc-qty-number {
    color: #f8fafc;
}
</style>

<script>
(function() {
    let currentVccData = null;
    // Map of serviceName => { qty: number, maxStock: number }
    let selectedServicesMap = {};
    let onProceedCallback = null;

    const backdrop = document.getElementById('vccSelectorBackdrop');
    const container = document.getElementById('vccSelectorContainer');
    const serviceList = document.getElementById('vccServiceListContainer');
    const btnCancel = document.getElementById('btnVccCancel');
    const btnProceed = document.getElementById('btnVccProceed');
    const btnProceedText = document.getElementById('btnVccProceedText');

    const productNameEl = document.getElementById('vccProductName');
    const productPriceEl = document.getElementById('vccProductPrice');
    const selectedCountEl = document.getElementById('vccSelectedCountText');
    const unitPriceEl = document.getElementById('vccUnitPriceText');
    const totalAmountEl = document.getElementById('vccTotalAmountText');
    const breakdownRow = document.getElementById('vccSelectedBreakdownRow');
    const breakdownList = document.getElementById('vccSelectedBreakdownList');

    function formatRupiah(val) {
        return 'Rp ' + Number(val || 0).toLocaleString('id-ID');
    }

    function killPageLoader() {
        const pageLoader = document.getElementById('pageLoader');
        if (pageLoader) {
            pageLoader.classList.add('fade-out');
        }
        if (typeof finishTopLoadingBar === 'function') {
            finishTopLoadingBar();
        }
    }

    function updateSummary() {
        let totalQty = 0;
        let breakdownHtml = '';

        for (const [srvName, item] of Object.entries(selectedServicesMap)) {
            if (item.qty > 0) {
                totalQty += item.qty;
                breakdownHtml += `
                    <span class="badge bg-white border text-body py-1 px-2 fw-semibold" style="font-size: 0.72rem;">
                        ${srvName}: <strong class="text-primary">${item.qty} {{ __('unit') }}</strong>
                    </span>
                `;
            }
        }

        const unitPrice = currentVccData ? currentVccData.price : 0;
        const total = totalQty * unitPrice;

        selectedCountEl.textContent = "{{ __(':qty unit kartu', ['qty' => 'PLACEHOLDER']) }}".replace('PLACEHOLDER', totalQty);
        unitPriceEl.textContent = formatRupiah(unitPrice);
        totalAmountEl.textContent = formatRupiah(total);

        if (totalQty > 0) {
            btnProceed.disabled = false;
            breakdownRow.classList.remove('d-none');
            breakdownList.innerHTML = breakdownHtml;
        } else {
            btnProceed.disabled = true;
            breakdownRow.classList.add('d-none');
            breakdownList.innerHTML = '';
        }
    }

    function renderServices(services) {
        if (!services || services.length === 0) {
            serviceList.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-exclamation-circle text-warning fs-3 mb-2"></i>
                    <p class="small mb-0">{{ __('Belum ada layanan yang dikonfigurasi untuk VCC ini.') }}</p>
                </div>
            `;
            return;
        }

        let html = '';
        services.forEach((srv, idx) => {
            const isAvailable = srv.available && srv.stock > 0;
            const currentItem = selectedServicesMap[srv.name];
            const isChecked = !!currentItem && currentItem.qty > 0;
            const currentQty = isChecked ? currentItem.qty : 1;
            const disabledAttr = isAvailable ? '' : 'disabled';
            const disabledClass = isAvailable ? '' : 'is-disabled';
            const selectedClass = isChecked ? 'is-selected' : '';

            html += `
                <div class="vcc-service-tile ${disabledClass} ${selectedClass}" data-service="${encodeURIComponent(srv.name)}" data-stock="${srv.stock}" data-available="${isAvailable ? '1' : '0'}">
                    <div class="d-flex align-items-center gap-2.5 overflow-hidden">
                        <input type="checkbox" class="vcc-custom-checkbox" id="vcc_srv_${idx}" value="${srv.name}" ${isChecked ? 'checked' : ''} ${disabledAttr}>
                        <div>
                            <label for="vcc_srv_${idx}" class="fw-bold text-body mb-0 small d-block" style="cursor: ${isAvailable ? 'pointer' : 'not-allowed'};">
                                ${srv.name}
                            </label>
                            <small class="text-secondary" style="font-size: 0.68rem;">
                                ${isAvailable ? "{{ __('Tersedia: :stock stok') }}".replace(':stock', srv.stock) : "{{ __('Stok habis') }}"}
                            </small>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        {{-- Stepper Kuantitas (Muncul setelah diceklis) --}}
                        <div class="vcc-qty-stepper ${isChecked ? '' : 'd-none'}" id="stepper_${idx}">
                            <button type="button" class="btn vcc-btn-step vcc-step-minus" data-service="${encodeURIComponent(srv.name)}" ${currentQty <= 1 ? 'disabled' : ''} title="{{ __('Kurangi Kuantitas') }}">
                                <i class="fas fa-minus" style="font-size: 0.6rem;"></i>
                            </button>
                            <span class="vcc-qty-number px-1.5" id="qty_num_${idx}">${currentQty}</span>
                            <button type="button" class="btn vcc-btn-step vcc-step-plus" data-service="${encodeURIComponent(srv.name)}" ${currentQty >= srv.stock ? 'disabled' : ''} title="{{ __('Tambah Kuantitas') }}">
                                <i class="fas fa-plus" style="font-size: 0.6rem;"></i>
                            </button>
                        </div>

                        {{-- Default Stock Badge saat belum diceklis --}}
                        <div class="vcc-stock-badge-wrap ${isChecked ? 'd-none' : ''}" id="stock_badge_${idx}">
                            ${isAvailable 
                                ? `<span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">${srv.stock} {{ __('stok') }}</span>`
                                : `<span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">{{ __('Habis') }}</span>`
                            }
                        </div>
                    </div>
                </div>
            `;
        });

        serviceList.innerHTML = html;

        // Attach Click & Stepper Listeners
        serviceList.querySelectorAll('.vcc-service-tile').forEach((tile, idx) => {
            if (tile.dataset.available !== '1') return;

            const checkbox = tile.querySelector('input[type="checkbox"]');
            const stepper = tile.querySelector('.vcc-qty-stepper');
            const stockBadge = tile.querySelector('.vcc-stock-badge-wrap');
            const qtyNum = tile.querySelector('.vcc-qty-number');
            const btnMinus = tile.querySelector('.vcc-step-minus');
            const btnPlus = tile.querySelector('.vcc-step-plus');
            const serviceName = decodeURIComponent(tile.dataset.service);
            const maxStock = parseInt(tile.dataset.stock, 10) || 1;

            function syncItemState(checked) {
                checkbox.checked = checked;
                if (checked) {
                    tile.classList.add('is-selected');
                    stepper.classList.remove('d-none');
                    stockBadge.classList.add('d-none');
                    const initialQty = selectedServicesMap[serviceName]?.qty || 1;
                    selectedServicesMap[serviceName] = { qty: initialQty, maxStock: maxStock };
                    qtyNum.textContent = initialQty;
                    btnMinus.disabled = initialQty <= 1;
                    btnPlus.disabled = initialQty >= maxStock;
                } else {
                    tile.classList.remove('is-selected');
                    stepper.classList.add('d-none');
                    stockBadge.classList.remove('d-none');
                    delete selectedServicesMap[serviceName];
                }
                updateSummary();
            }

            checkbox.addEventListener('change', function(e) {
                e.stopPropagation();
                syncItemState(this.checked);
            });

            // Klik label
            const label = tile.querySelector('label');
            if (label) {
                label.addEventListener('click', function(e) {
                    e.preventDefault();
                    syncItemState(!checkbox.checked);
                });
            }

            // Stepper Minus
            btnMinus.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!selectedServicesMap[serviceName]) return;
                let cur = selectedServicesMap[serviceName].qty;
                if (cur > 1) {
                    cur--;
                    selectedServicesMap[serviceName].qty = cur;
                    qtyNum.textContent = cur;
                    btnMinus.disabled = cur <= 1;
                    btnPlus.disabled = cur >= maxStock;
                    updateSummary();
                }
            });

            // Stepper Plus
            btnPlus.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!selectedServicesMap[serviceName]) return;
                let cur = selectedServicesMap[serviceName].qty;
                if (cur < maxStock) {
                    cur++;
                    selectedServicesMap[serviceName].qty = cur;
                    qtyNum.textContent = cur;
                    btnMinus.disabled = cur <= 1;
                    btnPlus.disabled = cur >= maxStock;
                    updateSummary();
                }
            });
        });

        updateSummary();
    }

    // Open Selector Modal/Drawer
    window.openVccSelector = async function(options) {
        killPageLoader();

        currentVccData = {
            productId: options.productId,
            name: options.productName || 'VCC',
            price: options.price || 0,
            formattedPrice: options.formattedPrice || formatRupiah(options.price || 0),
            mode: options.mode || 'checkout' // 'checkout' or 'cart'
        };

        selectedServicesMap = {};
        onProceedCallback = options.onProceed || null;

        if (btnProceedText) {
            btnProceedText.textContent = options.mode === 'cart' ? "{{ __('+ Keranjang') }}" : "{{ __('Lanjut Checkout') }}";
        }

        productNameEl.textContent = currentVccData.name;
        productPriceEl.textContent = currentVccData.formattedPrice;
        selectedCountEl.textContent = "{{ __(':qty unit kartu', ['qty' => 0]) }}";
        unitPriceEl.textContent = currentVccData.formattedPrice;
        totalAmountEl.textContent = 'Rp 0';
        breakdownRow.classList.add('d-none');
        btnProceed.disabled = true;

        backdrop.style.display = 'block';
        backdrop.offsetHeight; // Force reflow
        backdrop.classList.add('show');
        document.body.style.overflow = 'hidden';

        killPageLoader();

        if (options.services && Array.isArray(options.services) && options.services.length > 0) {
            renderServices(options.services);
        } else {
            // Fetch live info
            serviceList.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                    <p class="small mb-0">{{ __('Memuat info layanan VCC...') }}</p>
                </div>
            `;
            try {
                const res = await fetch(`/catalog/${options.productId}/vcc-info`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                killPageLoader();
                if (data.success && data.product) {
                    currentVccData.price = data.product.price;
                    currentVccData.formattedPrice = data.product.formatted_price;
                    productPriceEl.textContent = currentVccData.formattedPrice;
                    unitPriceEl.textContent = currentVccData.formattedPrice;
                    renderServices(data.product.services);
                } else {
                    serviceList.innerHTML = `
                        <div class="text-center py-4 text-danger small">
                            ${data.message || '{{ __('Gagal memuat layanan VCC.') }}'}
                        </div>
                    `;
                }
            } catch (err) {
                console.error('Fetch VCC info failed:', err);
                killPageLoader();
                serviceList.innerHTML = `
                    <div class="text-center py-4 text-danger small">
                        {{ __('Terjadi kesalahan jaringan saat memuat layanan VCC.') }}
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
            selectedServicesMap = {};
            currentVccData = null;
            killPageLoader();
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

    // Proceed Button Action
    if (btnProceed) {
        btnProceed.addEventListener('click', function(e) {
            e.preventDefault();
            if (!currentVccData) return;

            // Flatten services array based on chosen quantities
            // e.g. { Shopee: 2, DigitalOcean: 1 } => ['Shopee', 'Shopee', 'DigitalOcean']
            const chosenFlatServices = [];
            for (const [srvName, item] of Object.entries(selectedServicesMap)) {
                for (let i = 0; i < item.qty; i++) {
                    chosenFlatServices.push(srvName);
                }
            }

            if (chosenFlatServices.length === 0) return;

            const pId = currentVccData.productId;
            const mode = currentVccData.mode;

            if (typeof onProceedCallback === 'function') {
                onProceedCallback(chosenFlatServices, currentVccData);
                window.closeVccSelector();
                return;
            }

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
                        quantity: chosenFlatServices.length,
                        vcc_services: chosenFlatServices
                    })
                })
                .then(r => r.json())
                .then(data => {
                    killPageLoader();
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
                                title: data.message || '{{ __('VCC berhasil ditambahkan ke keranjang!') }}',
                                showConfirmButton: false,
                                timer: 2000
                            });
                        }
                    } else {
                        alert(data.message || '{{ __('Gagal menambahkan ke keranjang.') }}');
                    }
                })
                .catch(err => {
                    console.error('Cart add error:', err);
                    killPageLoader();
                    alert('{{ __('Terjadi kesalahan saat menambahkan produk ke keranjang.') }}');
                })
                .finally(() => {
                    btnProceed.disabled = false;
                    btnProceed.innerHTML = origHtml;
                });
            } else {
                // Direct Checkout review
                window.closeVccSelector();
                const params = new URLSearchParams();
                chosenFlatServices.forEach(s => params.append('vcc_services[]', s));
                params.set('quantity', chosenFlatServices.length);
                window.location.href = `/checkout/review/${pId}?` + params.toString();
            }
        });
    }
})();
</script>
