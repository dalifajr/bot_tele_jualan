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
        <p class="text-muted small mb-0">{{ __('Buat, lihat konfigurasi/QR, perpanjang, tangguhkan sementara, atau hapus akun VPN milik Anda.') }}</p>
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
            <p class="text-muted small mb-3">{{ __('Buat akun VPN instan menggunakan saldo dompet atau beli paket VPN di katalog produk.') }}</p>
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
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-2.5 btn-view-my-vpn"
                                            data-title="{{ strtoupper($acc->protocol) }} — {{ $acc->username }}"
                                            data-payload='@json($acc->config_payload)'
                                            data-text="{{ $acc->config_link }}"
                                            title="{{ __('Config & QR Code') }}">
                                        <i class="fas fa-qrcode"></i> {{ __('Config') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 btn-renew-my-vpn"
                                            data-action="{{ route('vpn.renew', $acc->id) }}"
                                            data-username="{{ $acc->username }}"
                                            title="{{ __('Perpanjang') }}">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                    @if($acc->status === 'suspended')
                                        <form action="{{ route('vpn.unsuspend', $acc->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2.5" title="{{ __('Aktifkan Kembali') }}">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('vpn.suspend', $acc->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-2.5" title="{{ __('Tangguhkan Sementara') }}">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <form action="{{ route('vpn.destroy', $acc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Hapus permanen akun VPN ini? Tindakan ini tidak dapat dibatalkan.') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="{{ __('Hapus Akun') }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
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
            $dotColor = match(true) {
                $adminSuspended || $acc->status === 'failed' => 'bg-danger',
                $acc->status === 'suspended' => 'bg-warning',
                $acc->expired_at && $acc->expired_at->isPast() => 'bg-secondary',
                default => 'bg-success',
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
                @else
                    <button type="button" class="m3-compact-action-btn btn-view-my-vpn"
                            data-title="{{ strtoupper($acc->protocol) }} — {{ $acc->username }}"
                            data-payload='@json($acc->config_payload)'
                            data-text="{{ $acc->config_link }}"
                            title="{{ __('Config & QR') }}">
                        <i class="fas fa-qrcode text-primary"></i>
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
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1 btn-renew-my-vpn"
                                    data-action="{{ route('vpn.renew', $acc->id) }}"
                                    data-username="{{ $acc->username }}">
                                <i class="fas fa-calendar-plus me-1"></i>{{ __('Perpanjang') }}
                            </button>
                            @if($acc->status === 'suspended')
                                <form action="{{ route('vpn.unsuspend', $acc->id) }}" method="POST" class="flex-grow-1">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success rounded-pill w-100"><i class="fas fa-play me-1"></i>{{ __('Aktifkan') }}</button>
                                </form>
                            @else
                                <form action="{{ route('vpn.suspend', $acc->id) }}" method="POST" class="flex-grow-1">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill w-100"><i class="fas fa-pause me-1"></i>{{ __('Suspend') }}</button>
                                </form>
                            @endif
                            <form action="{{ route('vpn.destroy', $acc->id) }}" method="POST" onsubmit="return confirm('{{ __('Hapus permanen akun VPN ini?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="fas fa-trash"></i></button>
                            </form>
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
{{-- Customer Create VPN Modal --}}
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
                            <input type="text" name="username" id="custCreateUsername" class="form-control font-monospace" required pattern="[a-zA-Z0-9_-]{3,24}" placeholder="nama_anda01">
                            <button type="button" class="btn btn-outline-primary" id="btnCheckMyVpnUser">{{ __('Cek') }}</button>
                        </div>
                        <small id="custUsernameStatus" class="form-text"></small>
                    </div>
                    <div class="mb-0 d-none" id="custSshPassWrap">
                        <label class="form-label small fw-bold">{{ __('Password SSH') }} <span class="text-danger">*</span></label>
                        <input type="text" name="password" class="form-control font-monospace" placeholder="{{ __('Minimal 4 karakter') }}">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-1">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Buat & Aktifkan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Customer Renew Modal --}}
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
                        <label class="form-label small fw-bold">{{ __('Pilih Durasi Tambahan') }}</label>
                        <select name="days" class="form-select" required>
                            <option value="7">7 {{ __('Hari') }}</option>
                            <option value="14">14 {{ __('Hari') }}</option>
                            <option value="30" selected>30 {{ __('Hari') }}</option>
                            <option value="60">60 {{ __('Hari') }}</option>
                            <option value="90">90 {{ __('Hari') }}</option>
                        </select>
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

{{-- Customer Config & QR Modal --}}
<div class="modal fade" id="myVpnConfigModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold" id="myVpnConfigTitle">{{ __('Konfigurasi VPN') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="myVpnQrWrap" class="text-center mb-3 d-none">
                    <canvas id="myVpnQrCanvas" width="210" height="210" class="rounded-4 border p-2 bg-white"></canvas>
                </div>
                <div id="myVpnLinksWrap" class="d-flex flex-column gap-2 mb-3"></div>
                <label class="form-label small fw-bold text-muted">{{ __('Detail Konfigurasi') }}</label>
                <textarea id="myVpnFullText" class="form-control font-monospace small" rows="6" readonly></textarea>
            </div>
            <div class="modal-footer border-0 pt-1">
                <button type="button" class="btn btn-outline-primary rounded-pill px-3" id="btnCopyMyVpnFull"><i class="fas fa-copy me-1"></i>{{ __('Salin Semua') }}</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    const prodSelect = document.getElementById('custCreateProduct');
    const sshWrap = document.getElementById('custSshPassWrap');
    const toggleSsh = () => {
        if (!prodSelect || !sshWrap) return;
        const opt = prodSelect.options[prodSelect.selectedIndex];
        sshWrap.classList.toggle('d-none', !opt || opt.dataset.protocol !== 'ssh');
    };
    if (prodSelect) {
        prodSelect.addEventListener('change', toggleSsh);
        toggleSsh();
    }

    const btnCheck = document.getElementById('btnCheckMyVpnUser');
    if (btnCheck && prodSelect) {
        btnCheck.addEventListener('click', async function () {
            const username = (document.getElementById('custCreateUsername')?.value || '').trim();
            const statusEl = document.getElementById('custUsernameStatus');
            const opt = prodSelect.options[prodSelect.selectedIndex];
            if (!username || username.length < 3) {
                statusEl.className = 'form-text text-warning';
                statusEl.textContent = '{{ __("Username minimal 3 karakter.") }}';
                return;
            }
            statusEl.className = 'form-text text-muted';
            statusEl.textContent = '{{ __("Memeriksa ke server VPN...") }}';
            try {
                const res = await fetch(opt.dataset.checkUrl + '?username=' + encodeURIComponent(username), {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                statusEl.className = 'form-text ' + (data.available ? 'text-success fw-semibold' : 'text-danger fw-semibold');
                statusEl.textContent = data.message || (data.available ? '{{ __("Username tersedia.") }}' : '{{ __("Username sudah digunakan.") }}');
            } catch (e) {
                statusEl.className = 'form-text text-danger';
                statusEl.textContent = '{{ __("Gagal memeriksa username.") }}';
            }
        });
    }

    document.querySelectorAll('.btn-renew-my-vpn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('customerRenewForm').action = this.dataset.action;
            document.getElementById('custRenewUsername').value = this.dataset.username || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('customerRenewVpnModal')).show();
        });
    });

    document.querySelectorAll('.btn-view-my-vpn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const title = this.dataset.title || 'VPN Config';
            const fullText = this.dataset.text || '';
            let payload = null;
            try { payload = JSON.parse(this.dataset.payload || 'null'); } catch (e) {}

            document.getElementById('myVpnConfigTitle').textContent = title;
            document.getElementById('myVpnFullText').value = fullText;

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

            bootstrap.Modal.getOrCreateInstance(document.getElementById('myVpnConfigModal')).show();
        });
    });

    const copyBtn = document.getElementById('btnCopyMyVpnFull');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            navigator.clipboard?.writeText(document.getElementById('myVpnFullText').value);
            const orig = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check text-success me-1"></i>{{ __("Tersalin!") }}';
            setTimeout(() => { this.innerHTML = orig; }, 1500);
        });
    }
});
</script>
@endpush
@endsection
