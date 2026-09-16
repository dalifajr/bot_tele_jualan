@extends('layouts.app')

@section('title', $product->name)
@section('page_subtitle', __('Detail Produk'))

@push('styles')
<style>
    .lift-hover {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .lift-hover:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
    }
    .btn-buy-now {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    .btn-buy-now:active:not(:disabled) {
        transform: scale(0.98);
    }
    .btn-buy-now.is-loading {
        opacity: 0.88;
        cursor: not-allowed !important;
        pointer-events: none;
    }
</style>
@endpush

@section('content')
<div class="product-detail-container pb-2">
    {{-- Top Navigation Back Bar (Mobile & Desktop Friendly) --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('catalog.index') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Katalog') }}">
                <i class="fas fa-arrow-left text-body"></i>
            </a>
            <div class="d-flex flex-column">
                <div class="text-muted small d-none d-sm-block">
                    <a href="{{ route('catalog.index') }}" class="text-decoration-none text-muted">{{ __('Katalog') }}</a> 
                    <i class="fas fa-chevron-right mx-1" style="font-size: 0.65rem;"></i> 
                    <span>{{ Str::limit($product->name, 25) }}</span>
                </div>
                <h5 class="fw-bold m-0 text-body" style="font-size: 1.1rem;">{{ __('Detail Produk') }}</h5>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if($product->stock_count > 0 && !$product->is_vpn)
            <a href="{{ route('cart.index') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center position-relative" style="width: 38px; height: 38px;" title="{{ __('Lihat Keranjang') }}">
                <i class="fas fa-shopping-cart text-primary"></i>
            </a>
            @endif
        </div>
    </div>

    <div class="row g-3 g-lg-4">
        {{-- Left / Top Column: Visual & Information --}}
        <div class="col-lg-7 col-xl-8">
            {{-- Product Hero Card --}}
            <div class="card border-0 shadow-sm overflow-hidden mb-3" style="border-radius: 20px;">
                {{-- Product Visual Banner / Image --}}
                @if($product->image_url)
                <div class="position-relative w-100 overflow-hidden text-center bg-dark" style="min-height: 260px; max-height: 380px; display: flex; align-items: center; justify-content: center;">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-100 h-100" style="object-fit: cover; max-height: 380px;">
                    <div class="position-absolute bottom-0 start-0 end-0 p-2.5 text-start" style="background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, transparent 100%);">
                        <span class="badge bg-dark bg-opacity-75 text-white rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">SKU #{{ $product->id }}</span>
                    </div>
                @else
                <div class="position-relative d-flex align-items-center justify-content-center text-white overflow-hidden" style="min-height: 230px; background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 50%, #6610f2 100%);">
                    <div class="position-absolute w-100 h-100 opacity-10" style="background-image: radial-gradient(#ffffff 2px, transparent 2px); background-size: 24px 24px;"></div>
                    <div class="text-center p-3 position-relative z-1">
                        <div class="rounded-4 bg-white bg-opacity-20 d-inline-flex align-items-center justify-content-center p-3 mb-2 shadow-sm border border-white border-opacity-25" style="width: 88px; height: 88px; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                            @if($product->is_vpn)
                                <i class="fas fa-shield-halved" style="font-size: 2.8rem; color: #ffffff;"></i>
                            @else
                                <i class="fas fa-box-open" style="font-size: 2.8rem; color: #ffffff;"></i>
                            @endif
                        </div>
                        <div class="small fw-semibold text-white-50">PRODUK DIGITAL &bull; SKU #{{ $product->id }}</div>
                    </div>
                @endif

                    {{-- Badges on top corners --}}
                    <div class="position-absolute top-0 start-0 m-3 d-flex flex-wrap gap-1" style="z-index: 5;">
                        @if($product->is_vpn)
                            <span class="badge bg-dark bg-opacity-75 rounded-pill px-3 py-1.5 fw-semibold shadow-sm" style="font-size: 0.78rem;">
                                <i class="fas fa-network-wired me-1 text-info"></i>VPN ({{ strtoupper($product->vpn_protocol) }})
                            </span>
                        @endif
                    </div>

                    <div class="position-absolute top-0 end-0 m-3" style="z-index: 5;">
                        @if($stockCount > 0)
                            <span class="badge bg-success rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="font-size: 0.78rem;">
                                <i class="fas fa-check-circle me-1"></i>{{ $stockCount }} {{ __('Stok Siap') }}
                            </span>
                        @else
                            <span class="badge bg-danger rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="font-size: 0.78rem;">
                                <i class="fas fa-times-circle me-1"></i>{{ __('Stok Habis') }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Card Body: Pricing & Highlights --}}
                <div class="card-body p-3 p-md-4">
                    {{-- Price Tag ala E-Commerce --}}
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <span class="fw-bold text-primary" style="font-size: 1.85rem; line-height: 1.2;">{{ $product->formatted_price }}</span>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1 small fw-semibold">{{ __('Harga Terbaik') }}</span>
                    </div>

                    <h4 class="fw-bold text-body mb-2" style="line-height: 1.35;">{{ $product->name }}</h4>

                    {{-- Ratings & Sales Snapshot --}}
                    @php
                        $reviews = \App\Models\Review::with('user')->where('product_id', $product->id)->orderBy('created_at', 'desc')->get();
                        $avgRating = $reviews->avg('rating') ?: 5.0;
                    @endphp
                    <div class="d-flex flex-wrap align-items-center gap-3 pb-3 mb-3 border-bottom text-muted small">
                        <div class="d-flex align-items-center gap-1 text-warning fw-bold">
                            <i class="fas fa-star"></i>
                            <span class="text-body fw-semibold">{{ number_format($avgRating, 1) }}</span>
                            <span class="text-secondary fw-normal">({{ $reviews->count() }} {{ __('ulasan') }})</span>
                        </div>
                        <div class="vr opacity-25"></div>
                        <div>
                            <i class="fas fa-bolt text-warning me-1"></i>
                            <span class="fw-semibold text-body">{{ $product->sales_count ?? 0 }}</span> {{ __('Terjual') }}
                        </div>
                        <div class="vr opacity-25"></div>
                        <div class="text-success fw-semibold">
                            <i class="fas fa-truck-fast me-1"></i>{{ __('Pengiriman Otomatis') }}
                        </div>
                    </div>

                    {{-- Trust / Value Propositions Badges (Ala Home Stat Cards) --}}
                    <div class="row g-2 mb-4">
                        <div class="col-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100 lift-hover bg-body p-2.5 text-center">
                                <div class="d-inline-flex bg-warning-subtle text-warning rounded-circle p-2 mx-auto mb-1.5 align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="fas fa-bolt" style="font-size: 1rem;"></i>
                                </div>
                                <span class="fw-bold text-body d-block" style="font-size: 0.76rem;">{{ __('Instan Delivery') }}</span>
                                <span class="text-muted" style="font-size: 0.68rem;">{{ __('Akun langsung dikirim') }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100 lift-hover bg-body p-2.5 text-center">
                                <div class="d-inline-flex bg-success-subtle text-success rounded-circle p-2 mx-auto mb-1.5 align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                        <path d="M9 12l2 2 4-4"></path>
                                    </svg>
                                </div>
                                <span class="fw-bold text-body d-block" style="font-size: 0.76rem;">{{ __('Garansi 100%') }}</span>
                                <span class="text-muted" style="font-size: 0.68rem;">{{ __('Jaminan uang kembali') }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100 lift-hover bg-body p-2.5 text-center">
                                <div class="d-inline-flex bg-primary-subtle text-primary rounded-circle p-2 mx-auto mb-1.5 align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="fas fa-headset" style="font-size: 1rem;"></i>
                                </div>
                                <span class="fw-bold text-body d-block" style="font-size: 0.76rem;">{{ __('Bantuan Live') }}</span>
                                <span class="text-muted" style="font-size: 0.68rem;">{{ __('Siap melayani 24/7') }}</span>
                            </div>
                        </div>
                    </div>

                    @php
                        $seller = $product->creator;
                        if (!$seller) {
                            $seller = \App\Models\User::where('role', 'admin')->first();
                        }
                        $sellerId = $seller ? $seller->id : 1;
                        $sellerChatUrl = route('chat.index', ['contact_id' => $sellerId]);
                        $sellerJoinDate = $seller && $seller->created_at ? $seller->created_at->translatedFormat('F Y') : 'Mei 2024';
                    @endphp

                    {{-- Store / Seller Profile Card --}}
                    <div class="p-3 rounded-4 bg-body-tertiary border d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <a href="{{ route('sellers.show', $sellerId) }}" class="text-decoration-none" title="{{ __('Kunjungi Profil Seller') }}">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 46px; height: 46px; font-size: 1.15rem;">
                                    {{ strtoupper(substr($seller->full_name ?? $seller->username ?? 'A', 0, 1)) }}
                                </div>
                            </a>
                            <div>
                                <div class="d-flex align-items-center gap-1.5">
                                    <a href="{{ route('sellers.show', $sellerId) }}" class="fw-bold text-body text-decoration-none hover-primary" style="font-size: 0.95rem;">
                                        {{ $seller->full_name ?? $seller->username ?? __('Official Store') }}
                                    </a>
                                    <i class="fas fa-check-circle text-primary" style="font-size: 0.92rem;" title="{{ __('Terverifikasi') }}"></i>
                                </div>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">
                                    <i class="far fa-calendar-alt text-secondary me-1"></i>{{ __('Bergabung') }} {{ $sellerJoinDate }}
                                </small>
                            </div>
                        </div>
                        <a href="{{ $sellerChatUrl }}" class="btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; flex-shrink: 0;" title="{{ __('Chat Penjual') }}">
                            <i class="fas fa-comment-dots fs-6"></i>
                        </a>
                    </div>

                    {{-- Product Description --}}
                    <div class="mb-4">
                        <h6 class="fw-bold text-body mb-2 d-flex align-items-center gap-2">
                            <i class="fas fa-align-left text-primary"></i>
                            <span>{{ __('Deskripsi Produk') }}</span>
                        </h6>
                        <div class="p-3 bg-light rounded-3 text-secondary small" style="line-height: 1.6; white-space: pre-line; font-size: 0.85rem;">
                            {{ $product->description ?: __('Tidak ada deskripsi rinci untuk produk ini.') }}
                        </div>
                    </div>

                    {{-- Customer Reviews List --}}
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold text-body m-0 d-flex align-items-center gap-2">
                                <i class="fas fa-comments text-warning"></i>
                                <span>{{ __('Ulasan Pembeli') }} ({{ $reviews->count() }})</span>
                            </h6>
                        </div>

                        <div class="reviews-list">
                            @forelse($reviews as $rev)
                            <div class="border-bottom py-2.5">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-secondary bg-opacity-25 text-body fw-bold d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem;">
                                            {{ strtoupper(substr($rev->user->full_name ?? $rev->user->username ?? 'U', 0, 1)) }}
                                        </div>
                                        <span class="fw-bold text-body small">{{ $rev->user->full_name ?? $rev->user->username ?? __('Pelanggan') }}</span>
                                        <div class="text-warning small" style="font-size: 0.72rem;">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="fa{{ $i <= $rev->rating ? 's' : 'r' }} fa-star"></i>
                                            @endfor
                                        </div>
                                    </div>
                                    <span class="text-muted" style="font-size: 0.72rem;">{{ $rev->created_at->format('d M Y') }}</span>
                                </div>
                                @if($rev->comment)
                                    <p class="mb-0 text-secondary ps-4 small" style="font-size: 0.8rem;">"{{ $rev->comment }}"</p>
                                @endif
                            </div>
                            @empty
                            <div class="text-center py-4 text-muted bg-light rounded-3">
                                <i class="far fa-comment-dots fs-3 mb-1 text-secondary opacity-50"></i>
                                <p class="small mb-0">{{ __('Belum ada ulasan untuk produk ini.') }}</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Checkout & Configuration Form (Desktop Card) --}}
        <div class="col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm sticky-top" style="border-radius: 20px; top: 80px; z-index: 10;">
                <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold text-body m-0 d-flex align-items-center gap-2">
                        <i class="fas fa-cart-shopping text-primary"></i>
                        <span>{{ __('Pilihan Pembelian') }}</span>
                    </h5>
                </div>

                <div class="card-body p-4">
                    @if($stockCount > 0)
                    <form action="{{ route('checkout.review', $product->id) }}" method="GET" id="mainBuyForm">

                        @if($product->is_vpn)
                            <div class="bg-primary-subtle p-3 rounded-4 mb-3 border border-primary-subtle">
                                <h6 class="fw-bold text-primary mb-2 d-flex align-items-center gap-1.5" style="font-size: 0.9rem;">
                                    <i class="fas fa-key"></i> {{ __('Konfigurasi Akun VPN') }}
                                </h6>
                                <p class="text-muted small mb-3" style="font-size: 0.75rem;">
                                    {{ __('Sistem otomatis membuat dan mengonfigurasi akun VPN Anda seketika.') }}
                                </p>
                                
                                <div class="mb-2">
                                    <label class="form-label text-dark small fw-bold mb-1">{{ __('Username VPN') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="vpn_username" class="form-control form-control-sm rounded-pill px-3" required placeholder="{{ __('Contoh: user123') }}" pattern="[a-zA-Z0-9_-]+" title="{{ __('Hanya huruf, angka, dash, dan underscore') }}">
                                    <div class="form-text text-muted" style="font-size: 0.68rem;">(Sistem akan menambahkan 4 karakter acak untuk mencegah duplikasi)</div>
                                </div>
                                
                                @if($product->vpn_protocol === 'ssh')
                                <div class="mb-2">
                                    <label class="form-label text-dark small fw-bold mb-1">{{ __('Password SSH') }} <span class="text-danger">*</span></label>
                                    <input type="password" name="vpn_password" class="form-control form-control-sm rounded-pill px-3" required placeholder="{{ __('Masukkan password') }}">
                                </div>
                                @endif

                                <div class="badge bg-primary text-white rounded-pill px-2.5 py-1 mt-1" style="font-size: 0.72rem;">
                                    <i class="far fa-calendar-check me-1"></i>{{ __('Masa Aktif:') }} {{ $product->vpn_duration_days }} {{ __('Hari') }}
                                </div>
                            </div>
                        @endif

                        {{-- Quantity Selector --}}
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold mb-2">{{ __('Jumlah Beli (QTY)') }}</label>
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-4 bg-light border">
                                <button type="button" class="btn btn-sm btn-light rounded-circle shadow-sm border" id="btnQtyMinus" style="width: 36px; height: 36px;">
                                    <i class="fas fa-minus text-secondary"></i>
                                </button>
                                <div class="text-center">
                                    <input type="number" id="quantity" name="quantity" class="form-control border-0 bg-transparent text-center fw-bold p-0 text-body" value="1" min="1" {{ !$product->is_vpn ? 'max=' . $stockCount : '' }} required style="width: 60px; font-size: 1.1rem; outline: none; box-shadow: none;">
                                    <div class="text-muted" style="font-size: 0.68rem;">{{ __('Maks:') }} {{ $stockCount }} unit</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-light rounded-circle shadow-sm border" id="btnQtyPlus" style="width: 36px; height: 36px;">
                                    <i class="fas fa-plus text-secondary"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Subtotal Calculation --}}
                        <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-4 border">
                            <span class="text-muted small fw-semibold">{{ __('Total Harga:') }}</span>
                            <span class="fw-bold text-primary fs-5" id="displaySubtotal">{{ $product->formatted_price }}</span>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex flex-column gap-2">
                            <button type="submit" id="btnDesktopBuyNow" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 btn-buy-now">
                                <i class="fas fa-bolt btn-buy-icon"></i>
                                <span class="btn-buy-text">{{ __('Beli Sekarang') }}</span>
                            </button>

                            @if(!$product->is_vpn)
                            <button type="button" class="btn btn-outline-primary w-100 rounded-pill py-2.5 fw-bold d-flex align-items-center justify-content-center gap-2" id="btnDesktopAddToCart">
                                <i class="fas fa-cart-plus"></i>
                                <span>{{ __('+ Keranjang Belanja') }}</span>
                            </button>
                            @endif
                        </div>
                    </form>
                    @else
                    <div class="text-center py-4">
                        <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 64px; height: 64px;">
                            <i class="fas fa-store-slash fs-3"></i>
                        </div>
                        <h6 class="fw-bold text-body mb-1">{{ __('Stok Sedang Habis') }}</h6>
                        <p class="text-muted small mb-4">{{ __('Produk ini saat ini belum tersedia. Silakan cek kembali dalam waktu dekat.') }}</p>
                        <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary rounded-pill px-4 py-2 w-100 fw-semibold">
                            <i class="fas fa-arrow-left me-1"></i> {{ __('Lihat Produk Lainnya') }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>



@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const unitPrice = {{ (float)$product->price }};
        const maxStock = {{ (int)$stockCount }};
        const isVpn = {{ $product->is_vpn ? 'true' : 'false' }};
        const qtyInput = document.getElementById('quantity');
        const displaySubtotal = document.getElementById('displaySubtotal');
        const mainBuyForm = document.getElementById('mainBuyForm');

        function formatRupiah(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }

        function updateSubtotal() {
            if (!qtyInput) return;
            let qty = parseInt(qtyInput.value, 10) || 1;
            if (qty < 1) qty = 1;
            if (!isVpn && maxStock > 0 && qty > maxStock) qty = maxStock;
            qtyInput.value = qty;
            if (displaySubtotal) {
                displaySubtotal.textContent = formatRupiah(unitPrice * qty);
            }
        }

        const btnMinus = document.getElementById('btnQtyMinus');
        const btnPlus = document.getElementById('btnQtyPlus');

        if (btnMinus) {
            btnMinus.addEventListener('click', function() {
                let current = parseInt(qtyInput.value, 10) || 1;
                if (current > 1) {
                    qtyInput.value = current - 1;
                    updateSubtotal();
                }
            });
        }

        if (btnPlus) {
            btnPlus.addEventListener('click', function() {
                let current = parseInt(qtyInput.value, 10) || 1;
                if (isVpn || maxStock <= 0 || current < maxStock) {
                    qtyInput.value = current + 1;
                    updateSubtotal();
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'info',
                            title: '{{ __("Batas stok tercapai") }}',
                            showConfirmButton: false,
                            timer: 1800
                        });
                    }
                }
            });
        }

        if (qtyInput) {
            qtyInput.addEventListener('input', updateSubtotal);
        }

        const isGuest = {{ Auth::guest() ? 'true' : 'false' }};

        // Asynchronous Add-to-Cart Function (Clean AJAX Without Page Redirect)
        async function handleAddToCart(buttonEl) {
            if (!buttonEl) return;

            if (isGuest) {
                window.location.href = "{{ route('login', ['redirect' => route('catalog.show', $product->id)]) }}";
                return;
            }

            const originalHtml = buttonEl.innerHTML;
            buttonEl.disabled = true;
            buttonEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const qty = parseInt(qtyInput ? qtyInput.value : 1, 10) || 1;
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            try {
                const response = await fetch("{{ route('cart.add', $product->id) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ quantity: qty })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    // Update global header cart badge instantly
                    if (typeof window.updateCartBadgeCount === 'function') {
                        window.updateCartBadgeCount(data.cart_total_qty);
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: data.message || '{{ __("Produk berhasil ditambahkan ke keranjang!") }}',
                            showConfirmButton: false,
                            timer: 2200
                        });
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'warning',
                            title: data.message || '{{ __("Gagal menambahkan ke keranjang.") }}',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                }
            } catch (err) {
                console.error('Add to cart AJAX error:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: '{{ __("Terjadi kesalahan jaringan.") }}',
                        showConfirmButton: false,
                        timer: 2500
                    });
                }
            } finally {
                buttonEl.disabled = false;
                buttonEl.innerHTML = originalHtml;
            }
        }

        // Attach to Desktop Add to Cart button
        const btnDesktopCart = document.getElementById('btnDesktopAddToCart');
        if (btnDesktopCart) {
            btnDesktopCart.addEventListener('click', function(e) {
                e.preventDefault();
                handleAddToCart(btnDesktopCart);
            });
        }

        // Attach to Mobile Add to Cart button
        const btnMobileCart = document.getElementById('btnMobileAddToCart');
        if (btnMobileCart) {
            btnMobileCart.addEventListener('click', function(e) {
                e.preventDefault();
                handleAddToCart(btnMobileCart);
            });
        }

        // Buy Now Button Loading State & Submission Coordination
        const btnDesktopBuy = document.getElementById('btnDesktopBuyNow');
        const btnMobileBuy = document.getElementById('btnMobileBuyNow');
        let isSubmittingOrder = false;

        function setBuyNowLoadingState(loading) {
            isSubmittingOrder = loading;
            const buyButtons = [btnDesktopBuy, btnMobileBuy].filter(Boolean);
            const cartButtons = [btnDesktopCart, btnMobileCart].filter(Boolean);

            buyButtons.forEach(btn => {
                if (loading) {
                    btn.disabled = true;
                    btn.classList.add('is-loading');
                    btn.setAttribute('aria-busy', 'true');
                    const icon = btn.querySelector('.btn-buy-icon');
                    const text = btn.querySelector('.btn-buy-text');
                    if (icon) {
                        icon.className = 'spinner-border spinner-border-sm me-1';
                        icon.setAttribute('role', 'status');
                        icon.setAttribute('aria-hidden', 'true');
                    }
                    if (text) {
                        text.textContent = "{{ __('Menyiapkan Review...') }}";
                    }
                } else {
                    btn.disabled = false;
                    btn.classList.remove('is-loading');
                    btn.removeAttribute('aria-busy');
                    const icon = btn.querySelector('.btn-buy-icon');
                    const text = btn.querySelector('.btn-buy-text');
                    if (icon) {
                        icon.className = 'fas fa-bolt btn-buy-icon';
                    }
                    if (text) {
                        text.textContent = "{{ __('Beli Sekarang') }}";
                    }
                }
            });

            // Prevent user from clicking add-to-cart simultaneously during checkout
            cartButtons.forEach(btn => {
                btn.disabled = loading;
                if (loading) {
                    btn.style.opacity = '0.6';
                    btn.style.pointerEvents = 'none';
                } else {
                    btn.style.opacity = '';
                    btn.style.pointerEvents = '';
                }
            });
        }

        if (mainBuyForm) {
            mainBuyForm.addEventListener('submit', function(e) {
                if (isGuest) {
                    e.preventDefault();
                    window.location.href = "{{ route('login', ['redirect' => route('catalog.show', $product->id)]) }}";
                    return false;
                }

                if (isSubmittingOrder) {
                    e.preventDefault();
                    return false;
                }

                if (!mainBuyForm.checkValidity()) {
                    return; // Let browser trigger native validation tooltips
                }

                setBuyNowLoadingState(true);
            });
        }

        // Mobile Buy Now Trigger
        if (btnMobileBuy && mainBuyForm) {
            btnMobileBuy.addEventListener('click', function(e) {
                e.preventDefault();
                if (isGuest) {
                    window.location.href = "{{ route('login', ['redirect' => route('catalog.show', $product->id)]) }}";
                    return;
                }

                if (isSubmittingOrder) return;

                // Validate required inputs (e.g. VPN username if present)
                if (mainBuyForm.reportValidity()) {
                    setBuyNowLoadingState(true);
                    mainBuyForm.submit();
                } else {
                    // Scroll into view if invalid fields exist
                    mainBuyForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
    });
</script>
@endpush
@endsection

@section('mobile_bottom_action_bar')
{{-- Mobile Sticky Action Bar ala Shopee / Tokopedia (Directly attached above Mobile Bottom Nav) --}}
<div class="mobile-sticky-action-bar d-lg-none">
    <div class="d-flex align-items-center gap-2">
        {{-- Chat Seller Icon Button --}}
        <a href="{{ $sellerChatUrl }}" class="btn btn-light rounded-4 d-flex flex-column align-items-center justify-content-center p-1 border shadow-sm" style="width: 48px; height: 44px; flex-shrink: 0;" title="{{ __('Chat Penjual') }}">
            <i class="fas fa-comment-dots text-primary" style="font-size: 1.05rem;"></i>
            <span style="font-size: 0.62rem;" class="text-secondary fw-bold mt-0.5">{{ __('Chat') }}</span>
        </a>

        @if($stockCount > 0)
            {{-- Add to Cart Button (AJAX on-page) --}}
            @if(!$product->is_vpn)
            <button type="button" class="btn btn-outline-primary rounded-pill py-2 px-3 fw-bold flex-grow-1 d-flex align-items-center justify-content-center gap-1.5 shadow-sm" id="btnMobileAddToCart" style="height: 44px; font-size: 0.85rem;">
                <i class="fas fa-cart-plus"></i>
                <span>{{ __('+ Keranjang') }}</span>
            </button>
            @endif

            {{-- Buy Now Button --}}
            <button type="button" class="btn btn-primary rounded-pill py-2 px-3 fw-bold flex-grow-1 d-flex align-items-center justify-content-center gap-1.5 shadow-sm btn-buy-now" id="btnMobileBuyNow" style="height: 44px; font-size: 0.85rem; white-space: nowrap;">
                <i class="fas fa-bolt btn-buy-icon"></i>
                <span class="btn-buy-text">{{ __('Beli Sekarang') }}</span>
            </button>
        @else
            <button type="button" class="btn btn-secondary rounded-pill py-2 px-3 fw-bold flex-grow-1 d-flex align-items-center justify-content-center gap-1.5 shadow-sm" disabled style="height: 44px; font-size: 0.85rem; opacity: 0.75;">
                <i class="fas fa-times-circle me-1"></i>
                <span>{{ __('Stok Habis') }}</span>
            </button>
        @endif
    </div>
</div>
@endsection
