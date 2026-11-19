@extends('layouts.app')

@section('title', __('Pusat Kesehatan Sistem & Kontrol VPS'))
@section('page_subtitle', __('Diagnostik infrastruktur, kontrol layanan VPS, pemeliharaan scheduler, pembaruan GitHub & log real-time'))

@push('styles')
<style>
    .sys-hub-tabs-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: 2px;
    }
    .sys-hub-tabs-scroll::-webkit-scrollbar {
        display: none;
    }
    .sys-hub-tabbar {
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
    .sys-hub-tabbar .nav-link {
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
    .sys-hub-tabbar .nav-link:hover:not(.active) {
        background: var(--surface-container-high, #e6e0e9);
        color: var(--on-surface, #1d1b20);
    }
    .sys-hub-tabbar .nav-link.active {
        background: var(--primary) !important;
        color: var(--on-primary, #ffffff) !important;
    }
    .sys-hub-tabbar .nav-link.active .sys-tab-badge {
        background: rgba(255, 255, 255, 0.22) !important;
        color: #ffffff !important;
    }
    .sys-tab-badge {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 9999px;
        line-height: 1.2;
    }
    .sys-kpi-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }
</style>
@endpush

@section('content')
@php
    $pendingIssuesCount = $metrics['expired_stuck_orders'] + $metrics['paid_stuck_orders'] + $metrics['failed_jobs'];
    $activeServicesCount = collect($services)->where('state', 'active')->count();
@endphp

{{-- Header Komando Pusat Kesehatan & Kontrol VPS --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h4 class="fw-bold mb-0 text-body">
                <i class="fas fa-heart-pulse text-primary me-1.5"></i>{{ __('Pusat Kesehatan Sistem') }}
            </h4>
            <span class="badge bg-{{ $healthBadgeColor }}-subtle text-{{ $healthBadgeColor }} rounded-pill px-3 py-1.5 fw-bold">
                <i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>{{ $healthStatus }} ({{ $healthScore }}/100)
            </span>
            <span class="badge rounded-pill px-2.5 py-1.5 fw-semibold {{ $bridgeStatus['available'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                <i class="fas fa-server me-1"></i>{{ $bridgeStatus['available'] ? __('VPS Bridge Aktif') : ($bridgeStatus['is_linux'] ? __('Mode Laravel') : __('Lokal')) }}
            </span>
        </div>
        <small class="text-secondary">{{ __('Diagnostik infrastruktur server, kontrol layanan Panel Jualan VPS, pemeliharaan scheduler, pembaruan GitHub, dan log real-time.') }}</small>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="button" id="btnPingTelegram" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
            <i class="fab fa-telegram me-1"></i>{{ __('Test Ping Telegram') }}
        </button>
        <button type="button" id="btnPingVpn" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold">
            <i class="fas fa-network-wired me-1"></i>{{ __('Test VPN SSH') }}
        </button>
        <form action="{{ route('admin.system.health.flush-cache') }}" method="POST" class="m-0">
            @csrf
            <input type="hidden" name="active_tab" id="headerFlushActiveTab" value="{{ $activeTab }}">
            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                <i class="fas fa-rotate me-1"></i>{{ __('Segarkan Cache') }}
            </button>
        </form>
    </div>
</div>

{{-- Kotak Hasil Diagnostik Ping AJAX --}}
<div id="pingResultBox" class="alert d-none rounded-4 mb-4 small fw-semibold"></div>

{{-- 4 Kartu KPI Global Infrastruktur --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5 d-flex align-items-center justify-content-between gap-2">
                <div class="overflow-hidden">
                    <div class="d-flex align-items-center gap-1.5 mb-0.5">
                        <small class="text-secondary fw-semibold text-truncate">{{ __('Server & Memori') }}</small>
                    </div>
                    <div class="fs-5 fw-bold text-body">
                        @if($serverTelemetry['is_linux'])
                            {{ $serverTelemetry['ram_percent'] }}% RAM
                        @else
                            PHP {{ $metrics['php_version'] }}
                        @endif
                    </div>
                    <small class="text-muted text-truncate d-block" style="font-size: 0.72rem;">
                        @if($serverTelemetry['is_linux'])
                            Load: {{ $serverTelemetry['load_label'] }} · Up: {{ $serverTelemetry['uptime_label'] }}
                        @else
                            Laravel {{ $metrics['laravel_version'] }} · {{ $serverTelemetry['php_memory_usage'] }}
                        @endif
                    </small>
                </div>
                <div class="sys-kpi-icon bg-primary-subtle text-primary">
                    <i class="fas fa-microchip"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5 d-flex align-items-center justify-content-between gap-2">
                <div class="overflow-hidden">
                    <div class="d-flex align-items-center gap-1.5 mb-0.5">
                        <small class="text-secondary fw-semibold">{{ __('Database') }}</small>
                        <span class="badge rounded-pill {{ $metrics['db_connected'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}" style="font-size: 0.65rem;">
                            {{ $metrics['db_connected'] ? __('Online') : __('Offline') }}
                        </span>
                    </div>
                    <div class="fs-5 fw-bold text-body">{{ strtoupper($metrics['db_driver']) }} · {{ $metrics['db_latency_ms'] }} ms</div>
                    <small class="text-muted text-truncate d-block" style="font-size: 0.72rem;">{{ __('Ukuran') }}: {{ $metrics['db_size_label'] }} · {{ $metrics['db_table_count'] }} {{ __('tabel') }}</small>
                </div>
                <div class="sys-kpi-icon bg-info-subtle text-info">
                    <i class="fas fa-database"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5 d-flex align-items-center justify-content-between gap-2">
                <div class="overflow-hidden">
                    <div class="d-flex align-items-center gap-1.5 mb-0.5">
                        <small class="text-secondary fw-semibold">{{ __('Penyimpanan Disk') }}</small>
                        <span class="badge rounded-pill {{ $metrics['disk_used_percent'] < 85 ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' }}" style="font-size: 0.65rem;">
                            {{ $metrics['disk_used_percent'] }}%
                        </span>
                    </div>
                    <div class="fs-5 fw-bold text-body">{{ $metrics['disk_free_label'] }} {{ __('tersisa') }}</div>
                    <small class="text-muted text-truncate d-block" style="font-size: 0.72rem;">{{ __('Log Laravel') }}: {{ $metrics['log_size_label'] }}</small>
                </div>
                <div class="sys-kpi-icon {{ $metrics['disk_used_percent'] >= 85 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                    <i class="fas fa-hard-drive"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5 d-flex align-items-center justify-content-between gap-2">
                <div class="overflow-hidden">
                    <div class="d-flex align-items-center gap-1.5 mb-0.5">
                        <small class="text-secondary fw-semibold">{{ __('Antrean Scheduler') }}</small>
                        <span class="badge rounded-pill {{ $pendingIssuesCount === 0 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }}" style="font-size: 0.65rem;">
                            {{ $pendingIssuesCount === 0 ? __('Sehat') : __('Perlu Aksi') }}
                        </span>
                    </div>
                    <div class="fs-5 fw-bold text-body">{{ $metrics['expired_stuck_orders'] }} {{ __('order expired') }}</div>
                    <small class="text-muted text-truncate d-block" style="font-size: 0.72rem;">{{ $metrics['ready_held_funds'] }} {{ __('held funds') }} · {{ $metrics['paid_stuck_orders'] }} {{ __('paid tertahan') }}</small>
                </div>
                <div class="sys-kpi-icon bg-warning-subtle text-warning-emphasis">
                    <i class="fas fa-list-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Navigasi 5 Tab Fokus (Material You Pill Bar) --}}
<div class="sys-hub-tabs-scroll mb-4">
    <ul class="nav sys-hub-tabbar" id="systemHealthTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'ringkasan' ? 'active' : '' }}"
                    id="sys-tab-ringkasan"
                    data-bs-toggle="tab"
                    data-bs-target="#sys-pane-ringkasan"
                    data-tab-slug="ringkasan"
                    type="button"
                    role="tab"
                    aria-controls="sys-pane-ringkasan"
                    aria-selected="{{ $activeTab === 'ringkasan' ? 'true' : 'false' }}">
                <i class="fas fa-stethoscope"></i>
                <span>{{ __('Ringkasan & Diagnostik') }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'layanan' ? 'active' : '' }}"
                    id="sys-tab-layanan"
                    data-bs-toggle="tab"
                    data-bs-target="#sys-pane-layanan"
                    data-tab-slug="layanan"
                    type="button"
                    role="tab"
                    aria-controls="sys-pane-layanan"
                    aria-selected="{{ $activeTab === 'layanan' ? 'true' : 'false' }}">
                <i class="fas fa-gears"></i>
                <span>{{ __('Layanan VPS & Bot') }}</span>
                @if($activeServicesCount > 0)
                    <span class="sys-tab-badge bg-success-subtle text-success">{{ $activeServicesCount }}/{{ count($services) }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'pemeliharaan' ? 'active' : '' }}"
                    id="sys-tab-pemeliharaan"
                    data-bs-toggle="tab"
                    data-bs-target="#sys-pane-pemeliharaan"
                    data-tab-slug="pemeliharaan"
                    type="button"
                    role="tab"
                    aria-controls="sys-pane-pemeliharaan"
                    aria-selected="{{ $activeTab === 'pemeliharaan' ? 'true' : 'false' }}">
                <i class="fas fa-screwdriver-wrench"></i>
                <span>{{ __('Pemeliharaan & Disk') }}</span>
                @if($pendingIssuesCount > 0)
                    <span class="sys-tab-badge bg-warning text-dark">{{ $pendingIssuesCount }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'pembaruan' ? 'active' : '' }}"
                    id="sys-tab-pembaruan"
                    data-bs-toggle="tab"
                    data-bs-target="#sys-pane-pembaruan"
                    data-tab-slug="pembaruan"
                    type="button"
                    role="tab"
                    aria-controls="sys-pane-pembaruan"
                    aria-selected="{{ $activeTab === 'pembaruan' ? 'true' : 'false' }}">
                <i class="fab fa-github"></i>
                <span>{{ __('Update & Konfigurasi') }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'log' ? 'active' : '' }}"
                    id="sys-tab-log"
                    data-bs-toggle="tab"
                    data-bs-target="#sys-pane-log"
                    data-tab-slug="log"
                    type="button"
                    role="tab"
                    aria-controls="sys-pane-log"
                    aria-selected="{{ $activeTab === 'log' ? 'true' : 'false' }}">
                <i class="fas fa-file-lines"></i>
                <span>{{ __('Log Real-Time') }}</span>
            </button>
        </li>
    </ul>
</div>

{{-- Konten 5 Tab --}}
<div class="tab-content" id="systemHealthTabsContent">
    <div class="tab-pane fade {{ $activeTab === 'ringkasan' ? 'show active' : '' }}" id="sys-pane-ringkasan" role="tabpanel" aria-labelledby="sys-tab-ringkasan" tabindex="0">
        @include('admin.system.partials.tab-overview')
    </div>
    <div class="tab-pane fade {{ $activeTab === 'layanan' ? 'show active' : '' }}" id="sys-pane-layanan" role="tabpanel" aria-labelledby="sys-tab-layanan" tabindex="0">
        @include('admin.system.partials.tab-services')
    </div>
    <div class="tab-pane fade {{ $activeTab === 'pemeliharaan' ? 'show active' : '' }}" id="sys-pane-pemeliharaan" role="tabpanel" aria-labelledby="sys-tab-pemeliharaan" tabindex="0">
        @include('admin.system.partials.tab-maintenance')
    </div>
    <div class="tab-pane fade {{ $activeTab === 'pembaruan' ? 'show active' : '' }}" id="sys-pane-pembaruan" role="tabpanel" aria-labelledby="sys-tab-pembaruan" tabindex="0">
        @include('admin.system.partials.tab-updates-config')
    </div>
    <div class="tab-pane fade {{ $activeTab === 'log' ? 'show active' : '' }}" id="sys-pane-log" role="tabpanel" aria-labelledby="sys-tab-log" tabindex="0">
        @include('admin.system.partials.tab-logs')
    </div>
</div>
@endsection

@push('scripts')
<script>
function switchHealthTab(slug) {
    const btn = document.getElementById('sys-tab-' + slug);
    if (btn && typeof bootstrap !== 'undefined') {
        bootstrap.Tab.getOrCreateInstance(btn).show();
    }
}

function copyTextValue(elId, btnEl) {
    const el = document.getElementById(elId);
    if (!el) return;
    const text = el.textContent.trim();
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            if (btnEl) {
                const orig = btnEl.innerHTML;
                btnEl.innerHTML = '<i class="fas fa-check me-1"></i>{{ __("Disalin") }}';
                setTimeout(() => { btnEl.innerHTML = orig; }, 1600);
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const pingBox = document.getElementById('pingResultBox');

    function runDiagPing(btnEl, url, loadingText) {
        if (!btnEl || !pingBox) return;
        btnEl.disabled = true;
        pingBox.className = 'alert alert-info rounded-4 mb-4 small fw-semibold';
        pingBox.textContent = loadingText;

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            pingBox.className = 'alert ' + (data.ok ? 'alert-success' : 'alert-danger') + ' rounded-4 mb-4 small fw-semibold';
            pingBox.textContent = data.message || 'Done';
        })
        .catch(() => {
            pingBox.className = 'alert alert-danger rounded-4 mb-4 small fw-semibold';
            pingBox.textContent = @json(__('Gagal melakukan ping ke endpoint diagnostik.'));
        })
        .finally(() => { btnEl.disabled = false; });
    }

    const btnPingTg = document.getElementById('btnPingTelegram');
    if (btnPingTg) {
        btnPingTg.addEventListener('click', () => runDiagPing(
            btnPingTg,
            @json(route('admin.system.health.ping-telegram')),
            @json(__('Menguji koneksi ke Telegram Bot API...'))
        ));
    }

    const btnPingVpn = document.getElementById('btnPingVpn');
    if (btnPingVpn) {
        btnPingVpn.addEventListener('click', () => runDiagPing(
            btnPingVpn,
            @json(route('admin.system.health.ping-vpn')),
            @json(__('Menguji koneksi SSH ke Server VPN...'))
        ));
    }

    // URL ?tab= synchronization
    document.querySelectorAll('#systemHealthTabs button[data-bs-toggle="tab"]').forEach(tabBtn => {
        tabBtn.addEventListener('shown.bs.tab', function (event) {
            const slug = event.target.getAttribute('data-tab-slug');
            if (!slug) return;
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', slug);
                window.history.replaceState(null, '', url.toString());
            } catch (e) {}
            const headerTab = document.getElementById('headerFlushActiveTab');
            if (headerTab) headerTab.value = slug;
        });
    });

    // Git Check Update AJAX
    const btnCheckUpdate = document.getElementById('btnCheckGitUpdate');
    const opsConsole = document.getElementById('opsTaskConsole');
    const opsBadge = document.getElementById('opsTaskStatusBadge');

    if (btnCheckUpdate && opsConsole) {
        btnCheckUpdate.addEventListener('click', function () {
            btnCheckUpdate.disabled = true;
            opsConsole.textContent = @json(__('Menghubungi repository GitHub (git fetch origin)...'));
            fetch(@json(route('admin.system.health.check-update')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
            .then(r => r.json())
            .then(data => {
                opsConsole.textContent = data.output || @json(__('Tidak ada output dari git fetch.'));
                if (opsBadge) {
                    opsBadge.className = 'badge rounded-pill ' + (data.ok ? 'bg-success' : 'bg-danger');
                    opsBadge.textContent = data.ok ? 'CHECKED' : 'ERROR';
                }
            })
            .catch(() => {
                opsConsole.textContent = @json(__('Gagal memeriksa pembaruan dari GitHub.'));
            })
            .finally(() => { btnCheckUpdate.disabled = false; });
        });
    }

    // Background Ops Task Status Polling
    function refreshTaskStatus() {
        if (!opsConsole) return;
        fetch(@json(route('admin.system.health.task-status')), {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.output) {
                opsConsole.textContent = data.output;
                opsConsole.scrollTop = opsConsole.scrollHeight;
            }
            if (opsBadge) {
                const st = data.status || 'idle';
                opsBadge.textContent = st.toUpperCase();
                opsBadge.className = 'badge rounded-pill ' + (
                    st === 'running' ? 'bg-warning text-dark' :
                    st === 'done' ? 'bg-success' :
                    st === 'failed' ? 'bg-danger' : 'bg-secondary'
                );
            }
            if (data.status === 'running') {
                setTimeout(refreshTaskStatus, 3000);
            }
        })
        .catch(() => {});
    }

    const btnRefreshTask = document.getElementById('btnRefreshTaskStatus');
    if (btnRefreshTask) {
        btnRefreshTask.addEventListener('click', refreshTaskStatus);
    }
    if (@json($taskStatus['status'] ?? 'idle') === 'running') {
        setTimeout(refreshTaskStatus, 2500);
    }

    // Live System Log Viewer
    const logSource = document.getElementById('logSourceSelect');
    const logLevel = document.getElementById('logLevelSelect');
    const logLines = document.getElementById('logLinesSelect');
    const btnFetchLogs = document.getElementById('btnFetchSystemLogs');
    const logViewer = document.getElementById('systemLogViewer');
    const logMeta = document.getElementById('logViewerMeta');

    function loadSystemLogs() {
        if (!logSource || !logViewer) return;
        if (btnFetchLogs) btnFetchLogs.disabled = true;
        const params = new URLSearchParams({
            source: logSource.value,
            level: logLevel ? logLevel.value : 'all',
            lines: logLines ? logLines.value : '100'
        });

        fetch(@json(route('admin.system.health.logs.fetch')) + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            logViewer.textContent = data.content || '';
            logViewer.scrollTop = logViewer.scrollHeight;
            if (logMeta) {
                logMeta.textContent = `${data.source} (${data.lines} lines, ${data.level}) · {{ __('Diperbarui:') }} ${data.fetched_at}`;
            }
        })
        .catch(() => {
            logViewer.textContent = @json(__('Gagal memuat isi log dari server.'));
        })
        .finally(() => {
            if (btnFetchLogs) btnFetchLogs.disabled = false;
        });
    }

    if (btnFetchLogs) btnFetchLogs.addEventListener('click', loadSystemLogs);
    if (logSource) logSource.addEventListener('change', loadSystemLogs);
    if (logLevel) logLevel.addEventListener('change', loadSystemLogs);
    if (logLines) logLines.addEventListener('change', loadSystemLogs);
});
</script>
@endpush
