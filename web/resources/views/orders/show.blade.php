@extends('layouts.app')

@section('title', __('Detail Pesanan ') . $order->reference)
@section('page_subtitle', __('Detail Pesanan'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('orders.index') }}" class="btn btn-light rounded-circle border d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Riwayat Pesanan') }}">
            <i class="fas fa-arrow-left text-body"></i>
        </a>
        <div>
            <h5 class="fw-bold m-0 text-body" style="font-size: 1.15rem;">{{ __('Detail Pesanan') }}</h5>
            <small class="text-muted font-monospace" style="font-size: 0.75rem;">{{ $order->reference }} &bull; {{ $order->created_at->format('d M Y, H:i') }}</small>
        </div>
    </div>
    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-1.5 fw-bold">
        {{ $order->status_label }}
    </span>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-transparent pt-3 px-4 pb-3 d-none d-md-block">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-body">
                        <i class="fas fa-receipt text-primary me-2"></i>
                        {{ __('Pesanan') }} <span class="font-monospace">{{ $order->reference }}</span>
                    </h5>
                    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-1.5">
                        {{ $order->status_label }}
                    </span>
                </div>
            </div>
            <div class="card-body px-4 pb-4 pt-4">
                <div class="mb-4">
                    <span class="text-muted small fw-semibold text-uppercase d-block mb-2" style="letter-spacing: 0.04em; font-size: 0.72rem;">{{ __('Item Pesanan:') }}</span>
                    <div class="list-group list-group-flush border rounded-3 overflow-hidden mb-3" style="border-color: var(--outline-color) !important;">
                        @php
                            $orderComplaints = $order->complaints ?? collect();
                            $claimableItems = $order->items->filter(function ($it) use ($order, $orderComplaints) {
                                if (!$order->isWarrantyActiveForItem($it)) {
                                    return false;
                                }
                                $alreadyClaimed = $orderComplaints->contains(function ($cmp) use ($it, $order) {
                                    if ($cmp->order_item_id) return (int) $cmp->order_item_id === (int) $it->id;
                                    if ($cmp->product_id) return (int) $cmp->product_id === (int) $it->product_id;
                                    return $order->items->count() === 1;
                                });
                                return !$alreadyClaimed;
                            })->values();
                        @endphp
                        @foreach($order->items as $item)
                        @php
                            $itemSeller = $item->product?->creator;
                            $itemWarrantyExpires = $order->getWarrantyExpiresAtForItem($item);
                            $itemWarrantyActive = $order->isWarrantyActiveForItem($item);
                            $itemComplaint = $orderComplaints->first(function ($cmp) use ($item, $order) {
                                if ($cmp->order_item_id) return (int) $cmp->order_item_id === (int) $item->id;
                                if ($cmp->product_id) return (int) $cmp->product_id === (int) $item->product_id;
                                return $order->items->count() === 1;
                            });
                        @endphp
                        <div class="list-group-item p-3" style="background: var(--surface-bg); border-color: var(--outline-color) !important;">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="min-w-0">
                                    <span class="fw-bold text-body">{{ $item->vpn_meta['label'] ?? ($item->product->name ?? '-') }}</span>
                                    <small class="text-muted d-block">{{ $item->quantity }} &times; Rp {{ number_format($item->unit_price, 0, ',', '.') }}</small>
                                    @php
                                        $custVpnMeta = is_array($item->vpn_meta) ? $item->vpn_meta : [];
                                        $isCustItemVpn = ($item->product && $item->product->is_vpn) || !empty($custVpnMeta['protocol']) || !empty($item->vpn_username);
                                        $custVpnProto = strtolower((string) ($custVpnMeta['protocol'] ?? $item->product?->vpn_protocol ?? ''));
                                        $custSniCode = (string) ($custVpnMeta['sni_mode'] ?? $item->product?->vpn_sni_mode ?? '3');
                                        $custSniLabel = match($custSniCode) {
                                            '1' => 'Zoom (support.zoom.us)',
                                            '2' => 'Iflix (live.iflix.com)',
                                            default => __('Default SNI'),
                                        };
                                    @endphp
                                    @if($isCustItemVpn && $custVpnProto !== 'ssh')
                                    <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                        <span class="badge bg-info-subtle text-info-emphasis rounded-pill" style="font-size: 0.68rem;">
                                            <i class="fas fa-sliders me-1"></i>{{ __('Preset:') }} {{ $custSniLabel }}
                                        </span>
                                    </div>
                                    @endif
                                    <div class="mt-1.5 d-flex flex-wrap align-items-center gap-1.5">
                                        @if($itemSeller)
                                            <span class="badge bg-secondary-subtle text-body rounded-pill px-2 py-1" style="font-size: 0.7rem;">
                                                <i class="fas fa-store text-primary me-1"></i>{{ $itemSeller->display_name }}
                                            </span>
                                            @if($itemSeller->id !== Auth::id())
                                                <a href="{{ route('chat.index', ['contact_id' => $itemSeller->id]) }}"
                                                   class="btn btn-xs btn-outline-primary rounded-pill px-2 py-0.5 fw-semibold"
                                                   style="font-size: 0.7rem;">
                                                    <i class="fas fa-comments me-1"></i>{{ __('Chat Penjual') }}
                                                </a>
                                            @endif
                                        @endif
                                        @if($order->status === 'delivered' && $itemWarrantyExpires)
                                            @if($itemComplaint)
                                                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2 py-1" style="font-size: 0.68rem;">
                                                    <i class="fas fa-toolbox me-1"></i>{{ __('Tiket #') }}{{ $itemComplaint->complaint_ref }}
                                                </span>
                                            @elseif($itemWarrantyActive)
                                                <button type="button"
                                                        class="btn btn-xs btn-outline-warning text-warning-emphasis rounded-pill px-2 py-0.5 fw-semibold"
                                                        style="font-size: 0.7rem;"
                                                        onclick="openComplaintModalForItem({{ $item->id }})">
                                                    <i class="fas fa-shield-alt me-1"></i>{{ __('Klaim Garansi') }}
                                                </button>
                                            @else
                                                <span class="badge bg-secondary-subtle text-muted rounded-pill px-2 py-1" style="font-size: 0.68rem;">
                                                    {{ __('Garansi Berakhir') }}
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                    @if($item->vcc_services && count($item->vcc_services) > 0)
                                        @php $groupedServices = array_count_values($item->vcc_services); @endphp
                                        <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                            <span class="badge bg-primary-subtle text-primary fw-bold" style="font-size: 0.68rem;">
                                                <i class="fas fa-credit-card me-1"></i>VCC
                                            </span>
                                            @foreach($groupedServices as $srvName => $srvQty)
                                                <span class="badge bg-secondary-subtle text-body" style="font-size: 0.65rem;">
                                                    {{ $srvName }}@if($srvQty > 1) ({{ $srvQty }})@endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <span class="fw-bold text-body text-nowrap">Rp {{ number_format($item->unit_price * $item->quantity, 0, ',', '.') }}</span>
                            </div>

                            {{-- Product Review Section --}}
                            @if($order->status === 'delivered' && $item->product)
                                @php
                                    $hasReview = \App\Models\Review::where('order_id', $order->id)
                                        ->where('product_id', $item->product_id)
                                        ->first();
                                @endphp
                                @if($hasReview)
                                    <div class="mt-3 p-2.5 bg-success-subtle rounded-3" style="font-size: 0.85rem;">
                                        <i class="fas fa-check-circle text-success me-1"></i> {{ __('Anda telah memberikan ulasan:') }}
                                        <span class="text-warning ms-1">
                                            @for($i=1; $i<=5; $i++)
                                                <i class="fa{{ $i <= $hasReview->rating ? 's' : 'r' }} fa-star"></i>
                                            @endfor
                                        </span>
                                        @if($hasReview->comment)
                                            <p class="mb-0 mt-1 text-secondary italic">"{{ $hasReview->comment }}"</p>
                                        @endif
                                    </div>
                                @else
                                    <div class="mt-3 p-3 m3-tonal-item" style="font-size: 0.85rem;">
                                        <h6 class="fw-bold text-body small mb-2"><i class="fas fa-star text-warning me-1"></i> {{ __('Berikan Ulasan & Rating:') }}</h6>
                                        <form action="{{ route('reviews.store') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="order_id" value="{{ $order->id }}">
                                            <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                            
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <span class="small text-muted">{{ __('Rating:') }}</span>
                                                <div class="rating-input d-flex gap-1 text-warning">
                                                    @for($star = 1; $star <= 5; $star++)
                                                    <input type="radio" name="rating" id="star-{{ $order->id }}-{{ $item->product_id }}-{{ $star }}" value="{{ $star }}" class="d-none" required>
                                                    <label for="star-{{ $order->id }}-{{ $item->product_id }}-{{ $star }}" style="cursor: pointer;" onclick="highlightStars({{ $order->id }}, {{ $item->product_id }}, {{ $star }})">
                                                        <i class="far fa-star fs-5" id="icon-{{ $order->id }}-{{ $item->product_id }}-{{ $star }}"></i>
                                                    </label>
                                                    @endfor
                                                </div>
                                            </div>

                                            <div class="input-group input-group-sm">
                                                <input type="text" name="comment" class="form-control" placeholder="{{ __('Tulis ulasan Anda (opsional)...') }}">
                                                <button class="btn btn-primary px-3" type="submit">{{ __('Kirim') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

                <hr>

                <h6 class="fw-bold mb-3 text-body">{{ __('Timeline') }}</h6>
                <div class="order-timeline">
                    <div class="timeline-step completed">
                        <div class="fw-bold small text-body">{{ __('Pesanan Dibuat') }}</div>
                        <div class="text-muted small">{{ $order->created_at->format('d M Y H:i:s') }}</div>
                    </div>

                    @if($order->status === 'delivered')
                    <div class="timeline-step completed">
                        <div class="fw-bold small text-body">{{ __('Pembayaran Diterima') }}</div>
                        <div class="text-muted small">{{ __('Pembayaran telah diverifikasi') }}</div>
                    </div>
                    <div class="timeline-step completed">
                        <div class="fw-bold small text-body">{{ __('Produk Dikirim') }}</div>
                        <div class="text-muted small">
                            {{ $order->delivered_at ? $order->delivered_at->format('d M Y H:i:s') : '-' }}
                        </div>
                    </div>
                    @elseif($order->status === 'paid')
                    <div class="timeline-step completed">
                        <div class="fw-bold small text-body">{{ __('Pembayaran Diterima') }}</div>
                        <div class="text-muted small">{{ __('Menunggu pengiriman produk') }}</div>
                    </div>
                    <div class="timeline-step active">
                        <div class="fw-bold small text-body">{{ __('Menunggu Pengiriman') }}</div>
                        <div class="text-muted small">{{ __('Produk sedang diproses') }}</div>
                    </div>
                    @elseif($order->status === 'pending_payment')
                    <div class="timeline-step active">
                        <div class="fw-bold small text-body">{{ __('Menunggu Pembayaran') }}</div>
                        <div class="text-muted small">
                            @if($order->expires_at)
                                {{ __('Batas waktu:') }} {{ $order->expires_at->format('d M Y H:i:s') }}
                            @else
                                {{ __('Segera lakukan pembayaran') }}
                            @endif
                        </div>
                    </div>
                    @elseif(in_array($order->status, ['cancelled', 'expired']))
                    <div class="timeline-step">
                        <div class="fw-bold small text-danger">
                            {{ $order->status === 'cancelled' ? __('Dibatalkan') : __('Kedaluwarsa') }}
                        </div>
                        <div class="text-muted small">
                            {{ $order->cancel_reason ?: __('Tidak ada alasan') }}
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Stock content for delivered orders --}}
                @if($order->status === 'delivered')
                    @if($order->stockUnits && $order->stockUnits->count() > 0)
                    <hr>
                    <h6 class="fw-bold mb-3 text-body"><i class="fas fa-key text-success me-2"></i>{{ __('Detail Akun yang Dibeli') }} ({{ __(':count unit', ['count' => $order->stockUnits->count()]) }})</h6>
                    <div class="d-flex flex-column gap-3">
                        @php $groupedUnits = $order->stockUnits->groupBy('product_id'); @endphp
                        @foreach($groupedUnits as $productId => $units)
                            @php $productName = $units->first()->product->name ?? __('Produk'); @endphp
                            <div class="mb-1">
                                <span class="badge bg-primary-subtle text-primary mb-2 fw-bold" style="font-size: 0.85rem;">{{ $productName }}</span>
                                <div class="d-flex flex-column gap-2">
                                    @foreach($units as $unit)
                                    <div class="{{ $unit->is_replaced ? 'p-2.5 rounded-4 border border-info bg-info-subtle bg-opacity-10' : '' }}">
                                        <div class="d-flex flex-wrap align-items-center gap-1.5 mb-1">
                                            @if($unit->vcc_service)
                                                <span class="badge bg-primary-subtle text-primary fw-bold">
                                                    <i class="fas fa-credit-card me-1"></i>{{ __('VCC Layanan:') }} {{ $unit->vcc_service }}
                                                </span>
                                            @elseif($units->count() > 1)
                                                <span class="badge bg-secondary-subtle text-secondary">{{ __('Unit') }} #{{ $loop->iteration }}</span>
                                            @endif
                                            @if($unit->is_replaced)
                                                <span class="badge bg-info text-dark rounded-pill px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;">
                                                    <i class="fas fa-sync-alt me-1"></i>{{ __('Akun Pengganti Baru (Diganti Admin)') }}
                                                </span>
                                                @if($unit->replaced_at)
                                                    <span class="text-muted" style="font-size: 0.72rem;">
                                                        <i class="far fa-clock me-1"></i>{{ $unit->replaced_at->format('d M Y, H:i') }}
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                        <x-account-credential-viewer :rawText="$unit->raw_text" />
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @endif

                    @if($order->vpnAccounts && $order->vpnAccounts->count() > 0)
                    <hr>
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <h6 class="fw-bold mb-0 text-body"><i class="fas fa-network-wired text-primary me-2"></i>{{ __('Konfigurasi Akun VPN') }}</h6>
                        <a href="{{ route('vpn.my-accounts') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                            <i class="fas fa-sliders me-1"></i>{{ __('Kelola di Akun VPN Saya') }}
                        </a>
                    </div>
                    @php $orderDefaultAdminId = \App\Models\User::where('role', 'admin')->value('id'); @endphp
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
                        <div class="card mb-3 overflow-hidden">
                            <div class="card-header bg-primary-subtle border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <h6 class="mb-0 fw-bold text-primary">{{ __('Protokol:') }} {{ strtoupper($vpn->protocol) }} ({{ $vpn->username }})</h6>
                                    <span class="badge {{ $vpn->status === 'active' ? 'bg-success text-white' : ($vpn->status === 'suspended' ? 'bg-warning text-dark' : 'bg-danger text-white') }} rounded-pill px-2.5">{{ ucfirst($vpn->status) }}</span>
                                </div>
                                @if($vpn->protocol !== 'ssh' && !$vpn->isAdminSuspended())
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
                            <div class="card-body">
                                <div class="row g-2 mb-2 small">
                                    <div class="col-6 col-sm-3">
                                        <span class="text-muted d-block">{{ __('Server / Domain:') }}</span>
                                        <strong class="text-body font-monospace text-break">{{ $vpn->display_domain }}</strong>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <span class="text-muted d-block">{{ __('Masa Aktif:') }}</span>
                                        <strong class="text-body">{{ $vpn->expired_at ? $vpn->expired_at->format('d M Y') : '-' }}</strong>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <span class="text-muted d-block">{{ __('Kuota / Limit IP:') }}</span>
                                        <strong class="text-body">{{ ($vpn->quota_gb ?? 0) > 0 ? $vpn->quota_gb . ' GB' : 'Unlimited' }} &bull; {{ max(1, (int) ($vpn->ip_limit ?? 1)) }} IP</strong>
                                    </div>
                                    <div class="col-6 col-sm-3">
                                        <span class="text-muted d-block">{{ __('Preset SNI:') }}</span>
                                        <strong class="text-primary">{{ $vpn->protocol === 'ssh' ? 'SSH' : $vpnSniLabel }}</strong>
                                    </div>
                                </div>

                                @if($vpn->isAdminSuspended())
                                    <div class="alert alert-danger border-0 py-2 px-3 small mb-2" style="border-radius: 12px;">
                                        <div class="fw-bold"><i class="fas fa-lock me-1"></i>{{ __('Akun ini disuspend oleh Admin.') }}</div>
                                        @if($vpn->suspended_reason)<div>{{ __('Alasan:') }} {{ $vpn->suspended_reason }}</div>@endif
                                        @if($vpn->suspended_by_user_id ?: $orderDefaultAdminId)
                                        <a href="{{ route('chat.index', ['contact_id' => $vpn->suspended_by_user_id ?: $orderDefaultAdminId]) }}" class="btn btn-danger btn-sm rounded-pill mt-2 px-3 fw-semibold">
                                            <i class="fas fa-comments me-1"></i>{{ __('Minta Buka Suspend via Chat Admin') }}
                                        </a>
                                        @endif
                                    </div>
                                @endif

                                @if($vpn->protocol === 'ssh')
                                    @php
                                        $sshInfo = is_array($vpn->config_payload['ssh_info'] ?? null) ? $vpn->config_payload['ssh_info'] : [];
                                        $sshHost = $sshInfo['host'] ?? $vpn->display_domain ?? '-';
                                        $sshUser = $sshInfo['username'] ?? $vpn->username;
                                        $sshPass = $sshInfo['password'] ?? $vpn->password ?? '';
                                        $sshPorts = is_array($sshInfo['ports'] ?? null) ? $sshInfo['ports'] : [
                                            'openssh' => 22,
                                            'ws_tls' => 443,
                                            'ws_ntls' => 80,
                                            'dropbear' => '109, 143',
                                            'udp_custom' => '1-65535',
                                        ];
                                        $sshPayload = $sshInfo['ws_payload'] ?? "GET / HTTP/1.1[crlf]Host: {$sshHost}[crlf]Upgrade: websocket[crlf][crlf]";
                                        $sshHcTls = $sshPass !== '' ? "{$sshHost}:443@{$sshUser}:{$sshPass}" : "{$sshHost}:443@{$sshUser}";
                                    @endphp
                                    <div class="rounded-4 border overflow-hidden mb-3" style="background: var(--surface-container-low);">
                                        <div class="d-flex align-items-center justify-content-between gap-2 py-2 px-3 border-bottom">
                                            <div class="min-w-0">
                                                <div class="text-muted" style="font-size: 0.7rem;">{{ __('Host / Domain') }}</div>
                                                <div class="font-monospace fw-semibold text-body small text-break">{{ $sshHost }}</div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 flex-shrink-0" onclick="navigator.clipboard?.writeText(@js($sshHost)); this.innerHTML='<i class=\'fas fa-check text-success\'></i>';"><i class="fas fa-copy"></i></button>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between gap-2 py-2 px-3 border-bottom">
                                            <div class="min-w-0">
                                                <div class="text-muted" style="font-size: 0.7rem;">{{ __('Username') }}</div>
                                                <div class="font-monospace fw-semibold text-body small text-break">{{ $sshUser }}</div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 flex-shrink-0" onclick="navigator.clipboard?.writeText(@js($sshUser)); this.innerHTML='<i class=\'fas fa-check text-success\'></i>';"><i class="fas fa-copy"></i></button>
                                        </div>
                                        @if($sshPass !== '')
                                        <div class="d-flex align-items-center justify-content-between gap-2 py-2 px-3 border-bottom">
                                            <div class="min-w-0">
                                                <div class="text-muted" style="font-size: 0.7rem;">{{ __('Password') }}</div>
                                                <div class="font-monospace fw-semibold text-body small text-break">{{ $sshPass }}</div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 flex-shrink-0" onclick="navigator.clipboard?.writeText(@js($sshPass)); this.innerHTML='<i class=\'fas fa-check text-success\'></i>';"><i class="fas fa-copy"></i></button>
                                        </div>
                                        @endif
                                        <div class="d-flex align-items-center justify-content-between gap-2 py-2 px-3">
                                            <div class="min-w-0">
                                                <div class="text-muted" style="font-size: 0.7rem;">{{ __('Format Cepat (WS TLS 443)') }}</div>
                                                <div class="font-monospace fw-semibold text-body small text-break">{{ $sshHcTls }}</div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 flex-shrink-0" onclick="navigator.clipboard?.writeText(@js($sshHcTls)); this.innerHTML='<i class=\'fas fa-check text-success\'></i>';"><i class="fas fa-copy"></i></button>
                                        </div>
                                    </div>
                                    <div class="row g-1.5 mb-3">
                                        @foreach(['OpenSSH' => ($sshPorts['openssh'] ?? 22), 'WS TLS' => ($sshPorts['ws_tls'] ?? 443), 'WS Non-TLS' => ($sshPorts['ws_ntls'] ?? 80), 'Dropbear' => ($sshPorts['dropbear'] ?? '109, 143'), 'UDP Custom' => ($sshPorts['udp_custom'] ?? '1-65535')] as $pLabel => $pVal)
                                        <div class="col-6 col-sm-4">
                                            <div class="p-2 rounded-3 border d-flex align-items-center justify-content-between gap-1" style="background: var(--surface-container-lowest);">
                                                <span class="text-muted" style="font-size: 0.72rem;">{{ $pLabel }}</span>
                                                <span class="font-monospace fw-bold text-body" style="font-size: 0.78rem;">{{ $pVal }}</span>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    <div class="p-2.5 rounded-4 border mb-2" style="background: var(--surface-container-low);">
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1.5">
                                            <span class="small fw-bold text-muted"><i class="fas fa-bolt text-warning me-1"></i>{{ __('Payload WebSocket (WS)') }}</span>
                                            <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 py-0.5 small fw-semibold" onclick="navigator.clipboard?.writeText(@js($sshPayload)); this.innerHTML='<i class=\'fas fa-check text-success\'></i>';">
                                                <i class="fas fa-copy me-1"></i>{{ __('Salin Payload') }}
                                            </button>
                                        </div>
                                        <div class="font-monospace small p-2 rounded-3 bg-white border text-break" style="font-size: 0.76rem;">{{ $sshPayload }}</div>
                                    </div>
                                    <details class="small">
                                        <summary class="text-muted fw-semibold" style="cursor: pointer;">{{ __('Lihat Teks Mentah') }}</summary>
                                        <div class="m3-tonal-item p-3 mt-2 text-break font-monospace" style="max-height: 220px; overflow-y: auto; white-space: pre-wrap; font-size: 0.8rem;">{{ $vpn->config_link ?: __('Tidak ada konfigurasi ditemukan.') }}</div>
                                    </details>
                                @else
                                    @if(!empty($vpnLinks))
                                        <div class="d-flex flex-column gap-1.5 mb-2">
                                            @foreach($vpnLinks as $linkType => $linkVal)
                                                @if($linkVal)
                                                <div class="d-flex align-items-center gap-2 p-2 rounded-3 border">
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

                                    <hr>
                                    <span class="text-muted small d-block mb-2">{{ __('Konfigurasi Output / Link:') }}</span>
                                    <div class="m3-tonal-item p-3 text-break font-monospace" style="max-height: 250px; overflow-y: auto; white-space: pre-wrap; font-size: 0.85rem;">{{ $vpn->config_link ?: __('Tidak ada konfigurasi ditemukan.') }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @endif
                @endif

                {{-- Modul Komplain / Garansi (Multi-Item & Multi-Seller Aware) --}}
                @if($order->status === 'delivered')
                <hr>
                <div class="mt-4 d-flex flex-column gap-3">
                    @if($orderComplaints->isNotEmpty())
                        @foreach($orderComplaints as $cmp)
                        @php
                            $cmpProduct = $cmp->resolveTargetProduct();
                            $cmpSeller = $cmp->resolveTargetSeller();
                            $statusBadge = match($cmp->status) {
                                'new' => ['bg' => 'danger-subtle', 'text' => 'danger', 'label' => __('Baru')],
                                'review' => ['bg' => 'warning-subtle', 'text' => 'warning', 'label' => __('Ditinjau')],
                                'done' => ['bg' => 'success-subtle', 'text' => 'success', 'label' => __('Selesai')],
                                'rejected' => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => __('Ditolak')],
                                'refund_requested' => ['bg' => 'info-subtle', 'text' => 'info', 'label' => __('Proses Refund')],
                                default => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => $cmp->status]
                            };
                        @endphp
                        <div class="m3-tonal-item p-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <h6 class="fw-bold mb-0 text-body">
                                    <i class="fas fa-toolbox text-warning me-2"></i>
                                    {{ __('Tiket Komplain & Garansi') }}
                                </h6>
                                <span class="badge bg-{{ $statusBadge['bg'] }} text-{{ $statusBadge['text'] }} rounded-pill px-3 py-1.5 small">
                                    {{ $statusBadge['label'] }}
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-2 small">
                                <span class="fw-bold text-secondary font-monospace">#{{ $cmp->complaint_ref }}</span>
                                @if($cmpProduct)
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1">{{ $cmpProduct->name }}</span>
                                @endif
                                @if($cmpSeller)
                                    <span class="badge bg-secondary-subtle text-body rounded-pill px-2.5 py-1">
                                        <i class="fas fa-store me-1"></i>{{ $cmpSeller->display_name }}
                                    </span>
                                @endif
                            </div>
                            <div class="mb-2">
                                <span class="text-muted small d-block">{{ __('Detail Masalah:') }}</span>
                                <p class="mb-0 text-body small bg-body p-2.5 rounded-3 border" style="border-color: var(--outline-color) !important;">{{ $cmp->complaint_text }}</p>
                            </div>
                            @if($cmp->status === 'rejected' && $cmp->rejected_reason)
                            <div class="alert alert-danger mb-0 py-2.5 px-3 rounded-3 mt-2">
                                <h6 class="fw-bold mb-1 small"><i class="fas fa-times-circle me-1"></i>{{ __('Alasan Penolakan Admin:') }}</h6>
                                <p class="mb-0 small">{{ $cmp->rejected_reason }}</p>
                            </div>
                            @endif
                            @if($cmp->status === 'done' && $cmp->refund_note)
                            <div class="alert alert-success mb-0 py-2.5 px-3 rounded-3 mt-2">
                                <h6 class="fw-bold mb-1 small"><i class="fas fa-check-circle me-1"></i>{{ __('Catatan Resolusi Admin:') }}</h6>
                                <p class="mb-0 small" style="white-space: pre-line;">{{ $cmp->refund_note }}</p>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    @endif

                    @if($claimableItems->isNotEmpty())
                    <div class="d-flex align-items-center justify-content-between m3-tonal-item p-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-1 text-body"><i class="fas fa-shield-alt text-primary me-2"></i>{{ __('Garansi Toko Aktif') }}</h6>
                            @if($order->warranty_expires_at)
                            <p class="text-muted small mb-0">{{ __('Batas akhir (terpanjang):') }} <strong>{{ $order->warranty_expires_at->format('d M Y H:i') }}</strong></p>
                            @endif
                            <p class="text-muted small mb-0 mt-1">{{ __('Ada akun digital yang bermasalah? Pilih produk spesifik dan ajukan klaim garansi langsung ke penjual terkait.') }}</p>
                        </div>
                        <button type="button" class="btn btn-warning rounded-pill px-4 fw-semibold" onclick="openComplaintModalForItem({{ $claimableItems->first()->id }})">
                            <i class="fas fa-toolbox me-2"></i>{{ __('Ajukan Komplain') }}
                        </button>
                    </div>
                    @elseif($orderComplaints->isEmpty())
                    <div class="d-flex align-items-center justify-content-between m3-tonal-item p-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-1 text-secondary"><i class="fas fa-shield-alt me-2 text-secondary"></i>{{ __('Garansi Toko Kedaluwarsa') }}</h6>
                            @if($order->warranty_expires_at)
                            <p class="text-muted small mb-0">{{ __('Garansi berakhir pada:') }} <strong>{{ $order->warranty_expires_at->format('d M Y H:i') }}</strong></p>
                            @else
                            <p class="text-muted small mb-0">{{ __('Produk dalam pesanan ini tidak memiliki garansi atau pesanan belum selesai.') }}</p>
                            @endif
                        </div>
                        <button type="button" class="btn btn-secondary rounded-pill px-4" disabled>
                            <i class="fas fa-toolbox me-2"></i>{{ __('Klaim Berakhir') }}
                        </button>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                @php
                    $displaySubtotal = ($order->subtotal > 0)
                        ? (int) $order->subtotal
                        : (int) $order->items->sum(fn($i) => $i->unit_price * $i->quantity);
                    $hasVoucher = ($order->discount_amount > 0) || !empty($order->coupon_code);
                @endphp
                <h6 class="fw-bold mb-3 text-body">{{ __('Ringkasan Pembayaran') }}</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ __('Subtotal') }}</span>
                    <span class="text-body fw-semibold">Rp {{ number_format($displaySubtotal, 0, ',', '.') }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ __('Jumlah Item') }}</span>
                    <span class="text-body">× {{ $order->quantity }}</span>
                </div>
                @if($hasVoucher)
                <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                    <span class="text-muted d-inline-flex align-items-center flex-wrap gap-1.5">
                        <span>{{ __('Diskon Voucher') }}</span>
                        @if($order->coupon_code)
                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 font-monospace" style="font-size: 0.72rem;">
                            <i class="fas fa-tag me-1"></i>{{ $order->coupon_code }}
                        </span>
                        @endif
                    </span>
                    <span class="text-success fw-semibold text-nowrap">- Rp {{ number_format((int) $order->discount_amount, 0, ',', '.') }}</span>
                </div>
                @endif
                @if($order->unique_code > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ __('Kode Unik') }}</span>
                    <span class="text-body">+ Rp {{ number_format((int) $order->unique_code, 0, ',', '.') }}</span>
                </div>
                @endif
                <hr>
                <div class="d-flex justify-content-between align-items-baseline">
                    <span class="fw-bold text-body">{{ __('Total Pembayaran') }}</span>
                    <span class="fw-bold product-price">{{ $order->formatted_total }}</span>
                </div>
            </div>
        </div>

        <div class="mt-3">
            @php
                $contactableSellers = $order->getDistinctSellers()
                    ->filter(fn ($entry) => $entry['seller']->id !== Auth::id())
                    ->values();
            @endphp
            @if($contactableSellers->count() === 1)
                @php $singleSeller = $contactableSellers->first()['seller']; @endphp
                <a href="{{ route('chat.index', ['contact_id' => $singleSeller->id]) }}" class="btn btn-outline-primary w-100 rounded-pill mb-2">
                    <i class="fas fa-comments me-2"></i>{{ __('Chat dengan :name', ['name' => $singleSeller->display_name]) }}
                </a>
            @elseif($contactableSellers->count() > 1)
                <div class="card mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold small text-body mb-2">
                            <i class="fas fa-comments text-primary me-1.5"></i>{{ __('Hubungi Penjual (:count Toko)', ['count' => $contactableSellers->count()]) }}
                        </h6>
                        <div class="d-flex flex-column gap-2">
                            @foreach($contactableSellers as $entry)
                                @php
                                    $entrySeller = $entry['seller'];
                                    $entryItemNames = $entry['items']->map(fn ($it) => $it->product->name ?? '-')->implode(', ');
                                @endphp
                                <div class="d-flex align-items-center justify-content-between gap-2 p-2 rounded-3 border" style="background: var(--surface-container, var(--bs-tertiary-bg)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
                                    <div class="min-w-0">
                                        <div class="fw-semibold small text-body text-truncate">
                                            <i class="fas fa-store text-primary me-1"></i>{{ $entrySeller->display_name }}
                                        </div>
                                        <div class="text-muted text-truncate" style="font-size: 0.72rem;" title="{{ $entryItemNames }}">
                                            {{ $entryItemNames }}
                                        </div>
                                    </div>
                                    <a href="{{ route('chat.index', ['contact_id' => $entrySeller->id]) }}"
                                       class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-semibold flex-shrink-0"
                                       style="font-size: 0.76rem;">
                                        <i class="fas fa-comments me-1"></i>{{ __('Chat') }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary w-100 rounded-pill mb-2">
                <i class="fas fa-arrow-left me-2"></i>{{ __('Kembali ke Riwayat') }}
            </a>
            @if($order->status === 'pending_payment')
            <a href="{{ route('checkout.success', $order->order_ref) }}" class="btn btn-success w-100 rounded-pill mb-2">
                <i class="fas fa-qrcode me-2"></i>{{ __('Bayar Sekarang (QRIS)') }}
            </a>
            <form action="{{ route('orders.cancel', $order->id) }}" method="POST" onsubmit="confirmAction(event, '{{ __('Apakah Anda yakin ingin membatalkan pesanan ini?') }}');">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100 rounded-pill">
                    <i class="fas fa-times-circle me-2"></i>{{ __('Batalkan Pesanan') }}
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

@if($order->status === 'pending_payment')
@section('mobile_bottom_action_bar')
{{-- Mobile Sticky Bottom Bar for Pending Payment (Stacked above Bottom Nav) --}}
<div class="mobile-sticky-action-bar d-lg-none">
    <div class="d-flex align-items-center justify-content-between gap-2">
        <div>
            <span class="text-muted d-block" style="font-size: 0.68rem; line-height: 1;">{{ __('Total Tagihan') }}</span>
            <span class="fw-bold text-primary" style="font-size: 1.05rem;">{{ $order->formatted_total }}</span>
        </div>
        <a href="{{ route('checkout.success', $order->order_ref) }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 fw-bold shadow-sm d-flex align-items-center gap-1" style="font-size: 0.82rem;">
            <i class="fas fa-qrcode"></i> {{ __('Bayar (QRIS)') }}
        </a>
    </div>
</div>
@endsection
@endif

@if($order->status === 'delivered' && isset($claimableItems) && $claimableItems->isNotEmpty())
@push('modals')
<div class="modal fade" id="complaintModal" tabindex="-1" aria-labelledby="complaintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold" id="complaintModalLabel"><i class="fas fa-toolbox text-warning me-2"></i>{{ __('Ajukan Klaim Garansi') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('orders.complaint', $order->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 small border-0 mb-3" style="border-radius: var(--radius-md, 16px);">
                        <i class="fas fa-info-circle me-1"></i> <b>{{ __('Kebijakan Garansi:') }}</b> {{ __('Pilih produk yang bermasalah lalu jelaskan kendala secara rinci. Tiket akan diteruskan langsung ke penjual produk tersebut.') }}
                    </div>
                    <div class="mb-3">
                        <label for="complaint_order_item_id" class="form-label text-muted small fw-bold">{{ __('Produk yang Dikomplain') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="complaint_order_item_id" name="order_item_id" required>
                            @foreach($claimableItems as $cItem)
                                <option value="{{ $cItem->id }}">
                                    {{ $cItem->product->name ?? '-' }} ({{ $cItem->quantity }}×) — {{ $cItem->product?->creator?->display_name ?? __('Penjual') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="complaint_text" class="form-label text-muted small fw-bold">{{ __('Deskripsi Masalah / Keluhan') }}</label>
                        <textarea class="form-control" id="complaint_text" name="complaint_text" rows="4" required minlength="10" maxlength="1000" placeholder="{{ __('Tuliskan keluhan Anda di sini (minimal 10 karakter)...') }}"></textarea>
                    </div>
                    <div class="mb-0">
                        <label for="attachment" class="form-label text-muted small fw-bold">{{ __('Unggah Foto Bukti (Opsional)') }}</label>
                        <input class="form-control" type="file" id="attachment" name="attachment" accept="image/*">
                        <div class="form-text">{{ __('Hanya menerima format gambar (jpg, png). Maksimal 10MB.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4">{{ __('Kirim Pengaduan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush
@endif

@if($order->status === 'delivered' && $order->vpnAccounts && $order->vpnAccounts->count() > 0)
@push('modals')
<div class="modal fade" id="orderVpnQrModal" tabindex="-1" aria-hidden="true">
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
@endpush
@endif

@push('scripts')
<script>
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

    function openComplaintModalForItem(orderItemId) {
        const select = document.getElementById('complaint_order_item_id');
        if (select && orderItemId) {
            select.value = String(orderItemId);
        }
        const modalEl = document.getElementById('complaintModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
    function highlightStars(orderId, productId, rating) {
        for (let i = 1; i <= 5; i++) {
            const starIcon = document.getElementById(`icon-${orderId}-${productId}-${i}`);
            if (starIcon) {
                if (i <= rating) {
                    starIcon.classList.remove('far');
                    starIcon.classList.add('fas');
                } else {
                    starIcon.classList.remove('fas');
                    starIcon.classList.add('far');
                }
            }
        }
        const radioButton = document.getElementById(`star-${orderId}-${productId}-${rating}`);
        if (radioButton) {
            radioButton.checked = true;
        }
    }
</script>
@endpush
@endsection
