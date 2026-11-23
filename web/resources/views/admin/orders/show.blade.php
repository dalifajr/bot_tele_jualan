@extends('layouts.app')

@section('title', __('Detail Pesanan Admin ') . $order->reference)
@section('page_subtitle', __('Pesanan'))

@section('content')
{{-- Header --}}
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-3">
    <div class="d-flex align-items-center gap-2 min-w-0">
        <a href="{{ route('admin.orders.index') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Daftar Pesanan') }}">
            <i class="fas fa-arrow-left text-body"></i>
        </a>
        <div class="min-w-0">
            <h5 class="fw-bold m-0 text-body text-truncate" style="font-size: 1.15rem;">{{ __('Detail Pesanan (Admin)') }}</h5>
            <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;">{{ $order->reference }} &bull; {{ $order->created_at->format('d M Y, H:i') }}</small>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 ms-auto ms-sm-0 flex-shrink-0">
        <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-1.5 fw-bold" style="font-size: 0.8rem;">
            {{ $order->status_label }}
        </span>
        <button class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#editOrderStatusModal" style="font-size: 0.8rem;">
            <i class="fas fa-edit"></i>
            <span>{{ __('Ubah Status') }}</span>
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-3"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

<div class="row g-3 g-md-4">
    {{-- Main Column (Left) --}}
    <div class="col-lg-8">
        {{-- Pending Payment Approval Banner --}}
        @if($order->status === 'pending_payment')
        <div class="card border-0 shadow-sm mb-3 bg-primary-subtle border-start border-primary border-4" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h6 class="fw-bold text-primary mb-1"><i class="fas fa-clock me-1"></i>{{ __('Menunggu Verifikasi Pembayaran') }}</h6>
                    <p class="text-secondary small mb-0">{{ __('Pelanggan telah membuat pesanan sebesar ') }} <b>{{ $order->formatted_total }}</b>.</p>
                </div>
                <div class="d-flex gap-2">
                    <form action="{{ route('admin.orders.accept', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, '{{ __('Konfirmasi terima pembayaran untuk pesanan ini?') }}');">
                        @csrf
                        <button type="submit" class="btn btn-success rounded-pill px-3 py-1.5 fw-bold">
                            <i class="fas fa-check me-1"></i>{{ __('Terima Pembayaran') }}
                        </button>
                    </form>
                    <form action="{{ route('admin.orders.reject', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, '{{ __('Tolak dan batalkan pesanan ini?') }}');">
                        @csrf
                        <button type="submit" class="btn btn-danger rounded-pill px-3 py-1.5 fw-bold">
                            <i class="fas fa-times me-1"></i>{{ __('Tolak Pesanan') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Order Items Card --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-body mb-0">
                    <i class="fas fa-shopping-bag text-primary me-2"></i>{{ __('Item Pesanan') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="list-group list-group-flush border rounded-3 overflow-hidden mt-1">
                    @foreach($order->items as $item)
                    <div class="list-group-item p-2.5 p-md-3 bg-light d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2.5 border-bottom">
                        <div class="d-flex align-items-center gap-2.5 min-w-0 flex-grow-1">
                            <div class="rounded-3 bg-white border p-1.5 d-flex align-items-center justify-content-center text-primary flex-shrink-0" style="width: 50px; height: 50px;">
                                @if($item->product && $item->product->image_url)
                                    <img src="{{ $item->product->image_url }}" alt="{{ $item->product->name }}" class="img-fluid rounded-2" style="max-height: 42px; object-fit: contain;">
                                @else
                                    <i class="fas fa-box fa-lg opacity-75"></i>
                                @endif
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                @php
                                    $adminItemLabel = $item->vpn_meta['label'] ?? ($item->product->name ?? '-');
                                    $itemVpnMeta = is_array($item->vpn_meta) ? $item->vpn_meta : [];
                                    $isItemVpn = ($item->product && $item->product->is_vpn) || !empty($itemVpnMeta['protocol']) || !empty($item->vpn_username);
                                    $itemVpnProto = strtolower((string) ($itemVpnMeta['protocol'] ?? $item->product?->vpn_protocol ?? ''));
                                    $itemSniCode = (string) ($itemVpnMeta['sni_mode'] ?? $item->product?->vpn_sni_mode ?? '3');
                                    $itemSniLabel = match($itemSniCode) {
                                        '1' => 'Zoom (support.zoom.us)',
                                        '2' => 'Iflix (live.iflix.com)',
                                        default => __('Default SNI'),
                                    };
                                @endphp
                                <h6 class="fw-bold text-body mb-1 text-truncate" style="font-size: 0.92rem;" title="{{ $adminItemLabel }}">{{ $adminItemLabel }}</h6>
                                <div class="text-muted small" style="font-size: 0.78rem;">
                                    {{ $item->quantity }} unit &times; Rp {{ number_format($item->unit_price, 0, ',', '.') }}
                                </div>
                                @if($isItemVpn)
                                <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                    @if($itemVpnProto)
                                    <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size: 0.68rem;">
                                        <i class="fas fa-network-wired me-1"></i>{{ strtoupper($itemVpnProto) }}@if($item->vpn_username) &bull; {{ $item->vpn_username }}@endif
                                    </span>
                                    @endif
                                    @if($itemVpnProto !== 'ssh')
                                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill" style="font-size: 0.68rem;">
                                        <i class="fas fa-sliders me-1"></i>{{ __('Preset:') }} {{ $itemSniLabel }}
                                    </span>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="text-end d-flex justify-content-between justify-content-sm-end align-items-center pt-2 pt-sm-0 border-top border-sm-top-0 flex-shrink-0">
                            <span class="text-muted small d-sm-none">{{ __('Subtotal:') }}</span>
                            <span class="fw-bold text-body fs-6">Rp {{ number_format($item->unit_price * $item->quantity, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="border-top mt-3 pt-3">
                    @php
                        $displaySubtotal = ($order->subtotal > 0)
                            ? (int) $order->subtotal
                            : (int) $order->items->sum(fn($i) => $i->unit_price * $i->quantity);
                        $hasVoucher = ($order->discount_amount > 0) || !empty($order->coupon_code);
                    @endphp
                    <div class="d-flex justify-content-between mb-1.5 small text-muted">
                        <span>{{ __('Subtotal') }}</span>
                        <span class="fw-semibold text-body">Rp {{ number_format($displaySubtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($hasVoucher)
                    <div class="d-flex justify-content-between align-items-center mb-1.5 small gap-2">
                        <span class="text-muted d-inline-flex align-items-center flex-wrap gap-1.5">
                            <span>{{ __('Diskon Voucher') }}</span>
                            @if($order->coupon_code)
                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 font-monospace" style="font-size: 0.72rem;">
                                <i class="fas fa-tag me-1"></i>{{ $order->coupon_code }}
                            </span>
                            @endif
                        </span>
                        <span class="fw-semibold text-success text-nowrap">- Rp {{ number_format((int) $order->discount_amount, 0, ',', '.') }}</span>
                    </div>
                    @endif
                    @if($order->unique_code)
                    <div class="d-flex justify-content-between mb-1.5 small text-muted">
                        <span>{{ __('Kode Unik') }}</span>
                        <span class="fw-semibold text-body">+ Rp {{ number_format((int) $order->unique_code, 0, ',', '.') }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between pt-2 border-top fw-bold fs-6">
                        <span>{{ __('Total Pembayaran') }}</span>
                        <span class="text-primary">{{ $order->formatted_total }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sent Stock / Credentials & Admin Stock Replacement Section --}}
        @if($order->stockUnits && $order->stockUnits->count() > 0)
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="fw-bold text-body mb-0">
                        <i class="fas fa-key text-success me-2"></i>{{ __('Data Akun / Lisensi Terkirim') }} ({{ $order->stockUnits->count() }} unit)
                    </h6>
                    <small class="text-muted">{{ __('Data kredensial yang diserahkan otomatis kepada pembeli') }}</small>
                </div>
                @if($order->status === 'delivered')
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#adminRefundOrderModal">
                    <i class="fas fa-undo-alt me-1"></i> {{ __('Refund Penuh & Batalkan') }}
                </button>
                @endif
            </div>
            <div class="card-body p-3 p-md-4">
                @php $isMultiUnit = $order->stockUnits->count() > 1; @endphp
                <div class="d-flex flex-column gap-3 mt-1">
                    @php $groupedUnits = $order->stockUnits->groupBy('product_id'); @endphp
                    @foreach($groupedUnits as $productId => $units)
                        @php $productName = $units->first()->product->name ?? __('Produk'); @endphp
                        <div class="mb-1">
                            <span class="badge bg-primary-subtle text-primary mb-2 fw-bold" style="font-size: 0.85rem;">{{ $productName }}</span>
                            <div class="d-flex flex-column gap-2">
                                @foreach($units as $unit)
                                <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center gap-3">
                                    <div class="text-break flex-grow-1" style="font-family: monospace; white-space: pre-wrap; font-size: 0.85rem;">{!! trim(\App\Services\TwoFactorService::renderWith2fa($unit->raw_text, true)) !!}</div>
                                    @if($order->status === 'delivered')
                                    <div class="flex-shrink-0">
                                        @if($isMultiUnit)
                                            <input type="checkbox" class="form-check-input stock-checkbox" data-order-id="{{ $order->id }}" value="{{ $unit->id }}" onchange="updateStockCheckboxes('{{ $order->id }}')" style="width: 1.5rem; height: 1.5rem; border-radius: 4px; cursor: pointer; border: 2px solid #ccc;">
                                        @else
                                            <form action="{{ route('admin.orders.replace-stock', [$order->id, $unit->id]) }}" method="POST" class="m-0" onsubmit="confirmAction(event, '{{ __('Apakah Anda yakin ingin mengganti akun ini dengan stok baru milik seller yang sama?') }}');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning text-warning-emphasis border-warning rounded-pill px-3">
                                                    <i class="fas fa-sync-alt me-1"></i> {{ __('Ganti Akun') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Bulk Action Bar --}}
                @if($isMultiUnit && $order->status === 'delivered')
                <div class="selected-actions-container mt-3 d-none" id="actions-{{ $order->id }}">
                    <div class="d-flex justify-content-between align-items-center bg-warning-subtle p-3 rounded-3 border border-warning">
                        <span class="small text-warning-emphasis fw-bold"><span class="checked-count" id="count-{{ $order->id }}">0</span> {{ __('akun terpilih:') }}</span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-warning rounded-pill px-3" onclick="submitBulkAction('{{ $order->id }}', 'replace')">
                                <i class="fas fa-sync-alt me-1"></i> {{ __('Ganti Akun Terpilih') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-danger rounded-pill px-3" onclick="submitBulkAction('{{ $order->id }}', 'refund')">
                                <i class="fas fa-undo-alt me-1"></i> {{ __('Refund Terpilih') }}
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Provisioned VPN Accounts Card (Admin View with Preset SNI & QR Code Modal) --}}
        @if($order->vpnAccounts && $order->vpnAccounts->count() > 0)
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="fw-bold text-body mb-0">
                        <i class="fas fa-network-wired text-primary me-2"></i>{{ __('Detail Akun VPN Terkirim') }} ({{ $order->vpnAccounts->count() }})
                    </h6>
                    <small class="text-muted">{{ __('Konfigurasi, preset SNI, dan kode QR akun VPN yang dibuat untuk pesanan ini') }}</small>
                </div>
                <a href="{{ route('admin.vpn-accounts.index', ['tab' => 'accounts', 'search' => $order->vpnAccounts->first()->username]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                    <i class="fas fa-sliders me-1"></i>{{ __('Kelola di Kelola VPN') }}
                </a>
            </div>
            <div class="card-body p-3 p-md-4">
                @foreach($order->vpnAccounts as $vpn)
                    @php
                        $vpnLinks = is_array($vpn->config_payload['links'] ?? null) ? $vpn->config_payload['links'] : [];
                        $matchedItem = $order->items->first(fn ($it) => strtolower((string) $it->vpn_username) === strtolower((string) $vpn->username)) ?? $order->items->first();
                        $vpnSniCode = (string) ($vpn->config_payload['sni_mode'] ?? ($matchedItem?->vpn_meta['sni_mode'] ?? ($matchedItem?->product?->vpn_sni_mode ?? '3')));
                        $vpnSniLabel = match($vpnSniCode) {
                            '1' => 'Zoom (support.zoom.us)',
                            '2' => 'Iflix (live.iflix.com)',
                            default => __('Default SNI'),
                        };
                        $vpnPayloadForModal = is_array($vpn->config_payload) ? array_merge(['sni_mode' => $vpnSniCode], $vpn->config_payload) : null;
                    @endphp
                    <div class="card border mb-3 overflow-hidden" style="border-radius: 14px;">
                        <div class="card-header bg-primary-subtle border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2 py-2.5 px-3">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-bold text-primary font-monospace">{{ strtoupper($vpn->protocol) }} &mdash; {{ $vpn->username }}</span>
                                <span class="badge {{ $vpn->status === 'active' ? 'bg-success text-white' : ($vpn->status === 'suspended' ? 'bg-warning text-dark' : 'bg-danger text-white') }} rounded-pill px-2.5">{{ ucfirst($vpn->status) }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-1.5">
                                @if($vpn->protocol !== 'ssh')
                                <button type="button"
                                        class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold btn-order-vpn-qr"
                                        data-title="{{ strtoupper($vpn->protocol) }} — {{ $vpn->username }}"
                                        data-sni-mode="{{ $vpnSniCode }}"
                                        data-payload='@json($vpnPayloadForModal)'
                                        data-text="{{ $vpn->config_link }}">
                                    <i class="fas fa-qrcode me-1"></i>{{ __('Lihat Kode QR') }}
                                </button>
                                @endif
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2 mb-3 small">
                                <div class="col-6 col-md-3">
                                    <span class="text-muted d-block">{{ __('Server / Domain:') }}</span>
                                    <strong class="text-body font-monospace text-break">{{ $vpn->display_domain }}</strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="text-muted d-block">{{ __('Masa Aktif:') }}</span>
                                    <strong class="text-body">{{ $vpn->expired_at ? $vpn->expired_at->format('d M Y') : '-' }}</strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="text-muted d-block">{{ __('Kuota / Limit IP:') }}</span>
                                    <strong class="text-body">{{ ($vpn->quota_gb ?? 0) > 0 ? $vpn->quota_gb . ' GB' : 'Unlimited' }} &bull; {{ max(1, (int) ($vpn->ip_limit ?? 1)) }} IP</strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="text-muted d-block">{{ __('Preset SNI:') }}</span>
                                    <strong class="text-primary">{{ $vpn->protocol === 'ssh' ? 'SSH' : $vpnSniLabel }}</strong>
                                </div>
                            </div>

                            @if(!empty($vpnLinks))
                                <div class="d-flex flex-column gap-1.5 mb-2.5">
                                    @foreach($vpnLinks as $linkType => $linkVal)
                                        @if($linkVal)
                                        <div class="d-flex align-items-center gap-2 p-2 rounded-3 border bg-light">
                                            <span class="badge bg-primary-subtle text-primary text-uppercase">{{ $linkType }}</span>
                                            <input type="text" class="form-control form-control-sm font-monospace border-0 bg-transparent p-0" value="{{ $linkVal }}" readonly>
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 flex-shrink-0" onclick="navigator.clipboard?.writeText(@js($linkVal)); this.innerHTML='<i class=\'fas fa-check text-success\'></i>';">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <span class="text-muted small d-block mb-1.5">{{ __('Konfigurasi Output / Link:') }}</span>
                            <div class="p-3 rounded-3 border bg-light text-break font-monospace" style="max-height: 220px; overflow-y: auto; white-space: pre-wrap; font-size: 0.82rem;">{{ $vpn->config_link ?: __('Tidak ada konfigurasi ditemukan.') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Complaint Cases Cards (Supports Multi-Item Complaints) --}}
        @php
            $adminOrderComplaints = ($order->complaints && $order->complaints->isNotEmpty())
                ? $order->complaints
                : ($order->complaintCase ? collect([$order->complaintCase]) : collect());
        @endphp
        @foreach($adminOrderComplaints as $ordCmp)
        <div class="card border-0 shadow-sm mb-3 border-start border-danger border-4" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-danger mb-0">
                        <i class="fas fa-shield-alt me-2"></i>{{ __('Pusat Komplain / Dispute') }} <span class="font-monospace small">#{{ $ordCmp->complaint_ref }}</span>
                    </h6>
                    <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1">
                        {{ ucfirst($ordCmp->status) }}
                    </span>
                </div>
                @if($ordCmp->resolveTargetProduct())
                <div class="small mb-2 d-flex flex-wrap gap-1.5">
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1">{{ $ordCmp->resolveTargetProduct()->name }}</span>
                    @if($ordCmp->resolveTargetSeller())
                    <span class="badge bg-secondary-subtle text-body rounded-pill px-2 py-1"><i class="fas fa-store me-1"></i>{{ $ordCmp->resolveTargetSeller()->display_name }}</span>
                    @endif
                </div>
                @endif
                <p class="text-muted small mb-3">{{ $ordCmp->complaint_text }}</p>
                @if(Route::has('admin.complaints.show'))
                <a href="{{ route('admin.complaints.show', $ordCmp->id) }}" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                    <i class="fas fa-gavel me-1"></i>{{ __('Tinjau Kasus Komplain') }}
                </a>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Side Column (Right) --}}
    <div class="col-lg-4">
        {{-- Customer Info Card --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-body mb-0">
                    <i class="fas fa-user text-primary me-2"></i>{{ __('Informasi Pelanggan') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center gap-3 mb-3 mt-1">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-5 flex-shrink-0 overflow-hidden" style="width: 44px; height: 44px;">
                        @if($order->customer && $order->customer->avatar)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($order->customer->avatar) }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            {{ strtoupper(substr($order->customer->full_name ?? $order->customer->username ?? 'P', 0, 1)) }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h6 class="fw-bold mb-0 text-body text-truncate">{{ $order->customer->full_name ?? $order->customer->username ?? '-' }}</h6>
                        <small class="text-muted text-truncate d-block">{{ $order->customer->username ? '@'.$order->customer->username : '-' }}</small>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">{{ __('Telegram ID:') }}</span>
                        <code>{{ $order->customer->telegram_id ?? '-' }}</code>
                    </div>
                    @if($order->customer && $order->customer->telegram_username)
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">{{ __('Telegram:') }}</span>
                        <a href="https://t.me/{{ $order->customer->telegram_username }}" target="_blank" class="text-decoration-none fw-semibold">
                            <i class="fab fa-telegram me-1"></i>Chat
                        </a>
                    </div>
                    @endif
                    @if(Route::has('admin.users.edit') && $order->customer)
                    <div class="text-end pt-2 border-top">
                        <a href="{{ route('admin.users.edit', $order->customer->id) }}" class="text-decoration-none small text-primary">
                            <i class="fas fa-external-link-alt me-1"></i>{{ __('Lihat Profil User') }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sellers Info Card (Supports Multi-Seller Cart Orders) --}}
        @php
            $involvedSellers = $order->getDistinctSellers();
        @endphp
        @if($involvedSellers->isNotEmpty())
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-body mb-0">
                    <i class="fas fa-store text-success me-2"></i>{{ __('Penjual / Seller') }}
                </h6>
                @if($involvedSellers->count() > 1)
                <span class="badge bg-primary-subtle text-primary rounded-pill">{{ $involvedSellers->count() }} {{ __('Toko') }}</span>
                @endif
            </div>
            <div class="card-body p-3 p-md-4 d-flex flex-column gap-3">
                @foreach($involvedSellers as $entry)
                @php
                    $seller = $entry['seller'];
                    $sellerItemLabels = $entry['items']->map(fn ($it) => ($it->product->name ?? '-') . ' (' . $it->quantity . '×)')->implode(', ');
                @endphp
                <div class="{{ !$loop->first ? 'pt-3 border-top' : '' }}">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center fw-bold fs-5 flex-shrink-0" style="width: 44px; height: 44px;">
                            {{ strtoupper(substr($seller->full_name ?? $seller->username ?? 'S', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <h6 class="fw-bold mb-0 text-body text-truncate">{{ $seller->full_name ?? $seller->username }}</h6>
                            <small class="text-muted text-truncate d-block">{{ $seller->username ? '@'.$seller->username : '-' }}</small>
                        </div>
                    </div>
                    <div class="p-2.5 bg-light rounded-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">{{ __('Telegram ID:') }}</span>
                            <code>{{ $seller->telegram_id ?? '-' }}</code>
                        </div>
                        <div class="text-muted" style="font-size: 0.76rem;">
                            <strong>{{ __('Item:') }}</strong> {{ $sellerItemLabels }}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Transaction Log & Timeline Card --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-body mb-0">
                    <i class="fas fa-history text-secondary me-2"></i>{{ __('Log Sistem') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <ul class="list-unstyled mb-0 small">
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-clock text-muted mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibuat:') }}</span>
                            <div class="fw-semibold">{{ $order->created_at->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @if($order->paid_at)
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-check-circle text-success mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibayar:') }}</span>
                            <div class="fw-semibold text-success">{{ \App\Support\SystemTimezone::toSystem($order->paid_at)?->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($order->delivered_at)
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-paper-plane text-primary mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dikirim:') }}</span>
                            <div class="fw-semibold text-primary">{{ \App\Support\SystemTimezone::toSystem($order->delivered_at)?->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($order->cancelled_at)
                    <li class="d-flex align-items-start gap-2">
                        <i class="fas fa-times-circle text-danger mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibatalkan:') }}</span>
                            <div class="fw-semibold text-danger">{{ \App\Support\SystemTimezone::toSystem($order->cancelled_at)?->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                </ul>

                @if($order->cancel_reason)
                <div class="alert alert-danger mt-3 small mb-0 py-2">
                    <b>{{ __('Alasan Batal:') }}</b> {{ $order->cancel_reason }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Full Refund & Cancel Choice Modal --}}
@if($order->status === 'delivered')
<div class="modal fade" id="adminRefundOrderModal" tabindex="-1" aria-labelledby="adminRefundOrderModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border" style="border-radius: 24px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold text-danger mb-0" id="adminRefundOrderModalLabel">
                    <i class="fas fa-undo-alt me-2"></i>{{ __('Refund & Batalkan Pesanan') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.orders.refund', $order->id) }}" method="POST">
                @csrf
                @php
                    $orderNetRefundable = max(0, (int) $order->total_amount - (int) ($order->unique_code ?? 0));
                    if ($orderNetRefundable <= 0) $orderNetRefundable = (int) $order->total_amount;
                @endphp
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        {{ __('Pesanan akan dibatalkan, seluruh stok ditarik ke karantina, dan dana tertahan seller dibatalkan. Pilih mekanisme pengembalian dana untuk pembeli:') }}
                    </p>

                    <div class="d-flex flex-column gap-2.5">
                        <label class="p-3 rounded-4 border d-flex align-items-start gap-2.5" style="cursor: pointer; background: var(--surface-container, var(--bs-tertiary-bg));">
                            <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="refund_destination" value="wallet" checked>
                            <div>
                                <div class="fw-bold small text-body">{{ __('Kirim Saldo ke Dompet Pembeli + Batalkan') }}</div>
                                <div class="text-muted" style="font-size: 0.76rem;">
                                    {{ __('Otomatis mengembalikan dana sebesar Rp:amount ke Dompet Saldo akun pembeli.', ['amount' => number_format($orderNetRefundable, 0, ',', '.')]) }}
                                </div>
                            </div>
                        </label>

                        <label class="p-3 rounded-4 border d-flex align-items-start gap-2.5" style="cursor: pointer; background: var(--surface-container, var(--bs-tertiary-bg));">
                            <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="refund_destination" value="external">
                            <div>
                                <div class="fw-bold small text-body">{{ __('Batalkan Saja (Refund Manual di Luar Website)') }}</div>
                                <div class="text-muted" style="font-size: 0.76rem;">
                                    {{ __('Saldo dompet pembeli TIDAK ditambah. Gunakan opsi ini bila Admin sudah mentransfer pengembalian dana secara manual di luar website.') }}
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-semibold">
                        <i class="fas fa-check me-1"></i>{{ __('Proses Pembatalan') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Edit Status Modal (Rendered at Root Body outside container) --}}
<div class="modal fade" id="editOrderStatusModal" tabindex="-1" aria-labelledby="editOrderStatusModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold" id="editOrderStatusModalLabel">{{ __('Ubah Status Pesanan') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.orders.update', $order->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <p class="mb-1 text-muted small">{{ __('No. Order') }}</p>
                        <h6 class="fw-bold text-primary font-monospace">{{ $order->reference }}</h6>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Status Baru') }}</label>
                        <select name="status" class="form-select" required>
                            <option value="pending_payment" {{ $order->status == 'pending_payment' ? 'selected' : '' }}>{{ __('Pending Payment') }}</option>
                            <option value="paid" {{ $order->status == 'paid' ? 'selected' : '' }}>{{ __('Paid (Lunas)') }}</option>
                            <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }}>{{ __('Delivered (Selesai)') }}</option>
                            <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled (Dibatalkan)') }}</option>
                            <option value="expired" {{ $order->status == 'expired' ? 'selected' : '' }}>{{ __('Expired (Kedaluwarsa)') }}</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Simpan Perubahan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Order VPN QR & Live Preset SNI Modal --}}
@if($order->vpnAccounts && $order->vpnAccounts->count() > 0)
<div class="modal fade" id="orderVpnQrModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-1">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="fas fa-qrcode text-primary"></i>
                    <span id="orderVpnQrTitle">{{ __('Kode QR Konfigurasi VPN') }}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="orderVpnQrCanvasWrap" class="text-center mb-3 d-none">
                    <canvas id="orderVpnQrCanvas" width="220" height="220" class="rounded-4 border p-2 bg-white"></canvas>
                </div>
                <div id="orderVpnQrSniWrap" class="mb-3 d-none">
                    <label class="form-label small fw-bold text-muted mb-1"><i class="fas fa-sliders text-primary me-1"></i>{{ __('Preset Mode SNI / Payload') }}</label>
                    <select id="orderVpnQrSniSelect" class="form-select form-select-sm">
                        <option value="3">{{ __('Tanpa Konfigurasi (Default SNI)') }}</option>
                        <option value="1">Zoom (support.zoom.us — Wildcard)</option>
                        <option value="2">Iflix (live.iflix.com)</option>
                    </select>
                </div>
                <div id="orderVpnQrLinksWrap" class="d-flex flex-column gap-2 mb-3"></div>
                <label class="form-label small fw-bold text-muted">{{ __('Teks Konfigurasi Lengkap') }}</label>
                <textarea id="orderVpnQrFullText" class="form-control font-monospace small" rows="5" readonly></textarea>
            </div>
            <div class="modal-footer border-0 pt-1">
                <button type="button" class="btn btn-outline-primary rounded-pill px-3" id="btnCopyOrderVpnQrAll"><i class="fas fa-copy me-1"></i>{{ __('Salin Semua') }}</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@endif
@endpush

@push('scripts')
<script>
    function updateStockCheckboxes(orderId) {
        const checkboxes = document.querySelectorAll(`.stock-checkbox[data-order-id="${orderId}"]`);
        const checked = document.querySelectorAll(`.stock-checkbox[data-order-id="${orderId}"]:checked`);
        const container = document.getElementById(`actions-${orderId}`);
        const countSpan = document.getElementById(`count-${orderId}`);
        
        if (container && countSpan) {
            if (checked.length > 0) {
                countSpan.textContent = checked.length;
                container.classList.remove('d-none');
            } else {
                container.classList.add('d-none');
            }
        }
    }

    function submitBulkAction(orderId, actionType) {
        const checked = document.querySelectorAll(`.stock-checkbox[data-order-id="${orderId}"]:checked`);
        if (checked.length === 0) return;

        const ids = Array.from(checked).map(cb => cb.value);

        if (actionType === 'replace') {
            const confirmMsg = "{{ __('Apakah Anda yakin ingin mengganti :count akun terpilih dengan stok baru dari seller?') }}".replace(':count', ids.length);
            const url = `/admin/orders/${orderId}/replace-stock-bulk`;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '{{ __('Konfirmasi Aksi') }}',
                    text: confirmMsg,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '{{ __('Ya, Lanjutkan') }}',
                    cancelButtonText: '{{ __('Batal') }}'
                }).then((result) => {
                    if (result.isConfirmed) {
                        executeBulkFormSubmit(url, ids);
                    }
                });
            } else if (confirm(confirmMsg)) {
                executeBulkFormSubmit(url, ids);
            }
            return;
        }

        // Bulk Refund with explicit choice: Wallet vs Manual External Transfer
        const url = `/admin/orders/${orderId}/refund-bulk`;
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '{{ __('Pilih Metode Refund Sebagian') }}',
                html: `<p class="small text-muted mb-3">${"{{ __('Akan menarik :count akun terpilih ke karantina dan membatalkan dana tertahan seller terkait.') }}".replace(':count', ids.length)}</p>
                    <div class="text-start d-flex flex-column gap-2">
                        <label class="p-2.5 rounded-3 border d-flex align-items-start gap-2" style="cursor:pointer;">
                            <input type="radio" name="swal_refund_dest" value="wallet" class="form-check-input mt-1" checked>
                            <span class="small"><strong>{{ __('Kirim ke Dompet Saldo Pembeli') }}</strong><br><span class="text-muted">{{ __('Nilai bersih akun terpilih masuk otomatis ke dompet pembeli.') }}</span></span>
                        </label>
                        <label class="p-2.5 rounded-3 border d-flex align-items-start gap-2" style="cursor:pointer;">
                            <input type="radio" name="swal_refund_dest" value="external" class="form-check-input mt-1">
                            <span class="small"><strong>{{ __('Batalkan Saja (Refund Manual di Luar Website)') }}</strong><br><span class="text-muted">{{ __('Tanpa menambah saldo dompet pembeli.') }}</span></span>
                        </label>
                    </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '{{ __('Proses Refund') }}',
                cancelButtonText: '{{ __('Batal') }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    const selectedDest = document.querySelector('input[name="swal_refund_dest"]:checked')?.value || 'external';
                    executeBulkFormSubmit(url, ids, selectedDest);
                }
            });
        } else if (confirm("{{ __('Proses refund sebagian untuk akun terpilih?') }}")) {
            executeBulkFormSubmit(url, ids, 'external');
        }
    }

    function executeBulkFormSubmit(url, ids, refundDestination = null) {
        const loader = document.getElementById('pageLoader');
        if (loader) loader.classList.remove('d-none');

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }

        const idsInput = document.createElement('input');
        idsInput.type = 'hidden';
        idsInput.name = 'stock_unit_ids';
        idsInput.value = JSON.stringify(ids);
        form.appendChild(idsInput);

        if (refundDestination) {
            const destInput = document.createElement('input');
            destInput.type = 'hidden';
            destInput.name = 'refund_destination';
            destInput.value = refundDestination;
            form.appendChild(destInput);
        }

        document.body.appendChild(form);
        form.submit();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const qrModalEl = document.getElementById('orderVpnQrModal');
        if (!qrModalEl) return;

        const escHtml = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        const b64Utf8 = (s) => { try { return btoa(unescape(encodeURIComponent(s))); } catch (e) { return btoa(s); } };
        const sniWrap = document.getElementById('orderVpnQrSniWrap');
        const sniSelect = document.getElementById('orderVpnQrSniSelect');
        let activeOrderQrState = null;

        function buildOrderXrayLinks(protocol, domain, uuid, username, sniMode) {
            if (!domain || !uuid || !protocol || protocol === 'ssh') return null;
            let addr = domain, host = domain, sni = domain, label = username;
            if (sniMode === '1') { addr = 'support.zoom.us'; host = 'support.zoom.us.' + domain; sni = host; label = username + ' (Zoom)'; }
            else if (sniMode === '2') { sni = 'live.iflix.com'; label = username + ' (Iflix)'; }
            const enc = encodeURIComponent(label);
            if (protocol === 'vmess') {
                return {
                    tls: 'vmess://' + b64Utf8(JSON.stringify({ v:'2', ps:label, add:addr, port:'443', id:uuid, aid:'0', net:'ws', path:'/vmess', type:'none', host, tls:'tls', sni, allowInsecure:true })),
                    ntls: 'vmess://' + b64Utf8(JSON.stringify({ v:'2', ps:label, add:addr, port:'80', id:uuid, aid:'0', net:'ws', path:'/vmess', type:'none', host, tls:'none' })),
                    grpc: 'vmess://' + b64Utf8(JSON.stringify({ v:'2', ps:label + ' gRPC', add:addr, port:'443', id:uuid, aid:'0', net:'grpc', path:'vmess-grpc', type:'none', host, tls:'tls', sni })),
                };
            }
            if (protocol === 'vless') {
                return {
                    tls: `vless://${uuid}@${addr}:443?path=/vless&security=tls&encryption=none&type=ws&host=${host}&sni=${sni}#${enc}`,
                    ntls: `vless://${uuid}@${addr}:80?path=/vless&encryption=none&type=ws&host=${host}#${enc}`,
                    grpc: `vless://${uuid}@${addr}:443?mode=gun&security=tls&encryption=none&type=grpc&serviceName=vless-grpc&sni=${sni}#${enc}`,
                };
            }
            if (protocol === 'trojan') {
                return {
                    tls: `trojan://${uuid}@${addr}:443?path=/trojan-ws&security=tls&type=ws&host=${host}&sni=${sni}#${enc}`,
                    grpc: `trojan://${uuid}@${addr}:443?mode=gun&security=tls&type=grpc&serviceName=trojan-grpc&sni=${sni}#${enc}`,
                };
            }
            if (protocol === 'shadowsocks') {
                const cred = btoa('aes-128-gcm:' + uuid);
                return {
                    tls: `ss://${cred}@${addr}:443?path=/ss-ws&security=tls&host=${host}&type=ws&sni=${sni}#${enc}`,
                    grpc: `ss://${cred}@${addr}:443?mode=gun&security=tls&type=grpc&serviceName=ss-grpc&sni=${sni}#${enc}`,
                };
            }
            return null;
        }

        function renderOrderQrModal(title, payload, fullText, overrideSni = null) {
            document.getElementById('orderVpnQrTitle').textContent = title || 'VPN QR';
            activeOrderQrState = { title, payload, fullText };
            let linksObj = (payload && payload.links && typeof payload.links === 'object') ? payload.links : null;
            let txt = fullText || '';

            const canSwitch = !!(payload && payload.protocol && payload.protocol !== 'ssh' && payload.domain && payload.uuid);
            if (sniWrap && sniSelect) {
                sniWrap.classList.toggle('d-none', !canSwitch);
                if (canSwitch) {
                    const chosen = overrideSni || String(payload.sni_mode || '3');
                    sniSelect.value = ['1', '2', '3'].includes(chosen) ? chosen : '3';
                    if (overrideSni !== null) {
                        const regen = buildOrderXrayLinks(String(payload.protocol).toLowerCase(), payload.domain, payload.uuid, payload.username || '', sniSelect.value);
                        if (regen) {
                            linksObj = regen;
                            const presetLabel = sniSelect.value === '1' ? 'Zoom (support.zoom.us)' : (sniSelect.value === '2' ? 'Iflix (live.iflix.com)' : 'Default SNI');
                            const lines = [
                                `=== ${String(payload.protocol).toUpperCase()} ACCOUNT ===`,
                                `Username : ${payload.username || '-'}`,
                                `Domain   : ${payload.domain || '-'}`,
                                `UUID     : ${payload.uuid || '-'}`,
                                `Preset   : ${presetLabel}`,
                                `Expired  : ${payload.expired_at || '-'}`,
                            ];
                            if (regen.tls) lines.push('', 'Link TLS (443):', regen.tls);
                            if (regen.ntls) lines.push('', 'Link Non-TLS (80):', regen.ntls);
                            if (regen.grpc) lines.push('', 'Link gRPC (443):', regen.grpc);
                            txt = lines.join('\n');
                        }
                    }
                }
            }

            document.getElementById('orderVpnQrFullText').value = txt;
            const linksWrap = document.getElementById('orderVpnQrLinksWrap');
            const qrWrap = document.getElementById('orderVpnQrCanvasWrap');
            const qrCanvas = document.getElementById('orderVpnQrCanvas');
            linksWrap.innerHTML = '';

            let primaryLink = '';
            if (linksObj) {
                Object.entries(linksObj).filter(([, v]) => !!v).forEach(([k, v]) => {
                    if (!primaryLink) primaryLink = v;
                    const row = document.createElement('div');
                    row.className = 'd-flex align-items-center gap-2 p-2 rounded-3 border';
                    row.innerHTML = `<span class="badge bg-primary-subtle text-primary text-uppercase">${escHtml(k)}</span>
                        <input type="text" class="form-control form-control-sm font-monospace border-0 bg-transparent p-0" value="${escHtml(v)}" readonly>
                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 flex-shrink-0"><i class="fas fa-copy"></i></button>`;
                    row.querySelector('button').addEventListener('click', function () {
                        navigator.clipboard?.writeText(v);
                        this.innerHTML = '<i class="fas fa-check text-success"></i>';
                        setTimeout(() => { this.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500);
                    });
                    linksWrap.appendChild(row);
                });
            }

            if (primaryLink && typeof window.renderLocalQrToCanvas === 'function') {
                qrWrap.classList.remove('d-none');
                window.renderLocalQrToCanvas(qrCanvas, primaryLink, 220);
            } else {
                qrWrap.classList.add('d-none');
            }
        }

        sniSelect?.addEventListener('change', function () {
            if (!activeOrderQrState || !activeOrderQrState.payload) return;
            renderOrderQrModal(activeOrderQrState.title, activeOrderQrState.payload, activeOrderQrState.fullText, this.value);
        });

        document.querySelectorAll('.btn-order-vpn-qr').forEach(function (btn) {
            btn.addEventListener('click', function () {
                let payload = null;
                try { payload = JSON.parse(this.dataset.payload || 'null'); } catch (e) {}
                renderOrderQrModal(this.dataset.title || 'VPN QR', payload, this.dataset.text || '', null);
                bootstrap.Modal.getOrCreateInstance(qrModalEl).show();
            });
        });

        document.getElementById('btnCopyOrderVpnQrAll')?.addEventListener('click', function () {
            navigator.clipboard?.writeText(document.getElementById('orderVpnQrFullText').value);
            const orig = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check text-success me-1"></i>' + @js(__('Tersalin!'));
            setTimeout(() => { this.innerHTML = orig; }, 1500);
        });
    });
</script>
@endpush
