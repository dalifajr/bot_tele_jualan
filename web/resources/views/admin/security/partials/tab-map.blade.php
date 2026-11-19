{{-- Tab 1: Peta Sebaran Geografis Pengunjung & Percobaan Login --}}
<div class="card overflow-hidden">
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
