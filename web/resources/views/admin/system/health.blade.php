@extends('layouts.app')

@section('title', __('Kesehatan Sistem'))
@section('page_subtitle', __('Pemantauan real-time database, disk, antrean & integrasi'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0 text-body"><i class="fas fa-heart-pulse text-primary me-1.5"></i>{{ __('Pusat Kesehatan Sistem') }}</h4>
        <small class="text-secondary">{{ __('Diagnostik infrastruktur, antrean scheduler, dan status integrasi eksternal.') }}</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" id="btnPingTelegram" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
            <i class="fab fa-telegram me-1"></i>{{ __('Test Ping Telegram') }}
        </button>
        <form action="{{ route('admin.system.health.flush-cache') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                <i class="fas fa-rotate me-1"></i>{{ __('Segarkan Cache') }}
            </button>
        </form>
    </div>
</div>

<div id="pingResultBox" class="alert d-none rounded-4 mb-4 small fw-semibold"></div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-secondary fw-semibold">{{ __('Database') }}</small>
                    <span class="badge rounded-pill {{ $metrics['db_connected'] ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' }}">
                        {{ $metrics['db_connected'] ? __('Online') : __('Offline') }}
                    </span>
                </div>
                <div class="fs-5 fw-bold text-body">{{ strtoupper($metrics['db_driver']) }} · {{ $metrics['db_latency_ms'] }} ms</div>
                <small class="text-muted">{{ __('Ukuran') }}: {{ $metrics['db_size_label'] }}</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-secondary fw-semibold">{{ __('Penyimpanan Disk') }}</small>
                    <span class="badge rounded-pill {{ $metrics['disk_used_percent'] < 85 ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' }}">
                        {{ $metrics['disk_used_percent'] }}%
                    </span>
                </div>
                <div class="fs-5 fw-bold text-body">{{ $metrics['disk_free_label'] }} {{ __('tersisa') }}</div>
                <small class="text-muted">{{ __('Log Laravel') }}: {{ $metrics['log_size_label'] }}</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-secondary fw-semibold">{{ __('Antrean Scheduler') }}</small>
                    <span class="badge rounded-pill {{ $metrics['expired_stuck_orders'] === 0 ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }}">
                        {{ $metrics['expired_stuck_orders'] === 0 ? __('Sehat') : __('Perlu Cron') }}
                    </span>
                </div>
                <div class="fs-5 fw-bold text-body">{{ $metrics['expired_stuck_orders'] }} {{ __('order expired') }}</div>
                <small class="text-muted">{{ $metrics['ready_held_funds'] }} {{ __('held funds siap rilis') }}</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-secondary fw-semibold">{{ __('Asset Bundle Vite') }}</small>
                    <span class="badge rounded-pill {{ $metrics['vite_bundle_built'] ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }}">
                        {{ $metrics['vite_bundle_built'] ? __('Lokal Aktif') : __('Fallback CDN') }}
                    </span>
                </div>
                <div class="fs-5 fw-bold text-body">PHP {{ $metrics['php_version'] }}</div>
                <small class="text-muted">Laravel {{ $metrics['laravel_version'] }}</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-4">
        <h6 class="fw-bold text-body mb-3">{{ __('Status Konfigurasi & Layanan') }}</h6>
        <div class="row g-3 small">
            <div class="col-md-4">
                <div class="p-3 rounded-4 border" style="border-color: var(--outline-variant) !important;">
                    <div class="text-muted mb-1">{{ __('Environment & Debug') }}</div>
                    <strong class="text-body">{{ strtoupper($metrics['app_env']) }} · Debug {{ $metrics['app_debug'] ? 'ON' : 'OFF' }}</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 rounded-4 border" style="border-color: var(--outline-variant) !important;">
                    <div class="text-muted mb-1">{{ __('Cache / Session / Queue') }}</div>
                    <strong class="text-body">{{ $metrics['cache_driver'] }} / {{ $metrics['session_driver'] }} / {{ $metrics['queue_driver'] }}</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 rounded-4 border" style="border-color: var(--outline-variant) !important;">
                    <div class="text-muted mb-1">{{ __('Integrasi Bot & VPN') }}</div>
                    <strong class="text-body">
                        Telegram: {{ $metrics['telegram_configured'] ? __('Aktif') : __('Belum Diatur') }} ·
                        VPN SSH: {{ $metrics['vpn_configured'] ? __('Aktif') : __('Belum Diatur') }}
                    </strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('btnPingTelegram');
    const box = document.getElementById('pingResultBox');
    if (!btn || !box) return;

    btn.addEventListener('click', function() {
        btn.disabled = true;
        box.className = 'alert alert-info rounded-4 mb-4 small fw-semibold';
        box.textContent = @json(__('Menguji koneksi ke Telegram Bot API...'));

        fetch(@json(route('admin.system.health.ping-telegram')), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            box.className = 'alert ' + (data.ok ? 'alert-success' : 'alert-danger') + ' rounded-4 mb-4 small fw-semibold';
            box.textContent = data.message || 'Done';
        })
        .catch(() => {
            box.className = 'alert alert-danger rounded-4 mb-4 small fw-semibold';
            box.textContent = @json(__('Gagal melakukan ping ke endpoint diagnostik.'));
        })
        .finally(() => { btn.disabled = false; });
    });
});
</script>
@endsection
