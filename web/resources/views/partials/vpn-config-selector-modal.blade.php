{{-- Material 3 VPN Checkout Config Selector (Desktop Popup Dialog & Mobile Bottom Drawer — mirrors VCC Selector) --}}
<div id="vpnSelectorBackdrop" class="vpn-selector-backdrop" style="display: none;" aria-hidden="true">
    <div id="vpnSelectorContainer" class="vpn-selector-container">
        <div class="vpn-drawer-handle d-md-none">
            <span class="vpn-drawer-pill"></span>
        </div>

        <div class="text-center mb-3 flex-shrink-0">
            <div class="vpn-icon-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-sm">
                <i class="fas fa-network-wired text-primary" style="font-size: 1.35rem;"></i>
            </div>
            <h5 class="fw-bold text-body mb-0.5">{{ __('Konfigurasi Akun VPN') }}</h5>
            <p class="text-secondary small mb-0">
                <span id="vpnSelProductName">Layanan VPN</span> &bull; <span class="fw-semibold text-primary" id="vpnSelBasePrice">Rp 0</span> / <span id="vpnSelBaseDays">30</span> {{ __('hari') }}
            </p>
        </div>

        <div class="vpn-dialog-body custom-scrollbar">
            {{-- 1. Server VPN (shown when > 1 server allowed) --}}
            <div id="vpnSelServerWrap" class="mb-2.5 d-none">
                <label class="form-label small fw-bold text-body mb-1">{{ __('Pilih Server VPN') }}</label>
                <select id="vpnSelServer" class="form-select form-select-sm"></select>
            </div>

            {{-- 2. Protokol VPN --}}
            <div class="mb-2.5">
                <label class="form-label small fw-bold text-body mb-1">{{ __('Pilih Protokol') }} <span class="text-danger">*</span></label>
                <div id="vpnSelProtoGrid" class="d-flex flex-wrap gap-1.5"></div>
            </div>

            {{-- 3. Durasi / Masa Aktif --}}
            <div class="mb-2.5">
                <label class="form-label small fw-bold text-body mb-1">{{ __('Masa Aktif (Hari)') }} <span class="text-danger">*</span></label>
                <input type="number" id="vpnSelDays" class="form-control form-control-sm mb-1.5" value="30" min="1" max="365" required>
                <div class="d-flex flex-wrap gap-1">
                    @foreach([1, 7, 14, 30, 60] as $dPreset)
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-0.5 fw-semibold vpn-sel-day-chip" data-days="{{ $dPreset }}" style="font-size: 0.74rem;">
                        {{ $dPreset }} {{ __('Hari') }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- 4. Username VPN (Global Live Auto-Check) --}}
            <div class="mb-2.5">
                <label class="form-label small fw-bold text-body mb-1">{{ __('Username VPN') }} <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm">
                    <input type="text" id="vpnSelUsername" class="form-control font-monospace" autocomplete="off" placeholder="{{ __('Contoh: user123') }}" pattern="[a-zA-Z0-9_-]{3,24}">
                    <span class="input-group-text bg-white" id="vpnSelUserIndicator">
                        <i class="fas fa-user-check text-muted"></i>
                    </span>
                </div>
                <div id="vpnSelUserStatus" class="form-text small text-muted" style="font-size: 0.72rem;">{{ __('Ketersediaan username diperiksa otomatis saat Anda mengetik.') }}</div>
            </div>

            {{-- 5A. Password SSH (only for SSH) --}}
            <div id="vpnSelSshPassWrap" class="mb-2.5 d-none">
                <label class="form-label small fw-bold text-body mb-1">{{ __('Password SSH') }} <span class="text-danger">*</span></label>
                <input type="text" id="vpnSelSshPassword" class="form-control form-control-sm font-monospace" minlength="3" maxlength="64" placeholder="{{ __('Minimal 3 karakter, tanpa spasi') }}">
            </div>

            {{-- 5B. Preset Custom SNI (only for Xray protocols) --}}
            <div id="vpnSelSniWrap" class="mb-2.5">
                <label class="form-label small fw-bold text-body mb-1">{{ __('Preset / Custom SNI') }}</label>
                <select id="vpnSelSniMode" class="form-select form-select-sm">
                    <option value="3">{{ __('Tanpa Konfigurasi (Default SNI)') }}</option>
                    <option value="1">Zoom (support.zoom.us — Wildcard)</option>
                    <option value="2">Iflix (live.iflix.com)</option>
                </select>
            </div>

            {{-- Summary Box --}}
            <div class="p-3 rounded-4 border mb-2" style="background: var(--surface-container-low, var(--bs-tertiary-bg)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
                <div class="d-flex justify-content-between align-items-center small text-secondary mb-1">
                    <span>{{ __('Spesifikasi') }}</span>
                    <span class="fw-semibold text-body" id="vpnSelSpecSummary">-</span>
                </div>
                <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1" style="border-color: var(--outline-variant, var(--bs-border-color)) !important;">
                    <span class="fw-bold text-body small">{{ __('Total Harga Bayar:') }}</span>
                    <span class="fw-bold text-primary fs-5" id="vpnSelTotalPrice">Rp 0</span>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-2 flex-shrink-0">
            <button type="button" class="btn btn-light border rounded-pill py-2.5 px-3 fw-bold flex-grow-1" id="btnVpnSelCancel">
                {{ __('Batal') }}
            </button>
            <button type="button" class="btn btn-primary rounded-pill py-2.5 px-3 fw-bold flex-grow-1 d-flex align-items-center justify-content-center gap-1.5 shadow-sm" id="btnVpnSelProceed" disabled>
                <i class="fas fa-bolt"></i>
                <span>{{ __('Lanjut Checkout') }}</span>
            </button>
        </div>
    </div>
</div>

<style>
.vpn-selector-backdrop {
    position: fixed;
    inset: 0;
    background: var(--overlay, rgba(15, 17, 23, 0.44));
    z-index: 10050;
    opacity: 0;
    visibility: hidden;
    transition: opacity 200ms ease, visibility 200ms ease;
}
.vpn-selector-backdrop.show {
    opacity: 1;
    visibility: visible;
}
.vpn-icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: var(--bs-primary-bg-subtle);
}
.vpn-dialog-body {
    overflow-y: auto;
    padding-right: 2px;
    flex: 1 1 auto;
}
.vpn-proto-pill {
    border-radius: 999px !important;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.35rem 0.85rem;
}
@media (min-width: 768px) {
    .vpn-selector-container {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.94);
        width: 92%;
        max-width: 490px;
        background: var(--surface-container-high, #fff);
        border: 1px solid var(--outline-variant, #cac4d0);
        border-radius: 28px;
        padding: 24px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.16);
        opacity: 0;
        transition: transform 220ms ease, opacity 200ms ease;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }
    .vpn-selector-backdrop.show .vpn-selector-container {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
    }
}
@media (max-width: 767.98px) {
    .vpn-selector-container {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--surface-container-high, #fff);
        border-radius: 28px 28px 0 0;
        padding: 14px 18px max(18px, env(safe-area-inset-bottom));
        transform: translateY(100%);
        transition: transform 240ms cubic-bezier(0.05, 0.7, 0.1, 1);
        max-height: 90dvh;
        display: flex;
        flex-direction: column;
    }
    .vpn-selector-backdrop.show .vpn-selector-container {
        transform: translateY(0);
    }
    .vpn-drawer-handle {
        display: flex;
        justify-content: center;
        padding-bottom: 10px;
    }
    .vpn-drawer-pill {
        width: 38px;
        height: 4px;
        border-radius: 99px;
        background: var(--outline-variant, #cbd5e1);
    }
}
</style>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    const backdrop = document.getElementById('vpnSelectorBackdrop');
    if (!backdrop) return;

    const elName = document.getElementById('vpnSelProductName');
    const elBasePrice = document.getElementById('vpnSelBasePrice');
    const elBaseDays = document.getElementById('vpnSelBaseDays');
    const elServerWrap = document.getElementById('vpnSelServerWrap');
    const elServer = document.getElementById('vpnSelServer');
    const elProtoGrid = document.getElementById('vpnSelProtoGrid');
    const elDays = document.getElementById('vpnSelDays');
    const elUsername = document.getElementById('vpnSelUsername');
    const elUserIndicator = document.getElementById('vpnSelUserIndicator');
    const elUserStatus = document.getElementById('vpnSelUserStatus');
    const elSshWrap = document.getElementById('vpnSelSshPassWrap');
    const elSshPass = document.getElementById('vpnSelSshPassword');
    const elSniWrap = document.getElementById('vpnSelSniWrap');
    const elSniMode = document.getElementById('vpnSelSniMode');
    const elSpecSummary = document.getElementById('vpnSelSpecSummary');
    const elTotalPrice = document.getElementById('vpnSelTotalPrice');
    const btnCancel = document.getElementById('btnVpnSelCancel');
    const btnProceed = document.getElementById('btnVpnSelProceed');

    let state = null;
    let checkTimer = null;
    let checkAbort = null;
    let usernameValid = false;

    const formatRp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(n)));

    function closeModal() {
        backdrop.classList.remove('show');
        backdrop.setAttribute('aria-hidden', 'true');
        setTimeout(() => { backdrop.style.display = 'none'; }, 220);
        document.body.style.overflow = '';
    }

    function updateProceedState() {
        if (!state) return;
        const days = parseInt(elDays.value || '0', 10) || 0;
        const daysOk = days >= 1 && days <= 365;
        const sshOk = state.selectedProtocol !== 'ssh' || (elSshPass.value.trim().length >= 3 && !/\s/.test(elSshPass.value));
        btnProceed.disabled = !(usernameValid && daysOk && sshOk && !!state.selectedProtocol);
    }

    function updatePricingAndSpec() {
        if (!state) return;
        const days = Math.max(1, Math.min(365, parseInt(elDays.value || '0', 10) || state.baseDays));
        const total = state.basePrice > 0
            ? (days === state.baseDays ? state.basePrice : Math.max(1, Math.round((state.basePrice / state.baseDays) * days)))
            : 0;
        elTotalPrice.textContent = formatRp(total);
        const quotaTxt = state.quotaGb > 0 ? (state.quotaGb + ' GB') : 'Unlimited';
        elSpecSummary.textContent = state.selectedProtocol.toUpperCase() + ' • ' + days + ' Hari • ' + quotaTxt + ' • ' + state.ipLimit + ' IP';

        document.querySelectorAll('.vpn-sel-day-chip').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.days, 10) === days);
        });
        updateProceedState();
    }

    function selectProtocol(proto) {
        state.selectedProtocol = proto;
        elProtoGrid.querySelectorAll('.vpn-proto-pill').forEach(b => {
            const active = b.dataset.proto === proto;
            b.classList.toggle('btn-primary', active);
            b.classList.toggle('btn-outline-primary', !active);
        });
        const isSsh = proto === 'ssh';
        elSshWrap.classList.toggle('d-none', !isSsh);
        elSniWrap.classList.toggle('d-none', isSsh);
        updatePricingAndSpec();
    }

    function runUsernameCheck() {
        if (!state) return;
        const uname = elUsername.value.trim();
        clearTimeout(checkTimer);
        if (checkAbort) { checkAbort.abort(); checkAbort = null; }

        if (uname.length === 0) {
            usernameValid = false;
            elUserIndicator.innerHTML = '<i class="fas fa-user-check text-muted"></i>';
            elUserStatus.className = 'form-text small text-muted';
            elUserStatus.textContent = @js(__('Ketersediaan username diperiksa otomatis saat Anda mengetik.'));
            updateProceedState();
            return;
        }
        if (!/^[a-zA-Z0-9_-]{3,24}$/.test(uname)) {
            usernameValid = false;
            elUserIndicator.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>';
            elUserStatus.className = 'form-text small text-warning fw-semibold';
            elUserStatus.textContent = @js(__('Username harus 3–24 karakter (huruf, angka, _ atau -).'));
            updateProceedState();
            return;
        }

        usernameValid = false;
        elUserIndicator.innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i>';
        elUserStatus.className = 'form-text small text-muted';
        elUserStatus.textContent = @js(__('Memeriksa ketersediaan username...'));
        updateProceedState();

        checkTimer = setTimeout(async function () {
            checkAbort = new AbortController();
            const srvId = (!elServerWrap.classList.contains('d-none') && elServer.value) ? elServer.value : (state.fixedServerId || '');
            const url = state.checkUrl + '?username=' + encodeURIComponent(uname) + (srvId ? '&server_id=' + encodeURIComponent(srvId) : '');
            try {
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: checkAbort.signal
                });
                const data = await res.json();
                if (data.success && data.available) {
                    usernameValid = true;
                    elUserIndicator.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                    elUserStatus.className = 'form-text small text-success fw-semibold';
                    elUserStatus.textContent = data.message || @js(__('Username tersedia.'));
                } else if (data.success && !data.available) {
                    usernameValid = false;
                    elUserIndicator.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
                    elUserStatus.className = 'form-text small text-danger fw-semibold';
                    elUserStatus.textContent = data.message || @js(__('Username sudah terpakai.'));
                } else {
                    usernameValid = true;
                    elUserIndicator.innerHTML = '<i class="fas fa-info-circle text-warning"></i>';
                    elUserStatus.className = 'form-text small text-warning';
                    elUserStatus.textContent = data.message || @js(__('Tidak dapat memverifikasi ke server saat ini.'));
                }
                updateProceedState();
            } catch (e) {
                if (e.name !== 'AbortError') {
                    usernameValid = true;
                    elUserIndicator.innerHTML = '<i class="fas fa-info-circle text-muted"></i>';
                    elUserStatus.className = 'form-text small text-muted';
                    elUserStatus.textContent = @js(__('Gagal mengecek otomatis; validasi tetap berjalan saat checkout.'));
                    updateProceedState();
                }
            }
        }, 420);
    }

    elUsername.addEventListener('input', runUsernameCheck);
    elServer.addEventListener('change', runUsernameCheck);
    elDays.addEventListener('input', updatePricingAndSpec);
    elSshPass.addEventListener('input', updateProceedState);
    document.querySelectorAll('.vpn-sel-day-chip').forEach(btn => {
        btn.addEventListener('click', function () {
            elDays.value = this.dataset.days;
            updatePricingAndSpec();
        });
    });

    btnCancel.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) closeModal();
    });

    btnProceed.addEventListener('click', function () {
        if (!state || btnProceed.disabled) return;
        const params = new URLSearchParams();
        params.set('quantity', '1');
        params.set('vpn_protocol', state.selectedProtocol);
        params.set('vpn_days', String(Math.max(1, Math.min(365, parseInt(elDays.value || '30', 10) || state.baseDays))));
        params.set('vpn_username', elUsername.value.trim());
        if (state.selectedProtocol === 'ssh') {
            params.set('vpn_password', elSshPass.value.trim());
        } else {
            params.set('vpn_sni_mode', elSniMode.value || '3');
        }
        const srvId = (!elServerWrap.classList.contains('d-none') && elServer.value) ? elServer.value : (state.fixedServerId || '');
        if (srvId) params.set('vpn_server_id', String(srvId));
        window.location.href = state.reviewUrl + '?' + params.toString();
    });

    window.openVpnCheckoutSelector = function (opts) {
        state = {
            productId: opts.productId,
            productName: opts.productName || 'VPN',
            basePrice: Number(opts.basePrice || 0),
            baseDays: Math.max(1, Number(opts.baseDays || 30)),
            quotaGb: Number(opts.quotaGb || 0),
            ipLimit: Math.max(1, Number(opts.ipLimit || 1)),
            defaultSni: String(opts.defaultSni || '3'),
            protocols: Array.isArray(opts.protocols) && opts.protocols.length ? opts.protocols : ['vmess'],
            servers: Array.isArray(opts.servers) ? opts.servers : [],
            fixedServerId: opts.fixedServerId || null,
            checkUrl: opts.checkUrl,
            reviewUrl: opts.reviewUrl,
            selectedProtocol: null,
        };

        elName.textContent = state.productName;
        elBasePrice.textContent = formatRp(state.basePrice);
        elBaseDays.textContent = String(state.baseDays);
        elDays.value = String(state.baseDays);
        elSniMode.value = ['1', '2', '3'].includes(state.defaultSni) ? state.defaultSni : '3';
        elUsername.value = '';
        elSshPass.value = '';
        usernameValid = false;
        elUserIndicator.innerHTML = '<i class="fas fa-user-check text-muted"></i>';
        elUserStatus.className = 'form-text small text-muted';
        elUserStatus.textContent = @js(__('Ketersediaan username diperiksa otomatis saat Anda mengetik.'));

        // Servers
        if (!state.fixedServerId && state.servers.length > 1) {
            elServerWrap.classList.remove('d-none');
            elServer.innerHTML = state.servers.map(s => `<option value="${s.id}">${s.name}${s.region ? ' (' + s.region + ')' : ''}</option>`).join('');
        } else {
            elServerWrap.classList.add('d-none');
            elServer.innerHTML = '';
        }

        // Protocols
        elProtoGrid.innerHTML = '';
        state.protocols.forEach(p => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-outline-primary vpn-proto-pill';
            b.dataset.proto = p;
            b.textContent = p.toUpperCase();
            b.addEventListener('click', () => selectProtocol(p));
            elProtoGrid.appendChild(b);
        });
        selectProtocol(state.protocols[0]);

        backdrop.style.display = 'block';
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => backdrop.classList.add('show'));
        setTimeout(() => elUsername.focus(), 260);
    };
})();
</script>
