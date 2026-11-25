@extends('layouts.app')

@section('title', __('Kelola Akun VPN'))
@section('page_subtitle', __('Manajemen Create, Renew, Suspend, Sync & Delete Akun VPN'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="fw-bold text-body mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-network-wired text-primary"></i>
            <span>{{ __('Kelola Akun VPN') }}</span>
        </h4>
        <p class="text-muted small mb-0">{{ __('Kelola seluruh akun SSH, VMess, VLESS, Trojan & Shadowsocks terintegrasi REST API.') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.vpn-servers.index') }}" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold btn-sm d-inline-flex align-items-center gap-1.5">
            <i class="fas fa-server"></i>
            <span>{{ __('Server VPN') }}</span>
        </a>
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
</div>

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
                    <a href="{{ route('admin.vpn-accounts.index') }}" class="btn btn-light btn-sm rounded-pill border"><i class="fas fa-times"></i></a>
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
            <p class="text-muted small mb-0">{{ __('Akun VPN yang dibuat otomatis dari pesanan atau dibuat manual akan muncul di sini.') }}</p>
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
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2.5 btn-view-vpn-config"
                                        data-title="{{ strtoupper($acc->protocol) }} — {{ $acc->username }}"
                                        data-payload='@json($acc->config_payload)'
                                        data-text="{{ $acc->config_link }}"
                                        title="{{ __('Lihat Config & QR') }}">
                                    <i class="fas fa-qrcode"></i>
                                </button>
                                @if(in_array($acc->status, ['failed', 'provisioning']))
                                    <form action="{{ route('admin.vpn-accounts.retry', $acc->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning rounded-pill px-2.5 fw-semibold" title="{{ __('Retry Provisioning') }}">
                                            <i class="fas fa-redo"></i> {{ __('Retry') }}
                                        </button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 btn-open-renew"
                                            data-action="{{ route('admin.vpn-accounts.renew', $acc->id) }}"
                                            data-action-reduce="{{ route('admin.vpn-accounts.reduce-duration', $acc->id) }}"
                                            data-username="{{ $acc->username }}"
                                            data-quota="{{ $acc->quota_gb ?? 0 }}"
                                            data-ip="{{ max(1, (int) ($acc->ip_limit ?? 1)) }}"
                                            data-expired="{{ $acc->expired_at ? $acc->expired_at->format('Y-m-d H:i:s') : '' }}"
                                            title="{{ __('Perpanjang Durasi') }}">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 btn-open-reduce"
                                            data-action="{{ route('admin.vpn-accounts.renew', $acc->id) }}"
                                            data-action-reduce="{{ route('admin.vpn-accounts.reduce-duration', $acc->id) }}"
                                            data-username="{{ $acc->username }}"
                                            data-quota="{{ $acc->quota_gb ?? 0 }}"
                                            data-ip="{{ max(1, (int) ($acc->ip_limit ?? 1)) }}"
                                            data-expired="{{ $acc->expired_at ? $acc->expired_at->format('Y-m-d H:i:s') : '' }}"
                                            title="{{ __('Kurangi Masa Aktif') }}">
                                        <i class="fas fa-calendar-minus"></i>
                                    </button>
                                    @if($acc->status === 'suspended')
                                        <form action="{{ route('admin.vpn-accounts.unsuspend', $acc->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2.5" title="{{ __('Aktifkan Kembali (Buka Suspend)') }}">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-2.5 btn-open-suspend"
                                                data-action="{{ route('admin.vpn-accounts.suspend', $acc->id) }}"
                                                data-username="{{ $acc->username }}"
                                                title="{{ __('Suspend Akun (Kunci Admin)') }}">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    @endif
                                    <form action="{{ route('admin.vpn-accounts.sync', $acc->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" title="{{ __('Sync dari Panel') }}">
                                            <i class="fas fa-rotate"></i>
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('admin.vpn-accounts.destroy', $acc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Hapus permanen akun VPN ini dari server VPS & database?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="{{ __('Hapus Permanen') }}">
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
    <div class="d-lg-none m3-collapsible-list" id="adminVpnAccountsAccordion">
        @foreach($accounts as $acc)
        @php
            $dotColor = match(true) {
                $acc->status === 'failed' => 'bg-danger',
                $acc->status === 'suspended' => 'bg-warning',
                $acc->expired_at && $acc->expired_at->isPast() => 'bg-secondary',
                default => 'bg-success',
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
                <button type="button" class="m3-compact-action-btn btn-view-vpn-config"
                        data-title="{{ strtoupper($acc->protocol) }} — {{ $acc->username }}"
                        data-payload='@json($acc->config_payload)'
                        data-text="{{ $acc->config_link }}"
                        title="{{ __('Config & QR') }}">
                    <i class="fas fa-qrcode text-primary"></i>
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
                        @if(in_array($acc->status, ['failed', 'provisioning']))
                            <form action="{{ route('admin.vpn-accounts.retry', $acc->id) }}" method="POST" class="flex-grow-1">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-warning rounded-pill w-100 fw-semibold"><i class="fas fa-redo me-1"></i>{{ __('Retry Provisioning') }}</button>
                            </form>
                        @else
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1 btn-open-renew"
                                    data-action="{{ route('admin.vpn-accounts.renew', $acc->id) }}"
                                    data-action-reduce="{{ route('admin.vpn-accounts.reduce-duration', $acc->id) }}"
                                    data-username="{{ $acc->username }}"
                                    data-quota="{{ $acc->quota_gb ?? 0 }}"
                                    data-ip="{{ max(1, (int) ($acc->ip_limit ?? 1)) }}"
                                    data-expired="{{ $acc->expired_at ? $acc->expired_at->format('Y-m-d H:i:s') : '' }}">
                                <i class="fas fa-calendar-plus me-1"></i>{{ __('Renew') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill flex-grow-1 btn-open-reduce"
                                    data-action="{{ route('admin.vpn-accounts.renew', $acc->id) }}"
                                    data-action-reduce="{{ route('admin.vpn-accounts.reduce-duration', $acc->id) }}"
                                    data-username="{{ $acc->username }}"
                                    data-quota="{{ $acc->quota_gb ?? 0 }}"
                                    data-ip="{{ max(1, (int) ($acc->ip_limit ?? 1)) }}"
                                    data-expired="{{ $acc->expired_at ? $acc->expired_at->format('Y-m-d H:i:s') : '' }}">
                                <i class="fas fa-calendar-minus me-1"></i>{{ __('Kurangi') }}
                            </button>
                            @if($acc->status === 'suspended')
                                <form action="{{ route('admin.vpn-accounts.unsuspend', $acc->id) }}" method="POST" class="flex-grow-1">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success rounded-pill w-100"><i class="fas fa-play me-1"></i>{{ __('Unsuspend') }}</button>
                                </form>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill flex-grow-1 btn-open-suspend"
                                        data-action="{{ route('admin.vpn-accounts.suspend', $acc->id) }}"
                                        data-username="{{ $acc->username }}">
                                    <i class="fas fa-ban me-1"></i>{{ __('Suspend') }}
                                </button>
                            @endif
                        @endif
                        <form action="{{ route('admin.vpn-accounts.destroy', $acc->id) }}" method="POST" onsubmit="return confirm('{{ __('Hapus permanen akun ini?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="fas fa-trash"></i></button>
                        </form>
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

@push('modals')
{{-- Create Manual Modal --}}
<div class="modal fade" id="adminCreateVpnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle text-primary me-2"></i>{{ __('Buat Akun VPN Manual') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.vpn-accounts.store') }}" method="POST">
                @csrf
                <div class="modal-body pt-2">
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Server VPN') }}</label>
                        <select name="vpn_server_id" class="form-select">
                            <option value="">{{ __('Gunakan Server Default') }}</option>
                            @foreach($servers as $srv)
                                <option value="{{ $srv->id }}">{{ $srv->name }} ({{ $srv->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Pemilik Akun (User)') }} <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-select" required>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ $u->id === Auth::id() ? 'selected' : '' }}>{{ $u->full_name ?: $u->username }} ({{ $u->role }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-2.5">
                        <div class="col-6">
                            <label class="form-label small fw-bold">{{ __('Protokol') }} <span class="text-danger">*</span></label>
                            <select name="protocol" id="adminCreateProto" class="form-select" required>
                                <option value="vmess">VMESS</option>
                                <option value="vless">VLESS</option>
                                <option value="trojan">TROJAN</option>
                                <option value="shadowsocks">SHADOWSOCKS</option>
                                <option value="ssh">SSH</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">{{ __('Masa Aktif (Hari)') }} <span class="text-danger">*</span></label>
                            <input type="number" name="days" class="form-control" value="30" min="1" max="365" required>
                        </div>
                    </div>
                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Username VPN') }} <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control font-monospace" required pattern="[a-zA-Z0-9_-]{3,24}" placeholder="user_sg01">
                    </div>
                    <div class="mb-2.5 d-none" id="adminCreateSshPassWrap">
                        <label class="form-label small fw-bold">{{ __('Password SSH') }} <span class="text-danger">*</span></label>
                        <input type="text" name="password" class="form-control font-monospace" placeholder="min 4 karakter">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">{{ __('Kuota (GB, 0=Unli)') }}</label>
                            <input type="number" name="quota_gb" class="form-control" value="0" min="0" max="10000">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">{{ __('Limit IP') }}</label>
                            <input type="number" name="ip_limit" class="form-control" value="2" min="1" max="50">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Buat Akun') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Renew & Reduce Modal --}}
<div class="modal fade" id="adminRenewVpnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold" id="renewModalTitle">
                    <i class="fas fa-calendar-plus text-success me-2" id="renewModalIcon"></i><span id="renewModalTitleText">{{ __('Perpanjang Akun VPN') }}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="adminRenewForm" method="POST">
                @csrf
                <input type="hidden" name="action_type" id="renewActionType" value="add">
                <div class="modal-body pt-2">
                    {{-- Segmented Mode Toggle --}}
                    <div class="d-flex p-1 bg-light rounded-pill mb-3 border">
                        <button type="button" class="btn btn-sm rounded-pill flex-grow-1 fw-semibold py-1.5 transition-all" id="renewTabAddBtn">
                            <i class="fas fa-plus-circle me-1 text-success"></i>{{ __('Tambah Durasi') }}
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill flex-grow-1 fw-semibold py-1.5 text-muted transition-all" id="renewTabReduceBtn">
                            <i class="fas fa-minus-circle me-1 text-danger"></i>{{ __('Kurangi Masa Aktif') }}
                        </button>
                    </div>

                    <div class="mb-2.5">
                        <label class="form-label small fw-bold">{{ __('Username') }}</label>
                        <input type="text" id="renewModalUsername" class="form-control font-monospace" readonly>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold" id="renewDaysLabel">{{ __('Tambah Durasi (Hari)') }} <span class="text-danger">*</span></label>
                        <input type="number" name="days" id="renewModalDays" class="form-control" value="1" min="1" max="365" required>
                    </div>

                    {{-- Presets: Tambah Durasi --}}
                    <div id="renewAddPresets" class="d-flex flex-wrap gap-1.5 mb-2.5">
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold preset-day-btn" data-days="1">+1 Hari</button>
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold preset-day-btn" data-days="7">+7 Hari</button>
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold preset-day-btn" data-days="30">+30 Hari</button>
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold preset-day-btn" data-days="60">+60 Hari</button>
                    </div>

                    {{-- Presets: Kurangi Durasi --}}
                    <div id="renewReducePresets" class="d-flex flex-wrap gap-1.5 mb-2.5 d-none">
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold preset-day-btn" data-days="1">-1 Hari</button>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold preset-day-btn" data-days="3">-3 Hari</button>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold preset-day-btn" data-days="7">-7 Hari</button>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold preset-day-btn" data-days="14">-14 Hari</button>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold preset-day-btn" data-days="30">-30 Hari</button>
                    </div>

                    {{-- Estimasi Expired Baru --}}
                    <div class="p-2.5 rounded-3 bg-light border d-flex justify-content-between align-items-center mb-3">
                        <span class="small text-muted" id="renewExpiryLabel">{{ __('Estimasi Expired Baru:') }}</span>
                        <span class="small fw-bold font-monospace text-body" id="renewEstimatedExpiry">--</span>
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
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold" id="renewSubmitBtn">{{ __('Perpanjang Sekarang') }}</button>
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
                    <p class="small text-muted mb-3">{{ __('Tarik seluruh daftar akun dari panel vpnxray-main untuk memperbarui status, masa aktif, kuota, dan mengimpor akun yang belum tercatat di website jualan.') }}</p>
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

{{-- Config & QR Modal --}}
<div class="modal fade" id="vpnConfigViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold" id="vpnConfigViewerTitle">{{ __('Konfigurasi VPN') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="vpnQrContainer" class="text-center mb-3 d-none">
                    <canvas id="vpnConfigQrCanvas" width="210" height="210" class="rounded-4 border p-2 bg-white"></canvas>
                </div>
                <div id="vpnLinksContainer" class="d-flex flex-column gap-2 mb-3"></div>
                <label class="form-label small fw-bold text-muted">{{ __('Teks Konfigurasi Lengkap') }}</label>
                <textarea id="vpnConfigFullText" class="form-control font-monospace small" rows="6" readonly></textarea>
            </div>
            <div class="modal-footer border-0 pt-1">
                <button type="button" class="btn btn-outline-primary rounded-pill px-3" id="btnCopyFullVpnConfig"><i class="fas fa-copy me-1"></i>{{ __('Salin Semua') }}</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const protoSelect = document.getElementById('adminCreateProto');
    const passWrap = document.getElementById('adminCreateSshPassWrap');
    if (protoSelect && passWrap) {
        protoSelect.addEventListener('change', function () {
            passWrap.classList.toggle('d-none', this.value !== 'ssh');
        });
    }

    let currentAccountExpired = '';
    let currentActionUrl = '';
    let currentActionReduceUrl = '';

    const renewForm = document.getElementById('adminRenewForm');
    const renewActionType = document.getElementById('renewActionType');
    const renewModalTitle = document.getElementById('renewModalTitleText');
    const renewModalIcon = document.getElementById('renewModalIcon');
    const renewDaysLabel = document.getElementById('renewDaysLabel');
    const renewDaysInput = document.getElementById('renewModalDays');
    const renewAddPresets = document.getElementById('renewAddPresets');
    const renewReducePresets = document.getElementById('renewReducePresets');
    const renewTabAddBtn = document.getElementById('renewTabAddBtn');
    const renewTabReduceBtn = document.getElementById('renewTabReduceBtn');
    const renewSubmitBtn = document.getElementById('renewSubmitBtn');
    const renewEstimatedExpiry = document.getElementById('renewEstimatedExpiry');

    function formatIndoDate(date) {
        if (!date || isNaN(date.getTime())) return '-';
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const day = String(date.getDate()).padStart(2, '0');
        const month = months[date.getMonth()];
        const year = date.getFullYear();
        return `${day} ${month} ${year}`;
    }

    function calculateEstimatedExpiry() {
        if (!renewEstimatedExpiry) return;
        const days = parseInt(renewDaysInput?.value, 10) || 0;
        const isReduce = renewActionType?.value === 'reduce';

        let base = currentAccountExpired ? new Date(currentAccountExpired.replace(/-/g, '/')) : new Date();
        if (isNaN(base.getTime())) base = new Date();
        const now = new Date();

        if (isReduce) {
            let target = new Date(base.getTime() - (days * 86400000));
            if (target <= now) {
                renewEstimatedExpiry.innerHTML = `<span class="text-danger fw-bold">${formatIndoDate(now)} (Kadaluarsa Hari Ini)</span>`;
            } else {
                renewEstimatedExpiry.textContent = formatIndoDate(target);
            }
        } else {
            if (base < now) base = now;
            let target = new Date(base.getTime() + (days * 86400000));
            renewEstimatedExpiry.textContent = formatIndoDate(target);
        }
    }

    function setRenewMode(mode) {
        if (!renewForm) return;
        if (mode === 'reduce') {
            renewActionType.value = 'reduce';
            renewForm.action = currentActionReduceUrl || currentActionUrl;
            renewModalTitle.textContent = '{{ __("Kurangi Masa Aktif Akun VPN") }}';
            renewModalIcon.className = 'fas fa-calendar-minus text-danger me-2';
            renewDaysLabel.innerHTML = '{{ __("Kurangi Durasi (Hari)") }} <span class="text-danger">*</span>';
            renewAddPresets?.classList.add('d-none');
            renewReducePresets?.classList.remove('d-none');
            renewTabAddBtn?.classList.remove('bg-white', 'shadow-sm');
            renewTabAddBtn?.classList.add('text-muted');
            renewTabReduceBtn?.classList.add('bg-white', 'shadow-sm');
            renewTabReduceBtn?.classList.remove('text-muted');
            renewSubmitBtn.className = 'btn btn-danger rounded-pill px-4 fw-semibold';
            renewSubmitBtn.textContent = '{{ __("Kurangi Masa Aktif") }}';
        } else {
            renewActionType.value = 'add';
            renewForm.action = currentActionUrl;
            renewModalTitle.textContent = '{{ __("Perpanjang Akun VPN") }}';
            renewModalIcon.className = 'fas fa-calendar-plus text-success me-2';
            renewDaysLabel.innerHTML = '{{ __("Tambah Durasi (Hari)") }} <span class="text-danger">*</span>';
            renewAddPresets?.classList.remove('d-none');
            renewReducePresets?.classList.add('d-none');
            renewTabAddBtn?.classList.add('bg-white', 'shadow-sm');
            renewTabAddBtn?.classList.remove('text-muted');
            renewTabReduceBtn?.classList.remove('bg-white', 'shadow-sm');
            renewTabReduceBtn?.classList.add('text-muted');
            renewSubmitBtn.className = 'btn btn-success rounded-pill px-4 fw-semibold';
            renewSubmitBtn.textContent = '{{ __("Perpanjang Sekarang") }}';
        }
        calculateEstimatedExpiry();
    }

    if (renewTabAddBtn && renewTabReduceBtn) {
        renewTabAddBtn.addEventListener('click', () => setRenewMode('add'));
        renewTabReduceBtn.addEventListener('click', () => setRenewMode('reduce'));
    }

    if (renewDaysInput) {
        renewDaysInput.addEventListener('input', calculateEstimatedExpiry);
    }

    document.querySelectorAll('.preset-day-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (renewDaysInput) {
                renewDaysInput.value = this.dataset.days || 1;
                calculateEstimatedExpiry();
            }
        });
    });

    document.querySelectorAll('.btn-open-renew').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentActionUrl = this.dataset.action;
            currentActionReduceUrl = this.dataset.actionReduce || this.dataset.action;
            currentAccountExpired = this.dataset.expired || '';
            document.getElementById('renewModalUsername').value = this.dataset.username || '';
            document.getElementById('renewModalQuota').value = this.dataset.quota || 0;
            document.getElementById('renewModalIp').value = this.dataset.ip || 1;
            if (renewDaysInput) renewDaysInput.value = 1;
            setRenewMode('add');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('adminRenewVpnModal')).show();
        });
    });

    document.querySelectorAll('.btn-open-reduce').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentActionUrl = this.dataset.action;
            currentActionReduceUrl = this.dataset.actionReduce || this.dataset.action;
            currentAccountExpired = this.dataset.expired || '';
            document.getElementById('renewModalUsername').value = this.dataset.username || '';
            document.getElementById('renewModalQuota').value = this.dataset.quota || 0;
            document.getElementById('renewModalIp').value = this.dataset.ip || 1;
            if (renewDaysInput) renewDaysInput.value = 1;
            setRenewMode('reduce');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('adminRenewVpnModal')).show();
        });
    });

    document.querySelectorAll('.btn-open-suspend').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('adminSuspendForm').action = this.dataset.action;
            document.getElementById('suspendModalUsername').value = this.dataset.username || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('adminSuspendVpnModal')).show();
        });
    });

    document.querySelectorAll('.btn-view-vpn-config').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const title = this.dataset.title || 'VPN Config';
            const fullText = this.dataset.text || '';
            let payload = null;
            try { payload = JSON.parse(this.dataset.payload || 'null'); } catch (e) {}

            document.getElementById('vpnConfigViewerTitle').textContent = title;
            document.getElementById('vpnConfigFullText').value = fullText;

            const linksWrap = document.getElementById('vpnLinksContainer');
            const qrWrap = document.getElementById('vpnQrContainer');
            const qrCanvas = document.getElementById('vpnConfigQrCanvas');
            linksWrap.innerHTML = '';

            let primaryLink = '';
            if (payload && payload.links && typeof payload.links === 'object') {
                const entries = Object.entries(payload.links).filter(([, v]) => !!v);
                entries.forEach(([key, val]) => {
                    if (!primaryLink) primaryLink = val;
                    const row = document.createElement('div');
                    row.className = 'd-flex align-items-center gap-2 p-2 rounded-3 border';
                    row.innerHTML = `
                        <span class="badge bg-primary-subtle text-primary text-uppercase">${key}</span>
                        <input type="text" class="form-control form-control-sm font-monospace border-0 bg-transparent p-0" value="${val.replace(/"/g, '&quot;')}" readonly>
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

            bootstrap.Modal.getOrCreateInstance(document.getElementById('vpnConfigViewerModal')).show();
        });
    });

    const copyAllBtn = document.getElementById('btnCopyFullVpnConfig');
    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function () {
            const txt = document.getElementById('vpnConfigFullText').value;
            navigator.clipboard?.writeText(txt);
            const orig = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check text-success me-1"></i>{{ __("Tersalin!") }}';
            setTimeout(() => { this.innerHTML = orig; }, 1600);
        });
    }
});
</script>
@endpush
@endsection
