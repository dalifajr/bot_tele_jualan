@extends('layouts.app')

@section('title', __('Akun VPN Saya'))
@section('page_subtitle', __('Kelola, Perpanjang, Config & QR Code Akun VPN Anda'))

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
            <div class="col-12 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="{{ __('Cari username VPN Anda...') }}">
            </div>
            <div class="col-6 col-md-3">
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
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1.5">
                <button type="submit" class="btn btn-primary btn-sm rounded-pill flex-grow-1 fw-semibold">{{ __('Cari') }}</button>
                @if(request()->hasAny(['search', 'protocol', 'status']))
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
                                <div class="d-inline-flex align-items-center justify-content-end gap-1.5">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 fw-semibold btn-renew-my-vpn"
                                            data-action="{{ route('vpn.renew', $acc->id) }}"
                                            data-username="{{ $acc->username }}"
                                            data-expired-iso="{{ $acc->expired_at ? $acc->expired_at->toIso8601String() : '' }}"
                                            title="{{ __('Perpanjang') }}">
                                        <i class="fas fa-calendar-plus me-1"></i>{{ __('Perpanjang') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 btn-open-my-vpn-sheet"
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
                                            data-delete-url="{{ route('vpn.destroy', $acc->id) }}"
                                            aria-label="{{ __('Menu Aksi') }}"
                                            title="{{ __('Menu Aksi Lainnya') }}">
                                        <i class="fas fa-bars"></i>
                                    </button>
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
            $dotColor = match(true) {
                $adminSuspended || $acc->status === 'failed' => 'bg-danger',
                $acc->status === 'suspended' => 'bg-warning',
                $isExpired || $acc->status === 'expired' => 'bg-secondary',
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
        <div class="m3-collapsible-card">
            <div class="m3-compact-row">
                <button type="button" class="m3-compact-trigger collapsed" data-bs-toggle="collapse" data-bs-target="#myVpnCollapse{{ $acc->id }}" aria-expanded="false">
                    <span class="m3-status-dot {{ $dotColor }}"></span>
                    <div class="min-w-0 flex-grow-1 text-start">
                        <div class="fw-bold text-body font-monospace text-truncate" style="font-size: 0.9rem;">
                            {{ $acc->username }}
                            <span class="badge bg-primary-subtle text-primary ms-1" style="font-size: 0.62rem;">{{ strtoupper($acc->protocol) }}</span>
                        </div>
                        <div class="text-muted text-truncate" style="font-size: 0.74rem;">
                            @if($adminSuspended)
                                <span class="text-danger fw-semibold"><i class="fas fa-lock me-1"></i>{{ __('Disuspend Admin') }}</span>
                            @else
                                {{ $acc->display_domain }} &bull; Exp: {{ $acc->expired_at ? $acc->expired_at->format('d M Y') : '-' }}
                            @endif
                        </div>
                    </div>
                    <i class="fas fa-chevron-down m3-compact-chevron"></i>
                </button>
                @if($adminSuspended && $chatTargetId)
                    <a href="{{ route('chat.index', ['contact_id' => $chatTargetId]) }}" class="m3-compact-action-btn" title="{{ __('Chat Admin') }}">
                        <i class="fas fa-comments text-danger"></i>
                    </a>
                @elseif(!$adminSuspended)
                    <button type="button" class="m3-compact-action-btn btn-open-my-vpn-sheet"
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
                            data-delete-url="{{ route('vpn.destroy', $acc->id) }}"
                            title="{{ __('Menu Aksi') }}">
                        <i class="fas fa-bars text-primary"></i>
                    </button>
                @endif
            </div>
            <div id="myVpnCollapse{{ $acc->id }}" class="collapse" data-bs-parent="#customerVpnAccordion">
                <div class="m3-collapsible-body">
                    <div class="small mb-2.5">
                        <div><span class="text-muted">{{ __('Server:') }}</span> <strong>{{ $acc->vpnServer?->name ?: __('Server VPN') }}</strong> ({{ $acc->display_domain }})</div>
                        <div><span class="text-muted">{{ __('Limit:') }}</span> {{ ($acc->quota_gb ?? 0) > 0 ? $acc->quota_gb . ' GB' : 'Unlimited' }} &bull; {{ max(1, (int) ($acc->ip_limit ?? 1)) }} IP</div>
                        <div><span class="text-muted">{{ __('Berakhir:') }}</span> {{ $acc->expired_at ? $acc->expired_at->format('d M Y H:i') : '-' }}</div>
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
                        <div class="d-flex flex-wrap gap-1.5">
                            <button type="button" class="btn btn-sm btn-primary rounded-pill flex-grow-1 fw-semibold btn-renew-my-vpn"
                                    data-action="{{ route('vpn.renew', $acc->id) }}"
                                    data-username="{{ $acc->username }}"
                                    data-expired-iso="{{ $acc->expired_at ? $acc->expired_at->toIso8601String() : '' }}">
                                <i class="fas fa-calendar-plus me-1"></i>{{ __('Perpanjang') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold btn-open-my-vpn-sheet"
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
                                <i class="fas fa-bars me-1"></i>{{ __('Aksi') }}
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
            <div class="modal-body pt-1 pb-3">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-light border text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3" id="mySheetBtnConfig">
                        <span class="rounded-circle bg-info-subtle text-info d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                            <i class="fas fa-qrcode"></i>
                        </span>
                        <div>
                            <div class="fw-bold small text-body">{{ __('Lihat Config & QR Code') }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">{{ __('Salin link konfigurasi atau pindai QR Code') }}</div>
                        </div>
                    </button>

                    <form id="mySheetFormSuspend" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-light border text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3 w-100">
                            <span class="rounded-circle bg-warning-subtle text-warning-emphasis d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="fas fa-pause"></i>
                            </span>
                            <div>
                                <div class="fw-bold small text-body">{{ __('Tangguhkan Sementara') }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ __('Nonaktifkan sementara, bisa diaktifkan lagi kapan saja') }}</div>
                            </div>
                        </button>
                    </form>

                    <form id="mySheetFormUnsuspend" method="POST" class="d-none m-0">
                        @csrf
                        <button type="submit" class="btn btn-light border text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3 w-100">
                            <span class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="fas fa-play"></i>
                            </span>
                            <div>
                                <div class="fw-bold small text-body">{{ __('Aktifkan Kembali') }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ __('Pulihkan akses koneksi akun VPN Anda') }}</div>
                            </div>
                        </button>
                    </form>

                    <form id="mySheetFormSync" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-light border text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3 w-100">
                            <span class="rounded-circle bg-secondary-subtle text-secondary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="fas fa-rotate"></i>
                            </span>
                            <div>
                                <div class="fw-bold small text-body">{{ __('Sync dari Server') }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ __('Perbarui detail konfigurasi terbaru dari server VPN') }}</div>
                            </div>
                        </button>
                    </form>

                    <form id="mySheetFormDelete" method="POST" class="m-0" onsubmit="return confirm('{{ __('Hapus permanen akun VPN ini? Tindakan ini tidak dapat dibatalkan.') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger text-start rounded-4 px-3 py-2.5 d-flex align-items-center gap-3 w-100">
                            <span class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                                <i class="fas fa-trash"></i>
                            </span>
                            <div>
                                <div class="fw-bold small">{{ __('Hapus Akun Permanen') }}</div>
                                <div class="small opacity-75" style="font-size: 0.72rem;">{{ __('Menghapus akun dari server dan daftar Anda') }}</div>
                            </div>
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
                                <option value="{{ $vp->id }}"
                                        data-protocol="{{ $vp->vpn_protocol }}"
                                        data-check-url="{{ route('catalog.check-vpn-username', $vp->id) }}">
                                    {{ $vp->name }} — {{ strtoupper($vp->vpn_protocol) }} ({{ $vp->vpn_duration_days }} {{ __('hari') }}) — {{ $vp->formatted_price }}
                                </option>
                            @endforeach
                        </select>
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
                    <div class="mb-0 d-none" id="custSshPassWrap">
                        <label class="form-label small fw-bold">{{ __('Password SSH') }} <span class="text-danger">*</span></label>
                        <input type="text" name="password" id="custSshPassword" class="form-control font-monospace" placeholder="{{ __('Minimal 4 karakter') }}">
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
                        <div class="p-2.5 rounded-3 border small d-flex align-items-center justify-content-between mb-2" style="background: var(--surface-container-low);">
                            <span class="text-muted">{{ __('Estimasi Expired Baru:') }}</span>
                            <strong class="text-primary" id="custRenewExpiryPreview">-</strong>
                        </div>
                        <div class="form-text small">{{ __('Biaya perpanjangan dihitung proporsional dari harga paket dan memotong saldo dompet Anda.') }}</div>
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

    // 1. Create Modal: SSH password toggle + Automatic Debounced Live Username Check
    const prodSelect = document.getElementById('custCreateProduct');
    const sshWrap = document.getElementById('custSshPassWrap');
    const sshInput = document.getElementById('custSshPassword');
    const custUserInput = document.getElementById('custCreateUsername');
    const custUserStatus = document.getElementById('custUsernameStatus');
    const custUserIndicator = document.getElementById('custUsernameIndicator');
    const custCreateSubmitBtn = document.getElementById('btnSubmitCustCreateVpn');

    const toggleSsh = () => {
        if (!prodSelect || !sshWrap) return;
        const opt = prodSelect.options[prodSelect.selectedIndex];
        const isSsh = !!(opt && opt.dataset.protocol === 'ssh');
        sshWrap.classList.toggle('d-none', !isSsh);
        if (sshInput) sshInput.required = isSsh;
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
        prodSelect.addEventListener('change', function () { toggleSsh(); triggerCustUsernameCheck(); });
        toggleSsh();
    }
    custUserInput?.addEventListener('input', triggerCustUsernameCheck);

    // 2. Renew Modal with Quick Presets (+1, +7, +30, +60) & Live Expiry Preview
    let custRenewBaseExpiry = null;
    const custRenewDays = document.getElementById('custRenewDaysInput');
    const custRenewPreview = document.getElementById('custRenewExpiryPreview');

    function updateCustRenewPreview() {
        if (!custRenewDays || !custRenewPreview) return;
        const days = Math.max(1, parseInt(custRenewDays.value || '0', 10) || 0);
        const now = new Date();
        const base = (custRenewBaseExpiry && custRenewBaseExpiry > now) ? new Date(custRenewBaseExpiry.getTime()) : now;
        base.setDate(base.getDate() + days);
        custRenewPreview.textContent = base.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        document.querySelectorAll('.btn-cust-renew-preset').forEach(function (b) {
            b.classList.toggle('active', parseInt(b.dataset.days, 10) === days);
        });
    }

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
            if (custRenewDays) custRenewDays.value = 30;
            updateCustRenewPreview();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('customerRenewVpnModal')).show();
        });
    });

    // 3. Config & QR Viewer with Lazy Auto-Fetch
    let currentMyConfigUrl = '';
    const myConfigModalEl = document.getElementById('myVpnConfigModal');
    const myConfigLoadingEl = document.getElementById('myVpnConfigLoading');
    const myConfigContentEl = document.getElementById('myVpnConfigContent');

    function renderMyVpnConfig(title, payload, fullText) {
        document.getElementById('myVpnConfigTitle').textContent = title || 'VPN Config';
        document.getElementById('myVpnFullText').value = fullText || '';

        const linksWrap = document.getElementById('myVpnLinksWrap');
        const qrWrap = document.getElementById('myVpnQrWrap');
        const qrCanvas = document.getElementById('myVpnQrCanvas');
        linksWrap.innerHTML = '';

        let primaryLink = '';
        if (payload && payload.links && typeof payload.links === 'object') {
            Object.entries(payload.links).filter(([, v]) => !!v).forEach(([key, val]) => {
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
