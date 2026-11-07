@extends('layouts.app')

@section('title', __('Notifikasi Login Web'))
@section('page_subtitle', __('Sistem Keamanan'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Daftar Percobaan Login') }}</h4>
        <p class="text-muted mb-0">{{ __('Riwayat autentikasi masuk, diagram analitik keamanan, dan pengelolaan pemblokiran') }}</p>
    </div>
</div>

{{-- KPI Metric Cards Keamanan (Flat Material 3) --}}
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
                    <i class="fas fa-heartbeat me-1"></i>{{ $securityStatus ?? 'Aman & Terkendali' }} ({{ $securityScore ?? 100 }}/100)
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

{{-- Filter Pencarian Log Login (Flat Material 3 Outlined Controls) --}}
<div class="card mb-4">
    <div class="card-body p-3">
        <form action="{{ route('admin.logins.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-lg-5 col-md-12">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Cari User / IP / Lokasi / Browser') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="{{ __('Ketik username, email, IP address, atau lokasi...') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Status Autentikasi') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>{{ __('Berhasil Login') }}</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ __('Gagal / Ditolak') }}</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Tipe Perangkat') }}</label>
                <select name="device_type" class="form-select">
                    <option value="">{{ __('Semua Perangkat') }}</option>
                    @foreach(($deviceLabels ?? []) as $dl)
                        <option value="{{ $dl }}" {{ request('device_type') === $dl ? 'selected' : '' }}>{{ $dl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-1 col-md-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill" title="{{ __('Terapkan Filter') }}"><i class="fas fa-filter"></i></button>
                    @if(request()->filled('search') || request()->filled('status') || request()->filled('device_type'))
                        <a href="{{ route('admin.logins.index') }}" class="btn btn-light rounded-pill px-3" title="{{ __('Reset Filter') }}"><i class="fas fa-undo"></i></a>
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
                <div class="table-responsive">
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
                <div class="table-responsive">
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
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    // 1. Login Trend Multi-Area Chart (Berhasil vs Gagal — Material 3 Semantic Palette)
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
        colors: ['#146c2e', '#ba1a1a'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: [2.5, 2.5] },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.26,
                opacityTo: 0.02,
                stops: [20, 100]
            }
        },
        grid: {
            borderColor: isDark ? '#30363d' : '#dadce0',
            strokeDashArray: 4
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
        colors: ['#146c2e', '#ba1a1a'],
        plotOptions: {
            bar: {
                horizontal: false,
                borderRadius: 5,
                columnWidth: '55%'
            }
        },
        grid: {
            borderColor: isDark ? '#30363d' : '#dadce0',
            strokeDashArray: 4
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
        colors: ['#0b57d0', '#146c2e', '#e37400', '#00639b', '#5f6368'],
        stroke: {
            width: 2,
            colors: [isDark ? '#161b22' : '#ffffff']
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
                borderRadius: 6,
                horizontal: true,
                distributed: true,
                barHeight: '55%'
            }
        },
        colors: ['#0b57d0', '#146c2e', '#e37400', '#6f42c1', '#00639b'],
        grid: {
            borderColor: isDark ? '#30363d' : '#dadce0',
            strokeDashArray: 4
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
