@extends('layouts.app')

@section('title', __('Log Audit Sistem'))
@section('page_subtitle', __('Audit Logs & Analytics'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1 text-body">{{ __('Log Audit Sistem') }}</h1>
        <p class="text-muted mb-0">{{ __('Pelacakan riwayat aktivitas administratif, perubahan sistem, dan diagram analitik') }}</p>
    </div>
</div>

{{-- KPI Metric Cards (Flat Material 3) --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="m3-metric-icon bg-primary-subtle text-primary">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem; letter-spacing: 0.04em;">{{ __('Total Log Audit') }}</span>
                    <h4 class="fw-bold mb-0 text-body">{{ number_format($totalAuditCount ?? 0, 0, ',', '.') }}</h4>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ __('Hari ini:') }} <span class="fw-semibold text-primary">{{ number_format($todayAuditCount ?? 0, 0, ',', '.') }}</span> ({{ __('Kemarin:') }} {{ number_format($yesterdayAuditCount ?? 0, 0, ',', '.') }})</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="m3-metric-icon bg-info-subtle text-info">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem; letter-spacing: 0.04em;">{{ __('Aktivitas 7 Hari') }}</span>
                    <h4 class="fw-bold mb-0 text-body">{{ number_format($weekAuditCount ?? 0, 0, ',', '.') }}</h4>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ __('Rata-rata:') }} <span class="fw-semibold">{{ $dailyAverageAudit ?? 0 }}</span> {{ __('log/hari') }}</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="m3-metric-icon bg-warning-subtle text-warning">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem; letter-spacing: 0.04em;">{{ __('Aksi Terbanyak') }}</span>
                    <h5 class="fw-bold mb-0 text-truncate text-body" style="font-size: 1rem;" title="{{ $topActionName ?? '-' }}">
                        {{ $topActionName ?? '-' }}
                    </h5>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ number_format($topActionCount ?? 0, 0, ',', '.') }} {{ __('kejadian') }} · <span class="text-danger fw-semibold">{{ $criticalAuditCount ?? 0 }} {{ __('kritis') }}</span></small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card m3-metric-card h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="m3-metric-icon bg-success-subtle text-success">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem; letter-spacing: 0.04em;">{{ __('Pelaku (User / Bot)') }}</span>
                    <h5 class="fw-bold mb-0 text-body" style="font-size: 1rem;">
                        {{ $actorAuditCount ?? 0 }} <span class="text-muted fw-normal" style="font-size: 0.8rem;">/ {{ $systemAuditCount ?? 0 }} bot</span>
                    </h5>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ __('Otomatisasi:') }} <span class="fw-semibold text-success">{{ $automationRate ?? 0 }}%</span></small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Panel Hasil Analisis & Kesimpulan Audit + Top Pelaku --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-body"><i class="fas fa-microscope text-primary me-2"></i>{{ __('Hasil Analisis & Kesimpulan Log Audit') }}</h5>
                    <span class="text-muted small">{{ __('Ringkasan intelijen operasional dan evaluasi jejak sistem otomatis') }}</span>
                </div>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5 small">
                    <i class="fas fa-sync-alt me-1"></i>{{ __('Analisis Real-Time') }}
                </span>
            </div>
            <div class="card-body px-4 pt-3 pb-3">
                <div class="d-flex flex-column gap-3">
                    @foreach(($auditAnalysisInsights ?? []) as $insight)
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
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-users-cog text-warning me-2"></i>{{ __('Pelaku (Actor) Paling Aktif') }}</h5>
                <span class="text-muted small">{{ __('Kontribusi eksekusi perubahan berdasarkan akun pengguna') }}</span>
            </div>
            <div class="card-body px-4 pt-3 pb-3">
                @if(isset($topActors) && count($topActors) > 0)
                    <div class="d-flex flex-column gap-2">
                        @foreach($topActors as $actorRow)
                            <div class="p-2.5 m3-tonal-item d-flex align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2.5 overflow-hidden">
                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 34px; height: 34px; font-size: 0.8rem;">
                                        {{ strtoupper(substr($actorRow->actor_username ?? 'U', 0, 1)) }}
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-bold text-body text-truncate" style="font-size: 0.85rem;">{{ $actorRow->actor_name }}</div>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0">{{ strtoupper($actorRow->actor_role) }}</span>
                                            <span class="ms-1">@ {{ $actorRow->actor_username }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <span class="fw-bold text-primary d-block" style="font-size: 0.9rem;">{{ number_format($actorRow->total_actions, 0, ',', '.') }} {{ __('aksi') }}</span>
                                    <small class="text-muted" style="font-size: 0.7rem;">{{ $actorRow->share_percent }}% {{ __('dari total') }}</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-user-clock fa-2x mb-2 text-secondary opacity-50"></i>
                        <p class="small mb-0">{{ __('Belum ada data pelaku pengguna manual yang tercatat.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Analytics Charts Row --}}
<div class="row g-3 mb-4">
    <!-- Chart: Activity Trend (Total vs Manual vs System/Bot) -->
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-area text-primary me-2"></i>{{ __('Tren Aktivitas Sistem (7 Hari Terakhir)') }}</h5>
                    <span class="text-muted small">{{ __('Perbandingan total log, aksi manual user, dan otomatisasi bot') }}</span>
                </div>
            </div>
            <div class="card-body px-3 pb-2 pt-2">
                <div id="auditTrendChart" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>

    <!-- Chart: Action Distribution -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-pie text-success me-2"></i>{{ __('Distribusi Aksi Terbanyak') }}</h5>
                <span class="text-muted small">{{ __('Porsi jenis aktivitas yang tercatat') }}</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center p-3">
                @if(!empty($actionDistributionData) && array_sum($actionDistributionData) > 0)
                    <div id="actionDistributionChart" style="min-height: 280px; width: 100%;"></div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2 text-secondary opacity-50"></i>
                        <p class="small mb-0">{{ __('Belum ada variasi data aksi.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Chart: Entity Distribution -->
    <div class="col-12 col-lg-3">
        <div class="card h-100">
            <div class="card-header bg-transparent pt-3 pb-2 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-cubes text-info me-2"></i>{{ __('Distribusi Objek Entitas') }}</h5>
                <span class="text-muted small">{{ __('Modul sistem yang paling sering berubah') }}</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center p-3">
                @if(!empty($entityDistributionData) && array_sum($entityDistributionData) > 0)
                    <div id="entityDistributionChart" style="min-height: 280px; width: 100%;"></div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-layer-group fa-2x mb-2 text-secondary opacity-50"></i>
                        <p class="small mb-0">{{ __('Belum ada data entitas.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Filter Card (Flat Material 3 Outlined Controls) --}}
<div class="card mb-4">
    <div class="card-body p-3">
        <form action="{{ route('admin.audit-logs.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Cari Kata Kunci') }}</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="{{ __('Aksi, detail, atau pelaku...') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Tipe Pelaku') }}</label>
                <select name="actor_type" class="form-select">
                    <option value="">{{ __('Semua Pelaku') }}</option>
                    <option value="user" {{ request('actor_type') === 'user' ? 'selected' : '' }}>{{ __('User / Admin') }}</option>
                    <option value="system" {{ request('actor_type') === 'system' ? 'selected' : '' }}>{{ __('System / Bot') }}</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-sm-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Objek Entitas') }}</label>
                <select name="entity_type" class="form-select">
                    <option value="">{{ __('Semua Entitas') }}</option>
                    @foreach(($availableEntityTypes ?? []) as $et)
                        <option value="{{ $et }}" {{ request('entity_type') === $et ? 'selected' : '' }}>{{ strtoupper($et) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Tanggal Mulai') }}</label>
                <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Tanggal Selesai') }}</label>
                <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
            </div>
            <div class="col-lg-1 col-md-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill" title="{{ __('Terapkan Filter') }}"><i class="fas fa-filter"></i></button>
                    @if(request()->filled('search') || request()->filled('actor_type') || request()->filled('entity_type') || request()->filled('start_date') || request()->filled('end_date'))
                        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-light rounded-pill px-3" title="{{ __('Reset Filter') }}"><i class="fas fa-undo"></i></a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Table Card (Google Cloud Data Table Flat) --}}
<div class="card overflow-hidden">
    <div class="card-body p-0">
        @if($logs->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="px-4 py-3" style="width: 170px;">{{ __('Waktu') }}</th>
                        <th class="py-3" style="width: 190px;">{{ __('Pelaku (Actor)') }}</th>
                        <th class="py-3" style="width: 180px;">{{ __('Aksi') }}</th>
                        <th class="py-3" style="width: 140px;">{{ __('Objek Entitas') }}</th>
                        <th class="py-3">{{ __('Detail Perubahan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td class="px-4 text-secondary small font-monospace">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i:s') }}</td>
                        <td>
                            @if($log->actor)
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    {{ strtoupper(substr($log->actor->username ?? 'U', 0, 1)) }}
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-body" style="font-size: 0.85rem;">{{ $log->actor->full_name ?? $log->actor->username }}</span>
                                    <span class="text-muted small">@if($log->actor->role === 'admin') Admin @elseif($log->actor->role === 'seller') Seller @else Customer @endif</span>
                                </div>
                            </div>
                            @else
                            <div class="d-flex align-items-center gap-2 text-muted">
                                <div class="avatar bg-secondary-subtle text-secondary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    S
                                </div>
                                <span class="small fw-semibold">{{ __('SYSTEM / BOT') }}</span>
                            </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5 fw-semibold" style="font-size: 0.75rem;">{{ $log->action }}</span>
                        </td>
                        <td>
                            @if(!empty($log->entity_type))
                                <span class="badge bg-info-subtle text-info rounded-pill px-2.5 py-1 font-monospace" style="font-size: 0.7rem;">
                                    {{ strtoupper($log->entity_type) }}{{ $log->entity_id ? ' #' . $log->entity_id : '' }}
                                </span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="text-muted text-wrap font-monospace" style="max-width: 500px; font-size: 0.8rem;">
                                {{ $log->detail }}
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-history text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">{{ __('Belum ada data log audit.') }}</p>
        </div>
        @endif
    </div>
    
    @if($logs->hasPages())
    <div class="card-footer bg-transparent px-4 py-3">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    // 1. Audit Trend Multi-Series Area Chart (Material 3 / GCP Palette)
    const trendOptions = {
        series: [
            {
                name: "{{ __('Total Aktivitas') }}",
                data: @json($auditTrendData ?? [])
            },
            {
                name: "{{ __('Pelaku (User/Admin)') }}",
                data: @json($auditActorTrendData ?? [])
            },
            {
                name: "{{ __('Sistem / Bot') }}",
                data: @json($auditSystemTrendData ?? [])
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
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: [2.5, 2, 2], dashArray: [0, 0, 4] },
        colors: ['#0b57d0', '#146c2e', '#5f6368'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.25,
                opacityTo: 0.02,
                stops: [20, 100]
            }
        },
        grid: {
            borderColor: isDark ? '#30363d' : '#dadce0',
            strokeDashArray: 4
        },
        xaxis: {
            categories: @json($auditTrendLabels ?? []),
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
            theme: isDark ? 'dark' : 'light',
            y: {
                formatter: function (val) {
                    return val + " {{ __('aktivitas') }}";
                }
            }
        }
    };
    const trendChart = new ApexCharts(document.querySelector("#auditTrendChart"), trendOptions);
    trendChart.render();

    // 2. Action Distribution Donut Chart
    @if(!empty($actionDistributionData) && array_sum($actionDistributionData) > 0)
    const actionDistOptions = {
        series: @json($actionDistributionData ?? []),
        labels: @json($actionDistributionLabels ?? []),
        chart: {
            type: 'donut',
            height: 280,
            background: 'transparent',
            fontFamily: 'inherit'
        },
        theme: { mode: isDark ? 'dark' : 'light' },
        colors: ['#0b57d0', '#146c2e', '#e37400', '#00639b', '#6f42c1', '#5f6368'],
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
                            label: "{{ __('Total Aksi') }}",
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
    const actionDistChart = new ApexCharts(document.querySelector("#actionDistributionChart"), actionDistOptions);
    actionDistChart.render();
    @endif

    // 3. Entity Distribution Horizontal Bar Chart
    @if(!empty($entityDistributionData) && array_sum($entityDistributionData) > 0)
    const entityDistOptions = {
        series: [{
            name: "{{ __('Jumlah Log') }}",
            data: @json($entityDistributionData ?? [])
        }],
        chart: {
            type: 'bar',
            height: 280,
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
                barHeight: '60%'
            }
        },
        colors: ['#00639b', '#0b57d0', '#146c2e', '#e37400', '#6f42c1', '#ba1a1a'],
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
            categories: @json($entityDistributionLabels ?? []),
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
    const entityDistChart = new ApexCharts(document.querySelector("#entityDistributionChart"), entityDistOptions);
    entityDistChart.render();
    @endif
});
</script>
@endpush
