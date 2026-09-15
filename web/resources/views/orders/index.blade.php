@extends('layouts.app')

@section('title', __('Riwayat Pesanan'))
@section('page_subtitle', __('Pesanan'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('dashboard') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Home') }}">
            <i class="fas fa-arrow-left text-body"></i>
        </a>
        <div>
            <h5 class="fw-bold m-0 text-body" style="font-size: 1.15rem;">{{ __('Riwayat Pesanan') }}</h5>
            <small class="text-muted" style="font-size: 0.75rem;">{{ __('Daftar seluruh transaksi dan status pesanan') }}</small>
        </div>
    </div>
    <a href="{{ route('catalog.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold d-flex align-items-center gap-1.5" style="font-size: 0.8rem;">
        <i class="fas fa-shopping-bag"></i>
        <span>{{ __('Beli Lagi') }}</span>
    </a>
</div>


{{-- Status Filter --}}
<div class="mb-4 d-flex flex-wrap gap-2">
    <a href="{{ route('orders.index') }}"
       class="btn btn-sm rounded-pill px-3 {{ is_null($status) ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ __('Semua') }}
    </a>
    @foreach(['pending_payment' => __('Menunggu'), 'paid' => __('Dibayar'), 'delivered' => __('Selesai'), 'cancelled' => __('Dibatalkan'), 'expired' => __('Kedaluwarsa')] as $key => $label)
    <a href="{{ route('orders.index', ['status' => $key]) }}"
       class="btn btn-sm rounded-pill px-3 {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0" id="ordersMainCardBody">
        {{-- Skeleton Shimmer Placeholder for Order Transitions --}}
        <div class="d-none" id="ordersSkeletonContainer">
            {{-- Mobile Skeleton Cards --}}
            <div class="d-md-none p-3">
                @for($sk = 0; $sk < 3; $sk++)
                <div class="card border-0 shadow-sm p-3 mb-2.5" style="border-radius: 14px;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="skeleton-shimmer" style="height: 14px; width: 90px;"></div>
                        <div class="skeleton-shimmer rounded-pill" style="height: 22px; width: 70px;"></div>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="skeleton-shimmer rounded-3" style="width: 40px; height: 40px; flex-shrink: 0;"></div>
                        <div class="flex-grow-1">
                            <div class="skeleton-shimmer mb-1.5" style="height: 14px; width: 70%;"></div>
                            <div class="skeleton-shimmer" style="height: 11px; width: 35%;"></div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <div class="skeleton-shimmer" style="height: 16px; width: 85px;"></div>
                        <div class="skeleton-shimmer rounded-pill" style="height: 26px; width: 65px;"></div>
                    </div>
                </div>
                @endfor
            </div>

            {{-- Desktop Skeleton Rows --}}
            <div class="d-none d-md-block p-4">
                @for($st = 0; $st < 4; $st++)
                <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                    <div class="skeleton-shimmer" style="height: 16px; width: 110px;"></div>
                    <div class="skeleton-shimmer" style="height: 16px; width: 180px;"></div>
                    <div class="skeleton-shimmer" style="height: 16px; width: 35px;"></div>
                    <div class="skeleton-shimmer" style="height: 16px; width: 95px;"></div>
                    <div class="skeleton-shimmer rounded-pill" style="height: 24px; width: 75px;"></div>
                    <div class="skeleton-shimmer" style="height: 14px; width: 90px;"></div>
                    <div class="skeleton-shimmer rounded-pill" style="height: 28px; width: 65px;"></div>
                </div>
                @endfor
            </div>
        </div>

        <div id="ordersContentWrapper">
        @if($orders->count() > 0)
        {{-- Modern E-Commerce Order Cards View --}}
        <div class="orders-list d-flex flex-column gap-3 p-3 p-md-4">
            @foreach($orders as $order)
            @php
                $product = $order->product;
                $seller = $product?->creator;
                $sellerId = $seller ? $seller->id : null;
                $sellerName = $seller ? ($seller->full_name ?? $seller->username ?? 'Official Store') : 'Official Store';
            @endphp
            <div class="card border border-subtle shadow-sm rounded-4 overflow-hidden order-transaction-card lift-hover">
                {{-- Card Header: Toko, Reference, Tanggal, Status --}}
                <div class="card-header bg-light bg-opacity-50 border-bottom px-3 px-md-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
                        @if($sellerId)
                        <a href="{{ route('sellers.show', $sellerId) }}" class="fw-bold text-dark text-decoration-none hover-primary d-inline-flex align-items-center gap-1.5" title="{{ __('Kunjungi Toko') }}">
                            <i class="fas fa-store text-primary"></i>
                            <span>{{ $sellerName }}</span>
                            <i class="fas fa-check-circle text-primary small" title="{{ __('Seller Terverifikasi') }}"></i>
                        </a>
                        @else
                        <span class="fw-bold text-dark d-inline-flex align-items-center gap-1.5">
                            <i class="fas fa-store text-primary"></i>
                            <span>{{ $sellerName }}</span>
                        </span>
                        @endif

                        <span class="text-muted d-none d-sm-inline">|</span>

                        <span class="text-muted small font-monospace">
                            #{{ $order->reference }}
                        </span>

                        <span class="text-muted d-none d-md-inline">&bull;</span>

                        <span class="text-muted small d-none d-md-inline">
                            {{ $order->created_at->format('d M Y, H:i') }}
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-1.5 fw-bold" style="font-size: 0.78rem;">
                            @if($order->status === 'delivered')
                                <i class="fas fa-check-circle me-1"></i>
                            @elseif($order->status === 'pending_payment')
                                <i class="fas fa-clock me-1"></i>
                            @elseif($order->status === 'cancelled' || $order->status === 'expired')
                                <i class="fas fa-times-circle me-1"></i>
                            @endif
                            {{ $order->status_label }}
                        </span>
                    </div>
                </div>

                {{-- Card Body: Item Details, Thumbnail, Qty, Price --}}
                <div class="card-body p-3 p-md-4">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-md-8">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 bg-light border p-2 d-flex align-items-center justify-content-center text-primary flex-shrink-0" style="width: 58px; height: 58px;">
                                    @if($product && $product->image_url)
                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="img-fluid rounded-2" style="max-height: 44px; object-fit: contain;">
                                    @else
                                        <i class="fas fa-box-open fa-2x opacity-75"></i>
                                    @endif
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <h6 class="fw-bold text-dark mb-1 text-truncate" title="{{ $product->name ?? '-' }}">
                                        @if($product)
                                        <a href="{{ route('catalog.show', $product->id) }}" class="text-dark text-decoration-none hover-primary">
                                            {{ $product->name }}
                                        </a>
                                        @else
                                        <span>{{ __('Produk Tidak Tersedia') }}</span>
                                        @endif
                                    </h6>
                                    <div class="text-muted small d-flex flex-wrap align-items-center gap-2">
                                        <span>{{ $order->quantity }} unit &times; {{ $order->product ? 'Rp ' . number_format($order->product->price, 0, ',', '.') : '-' }}</span>
                                        @if($product && $product->warranty_days)
                                            <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                                <i class="fas fa-shield-alt me-1"></i>Garansi {{ $product->warranty_days }} Hari
                                            </span>
                                        @endif
                                        @if($order->coupon_code)
                                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5" style="font-size: 0.68rem;">
                                                <i class="fas fa-tag me-1"></i>Kupon {{ $order->coupon_code }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4 text-md-end border-top border-md-top-0 pt-2 pt-md-0">
                            <div class="text-muted small mb-0.5">{{ __('Total Belanja') }}</div>
                            <div class="fw-bold text-primary fs-5">{{ $order->formatted_total }}</div>
                        </div>
                    </div>
                </div>

                {{-- Card Footer: Contextual Quick Actions --}}
                <div class="card-footer bg-white border-top px-3 px-md-4 py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="text-muted small">
                        @if($order->status === 'pending_payment')
                            <span class="text-warning-emphasis"><i class="fas fa-info-circle me-1"></i>{{ __('Selesaikan pembayaran sebelum batas waktu.') }}</span>
                        @elseif($order->status === 'delivered')
                            <span class="text-success"><i class="fas fa-check-circle me-1"></i>{{ __('Pesanan telah terkirim secara otomatis.') }}</span>
                        @elseif($order->status === 'cancelled')
                            <span class="text-danger"><i class="fas fa-ban me-1"></i>{{ __('Pesanan telah dibatalkan.') }}</span>
                        @else
                            <span>{{ $order->created_at->format('d M Y, H:i') }}</span>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                        @if($order->status === 'pending_payment')
                            <form action="{{ route('orders.cancel', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1.5 fw-semibold">
                                    <i class="fas fa-times me-1"></i>{{ __('Batalkan') }}
                                </button>
                            </form>
                            <a href="{{ route('checkout.success', ['order_ref' => $order->order_ref]) }}" class="btn btn-sm btn-success rounded-pill px-4 py-1.5 fw-bold shadow-sm">
                                <i class="fas fa-wallet me-1"></i>{{ __('Bayar Sekarang') }}
                            </a>
                        @elseif($order->status === 'delivered' || $order->status === 'paid')
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#detailOrderModal{{ $order->id }}">
                                <i class="fas fa-key me-1"></i>{{ __('Lihat Akun / Lisensi') }}
                            </button>
                            @if($product)
                            <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold">
                                <i class="fas fa-redo me-1"></i>{{ __('Beli Lagi') }}
                            </a>
                            @endif
                        @elseif($order->status === 'cancelled' || $order->status === 'expired')
                            @if($product)
                            <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold">
                                <i class="fas fa-redo me-1"></i>{{ __('Beli Lagi') }}
                            </a>
                            @endif
                        @endif

                        <button type="button" class="btn btn-sm btn-light text-secondary rounded-circle border d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px;" data-bs-toggle="modal" data-bs-target="#detailOrderModal{{ $order->id }}" title="{{ __('Detail Transaksi') }}">
                            <i class="fas fa-chevron-right small"></i>
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="px-4 py-3 border-top">
            {{ $orders->withQueryString()->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-box-open text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-3">
                @if($status)
                    {{ __('Tidak ada pesanan dengan status ":status".', ['status' => $status]) }}
                @else
                    {{ __('Belum ada pesanan.') }}
                @endif
            </p>
            <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-store me-2"></i>{{ __('Mulai Belanja') }}
            </a>
        </div>
        @endif
        </div>{{-- /#ordersContentWrapper --}}
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterLinks = document.querySelectorAll('.category-chips-wrapper a, a[href*="status="], a[href="{{ route('orders.index') }}"]');
        const skeleton = document.getElementById('ordersSkeletonContainer');
        const content = document.getElementById('ordersContentWrapper');

        filterLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (e.ctrlKey || e.metaKey || e.shiftKey) return;
                if (skeleton && content) {
                    skeleton.classList.remove('d-none');
                    content.classList.add('d-none');
                }
            });
        });
    });
</script>
@endpush

@push('modals')
@foreach($orders as $order)
{{-- Detail Order Modal --}}
<div class="modal fade" id="detailOrderModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Detail Pesanan #:ref', ['ref' => $order->reference]) }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">{{ __('Informasi Pesanan') }}</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted" style="width: 120px;">{{ __('Produk') }}</td><td class="fw-bold">{{ $order->product->name ?? '-' }}</td></tr>
                            <tr><td class="text-muted">Kuantitas (QTY)</td><td>{{ $order->quantity }} unit</td></tr>
                            <tr><td class="text-muted">{{ __('Status') }}</td>
                                <td>
                                    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3">
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                            </tr>
                            <tr><td class="text-muted">{{ __('Tanggal Transaksi') }}</td><td>{{ $order->created_at->format('d M Y H:i:s') }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">{{ __('Rincian Pembayaran') }}</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted" style="width: 120px;">{{ __('Subtotal') }}</td><td>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td></tr>
                            <tr><td class="text-muted">{{ __('Kode Unik') }}</td><td>Rp {{ $order->unique_code }}</td></tr>
                            <tr><td class="text-muted">{{ __('Total Bayar') }}</td><td class="fw-bold text-primary">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td></tr>
                        </table>
                    </div>

                    @if($order->status === 'delivered' && $order->stockUnits && $order->stockUnits->count() > 0)
                    <div class="col-12 mt-2">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3"><i class="fas fa-key text-success me-2"></i>{{ __('Data Akun yang Dikirim (:count unit)', ['count' => $order->stockUnits->count()]) }}</h6>
                        <div class="d-flex flex-column gap-3">
                            @foreach($order->stockUnits as $unit)
                                <div>
                                    @if($order->stockUnits->count() > 1)
                                        <span class="badge bg-secondary-subtle text-secondary mb-1">Unit #{{ $loop->iteration }}</span>
                                    @endif
                                    <x-account-credential-viewer :rawText="$unit->raw_text" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="col-12 mt-2">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">{{ __('Timeline Transaksi') }}</h6>
                        <div class="d-flex flex-wrap gap-3 small">
                            <div><span class="text-muted">{{ __('Dibuat:') }}</span> <br><b>{{ $order->created_at->format('d M Y H:i:s') }}</b></div>
                            @if($order->paid_at)
                            <div><span class="text-muted">{{ __('Dibayar:') }}</span> <br><b class="text-success">{{ \Carbon\Carbon::parse($order->paid_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                            @if($order->delivered_at)
                            <div><span class="text-muted">{{ __('Dikirim:') }}</span> <br><b class="text-primary">{{ \Carbon\Carbon::parse($order->delivered_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                            @if($order->cancelled_at)
                            <div><span class="text-muted">{{ __('Dibatalkan:') }}</span> <br><b class="text-danger">{{ \Carbon\Carbon::parse($order->cancelled_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                        </div>
                        @if($order->cancel_reason)
                        <div class="alert alert-danger mt-3 small mb-0">
                            <b>{{ __('Alasan Batal:') }}</b> {{ $order->cancel_reason }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                @if($order->status === 'pending_payment')
                <a href="{{ route('checkout.success', ['order_ref' => $order->order_ref]) }}" class="btn btn-success rounded-pill px-4">
                    <i class="fas fa-qrcode me-1"></i>Bayar Sekarang (QRIS)
                </a>
                <form action="{{ route('orders.cancel', $order->id) }}" method="POST" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini?');" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger rounded-pill px-4">
                        <i class="fas fa-times-circle me-1"></i>{{ __('Batalkan Pesanan') }}
                    </button>
                </form>
                @endif
                <a href="{{ route('orders.show', $order->id) }}" class="btn btn-outline-primary rounded-pill px-4">
                    <i class="fas fa-external-link-alt me-1"></i>{{ __('Lihat Detail') }}
                </a>
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endpush
@endsection
