@extends('layouts.app')

@section('title', __('Akun VPN Saya'))
@section('page_subtitle', __('Kelola, Perpanjang, Config & QR Code Akun VPN Anda'))

@push('styles')
<style>
    .dropdown-menu { z-index: 1060 !important; }
    .m3-vpn-avatar {
        position: relative;
        width: 40px;
        height: 40px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary);
        font-size: 0.95rem;
    }
    .m3-sheet-action-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .m3-sheet-action-tile {
        appearance: none;
        width: 100%;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 16px;
        border: 1px solid var(--outline-variant, var(--bs-border-color));
        background: var(--surface-container-low, var(--bs-body-bg));
        color: var(--on-surface, var(--bs-body-color));
        text-align: left;
        cursor: pointer;
        transition: background-color 0.15s ease, transform 0.12s ease;
    }
    .m3-sheet-action-tile:active { transform: scale(0.985); }
    .m3-sheet-action-tile:hover { background: var(--surface-container, var(--bs-tertiary-bg)); }
    .m3-sheet-action-tile.is-danger {
        border-color: rgba(220, 53, 69, 0.25);
        background: rgba(220, 53, 69, 0.06);
        color: var(--bs-danger);
    }
    .m3-sheet-action-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.95rem;
    }
    .m3-sheet-action-text {
        flex: 1 1 auto;
        min-width: 0;
        line-height: 1.3;
    }
    .m3-sheet-action-title {
        font-weight: 700;
        font-size: 0.88rem;
        display: block;
    }
    .m3-sheet-action-sub {
        font-size: 0.74rem;
        opacity: 0.75;
        display: block;
        margin-top: 2px;
    }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="fw-bold text-body mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-shield-halved text-primary"></i>
            <span>{{ __('Akun VPN Saya') }}</span>
        </h4>
        <p class="text-muted small mb-0">{{ __('Lihat konfigurasi/QR, perpanjang, tangguhkan sementara, atau kelola akun VPN milik Anda.') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold btn-sm d-inline-flex align-items-center gap-1.5">
            <i class="fas fa-store"></i>
            <span>{{ __('Katalog') }}</span>
        </a>
        @if($vpnProducts->isNotEmpty())
        <button type="button" class="btn btn-primary rounded-pill px-3 fw-semibold btn-sm d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#customerCreateVpnModal">
            <i class="fas fa-plus"></i>
            <span>{{ __('Buat Akun VPN') }}</span>
        </button>
        @endif
    </div>
</div>

{{-- KPI Summary --}}
@php
    $custTrafficRangeLabel = match($trafficRange ?? 'all') {
        'today' => __('Hari Ini'),
        '7d' => __('7 Hari'),
        '30d' => __('30 Hari'),
        'month' => __('Bulan Ini'),
        default => __('Semua Waktu'),
    };
@endphp
<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-2 g-md-3 mb-3">
    <div class="col">
        <div class="card h-100"><div class="card-body p-3">
            <div class="text-muted small fw-semibold">{{ __('Total Akun') }}</div>
            <div class="fs-4 fw-bold text-body">{{ $stats['total'] }}</div>
        </div></div>
    </div>
    <div class="col">
        <div class="card h-100"><div class="card-body p-3">
            <div class="text-muted small fw-semibold">{{ __('Aktif') }}</div>
            <div class="fs-4 fw-bold text-success">{{ $stats['active'] }}</div>
            @if(($stats['expiring_soon'] ?? 0) > 0)
                <div class="text-warning-emphasis fw-semibold" style="font-size: 0.7rem;">
                    <i class="fas fa-clock me-0.5"></i>{{ __(':count hampir habis', ['count' => $stats['expiring_soon']]) }}
                </div>
            @endif
        </div></div>
    </div>
    <div class="col">
        <div class="card h-100"><div class="card-body p-3">
            <div class="text-muted small fw-semibold">{{ __('Disuspend') }}</div>
            <div class="fs-4 fw-bold text-warning">{{ $stats['suspended'] }}</div>
        </div></div>
    </div>
    <div class="col">
        <div class="card h-100 border-primary-subtle"><div class="card-body p-3">
            <div class="text-muted small fw-semibold d-flex align-items-center justify-content-between gap-1">
                <span>{{ __('Total Traffic') }}</span>
                <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size: 0.65rem;">{{ $custTrafficRangeLabel }}</span>
            </div>
            <div class="fs-5 fw-bold text-primary">{{ \App\Models\VpnAccount::formatBytes((int) ($trafficSummary['total_bytes'] ?? 0)) }}</div>
            <div class="d-flex flex-wrap gap-2 mt-0.5" style="font-size: 0.72rem;">
                <span class="text-info fw-semibold" title="{{ __('Upload') }}"><i class="fas fa-arrow-up me-0.5"></i>{{ \App\Models\VpnAccount::formatBytes((int) ($trafficSummary['total_uplink'] ?? 0)) }}</span>
                <span class="text-success fw-semibold" title="{{ __('Download') }}"><i class="fas fa-arrow-down me-0.5"></i>{{ \App\Models\VpnAccount::formatBytes((int) ($trafficSummary['total_downlink'] ?? 0)) }}</span>
            </div>
        </div></div>
    </div>
    <div class="col col-12 col-md-6 col-xl">
        <div class="card h-100"><div class="card-body p-3">
            <div class="text-muted small fw-semibold">{{ __('Saldo Dompet') }}</div>
            <div class="fs-5 fw-bold text-primary">Rp {{ number_format((int) (Auth::user()->customer_wallet_balance ?? 0), 0, ',', '.') }}</div>
        </div></div>
    </div>
</div>

@if($stats['admin_suspended'] > 0)
<div class="alert alert-danger border-0 mb-3 d-flex align-items-start justify-content-between flex-wrap gap-2" style="border-radius: 16px;">
    <div class="d-flex align-items-start gap-2.5 small">
        <i class="fas fa-lock fs-5 mt-0.5"></i>
        <div>
            <div class="fw-bold">{{ __('Ada :count Akun Disuspend oleh Admin', ['count' => $stats['admin_suspended']]) }}</div>
            <div>{{ __('Akun yang ditangguhkan oleh Admin tidak dapat dibuka sendiri. Silakan hubungi Admin melalui Chat untuk meminta pembukaan akses kembali.') }}</div>
        </div>
    </div>
    @if($defaultAdminId)
    <a href="{{ route('chat.index', ['contact_id' => $defaultAdminId]) }}" class="btn btn-danger btn-sm rounded-pill px-3 fw-semibold align-self-center">
        <i class="fas fa-comments me-1"></i>{{ __('Chat Admin') }}
    </a>
    @endif
</div>
@endif

{{-- Filter Bar --}}
<div class="card mb-3">
    <div class="card-body p-3">
        <form action="{{ route('vpn.my-accounts') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="{{ __('Cari username VPN Anda...') }}">
            </div>
            <div class="col-6 col-md-2">
                <select name="protocol" class="form-select form-select-sm">
                    <option value="">{{ __('Semua Protokol') }}</option>
                    @foreach(['vmess', 'vless', 'trojan', 'shadowsocks', 'ssh'] as $p)
                        <option value="{{ $p }}" {{ request('protocol') === $p ? 'selected' : '' }}>{{ strtoupper($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Aktif') }}</option>
                    <option value="expiring_soon" {{ request('status') === 'expiring_soon' ? 'selected' : '' }}>{{ __('Hampir Expired (≤ 3 Hari)') }}</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>{{ __('Disuspend') }}</option>
                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>{{ __('Expired') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="traffic_range" class="form-select form-select-sm" title="{{ __('Rentang Waktu Traffic') }}">
                    <option value="all" {{ ($trafficRange ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('Traffic: Semua Waktu') }}</option>
                    <option value="today" {{ ($trafficRange ?? 'all') === 'today' ? 'selected' : '' }}>{{ __('Traffic: Hari Ini') }}</option>
                    <option value="7d" {{ ($trafficRange ?? 'all') === '7d' ? 'selected' : '' }}>{{ __('Traffic: 7 Hari Terakhir') }}</option>
                    <option value="30d" {{ ($trafficRange ?? 'all') === '30d' ? 'selected' : '' }}>{{ __('Traffic: 30 Hari Terakhir') }}</option>
                    <option value="month" {{ ($trafficRange ?? 'all') === 'month' ? 'selected' : '' }}>{{ __('Traffic: Bulan Ini') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1.5">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill flex-grow-1 fw-semibold">{{ __('Cari') }}</button>
                @if(request()->hasAny(['search', 'protocol', 'status']) || ($trafficRange ?? 'all') !== 'all')
                    <a href="{{ route('vpn.my-accounts') }}" class="btn btn-light btn-sm rounded-pill border"><i class="fas fa-times"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

@if($accounts->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                <i class="fas fa-shield-halved fs-3"></i>
            </div>
            <h6 class="fw-bold text-body mb-1">{{ __('Belum Ada Akun VPN') }}</h6>
            <p class="text-muted small mb-3">
                @if($vpnProducts->isNotEmpty())
                    {{ __('Buat akun VPN instan menggunakan saldo dompet atau beli paket VPN di katalog produk.') }}
                @else
                    {{ __('Akun VPN yang Anda beli dari katalog atau dibuatkan oleh Admin akan muncul otomatis di sini.') }}
                @endif
            </p>
            @if($vpnProducts->isNotEmpty())
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#customerCreateVpnModal">
                    <i class="fas fa-plus me-1"></i>{{ __('Buat Akun VPN Pertama') }}
                </button>
            @else
                <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Lihat Katalog Produk') }}</a>
            @endif
        </div>
    </div>
@else
    {{-- Desktop Table --}}
    <div class="card d-none d-lg-block">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">{{ __('Akun VPN') }}</th>
                        <th>{{ __('Server / Domain') }}</th>
                        <th>{{ __('Kuota / IP') }}</th>
                        <th>{{ __('Traffic (↑ / ↓)') }}</th>
                        <th>{{ __('Masa Aktif') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end pe-3">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $acc)
                    @php
                        $adminSuspended = $acc->isAdminSuspended();
                        $chatTargetId = $acc->suspended_by_user_id ?: $defaultAdminId;
                        $isExpired = $acc->expired_at && $acc->expired_at->isPast();
                        $expHl = $acc->getExpiryHighlight();
                        $quotaInfo = $acc->getQuotaUsage();
                        $accTraffic = $trafficSummary['per_account'][$acc->id] ?? ['uplink' => 0, 'downlink' => 0, 'total' => 0];
                        $statusLabel = match(true) {
                            $adminSuspended => __('Disuspend Admin'),
                            $acc->status === 'suspended' => __('Ditangguhkan Mandiri'),
                            $acc->status === 'failed' => __('Gagal Provisioning'),
                            $isExpired || $acc->status === 'expired' => __('Expired'),
                            default => __('Aktif'),
                        };
                    @endphp
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold text-body font-monospace">{{ $acc->username }}</div>
                            <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size: 0.68rem;">{{ strtoupper($acc->protocol) }}</span>
                        </td>
                        <td class="small">
                            <div class="fw-semibold text-body">{{ $acc->vpnServer?->name ?: __('Server VPN') }}</div>
                            <div class="text-muted font-monospace" style="font-size: 0.72rem;">{{ $acc->display_domain }}</div>
                        </td>
                        <td class="small" style="min-width: 130px;">
                            <div class="d-flex align-items-center justify-content-between gap-1">
                                <span>{{ $quotaInfo['limited'] ? $acc->quota_gb . ' GB' : 'Unlimited' }}</span>
                                <span class="text-muted">{{ max(1, (int) ($acc->ip_limit ?? 1)) }} IP</span>
                            </div>
                            @if($quotaInfo['limited'])
                                <div class="progress mt-1" style="height: 6px; border-radius: 999px;" title="{{ __('Terpakai :used / :tot (:pct%)', ['used' => \App\Models\VpnAccount::formatBytes($quotaInfo['used_bytes']), 'tot' => $acc->quota_gb . ' GB', 'pct' => $quotaInfo['used_percent']]) }}">
                                    <div class="progress-bar {{ $quotaInfo['bar_class'] }}" role="progressbar" style="width: {{ $quotaInfo['remaining_percent'] }}%;"></div>
                                </div>
                                <div class="{{ $quotaInfo['text_class'] }} mt-0.5" style="font-size: 0.68rem;">
                                    {{ __('Sisa :rem', ['rem' => \App\Models\VpnAccount::formatBytes($quotaInfo['remaining_bytes'])]) }} ({{ $quotaInfo['remaining_percent'] }}%)
                                </div>
                            @endif
                        </td>
                        <td class="small" style="min-width: 118px;">
                            <div class="text-info fw-semibold" style="font-size: 0.76rem;" title="{{ __('Upload') }}">
                                <i class="fas fa-arrow-up me-1"></i>{{ \App\Models\VpnAccount::formatBytes((int) $accTraffic['uplink']) }}
                            </div>
                            <div class="text-success fw-semibold" style="font-size: 0.76rem;" title="{{ __('Download') }}">
                                <i class="fas fa-arrow-down me-1"></i>{{ \App\Models\VpnAccount::formatBytes((int) $accTraffic['downlink']) }}
                            </div>
                        </td>
                        <td class="small">
                            @if($expHl['level'] !== 'normal')
                                <div class="d-inline-block px-2 py-1 rounded-3 {{ $expHl['pill_class'] }}">
                                    <div class="fw-bold" style="font-size: 0.78rem;">{{ $acc->expired_at ? $acc->expired_at->format('d M Y') : '-' }}</div>
                                    <div style="font-size: 0.68rem;"><i class="fas {{ $expHl['level'] === 'warning' ? 'fa-clock' : 'fa-exclamation-triangle' }} me-1"></i>{{ $expHl['label'] }}</div>
                                </div>
                            @else
                                <div class="fw-semibold text-body">{{ $acc->expired_at ? $acc->expired_at->format('d M Y') : '-' }}</div>
                                @if($acc->expired_at)
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $expHl['label'] }}</div>
                                @endif
                            @endif
                        </td>
                        <td>
                            @if($adminSuspended)
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1"><i class="fas fa-lock me-1"></i>{{ __('Disuspend Admin') }}</span>
                                @if($acc->suspended_reason)
                                    <div class="text-danger small mt-0.5" style="font-size: 0.7rem;">{{ $acc->suspended_reason }}</div>
                                @endif
                            @elseif($acc->status === 'suspended')
                                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5 py-1">{{ __('Ditangguhkan Mandiri') }}</span>
                            @elseif($acc->status === 'failed')
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1">{{ __('Gagal Provisioning') }}</span>
                            @elseif($isExpired || $acc->status === 'expired')
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1">{{ __('Expired') }}</span>
                            @else
                                <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1">{{ __('Aktif') }}</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            @if($adminSuspended)
                                @if($chatTargetId)
                                <a href="{{ route('chat.index', ['contact_id' => $chatTargetId]) }}" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold">
                                    <i class="fas fa-comments me-1"></i>{{ __('Minta Buka Suspend via Chat Admin') }}
                                </a>
                                @else
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1.5">{{ __('Hubungi Admin') }}</span>
                                @endif
                            @else
                                @php $accPricing = $renewPricing[$acc->id] ?? null; @endphp
                                <div class="d-inline-flex align-items-center justify-content-end gap-1.5">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 fw-semibold btn-renew-my-vpn"
                                            data-account-id="{{ $acc->id }}"
                                            data-action="{{ route('vpn.renew', $acc->id) }}"
                                            data-username="{{ $acc->username }}"
                                            data-expired-iso="{{ $acc->expired_at ? $acc->expired_at->toIso8601String() : '' }}"
                                            data-base-price="{{ (int) ($accPricing['base_price'] ?? 0) }}"
                                            data-base-days="{{ (int) ($accPricing['base_days'] ?? 30) }}"
                                            title="{{ __('Perpanjang') }}">
                                        <i class="fas fa-calendar-plus me-1"></i>{{ __('Perpanjang') }}
                                    </button>
                                    {{-- Desktop Hamburger Dropdown Menu (strategy: fixed prevents table-responsive clipping) --}}
                                    <div class="dropdown d-inline-block">
                                        <button type="button"
                                                class="btn btn-sm btn-light border rounded-pill px-2.5"
                                                data-bs-toggle="dropdown"
                                                data-bs-popper-config='{"strategy": "fixed"}'
                                                aria-expanded="false"
                                                aria-label="{{ __('Menu Aksi') }}"
                                                title="{{ __('Menu Aksi Lainnya') }}">
                                            <i class="fas fa-bars"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-1.5" style="border-radius: 14px; min-width: 225px; z-index: 1060;">
                                            <li>
                                                <button type="button"
                                                        class="dropdown-item py-2 d-flex align-items-center gap-2 btn-direct-my-vpn-config"
                                                        data-title="{{ strtoupper($acc->protocol) }} — {{ $acc->username }}"
                                                        data-config-url="{{ route('vpn.config', $acc->id) }}"
                                                        data-payload='@json($acc->config_payload)'
                                                        data-text="{{ $acc->config_link }}">
                                                    <i class="fas fa-qrcode text-info fa-fw"></i>
                                                    <span>{{ __('Lihat Config & QR Code') }}</span>
                                                </button>
                                            </li>
                                            @if($acc->status === 'suspended')
                                            <li>
                                                <form action="{{ route('vpn.unsuspend', $acc->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2 text-success">
                                                        <i class="fas fa-play fa-fw"></i>
                                                        <span>{{ __('Aktifkan Kembali') }}</span>
                                                    </button>
                                                </form>
                                            </li>
                                            @else
                                            <li>
                                                <form action="{{ route('vpn.suspend', $acc->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2 text-warning-emphasis">
                                                        <i class="fas fa-pause text-warning fa-fw"></i>
                                                        <span>{{ __('Tangguhkan Sementara') }}</span>
                                                    </button>
                                                </form>
                                            </li>
                                            @endif
                                            <li>
                                                <form action="{{ route('vpn.sync', $acc->id) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2">
                                                        <i class="fas fa-rotate text-secondary fa-fw"></i>
                                                        <span>{{ __('Sync dari Server') }}</span>
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <form action="{{ route('vpn.destroy', $acc->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, @js(__('Hapus permanen akun VPN ini? Tindakan ini tidak dapat dibatalkan.')), @js(__('Hapus Akun VPN?')))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2 text-danger">
                                                        <i class="fas fa-trash fa-fw"></i>
                                                        <span>{{ __('Hapus Akun Permanen') }}</span>
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile Collapsible Compact List --}}
    <div class="d-lg-none m3-collapsible-list" id="customerVpnAccordion">
        @foreach($accounts as $acc)
        @php
            $adminSuspended = $acc->isAdminSuspended();
            $chatTargetId = $acc->suspended_by_user_id ?: $defaultAdminId;
            $isExpired = $acc->expired_at && $acc->expired_at->isPast();
            $expHl = $acc->getExpiryHighlight();
            $quotaInfo = $acc->getQuotaUsage();
            $accTraffic = $trafficSummary['per_account'][$acc->id] ?? ['uplink' => 0, 'downlink' => 0, 'total' => 0];
            $dotColor = match(true) {
                $adminSuspended || $acc->status === 'failed' => 'bg-danger',
                $acc->status === 'suspended' => 'bg-warning',
                $isExpired || $acc->status === 'expired' => 'bg-secondary',
                $expHl['level'] === 'critical' => 'bg-danger',
                $expHl['level'] === 'warning' => 'bg-warning',
                default => 'bg-success',
            };
            $statusLabel = match(true) {
                $adminSuspended => __('Disuspend Admin'),
                $acc->status === 'suspended' => __('Ditangguhkan Mandiri'),
                $acc->status === 'failed' => __('Gagal Provisioning'),
                $isExpired || $acc->status === 'expired' => __('Expired'),
                default => __('Aktif'),
            };
        @endphp
        @php $accPricing = $renewPricing[$acc->id] ?? null; @endphp
        <div class="m3-collapsible-card">
            <div class="m3-compact-row">
                <button type="button" class="m3-compact-trigger collapsed" data-bs-toggle="collapse" data-bs-target="#myVpnCollapse{{ $acc->id }}" aria-expanded="false">
                    <span class="m3-vpn-avatar">
                        <i class="fas fa-shield-halved"></i>
                        <span class="m3-status-dot {{ $dotColor }}"></span>
                    </span>
                    <div class="min-w-0 flex-grow-1 text-start">
                        <div class="fw-bold text-body font-monospace text-truncate d-flex align-items-center gap-1.5" style="font-size: 0.9rem;">
                            <span class="text-truncate">{{ $acc->username }}</span>
                            <span class="badge bg-primary-subtle text-primary rounded-pill flex-shrink-0" style="font-size: 0.62rem;">{{ strtoupper($acc->protocol) }}</span>
                            @if($expHl['level'] !== 'normal')
                                <span class="badge {{ $expHl['pill_class'] }} rounded-pill flex-shrink-0" style="font-size: 0.6rem;">{{ $expHl['label'] }}</span>
                            @endif
                        </div>
                        <div class="text-muted text-truncate" style="font-size: 0.74rem;">
                            @if($adminSuspended)
                                <span class="text-danger fw-semibold"><i class="fas fa-lock me-1"></i>{{ __('Disuspend Admin') }}</span>
                            @else
                                {{ $acc->display_domain }} &bull;
                                <span class="{{ $expHl['level'] !== 'normal' ? $expHl['text_class'] : '' }}">Exp: {{ $acc->expired_at ? $acc->expired_at->format('d M Y') : '-' }}</span>
                            @endif
                        </div>
                    </div>
                    <i class="fas fa-chevron-down m3-compact-chevron"></i>
                </button>
            </div>
            <div id="myVpnCollapse{{ $acc->id }}" class="collapse" data-bs-parent="#customerVpnAccordion">
                <div class="m3-collapsible-body">
                    <div class="small mb-2.5 d-flex flex-column gap-1">
                        <div><span class="text-muted">{{ __('Server:') }}</span> <strong>{{ $acc->vpnServer?->name ?: __('Server VPN') }}</strong> <span class="font-monospace text-muted">({{ $acc->display_domain }})</span></div>
                        <div>
                            <span class="text-muted">{{ __('Limit:') }}</span> {{ $quotaInfo['limited'] ? $acc->quota_gb . ' GB' : 'Unlimited' }} &bull; {{ max(1, (int) ($acc->ip_limit ?? 1)) }} IP
                            @if($quotaInfo['limited'])
                                <div class="progress mt-1" style="height: 6px; border-radius: 999px;">
                                    <div class="progress-bar {{ $quotaInfo['bar_class'] }}" role="progressbar" style="width: {{ $quotaInfo['remaining_percent'] }}%;"></div>
                                </div>
                                <div class="{{ $quotaInfo['text_class'] }}" style="font-size: 0.7rem;">{{ __('Sisa Kuota:') }} {{ \App\Models\VpnAccount::formatBytes($quotaInfo['remaining_bytes']) }} / {{ $acc->quota_gb }} GB ({{ $quotaInfo['remaining_percent'] }}%)</div>
                            @endif
                        </div>
                        <div>
                            <span class="text-muted">{{ __('Traffic') }} ({{ $custTrafficRangeLabel }}):</span>
                            <span class="text-info fw-semibold ms-1"><i class="fas fa-arrow-up"></i> {{ \App\Models\VpnAccount::formatBytes((int) $accTraffic['uplink']) }}</span>
                            <span class="text-success fw-semibold ms-2"><i class="fas fa-arrow-down"></i> {{ \App\Models\VpnAccount::formatBytes((int) $accTraffic['downlink']) }}</span>
                        </div>
                        <div><span class="text-muted">{{ __('Berakhir:') }}</span> <span class="{{ $expHl['level'] !== 'normal' ? $expHl['text_class'] : '' }}">{{ $acc->expired_at ? $acc->expired_at->format('d M Y H:i') : '-' }}</span></div>
                        @if($accPricing && ($accPricing['base_price'] ?? 0) > 0)
                        <div><span class="text-muted">{{ __('Tarif Acuan:') }}</span> <strong class="text-primary">Rp {{ number_format($accPricing['base_price'], 0, ',', '.') }} / {{ $accPricing['base_days'] }} {{ __('hari') }}</strong></div>
                        @endif
                    </div>

                    @if($adminSuspended)
                        <div class="alert alert-danger border-0 py-2 px-3 small mb-2.5" style="border-radius: 12px;">
                            <div class="fw-bold"><i class="fas fa-lock me-1"></i>{{ __('Akun Disuspend oleh Admin') }}</div>
                            @if($acc->suspended_reason)
                                <div class="mt-0.5">{{ __('Alasan:') }} {{ $acc->suspended_reason }}</div>
                            @endif
                            <div class="mt-1">{{ __('Anda wajib meminta pembukaan kembali akses melalui Chat kepada Admin.') }}</div>
                        </div>
                        @if($chatTargetId)
                        <a href="{{ route('chat.index', ['contact_id' => $chatTargetId]) }}" class="btn btn-sm btn-danger rounded-pill w-100 fw-semibold">
                            <i class="fas fa-comments me-1"></i>{{ __('Minta Buka Suspend via Chat Admin') }}
                        </a>
                        @endif
                    @else
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-primary rounded-pill flex-grow-1 fw-semibold py-2 btn-renew-my-vpn"
                                    data-account-id="{{ $acc->id }}"
                                    data-action="{{ route('vpn.renew', $acc->id) }}"
                                    data-username="{{ $acc->username }}"
                                    data-expired-iso="{{ $acc->expired_at ? $acc->expired_at->toIso8601String() : '' }}"
                                    data-base-price="{{ (int) ($accPricing['base_price'] ?? 0) }}"
                                    data-base-days="{{ (int) ($accPricing['base_days'] ?? 30) }}">
                                <i class="fas fa-calendar-plus me-1"></i>{{ __('Perpanjang') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5 flex-shrink-0 btn-open-my-vpn-sheet"
                                    data-username="{{ $acc->username }}"
                                    data-protocol="{{ strtoupper($acc->protocol) }}"
                                    data-server="{{ $acc->vpnServer?->name ?: __('Server VPN') }}"
                                    data-status="{{ $acc->status }}"
                                    data-status-label="{{ $statusLabel }}"
                                    data-config-url="{{ route('vpn.config', $acc->id) }}"
                                    data-payload='@json($acc->config_payload)'
                                    data-text="{{ $acc->config_link }}"
                                    data-suspend-url="{{ route('vpn.suspend', $acc->id) }}"
                                    data-unsuspend-url="{{ route('vpn.unsuspend', $acc->id) }}"
                                    data-sync-url="{{ route('vpn.sync', $acc->id) }}"
                                    data-delete-url="{{ route('vpn.destroy', $acc->id) }}">
                                <i class="fas fa-bars"></i>
                                <span>{{ __('Menu') }}</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($accounts->hasPages())
        <div class="mt-3">{{ $accounts->links() }}</div>
    @endif
@endif

@push('modals')
{{-- M3 Action Sheet Modal for Customer VPN Account Actions --}}
<div class="modal fade" id="myVpnActionSheetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-2">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title fw-bold font-monospace mb-0" id="mySheetVpnUser">-</h5>
                        <span class="badge bg-primary-subtle text-primary rounded-pill" id="mySheetVpnProto">-</span>
                    </div>
                    <div class="small text-muted mt-0.5" id="mySheetVpnMeta">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1 pb-4">
                <div class="m3-sheet-action-list">
                    <button type="button" class="m3-sheet-action-tile" id="mySheetBtnConfig">
                        <span class="m3-sheet-action-icon bg-info-subtle text-info">
                            <i class="fas fa-qrcode"></i>
                        </span>
                        <span class="m3-sheet-action-text">
                            <span class="m3-sheet-action-title">{{ __('Lihat Config & QR Code') }}</span>
                            <span class="m3-sheet-action-sub">{{ __('Salin link konfigurasi atau pindai QR Code') }}</span>
                        </span>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </button>

                    <form id="mySheetFormSuspend" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="m3-sheet-action-tile">
                            <span class="m3-sheet-action-icon bg-warning-subtle text-warning-emphasis">
                                <i class="fas fa-pause"></i>
                            </span>
                            <span class="m3-sheet-action-text">
                                <span class="m3-sheet-action-title">{{ __('Tangguhkan Sementara') }}</span>
                                <span class="m3-sheet-action-sub">{{ __('Nonaktifkan sementara, bisa diaktifkan lagi kapan saja') }}</span>
                            </span>
                            <i class="fas fa-chevron-right text-muted small"></i>
                        </button>
                    </form>

                    <form id="mySheetFormUnsuspend" method="POST" class="d-none m-0">
                        @csrf
                        <button type="submit" class="m3-sheet-action-tile">
                            <span class="m3-sheet-action-icon bg-success-subtle text-success">
                                <i class="fas fa-play"></i>
                            </span>
                            <span class="m3-sheet-action-text">
                                <span class="m3-sheet-action-title">{{ __('Aktifkan Kembali') }}</span>
                                <span class="m3-sheet-action-sub">{{ __('Pulihkan akses koneksi akun VPN Anda') }}</span>
                            </span>
                            <i class="fas fa-chevron-right text-muted small"></i>
                        </button>
                    </form>

                    <form id="mySheetFormSync" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="m3-sheet-action-tile">
                            <span class="m3-sheet-action-icon bg-secondary-subtle text-secondary">
                                <i class="fas fa-rotate"></i>
                            </span>
                            <span class="m3-sheet-action-text">
                                <span class="m3-sheet-action-title">{{ __('Sync dari Server') }}</span>
                                <span class="m3-sheet-action-sub">{{ __('Perbarui detail konfigurasi terbaru dari server VPN') }}</span>
                            </span>
                            <i class="fas fa-chevron-right text-muted small"></i>
                        </button>
                    </form>

                    <form id="mySheetFormDelete" method="POST" class="m-0" onsubmit="bootstrap.Modal.getInstance(document.getElementById('myVpnActionSheetModal'))?.hide(); confirmAction(event, @js(__('Hapus permanen akun VPN ini? Tindakan ini tidak dapat dibatalkan.')), @js(__('Hapus Akun VPN?')))">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="m3-sheet-action-tile is-danger">
                            <span class="m3-sheet-action-icon bg-danger-subtle text-danger">
                                <i class="fas fa-trash"></i>
                            </span>
                            <span class="m3-sheet-action-text">
                                <span class="m3-sheet-action-title">{{ __('Hapus Akun Permanen') }}</span>
                                <span class="m3-sheet-action-sub">{{ __('Menghapus akun dari server dan daftar Anda') }}</span>
                            </span>
                            <i class="fas fa-chevron-right small opacity-75"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Customer Create VPN Modal (Strictly Gated when Active VPN Products Exist) --}}
@if($vpnProducts->isNotEmpty())
<div class="modal fade" id="customerCreateVpnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle text-primary me-2"></i>{{ __('Buat Akun VPN Baru') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('vpn.store') }}" method="POST">
                @csrf
                <div class="modal-body pt-2">
                    <div class="alert alert-info border-0 py-2 px-3 small mb-3" style="border-radius: 12px;">
                        <i class="fas fa-wallet me-1"></i>{{ __('Pembayaran instan memotong Saldo Dompet Anda:') }} <strong>Rp {{ number_format((int) (Auth::user()->customer_wallet_balance ?? 0), 0, ',', '.') }}</strong>.
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Pilih Paket VPN') }} <span class="text-danger">*</span></label>
                        <select name="product_id" id="custCreateProduct" class="form-select" required>
                            @foreach($vpnProducts as $vp)
                                @php $vpProtos = $vp->getAvailableVpnProtocols(); @endphp
                                <option value="{{ $vp->id }}"
                                        data-protocol="{{ $vpProtos[0] ?? $vp->vpn_protocol }}"
                                        data-protocols='@json($vpProtos)'
                                        data-sni-mode="{{ $vp->vpn_sni_mode ?? '3' }}"
                                        data-check-url="{{ route('catalog.check-vpn-username', $vp->id) }}">
                                    {{ $vp->name }} — {{ strtoupper(implode('/', $vpProtos)) }} ({{ $vp->vpn_duration_days }} {{ __('hari') }}) — {{ $vp->formatted_price }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2.5 d-none" id="custCreateProtoWrap">
                        <label class="form-label small fw-bold">{{ __('Protokol VPN') }} <span class="text-danger">*</span></label>
                        <select name="protocol" id="custCreateProto" class="form-select"></select>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Username VPN') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="username" id="custCreateUsername" class="form-control font-monospace" required autocomplete="off" pattern="[a-zA-Z0-9_-]{3,24}" placeholder="nama_anda01">
                            <span class="input-group-text bg-white" id="custUsernameIndicator">
                                <i class="fas fa-user-check text-muted"></i>
                            </span>
                        </div>
                        <small id="custUsernameStatus" class="form-text text-muted">{{ __('Ketersediaan username diperiksa otomatis saat Anda mengetik.') }}</small>
                    </div>
                    <div class="mb-2.5 d-none" id="custSshPassWrap">
                        <label class="form-label small fw-bold">{{ __('Password SSH') }} <span class="text-danger">*</span></label>
                        <input type="text" name="password" id="custSshPassword" class="form-control font-monospace" minlength="3" maxlength="64" placeholder="{{ __('Minimal 3 karakter, tanpa spasi') }}">
                    </div>
                    <div class="mb-0" id="custSniWrap">
                        <label class="form-label small fw-bold">{{ __('Preset / Custom SNI') }}</label>
                        <select name="sni_mode" id="custCreateSniMode" class="form-select">
                            <option value="3">{{ __('Tanpa Konfigurasi (Default SNI)') }}</option>
                            <option value="1">Zoom (support.zoom.us — Wildcard)</option>
                            <option value="2">Iflix (live.iflix.com)</option>
                        </select>
                        <div class="form-text small">{{ __('Pilih profil SNI sesuai kebutuhan koneksi Anda.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnSubmitCustCreateVpn">{{ __('Buat & Aktifkan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Customer Renew Modal with Quick-Preset (+1, +7, +30, +60) & Expiry Preview --}}
<div class="modal fade" id="customerRenewVpnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="fas fa-calendar-plus text-primary me-2"></i>{{ __('Perpanjang Akun VPN') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="customerRenewForm" method="POST">
                @csrf
                <div class="modal-body pt-2">
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Username') }}</label>
                        <input type="text" id="custRenewUsername" class="form-control font-monospace" readonly>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">{{ __('Tambah Durasi (Hari)') }} <span class="text-danger">*</span></label>
                        <input type="number" name="days" id="custRenewDaysInput" class="form-control mb-2" value="30" min="1" max="365" required>
                        <div class="d-flex flex-wrap gap-1.5 mb-2">
                            @foreach([1, 7, 30, 60] as $preset)
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold btn-cust-renew-preset {{ $preset === 30 ? 'active' : '' }}" data-days="{{ $preset }}">
                                +{{ $preset }} {{ __('Hari') }}
                            </button>
                            @endforeach
                        </div>
                        <div class="p-3 rounded-4 border small d-flex flex-column gap-1.5 mb-2" style="background: var(--surface-container-low);">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted">{{ __('Estimasi Expired Baru:') }}</span>
                                <strong class="text-body" id="custRenewExpiryPreview">-</strong>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted">{{ __('Biaya Perpanjangan:') }}</span>
                                <strong class="text-primary fs-6" id="custRenewCostPreview">-</strong>
                            </div>
                            <div class="d-flex align-items-center justify-content-between border-top pt-1.5 mt-0.5">
                                <span class="text-muted">{{ __('Saldo Dompet Anda:') }}</span>
                                <span class="fw-semibold text-body">Rp {{ number_format((int) (Auth::user()->customer_wallet_balance ?? 0), 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <div id="custRenewPayMethodWrap" class="mb-2.5">
                            <label class="form-label small fw-bold mb-1.5">{{ __('Metode Pembayaran') }} <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="d-flex align-items-start gap-2 p-2.5 rounded-4 border h-100" id="custRenewWalletCard" style="cursor: pointer; background: var(--surface-container-lowest);">
                                        <input class="form-check-input mt-0.5 flex-shrink-0" type="radio" name="payment_method" id="custRenewPayWallet" value="wallet" checked>
                                        <span class="min-w-0">
                                            <span class="d-block small fw-bold text-body"><i class="fas fa-wallet text-primary me-1"></i>{{ __('Dompet Saldo') }}</span>
                                            <span class="d-block text-muted" style="font-size: 0.72rem;" id="custRenewWalletHint">{{ __('Langsung aktif instan') }}</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <label class="d-flex align-items-start gap-2 p-2.5 rounded-4 border h-100" id="custRenewQrisCard" style="cursor: pointer; background: var(--surface-container-lowest);">
                                        <input class="form-check-input mt-0.5 flex-shrink-0" type="radio" name="payment_method" id="custRenewPayQris" value="qris">
                                        <span class="min-w-0">
                                            <span class="d-block small fw-bold text-body"><i class="fas fa-qrcode text-success me-1"></i>{{ __('Bayar QRIS') }}</span>
                                            <span class="d-block text-muted" style="font-size: 0.72rem;">{{ __('Scan QRIS otomatis') }}</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div id="custRenewNoPriceAlert" class="alert alert-warning border-0 py-2 px-3 small d-none mb-2" style="border-radius: 12px;">
                            <div class="fw-bold"><i class="fas fa-circle-info me-1"></i>{{ __('Tarif perpanjangan belum ditetapkan Admin') }}</div>
                            <div class="mt-0.5">{{ __('Silakan hubungi Admin melalui Chat untuk memperpanjang akun VPN ini.') }}</div>
                            @if($defaultAdminId)
                            <a href="{{ route('chat.index', ['contact_id' => $defaultAdminId]) }}" class="btn btn-warning btn-sm rounded-pill mt-2 px-3 fw-semibold">
                                <i class="fas fa-comments me-1"></i>{{ __('Chat Admin') }}
                            </a>
                            @endif
                        </div>
                        <div id="custRenewLowBalanceAlert" class="alert alert-info border-0 py-2 px-3 small d-none mb-2" style="border-radius: 12px;">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                <span><i class="fas fa-info-circle me-1"></i>{{ __('Saldo dompet kurang untuk durasi ini. Gunakan QRIS atau isi saldo.') }}</span>
                                <a href="{{ route('wallet.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">{{ __('Isi Saldo') }}</a>
                            </div>
                        </div>
                        <div class="form-text small">{{ __('Biaya perpanjangan dihitung proporsional dari harga pembelian akun. Pilih Dompet Saldo untuk instan atau QRIS untuk bayar via e-wallet/bank.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnSubmitCustRenew">{{ __('Perpanjang Sekarang') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Customer Config & QR Modal (with Lazy Auto-Fetch) --}}
<div class="modal fade" id="myVpnConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold" id="myVpnConfigTitle">{{ __('Konfigurasi VPN') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="myVpnConfigLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">{{ __('Menarik detail konfigurasi dari server VPN...') }}</div>
                </div>
                <div id="myVpnConfigContent">
                    <div id="myVpnQrWrap" class="text-center mb-3 d-none">
                        <canvas id="myVpnQrCanvas" width="210" height="210" class="rounded-4 border p-2 bg-white"></canvas>
                    </div>
                    <div id="myVpnConfigSniWrap" class="mb-3 d-none">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-sliders text-primary me-1"></i>{{ __('Preset Mode SNI / Payload') }}</label>
                        <select id="myVpnConfigSniSelect" class="form-select form-select-sm">
                            <option value="3">{{ __('Tanpa Konfigurasi (Default SNI)') }}</option>
                            <option value="1">Zoom (support.zoom.us — Wildcard)</option>
                            <option value="2">Iflix (live.iflix.com)</option>
                        </select>
                    </div>
                    <div id="myVpnLinksWrap" class="d-flex flex-column gap-2 mb-3"></div>
                    <label class="form-label small fw-bold text-muted">{{ __('Detail Konfigurasi') }}</label>
                    <textarea id="myVpnFullText" class="form-control font-monospace small" rows="6" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-1 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="btnRefreshMyVpnConfig" title="{{ __('Tarik Ulang dari Server') }}">
                    <i class="fas fa-rotate me-1"></i>{{ __('Refresh') }}
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary rounded-pill px-3" id="btnCopyMyVpnFull"><i class="fas fa-copy me-1"></i>{{ __('Salin Semua') }}</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    function escHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    // 1. Create Modal: Multi-protocol selector + SSH password toggle + Automatic Debounced Live Username Check
    const prodSelect = document.getElementById('custCreateProduct');
    const custProtoWrap = document.getElementById('custCreateProtoWrap');
    const custProtoSelect = document.getElementById('custCreateProto');
    const sshWrap = document.getElementById('custSshPassWrap');
    const sshInput = document.getElementById('custSshPassword');
    const custSniWrap = document.getElementById('custSniWrap');
    const custSniSelect = document.getElementById('custCreateSniMode');
    const custUserInput = document.getElementById('custCreateUsername');
    const custUserStatus = document.getElementById('custUsernameStatus');
    const custUserIndicator = document.getElementById('custUsernameIndicator');
    const custCreateSubmitBtn = document.getElementById('btnSubmitCustCreateVpn');

    const syncProductProtocols = () => {
        if (!prodSelect || !custProtoSelect || !custProtoWrap) return;
        const opt = prodSelect.options[prodSelect.selectedIndex];
        let protos = [];
        try {
            protos = JSON.parse(opt?.dataset?.protocols || '[]');
        } catch (e) { protos = []; }
        if (!Array.isArray(protos) || protos.length === 0) {
            protos = [opt?.dataset?.protocol || 'vmess'];
        }
        custProtoSelect.innerHTML = protos.map(p => `<option value="${escHtml(p)}">${escHtml(p.toUpperCase())}</option>`).join('');
        custProtoWrap.classList.toggle('d-none', protos.length <= 1);
    };

    const toggleSsh = () => {
        if (!prodSelect || !sshWrap) return;
        const opt = prodSelect.options[prodSelect.selectedIndex];
        const activeProto = (custProtoSelect && custProtoSelect.value) ? custProtoSelect.value : (opt?.dataset?.protocol || '');
        const isSsh = activeProto === 'ssh';
        sshWrap.classList.toggle('d-none', !isSsh);
        if (sshInput) sshInput.required = isSsh;
        if (custSniWrap) custSniWrap.classList.toggle('d-none', isSsh);
        if (!isSsh && opt && opt.dataset.sniMode && custSniSelect) {
            custSniSelect.value = opt.dataset.sniMode;
        }
    };

    let custCheckTimer = null;
    let custCheckAbort = null;
    const triggerCustUsernameCheck = () => {
        if (!custUserInput || !custUserStatus || !prodSelect) return;
        const username = custUserInput.value.trim();
        const opt = prodSelect.options[prodSelect.selectedIndex];
        clearTimeout(custCheckTimer);
        if (custCheckAbort) { custCheckAbort.abort(); custCheckAbort = null; }

        if (username.length === 0) {
            if (custCreateSubmitBtn) custCreateSubmitBtn.disabled = false;
            if (custUserIndicator) custUserIndicator.innerHTML = '<i class="fas fa-user-check text-muted"></i>';
            custUserStatus.className = 'form-text text-muted';
            custUserStatus.textContent = '{{ __("Ketersediaan username diperiksa otomatis saat Anda mengetik.") }}';
            return;
        }
        if (!/^[a-zA-Z0-9_-]{3,24}$/.test(username)) {
            if (custCreateSubmitBtn) custCreateSubmitBtn.disabled = true;
            if (custUserIndicator) custUserIndicator.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>';
            custUserStatus.className = 'form-text text-warning fw-semibold';
            custUserStatus.textContent = '{{ __("Username harus 3–24 karakter (huruf, angka, _ atau -).") }}';
            return;
        }
        if (!opt || !opt.dataset.checkUrl) return;

        if (custUserIndicator) custUserIndicator.innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i>';
        custUserStatus.className = 'form-text text-muted';
        custUserStatus.textContent = '{{ __("Memeriksa ke server VPN...") }}';

        custCheckTimer = setTimeout(async function () {
            custCheckAbort = new AbortController();
            try {
                const res = await fetch(opt.dataset.checkUrl + '?username=' + encodeURIComponent(username), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: custCheckAbort.signal
                });
                const data = await res.json();
                if (data.success && data.available) {
                    if (custCreateSubmitBtn) custCreateSubmitBtn.disabled = false;
                    if (custUserIndicator) custUserIndicator.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                    custUserStatus.className = 'form-text text-success fw-semibold';
                    custUserStatus.textContent = data.message || '{{ __("Username tersedia.") }}';
                } else if (data.success && !data.available) {
                    if (custCreateSubmitBtn) custCreateSubmitBtn.disabled = true;
                    if (custUserIndicator) custUserIndicator.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
                    custUserStatus.className = 'form-text text-danger fw-semibold';
                    custUserStatus.textContent = data.message || '{{ __("Username sudah digunakan.") }}';
                } else {
                    if (custCreateSubmitBtn) custCreateSubmitBtn.disabled = false;
                    if (custUserIndicator) custUserIndicator.innerHTML = '<i class="fas fa-info-circle text-warning"></i>';
                    custUserStatus.className = 'form-text text-warning';
                    custUserStatus.textContent = data.message || '{{ __("Tidak dapat memverifikasi ke server saat ini.") }}';
                }
            } catch (e) {
                if (e.name !== 'AbortError') {
                    if (custCreateSubmitBtn) custCreateSubmitBtn.disabled = false;
                    if (custUserIndicator) custUserIndicator.innerHTML = '<i class="fas fa-info-circle text-muted"></i>';
                    custUserStatus.className = 'form-text text-muted';
                    custUserStatus.textContent = '{{ __("Gagal memeriksa username secara otomatis.") }}';
                }
            }
        }, 450);
    };

    if (prodSelect) {
        prodSelect.addEventListener('change', function () { syncProductProtocols(); toggleSsh(); triggerCustUsernameCheck(); });
        syncProductProtocols();
        toggleSsh();
    }
    custProtoSelect?.addEventListener('change', function () { toggleSsh(); triggerCustUsernameCheck(); });
    custUserInput?.addEventListener('input', triggerCustUsernameCheck);

    // 2. Renew Modal with Quick Presets (+1, +7, +30, +60), Live Expiry, Live Cost & Wallet/QRIS Payment Selector
    let custRenewBaseExpiry = null;
    let custRenewBasePrice = 0;
    let custRenewBaseDays = 30;
    const custWalletBalance = {{ (int) (Auth::user()->customer_wallet_balance ?? 0) }};
    const custRenewDays = document.getElementById('custRenewDaysInput');
    const custRenewPreview = document.getElementById('custRenewExpiryPreview');
    const custRenewCostEl = document.getElementById('custRenewCostPreview');
    const custRenewPayWrap = document.getElementById('custRenewPayMethodWrap');
    const custRenewPayWallet = document.getElementById('custRenewPayWallet');
    const custRenewPayQris = document.getElementById('custRenewPayQris');
    const custRenewWalletCard = document.getElementById('custRenewWalletCard');
    const custRenewQrisCard = document.getElementById('custRenewQrisCard');
    const custRenewWalletHint = document.getElementById('custRenewWalletHint');
    const custRenewNoPriceAlert = document.getElementById('custRenewNoPriceAlert');
    const custRenewLowBalAlert = document.getElementById('custRenewLowBalanceAlert');
    const custRenewSubmitBtn = document.getElementById('btnSubmitCustRenew');

    function syncRenewPayCardState(cost) {
        const insufficient = cost > custWalletBalance;
        if (custRenewPayWallet) {
            custRenewPayWallet.disabled = insufficient;
            if (insufficient && custRenewPayWallet.checked && custRenewPayQris) {
                custRenewPayQris.checked = true;
            }
        }
        if (custRenewWalletCard) {
            custRenewWalletCard.style.opacity = insufficient ? '0.6' : '1';
            custRenewWalletCard.classList.toggle('border-primary', !!(custRenewPayWallet && custRenewPayWallet.checked));
        }
        if (custRenewQrisCard) {
            custRenewQrisCard.classList.toggle('border-primary', !!(custRenewPayQris && custRenewPayQris.checked));
        }
        if (custRenewWalletHint) {
            custRenewWalletHint.textContent = insufficient ? @js(__('Saldo tidak mencukupi')) : @js(__('Langsung aktif instan'));
        }
        if (custRenewLowBalAlert) {
            custRenewLowBalAlert.classList.toggle('d-none', !insufficient);
        }
        const isQris = !!(custRenewPayQris && custRenewPayQris.checked);
        if (custRenewSubmitBtn) {
            custRenewSubmitBtn.disabled = (!isQris && insufficient);
            custRenewSubmitBtn.textContent = isQris ? @js(__('Bayar & Perpanjang via QRIS')) : @js(__('Perpanjang Sekarang'));
        }
    }

    function updateCustRenewPreview() {
        if (!custRenewDays || !custRenewPreview) return;
        const days = Math.max(1, parseInt(custRenewDays.value || '0', 10) || 0);
        const now = new Date();
        const base = (custRenewBaseExpiry && custRenewBaseExpiry > now) ? new Date(custRenewBaseExpiry.getTime()) : now;
        base.setDate(base.getDate() + days);
        custRenewPreview.textContent = base.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

        if (custRenewBasePrice > 0 && custRenewBaseDays > 0) {
            const cost = Math.max(1, Math.round((custRenewBasePrice / custRenewBaseDays) * days));
            if (custRenewCostEl) custRenewCostEl.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(cost);
            if (custRenewNoPriceAlert) custRenewNoPriceAlert.classList.add('d-none');
            if (custRenewPayWrap) custRenewPayWrap.classList.remove('d-none');
            syncRenewPayCardState(cost);
        } else {
            if (custRenewCostEl) custRenewCostEl.textContent = @js(__('Belum diatur'));
            if (custRenewNoPriceAlert) custRenewNoPriceAlert.classList.remove('d-none');
            if (custRenewPayWrap) custRenewPayWrap.classList.add('d-none');
            if (custRenewLowBalAlert) custRenewLowBalAlert.classList.add('d-none');
            if (custRenewSubmitBtn) {
                custRenewSubmitBtn.disabled = true;
                custRenewSubmitBtn.textContent = @js(__('Perpanjang Sekarang'));
            }
        }

        document.querySelectorAll('.btn-cust-renew-preset').forEach(function (b) {
            b.classList.toggle('active', parseInt(b.dataset.days, 10) === days);
        });
    }

    custRenewPayWallet?.addEventListener('change', updateCustRenewPreview);
    custRenewPayQris?.addEventListener('change', updateCustRenewPreview);
    custRenewDays?.addEventListener('input', updateCustRenewPreview);
    document.querySelectorAll('.btn-cust-renew-preset').forEach(function (b) {
        b.addEventListener('click', function () {
            if (custRenewDays) {
                custRenewDays.value = this.dataset.days;
                updateCustRenewPreview();
            }
        });
    });

    document.querySelectorAll('.btn-renew-my-vpn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('customerRenewForm').action = this.dataset.action;
            document.getElementById('custRenewUsername').value = this.dataset.username || '';
            custRenewBaseExpiry = this.dataset.expiredIso ? new Date(this.dataset.expiredIso) : null;
            custRenewBasePrice = parseInt(this.dataset.basePrice || '0', 10) || 0;
            custRenewBaseDays = Math.max(1, parseInt(this.dataset.baseDays || '30', 10) || 30);
            if (custRenewDays) custRenewDays.value = 30;
            if (custRenewPayWallet) custRenewPayWallet.checked = true;
            updateCustRenewPreview();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('customerRenewVpnModal')).show();
        });
    });

    // Auto-open Renew modal when arriving from Telegram "Renew Sekarang" or Website Notification Hub (?renew=<id>)
    const autoRenewId = @json($autoRenewAccountId ?? null);
    if (autoRenewId) {
        const targetRenewBtn = document.querySelector(`.btn-renew-my-vpn[data-account-id="${autoRenewId}"]`);
        if (targetRenewBtn) {
            setTimeout(() => targetRenewBtn.click(), 220);
        }
    }

    // 3. Config & QR Viewer with Lazy Auto-Fetch + Live Preset SNI Switcher
    let currentMyConfigUrl = '';
    let activeMyConfigState = null;
    const myConfigModalEl = document.getElementById('myVpnConfigModal');
    const myConfigLoadingEl = document.getElementById('myVpnConfigLoading');
    const myConfigContentEl = document.getElementById('myVpnConfigContent');
    const myConfigSniWrap = document.getElementById('myVpnConfigSniWrap');
    const myConfigSniSelect = document.getElementById('myVpnConfigSniSelect');

    function encodeB64Utf8(str) {
        try { return btoa(unescape(encodeURIComponent(str))); } catch (e) { return btoa(str); }
    }

    function buildMyXrayLinksForSni(protocol, domain, uuid, username, sniMode) {
        if (!domain || !uuid || !protocol || protocol === 'ssh') return null;
        let addr = domain, host = domain, sni = domain, label = username;
        if (sniMode === '1') {
            addr = 'support.zoom.us';
            host = 'support.zoom.us.' + domain;
            sni = host;
            label = username + ' (Zoom)';
        } else if (sniMode === '2') {
            sni = 'live.iflix.com';
            label = username + ' (Iflix)';
        }
        const encLabel = encodeURIComponent(label);
        if (protocol === 'vmess') {
            const tlsObj = { v: '2', ps: label, add: addr, port: '443', id: uuid, aid: '0', net: 'ws', path: '/vmess', type: 'none', host: host, tls: 'tls', sni: sni, allowInsecure: true };
            const ntlsObj = { v: '2', ps: label, add: addr, port: '80', id: uuid, aid: '0', net: 'ws', path: '/vmess', type: 'none', host: host, tls: 'none' };
            const grpcObj = { v: '2', ps: label + ' gRPC', add: addr, port: '443', id: uuid, aid: '0', net: 'grpc', path: 'vmess-grpc', type: 'none', host: host, tls: 'tls', sni: sni };
            return {
                tls: 'vmess://' + encodeB64Utf8(JSON.stringify(tlsObj)),
                ntls: 'vmess://' + encodeB64Utf8(JSON.stringify(ntlsObj)),
                grpc: 'vmess://' + encodeB64Utf8(JSON.stringify(grpcObj)),
            };
        }
        if (protocol === 'vless') {
            return {
                tls: `vless://${uuid}@${addr}:443?path=/vless&security=tls&encryption=none&type=ws&host=${host}&sni=${sni}#${encLabel}`,
                ntls: `vless://${uuid}@${addr}:80?path=/vless&encryption=none&type=ws&host=${host}#${encLabel}`,
                grpc: `vless://${uuid}@${addr}:443?mode=gun&security=tls&encryption=none&type=grpc&serviceName=vless-grpc&sni=${sni}#${encLabel}`,
            };
        }
        if (protocol === 'trojan') {
            return {
                tls: `trojan://${uuid}@${addr}:443?path=/trojan-ws&security=tls&type=ws&host=${host}&sni=${sni}#${encLabel}`,
                grpc: `trojan://${uuid}@${addr}:443?mode=gun&security=tls&type=grpc&serviceName=trojan-grpc&sni=${sni}#${encLabel}`,
            };
        }
        if (protocol === 'shadowsocks') {
            const ssCred = btoa('aes-128-gcm:' + uuid);
            return {
                tls: `ss://${ssCred}@${addr}:443?path=/ss-ws&security=tls&host=${host}&type=ws&sni=${sni}#${encLabel}`,
                grpc: `ss://${ssCred}@${addr}:443?mode=gun&security=tls&type=grpc&serviceName=ss-grpc&sni=${sni}#${encLabel}`,
            };
        }
        return null;
    }

    function formatMyXrayTextForSni(payload, links, sniMode) {
        const protoUpper = String(payload.protocol || '').toUpperCase();
        const quotaText = (Number(payload.quota_gb || 0) > 0) ? (payload.quota_gb + ' GB') : 'Unlimited';
        const presetName = sniMode === '1' ? 'Zoom (support.zoom.us)' : (sniMode === '2' ? 'Iflix (live.iflix.com)' : 'Default SNI');
        const lines = [
            `=== ${protoUpper} ACCOUNT ===`,
            `Username : ${payload.username || '-'}`,
            `Domain   : ${payload.domain || '-'}`,
            `UUID     : ${payload.uuid || '-'}`,
            `Preset   : ${presetName}`,
            `Quota    : ${quotaText}`,
            `IP Limit : ${Math.max(1, Number(payload.ip_limit || 1))} IP`,
            `Expired  : ${payload.expired_at || '-'}`,
        ];
        if (links.tls) lines.push('', 'Link TLS (443):', links.tls);
        if (links.ntls) lines.push('', 'Link Non-TLS (80):', links.ntls);
        if (links.grpc) lines.push('', 'Link gRPC (443):', links.grpc);
        return lines.join('\n');
    }

    function renderMyVpnConfig(title, payload, fullText, overrideSni = null) {
        document.getElementById('myVpnConfigTitle').textContent = title || 'VPN Config';
        activeMyConfigState = { title, payload, fullText };

        let linksObj = (payload && payload.links && typeof payload.links === 'object') ? payload.links : null;
        let textToShow = fullText || '';

        const canSwitchSni = !!(payload && payload.protocol && payload.protocol !== 'ssh' && payload.domain && payload.uuid);
        if (myConfigSniWrap && myConfigSniSelect) {
            myConfigSniWrap.classList.toggle('d-none', !canSwitchSni);
            if (canSwitchSni) {
                const chosenSni = overrideSni || String(payload.sni_mode || '3');
                myConfigSniSelect.value = ['1', '2', '3'].includes(chosenSni) ? chosenSni : '3';
                if (overrideSni !== null) {
                    const regenerated = buildMyXrayLinksForSni(String(payload.protocol).toLowerCase(), payload.domain, payload.uuid, payload.username || '', myConfigSniSelect.value);
                    if (regenerated) {
                        linksObj = regenerated;
                        textToShow = formatMyXrayTextForSni(payload, regenerated, myConfigSniSelect.value);
                    }
                }
            }
        }

        document.getElementById('myVpnFullText').value = textToShow;

        const linksWrap = document.getElementById('myVpnLinksWrap');
        const qrWrap = document.getElementById('myVpnQrWrap');
        const qrCanvas = document.getElementById('myVpnQrCanvas');
        linksWrap.innerHTML = '';

        let primaryLink = '';
        if (linksObj && typeof linksObj === 'object') {
            Object.entries(linksObj).filter(([, v]) => !!v).forEach(([key, val]) => {
                if (!primaryLink) primaryLink = val;
                const row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2 p-2 rounded-3 border';
                row.innerHTML = `
                    <span class="badge bg-primary-subtle text-primary text-uppercase">${escHtml(key)}</span>
                    <input type="text" class="form-control form-control-sm font-monospace border-0 bg-transparent p-0" value="${escHtml(val)}" readonly>
                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 flex-shrink-0"><i class="fas fa-copy"></i></button>
                `;
                row.querySelector('button').addEventListener('click', function () {
                    navigator.clipboard?.writeText(val);
                    this.innerHTML = '<i class="fas fa-check text-success"></i>';
                    setTimeout(() => { this.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500);
                });
                linksWrap.appendChild(row);
            });
        }

        if (primaryLink && typeof window.renderLocalQrToCanvas === 'function') {
            qrWrap.classList.remove('d-none');
            window.renderLocalQrToCanvas(qrCanvas, primaryLink, 210);
        } else {
            qrWrap.classList.add('d-none');
        }
    }

    myConfigSniSelect?.addEventListener('change', function () {
        if (!activeMyConfigState || !activeMyConfigState.payload) return;
        renderMyVpnConfig(activeMyConfigState.title, activeMyConfigState.payload, activeMyConfigState.fullText, this.value);
    });

    // Direct Config button inside Desktop Dropdown menu
    document.querySelectorAll('.btn-direct-my-vpn-config').forEach(function (btn) {
        btn.addEventListener('click', function () {
            let parsedPayload = null;
            try { parsedPayload = JSON.parse(this.dataset.payload || 'null'); } catch (e) {}
            openMyVpnConfig(this.dataset.title || 'VPN Config', parsedPayload, this.dataset.text || '', this.dataset.configUrl || '', false);
        });
    });

    async function openMyVpnConfig(title, payload, fullText, configUrl, forceRefresh = false) {
        currentMyConfigUrl = configUrl || '';
        document.getElementById('myVpnConfigTitle').textContent = title || 'VPN Config';
        const needsFetch = forceRefresh || (!payload && (!fullText || !fullText.trim()));

        bootstrap.Modal.getOrCreateInstance(myConfigModalEl).show();

        if (!needsFetch) {
            myConfigLoadingEl.classList.add('d-none');
            myConfigContentEl.classList.remove('d-none');
            renderMyVpnConfig(title, payload, fullText);
            return;
        }

        if (!currentMyConfigUrl) {
            myConfigLoadingEl.classList.add('d-none');
            myConfigContentEl.classList.remove('d-none');
            renderMyVpnConfig(title, payload, fullText || '{{ __("Konfigurasi belum tersedia.") }}');
            return;
        }

        myConfigLoadingEl.classList.remove('d-none');
        myConfigContentEl.classList.add('d-none');
        try {
            const url = currentMyConfigUrl + (forceRefresh ? '?refresh=1' : '');
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            myConfigLoadingEl.classList.add('d-none');
            myConfigContentEl.classList.remove('d-none');
            renderMyVpnConfig(data.title || title, data.config_payload || null, data.config_link || data.message || '');
        } catch (e) {
            myConfigLoadingEl.classList.add('d-none');
            myConfigContentEl.classList.remove('d-none');
            renderMyVpnConfig(title, null, '{{ __("Gagal menarik detail konfigurasi dari server VPN.") }}');
        }
    }

    document.getElementById('btnRefreshMyVpnConfig')?.addEventListener('click', function () {
        if (!currentMyConfigUrl) return;
        openMyVpnConfig(document.getElementById('myVpnConfigTitle').textContent, null, '', currentMyConfigUrl, true);
    });

    document.getElementById('btnCopyMyVpnFull')?.addEventListener('click', function () {
        navigator.clipboard?.writeText(document.getElementById('myVpnFullText').value);
        const orig = this.innerHTML;
        this.innerHTML = '<i class="fas fa-check text-success me-1"></i>{{ __("Tersalin!") }}';
        setTimeout(() => { this.innerHTML = orig; }, 1500);
    });

    // 4. Customer Action Sheet Modal (Hamburger Button)
    const mySheetModalEl = document.getElementById('myVpnActionSheetModal');
    let myActiveSheet = null;

    document.querySelectorAll('.btn-open-my-vpn-sheet').forEach(function (btn) {
        btn.addEventListener('click', function () {
            let parsedPayload = null;
            try { parsedPayload = JSON.parse(this.dataset.payload || 'null'); } catch (e) {}

            myActiveSheet = {
                username: this.dataset.username || '',
                protocol: this.dataset.protocol || '',
                server: this.dataset.server || '',
                status: this.dataset.status || '',
                statusLabel: this.dataset.statusLabel || '',
                configUrl: this.dataset.configUrl || '',
                payload: parsedPayload,
                text: this.dataset.text || '',
                suspendUrl: this.dataset.suspendUrl || '',
                unsuspendUrl: this.dataset.unsuspendUrl || '',
                syncUrl: this.dataset.syncUrl || '',
                deleteUrl: this.dataset.deleteUrl || '',
            };

            document.getElementById('mySheetVpnUser').textContent = myActiveSheet.username;
            document.getElementById('mySheetVpnProto').textContent = myActiveSheet.protocol;
            document.getElementById('mySheetVpnMeta').textContent = myActiveSheet.server + ' • ' + myActiveSheet.statusLabel;

            const isSuspended = myActiveSheet.status === 'suspended';
            const fSusp = document.getElementById('mySheetFormSuspend');
            const fUnsusp = document.getElementById('mySheetFormUnsuspend');
            fSusp.classList.toggle('d-none', isSuspended);
            fSusp.action = myActiveSheet.suspendUrl;
            fUnsusp.classList.toggle('d-none', !isSuspended);
            fUnsusp.action = myActiveSheet.unsuspendUrl;

            document.getElementById('mySheetFormSync').action = myActiveSheet.syncUrl;
            document.getElementById('mySheetFormDelete').action = myActiveSheet.deleteUrl;

            bootstrap.Modal.getOrCreateInstance(mySheetModalEl).show();
        });
    });

    document.getElementById('mySheetBtnConfig')?.addEventListener('click', function () {
        if (!myActiveSheet) return;
        bootstrap.Modal.getOrCreateInstance(mySheetModalEl).hide();
        openMyVpnConfig(
            myActiveSheet.protocol + ' — ' + myActiveSheet.username,
            myActiveSheet.payload,
            myActiveSheet.text,
            myActiveSheet.configUrl,
            false
        );
    });
});
</script>
@endpush
@endsection
