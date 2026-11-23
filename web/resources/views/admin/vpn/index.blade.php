@extends('layouts.app')

@section('title', __('Kelola VPN'))
@section('page_subtitle', __('Manajemen Akun VPN & Koneksi Multi-Server REST API'))

@push('styles')
<style>
    .dropdown-menu { z-index: 1060 !important; }
</style>
@endpush

@section('content')
@php
    $isServerTab = ($activeTab ?? 'accounts') === 'servers';
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="fw-bold text-body mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-network-wired text-primary"></i>
            <span>{{ __('Kelola VPN') }}</span>
        </h4>
        <p class="text-muted small mb-0">{{ __('Kelola seluruh akun VPN, tagihan manual, dan koneksi multi-server REST API vpnxray-main dalam satu halaman.') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        {{-- Contextual actions for Accounts Tab --}}
        <div id="vpnHeaderActionsAccounts" class="d-flex flex-wrap gap-2 {{ $isServerTab ? 'd-none' : '' }}">
            @if($servers->where('driver', 'vpnxray_api')->isNotEmpty())
            <button type="button" class="btn btn-outline-primary rounded-pill px-3 fw-semibold btn-sm d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#syncServerModal">
                <i class="fas fa-rotate"></i>
                <span>{{ __('Sync Panel') }}</span>
            </button>
            @endif
            <button type="button" class="btn btn-primary rounded-pill px-3 fw-semibold btn-sm d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#adminCreateVpnModal">
                <i class="fas fa-plus"></i>
                <span>{{ __('Buat Akun Manual') }}</span>
            </button>
        </div>
        {{-- Contextual actions for Servers Tab --}}
        <div id="vpnHeaderActionsServers" class="d-flex flex-wrap gap-2 {{ $isServerTab ? '' : 'd-none' }}">
            <button type="button" class="btn btn-primary rounded-pill px-3 fw-semibold btn-sm d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#addVpnServerModal">
                <i class="fas fa-plus"></i>
                <span>{{ __('Tambah Server') }}</span>
            </button>
        </div>
    </div>
</div>

{{-- Unified 2-Tab Navigation Pill Bar (Flat Material You) --}}
<div class="card mb-3">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill gap-2" id="adminVpnMainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-semibold d-flex align-items-center justify-content-center gap-2 {{ !$isServerTab ? 'active' : '' }}"
                        id="tab-vpn-accounts-btn"
                        data-bs-toggle="pill"
                        data-bs-target="#pane-vpn-accounts"
                        data-vpn-tab="accounts"
                        type="button"
                        role="tab"
                        aria-controls="pane-vpn-accounts"
                        aria-selected="{{ !$isServerTab ? 'true' : 'false' }}">
                    <i class="fas fa-users-gear"></i>
                    <span>{{ __('Akun VPN') }}</span>
                    <span class="badge rounded-pill {{ !$isServerTab ? 'bg-white text-primary' : 'bg-primary-subtle text-primary' }}">{{ $stats['total'] }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-semibold d-flex align-items-center justify-content-center gap-2 {{ $isServerTab ? 'active' : '' }}"
                        id="tab-vpn-servers-btn"
                        data-bs-toggle="pill"
                        data-bs-target="#pane-vpn-servers"
                        data-vpn-tab="servers"
                        type="button"
                        role="tab"
                        aria-controls="pane-vpn-servers"
                        aria-selected="{{ $isServerTab ? 'true' : 'false' }}">
                    <i class="fas fa-server"></i>
                    <span>{{ __('Server VPN') }}</span>
                    <span class="badge rounded-pill {{ $isServerTab ? 'bg-white text-primary' : 'bg-primary-subtle text-primary' }}">{{ $serverStats['total'] }}</span>
                </button>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content" id="adminVpnMainTabsContent">
    {{-- ===================================================================== --}}
    {{-- TAB 1: AKUN VPN                                                       --}}
    {{-- ===================================================================== --}}
    <div class="tab-pane fade {{ !$isServerTab ? 'show active' : '' }}" id="pane-vpn-accounts" role="tabpanel" aria-labelledby="tab-vpn-accounts-btn">
        {{-- KPI Summary --}}
        <div class="row g-2 g-md-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body p-3">
                    <div class="text-muted small fw-semibold">{{ __('Total Akun') }}</div>
                    <div class="fs-4 fw-bold text-body">{{ $stats['total'] }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body p-3">
                    <div class="text-muted small fw-semibold">{{ __('Aktif') }}</div>
                    <div class="fs-4 fw-bold text-success">{{ $stats['active'] }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body p-3">
                    <div class="text-muted small fw-semibold">{{ __('Disuspend') }}</div>
                    <div class="fs-4 fw-bold text-warning">{{ $stats['suspended'] }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body p-3">
                    <div class="text-muted small fw-semibold">{{ __('Gagal / Pending') }}</div>
                    <div class="fs-4 fw-bold text-danger">{{ $stats['failed'] }}</div>
                </div></div>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="card mb-3">
            <div class="card-body p-3">
                <form action="{{ route('admin.vpn-accounts.index') }}" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="accounts">
                    <div class="col-12 col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="{{ __('Cari username, pemilik, atau ref order...') }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="server_id" class="form-select form-select-sm">
                            <option value="">{{ __('Semua Server') }}</option>
                            @foreach($servers as $srv)
                                <option value="{{ $srv->id }}" {{ (string) request('server_id') === (string) $srv->id ? 'selected' : '' }}>{{ $srv->name }}</option>
                            @endforeach
                        </select>
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
                            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>{{ __('Disuspend') }}</option>
                            <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>{{ __('Expired') }}</option>
                            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ __('Gagal') }}</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 d-flex gap-1.5">
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill flex-grow-1 fw-semibold">{{ __('Filter') }}</button>
                        @if(request()->hasAny(['search', 'server_id', 'protocol', 'status']))
                            <a href="{{ route('admin.vpn-accounts.index', ['tab' => 'accounts']) }}" class="btn btn-light btn-sm rounded-pill border"><i class="fas fa-times"></i></a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if($accounts->isEmpty())
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-network-wired fs-1 text-muted opacity-50 mb-3"></i>
                    <h6 class="fw-bold text-body mb-1">{{ __('Belum Ada Akun VPN') }}</h6>
                    <p class="text-muted small mb-0">{{ __('Akun VPN yang dibuat otomatis dari pesanan, sinkronisasi panel, atau dibuat manual akan muncul di sini.') }}</p>
                </div>
            </div>
        @else
            {{-- Desktop Table --}}
            <div class="card d-none d-lg-block">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">{{ __('Username & Protokol') }}</th>
                                <th>{{ __('Pemilik') }}</th>
                                <th>{{ __('Server') }}</th>
                                <th>{{ __('Kuota / IP') }}</th>
                                <th>{{ __('Masa Aktif') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end pe-3">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts as $acc)
                            @php
                                $isExpired = $acc->expired_at && $acc->expired_at->isPast();
                                $isFailedOrProv = in_array($acc->status, ['failed', 'provisioning']);
                                $badge = match(true) {
                                    $acc->status === 'failed' => ['bg' => 'danger-subtle', 'text' => 'danger', 'label' => __('Gagal')],
                                    $acc->status === 'provisioning' => ['bg' => 'info-subtle', 'text' => 'info', 'label' => __('Provisioning')],
                                    $acc->isAdminSuspended() => ['bg' => 'danger-subtle', 'text' => 'danger', 'label' => __('Suspend Admin')],
                                    $acc->status === 'suspended' => ['bg' => 'warning-subtle', 'text' => 'warning-emphasis', 'label' => __('Suspend User')],
                                    $isExpired || $acc->status === 'expired' => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => __('Expired')],
                                    default => ['bg' => 'success-subtle', 'text' => 'success', 'label' => __('Aktif')],
                                };
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-body font-monospace">{{ $acc->username }}</div>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size: 0.68rem;">{{ strtoupper($acc->protocol) }}</span>
                                    @if($acc->external_ref)
                                        <small class="text-muted ms-1 font-monospace">{{ $acc->external_ref }}</small>
                                    @endif
                                </td>
                                <td class="small">
                                    <div class="fw-semibold text-body">{{ $acc->user?->full_name ?: $acc->user?->username ?: '-' }}</div>
                                    @if($acc->order)
                                        <a href="{{ route('admin.orders.show', $acc->order_id) }}" class="text-decoration-none font-monospace" style="font-size: 0.72rem;">{{ $acc->order->order_ref }}</a>
                                    @endif
                                </td>
                                <td class="small">
                                    <div class="fw-semibold text-body">{{ $acc->vpnServer?->name ?: __('Legacy/Default') }}</div>
                                    <div class="text-muted font-monospace" style="font-size: 0.72rem;">{{ $acc->display_domain }}</div>
                                </td>
                                <td class="small">
                                    <div>{{ ($acc->quota_gb ?? 0) > 0 ? $acc->quota_gb . ' GB' : 'Unlimited' }}</div>
                                    <div class="text-muted">{{ max(1, (int) ($acc->ip_limit ?? 1)) }} IP</div>
                                </td>
                                <td class="small">
                                    <div class="fw-semibold text-body">{{ $acc->expired_at ? $acc->expired_at->format('d M Y') : '-' }}</div>
                                    @if($acc->expired_at)
                                        <div class="text-muted" style="font-size: 0.72rem;">{{ $acc->expired_at->diffForHumans() }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $badge['bg'] }} text-{{ $badge['text'] }} rounded-pill px-2.5 py-1">{{ $badge['label'] }}</span>
                                    @if($acc->suspended_reason && $acc->status === 'suspended')
                                        <div class="text-muted text-truncate" style="max-width: 150px; font-size: 0.7rem;" title="{{ $acc->suspended_reason }}">{{ $acc->suspended_reason }}</div>
                                    @endif
                                    @if($acc->last_error && $acc->status === 'failed')
                                        <div class="text-danger text-truncate" style="max-width: 150px; font-size: 0.7rem;" title="{{ $acc->last_error }}">{{ $acc->last_error }}</div>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center justify-content-end gap-1.5">
                                        @if($isFailedOrProv)
                                            <form action="{{ route('admin.vpn-accounts.retry', $acc->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning rounded-pill px-2.5 fw-semibold" title="{{ __('Retry Provisioning') }}">
                                                    <i class="fas fa-redo me-1"></i>{{ __('Retry') }}
                                                </button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 fw-semibold btn-open-renew"
                                                    data-action="{{ route('admin.vpn-accounts.renew', $acc->id) }}"
                                                    data-username="{{ $acc->username }}"
                                                    data-quota="{{ $acc->quota_gb ?? 0 }}"
                                                    data-ip="{{ max(1, (int) ($acc->ip_limit ?? 1)) }}"
                                                    data-expired-iso="{{ $acc->expired_at ? $acc->expired_at->toIso8601String() : '' }}"
                                                    data-expired-label="{{ $acc->expired_at ? $acc->expired_at->format('d M Y') : '-' }}"
                                                    title="{{ __('Perpanjang') }}">
                                                <i class="fas fa-calendar-plus me-1"></i>{{ __('Perpanjang') }}
                                            </button>
                                        @endif

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
                                                            class="dropdown-item py-2 d-flex align-items-center gap-2 btn-direct-vpn-config"
                                                            data-title="{{ strtoupper($acc->protocol) }} — {{ $acc->username }}"
                                                            data-config-url="{{ route('admin.vpn-accounts.config', $acc->id) }}"
                                                            data-payload='@json($acc->config_payload)'
                                                            data-text="{{ $acc->config_link }}">
                                                        <i class="fas fa-qrcode text-info fa-fw"></i>
                                                        <span>{{ __('Lihat Config & QR Code') }}</span>
                                                    </button>
                                                </li>
                                                @if(!$isFailedOrProv)
                                                    @if($acc->status === 'suspended')
                                                    <li>
                                                        <form action="{{ route('admin.vpn-accounts.unsuspend', $acc->id) }}" method="POST" class="m-0">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2 text-success">
                                                                <i class="fas fa-play fa-fw"></i>
                                                                <span>{{ __('Aktifkan Kembali') }}</span>
                                                            </button>
                                                        </form>
                                                    </li>
                                                    @else
                                                    <li>
                                                        <button type="button"
                                                                class="dropdown-item py-2 d-flex align-items-center gap-2 text-warning-emphasis btn-direct-vpn-suspend"
                                                                data-action="{{ route('admin.vpn-accounts.suspend', $acc->id) }}"
                                                                data-username="{{ $acc->username }}">
                                                            <i class="fas fa-ban text-warning fa-fw"></i>
                                                            <span>{{ __('Suspend (Kunci Admin)') }}</span>
                                                        </button>
                                                    </li>
                                                    @endif
                                                    <li>
                                                        <form action="{{ route('admin.vpn-accounts.sync', $acc->id) }}" method="POST" class="m-0">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2">
                                                                <i class="fas fa-rotate text-secondary fa-fw"></i>
                                                                <span>{{ __('Sync dari Panel VPN') }}</span>
                                                            </button>
                                                        </form>
                                                    </li>
                                                @else
                                                    <li>
                                                        <form action="{{ route('admin.vpn-accounts.retry', $acc->id) }}" method="POST" class="m-0">
                                                            @csrf
                                                            <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2 text-warning-emphasis">
                                                                <i class="fas fa-redo text-warning fa-fw"></i>
                                                                <span>{{ __('Retry Provisioning') }}</span>
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li>
                                                    <form action="{{ route('admin.vpn-accounts.destroy', $acc->id) }}" method="POST" class="m-0" onsubmit="return confirm('{{ __('Hapus permanen akun VPN ini dari server VPS & database?') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item py-2 d-flex align-items-center gap-2 text-danger">
                                                            <i class="fas fa-trash fa-fw"></i>
                                                            <span>{{ __('Hapus Permanen') }}</span>
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile Collapsible Compact List --}}
            <div class="d-lg-none m3-collapsible-list" id="adminVpnAccountsAccordion">
                @foreach($accounts as $acc)
                @php
                    $isExpired = $acc->expired_at && $acc->expired_at->isPast();
                    $isFailedOrProv = in_array($acc->status, ['failed', 'provisioning']);
                    $dotColor = match(true) {
                        $acc->status === 'failed' => 'bg-danger',
                        $acc->status === 'suspended' => 'bg-warning',
                        $isExpired || $acc->status === 'expired' => 'bg-secondary',
                        default => 'bg-success',
                    };
                    $statusLabel = match(true) {
                        $acc->status === 'failed' => __('Gagal'),
                        $acc->status === 'provisioning' => __('Provisioning'),
                        $acc->isAdminSuspended() => __('Suspend Admin'),
                        $acc->status === 'suspended' => __('Suspend User'),
                        $isExpired || $acc->status === 'expired' => __('Expired'),
                        default => __('Aktif'),
                    };
                @endphp
                <div class="m3-collapsible-card">
                    <div class="m3-compact-row">
                        <button type="button" class="m3-compact-trigger collapsed" data-bs-toggle="collapse" data-bs-target="#adminVpnCollapse{{ $acc->id }}" aria-expanded="false">
                            <span class="m3-status-dot {{ $dotColor }}"></span>
                            <div class="min-w-0 flex-grow-1 text-start">
                                <div class="fw-bold text-body font-monospace text-truncate" style="font-size: 0.9rem;">
                                    {{ $acc->username }}
                                    <span class="badge bg-primary-subtle text-primary ms-1" style="font-size: 0.62rem;">{{ strtoupper($acc->protocol) }}</span>
                                </div>
                                <div class="text-muted text-truncate" style="font-size: 0.74rem;">
                                    {{ $acc->user?->full_name ?: $acc->user?->username ?: '-' }} &bull; Exp: {{ $acc->expired_at ? $acc->expired_at->format('d M Y') : '-' }}
                                </div>
                            </div>
                            <i class="fas fa-chevron-down m3-compact-chevron"></i>
                        </button>
                        <button type="button" class="m3-compact-action-btn btn-open-admin-vpn-sheet"
                                data-id="{{ $acc->id }}"
                                data-username="{{ $acc->username }}"
                                data-protocol="{{ strtoupper($acc->protocol) }}"
                                data-server="{{ $acc->vpnServer?->name ?: __('Legacy/Default') }}"
                                data-status="{{ $acc->status }}"
                                data-status-label="{{ $statusLabel }}"
                                data-config-url="{{ route('admin.vpn-accounts.config', $acc->id) }}"
                                data-payload='@json($acc->config_payload)'
                                data-text="{{ $acc->config_link }}"
                                data-suspend-url="{{ route('admin.vpn-accounts.suspend', $acc->id) }}"
                                data-unsuspend-url="{{ route('admin.vpn-accounts.unsuspend', $acc->id) }}"
                                data-sync-url="{{ route('admin.vpn-accounts.sync', $acc->id) }}"
                                data-retry-url="{{ route('admin.vpn-accounts.retry', $acc->id) }}"
                                data-delete-url="{{ route('admin.vpn-accounts.destroy', $acc->id) }}"
                                title="{{ __('Menu Aksi') }}">
                            <i class="fas fa-bars text-primary"></i>
                        </button>
                    </div>
                    <div id="adminVpnCollapse{{ $acc->id }}" class="collapse" data-bs-parent="#adminVpnAccountsAccordion">
                        <div class="m3-collapsible-body">
                            <div class="small mb-2.5">
                                <div><span class="text-muted">{{ __('Server:') }}</span> <strong>{{ $acc->vpnServer?->name ?: __('Default') }}</strong> ({{ $acc->display_domain }})</div>
                                <div><span class="text-muted">{{ __('Limit:') }}</span> {{ ($acc->quota_gb ?? 0) > 0 ? $acc->quota_gb . ' GB' : 'Unlimited' }} &bull; {{ max(1, (int) ($acc->ip_limit ?? 1)) }} IP</div>
                                @if($acc->isAdminSuspended())
                                    <div class="text-danger fw-semibold mt-1"><i class="fas fa-lock me-1"></i>{{ __('Disuspend Admin:') }} {{ $acc->suspended_reason }}</div>
                                @elseif($acc->status === 'failed' && $acc->last_error)
                                    <div class="text-danger mt-1"><i class="fas fa-triangle-exclamation me-1"></i>{{ $acc->last_error }}</div>
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-1.5">
                                @if($isFailedOrProv)
                                    <form action="{{ route('admin.vpn-accounts.retry', $acc->id) }}" method="POST" class="flex-grow-1">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning rounded-pill w-100 fw-semibold"><i class="fas fa-redo me-1"></i>{{ __('Retry Provisioning') }}</button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-primary rounded-pill flex-grow-1 fw-semibold btn-open-renew"
                                            data-action="{{ route('admin.vpn-accounts.renew', $acc->id) }}"
                                            data-username="{{ $acc->username }}"
                                            data-quota="{{ $acc->quota_gb ?? 0 }}"
                                            data-ip="{{ max(1, (int) ($acc->ip_limit ?? 1)) }}"
                                            data-expired-iso="{{ $acc->expired_at ? $acc->expired_at->toIso8601String() : '' }}"
                                            data-expired-label="{{ $acc->expired_at ? $acc->expired_at->format('d M Y') : '-' }}">
                                        <i class="fas fa-calendar-plus me-1"></i>{{ __('Perpanjang') }}
                                    </button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold btn-open-admin-vpn-sheet"
                                        data-id="{{ $acc->id }}"
                                        data-username="{{ $acc->username }}"
                                        data-protocol="{{ strtoupper($acc->protocol) }}"
                                        data-server="{{ $acc->vpnServer?->name ?: __('Legacy/Default') }}"
                                        data-status="{{ $acc->status }}"
                                        data-status-label="{{ $statusLabel }}"
                                        data-config-url="{{ route('admin.vpn-accounts.config', $acc->id) }}"
                                        data-payload='@json($acc->config_payload)'
                                        data-text="{{ $acc->config_link }}"
                                        data-suspend-url="{{ route('admin.vpn-accounts.suspend', $acc->id) }}"
                                        data-unsuspend-url="{{ route('admin.vpn-accounts.unsuspend', $acc->id) }}"
                                        data-sync-url="{{ route('admin.vpn-accounts.sync', $acc->id) }}"
                                        data-retry-url="{{ route('admin.vpn-accounts.retry', $acc->id) }}"
                                        data-delete-url="{{ route('admin.vpn-accounts.destroy', $acc->id) }}">
                                    <i class="fas fa-bars me-1"></i>{{ __('Aksi') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            @if($accounts->hasPages())
                <div class="mt-3">{{ $accounts->links() }}</div>
            @endif
        @endif
    </div>

    {{-- ===================================================================== --}}
    {{-- TAB 2: SERVER VPN                                                     --}}
    {{-- ===================================================================== --}}
    <div class="tab-pane fade {{ $isServerTab ? 'show active' : '' }}" id="pane-vpn-servers" role="tabpanel" aria-labelledby="tab-vpn-servers-btn">
        {{-- KPI Cards --}}
        <div class="row g-2 g-md-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body p-3">
                    <div class="text-muted small fw-semibold">{{ __('Total Server') }}</div>
                    <div class="fs-4 fw-bold text-body">{{ $serverStats['total'] }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body p-3">
                    <div class="text-muted small fw-semibold">{{ __('Server Aktif') }}</div>
                    <div class="fs-4 fw-bold text-success">{{ $serverStats['active'] }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body p-3">
                    <div class="text-muted small fw-semibold">{{ __('Mode REST API') }}</div>
                    <div class="fs-4 fw-bold text-primary">{{ $serverStats['api_driver'] }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body p-3">
                    <div class="text-muted small fw-semibold">{{ __('Status Online') }}</div>
                    <div class="fs-4 fw-bold text-info">{{ $serverStats['online'] }}</div>
                </div></div>
            </div>
        </div>

        {{-- Quick Integration Guide --}}
        <div class="alert alert-info border-0 mb-3 d-flex align-items-start gap-2.5" style="border-radius: 16px;">
            <i class="fas fa-shield-halved fs-5 mt-0.5 text-info"></i>
            <div class="small">
                <div class="fw-bold mb-1">{{ __('Panduan Koneksi Panel vpnxray-main') }}</div>
                <div>{{ __('Buka panel vpnxray-main → Admin → Pengaturan Sistem → kartu External REST API → klik Generate API Token & HMAC Secret, lalu masukkan Base URL, Token, dan HMAC Secret tersebut saat menambah server di sini.') }}</div>
            </div>
        </div>

        @if($servers->isEmpty())
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                        <i class="fas fa-server fs-3"></i>
                    </div>
                    <h6 class="fw-bold text-body mb-1">{{ __('Belum Ada Server VPN Terdaftar') }}</h6>
                    <p class="text-muted small mb-3">{{ __('Tambahkan minimal satu server vpnxray-main (REST API) atau Legacy SSH agar produk VPN otomatis terhubung.') }}</p>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#addVpnServerModal">
                        <i class="fas fa-plus me-1"></i>{{ __('Tambah Server Pertama') }}
                    </button>
                </div>
            </div>
        @else
            {{-- Desktop Table --}}
            <div class="card d-none d-lg-block">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">{{ __('Server') }}</th>
                                <th>{{ __('Driver') }}</th>
                                <th>{{ __('Endpoint') }}</th>
                                <th>{{ __('Produk / Akun') }}</th>
                                <th>{{ __('Status Ping') }}</th>
                                <th class="text-end pe-3">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($servers as $server)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-body d-flex align-items-center gap-1.5">
                                        <span>{{ $server->name }}</span>
                                        @if($server->is_default)
                                            <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size: 0.68rem;">{{ __('Default') }}</span>
                                        @endif
                                        @if(!$server->is_active)
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size: 0.68rem;">{{ __('Nonaktif') }}</span>
                                        @endif
                                    </div>
                                    <small class="text-muted font-monospace">{{ $server->code }} @if($server->region) &bull; {{ $server->region }} @endif</small>
                                </td>
                                <td>
                                    @if($server->isApiDriver())
                                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1"><i class="fas fa-bolt me-1"></i>REST API v1</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5 py-1"><i class="fas fa-terminal me-1"></i>Legacy SSH</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="small font-monospace text-body text-truncate" style="max-width: 240px;" title="{{ $server->display_endpoint }}">{{ $server->display_endpoint }}</div>
                                    <small class="text-muted">Timeout: {{ $server->timeout_seconds }}s @if($server->isApiDriver()) &bull; SSL: {{ $server->verify_ssl ? 'On' : 'Off' }} @endif</small>
                                </td>
                                <td class="small">
                                    <div><span class="fw-semibold text-body">{{ $server->products_count }}</span> {{ __('produk') }}</div>
                                    <div class="text-muted"><span class="fw-semibold text-body">{{ $server->vpn_accounts_count }}</span> {{ __('akun') }}</div>
                                </td>
                                <td>
                                    @if($server->last_status === 'online')
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1">
                                            <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>Online ({{ $server->last_ping_ms ? number_format($server->last_ping_ms, 0) . ' ms' : '-' }})
                                        </span>
                                    @elseif($server->last_status === 'offline')
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1" title="{{ $server->last_error }}">
                                            <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>Offline
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1">{{ __('Belum diuji') }}</span>
                                    @endif
                                    @if($server->last_ping_at)
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ $server->last_ping_at->diffForHumans() }}</div>
                                    @endif
                                    @if($server->last_status === 'offline' && $server->last_error)
                                        <div class="text-danger text-truncate mt-0.5" style="max-width: 220px; font-size: 0.7rem;" title="{{ $server->last_error }}">
                                            <i class="fas fa-circle-exclamation me-0.5"></i>{{ $server->last_error }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2.5 btn-ping-server" data-url="{{ route('admin.vpn-servers.ping', $server->id) }}" data-name="{{ $server->name }}">
                                            <i class="fas fa-heartbeat"></i> {{ __('Test') }}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 btn-diagnose-server" data-url="{{ route('admin.vpn-servers.diagnose', $server->id) }}" data-name="{{ $server->name }}" title="{{ __('Diagnostik 5-Tahap') }}">
                                            <i class="fas fa-stethoscope"></i> {{ __('Diagnostik') }}
                                        </button>
                                        @if(!$server->is_default)
                                        <form action="{{ route('admin.vpn-servers.default', $server->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-2.5" title="{{ __('Jadikan Default') }}">
                                                <i class="fas fa-star"></i>
                                            </button>
                                        </form>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" data-bs-toggle="modal" data-bs-target="#editVpnServerModal{{ $server->id }}" title="{{ __('Edit') }}">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form action="{{ route('admin.vpn-servers.destroy', $server->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Hapus server VPN ini? Akun lokal tetap tersimpan namun tidak lagi terhubung ke server ini.') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="{{ __('Hapus') }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile Collapsible Compact List --}}
            <div class="d-lg-none m3-collapsible-list" id="adminVpnServersAccordion">
                @foreach($servers as $server)
                @php
                    $dotClass = match($server->last_status) {
                        'online' => 'bg-success',
                        'offline' => 'bg-danger',
                        default => 'bg-secondary',
                    };
                @endphp
                <div class="m3-collapsible-card">
                    <div class="m3-compact-row">
                        <button type="button" class="m3-compact-trigger collapsed" data-bs-toggle="collapse" data-bs-target="#srvCollapse{{ $server->id }}" aria-expanded="false">
                            <span class="m3-status-dot {{ $dotClass }}"></span>
                            <div class="min-w-0 flex-grow-1 text-start">
                                <div class="fw-bold text-body text-truncate" style="font-size: 0.9rem;">
                                    {{ $server->name }}
                                    @if($server->is_default)
                                        <span class="badge bg-primary-subtle text-primary ms-1" style="font-size: 0.64rem;">Default</span>
                                    @endif
                                </div>
                                <div class="text-muted text-truncate" style="font-size: 0.74rem;">
                                    {{ $server->isApiDriver() ? 'REST API v1' : 'Legacy SSH' }} &bull; {{ $server->vpn_accounts_count }} {{ __('akun') }}
                                </div>
                            </div>
                            <i class="fas fa-chevron-down m3-compact-chevron"></i>
                        </button>
                        <button type="button" class="m3-compact-action-btn btn-ping-server" data-url="{{ route('admin.vpn-servers.ping', $server->id) }}" data-name="{{ $server->name }}" title="{{ __('Test Koneksi') }}">
                            <i class="fas fa-heartbeat text-info"></i>
                        </button>
                    </div>
                    <div id="srvCollapse{{ $server->id }}" class="collapse" data-bs-parent="#adminVpnServersAccordion">
                        <div class="m3-collapsible-body">
                            <div class="small mb-2">
                                <div class="text-muted">{{ __('Endpoint:') }} <span class="font-monospace text-body text-break">{{ $server->display_endpoint }}</span></div>
                                <div class="text-muted">{{ __('Kode / Region:') }} <span class="text-body">{{ $server->code }} {{ $server->region ? '('.$server->region.')' : '' }}</span></div>
                                @if($server->last_error)
                                    <div class="text-danger mt-1"><i class="fas fa-exclamation-circle me-1"></i>{{ $server->last_error }}</div>
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-1.5">
                                <button type="button" class="btn btn-sm btn-outline-info rounded-pill flex-grow-1 btn-diagnose-server" data-url="{{ route('admin.vpn-servers.diagnose', $server->id) }}" data-name="{{ $server->name }}">
                                    <i class="fas fa-stethoscope me-1"></i>{{ __('Diagnostik') }}
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1" data-bs-toggle="modal" data-bs-target="#editVpnServerModal{{ $server->id }}">
                                    <i class="fas fa-pen me-1"></i>{{ __('Edit') }}
                                </button>
                                @if(!$server->is_default)
                                <form action="{{ route('admin.vpn-servers.default', $server->id) }}" method="POST" class="flex-grow-1">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success rounded-pill w-100">
                                        <i class="fas fa-star me-1"></i>{{ __('Default') }}
                                    </button>
                                </form>
                                @endif
                                <form action="{{ route('admin.vpn-servers.destroy', $server->id) }}" method="POST" onsubmit="return confirm('{{ __('Hapus server VPN ini?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@push('modals')
{{-- ===================================================================== --}}
{{-- M3 ACTION SHEET MODAL FOR ADMIN VPN ACCOUNT ROW ACTIONS               --}}
{{-- ===================================================================== --}}
<div class="modal fade" id="adminVpnActionSheetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-2">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title fw-bold font-monospace mb-0" id="sheetAdminVpnUser">-</h5>
                        <span class="badge bg-primary-subtle text-primary rounded-pill" id="sheetAdminVpnProto">-</span>
                    </div>
                    <div class="small text-muted mt-0.5" id="sheetAdminVpnMeta">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1 pb-3">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-light border text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3" id="sheetBtnViewConfig">
                        <span class="rounded-circle bg-info-subtle text-info d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                            <i class="fas fa-qrcode"></i>
                        </span>
                        <div>
                            <div class="fw-bold small text-body">{{ __('Lihat Config & QR Code') }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">{{ __('Otomatis menarik detail konfigurasi jika belum tersimpan') }}</div>
                        </div>
                    </button>

                    <button type="button" class="btn btn-light border text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3" id="sheetBtnSuspend">
                        <span class="rounded-circle bg-warning-subtle text-warning-emphasis d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                            <i class="fas fa-ban"></i>
                        </span>
                        <div>
                            <div class="fw-bold small text-body">{{ __('Suspend Akun (Kunci Admin)') }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">{{ __('Pelanggan tidak dapat membuka suspend sendiri') }}</div>
                        </div>
                    </button>

                    <form id="sheetFormUnsuspend" method="POST" class="d-none m-0">
                        @csrf
                        <button type="submit" class="btn btn-light border text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3 w-100">
                            <span class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="fas fa-play"></i>
                            </span>
                            <div>
                                <div class="fw-bold small text-body">{{ __('Aktifkan Kembali (Buka Suspend)') }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ __('Pulihkan akses akun VPN pada server') }}</div>
                            </div>
                        </button>
                    </form>

                    <form id="sheetFormSync" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-light border text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3 w-100">
                            <span class="rounded-circle bg-secondary-subtle text-secondary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="fas fa-rotate"></i>
                            </span>
                            <div>
                                <div class="fw-bold small text-body">{{ __('Sync dari Panel VPN') }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ __('Perbarui masa aktif, kuota & link konfigurasi terbaru') }}</div>
                            </div>
                        </button>
                    </form>

                    <form id="sheetFormRetry" method="POST" class="d-none m-0">
                        @csrf
                        <button type="submit" class="btn btn-light border text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3 w-100">
                            <span class="rounded-circle bg-warning-subtle text-warning-emphasis d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="fas fa-redo"></i>
                            </span>
                            <div>
                                <div class="fw-bold small text-body">{{ __('Retry Provisioning') }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ __('Ulangi pembuatan akun pada server panel VPN') }}</div>
                            </div>
                        </button>
                    </form>

                    <form id="sheetFormDelete" method="POST" class="m-0" onsubmit="return confirm('{{ __('Hapus permanen akun VPN ini dari server VPS & database?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3 w-100">
                            <span class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="fas fa-trash"></i>
                            </span>
                            <div>
                                <div class="fw-bold small">{{ __('Hapus Permanen') }}</div>
                                <div class="small opacity-75" style="font-size: 0.72rem;">{{ __('Menghapus akun dari VPS dan database lokal') }}</div>
                            </div>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===================================================================== --}}
{{-- CREATE MANUAL VPN ACCOUNT MODAL (WITH CUSTOMER PICKER & BILLING MODE) --}}
{{-- ===================================================================== --}}
<div class="modal fade" id="adminCreateVpnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle text-primary me-2"></i>{{ __('Buat Akun VPN Manual') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.vpn-accounts.store') }}" method="POST" id="adminCreateVpnForm">
                @csrf
                <input type="hidden" name="customer_id" id="vpnCreateCustomerId" value="">
                <div class="modal-body pt-2">
                    {{-- 1. Pemilik Akun (Customer Picker with Live Search) --}}
                    <div class="mb-3 p-3 rounded-4 border" style="background: var(--surface-container-low);">
                        <label class="form-label small fw-bold text-body d-block mb-2">
                            <i class="fas fa-user-tag text-primary me-1"></i>{{ __('Pemilik Akun VPN') }}
                        </label>
                        <div class="d-flex flex-wrap gap-3 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="assign_target" id="vpnAssignSelf" value="self" checked>
                                <label class="form-check-label small fw-semibold" for="vpnAssignSelf" style="cursor: pointer;">
                                    <i class="fas fa-user-shield text-muted me-1"></i>{{ __('Saya Sendiri (Admin)') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="assign_target" id="vpnAssignCustomer" value="customer">
                                <label class="form-check-label small fw-semibold" for="vpnAssignCustomer" style="cursor: pointer;">
                                    <i class="fas fa-user-check text-success me-1"></i>{{ __('Tugaskan ke Customer Tertentu') }}
                                </label>
                            </div>
                        </div>

                        <div id="vpnCustomerPickerBox" class="d-none mt-2 pt-2 border-top">
                            {{-- Selected Customer Chip --}}
                            <div id="vpnSelectedCustomerChip" class="alert alert-success border-0 shadow-sm d-none align-items-center justify-content-between p-2 mb-2" style="border-radius: 12px;">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <strong class="d-block text-body small text-truncate" id="vpnSelectedCustName">-</strong>
                                        <span class="text-muted d-block text-truncate" style="font-size: 0.72rem;" id="vpnSelectedCustDetail">-</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill flex-shrink-0" id="btnClearVpnCustomer" style="font-size: 0.72rem;">
                                    <i class="fas fa-times me-1"></i>{{ __('Ganti') }}
                                </button>
                            </div>

                            <div id="vpnCustomerSearchWrap">
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" id="vpnCustomerSearchInput" class="form-control border-start-0" placeholder="{{ __('Cari nama, @username, email, atau Telegram ID...') }}" autocomplete="off">
                                </div>
                                <div id="vpnCustomerSearchResults" class="bg-white border rounded-3 p-1 overflow-auto" style="max-height: 160px;">
                                    @forelse($customers as $c)
                                        <div class="vpn-cust-item d-flex align-items-center justify-content-between p-2 rounded-2"
                                             style="cursor: pointer;"
                                             data-id="{{ $c->id }}"
                                             data-name="{{ $c->full_name ?: $c->username }}"
                                             data-username="{{ $c->username }}"
                                             data-tg="{{ $c->telegram_id ?: '-' }}">
                                            <div class="min-w-0">
                                                <div class="small fw-semibold text-body text-truncate">{{ $c->full_name ?: $c->username }}</div>
                                                <div class="text-muted text-truncate" style="font-size: 0.7rem;">
                                                    @if($c->username) @<span>{{ $c->username }}</span> @endif
                                                    @if($c->telegram_id) &bull; TG: <code>{{ $c->telegram_id }}</code> @endif
                                                </div>
                                            </div>
                                            <span class="badge bg-secondary-subtle text-body rounded-pill flex-shrink-0" style="font-size: 0.65rem;">{{ ucfirst($c->role) }}</span>
                                        </div>
                                    @empty
                                        <div class="p-2 text-center text-muted small">{{ __('Ketik untuk mencari customer...') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 2. Optional VPN Product Template --}}
                    @if($vpnProducts->isNotEmpty())
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Template Paket VPN (Opsional)') }}</label>
                        <select name="product_id" id="adminCreateTemplate" class="form-select form-select-sm">
                            <option value="">{{ __('-- Kustom Manual (Isi Sendiri) --') }}</option>
                            @foreach($vpnProducts as $vp)
                                <option value="{{ $vp->id }}"
                                        data-protocol="{{ $vp->vpn_protocol }}"
                                        data-server-id="{{ $vp->vpn_server_id }}"
                                        data-days="{{ $vp->vpn_duration_days ?: 30 }}"
                                        data-quota="{{ $vp->vpn_quota_gb ?? 0 }}"
                                        data-ip="{{ max(1, (int) ($vp->vpn_ip_limit ?? 2)) }}"
                                        data-sni-mode="{{ $vp->vpn_sni_mode ?? '3' }}"
                                        data-price="{{ (int) $vp->price }}">
                                    {{ $vp->name }} — {{ strtoupper($vp->vpn_protocol) }} ({{ $vp->vpn_duration_days }} {{ __('hari') }}) — {{ $vp->formatted_price }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    {{-- 3. Server, Protocol, Duration --}}
                    <div class="row g-2 mb-2.5">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">{{ __('Server VPN') }}</label>
                            <select name="vpn_server_id" id="adminCreateServer" class="form-select">
                                <option value="">{{ __('Gunakan Server Default') }}</option>
                                @foreach($servers as $srv)
                                    <option value="{{ $srv->id }}">{{ $srv->name }} ({{ $srv->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small fw-bold">{{ __('Protokol') }} <span class="text-danger">*</span></label>
                            <select name="protocol" id="adminCreateProto" class="form-select" required>
                                <option value="vmess">VMESS</option>
                                <option value="vless">VLESS</option>
                                <option value="trojan">TROJAN</option>
                                <option value="shadowsocks">SHADOWSOCKS</option>
                                <option value="ssh">SSH</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">{{ __('Masa Aktif (Hari)') }} <span class="text-danger">*</span></label>
                            <input type="number" name="days" id="adminCreateDays" class="form-control" value="30" min="1" max="365" required>
                        </div>
                    </div>

                    {{-- 4. Username with Automatic Live Debounced AJAX Check --}}
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Username VPN') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="username" id="adminCreateUsername" class="form-control font-monospace" required pattern="[a-zA-Z0-9_-]{3,24}" placeholder="user_sg01" autocomplete="off">
                            <span class="input-group-text bg-white" id="adminCreateUserIndicator">
                                <i class="fas fa-user-check text-muted"></i>
                            </span>
                        </div>
                        <div id="adminCreateUserStatus" class="form-text small text-muted">{{ __('Ketersediaan username diperiksa otomatis saat Anda mengetik.') }}</div>
                    </div>

                    <div class="mb-2.5 d-none" id="adminCreateSshPassWrap">
                        <label class="form-label small fw-bold">{{ __('Password SSH') }} <span class="text-danger">*</span></label>
                        <input type="text" name="password" id="adminCreateSshPass" class="form-control font-monospace" placeholder="{{ __('Minimal 4 karakter') }}">
                    </div>

                    <div class="mb-2.5" id="adminCreateSniWrap">
                        <label class="form-label small fw-bold">{{ __('Preset / Custom SNI') }}</label>
                        <select name="sni_mode" id="adminCreateSniMode" class="form-select">
                            <option value="3" selected>{{ __('Tanpa Konfigurasi (Default SNI)') }}</option>
                            <option value="1">Zoom (support.zoom.us — Wildcard)</option>
                            <option value="2">Iflix (live.iflix.com)</option>
                        </select>
                        <div class="form-text small">{{ __('Pilih profil SNI untuk konfigurasi koneksi Xray (sama seperti panel vpnxray-main).') }}</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">{{ __('Kuota (GB, 0=Unli)') }}</label>
                            <input type="number" name="quota_gb" id="adminCreateQuota" class="form-control" value="0" min="0" max="10000">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">{{ __('Limit IP') }}</label>
                            <input type="number" name="ip_limit" id="adminCreateIp" class="form-control" value="2" min="1" max="50">
                        </div>
                    </div>

                    {{-- 5. Toggle Status Pembayaran (Sudah Terbayar vs Kirim Tagihan Billing) --}}
                    <div class="p-3 rounded-4 border" style="background: var(--surface-container-low);">
                        <div class="form-check form-switch d-flex align-items-center justify-content-between ps-0 mb-0">
                            <label class="form-check-label pe-3" for="adminCreateIsPaid" style="cursor: pointer;">
                                <span class="fw-bold small text-body d-block" id="adminPaidToggleTitle">
                                    <i class="fas fa-check-circle text-success me-1"></i>{{ __('Status: Sudah Terbayar (Langsung Aktif)') }}
                                </span>
                                <span class="text-muted d-block" style="font-size: 0.74rem;" id="adminPaidToggleHint">
                                    {{ __('Akun langsung dibuat di server VPN dan muncul pada menu Akun VPN Saya milik pemilik terpilih.') }}
                                </span>
                            </label>
                            <input type="hidden" name="is_paid" value="0">
                            <input class="form-check-input fs-5 m-0 flex-shrink-0" type="checkbox" role="switch" name="is_paid" value="1" id="adminCreateIsPaid" checked style="cursor: pointer;">
                        </div>

                        <div id="adminBillingAmountWrap" class="d-none mt-3 pt-3 border-top">
                            <label class="form-label small fw-bold text-body mb-1">
                                {{ __('Nominal Tagihan (Rp)') }} <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold">Rp</span>
                                <input type="number" name="billing_amount" id="adminBillingAmount" class="form-control" min="1000" max="100000000" placeholder="15000">
                            </div>
                            <div class="form-text small text-primary mt-1">
                                <i class="fas fa-receipt me-1"></i>{{ __('Pesanan berstatus Menunggu Pembayaran (QRIS + notifikasi) akan dikirim ke customer. Begitu lunas, akun VPN otomatis di-provision dan aktif.') }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnSubmitAdminCreateVpn">
                        <i class="fas fa-plus me-1"></i><span>{{ __('Buat Akun Sekarang') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================================================================== --}}
{{-- RENEW MODAL WITH QUICK-PRESET (+1, +7, +30, +60) & EXPIRY PREVIEW     --}}
{{-- ===================================================================== --}}
<div class="modal fade" id="adminRenewVpnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="fas fa-calendar-plus text-primary me-2"></i>{{ __('Perpanjang Akun VPN') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="adminRenewForm" method="POST">
                @csrf
                <div class="modal-body pt-2">
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Username') }}</label>
                        <input type="text" id="renewModalUsername" class="form-control font-monospace" readonly>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Tambah Durasi (Hari)') }} <span class="text-danger">*</span></label>
                        <input type="number" name="days" id="adminRenewDaysInput" class="form-control mb-2" value="30" min="1" max="365" required>
                        <div class="d-flex flex-wrap gap-1.5">
                            @foreach([1, 7, 30, 60] as $preset)
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold btn-admin-renew-preset {{ $preset === 30 ? 'active' : '' }}" data-days="{{ $preset }}">
                                +{{ $preset }} {{ __('Hari') }}
                            </button>
                            @endforeach
                        </div>
                        <div class="mt-2 p-2.5 rounded-3 border small d-flex align-items-center justify-content-between" style="background: var(--surface-container-low);">
                            <span class="text-muted">{{ __('Estimasi Expired Baru:') }}</span>
                            <strong class="text-primary" id="adminRenewExpiryPreview">-</strong>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">{{ __('Kuota (GB)') }}</label>
                            <input type="number" name="quota_gb" id="renewModalQuota" class="form-control" min="0" max="10000">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">{{ __('Limit IP') }}</label>
                            <input type="number" name="ip_limit" id="renewModalIp" class="form-control" min="1" max="50">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Perpanjang Sekarang') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Suspend Modal (Admin Lock) --}}
<div class="modal fade" id="adminSuspendVpnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold text-warning-emphasis"><i class="fas fa-ban me-2"></i>{{ __('Suspend Akun VPN (Kunci Admin)') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="adminSuspendForm" method="POST">
                @csrf
                <div class="modal-body pt-2">
                    <div class="alert alert-warning border-0 small mb-3" style="border-radius: 14px;">
                        <i class="fas fa-lock me-1"></i>{{ __('Akun yang disuspend oleh Admin tidak dapat diaktifkan kembali oleh pelanggan sendiri. Pelanggan wajib menghubungi Admin via Chat untuk membuka suspend.') }}
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Username') }}</label>
                        <input type="text" id="suspendModalUsername" class="form-control font-monospace" readonly>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold">{{ __('Alasan Suspend (Ditampilkan ke Pelanggan)') }}</label>
                        <input type="text" name="reason" class="form-control" placeholder="{{ __('Contoh: Pelanggaran batas multi-login / penyalahgunaan') }}" maxlength="120">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-semibold">{{ __('Suspend Sekarang') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Sync Server Modal --}}
<div class="modal fade" id="syncServerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="fas fa-rotate text-primary me-2"></i>{{ __('Sync Akun dari Panel vpnxray-main') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.vpn-accounts.sync-server') }}" method="POST">
                @csrf
                <div class="modal-body pt-2">
                    <p class="small text-muted mb-3">{{ __('Tarik seluruh daftar akun beserta detail konfigurasinya dari panel vpnxray-main untuk memperbarui status, masa aktif, kuota, dan mengimpor akun yang belum tercatat di website jualan.') }}</p>
                    <label class="form-label small fw-bold">{{ __('Pilih Server VPN (REST API)') }}</label>
                    <select name="vpn_server_id" class="form-select" required>
                        @foreach($servers->where('driver', 'vpnxray_api') as $srv)
                            <option value="{{ $srv->id }}">{{ $srv->name }} ({{ $srv->display_endpoint }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Mulai Sync') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Config & QR Modal (with Lazy Auto-Fetch) --}}
<div class="modal fade" id="vpnConfigViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold" id="vpnConfigViewerTitle">{{ __('Konfigurasi VPN') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="vpnConfigLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">{{ __('Menarik detail konfigurasi langsung dari panel VPN...') }}</div>
                </div>
                <div id="vpnConfigBodyContent">
                    <div id="vpnQrContainer" class="text-center mb-3 d-none">
                        <canvas id="vpnConfigQrCanvas" width="210" height="210" class="rounded-4 border p-2 bg-white"></canvas>
                    </div>
                    <div id="vpnConfigSniWrap" class="mb-3 d-none">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-sliders text-primary me-1"></i>{{ __('Preset Mode SNI / Payload') }}</label>
                        <select id="vpnConfigSniSelect" class="form-select form-select-sm">
                            <option value="3">{{ __('Tanpa Konfigurasi (Default SNI)') }}</option>
                            <option value="1">Zoom (support.zoom.us — Wildcard)</option>
                            <option value="2">Iflix (live.iflix.com)</option>
                        </select>
                    </div>
                    <div id="vpnLinksContainer" class="d-flex flex-column gap-2 mb-3"></div>
                    <label class="form-label small fw-bold text-muted">{{ __('Teks Konfigurasi Lengkap') }}</label>
                    <textarea id="vpnConfigFullText" class="form-control font-monospace small" rows="6" readonly></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-1 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="btnRefreshVpnConfig" title="{{ __('Tarik Ulang dari Panel') }}">
                    <i class="fas fa-rotate me-1"></i>{{ __('Refresh') }}
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary rounded-pill px-3" id="btnCopyFullVpnConfig"><i class="fas fa-copy me-1"></i>{{ __('Salin Semua') }}</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===================================================================== --}}
{{-- SERVER VPN MODALS (ADD, EDIT, 5-STAGE DIAGNOSTIC)                     --}}
{{-- ===================================================================== --}}
<div class="modal fade" id="addVpnServerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="fas fa-server text-primary me-2"></i>{{ __('Tambah Server VPN Baru') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.vpn-servers.store') }}" method="POST">
                @csrf
                <div class="modal-body pt-2">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('Nama Server') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Singapore SG-01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">{{ __('Kode Unik') }} <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control font-monospace" required placeholder="sg-01" pattern="[a-zA-Z0-9_-]+">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">{{ __('Region') }}</label>
                            <input type="text" name="region" class="form-control" placeholder="SG / ID / JP">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('Driver Koneksi') }} <span class="text-danger">*</span></label>
                            <select name="driver" class="form-select vpn-driver-select" data-target="add" required>
                                <option value="vpnxray_api" selected>{{ __('REST API vpnxray-main (Direkomendasikan)') }}</option>
                                <option value="legacy_ssh">{{ __('Legacy SSH Root (Fallback VPS Lama)') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">{{ __('Timeout (Detik)') }}</label>
                            <input type="number" name="timeout_seconds" class="form-control" value="15" min="3" max="60">
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-3 pb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="addSrvActive" checked>
                                <label class="form-check-label small fw-semibold" for="addSrvActive">{{ __('Aktif') }}</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="addSrvDefault">
                                <label class="form-check-label small fw-semibold" for="addSrvDefault">{{ __('Default') }}</label>
                            </div>
                        </div>

                        <div class="col-12 vpn-api-fields-add">
                            <div class="p-3 rounded-4 border" style="background: var(--surface-container-low);">
                                <div class="fw-bold small text-primary mb-2"><i class="fas fa-plug me-1"></i>{{ __('Kredensial REST API vpnxray-main') }}</div>
                                <div class="mb-2.5">
                                    <label class="form-label small fw-semibold">{{ __('Base URL Panel vpnxray-main') }} <span class="text-danger">*</span></label>
                                    <input type="url" name="base_url" class="form-control font-monospace" placeholder="https://panel-sg1.domainanda.com">
                                    <div class="form-text small">{{ __('Otomatis diarahkan ke /api/v1/vpn jika belum disertakan.') }}</div>
                                </div>
                                <div class="row g-2.5">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">{{ __('Bearer API Token') }} <span class="text-danger">*</span></label>
                                        <input type="password" name="api_token" class="form-control font-monospace" autocomplete="new-password" placeholder="vpnx_...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">{{ __('HMAC-SHA256 Secret') }} <span class="text-danger">*</span></label>
                                        <input type="password" name="hmac_secret" class="form-control font-monospace" autocomplete="new-password" placeholder="64-char hex secret">
                                    </div>
                                </div>
                                <div class="form-check form-switch mt-2.5">
                                    <input class="form-check-input" type="checkbox" name="verify_ssl" value="1" id="addVerifySsl" checked>
                                    <label class="form-check-label small" for="addVerifySsl">{{ __('Verifikasi Sertifikat SSL (HTTPS)') }}</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 vpn-ssh-fields-add d-none">
                            <div class="p-3 rounded-4 border" style="background: var(--surface-container-low);">
                                <div class="fw-bold small text-warning-emphasis mb-2"><i class="fas fa-terminal me-1"></i>{{ __('Kredensial Legacy SSH') }}</div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">{{ __('Host / IP VPS') }}</label>
                                        <input type="text" name="ssh_host" class="form-control font-monospace" placeholder="103.x.x.x">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">{{ __('Port SSH') }}</label>
                                        <input type="number" name="ssh_port" class="form-control" value="22">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">{{ __('Username') }}</label>
                                        <input type="text" name="ssh_username" class="form-control" value="root">
                                    </div>
                                </div>
                                <label class="form-label small fw-semibold">{{ __('Private Key (PEM/OpenSSH)') }}</label>
                                <textarea name="ssh_private_key" class="form-control font-monospace small" rows="4" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Simpan Server') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($servers as $server)
<div class="modal fade" id="editVpnServerModal{{ $server->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="fas fa-pen text-primary me-2"></i>{{ __('Edit Server:') }} {{ $server->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.vpn-servers.update', $server->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body pt-2">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('Nama Server') }}</label>
                            <input type="text" name="name" class="form-control" required value="{{ $server->name }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">{{ __('Kode Unik') }}</label>
                            <input type="text" name="code" class="form-control font-monospace" required value="{{ $server->code }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">{{ __('Region') }}</label>
                            <input type="text" name="region" class="form-control" value="{{ $server->region }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('Driver Koneksi') }}</label>
                            <select name="driver" class="form-select vpn-driver-select" data-target="edit{{ $server->id }}" required>
                                <option value="vpnxray_api" {{ $server->driver === 'vpnxray_api' ? 'selected' : '' }}>{{ __('REST API vpnxray-main') }}</option>
                                <option value="legacy_ssh" {{ $server->driver === 'legacy_ssh' ? 'selected' : '' }}>{{ __('Legacy SSH') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">{{ __('Timeout (Detik)') }}</label>
                            <input type="number" name="timeout_seconds" class="form-control" value="{{ $server->timeout_seconds }}" min="3" max="60">
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-3 pb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editSrvActive{{ $server->id }}" {{ $server->is_active ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold" for="editSrvActive{{ $server->id }}">{{ __('Aktif') }}</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="editSrvDefault{{ $server->id }}" {{ $server->is_default ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold" for="editSrvDefault{{ $server->id }}">{{ __('Default') }}</label>
                            </div>
                        </div>

                        <div class="col-12 vpn-api-fields-edit{{ $server->id }} {{ $server->isApiDriver() ? '' : 'd-none' }}">
                            <div class="p-3 rounded-4 border" style="background: var(--surface-container-low);">
                                <div class="mb-2.5">
                                    <label class="form-label small fw-semibold">{{ __('Base URL Panel vpnxray-main') }}</label>
                                    <input type="url" name="base_url" class="form-control font-monospace" value="{{ $server->base_url }}">
                                </div>
                                <div class="row g-2.5">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">{{ __('Bearer API Token Baru (Kosongkan jika tetap)') }}</label>
                                        <input type="password" name="api_token" class="form-control font-monospace" autocomplete="new-password" placeholder="••••••••••••">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">{{ __('HMAC Secret Baru (Kosongkan jika tetap)') }}</label>
                                        <input type="password" name="hmac_secret" class="form-control font-monospace" autocomplete="new-password" placeholder="••••••••••••">
                                    </div>
                                </div>
                                <div class="form-check form-switch mt-2.5">
                                    <input class="form-check-input" type="checkbox" name="verify_ssl" value="1" id="editVerifySsl{{ $server->id }}" {{ $server->verify_ssl ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="editVerifySsl{{ $server->id }}">{{ __('Verifikasi Sertifikat SSL (HTTPS)') }}</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 vpn-ssh-fields-edit{{ $server->id }} {{ $server->isApiDriver() ? 'd-none' : '' }}">
                            <div class="p-3 rounded-4 border" style="background: var(--surface-container-low);">
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">{{ __('Host / IP VPS') }}</label>
                                        <input type="text" name="ssh_host" class="form-control font-monospace" value="{{ $server->ssh_host }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">{{ __('Port SSH') }}</label>
                                        <input type="number" name="ssh_port" class="form-control" value="{{ $server->ssh_port ?: 22 }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">{{ __('Username') }}</label>
                                        <input type="text" name="ssh_username" class="form-control" value="{{ $server->ssh_username ?: 'root' }}">
                                    </div>
                                </div>
                                <label class="form-label small fw-semibold">{{ __('Private Key Baru (Kosongkan jika tetap)') }}</label>
                                <textarea name="ssh_private_key" class="form-control font-monospace small" rows="3" placeholder="Kosongkan jika tidak mengubah key"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Simpan Perubahan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

{{-- Deep 5-Stage Diagnostic Modal --}}
<div class="modal fade" id="vpnDiagnoseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="fas fa-stethoscope text-primary"></i>
                    <span id="vpnDiagTitle">{{ __('Diagnostik Koneksi Server VPN') }}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="vpnDiagLoading" class="text-center py-4">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">{{ __('Menjalankan diagnostik 5-tahap (DNS, TCP, HTTP/2 vs HTTP/1.1, SSL, Clock Skew, Auth & Health)...') }}</div>
                </div>
                <div id="vpnDiagContent" class="d-none">
                    <div id="vpnDiagHeadline" class="alert border-0 small mb-3" style="border-radius: 14px;"></div>
                    <div id="vpnDiagSteps" class="d-flex flex-column gap-2 mb-3"></div>
                    <div id="vpnDiagRecsWrap" class="d-none p-3 rounded-4 border" style="background: var(--surface-container-low);">
                        <div class="fw-bold small text-body mb-1"><i class="fas fa-lightbulb text-warning me-1"></i>{{ __('Rekomendasi Perbaikan') }}</div>
                        <ul id="vpnDiagRecs" class="small mb-0 ps-3"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-1">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3 d-none" id="btnCopyVpnDiag"><i class="fas fa-copy me-1"></i>{{ __('Salin Laporan') }}</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
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

    // =========================================================================
    // 1. UNIFIED TAB SYNC (Header Buttons + URL Query Param)
    // =========================================================================
    const hdrAcc = document.getElementById('vpnHeaderActionsAccounts');
    const hdrSrv = document.getElementById('vpnHeaderActionsServers');
    document.querySelectorAll('#adminVpnMainTabs [data-vpn-tab]').forEach(function (tabBtn) {
        tabBtn.addEventListener('shown.bs.tab', function () {
            const tab = this.dataset.vpnTab;
            const isSrv = tab === 'servers';
            if (hdrAcc) hdrAcc.classList.toggle('d-none', isSrv);
            if (hdrSrv) hdrSrv.classList.toggle('d-none', !isSrv);

            // Update badge contrast on pills
            document.querySelectorAll('#adminVpnMainTabs [data-vpn-tab]').forEach(function (b) {
                const badge = b.querySelector('.badge');
                if (!badge) return;
                const active = b.classList.contains('active');
                badge.className = 'badge rounded-pill ' + (active ? 'bg-white text-primary' : 'bg-primary-subtle text-primary');
            });

            if (window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                window.history.replaceState(null, '', url.toString());
            }
        });
    });

    // =========================================================================
    // 2. CONFIG & QR VIEWER WITH LAZY AUTO-FETCH + LIVE PRESET SNI SWITCHER
    // =========================================================================
    let currentConfigUrl = '';
    let activeConfigState = null;
    const configModalEl = document.getElementById('vpnConfigViewerModal');
    const configLoadingEl = document.getElementById('vpnConfigLoading');
    const configBodyEl = document.getElementById('vpnConfigBodyContent');
    const configSniWrap = document.getElementById('vpnConfigSniWrap');
    const configSniSelect = document.getElementById('vpnConfigSniSelect');

    function encodeB64Utf8(str) {
        try { return btoa(unescape(encodeURIComponent(str))); } catch (e) { return btoa(str); }
    }

    function buildXrayLinksForSni(protocol, domain, uuid, username, sniMode) {
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

    function formatXrayTextForSni(payload, links, sniMode) {
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

    function renderVpnConfigPayload(title, payload, fullText, overrideSni = null) {
        document.getElementById('vpnConfigViewerTitle').textContent = title || 'VPN Config';
        activeConfigState = { title, payload, fullText };

        let linksObj = (payload && payload.links && typeof payload.links === 'object') ? payload.links : null;
        let textToShow = fullText || '';

        const canSwitchSni = !!(payload && payload.protocol && payload.protocol !== 'ssh' && payload.domain && payload.uuid);
        if (configSniWrap && configSniSelect) {
            configSniWrap.classList.toggle('d-none', !canSwitchSni);
            if (canSwitchSni) {
                const chosenSni = overrideSni || String(payload.sni_mode || '3');
                configSniSelect.value = ['1', '2', '3'].includes(chosenSni) ? chosenSni : '3';
                if (overrideSni !== null) {
                    const regenerated = buildXrayLinksForSni(String(payload.protocol).toLowerCase(), payload.domain, payload.uuid, payload.username || '', configSniSelect.value);
                    if (regenerated) {
                        linksObj = regenerated;
                        textToShow = formatXrayTextForSni(payload, regenerated, configSniSelect.value);
                    }
                }
            }
        }

        document.getElementById('vpnConfigFullText').value = textToShow;

        const linksWrap = document.getElementById('vpnLinksContainer');
        const qrWrap = document.getElementById('vpnQrContainer');
        const qrCanvas = document.getElementById('vpnConfigQrCanvas');
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

    configSniSelect?.addEventListener('change', function () {
        if (!activeConfigState || !activeConfigState.payload) return;
        renderVpnConfigPayload(activeConfigState.title, activeConfigState.payload, activeConfigState.fullText, this.value);
    });

    async function openVpnConfigModal(title, payload, fullText, configUrl, forceRefresh = false) {
        currentConfigUrl = configUrl || '';
        document.getElementById('vpnConfigViewerTitle').textContent = title || 'VPN Config';
        const needsFetch = forceRefresh || (!payload && (!fullText || !fullText.trim()));

        bootstrap.Modal.getOrCreateInstance(configModalEl).show();

        if (!needsFetch) {
            configLoadingEl.classList.add('d-none');
            configBodyEl.classList.remove('d-none');
            renderVpnConfigPayload(title, payload, fullText);
            return;
        }

        if (!currentConfigUrl) {
            configLoadingEl.classList.add('d-none');
            configBodyEl.classList.remove('d-none');
            renderVpnConfigPayload(title, payload, fullText || '{{ __("Konfigurasi belum tersedia.") }}');
            return;
        }

        configLoadingEl.classList.remove('d-none');
        configBodyEl.classList.add('d-none');
        try {
            const url = currentConfigUrl + (forceRefresh ? '?refresh=1' : '');
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            configLoadingEl.classList.add('d-none');
            configBodyEl.classList.remove('d-none');
            renderVpnConfigPayload(data.title || title, data.config_payload || null, data.config_link || '');
        } catch (e) {
            configLoadingEl.classList.add('d-none');
            configBodyEl.classList.remove('d-none');
            renderVpnConfigPayload(title, null, '{{ __("Gagal menarik detail konfigurasi dari panel VPN.") }}');
        }
    }

    const btnRefreshCfg = document.getElementById('btnRefreshVpnConfig');
    if (btnRefreshCfg) {
        btnRefreshCfg.addEventListener('click', function () {
            if (!currentConfigUrl) return;
            openVpnConfigModal(document.getElementById('vpnConfigViewerTitle').textContent, null, '', currentConfigUrl, true);
        });
    }

    const copyAllBtn = document.getElementById('btnCopyFullVpnConfig');
    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function () {
            navigator.clipboard?.writeText(document.getElementById('vpnConfigFullText').value);
            const orig = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check text-success me-1"></i>{{ __("Tersalin!") }}';
            setTimeout(() => { this.innerHTML = orig; }, 1600);
        });
    }

    // Direct buttons inside Desktop Dropdown menu
    document.querySelectorAll('.btn-direct-vpn-config').forEach(function (btn) {
        btn.addEventListener('click', function () {
            let parsedPayload = null;
            try { parsedPayload = JSON.parse(this.dataset.payload || 'null'); } catch (e) {}
            openVpnConfigModal(this.dataset.title || 'VPN Config', parsedPayload, this.dataset.text || '', this.dataset.configUrl || '', false);
        });
    });

    document.querySelectorAll('.btn-direct-vpn-suspend').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('adminSuspendForm').action = this.dataset.action || '';
            document.getElementById('suspendModalUsername').value = this.dataset.username || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('adminSuspendVpnModal')).show();
        });
    });

    // =========================================================================
    // 3. ADMIN ACTION SHEET MODAL (SMARTPHONE / MOBILE ONLY)
    // =========================================================================
    const sheetModalEl = document.getElementById('adminVpnActionSheetModal');
    let activeSheetData = null;

    document.querySelectorAll('.btn-open-admin-vpn-sheet').forEach(function (btn) {
        btn.addEventListener('click', function () {
            let parsedPayload = null;
            try { parsedPayload = JSON.parse(this.dataset.payload || 'null'); } catch (e) {}

            activeSheetData = {
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
                retryUrl: this.dataset.retryUrl || '',
                deleteUrl: this.dataset.deleteUrl || '',
            };

            document.getElementById('sheetAdminVpnUser').textContent = activeSheetData.username;
            document.getElementById('sheetAdminVpnProto').textContent = activeSheetData.protocol;
            document.getElementById('sheetAdminVpnMeta').textContent = activeSheetData.server + ' • ' + activeSheetData.statusLabel;

            const isFailed = activeSheetData.status === 'failed' || activeSheetData.status === 'provisioning';
            const isSuspended = activeSheetData.status === 'suspended';

            document.getElementById('sheetBtnSuspend').classList.toggle('d-none', isFailed || isSuspended);
            const fUnsusp = document.getElementById('sheetFormUnsuspend');
            fUnsusp.classList.toggle('d-none', isFailed || !isSuspended);
            fUnsusp.action = activeSheetData.unsuspendUrl;

            const fSync = document.getElementById('sheetFormSync');
            fSync.classList.toggle('d-none', isFailed);
            fSync.action = activeSheetData.syncUrl;

            const fRetry = document.getElementById('sheetFormRetry');
            fRetry.classList.toggle('d-none', !isFailed);
            fRetry.action = activeSheetData.retryUrl;

            document.getElementById('sheetFormDelete').action = activeSheetData.deleteUrl;

            bootstrap.Modal.getOrCreateInstance(sheetModalEl).show();
        });
    });

    document.getElementById('sheetBtnViewConfig')?.addEventListener('click', function () {
        if (!activeSheetData) return;
        bootstrap.Modal.getOrCreateInstance(sheetModalEl).hide();
        openVpnConfigModal(
            activeSheetData.protocol + ' — ' + activeSheetData.username,
            activeSheetData.payload,
            activeSheetData.text,
            activeSheetData.configUrl,
            false
        );
    });

    document.getElementById('sheetBtnSuspend')?.addEventListener('click', function () {
        if (!activeSheetData) return;
        bootstrap.Modal.getOrCreateInstance(sheetModalEl).hide();
        document.getElementById('adminSuspendForm').action = activeSheetData.suspendUrl;
        document.getElementById('suspendModalUsername').value = activeSheetData.username;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('adminSuspendVpnModal')).show();
    });

    // =========================================================================
    // 4. RENEW MODAL WITH QUICK-PRESETS (+1, +7, +30, +60) & LIVE PREVIEW
    // =========================================================================
    let renewBaseExpiryDate = null;
    const renewDaysInput = document.getElementById('adminRenewDaysInput');
    const renewPreviewEl = document.getElementById('adminRenewExpiryPreview');

    function updateAdminRenewPreview() {
        if (!renewDaysInput || !renewPreviewEl) return;
        const days = Math.max(1, parseInt(renewDaysInput.value || '0', 10) || 0);
        const now = new Date();
        const base = (renewBaseExpiryDate && renewBaseExpiryDate > now) ? new Date(renewBaseExpiryDate.getTime()) : now;
        base.setDate(base.getDate() + days);
        renewPreviewEl.textContent = base.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

        document.querySelectorAll('.btn-admin-renew-preset').forEach(function (pBtn) {
            pBtn.classList.toggle('active', parseInt(pBtn.dataset.days, 10) === days);
        });
    }

    if (renewDaysInput) {
        renewDaysInput.addEventListener('input', updateAdminRenewPreview);
    }

    document.querySelectorAll('.btn-admin-renew-preset').forEach(function (pBtn) {
        pBtn.addEventListener('click', function () {
            if (renewDaysInput) {
                renewDaysInput.value = this.dataset.days;
                updateAdminRenewPreview();
            }
        });
    });

    document.querySelectorAll('.btn-open-renew').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('adminRenewForm').action = this.dataset.action;
            document.getElementById('renewModalUsername').value = this.dataset.username || '';
            document.getElementById('renewModalQuota').value = this.dataset.quota || 0;
            document.getElementById('renewModalIp').value = this.dataset.ip || 1;
            renewBaseExpiryDate = this.dataset.expiredIso ? new Date(this.dataset.expiredIso) : null;
            if (renewDaysInput) renewDaysInput.value = 30;
            updateAdminRenewPreview();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('adminRenewVpnModal')).show();
        });
    });

    // =========================================================================
    // 5. CREATE MANUAL MODAL: CUSTOMER PICKER + TEMPLATE + BILLING + LIVE CHECK
    // =========================================================================
    const assignSelfRadio = document.getElementById('vpnAssignSelf');
    const assignCustRadio = document.getElementById('vpnAssignCustomer');
    const custPickerBox = document.getElementById('vpnCustomerPickerBox');
    const custIdInput = document.getElementById('vpnCreateCustomerId');
    const custChip = document.getElementById('vpnSelectedCustomerChip');
    const custSearchWrap = document.getElementById('vpnCustomerSearchWrap');
    const custSearchInput = document.getElementById('vpnCustomerSearchInput');
    const custResultsBox = document.getElementById('vpnCustomerSearchResults');

    function toggleVpnCustomerBox() {
        const show = assignCustRadio && assignCustRadio.checked;
        if (custPickerBox) custPickerBox.classList.toggle('d-none', !show);
        if (!show && custIdInput) custIdInput.value = '';
    }
    assignSelfRadio?.addEventListener('change', toggleVpnCustomerBox);
    assignCustRadio?.addEventListener('change', toggleVpnCustomerBox);

    function selectVpnCustomer(id, name, username, tg) {
        if (custIdInput) custIdInput.value = id;
        document.getElementById('vpnSelectedCustName').textContent = name;
        document.getElementById('vpnSelectedCustDetail').textContent = (username ? '@' + username : '') + (tg && tg !== '-' ? ' • TG: ' + tg : '');
        custChip.classList.remove('d-none');
        custChip.classList.add('d-flex');
        custSearchWrap.classList.add('d-none');
    }

    function bindCustomerItemClicks() {
        custResultsBox?.querySelectorAll('.vpn-cust-item').forEach(function (item) {
            item.addEventListener('click', function () {
                selectVpnCustomer(this.dataset.id, this.dataset.name, this.dataset.username, this.dataset.tg);
            });
        });
    }
    bindCustomerItemClicks();

    document.getElementById('btnClearVpnCustomer')?.addEventListener('click', function () {
        if (custIdInput) custIdInput.value = '';
        custChip.classList.add('d-none');
        custChip.classList.remove('d-flex');
        custSearchWrap.classList.remove('d-none');
        custSearchInput?.focus();
    });

    let custSearchTimer = null;
    custSearchInput?.addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(custSearchTimer);
        custSearchTimer = setTimeout(async function () {
            try {
                const res = await fetch("{{ route('admin.stock.search-customers') }}?q=" + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const list = await res.json();
                if (!Array.isArray(list) || list.length === 0) {
                    custResultsBox.innerHTML = '<div class="p-2 text-center text-muted small">{{ __("Customer tidak ditemukan.") }}</div>';
                    return;
                }
                custResultsBox.innerHTML = list.map(function (c) {
                    const displayName = c.full_name || c.username || ('User #' + c.id);
                    const sub = (c.username ? '@' + c.username : '') + (c.telegram_id ? ' • TG: ' + c.telegram_id : '');
                    return `<div class="vpn-cust-item d-flex align-items-center justify-content-between p-2 rounded-2" style="cursor:pointer;"
                                 data-id="${c.id}" data-name="${escHtml(displayName)}" data-username="${escHtml(c.username || '')}" data-tg="${escHtml(c.telegram_id || '-')}">
                                <div class="min-w-0">
                                    <div class="small fw-semibold text-body text-truncate">${escHtml(displayName)}</div>
                                    <div class="text-muted text-truncate" style="font-size:0.7rem;">${escHtml(sub)}</div>
                                </div>
                                <span class="badge bg-secondary-subtle text-body rounded-pill flex-shrink-0" style="font-size:0.65rem;">${escHtml(c.role || 'customer')}</span>
                            </div>`;
                }).join('');
                bindCustomerItemClicks();
            } catch (e) {}
        }, 250);
    });

    // Template product autofill
    const tplSelect = document.getElementById('adminCreateTemplate');
    const protoSelect = document.getElementById('adminCreateProto');
    const serverSelect = document.getElementById('adminCreateServer');
    const passWrap = document.getElementById('adminCreateSshPassWrap');
    const passInput = document.getElementById('adminCreateSshPass');
    const sniWrap = document.getElementById('adminCreateSniWrap');
    const sniSelect = document.getElementById('adminCreateSniMode');

    function syncSshPasswordVisibility() {
        const isSsh = protoSelect && protoSelect.value === 'ssh';
        if (passWrap) passWrap.classList.toggle('d-none', !isSsh);
        if (passInput) passInput.required = isSsh;
        if (sniWrap) sniWrap.classList.toggle('d-none', isSsh);
    }

    if (tplSelect) {
        tplSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) return;
            if (opt.dataset.protocol && protoSelect) protoSelect.value = opt.dataset.protocol;
            if (opt.dataset.serverId && serverSelect) serverSelect.value = opt.dataset.serverId;
            if (opt.dataset.days) document.getElementById('adminCreateDays').value = opt.dataset.days;
            if (opt.dataset.quota !== undefined) document.getElementById('adminCreateQuota').value = opt.dataset.quota;
            if (opt.dataset.ip) document.getElementById('adminCreateIp').value = opt.dataset.ip;
            if (opt.dataset.sniMode && sniSelect) sniSelect.value = opt.dataset.sniMode;
            if (opt.dataset.price && parseInt(opt.dataset.price, 10) > 0) {
                document.getElementById('adminBillingAmount').value = opt.dataset.price;
            }
            syncSshPasswordVisibility();
            triggerAdminUsernameCheck();
        });
    }

    // Paid vs Billing toggle
    const isPaidToggle = document.getElementById('adminCreateIsPaid');
    const billingWrap = document.getElementById('adminBillingAmountWrap');
    const billingInput = document.getElementById('adminBillingAmount');
    const paidTitle = document.getElementById('adminPaidToggleTitle');
    const paidHint = document.getElementById('adminPaidToggleHint');
    const submitCreateBtn = document.getElementById('btnSubmitAdminCreateVpn');

    function syncPaidToggleUI() {
        const paid = !isPaidToggle || isPaidToggle.checked;
        if (billingWrap) billingWrap.classList.toggle('d-none', paid);
        if (billingInput) billingInput.required = !paid;
        if (paidTitle) {
            paidTitle.innerHTML = paid
                ? '<i class="fas fa-check-circle text-success me-1"></i>{{ __("Status: Sudah Terbayar (Langsung Aktif)") }}'
                : '<i class="fas fa-file-invoice-dollar text-warning me-1"></i>{{ __("Status: Belum Terbayar (Kirim Billing / Tagihan)") }}';
        }
        if (paidHint) {
            paidHint.textContent = paid
                ? '{{ __("Akun langsung dibuat di server VPN dan muncul pada menu Akun VPN Saya milik pemilik terpilih.") }}'
                : '{{ __("Membuat tagihan pembayaran (QRIS) untuk customer. Akun VPN otomatis dibuat begitu pembayaran diterima.") }}';
        }
        if (submitCreateBtn) {
            submitCreateBtn.querySelector('span').textContent = paid ? '{{ __("Buat Akun Sekarang") }}' : '{{ __("Buat & Kirim Tagihan") }}';
        }
    }
    isPaidToggle?.addEventListener('change', syncPaidToggleUI);
    syncPaidToggleUI();

    // Automatic Debounced Live AJAX Username Check (450ms + AbortController)
    const adminUserInput = document.getElementById('adminCreateUsername');
    const adminUserStatus = document.getElementById('adminCreateUserStatus');
    const adminUserIndicator = document.getElementById('adminCreateUserIndicator');
    let adminUserCheckTimer = null;
    let adminUserAbortCtrl = null;
    let adminUsernameBlocked = false;

    function updateSubmitLock() {
        if (submitCreateBtn) submitCreateBtn.disabled = adminUsernameBlocked;
    }

    function triggerAdminUsernameCheck() {
        if (!adminUserInput || !adminUserStatus) return;
        const username = adminUserInput.value.trim();
        clearTimeout(adminUserCheckTimer);
        if (adminUserAbortCtrl) { adminUserAbortCtrl.abort(); adminUserAbortCtrl = null; }

        if (username.length === 0) {
            adminUsernameBlocked = false;
            adminUserIndicator.innerHTML = '<i class="fas fa-user-check text-muted"></i>';
            adminUserStatus.className = 'form-text small text-muted';
            adminUserStatus.textContent = '{{ __("Ketersediaan username diperiksa otomatis saat Anda mengetik.") }}';
            updateSubmitLock();
            return;
        }
        if (!/^[a-zA-Z0-9_-]{3,24}$/.test(username)) {
            adminUsernameBlocked = true;
            adminUserIndicator.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>';
            adminUserStatus.className = 'form-text small text-warning fw-semibold';
            adminUserStatus.textContent = '{{ __("Username harus 3–24 karakter (huruf, angka, _ atau -).") }}';
            updateSubmitLock();
            return;
        }

        adminUserIndicator.innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i>';
        adminUserStatus.className = 'form-text small text-muted';
        adminUserStatus.textContent = '{{ __("Memeriksa ketersediaan username di server VPN...") }}';

        adminUserCheckTimer = setTimeout(async function () {
            adminUserAbortCtrl = new AbortController();
            const proto = protoSelect ? protoSelect.value : 'vmess';
            const srvId = serverSelect ? serverSelect.value : '';
            const url = "{{ route('admin.vpn-accounts.check-username') }}?username=" + encodeURIComponent(username)
                + "&protocol=" + encodeURIComponent(proto)
                + (srvId ? "&server_id=" + encodeURIComponent(srvId) : "");
            try {
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: adminUserAbortCtrl.signal
                });
                const data = await res.json();
                if (data.success && data.available) {
                    adminUsernameBlocked = false;
                    adminUserIndicator.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                    adminUserStatus.className = 'form-text small text-success fw-semibold';
                    adminUserStatus.textContent = data.message || '{{ __("Username tersedia.") }}';
                } else if (data.success && !data.available) {
                    adminUsernameBlocked = true;
                    adminUserIndicator.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';
                    adminUserStatus.className = 'form-text small text-danger fw-semibold';
                    adminUserStatus.textContent = data.message || '{{ __("Username sudah terpakai di server VPN.") }}';
                } else {
                    adminUsernameBlocked = false;
                    adminUserIndicator.innerHTML = '<i class="fas fa-info-circle text-warning"></i>';
                    adminUserStatus.className = 'form-text small text-warning';
                    adminUserStatus.textContent = data.message || '{{ __("Tidak dapat memverifikasi ke server saat ini.") }}';
                }
                updateSubmitLock();
            } catch (e) {
                if (e.name !== 'AbortError') {
                    adminUsernameBlocked = false;
                    adminUserIndicator.innerHTML = '<i class="fas fa-info-circle text-muted"></i>';
                    adminUserStatus.className = 'form-text small text-muted';
                    adminUserStatus.textContent = '{{ __("Gagal mengecek otomatis; validasi tetap berjalan saat simpan.") }}';
                    updateSubmitLock();
                }
            }
        }, 450);
    }

    adminUserInput?.addEventListener('input', triggerAdminUsernameCheck);
    protoSelect?.addEventListener('change', function () { syncSshPasswordVisibility(); triggerAdminUsernameCheck(); });
    serverSelect?.addEventListener('change', triggerAdminUsernameCheck);
    syncSshPasswordVisibility();

    document.getElementById('adminCreateVpnForm')?.addEventListener('submit', function (e) {
        if (assignCustRadio && assignCustRadio.checked && (!custIdInput || !custIdInput.value)) {
            e.preventDefault();
            alert('{{ __("Silakan pilih customer tujuan terlebih dahulu.") }}');
        }
    });

    // =========================================================================
    // 6. SERVER TAB: DRIVER TOGGLE, PING TEST & 5-STAGE DIAGNOSTIC
    // =========================================================================
    document.querySelectorAll('.vpn-driver-select').forEach(function (select) {
        select.addEventListener('change', function () {
            const target = this.dataset.target;
            const isApi = this.value === 'vpnxray_api';
            const apiBox = document.querySelector('.vpn-api-fields-' + target);
            const sshBox = document.querySelector('.vpn-ssh-fields-' + target);
            if (apiBox) apiBox.classList.toggle('d-none', !isApi);
            if (sshBox) sshBox.classList.toggle('d-none', isApi);
        });
    });

    document.querySelectorAll('.btn-ping-server').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const url = this.dataset.url;
            const name = this.dataset.name || 'Server VPN';
            const orig = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                const msg = data.message || (data.ok ? '{{ __("Server VPN merespons normal.") }}' : '{{ __("Tidak ada respons detail dari server.") }}');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: data.ok ? 'success' : 'error',
                        title: data.ok ? (name + ' Online') : (name + ' Offline'),
                        html: '<div class="small text-break">' + escHtml(msg) + '</div>',
                        showCancelButton: false,
                        showDenyButton: false,
                        confirmButtonText: 'OK'
                    }).then(() => window.location.reload());
                } else {
                    alert(msg);
                    window.location.reload();
                }
            } catch (e) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        html: '<div class="small">{{ __("Gagal menguji koneksi ke server VPN.") }}</div>',
                        showCancelButton: false,
                        showDenyButton: false,
                        confirmButtonText: 'OK'
                    });
                }
            } finally {
                this.disabled = false;
                this.innerHTML = orig;
            }
        });
    });

    let lastDiagReportText = '';
    document.querySelectorAll('.btn-diagnose-server').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const url = this.dataset.url;
            const name = this.dataset.name || 'Server VPN';
            const modalEl = document.getElementById('vpnDiagnoseModal');
            const loadingEl = document.getElementById('vpnDiagLoading');
            const contentEl = document.getElementById('vpnDiagContent');
            const headlineEl = document.getElementById('vpnDiagHeadline');
            const stepsEl = document.getElementById('vpnDiagSteps');
            const recsWrap = document.getElementById('vpnDiagRecsWrap');
            const recsEl = document.getElementById('vpnDiagRecs');
            const copyBtn = document.getElementById('btnCopyVpnDiag');

            document.getElementById('vpnDiagTitle').textContent = '{{ __("Diagnostik:") }} ' + name;
            loadingEl.classList.remove('d-none');
            contentEl.classList.add('d-none');
            copyBtn.classList.add('d-none');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const report = await res.json();
                loadingEl.classList.add('d-none');
                contentEl.classList.remove('d-none');
                copyBtn.classList.remove('d-none');

                headlineEl.className = 'alert border-0 small mb-3 ' + (report.ok ? 'alert-success' : 'alert-danger');
                headlineEl.innerHTML = '<div class="fw-bold">' + (report.ok ? 'ONLINE' : 'OFFLINE') + ' — ' + escHtml(report.endpoint || '') + '</div><div>' + escHtml(report.headline || '') + '</div>';

                stepsEl.innerHTML = '';
                const plainLines = ['=== VPN DIAGNOSTIC: ' + name + ' (' + (report.endpoint || '') + ') ===', 'Result: ' + (report.ok ? 'ONLINE' : 'OFFLINE') + ' — ' + (report.headline || '')];
                (report.steps || []).forEach(function (st) {
                    const badgeCls = st.status === 'ok' ? 'bg-success-subtle text-success' : (st.status === 'warn' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger');
                    const iconCls = st.status === 'ok' ? 'fa-check-circle' : (st.status === 'warn' ? 'fa-triangle-exclamation' : 'fa-times-circle');
                    const msLabel = (st.latency_ms !== null && st.latency_ms !== undefined) ? (' <span class="text-muted">(' + st.latency_ms + ' ms)</span>') : '';
                    const card = document.createElement('div');
                    card.className = 'p-2.5 rounded-3 border';
                    card.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                            <div class="fw-bold small text-body">${escHtml(st.title)}${msLabel}</div>
                            <span class="badge ${badgeCls} rounded-pill px-2 py-0.5 text-uppercase" style="font-size:0.68rem;"><i class="fas ${iconCls} me-1"></i>${escHtml(st.status)}</span>
                        </div>
                        <div class="small text-secondary text-break">${escHtml(st.detail)}</div>
                        ${st.hint ? '<div class="small text-primary mt-1"><i class="fas fa-arrow-right me-1"></i>' + escHtml(st.hint) + '</div>' : ''}
                    `;
                    stepsEl.appendChild(card);
                    plainLines.push('[' + String(st.status || '').toUpperCase() + '] ' + st.title + ': ' + st.detail + (st.hint ? ' | Hint: ' + st.hint : ''));
                });

                const recs = report.recommendations || [];
                if (recs.length > 0) {
                    recsWrap.classList.remove('d-none');
                    recsEl.innerHTML = recs.map(r => '<li>' + escHtml(r) + '</li>').join('');
                    plainLines.push('Recommendations:', ...recs.map(r => '- ' + r));
                } else {
                    recsWrap.classList.add('d-none');
                }
                lastDiagReportText = plainLines.join('\n');
            } catch (e) {
                loadingEl.classList.add('d-none');
                contentEl.classList.remove('d-none');
                headlineEl.className = 'alert alert-danger border-0 small mb-0';
                headlineEl.textContent = '{{ __("Gagal menjalankan diagnostik ke server.") }}';
                stepsEl.innerHTML = '';
                recsWrap.classList.add('d-none');
            }
        });
    });

    document.getElementById('btnCopyVpnDiag')?.addEventListener('click', function () {
        navigator.clipboard?.writeText(lastDiagReportText);
        const orig = this.innerHTML;
        this.innerHTML = '<i class="fas fa-check text-success me-1"></i>{{ __("Tersalin!") }}';
        setTimeout(() => { this.innerHTML = orig; }, 1500);
    });
});
</script>
@endpush
@endsection
