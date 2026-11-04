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

{{-- KPI Metric Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem;">{{ __('Total Log Audit') }}</span>
                    <h4 class="fw-bold mb-0 text-body">{{ number_format($totalAuditCount ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem;">{{ __('Aktivitas 7 Hari') }}</span>
                    <h4 class="fw-bold mb-0 text-body">{{ number_format($weekAuditCount ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem;">{{ __('Aksi Terbanyak') }}</span>
                    <h5 class="fw-bold mb-0 text-truncate text-body" style="font-size: 1rem;" title="{{ $topActionName ?? '-' }}">
                        {{ $topActionName ?? '-' }}
                    </h5>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ number_format($topActionCount ?? 0, 0, ',', '.') }} {{ __('kejadian') }}</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem;">{{ __('Pelaku (User / Bot)') }}</span>
                    <h5 class="fw-bold mb-0 text-body" style="font-size: 1rem;">
                        {{ $actorAuditCount ?? 0 }} <span class="text-muted fw-normal" style="font-size: 0.8rem;">/ {{ $systemAuditCount ?? 0 }} bot</span>
                    </h5>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Analytics Charts Row --}}
<div class="row g-3 mb-4">
    <!-- Chart: Activity Trend -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm h-100 rounded-4">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-area text-primary me-2"></i>{{ __('Tren Aktivitas Sistem (7 Hari Terakhir)') }}</h5>
                    <span class="text-muted small">{{ __('Frekuensi eksekusi log audit per hari') }}</span>
                </div>
            </div>
            <div class="card-body px-3 pb-2 pt-2">
                <div id="auditTrendChart" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>

    <!-- Chart: Action Distribution -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100 rounded-4">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4">
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
</div>

{{-- Filter Card --}}
<div class="card border-0 shadow-sm mb-4 rounded-4">
    <div class="card-body p-3">
        <form action="{{ route('admin.audit-logs.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-lg-5 col-md-12">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Cari Kata Kunci') }}</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-0" placeholder="{{ __('Cari berdasarkan aksi, detail, atau nama pelaku...') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Tanggal Mulai') }}</label>
                <input type="date" name="start_date" class="form-control bg-light border-0" value="{{ request('start_date') }}">
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Tanggal Selesai') }}</label>
                <input type="date" name="end_date" class="form-control bg-light border-0" value="{{ request('end_date') }}">
            </div>
            <div class="col-lg-1 col-md-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill" title="{{ __('Terapkan Filter') }}"><i class="fas fa-filter"></i></button>
                    @if(request()->filled('search') || request()->filled('start_date') || request()->filled('end_date'))
                        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-light rounded-pill text-muted px-3" title="{{ __('Reset Filter') }}"><i class="fas fa-undo"></i></a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Table Card --}}
<div class="card border-0 shadow-sm overflow-hidden rounded-4">
    <div class="card-body p-0">
        @if($logs->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom bg-light">
                        <th class="px-4 py-3 border-0" style="width: 180px;">{{ __('Waktu') }}</th>
                        <th class="py-3 border-0" style="width: 180px;">{{ __('Pelaku (Actor)') }}</th>
                        <th class="py-3 border-0" style="width: 180px;">{{ __('Aksi') }}</th>
                        <th class="py-3 border-0">{{ __('Detail Perubahan') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td class="px-4 text-secondary small">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i:s') }}</td>
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
    <div class="card-footer bg-transparent border-0 px-4 py-3">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Audit Trend Area Chart
    const trendOptions = {
        series: [{
            name: "{{ __('Jumlah Aktivitas') }}",
            data: @json($auditTrendData ?? [])
        }],
        chart: {
            type: 'area',
            height: 280,
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit'
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        colors: ['#0d6efd'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [20, 100]
            }
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
        tooltip: {
            theme: 'dark',
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
            fontFamily: 'inherit'
        },
        colors: ['#0d6efd', '#20c997', '#ffc107', '#0dcaf0', '#6f42c1', '#6c757d'],
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
            theme: 'dark'
        }
    };
    const actionDistChart = new ApexCharts(document.querySelector("#actionDistributionChart"), actionDistOptions);
    actionDistChart.render();
    @endif
});
</script>
@endpush
