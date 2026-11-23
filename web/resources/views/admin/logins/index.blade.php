@extends('layouts.app')

@section('title', __('Notifikasi Login Web'))
@section('page_subtitle', __('IAM & Security Command Center'))

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<style>
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

    /* M3 Segmented Pill Controls for Map Header */
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

    /* Mini Stat Strip Cards */
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

    /* Right Side Top Locations Panel */
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

    /* M3 Leaflet Zoom Controls & Popup */
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
    @if(request()->filled('search') || request()->filled('status') || request()->filled('device_type'))
        <a href="{{ route('admin.logins.index') }}" class="cm-btn-outline">
            <i class="fas fa-undo"></i>
            <span>{{ __('Reset Filter') }}</span>
        </a>
    @endif
@endsection

@section('content')
{{-- KPI Metric Cards Keamanan (Google Cloud Console Outlined Surfaces) --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="m3-metric-icon bg-primary-subtle text-primary">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem; letter-spacing: 0.04em;">{{ __('Total Percobaan') }}</span>
                    <h4 class="fw-bold mb-0 text-body">{{ number_format($totalLoginAttempts ?? 0, 0, ',', '.') }}</h4>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ __('Hari ini:') }} <span class="fw-semibold text-primary">{{ number_format($todayLoginCount ?? 0, 0, ',', '.') }}</span> · <span class="fw-semibold">{{ number_format($uniqueIpCount ?? 0, 0, ',', '.') }}</span> IP</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="m3-metric-icon bg-success-subtle text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem; letter-spacing: 0.04em;">{{ __('Login Berhasil') }}</span>
                    <h4 class="fw-bold mb-0 text-success">{{ number_format($successLoginCount ?? 0, 0, ',', '.') }}</h4>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ __('Token TG:') }} <span class="fw-semibold text-success">{{ $tgUsedTokens ?? 0 }}/{{ $tgTotalTokens ?? 0 }}</span> ({{ $tgConversionRate ?? 0 }}%)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="m3-metric-icon bg-danger-subtle text-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem; letter-spacing: 0.04em;">{{ __('Login Gagal / Ditolak') }}</span>
                    <h4 class="fw-bold mb-0 text-danger">{{ number_format($failedLoginCount ?? 0, 0, ',', '.') }}</h4>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ __('Hari ini:') }} <span class="fw-semibold text-danger">{{ $todayFailedCount ?? 0 }}</span> · <span class="fw-semibold">{{ $uniqueFailedIpCount ?? 0 }}</span> {{ __('IP Gagal') }}</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="m3-metric-icon bg-info-subtle text-info">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem; letter-spacing: 0.04em;">{{ __('Tingkat Keberhasilan') }}</span>
                    <h4 class="fw-bold mb-0 text-body">{{ $successRate ?? 0 }}%</h4>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ __('Skor Keamanan:') }} <span class="fw-bold text-{{ $securityBadgeColor ?? 'success' }}">{{ $securityScore ?? 100 }}/100</span></small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Panel Hasil Analisis Keamanan & Deteksi Anomali Login --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-body"><i class="fas fa-user-shield text-primary me-2"></i>{{ __('Hasil Analisis Keamanan & Evaluasi Risiko Login') }}</h5>
                    <span class="text-muted small">{{ __('Diagnosis otomatis pola autentikasi, potensi serangan brute-force, dan rekomendasi mitigasi') }}</span>
                </div>
                <span class="badge bg-{{ $securityBadgeColor ?? 'success' }}-subtle text-{{ $securityBadgeColor ?? 'success' }} rounded-pill px-3 py-1.5 fw-semibold">
                    <i class="fas fa-heartbeat me-1"></i>{{ $securityStatus ?? __('Aman & Terkendali') }} ({{ $securityScore ?? 100 }}/100)
                </span>
            </div>
            <div class="card-body px-4 pt-3 pb-3">
                <div class="d-flex flex-column gap-3">
                    @foreach(($loginAnalysisInsights ?? []) as $insight)
                        <div class="d-flex align-items-start gap-3 p-3 m3-tonal-item border-start border-4 border-{{ $insight['color'] ?? 'primary' }}">
                            <div class="rounded-3 bg-{{ $insight['color'] ?? 'primary' }}-subtle text-{{ $insight['color'] ?? 'primary' }} d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width: 36px; height: 36px;">
                                <i class="fas {{ $insight['icon'] ?? 'fa-info-circle' }}"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1 text-body" style="font-size: 0.9rem;">{{ $insight['title'] }}</h6>
                                <p class="text-muted small mb-0" style="line-height: 1.45;">{{ $insight['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-crosshairs text-danger me-2"></i>{{ __('Radar IP Gagal & Akun Target Serangan') }}</h5>
                <span class="text-muted small">{{ __('Sumber IP gagal terbanyak dan akun yang sering dicoba') }}</span>
            </div>
            <div class="card-body px-4 pt-3 pb-3">
                <h6 class="text-uppercase text-muted fw-bold mb-2" style="font-size: 0.7rem; letter-spacing: 0.04em;">{{ __('Top IP Gagal Login') }}</h6>
                @if(isset($topFailedIps) && count($topFailedIps) > 0)
                    <div class="d-flex flex-column gap-2 mb-3">
                        @foreach($topFailedIps as $badIp)
                            <div class="p-2.5 m3-tonal-item d-flex align-items-center justify-content-between gap-2">
                                <div class="overflow-hidden">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold font-monospace text-body" style="font-size: 0.85rem;">{{ $badIp->ip_address }}</span>
                                        @if($badIp->is_blocked)
                                            <span class="badge bg-danger text-white rounded-pill" style="font-size: 0.65rem;">{{ __('Diblokir') }}</span>
                                        @else
                                            <span class="badge bg-{{ $badIp->risk_badge === 'Tinggi' ? 'danger' : ($badIp->risk_badge === 'Sedang' ? 'warning' : 'secondary') }}-subtle text-{{ $badIp->risk_badge === 'Tinggi' ? 'danger' : ($badIp->risk_badge === 'Sedang' ? 'warning' : 'secondary') }} rounded-pill" style="font-size: 0.65rem;">{{ __('Risiko') }} {{ $badIp->risk_badge }}</span>
                                        @endif
                                    </div>
                                    <small class="text-muted d-block text-truncate" style="font-size: 0.7rem;">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $badIp->last_location ?: 'Unknown' }}
                                    </small>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1 fw-bold">{{ $badIp->failed_count }}x {{ __('gagal') }}</span>
                                    @if(!$badIp->is_blocked)
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2" style="font-size: 0.72rem;" onclick="openUnifiedBlockModal('{{ $badIp->ip_address }}', '', '', '', '')">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-2.5 m3-tonal-item text-muted small mb-3">
                        <i class="fas fa-check-circle text-success me-1"></i>{{ __('Belum ada IP dengan catatan gagal login.') }}
                    </div>
                @endif

                <h6 class="text-uppercase text-muted fw-bold mb-2" style="font-size: 0.7rem; letter-spacing: 0.04em;">{{ __('Akun / Username Paling Sering Dicoba Gagal') }}</h6>
                @if(isset($topTargetedAccounts) && count($topTargetedAccounts) > 0)
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($topTargetedAccounts as $targetAcc)
                            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3 py-1.5" style="font-size: 0.75rem;">
                                <i class="fas fa-user-lock text-warning me-1"></i>{{ Str::limit($targetAcc->username_or_email, 22) }}
                                <strong class="text-danger ms-1">({{ $targetAcc->failed_count }}x / {{ $targetAcc->unique_ips }} IP)</strong>
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="p-2.5 m3-tonal-item text-muted small">
                        <i class="fas fa-shield-alt text-secondary me-1"></i>{{ __('Tidak ada akun yang menjadi target kegagalan berulang.') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Peta Sebaran Lokasi Pengunjung Website & Percobaan Login (Google Maps Basemap + M3 Controls) --}}
<div class="card mb-4 overflow-hidden">
    <div class="card-header bg-transparent pt-4 pb-3 px-4">
        <div class="d-flex flex-column gap-3">
            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
                <div>
                    <h5 class="fw-bold mb-1 text-body">
                        <i class="fas fa-map-marked-alt text-primary me-2"></i>{{ __('Peta Sebaran Lokasi Pengunjung & Percobaan Login') }}
                    </h5>
                    <span class="text-muted small">
                        {{ __('Pemetaan geografis pengunjung website (30 hari terakhir) serta percobaan autentikasi berdasarkan lokasi IP') }}
                    </span>
                </div>

                <div class="geo-controls-wrap">
                    {{-- M3 Segmented Filter Layer --}}
                    <div class="geo-segmented-bar" role="group" aria-label="{{ __('Filter Layer Peta') }}">
                        <button type="button" class="geo-seg-btn geo-layer-btn active" data-layer="all">{{ __('Semua') }}</button>
                        <button type="button" class="geo-seg-btn geo-layer-btn" data-layer="visitors">{{ __('Pengunjung') }}</button>
                        <button type="button" class="geo-seg-btn geo-layer-btn" data-layer="login_success">{{ __('Login Sukses') }}</button>
                        <button type="button" class="geo-seg-btn geo-layer-btn" data-layer="login_failed">{{ __('Login Gagal') }}</button>
                    </div>

                    {{-- M3 Segmented Basemap Switch (Google Roadmap / Satellite) --}}
                    <div class="geo-segmented-bar" role="group" aria-label="{{ __('Gaya Peta') }}">
                        <button type="button" class="geo-seg-btn geo-basemap-btn active" data-basemap="roadmap" title="{{ __('Tampilan Peta Jalan Google') }}">
                            <i class="fas fa-map"></i><span>{{ __('Peta') }}</span>
                        </button>
                        <button type="button" class="geo-seg-btn geo-basemap-btn" data-basemap="hybrid" title="{{ __('Tampilan Satelit Google') }}">
                            <i class="fas fa-satellite"></i><span>{{ __('Satelit') }}</span>
                        </button>
                    </div>

                    {{-- Quick Viewport Focus Buttons --}}
                    <div class="geo-focus-group" role="group" aria-label="{{ __('Fokus Wilayah Peta') }}">
                        <button type="button" class="geo-focus-btn" id="btnGeoFocusIndonesia" title="{{ __('Fokus ke Indonesia') }}">
                            <i class="fas fa-crosshairs text-primary"></i><span>{{ __('Indonesia') }}</span>
                        </button>
                        <button type="button" class="geo-focus-btn" id="btnGeoFocusWorld" title="{{ __('Tampilan Peta Dunia') }}">
                            <i class="fas fa-globe-asia text-secondary"></i><span>{{ __('Dunia') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body px-4 pt-3 pb-4">
        {{-- Mini Strip Statistik Geografis --}}
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="geo-stat-card">
                    <div class="overflow-hidden">
                        <div class="text-muted text-uppercase fw-bold text-truncate mb-1" style="font-size: 0.68rem; letter-spacing: 0.04em;">{{ __('Pengunjung Hari Ini') }}</div>
                        <div class="fw-bold text-body fs-5 lh-1">{{ number_format($geoMapSummary['visitors_today'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="geo-stat-icon bg-primary-subtle text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="geo-stat-card">
                    <div class="overflow-hidden">
                        <div class="text-muted text-uppercase fw-bold text-truncate mb-1" style="font-size: 0.68rem; letter-spacing: 0.04em;">{{ __('Pengunjung (30 Hari)') }}</div>
                        <div class="fw-bold text-body fs-5 lh-1">{{ number_format($geoMapSummary['visitors_30d'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="geo-stat-icon bg-info-subtle text-info">
                        <i class="fas fa-chart-area"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="geo-stat-card">
                    <div class="overflow-hidden">
                        <div class="text-muted text-uppercase fw-bold text-truncate mb-1" style="font-size: 0.68rem; letter-spacing: 0.04em;">{{ __('Titik Kota Terpetakan') }}</div>
                        <div class="fw-bold text-body fs-5 lh-1">{{ number_format($geoMapSummary['mapped_locations'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="geo-stat-icon bg-success-subtle text-success">
                        <i class="fas fa-map-pin"></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="geo-stat-card">
                    <div class="overflow-hidden">
                        <div class="text-muted text-uppercase fw-bold text-truncate mb-1" style="font-size: 0.68rem; letter-spacing: 0.04em;">{{ __('Negara Terdeteksi') }}</div>
                        <div class="fw-bold text-body fs-5 lh-1">{{ number_format($geoMapSummary['unique_countries'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="geo-stat-icon bg-warning-subtle text-warning-emphasis">
                        <i class="fas fa-flag"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 align-items-start">
            {{-- Kolom Kiri: Kanvas Peta Interaktif Google Maps --}}
            <div class="col-12 col-lg-8">
                <div class="position-relative">
                    <div id="visitorGeoMap" class="geo-basemap-roadmap" aria-label="{{ __('Peta Sebaran Lokasi Pengunjung & Percobaan Login') }}"></div>
                    @if(empty($geoMapPoints))
                        <div class="position-absolute top-50 start-50 translate-middle text-center p-4 rounded-4 shadow-sm" style="z-index: 400; background: var(--surface-container-lowest); border: 1px solid var(--outline-variant); max-width: 340px;">
                            <i class="fas fa-map-marker-alt text-primary fa-2x mb-2 opacity-75"></i>
                            <div class="fw-bold text-body small mb-1">{{ __('Belum Ada Koordinat Publik Tercatat') }}</div>
                            <div class="text-muted" style="font-size: 0.76rem;">{{ __('Kunjungan atau percobaan login dari IP publik akan otomatis dipetakan di sini.') }}</div>
                        </div>
                    @endif
                </div>
                <div class="geo-legend-bar">
                    <span class="geo-legend-item">
                        <span class="geo-legend-dot" style="background: #0b57d0;"></span>
                        <span>{{ __('Pengunjung Website') }}</span>
                    </span>
                    <span class="geo-legend-item">
                        <span class="geo-legend-dot" style="background: #0b8043;"></span>
                        <span>{{ __('Login Berhasil Dominan') }}</span>
                    </span>
                    <span class="geo-legend-item">
                        <span class="geo-legend-dot" style="background: #d50000;"></span>
                        <span>{{ __('Anomali / Login Gagal') }}</span>
                    </span>
                </div>
            </div>

            {{-- Kolom Kanan: Daftar Top Lokasi Teraktif --}}
            <div class="col-12 col-lg-4">
                <div class="geo-location-panel">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
                        <h6 class="text-uppercase text-muted fw-bold mb-0" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                            <i class="fas fa-ranking-star text-primary me-1"></i>{{ __('Top Lokasi Pengunjung & Login') }}
                        </h6>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1" style="font-size: 0.68rem;">{{ count($geoMapSummary['top_locations'] ?? []) }} {{ __('lokasi') }}</span>
                    </div>

                    @php
                        $topLocations = $geoMapSummary['top_locations'] ?? [];
                        $maxLocationActivity = !empty($topLocations) ? max(1, (int) ($topLocations[0]['total_activity'] ?? 1)) : 1;
                    @endphp

                    @if(!empty($topLocations))
                        <div class="geo-location-list">
                            @foreach($topLocations as $locIdx => $loc)
                                @php
                                    $pct = min(100, round((($loc['total_activity'] ?? 0) / $maxLocationActivity) * 100));
                                    $barColor = ($loc['risk_level'] ?? 'normal') === 'high' ? 'bg-danger' : (($loc['login_success'] ?? 0) > 0 && ($loc['visitors_count'] ?? 0) === 0 ? 'bg-success' : 'bg-primary');
                                @endphp
                                <div class="geo-location-row"
                                     data-geo-index="{{ $locIdx }}"
                                     data-lat="{{ $loc['lat'] }}"
                                     data-lng="{{ $loc['lng'] }}">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                                            <span class="badge bg-secondary-subtle text-secondary font-monospace flex-shrink-0" style="font-size: 0.66rem; padding: 3px 6px;">{{ $loc['country_code'] }}</span>
                                            <span class="fw-bold text-body text-truncate" style="font-size: 0.84rem;" title="{{ $loc['label'] }}">{{ $loc['label'] }}</span>
                                        </div>
                                        <span class="fw-bold text-primary font-monospace flex-shrink-0" style="font-size: 0.82rem;">{{ number_format($loc['total_activity'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="progress mb-2" style="height: 5px;">
                                        <div class="progress-bar {{ $barColor }}" role="progressbar" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 text-muted" style="font-size: 0.72rem;">
                                        <span><i class="fas fa-eye text-primary me-1"></i>{{ $loc['visitors_count'] }} {{ __('kunjungan') }}</span>
                                        <span><i class="fas fa-check text-success me-1"></i>{{ $loc['login_success'] }}</span>
                                        @if($loc['login_failed'] > 0)
                                            <span class="text-danger fw-semibold"><i class="fas fa-times me-1"></i>{{ $loc['login_failed'] }} {{ __('gagal') }}</span>
                                        @endif
                                        <span class="ms-auto fw-medium">{{ $loc['unique_ips'] }} IP</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="my-auto text-center py-4 text-muted">
                            <i class="fas fa-globe fa-2x mb-2 opacity-50"></i>
                            <p class="small mb-0">{{ __('Belum ada data sebaran lokasi publik yang tercatat.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Analytics Charts Row 1: Tren Harian & Pola Jam Akses --}}
<div class="row g-3 mb-4">
    <!-- Chart: Login Trend -->
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-line text-primary me-2"></i>{{ __('Tren Percobaan Login (7 Hari Terakhir)') }}</h5>
                <span class="text-muted small">{{ __('Perbandingan frekuensi login berhasil vs gagal dari hari ke hari') }}</span>
            </div>
            <div class="card-body px-3 pb-2 pt-2">
                <div id="loginTrendChart" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>

    <!-- Chart: Hourly Time Slot Distribution -->
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-body"><i class="fas fa-clock text-warning me-2"></i>{{ __('Pola Jam Akses & Serangan') }}</h5>
                    <span class="text-muted small">{{ __('Distribusi waktu login per 4 jam (Jam Puncak:') }} <strong>{{ $peakTimeWindow ?? '-' }}</strong>)</span>
                </div>
            </div>
            <div class="card-body px-3 pb-2 pt-2">
                <div id="loginHourlyChart" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>
</div>

{{-- Analytics Charts Row 2: Distribusi Perangkat & Browser --}}
<div class="row g-3 mb-4">
    <!-- Chart: Device Distribution -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-laptop text-info me-2"></i>{{ __('Distribusi Perangkat Pengguna') }}</h5>
                <span class="text-muted small">{{ __('Persentase platform perangkat yang mengakses sistem') }}</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center p-3">
                @if(!empty($deviceData) && array_sum($deviceData) > 0)
                    <div id="deviceDistChart" style="min-height: 260px; width: 100%;"></div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2 text-secondary opacity-50"></i>
                        <p class="small mb-0">{{ __('Belum ada data perangkat yang tercatat.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Chart: Browser Distribution -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-globe text-success me-2"></i>{{ __('Distribusi Peramban (Browser) Terpopuler') }}</h5>
                <span class="text-muted small">{{ __('Perbandingan browser dan klien yang digunakan saat login') }}</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center p-3">
                @if(!empty($browserData) && array_sum($browserData) > 0)
                    <div id="browserDistChart" style="min-height: 260px; width: 100%;"></div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-globe fa-2x mb-2 text-secondary opacity-50"></i>
                        <p class="small mb-0">{{ __('Belum ada data browser yang tercatat.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Filter Pencarian Log Login (Google Cloud Console Filter Bar Surface) --}}
<div class="card mb-4 overflow-hidden">
    <div class="sd-dashboard-filter-bar border-bottom-0 p-3">
        <form action="{{ route('admin.logins.index') }}" method="GET" class="row g-2 align-items-end w-100 m-0">
            <div class="col-lg-5 col-md-12">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Cari User / IP / Lokasi / Browser') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-filter text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="{{ __('Ketik username, email, IP address, atau lokasi...') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Status Autentikasi') }}</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>{{ __('Berhasil Login') }}</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ __('Gagal / Ditolak') }}</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Tipe Perangkat') }}</label>
                <select name="device_type" class="form-select form-select-sm">
                    <option value="">{{ __('Semua Perangkat') }}</option>
                    @foreach(($deviceLabels ?? []) as $dl)
                        <option value="{{ $dl }}" {{ request('device_type') === $dl ? 'selected' : '' }}>{{ $dl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-1 col-md-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100" title="{{ __('Terapkan Filter') }}"><i class="fas fa-search"></i></button>
                    @if(request()->filled('search') || request()->filled('status') || request()->filled('device_type'))
                        <a href="{{ route('admin.logins.index') }}" class="btn btn-sm btn-light px-2.5" title="{{ __('Reset Filter') }}"><i class="fas fa-undo"></i></a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <ul class="nav nav-pills gap-2 mb-0" id="loginTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill px-4 fw-medium" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-pane" type="button" role="tab" aria-selected="true">
                <i class="fas fa-shield-alt me-2"></i>{{ __('Semua Percobaan Web') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-4 fw-medium" id="tg-tab" data-bs-toggle="tab" data-bs-target="#tg-pane" type="button" role="tab" aria-selected="false">
                <i class="fab fa-telegram me-2"></i>{{ __('Login Telegram') }}
            </button>
        </li>
    </ul>

    <form action="{{ route('admin.logins.unblock-all-devices') }}" method="POST" class="d-inline" onclick="confirmAction(event, '{{ __('Reset seluruh pemblokiran IP dan Perangkat di sistem?') }}')">
        @csrf
        <button type="submit" class="btn btn-outline-warning btn-sm rounded-pill px-3 py-2 fw-medium">
            <i class="fas fa-sync-alt me-1"></i>{{ __('Reset Semua Pemblokiran') }}
        </button>
    </form>
</div>

<div class="tab-content" id="loginTabsContent">
    <div class="tab-pane fade show active" id="logs-pane" role="tabpanel" tabindex="0">
        <div class="card overflow-hidden">
            <div class="card-body p-0">
                @if($loginLogs->count() > 0)
                {{-- Desktop Table View --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">{{ __('Waktu') }}</th>
                                <th class="py-3">{{ __('Status') }}</th>
                                <th class="py-3">{{ __('User/Email') }}</th>
                                <th class="py-3">{{ __('IP & Lokasi') }}</th>
                                <th class="py-3">{{ __('Perangkat') }}</th>
                                <th class="py-3 text-end pe-4">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loginLogs as $log)
                            @php
                                $effectiveFp = $log->device_fingerprint;
                                $effectiveDevId = $log->device_id;
                                $isIpBlocked = \Illuminate\Support\Facades\Cache::has('blocked_ip:' . $log->ip_address);
                                $isDeviceBlocked = ($effectiveFp && \Illuminate\Support\Facades\Cache::has('blocked_device_fp:' . $effectiveFp))
                                    || ($effectiveDevId && \Illuminate\Support\Facades\Cache::has('blocked_device_id:' . $effectiveDevId));
                                $isAccountSuspended = ($log->user && $log->user->is_suspended);
                            @endphp
                            <tr class="{{ $isDeviceBlocked ? 'table-danger bg-danger bg-opacity-10' : '' }}">
                                <td class="px-4 text-secondary small font-monospace">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($log->is_successful)
                                        <span class="badge bg-success-subtle text-success px-3 py-1.5 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ __('Berhasil') }}</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger px-3 py-1.5 rounded-pill"><i class="fas fa-times-circle me-1"></i>{{ __('Gagal') }}</span>
                                    @endif
                                    @if($isDeviceBlocked)
                                        <div class="mt-1">
                                            <span class="badge bg-danger text-white rounded-pill px-2 py-1 small" title="{{ __('Perangkat ini diblokir') }}"><i class="fas fa-laptop me-1"></i>{{ __('Perangkat Diblokir') }}</span>
                                        </div>
                                    @elseif($isIpBlocked)
                                        <div class="mt-1">
                                            <span class="badge bg-danger text-white rounded-pill px-2 py-1 small" title="{{ __('IP ini diblokir') }}"><i class="fas fa-ban me-1"></i>{{ __('IP Diblokir') }}</span>
                                        </div>
                                    @elseif($isAccountSuspended)
                                        <div class="mt-1">
                                            <span class="badge bg-danger text-white rounded-pill px-2 py-1 small" title="{{ __('Akun ini ditangguhkan') }}"><i class="fas fa-user-slash me-1"></i>{{ __('Akun Ditangguhkan') }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="fw-bold text-body">
                                    {{ $log->username_or_email ?? '-' }}
                                    @if($isAccountSuspended)
                                        <div class="mt-1"><span class="badge bg-danger-subtle text-danger font-monospace" style="font-size: 0.65rem;"><i class="fas fa-user-slash me-1"></i>{{ __('Akun Ditangguhkan') }}</span></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-body font-monospace" style="font-size: 0.85rem;">
                                        {{ $log->ip_address }}
                                        @if($isIpBlocked)
                                            <span class="badge bg-danger text-white ms-1" style="font-size: 0.65rem;"><i class="fas fa-ban me-1"></i>{{ __('IP Diblokir') }}</span>
                                        @endif
                                    </div>
                                    <div class="text-secondary small"><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $log->location ?? 'Unknown' }}</div>
                                </td>
                                <td>
                                    <div class="text-body small fw-medium">{{ $log->device_type }}</div>
                                    <div class="text-secondary small" title="{{ $log->user_agent }}"><i class="fab me-1 {{ str_contains(strtolower($log->browser ?? ''), 'firefox') ? 'fa-firefox-browser text-warning' : (str_contains(strtolower($log->browser ?? ''), 'chrome') ? 'fa-chrome text-primary' : 'fa-globe text-secondary') }}"></i>{{ $log->browser ?? '-' }}</div>
                                    @if($effectiveFp)
                                        <div class="mt-1 d-flex align-items-center gap-1 flex-wrap">
                                            <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size: 0.65rem;" title="Fingerprint: {{ $effectiveFp }}"><i class="fas fa-fingerprint me-1"></i>{{ Str::limit($effectiveFp, 16) }}</span>
                                            @if($isDeviceBlocked)
                                                <span class="badge bg-danger text-white" style="font-size: 0.65rem;"><i class="fas fa-laptop me-1"></i>{{ __('Perangkat Diblokir') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-shield-alt me-1"></i>{{ __('Kelola Blokir') }}
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="z-index: 1050;">
                                            <li>
                                                <button type="button" class="dropdown-item text-danger py-2" onclick="openUnifiedBlockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}')">
                                                    <i class="fas fa-ban me-2 text-danger"></i>{{ __('Blokir Akses') }}
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item text-success py-2" onclick="openUnifiedUnblockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}', {{ \Illuminate\Support\Facades\Cache::has('blocked_ip:' . $log->ip_address) ? 'true' : 'false' }}, {{ $isDeviceBlocked ? 'true' : 'false' }}, {{ ($log->user && $log->user->is_suspended) ? 'true' : 'false' }})">
                                                    <i class="fas fa-unlock me-2 text-success"></i>{{ __('Buka Blokir (Unblock)') }}
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Collapsible Compact List (< 768px) --}}
                <div class="d-md-none p-2.5 m3-collapsible-list" id="standaloneLoginLogsAccordion">
                    @foreach($loginLogs as $log)
                    @php
                        $effectiveFp = $log->device_fingerprint;
                        $effectiveDevId = $log->device_id;
                        $isIpBlocked = \Illuminate\Support\Facades\Cache::has('blocked_ip:' . $log->ip_address);
                        $isDeviceBlocked = ($effectiveFp && \Illuminate\Support\Facades\Cache::has('blocked_device_fp:' . $effectiveFp))
                            || ($effectiveDevId && \Illuminate\Support\Facades\Cache::has('blocked_device_id:' . $effectiveDevId));
                        $isAccountSuspended = ($log->user && $log->user->is_suspended);
                    @endphp
                    <div class="m3-mobile-item-card m3-collapsible-card {{ $isDeviceBlocked ? 'border-danger' : '' }}">
                        <div class="m3-compact-row">
                            <button type="button"
                                    class="m3-compact-trigger"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#standaloneLoginLogCollapse-{{ $log->id }}"
                                    aria-expanded="false"
                                    aria-controls="standaloneLoginLogCollapse-{{ $log->id }}">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold text-body text-truncate" style="font-size: 0.84rem;">
                                        {{ $log->username_or_email ?? '-' }}
                                    </div>
                                    <div class="text-secondary text-truncate font-monospace" style="font-size: 0.72rem;">
                                        {{ $log->ip_address }} · {{ $log->created_at->format('d M, H:i') }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                                    @if($log->is_successful)
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Berhasil') }}</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Gagal') }}</span>
                                    @endif
                                    @if($isDeviceBlocked || $isIpBlocked || $isAccountSuspended)
                                        <span class="badge bg-danger text-white rounded-pill px-1.5 py-0.5" style="font-size: 0.6rem;"><i class="fas fa-ban"></i></span>
                                    @endif
                                    <i class="fas fa-chevron-down m3-compact-chevron"></i>
                                </div>
                            </button>
                        </div>

                        <div class="collapse" id="standaloneLoginLogCollapse-{{ $log->id }}" data-bs-parent="#standaloneLoginLogsAccordion">
                            <div class="m3-collapsible-body">
                                <div class="small text-secondary mb-2.5">
                                    @if($isDeviceBlocked || $isIpBlocked || $isAccountSuspended)
                                        <div class="mb-1.5 d-flex flex-wrap gap-1">
                                            @if($isDeviceBlocked)
                                                <span class="badge bg-danger text-white rounded-pill">{{ __('Perangkat Diblokir') }}</span>
                                            @elseif($isIpBlocked)
                                                <span class="badge bg-danger text-white rounded-pill">{{ __('IP Diblokir') }}</span>
                                            @elseif($isAccountSuspended)
                                                <span class="badge bg-danger text-white rounded-pill">{{ __('Suspended') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    <div><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $log->location ?? 'Unknown' }}</div>
                                    <div><i class="fas fa-laptop me-1"></i>{{ $log->device_type }} · {{ $log->browser ?? '-' }}</div>
                                    @if($effectiveFp)
                                        <span class="badge bg-secondary-subtle text-secondary font-monospace mt-1" style="font-size: 0.65rem;"><i class="fas fa-fingerprint me-1"></i>{{ Str::limit($effectiveFp, 18) }}</span>
                                    @endif
                                </div>
                                <div class="d-flex gap-1.5">
                                    <button type="button" class="btn btn-sm btn-outline-danger m3-compact-action-btn flex-grow-1"
                                            onclick="openUnifiedBlockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}')">
                                        <i class="fas fa-ban"></i><span>{{ __('Blokir') }}</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success m3-compact-action-btn flex-grow-1"
                                            onclick="openUnifiedUnblockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}', {{ $isIpBlocked ? 'true' : 'false' }}, {{ $isDeviceBlocked ? 'true' : 'false' }}, {{ $isAccountSuspended ? 'true' : 'false' }})">
                                        <i class="fas fa-unlock"></i><span>{{ __('Unblock') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="px-4 py-3 border-top">
                    {{ $loginLogs->links('pagination::bootstrap-5') }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-shield-alt text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">{{ __('Belum ada riwayat percobaan login.') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tg-pane" role="tabpanel" tabindex="0">
        <div class="card overflow-hidden">
            <div class="card-body p-0">
                @if($loginTokens->count() > 0)
                {{-- Desktop Table --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">{{ __('Kode / Token') }}</th>
                                <th class="py-3">{{ __('Status') }}</th>
                                <th class="py-3">{{ __('IP Address') }}</th>
                                <th class="py-3">{{ __('Browser') }}</th>
                                <th class="py-3">{{ __('Waktu Expired') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loginTokens as $login)
                            <tr>
                                <td class="px-4 fw-bold text-body font-monospace">{{ $login->token }}</td>
                                <td>
                                    @if($login->status === 'used')
                                        <span class="badge bg-success-subtle text-success px-3 py-1.5 rounded-pill">{{ __('Digunakan') }}</span>
                                    @elseif($login->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning px-3 py-1.5 rounded-pill">{{ __('Menunggu Verifikasi') }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary px-3 py-1.5 rounded-pill">{{ ucfirst($login->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-secondary small font-monospace">{{ $login->ip_address ?? '-' }}</td>
                                <td class="text-secondary small text-truncate" style="max-width: 200px;" title="{{ $login->user_agent }}">{{ $login->user_agent ?? '-' }}</td>
                                <td class="text-secondary small">{{ $login->expires_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Collapsible Compact List (< 768px) --}}
                <div class="d-md-none p-2.5 m3-collapsible-list" id="standaloneTgTokensAccordion">
                    @foreach($loginTokens as $login)
                    <div class="m3-mobile-item-card m3-collapsible-card">
                        <div class="m3-compact-row">
                            <button type="button"
                                    class="m3-compact-trigger"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#standaloneTgTokenCollapse-{{ $login->id }}"
                                    aria-expanded="false"
                                    aria-controls="standaloneTgTokenCollapse-{{ $login->id }}">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold font-monospace text-body text-truncate" style="font-size: 0.8rem;">{{ Str::limit($login->token, 22) }}</div>
                                    <div class="text-secondary text-truncate font-monospace" style="font-size: 0.72rem;">
                                        {{ $login->ip_address ?? '-' }} · {{ $login->expires_at->diffForHumans() }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    @if($login->status === 'used')
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Digunakan') }}</span>
                                    @elseif($login->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Menunggu') }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ ucfirst($login->status) }}</span>
                                    @endif
                                    <i class="fas fa-chevron-down m3-compact-chevron"></i>
                                </div>
                            </button>
                        </div>
                        <div class="collapse" id="standaloneTgTokenCollapse-{{ $login->id }}" data-bs-parent="#standaloneTgTokensAccordion">
                            <div class="m3-collapsible-body small text-secondary">
                                <div class="font-monospace text-body text-break mb-1" style="font-size: 0.74rem;">{{ $login->token }}</div>
                                <div class="text-break">{{ $login->user_agent ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="px-4 py-3 border-top">
                    {{ $loginTokens->links('pagination::bootstrap-5') }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fab fa-telegram text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">{{ __('Belum ada riwayat percobaan login telegram.') }}</p>
                </div>
                @endif
            </div>
        </div>
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

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);

                if (ip) {
                    const ipInput = document.createElement('input');
                    ipInput.type = 'hidden';
                    ipInput.name = 'ip_address';
                    ipInput.value = ip;
                    form.appendChild(ipInput);
                }

                if (deviceFp) {
                    const fpInput = document.createElement('input');
                    fpInput.type = 'hidden';
                    fpInput.name = 'device_fingerprint';
                    fpInput.value = deviceFp;
                    form.appendChild(fpInput);
                }

                if (deviceId) {
                    const devInput = document.createElement('input');
                    devInput.type = 'hidden';
                    devInput.name = 'device_id';
                    devInput.value = deviceId;
                    form.appendChild(devInput);
                }

                if (userId) {
                    const uInput = document.createElement('input');
                    uInput.type = 'hidden';
                    uInput.name = 'user_id';
                    uInput.value = userId;
                    form.appendChild(uInput);
                }

                const bIp = document.createElement('input');
                bIp.type = 'hidden';
                bIp.name = 'block_ip';
                bIp.value = result.value.blockIp ? '1' : '0';
                form.appendChild(bIp);

                const bDev = document.createElement('input');
                bDev.type = 'hidden';
                bDev.name = 'block_device';
                bDev.value = result.value.blockDevice ? '1' : '0';
                form.appendChild(bDev);

                const sAcc = document.createElement('input');
                sAcc.type = 'hidden';
                sAcc.name = 'suspend_account';
                sAcc.value = result.value.suspendAccount ? '1' : '0';
                form.appendChild(sAcc);

                const dur = document.createElement('input');
                dur.type = 'hidden';
                dur.name = 'duration';
                dur.value = result.value.duration;
                form.appendChild(dur);

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

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);

                if (ip) {
                    const ipInput = document.createElement('input');
                    ipInput.type = 'hidden';
                    ipInput.name = 'ip_address';
                    ipInput.value = ip;
                    form.appendChild(ipInput);
                }

                if (deviceFp) {
                    const fpInput = document.createElement('input');
                    fpInput.type = 'hidden';
                    fpInput.name = 'device_fingerprint';
                    fpInput.value = deviceFp;
                    form.appendChild(fpInput);
                }

                if (deviceId) {
                    const devInput = document.createElement('input');
                    devInput.type = 'hidden';
                    devInput.name = 'device_id';
                    devInput.value = deviceId;
                    form.appendChild(devInput);
                }

                if (userId) {
                    const uInput = document.createElement('input');
                    uInput.type = 'hidden';
                    uInput.name = 'user_id';
                    uInput.value = userId;
                    form.appendChild(uInput);
                }

                const uIp = document.createElement('input');
                uIp.type = 'hidden';
                uIp.name = 'unblock_ip';
                uIp.value = result.value.unblockIp ? '1' : '0';
                form.appendChild(uIp);

                const uDev = document.createElement('input');
                uDev.type = 'hidden';
                uDev.name = 'unblock_device';
                uDev.value = result.value.unblockDevice ? '1' : '0';
                form.appendChild(uDev);

                const uAcc = document.createElement('input');
                uAcc.type = 'hidden';
                uAcc.name = 'unsuspend_account';
                uAcc.value = result.value.unsuspendAccount ? '1' : '0';
                form.appendChild(uAcc);

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

    // === 0. Interactive Visitor & Login Geolocation Map (Google Maps Basemap) ===
    const geoMapContainer = document.getElementById('visitorGeoMap');
    const geoPoints = @json($geoMapPoints ?? []);
    const loginsBaseUrl = @json(route('admin.logins.index'));
    const mapLang = @json(app()->getLocale() === 'en' ? 'en' : 'id');

    function escapeGeoHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    if (geoMapContainer && typeof L !== 'undefined') {
        const defaultCenter = [-2.5, 118.0]; // Fokus awal Indonesia
        const geoMap = L.map('visitorGeoMap', {
            center: defaultCenter,
            zoom: 4,
            minZoom: 2,
            maxZoom: 18,
            scrollWheelZoom: false,
            worldCopyJump: true
        });

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

        let currentBasemap = 'roadmap';
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
            currentBasemap = mode;
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

                const filterUrl = loginsBaseUrl + '?search=' + encodeURIComponent(p.filter_query || p.city || p.country || '');
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

        const initialBounds = renderGeoMarkers('all');
        if (initialBounds.length === 1) {
            geoMap.setView(initialBounds[0], 7);
        } else if (initialBounds.length > 1) {
            geoMap.fitBounds(initialBounds, { padding: [36, 36], maxZoom: 7 });
        }

        // Layer Filter Buttons
        document.querySelectorAll('.geo-layer-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.geo-layer-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentLayerFilter = this.getAttribute('data-layer') || 'all';
                renderGeoMarkers(currentLayerFilter);
            });
        });

        // Google Basemap Switcher (Roadmap / Hybrid Satellite)
        document.querySelectorAll('.geo-basemap-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.geo-basemap-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const mode = this.getAttribute('data-basemap') || 'roadmap';
                setBasemap(mode);
            });
        });

        // Quick Viewport Focus Buttons
        const btnFocusId = document.getElementById('btnGeoFocusIndonesia');
        if (btnFocusId) {
            btnFocusId.addEventListener('click', () => geoMap.flyTo([-2.5, 118.0], 5, { duration: 0.7 }));
        }
        const btnFocusWorld = document.getElementById('btnGeoFocusWorld');
        if (btnFocusWorld) {
            btnFocusWorld.addEventListener('click', () => geoMap.flyTo([15.0, 20.0], 2, { duration: 0.7 }));
        }

        // Top Locations Click-to-Fly
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

    // 1. Login Trend Multi-Area Chart (Berhasil vs Gagal — Google Cloud Console Semantic Palette)
    const loginTrendOptions = {
        series: [
            {
                name: "{{ __('Login Berhasil') }}",
                data: @json($loginSuccessTrend ?? [])
            },
            {
                name: "{{ __('Login Gagal') }}",
                data: @json($loginFailedTrend ?? [])
            }
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
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.24,
                opacityTo: 0.02,
                stops: [20, 100]
            }
        },
        grid: {
            borderColor: isDark ? '#3c4043' : '#dadce0',
            strokeDashArray: 3
        },
        xaxis: {
            categories: @json($loginTrendLabels ?? []),
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                formatter: function (val) {
                    return Math.round(val);
                }
            },
            min: 0
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right'
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light'
        }
    };
    const loginTrendChart = new ApexCharts(document.querySelector("#loginTrendChart"), loginTrendOptions);
    loginTrendChart.render();

    // 2. Hourly Time Slot Stacked Bar Chart
    const loginHourlyOptions = {
        series: [
            {
                name: "{{ __('Login Berhasil') }}",
                data: @json($hourlySuccessSlotData ?? [])
            },
            {
                name: "{{ __('Login Gagal') }}",
                data: @json($hourlyFailedSlotData ?? [])
            }
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
        plotOptions: {
            bar: {
                horizontal: false,
                borderRadius: 4,
                columnWidth: '52%'
            }
        },
        grid: {
            borderColor: isDark ? '#3c4043' : '#dadce0',
            strokeDashArray: 3
        },
        dataLabels: { enabled: false },
        xaxis: {
            categories: @json($hourlySlotLabels ?? []),
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                formatter: function (val) {
                    return Math.round(val);
                }
            },
            min: 0
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right'
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light'
        }
    };
    const loginHourlyChart = new ApexCharts(document.querySelector("#loginHourlyChart"), loginHourlyOptions);
    loginHourlyChart.render();

    // 3. Device Distribution Donut Chart
    @if(!empty($deviceData) && array_sum($deviceData) > 0)
    const deviceDistOptions = {
        series: @json($deviceData ?? []),
        labels: @json($deviceLabels ?? []),
        chart: {
            type: 'donut',
            height: 260,
            background: 'transparent',
            fontFamily: 'inherit'
        },
        theme: { mode: isDark ? 'dark' : 'light' },
        colors: ['#3367d6', '#0b8043', '#dc6d00', '#12b5cb', '#5f6368'],
        stroke: {
            width: 2,
            colors: [isDark ? '#28292c' : '#ffffff']
        },
        legend: {
            position: 'bottom',
            fontSize: '12px'
        },
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                return Math.round(val) + "%";
            }
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: "{{ __('Total Akses') }}",
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                            }
                        }
                    }
                }
            }
        },
        tooltip: {
            theme: isDark ? 'dark' : 'light'
        }
    };
    const deviceDistChart = new ApexCharts(document.querySelector("#deviceDistChart"), deviceDistOptions);
    deviceDistChart.render();
    @endif

    // 4. Browser Distribution Horizontal Bar Chart
    @if(!empty($browserData) && array_sum($browserData) > 0)
    const browserDistOptions = {
        series: [{
            name: "{{ __('Jumlah Akses') }}",
            data: @json($browserData ?? [])
        }],
        chart: {
            type: 'bar',
            height: 260,
            toolbar: { show: false },
            background: 'transparent',
            fontFamily: 'inherit'
        },
        theme: { mode: isDark ? 'dark' : 'light' },
        plotOptions: {
            bar: {
                borderRadius: 4,
                horizontal: true,
                distributed: true,
                barHeight: '55%'
            }
        },
        colors: ['#3367d6', '#0b8043', '#dc6d00', '#9334e6', '#12b5cb'],
        grid: {
            borderColor: isDark ? '#3c4043' : '#dadce0',
            strokeDashArray: 3
        },
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                return val;
            }
        },
        xaxis: {
            categories: @json($browserLabels ?? []),
            labels: {
                formatter: function (val) {
                    return Math.round(val);
                }
            }
        },
        legend: { show: false },
        tooltip: {
            theme: isDark ? 'dark' : 'light'
        }
    };
    const browserDistChart = new ApexCharts(document.querySelector("#browserDistChart"), browserDistOptions);
    browserDistChart.render();
    @endif
});
</script>
@endpush
