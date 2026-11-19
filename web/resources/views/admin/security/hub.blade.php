@extends('layouts.app')

@section('title', __('Pusat Keamanan & Firewall'))
@section('page_subtitle', __('IAM, peta sebaran pengunjung, analitik autentikasi, firewall IP/CIDR & pengawasan sesi aktif'))

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<style>
    /* Unified Security Hub — M3 Scrollable Pill Tab Bar */
    .sec-hub-tabs-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: 2px;
    }
    .sec-hub-tabs-scroll::-webkit-scrollbar {
        display: none;
    }
    .sec-hub-tabbar {
        display: inline-flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 6px;
        padding: 6px;
        border-radius: 9999px;
        background: var(--surface-container-low, #f3edf7);
        border: 1px solid var(--outline-variant, #cac4d0);
        min-width: max-content;
    }
    .sec-hub-tabbar .nav-link {
        border: none !important;
        border-radius: 9999px !important;
        padding: 9px 16px;
        font-size: 0.84rem;
        font-weight: 600;
        color: var(--on-surface-variant, #49454f);
        background: transparent;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        transition: background-color 0.18s ease, color 0.18s ease;
    }
    .sec-hub-tabbar .nav-link:hover:not(.active) {
        background: var(--surface-container-high, #e6e0e9);
        color: var(--on-surface, #1d1b20);
    }
    .sec-hub-tabbar .nav-link.active {
        background: var(--primary) !important;
        color: var(--on-primary, #ffffff) !important;
    }
    .sec-hub-tabbar .nav-link.active .sec-tab-badge {
        background: rgba(255, 255, 255, 0.22) !important;
        color: #ffffff !important;
    }
    .sec-tab-badge {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 9999px;
        line-height: 1.2;
    }

    /* Map & Geo Controls */
    #visitorGeoMap {
        height: 440px;
        width: 100%;
        border-radius: var(--radius-md, 16px);
        z-index: 1;
        background: var(--surface-container);
        border: 1px solid var(--outline-variant);
        overflow: hidden;
    }
    [data-bs-theme="dark"] #visitorGeoMap.geo-basemap-roadmap .leaflet-tile-pane {
        filter: invert(100%) hue-rotate(180deg) brightness(92%) contrast(90%);
    }
    @media (max-width: 991.98px) {
        #visitorGeoMap {
            height: 360px;
        }
    }
    @media (max-width: 575.98px) {
        #visitorGeoMap {
            height: 300px;
        }
    }

    .geo-controls-wrap {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    .geo-segmented-bar {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
        padding: 4px;
        border-radius: 9999px;
        background: var(--surface-container-high, #ece6f0);
        border: 1px solid var(--outline-variant, #cac4d0);
    }
    .geo-seg-btn {
        appearance: none;
        border: none !important;
        background: transparent;
        color: var(--on-surface-variant, #49454f);
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1;
        height: 32px;
        padding: 0 14px;
        border-radius: 9999px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        cursor: pointer;
        white-space: nowrap;
        transition: background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
    }
    .geo-seg-btn:hover:not(.active) {
        background: rgba(var(--primary-rgb, 101, 85, 143), 0.1);
        color: var(--primary);
    }
    .geo-seg-btn.active {
        background: var(--primary) !important;
        color: var(--on-primary, #ffffff) !important;
        box-shadow: 0 2px 6px rgba(var(--primary-rgb, 101, 85, 143), 0.25);
    }
    .geo-focus-group {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .geo-focus-btn {
        appearance: none;
        height: 38px;
        padding: 0 14px;
        border-radius: 9999px !important;
        border: 1px solid var(--outline-variant, #cac4d0) !important;
        background: var(--surface-container-lowest, #ffffff);
        color: var(--on-surface, #1d1b20);
        font-size: 0.78rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        white-space: nowrap;
        transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
    }
    .geo-focus-btn:hover {
        background: var(--primary-container);
        border-color: var(--primary) !important;
        color: var(--on-primary-container);
    }

    .geo-stat-card {
        padding: 12px 16px;
        border-radius: 14px;
        background: var(--surface-container-low, #f3edf7);
        border: 1px solid var(--outline-variant, #cac4d0);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        height: 100%;
    }
    .geo-stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.95rem;
    }

    .geo-location-panel {
        padding: 16px;
        border-radius: var(--radius-md, 16px);
        background: var(--surface-container-low, #f3edf7);
        border: 1px solid var(--outline-variant, #cac4d0);
        height: 440px;
        display: flex;
        flex-direction: column;
    }
    @media (max-width: 991.98px) {
        .geo-location-panel {
            height: auto;
            max-height: 380px;
        }
    }
    .geo-location-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        overflow-y: auto;
        padding-right: 4px;
        flex: 1 1 auto;
    }
    .geo-location-row {
        padding: 12px 14px;
        border-radius: 12px;
        background: var(--surface-container-lowest, #ffffff);
        border: 1px solid var(--outline-variant, #cac4d0);
        cursor: pointer;
        transition: background-color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
    }
    .geo-location-row:hover {
        background-color: var(--surface-container-high) !important;
        border-color: var(--primary) !important;
    }
    .geo-legend-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 16px;
        margin-top: 12px;
        font-size: 0.76rem;
        color: var(--on-surface-variant);
    }
    .geo-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-weight: 500;
    }
    .geo-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }

    #visitorGeoMap .leaflet-control-zoom {
        border: 1px solid var(--outline-variant, #cac4d0) !important;
        border-radius: 12px !important;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
    }
    #visitorGeoMap .leaflet-control-zoom a {
        background: var(--surface-container-lowest, #ffffff) !important;
        color: var(--on-surface, #1d1b20) !important;
        border-bottom-color: var(--outline-variant, #cac4d0) !important;
        width: 34px !important;
        height: 34px !important;
        line-height: 34px !important;
        font-weight: 700;
    }
    #visitorGeoMap .leaflet-control-zoom a:hover {
        background: var(--surface-container-high, #e6e0e9) !important;
        color: var(--primary) !important;
    }
    .leaflet-popup-content-wrapper {
        border-radius: 16px !important;
        background: var(--surface-container-lowest, #ffffff) !important;
        color: var(--on-surface, #1d1b20) !important;
        border: 1px solid var(--outline-variant, #cac4d0);
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.18) !important;
    }
    .leaflet-popup-content {
        margin: 14px 16px !important;
    }
    .leaflet-popup-tip {
        background: var(--surface-container-lowest, #ffffff) !important;
    }
    .leaflet-container a.leaflet-popup-close-button {
        color: var(--on-surface-variant) !important;
        top: 8px !important;
        right: 8px !important;
    }
</style>
@endpush

@section('action_bar_buttons')
    @if(request()->filled('search') || request()->filled('status') || request()->filled('device_type') || request()->filled('fw_search') || (request()->filled('fw_type') && request('fw_type') !== 'all'))
        <a href="{{ route('admin.logins.index', ['tab' => $activeTab]) }}" class="cm-btn-outline">
            <i class="fas fa-undo"></i>
            <span>{{ __('Reset Filter') }}</span>
        </a>
    @endif
@endsection

@section('content')
@php
    $threatBadgeClass = $threatLevel === 'high'
        ? 'bg-danger-subtle text-danger'
        : ($threatLevel === 'medium' ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary');
    $threatLabel = $threatLevel === 'high'
        ? __('Ancaman Tinggi')
        : ($threatLevel === 'medium' ? __('Waspada') : __('Status Aman'));
@endphp

{{-- Header Komando Pusat Keamanan & Firewall --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h4 class="fw-bold mb-0 text-body">
                <i class="fas fa-shield-halved text-primary me-1.5"></i>{{ __('Pusat Keamanan & Firewall') }}
            </h4>
            <span class="badge {{ $threatBadgeClass }} rounded-pill px-3 py-1.5 fw-bold">
                <i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>{{ $threatLabel }}
            </span>
            <span class="badge bg-{{ $securityBadgeColor ?? 'success' }}-subtle text-{{ $securityBadgeColor ?? 'success' }} rounded-pill px-3 py-1.5 fw-semibold">
                <i class="fas fa-heartbeat me-1"></i>{{ __('Skor') }} {{ $securityScore ?? 100 }}/100
            </span>
        </div>
        <small class="text-secondary">{{ __('Satu pusat kendali terpadu untuk peta geografis, analitik login, riwayat token, aturan firewall IP/CIDR, deteksi anti-fraud, dan sesi aktif.') }}</small>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <form action="{{ route('admin.security.firewall.auto-block') }}" method="POST" class="m-0">
            @csrf
            <input type="hidden" name="active_tab" value="{{ $activeTab }}">
            <input type="hidden" name="threshold" value="5">
            <input type="hidden" name="duration_days" value="7">
            <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 py-2 fw-semibold">
                <i class="fas fa-bolt me-1"></i>{{ __('Auto-Blokir IP Agresif (≥5 Gagal)') }}
            </button>
        </form>
    </div>
</div>

{{-- Ringkasan Global 4 KPI Keamanan --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between gap-2">
                <div class="overflow-hidden">
                    <small class="text-secondary fw-semibold d-block text-truncate">{{ __('Aturan Firewall Aktif') }}</small>
                    <div class="fs-4 fw-bold text-primary mt-0.5">{{ number_format($activeRulesCount) }}</div>
                    <small class="text-muted" style="font-size: 0.72rem;">{{ __(':hits blokir tertahan', ['hits' => number_format($totalBlockedHits)]) }}</small>
                </div>
                <div class="geo-stat-icon bg-primary-subtle text-primary">
                    <i class="fas fa-shield-virus"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between gap-2">
                <div class="overflow-hidden">
                    <small class="text-secondary fw-semibold d-block text-truncate">{{ __('Login Gagal (24j)') }}</small>
                    <div class="fs-4 fw-bold text-danger mt-0.5">{{ number_format($failedLogins24h) }}</div>
                    <small class="text-muted" style="font-size: 0.72rem;">{{ __('Rasio gagal :rate%', ['rate' => $failRate24h]) }} · {{ $successLogins24h }} {{ __('sukses') }}</small>
                </div>
                <div class="geo-stat-icon bg-danger-subtle text-danger">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between gap-2">
                <div class="overflow-hidden">
                    <small class="text-secondary fw-semibold d-block text-truncate">{{ __('Sesi Aktif (24j)') }}</small>
                    <div class="fs-4 fw-bold text-body mt-0.5">{{ number_format($activeSessionsCount) }}</div>
                    <small class="text-muted" style="font-size: 0.72rem;">{{ __('Perangkat online terpantau') }}</small>
                </div>
                <div class="geo-stat-icon bg-info-subtle text-info">
                    <i class="fas fa-laptop-medical"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between gap-2">
                <div class="overflow-hidden">
                    <small class="text-secondary fw-semibold d-block text-truncate">{{ __('Adopsi 2FA & Suspended') }}</small>
                    <div class="fs-4 fw-bold text-success mt-0.5">{{ $twoFactorAdoptionRate }}%</div>
                    <small class="text-muted" style="font-size: 0.72rem;">{{ __(':count akun 2FA', ['count' => $twoFactorUsersCount]) }} · <span class="text-warning fw-semibold">{{ $suspendedUsersCount }} {{ __('suspended') }}</span></small>
                </div>
                <div class="geo-stat-icon bg-success-subtle text-success">
                    <i class="fas fa-user-lock"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Navigasi 6 Tab Fokus (Material You Scrollable Pill Bar) --}}
<div class="sec-hub-tabs-scroll mb-4">
    <ul class="nav sec-hub-tabbar" id="securityHubTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'peta' ? 'active' : '' }}"
                    id="sec-tab-peta"
                    data-bs-toggle="tab"
                    data-bs-target="#sec-pane-peta"
                    data-tab-slug="peta"
                    type="button"
                    role="tab"
                    aria-controls="sec-pane-peta"
                    aria-selected="{{ $activeTab === 'peta' ? 'true' : 'false' }}">
                <i class="fas fa-map-location-dot"></i>
                <span>{{ __('Peta Sebaran') }}</span>
                @if(($geoMapSummary['mapped_locations'] ?? 0) > 0)
                    <span class="sec-tab-badge bg-primary-subtle text-primary">{{ $geoMapSummary['mapped_locations'] }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'analitik' ? 'active' : '' }}"
                    id="sec-tab-analitik"
                    data-bs-toggle="tab"
                    data-bs-target="#sec-pane-analitik"
                    data-tab-slug="analitik"
                    type="button"
                    role="tab"
                    aria-controls="sec-pane-analitik"
                    aria-selected="{{ $activeTab === 'analitik' ? 'true' : 'false' }}">
                <i class="fas fa-chart-line"></i>
                <span>{{ __('Analitik Login') }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'riwayat' ? 'active' : '' }}"
                    id="sec-tab-riwayat"
                    data-bs-toggle="tab"
                    data-bs-target="#sec-pane-riwayat"
                    data-tab-slug="riwayat"
                    type="button"
                    role="tab"
                    aria-controls="sec-pane-riwayat"
                    aria-selected="{{ $activeTab === 'riwayat' ? 'true' : 'false' }}">
                <i class="fas fa-right-to-bracket"></i>
                <span>{{ __('Riwayat Login & Token') }}</span>
                @if(($tgPendingTokens ?? 0) > 0)
                    <span class="sec-tab-badge bg-warning text-dark">{{ $tgPendingTokens }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'firewall' ? 'active' : '' }}"
                    id="sec-tab-firewall"
                    data-bs-toggle="tab"
                    data-bs-target="#sec-pane-firewall"
                    data-tab-slug="firewall"
                    type="button"
                    role="tab"
                    aria-controls="sec-pane-firewall"
                    aria-selected="{{ $activeTab === 'firewall' ? 'true' : 'false' }}">
                <i class="fas fa-shield-virus"></i>
                <span>{{ __('Firewall & Blokir') }}</span>
                <span class="sec-tab-badge bg-danger-subtle text-danger">{{ $activeRulesCount }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'ancaman' ? 'active' : '' }}"
                    id="sec-tab-ancaman"
                    data-bs-toggle="tab"
                    data-bs-target="#sec-pane-ancaman"
                    data-tab-slug="ancaman"
                    type="button"
                    role="tab"
                    aria-controls="sec-pane-ancaman"
                    aria-selected="{{ $activeTab === 'ancaman' ? 'true' : 'false' }}">
                <i class="fas fa-crosshairs"></i>
                <span>{{ __('Intelijen Ancaman') }}</span>
                @if($topSuspiciousIps->count() > 0)
                    <span class="sec-tab-badge bg-warning-subtle text-warning-emphasis">{{ $topSuspiciousIps->count() }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'sesi-audit' ? 'active' : '' }}"
                    id="sec-tab-sesi-audit"
                    data-bs-toggle="tab"
                    data-bs-target="#sec-pane-sesi-audit"
                    data-tab-slug="sesi-audit"
                    type="button"
                    role="tab"
                    aria-controls="sec-pane-sesi-audit"
                    aria-selected="{{ $activeTab === 'sesi-audit' ? 'true' : 'false' }}">
                <i class="fas fa-laptop-medical"></i>
                <span>{{ __('Sesi, Postur & Audit') }}</span>
                @if($activeSessionsCount > 0)
                    <span class="sec-tab-badge bg-info-subtle text-info">{{ $activeSessionsCount }}</span>
                @endif
            </button>
        </li>
    </ul>
</div>

{{-- Konten 6 Tab --}}
<div class="tab-content" id="securityHubTabsContent">
    <div class="tab-pane fade {{ $activeTab === 'peta' ? 'show active' : '' }}" id="sec-pane-peta" role="tabpanel" aria-labelledby="sec-tab-peta" tabindex="0">
        @include('admin.security.partials.tab-map')
    </div>
    <div class="tab-pane fade {{ $activeTab === 'analitik' ? 'show active' : '' }}" id="sec-pane-analitik" role="tabpanel" aria-labelledby="sec-tab-analitik" tabindex="0">
        @include('admin.security.partials.tab-analytics')
    </div>
    <div class="tab-pane fade {{ $activeTab === 'riwayat' ? 'show active' : '' }}" id="sec-pane-riwayat" role="tabpanel" aria-labelledby="sec-tab-riwayat" tabindex="0">
        @include('admin.security.partials.tab-login-logs')
    </div>
    <div class="tab-pane fade {{ $activeTab === 'firewall' ? 'show active' : '' }}" id="sec-pane-firewall" role="tabpanel" aria-labelledby="sec-tab-firewall" tabindex="0">
        @include('admin.security.partials.tab-firewall')
    </div>
    <div class="tab-pane fade {{ $activeTab === 'ancaman' ? 'show active' : '' }}" id="sec-pane-ancaman" role="tabpanel" aria-labelledby="sec-tab-ancaman" tabindex="0">
        @include('admin.security.partials.tab-threats')
    </div>
    <div class="tab-pane fade {{ $activeTab === 'sesi-audit' ? 'show active' : '' }}" id="sec-pane-sesi-audit" role="tabpanel" aria-labelledby="sec-tab-sesi-audit" tabindex="0">
        @include('admin.security.partials.tab-sessions-posture')
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openUnifiedBlockModal(ip, deviceFp, deviceId, userId, username) {
        Swal.fire({
            title: '{{ __("Kelola Pemblokiran Akses") }}',
            html: `
                <div class="text-start fs-6 p-2">
                    <p class="text-muted small mb-3">{{ __("Centang opsi pemblokiran yang ingin diterapkan dan tentukan durasinya:") }}</p>

                    ${ip ? `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_block_ip" checked>
                        <label class="form-check-label fw-bold" for="swal_block_ip">
                            <i class="fas fa-network-wired text-danger me-1"></i> {{ __("Blokir IP Address") }} (${ip})
                        </label>
                    </div>
                    ` : ''}

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_block_device" checked>
                        <label class="form-check-label fw-bold" for="swal_block_device">
                            <i class="fas fa-laptop text-dark me-1"></i> {{ __("Blokir Perangkat (Fingerprint & Cookie)") }}
                        </label>
                        <div class="text-muted small ms-4">{{ __("Perangkat tetap diblokir meskipun pengguna berganti IP publik / VPN.") }}</div>
                    </div>

                    ${userId ? `
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="swal_suspend_account">
                        <label class="form-check-label fw-bold text-danger" for="swal_suspend_account">
                            <i class="fas fa-user-slash me-1"></i> {{ __("Tangguhkan Akun User") }} (@${username})
                        </label>
                    </div>
                    ` : ''}

                    <div class="mt-3">
                        <label class="form-label fw-bold small text-muted mb-1">{{ __("Durasi Pemblokiran") }}</label>
                        <select id="swal_duration" class="form-select">
                            <option value="1">1 {{ __("Hari") }}</option>
                            <option value="7">7 {{ __("Hari") }}</option>
                            <option value="30" selected>30 {{ __("Hari") }}</option>
                            <option value="365">1 {{ __("Tahun (365 Hari)") }}</option>
                        </select>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-ban me-1"></i> {{ __("Terapkan Pemblokiran") }}',
            cancelButtonText: '{{ __("Batal") }}',
            preConfirm: () => {
                const blockIp = document.getElementById('swal_block_ip') ? document.getElementById('swal_block_ip').checked : false;
                const blockDevice = document.getElementById('swal_block_device') ? document.getElementById('swal_block_device').checked : false;
                const suspendAccount = document.getElementById('swal_suspend_account') ? document.getElementById('swal_suspend_account').checked : false;
                const duration = document.getElementById('swal_duration').value;

                if (!blockIp && !blockDevice && !suspendAccount) {
                    Swal.showValidationMessage('{{ __("Pilih minimal satu opsi yang ingin diblokir!") }}');
                    return false;
                }

                return { blockIp, blockDevice, suspendAccount, duration };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("admin.logins.unified-block") }}';

                const addHidden = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                };

                addHidden('_token', '{{ csrf_token() }}');
                if (ip) addHidden('ip_address', ip);
                if (deviceFp) addHidden('device_fingerprint', deviceFp);
                if (deviceId) addHidden('device_id', deviceId);
                if (userId) addHidden('user_id', userId);
                addHidden('block_ip', result.value.blockIp ? '1' : '0');
                addHidden('block_device', result.value.blockDevice ? '1' : '0');
                addHidden('suspend_account', result.value.suspendAccount ? '1' : '0');
                addHidden('duration', result.value.duration);

                document.body.appendChild(form);
                const loader = document.getElementById('pageLoader');
                if (loader) loader.classList.remove('fade-out');
                form.submit();
            }
        });
    }

    function openUnifiedUnblockModal(ip, deviceFp, deviceId, userId, username, isIpBlocked, isDeviceBlocked, isAccountSuspended) {
        Swal.fire({
            title: '{{ __("Buka Pemblokiran (Unblock)") }}',
            html: `
                <div class="text-start fs-6 p-2">
                    <p class="text-muted small mb-3">{{ __("Centang opsi pemblokiran yang ingin dibuka:") }}</p>

                    ${ip ? `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_unblock_ip" ${isIpBlocked ? 'checked' : ''}>
                        <label class="form-check-label fw-bold" for="swal_unblock_ip">
                            <i class="fas fa-network-wired text-success me-1"></i> {{ __("Buka Blokir IP Address") }} (${ip})
                        </label>
                    </div>
                    ` : ''}

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_unblock_device" ${isDeviceBlocked ? 'checked' : ''}>
                        <label class="form-check-label fw-bold" for="swal_unblock_device">
                            <i class="fas fa-laptop text-success me-1"></i> {{ __("Buka Blokir Perangkat (Fingerprint & Cookie)") }}
                        </label>
                    </div>

                    ${userId ? `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_unsuspend_account" ${isAccountSuspended ? 'checked' : ''}>
                        <label class="form-check-label fw-bold text-success" for="swal_unsuspend_account">
                            <i class="fas fa-user-check me-1"></i> {{ __("Aktifkan Kembali Akun User") }} (@${username})
                        </label>
                    </div>
                    ` : ''}
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-unlock me-1"></i> {{ __("Proses Buka Blokir") }}',
            cancelButtonText: '{{ __("Batal") }}',
            preConfirm: () => {
                const unblockIp = document.getElementById('swal_unblock_ip') ? document.getElementById('swal_unblock_ip').checked : false;
                const unblockDevice = document.getElementById('swal_unblock_device') ? document.getElementById('swal_unblock_device').checked : false;
                const unsuspendAccount = document.getElementById('swal_unsuspend_account') ? document.getElementById('swal_unsuspend_account').checked : false;

                if (!unblockIp && !unblockDevice && !unsuspendAccount) {
                    Swal.showValidationMessage('{{ __("Pilih minimal satu opsi yang ingin dibuka!") }}');
                    return false;
                }

                return { unblockIp, unblockDevice, unsuspendAccount };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("admin.logins.unified-unblock") }}';

                const addHidden = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                };

                addHidden('_token', '{{ csrf_token() }}');
                if (ip) addHidden('ip_address', ip);
                if (deviceFp) addHidden('device_fingerprint', deviceFp);
                if (deviceId) addHidden('device_id', deviceId);
                if (userId) addHidden('user_id', userId);
                addHidden('unblock_ip', result.value.unblockIp ? '1' : '0');
                addHidden('unblock_device', result.value.unblockDevice ? '1' : '0');
                addHidden('unsuspend_account', result.value.unsuspendAccount ? '1' : '0');

                document.body.appendChild(form);
                const loader = document.getElementById('pageLoader');
                if (loader) loader.classList.remove('fade-out');
                form.submit();
            }
        });
    }
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const initialActiveTab = @json($activeTab);

    // === 1. Lazy & Resize-Safe Visitor/Login Geolocation Map (Google Maps Basemap) ===
    const geoMapContainer = document.getElementById('visitorGeoMap');
    const geoPoints = @json($geoMapPoints ?? []);
    const loginsBaseUrl = @json(route('admin.logins.index'));
    const mapLang = @json(app()->getLocale() === 'en' ? 'en' : 'id');

    let geoMapInstance = null;
    let initialMapBounds = [];

    function escapeGeoHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initVisitorGeoMapIfNeeded() {
        if (!geoMapContainer || typeof L === 'undefined') return;
        if (geoMapInstance) {
            setTimeout(() => geoMapInstance.invalidateSize(), 60);
            return;
        }

        const defaultCenter = [-2.5, 118.0];
        const geoMap = L.map('visitorGeoMap', {
            center: defaultCenter,
            zoom: 4,
            minZoom: 2,
            maxZoom: 18,
            scrollWheelZoom: false,
            worldCopyJump: true
        });
        geoMapInstance = geoMap;
        window.__visitorGeoMap = geoMap;

        const googleBasemaps = {
            roadmap: {
                url: `https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}&hl=${mapLang}`,
                attribution: '&copy; Google Maps'
            },
            hybrid: {
                url: `https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}&hl=${mapLang}`,
                attribution: '&copy; Google Maps Satellite'
            }
        };

        let activeTileLayer = L.tileLayer(googleBasemaps.roadmap.url, {
            attribution: googleBasemaps.roadmap.attribution,
            subdomains: ['0', '1', '2', '3'],
            maxZoom: 20
        }).addTo(geoMap);

        const markersLayer = L.layerGroup().addTo(geoMap);
        const markerByCoords = {};
        let currentLayerFilter = 'all';

        function setBasemap(mode) {
            const spec = googleBasemaps[mode] || googleBasemaps.roadmap;
            if (activeTileLayer) {
                geoMap.removeLayer(activeTileLayer);
            }
            activeTileLayer = L.tileLayer(spec.url, {
                attribution: spec.attribution,
                subdomains: ['0', '1', '2', '3'],
                maxZoom: 20
            }).addTo(geoMap);

            geoMapContainer.classList.toggle('geo-basemap-roadmap', mode === 'roadmap');
        }

        function getPointMetricForLayer(p, layer) {
            if (layer === 'visitors') return p.visitors_count || 0;
            if (layer === 'login_success') return p.login_success || 0;
            if (layer === 'login_failed') return p.login_failed || 0;
            return p.total_activity || 0;
        }

        function getPointColor(p, layer) {
            if (layer === 'login_failed') return '#d50000';
            if (layer === 'login_success') return '#0b8043';
            if (layer === 'visitors') return '#0b57d0';
            if (p.login_failed > 0 && p.login_failed >= p.login_success) return '#d50000';
            if (p.login_success > 0 && p.visitors_count === 0) return '#0b8043';
            return '#0b57d0';
        }

        function renderGeoMarkers(layer) {
            markersLayer.clearLayers();
            Object.keys(markerByCoords).forEach(k => delete markerByCoords[k]);

            const visibleBounds = [];
            geoPoints.forEach((p, idx) => {
                const metric = getPointMetricForLayer(p, layer);
                if (metric <= 0) return;

                const color = getPointColor(p, layer);
                const radius = Math.min(26, Math.max(7, 6 + Math.sqrt(metric) * 3.2));

                const marker = L.circleMarker([p.lat, p.lng], {
                    radius: radius,
                    fillColor: color,
                    color: '#ffffff',
                    weight: 2,
                    opacity: 0.95,
                    fillOpacity: 0.82
                });

                const filterUrl = loginsBaseUrl + '?tab=riwayat&search=' + encodeURIComponent(p.filter_query || p.city || p.country || '');
                const popupHtml = `
                    <div style="min-width: 220px; font-size: 0.8rem;">
                        <div class="fw-bold mb-2 d-flex align-items-center justify-content-between gap-2 pe-3">
                            <span>${escapeGeoHtml(p.label)}</span>
                            <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size: 0.66rem;">${escapeGeoHtml(p.country_code)}</span>
                        </div>
                        <div class="d-flex flex-column gap-1 mb-3" style="font-size: 0.77rem;">
                            <div class="d-flex justify-content-between gap-3">
                                <span class="text-muted">{{ __('Pengunjung Website') }}:</span>
                                <strong>${Number(p.visitors_count || 0)} (${Number(p.visitor_hits || 0)} hits)</strong>
                            </div>
                            <div class="d-flex justify-content-between gap-3">
                                <span class="text-muted">{{ __('Login Berhasil') }}:</span>
                                <strong class="text-success">${Number(p.login_success || 0)}</strong>
                            </div>
                            <div class="d-flex justify-content-between gap-3">
                                <span class="text-muted">{{ __('Login Gagal') }}:</span>
                                <strong class="text-danger">${Number(p.login_failed || 0)}</strong>
                            </div>
                            <div class="d-flex justify-content-between gap-3">
                                <span class="text-muted">{{ __('IP Unik') }}:</span>
                                <strong>${Number(p.unique_ips || 0)}</strong>
                            </div>
                        </div>
                        <a href="${escapeGeoHtml(filterUrl)}" class="btn btn-sm btn-primary rounded-pill w-100 py-1 fw-semibold text-white text-decoration-none" style="font-size: 0.74rem;">
                            <i class="fas fa-filter me-1"></i>{{ __('Filter Log Lokasi Ini') }}
                        </a>
                    </div>
                `;

                marker.bindPopup(popupHtml);
                marker.addTo(markersLayer);
                markerByCoords[idx] = marker;
                visibleBounds.push([p.lat, p.lng]);
            });

            return visibleBounds;
        }

        initialMapBounds = renderGeoMarkers('all');
        setTimeout(() => {
            geoMap.invalidateSize();
            if (initialMapBounds.length === 1) {
                geoMap.setView(initialMapBounds[0], 7);
            } else if (initialMapBounds.length > 1) {
                geoMap.fitBounds(initialMapBounds, { padding: [36, 36], maxZoom: 7 });
            }
        }, 80);

        document.querySelectorAll('.geo-layer-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.geo-layer-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentLayerFilter = this.getAttribute('data-layer') || 'all';
                renderGeoMarkers(currentLayerFilter);
            });
        });

        document.querySelectorAll('.geo-basemap-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.geo-basemap-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const mode = this.getAttribute('data-basemap') || 'roadmap';
                setBasemap(mode);
            });
        });

        const btnFocusId = document.getElementById('btnGeoFocusIndonesia');
        if (btnFocusId) {
            btnFocusId.addEventListener('click', () => geoMap.flyTo([-2.5, 118.0], 5, { duration: 0.7 }));
        }
        const btnFocusWorld = document.getElementById('btnGeoFocusWorld');
        if (btnFocusWorld) {
            btnFocusWorld.addEventListener('click', () => geoMap.flyTo([15.0, 20.0], 2, { duration: 0.7 }));
        }

        document.querySelectorAll('.geo-location-row').forEach(row => {
            row.addEventListener('click', function () {
                const lat = parseFloat(this.getAttribute('data-lat'));
                const lng = parseFloat(this.getAttribute('data-lng'));
                const idx = parseInt(this.getAttribute('data-geo-index'), 10);
                if (!isNaN(lat) && !isNaN(lng)) {
                    geoMap.flyTo([lat, lng], 8, { duration: 0.75 });
                    if (markerByCoords[idx]) {
                        setTimeout(() => markerByCoords[idx].openPopup(), 450);
                    }
                }
            });
        });
    }

    // === 2. Lazy & Resize-Safe ApexCharts for Login Analytics Tab ===
    let analyticsChartsInitialized = false;

    function initSecurityAnalyticsChartsIfNeeded() {
        if (typeof ApexCharts === 'undefined') return;
        if (analyticsChartsInitialized) {
            setTimeout(() => window.dispatchEvent(new Event('resize')), 60);
            return;
        }
        analyticsChartsInitialized = true;

        const trendEl = document.querySelector("#loginTrendChart");
        if (trendEl) {
            new ApexCharts(trendEl, {
                series: [
                    { name: "{{ __('Login Berhasil') }}", data: @json($loginSuccessTrend ?? []) },
                    { name: "{{ __('Login Gagal') }}", data: @json($loginFailedTrend ?? []) }
                ],
                chart: {
                    type: 'area',
                    height: 280,
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: 'transparent',
                    fontFamily: 'inherit'
                },
                theme: { mode: isDark ? 'dark' : 'light' },
                colors: [isDark ? '#81c995' : '#0b8043', isDark ? '#f28b82' : '#d50000'],
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: [2, 2] },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.24, opacityTo: 0.02, stops: [20, 100] }
                },
                grid: { borderColor: isDark ? '#3c4043' : '#dadce0', strokeDashArray: 3 },
                xaxis: { categories: @json($loginTrendLabels ?? []), axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { formatter: (val) => Math.round(val) }, min: 0 },
                legend: { position: 'top', horizontalAlign: 'right' },
                tooltip: { theme: isDark ? 'dark' : 'light' }
            }).render();
        }

        const hourlyEl = document.querySelector("#loginHourlyChart");
        if (hourlyEl) {
            new ApexCharts(hourlyEl, {
                series: [
                    { name: "{{ __('Login Berhasil') }}", data: @json($hourlySuccessSlotData ?? []) },
                    { name: "{{ __('Login Gagal') }}", data: @json($hourlyFailedSlotData ?? []) }
                ],
                chart: {
                    type: 'bar',
                    height: 280,
                    stacked: true,
                    toolbar: { show: false },
                    background: 'transparent',
                    fontFamily: 'inherit'
                },
                theme: { mode: isDark ? 'dark' : 'light' },
                colors: [isDark ? '#81c995' : '#0b8043', isDark ? '#f28b82' : '#d50000'],
                plotOptions: { bar: { horizontal: false, borderRadius: 4, columnWidth: '52%' } },
                grid: { borderColor: isDark ? '#3c4043' : '#dadce0', strokeDashArray: 3 },
                dataLabels: { enabled: false },
                xaxis: { categories: @json($hourlySlotLabels ?? []), axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { formatter: (val) => Math.round(val) }, min: 0 },
                legend: { position: 'top', horizontalAlign: 'right' },
                tooltip: { theme: isDark ? 'dark' : 'light' }
            }).render();
        }

        @if(!empty($deviceData) && array_sum($deviceData) > 0)
        const deviceEl = document.querySelector("#deviceDistChart");
        if (deviceEl) {
            new ApexCharts(deviceEl, {
                series: @json($deviceData ?? []),
                labels: @json($deviceLabels ?? []),
                chart: { type: 'donut', height: 260, background: 'transparent', fontFamily: 'inherit' },
                theme: { mode: isDark ? 'dark' : 'light' },
                colors: ['#3367d6', '#0b8043', '#dc6d00', '#12b5cb', '#5f6368'],
                stroke: { width: 2, colors: [isDark ? '#28292c' : '#ffffff'] },
                legend: { position: 'bottom', fontSize: '12px' },
                dataLabels: { enabled: true, formatter: (val) => Math.round(val) + "%" },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: "{{ __('Total Akses') }}",
                                    formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                                }
                            }
                        }
                    }
                },
                tooltip: { theme: isDark ? 'dark' : 'light' }
            }).render();
        }
        @endif

        @if(!empty($browserData) && array_sum($browserData) > 0)
        const browserEl = document.querySelector("#browserDistChart");
        if (browserEl) {
            new ApexCharts(browserEl, {
                series: [{ name: "{{ __('Jumlah Akses') }}", data: @json($browserData ?? []) }],
                chart: { type: 'bar', height: 260, toolbar: { show: false }, background: 'transparent', fontFamily: 'inherit' },
                theme: { mode: isDark ? 'dark' : 'light' },
                plotOptions: { bar: { borderRadius: 4, horizontal: true, distributed: true, barHeight: '55%' } },
                colors: ['#3367d6', '#0b8043', '#dc6d00', '#9334e6', '#12b5cb'],
                grid: { borderColor: isDark ? '#3c4043' : '#dadce0', strokeDashArray: 3 },
                dataLabels: { enabled: true, formatter: (val) => val },
                xaxis: { categories: @json($browserLabels ?? []), labels: { formatter: (val) => Math.round(val) } },
                legend: { show: false },
                tooltip: { theme: isDark ? 'dark' : 'light' }
            }).render();
        }
        @endif
    }

    // Initialize visual widgets for whichever tab is active on load
    if (initialActiveTab === 'peta') {
        initVisitorGeoMapIfNeeded();
    } else if (initialActiveTab === 'analitik') {
        initSecurityAnalyticsChartsIfNeeded();
    }

    // Bind Bootstrap tab switch events: URL sync + lazy init / resize fix
    document.querySelectorAll('#securityHubTabs button[data-bs-toggle="tab"]').forEach(tabBtn => {
        tabBtn.addEventListener('shown.bs.tab', function (event) {
            const slug = event.target.getAttribute('data-tab-slug');
            if (slug) {
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', slug);
                    window.history.replaceState(null, '', url.toString());
                } catch (e) {}

                // Also sync header quick-action hidden active_tab input
                const headerTabInput = document.querySelector('form[action*="auto-block"] input[name="active_tab"]');
                if (headerTabInput) headerTabInput.value = slug;
            }

            if (slug === 'peta') {
                initVisitorGeoMapIfNeeded();
            } else if (slug === 'analitik') {
                initSecurityAnalyticsChartsIfNeeded();
            }
        });
    });
});
</script>
@endpush
