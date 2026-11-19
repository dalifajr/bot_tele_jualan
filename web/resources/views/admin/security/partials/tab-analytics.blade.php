{{-- Tab 2: Analitik Login & Evaluasi Risiko Autentikasi --}}

{{-- 4 KPI Metric Cards Login --}}
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

{{-- Panel Hasil Analisis Keamanan & Evaluasi Risiko Login --}}
<div class="card mb-4">
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
        <div class="row g-3">
            @foreach(($loginAnalysisInsights ?? []) as $insight)
                <div class="col-12 col-lg-4">
                    <div class="d-flex align-items-start gap-3 p-3 m3-tonal-item border-start border-4 border-{{ $insight['color'] ?? 'primary' }} h-100">
                        <div class="rounded-3 bg-{{ $insight['color'] ?? 'primary' }}-subtle text-{{ $insight['color'] ?? 'primary' }} d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width: 36px; height: 36px;">
                            <i class="fas {{ $insight['icon'] ?? 'fa-info-circle' }}"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1 text-body" style="font-size: 0.9rem;">{{ $insight['title'] }}</h6>
                            <p class="text-muted small mb-0" style="line-height: 1.45;">{{ $insight['desc'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Analytics Charts Row 1: Tren Harian & Pola Jam Akses --}}
<div class="row g-3 mb-4">
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
<div class="row g-3">
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
