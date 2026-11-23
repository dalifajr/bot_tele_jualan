@extends('layouts.app')

@section('title', __('Server VPN (Multi-Server)'))
@section('page_subtitle', __('Kelola Koneksi REST API vpnxray-main & Fallback SSH'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h4 class="fw-bold text-body mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-server text-primary"></i>
            <span>{{ __('Kelola Server VPN') }}</span>
        </h4>
        <p class="text-muted small mb-0">{{ __('Hubungkan beberapa VPS panel vpnxray-main melalui REST API v1 terenkripsi HMAC-SHA256.') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.vpn-accounts.index') }}" class="btn btn-outline-primary rounded-pill px-3 fw-semibold btn-sm d-inline-flex align-items-center gap-1.5">
            <i class="fas fa-network-wired"></i>
            <span>{{ __('Kelola Akun VPN') }}</span>
        </a>
        <button type="button" class="btn btn-primary rounded-pill px-3 fw-semibold btn-sm d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#addVpnServerModal">
            <i class="fas fa-plus"></i>
            <span>{{ __('Tambah Server') }}</span>
        </button>
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">{{ __('Total Server') }}</div>
                <div class="fs-4 fw-bold text-body">{{ $stats['total'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">{{ __('Server Aktif') }}</div>
                <div class="fs-4 fw-bold text-success">{{ $stats['active'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">{{ __('Mode REST API') }}</div>
                <div class="fs-4 fw-bold text-primary">{{ $stats['api_driver'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold">{{ __('Status Online') }}</div>
                <div class="fs-4 fw-bold text-info">{{ $stats['online'] }}</div>
            </div>
        </div>
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
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2.5 btn-ping-server" data-url="{{ route('admin.vpn-servers.ping', $server->id) }}" data-name="{{ $server->name }}">
                                    <i class="fas fa-heartbeat"></i> {{ __('Test') }}
                                </button>
                                @if(!$server->is_default)
                                <form action="{{ route('admin.vpn-servers.default', $server->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-2.5" title="{{ __('Jadikan Default') }}">
                                        <i class="fas fa-star"></i>
                                    </button>
                                </form>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" data-bs-toggle="modal" data-bs-target="#editVpnServerModal{{ $server->id }}">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form action="{{ route('admin.vpn-servers.destroy', $server->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Hapus server VPN ini? Akun lokal tetap tersimpan namun tidak lagi terhubung ke server ini.') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5">
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

@push('modals')
{{-- Add Server Modal --}}
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

                        {{-- REST API Fields --}}
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

                        {{-- Legacy SSH Fields --}}
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

{{-- Edit Server Modals --}}
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
@endpush

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
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
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: data.ok ? 'success' : 'error',
                        title: data.ok ? name + ' Online' : name + ' Offline',
                        text: data.message || '',
                        confirmButtonText: 'OK'
                    }).then(() => window.location.reload());
                } else {
                    alert(data.message || (data.ok ? 'Online' : 'Offline'));
                    window.location.reload();
                }
            } catch (e) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', '{{ __("Gagal menguji koneksi ke server VPN.") }}', 'error');
                }
            } finally {
                this.disabled = false;
                this.innerHTML = orig;
            }
        });
    });
});
</script>
@endpush
@endsection
