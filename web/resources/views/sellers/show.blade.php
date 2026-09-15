@extends('layouts.app')

@section('title', ($seller->full_name ?? $seller->username) . ' - ' . __('Profil Seller'))
@section('page_subtitle', __('Profil Seller'))
@section('meta_description', 'Lihat profil toko dan katalog produk dari ' . ($seller->full_name ?? $seller->username))

@push('styles')
<style>
    .lift-hover {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .lift-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08) !important;
    }
    .hero-banner-seller {
        background: linear-gradient(135deg, #0d47a1 0%, #1565c0 50%, #1e88e5 100%);
        border-radius: 24px;
        position: relative;
        overflow: hidden;
    }
    .hero-pattern {
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        opacity: 0.12;
        background-image: radial-gradient(#ffffff 1.5px, transparent 1.5px);
        background-size: 22px 22px;
        pointer-events: none;
    }
    .hero-circle-1 {
        position: absolute;
        top: -60px; right: -60px;
        width: 300px; height: 300px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
        pointer-events: none;
    }
    .hero-circle-2 {
        position: absolute;
        bottom: -80px; right: 100px;
        width: 180px; height: 180px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        pointer-events: none;
    }
</style>
@endpush

@section('content')
<div class="seller-profile-container pb-5">
    {{-- Top Back Navigation --}}
    <div class="d-flex align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('catalog.index') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Katalog') }}">
                <i class="fas fa-arrow-left text-secondary"></i>
            </a>
            <h5 class="fw-bold m-0 text-body" style="font-size: 1.1rem;">{{ __('Profil Seller') }}</h5>
        </div>
    </div>

    {{-- Seller Hero Banner Card ala Home --}}
    <div class="hero-banner-seller p-4 p-md-5 text-white shadow-sm mb-4 position-relative">
        <div class="hero-pattern"></div>
        <div class="hero-circle-1"></div>
        <div class="hero-circle-2"></div>
        
        <div class="position-relative z-1">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                {{-- Left: Seller Avatar & Identity --}}
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white text-primary fw-bold d-flex align-items-center justify-content-center shadow-lg border border-3 border-white flex-shrink-0" style="width: 68px; height: 68px; font-size: 1.75rem;">
                        {{ strtoupper(substr($seller->full_name ?? $seller->username ?? 'S', 0, 1)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h3 class="fw-bold mb-0 text-white" style="font-size: 1.35rem;">
                                {{ $seller->full_name ?? $seller->username }}
                            </h3>
                            <i class="fas fa-check-circle text-info" style="font-size: 1.15rem;" title="{{ __('Penjual Terverifikasi') }}"></i>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            {{-- Solid White Badge dengan Teks Primary (Kontras Tinggi) --}}
                            <span class="badge bg-white text-primary rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="font-size: 0.75rem; letter-spacing: 0.3px;">
                                <i class="fas fa-store me-1 text-primary"></i>{{ $seller->role === 'admin' ? __('Official Store Admin') : __('Official Seller') }}
                            </span>
                            {{-- Badge Tanggal Bergabung Bergaris Tepi Putih Tajam --}}
                            <span class="badge bg-white bg-opacity-15 border border-white border-opacity-30 rounded-pill px-3 py-1.5 text-white fw-semibold small">
                                <i class="far fa-calendar-alt me-1 text-white"></i>{{ __('Bergabung') }} {{ $sellerJoinDate }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Right: Direct Actions --}}
                <div class="d-flex align-items-center gap-2 w-100 w-md-auto mt-2 mt-md-0">
                    <a href="{{ route('chat.index', ['contact_id' => $seller->id]) }}" class="btn btn-light text-primary rounded-pill px-4 py-2.5 fw-bold d-flex align-items-center justify-content-center gap-2 flex-grow-1 flex-md-grow-0 shadow-sm lift-hover" style="font-size: 0.88rem;">
                        <i class="fas fa-comment-dots text-primary"></i>
                        <span>{{ __('Chat Penjual') }}</span>
                    </a>
                    <button type="button" id="btnShareSellerProfile" class="btn btn-outline-light rounded-pill px-3 py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-1.5 shadow-sm" style="font-size: 0.88rem;" title="{{ __('Bagikan Profil') }}">
                        <i class="fas fa-share-alt"></i>
                        <span class="d-none d-sm-inline">{{ __('Bagikan') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Store Stats Cards Independen ala Home (Produk Aktif, Unit Terjual, Rating) --}}
    <div class="row g-2 g-md-3 mb-4">
        <!-- Card 1: Produk Aktif -->
        <div class="col-4">
            <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden bg-body text-body">
                <div class="card-body p-3 text-center">
                    <div class="d-inline-flex bg-primary-subtle text-primary rounded-circle p-2.5 mb-2">
                        <i class="fas fa-box-open fa-lg"></i>
                    </div>
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;">{{ __('Produk Aktif') }}</div>
                    <h4 class="fw-bold mb-0 text-body mt-1">{{ $totalProducts }}</h4>
                </div>
            </div>
        </div>

        <!-- Card 2: Unit Terjual -->
        <div class="col-4">
            <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden bg-body text-body">
                <div class="card-body p-3 text-center">
                    <div class="d-inline-flex bg-success-subtle text-success rounded-circle p-2.5 mb-2">
                        <i class="fas fa-bag-shopping fa-lg"></i>
                    </div>
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;">{{ __('Unit Terjual') }}</div>
                    <h4 class="fw-bold mb-0 text-body mt-1">{{ $totalSoldUnits }}</h4>
                </div>
            </div>
        </div>

        <!-- Card 3: Rating Toko -->
        <div class="col-4">
            <div class="card border-0 shadow-sm h-100 rounded-4 lift-hover overflow-hidden bg-body text-body">
                <div class="card-body p-3 text-center">
                    <div class="d-inline-flex bg-warning-subtle text-warning rounded-circle p-2.5 mb-2">
                        <i class="fas fa-star fa-lg"></i>
                    </div>
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;">{{ __('Penilaian') }}</div>
                    <h4 class="fw-bold mb-0 text-body mt-1">
                        {{ $avgRating ? number_format($avgRating, 1) : '5.0' }}
                        <small class="text-muted fw-normal" style="font-size: 0.72rem;">({{ $totalReviews }})</small>
                    </h4>
                </div>
            </div>
        </div>
    </div>

    {{-- Store Catalog Title & Search Bar --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h5 class="fw-bold mb-0 text-body"><i class="fas fa-boxes text-primary me-2"></i>{{ __('Katalog Produk Toko') }}</h5>
            <small class="text-muted">{{ __(':count produk siap dipesan', ['count' => $products->count()]) }}</small>
        </div>
        <div style="max-width: 300px; width: 100%;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-body border-end-0 rounded-start-pill ps-3"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="storeSearchInput" class="form-control border-start-0 rounded-end-pill pe-3" placeholder="{{ __('Cari di toko ini...') }}">
            </div>
        </div>
    </div>

    {{-- Filter Chips --}}
    <div class="category-chips-wrapper mb-3 pb-1 d-flex gap-2 overflow-x-auto text-nowrap" style="-webkit-overflow-scrolling: touch; scrollbar-width: none;">
        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold store-filter-chip active" data-filter="all">
            <i class="fas fa-th-large me-1"></i> {{ __('Semua') }}
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold store-filter-chip" data-filter="ready">
            <i class="fas fa-check-circle text-success me-1"></i> {{ __('Ready Stok') }}
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold store-filter-chip" data-filter="account">
            <i class="fas fa-user-shield text-info me-1"></i> {{ __('Akun Digital') }}
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold store-filter-chip" data-filter="vpn">
            <i class="fas fa-network-wired text-warning me-1"></i> {{ __('VPN / SSH') }}
        </button>
    </div>

    {{-- Products Grid --}}
    <div class="row g-2 g-md-4" id="storeProductsRow">
        @forelse($products as $product)
        <div class="col-6 col-lg-4 col-xl-3 store-item-col" 
             data-name="{{ strtolower($product->name) }}" 
             data-is-vpn="{{ $product->is_vpn ? '1' : '0' }}" 
             data-has-stock="{{ $product->stock_count > 0 ? '1' : '0' }}">
            <div class="card product-card h-100 position-relative clickable-product-card" data-href="{{ route('catalog.show', $product->id) }}" style="cursor: pointer;">
                {{-- Stock Badge --}}
                <div class="product-badge">
                    @if($product->stock_count > 0)
                        <span class="badge bg-success-subtle text-success rounded-pill px-2 px-md-3">
                            <i class="fas fa-check-circle me-1"></i>{{ $product->stock_count }} <span class="d-none d-sm-inline">{{ __('stok') }}</span>
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2 px-md-3">
                            <i class="fas fa-times-circle me-1"></i>{{ __('Habis') }}
                        </span>
                    @endif
                </div>

                {{-- Product Thumbnail / Icon --}}
                <div class="product-icon-wrapper position-relative overflow-hidden">
                    @if($product->image_url)
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-100 h-100" style="object-fit: cover; transition: transform 0.35s ease;">
                    @else
                        @if($product->is_vpn)
                            <i class="fas fa-network-wired"></i>
                        @else
                            <i class="fas fa-box-open"></i>
                        @endif
                    @endif
                </div>

                <div class="card-body d-flex flex-column p-2 p-md-3">
                    <h6 class="fw-bold mb-1 text-body" style="font-size: 0.9rem; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.4em;" title="{{ $product->name }}">{{ $product->name }}</h6>
                    @if($product->description)
                        <p class="text-muted small mb-2 flex-grow-1 d-none d-sm-block" style="font-size: 0.78rem;">{{ Str::limit($product->description, 50) }}</p>
                    @endif

                    <div class="d-flex justify-content-between align-items-center mb-2 small text-muted" style="font-size: 0.72rem;">
                        <span><i class="fas fa-box me-1 text-primary"></i>SKU #{{ $product->id }}</span>
                        @if(isset($product->sales_count) && $product->sales_count > 0)
                            <span class="fw-bold text-success">{{ $product->sales_count }} <span class="d-none d-sm-inline">{{ __('terjual') }}</span></span>
                        @endif
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-1 mt-auto pt-2 border-top">
                        <span class="product-price mb-0" style="font-size: 0.95rem;">{{ $product->formatted_price }}</span>
                        <div class="d-flex gap-1 align-items-center">
                            @if($product->stock_count > 0 && !$product->is_vpn)
                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-bold btn-quick-cart-add btn-stop-prop d-flex align-items-center gap-1" data-product-id="{{ $product->id }}" title="{{ __('Tambah ke Keranjang') }}" style="font-size: 0.75rem;">
                                <i class="fas fa-cart-plus"></i><span class="d-none d-sm-inline">+ {{ __('Keranjang') }}</span><span class="d-sm-none">+</span>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="text-center py-5 bg-white rounded-4 shadow-sm">
                <i class="fas fa-store-slash text-muted mb-3" style="font-size: 3.5rem;"></i>
                <h5 class="fw-bold text-muted">{{ __('Seller ini belum memiliki produk aktif') }}</h5>
                <p class="text-muted small mb-3">{{ __('Produk digital akan segera ditampilkan begitu seller mengunggah stok.') }}</p>
                <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">{{ __('Lihat Katalog Utama') }}</a>
            </div>
        </div>
        @endforelse
    </div>

    <div id="storeNoMatchFound" class="col-12 d-none text-center py-5">
        <i class="fas fa-search text-muted mb-3" style="font-size: 3rem;"></i>
        <h6 class="fw-bold text-muted">{{ __('Tidak ada produk yang cocok dengan pencarian Anda di toko ini') }}</h6>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 mt-2" onclick="resetStoreFilters()">{{ __('Reset Filter') }}</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('storeSearchInput');
        const filterChips = document.querySelectorAll('.store-filter-chip');
        const productItems = document.querySelectorAll('.store-item-col');
        const noMatch = document.getElementById('storeNoMatchFound');
        let currentFilter = 'all';

        function applyFilter() {
            const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
            let visibleCount = 0;

            productItems.forEach(item => {
                const name = item.getAttribute('data-name') || '';
                const isVpn = item.getAttribute('data-is-vpn') === '1';
                const hasStock = item.getAttribute('data-has-stock') === '1';

                const matchesSearch = !query || name.includes(query);
                let matchesCategory = true;

                if (currentFilter === 'ready') {
                    matchesCategory = hasStock;
                } else if (currentFilter === 'account') {
                    matchesCategory = !isVpn;
                } else if (currentFilter === 'vpn') {
                    matchesCategory = isVpn;
                }

                if (matchesSearch && matchesCategory) {
                    item.classList.remove('d-none');
                    visibleCount++;
                } else {
                    item.classList.add('d-none');
                }
            });

            if (noMatch) {
                if (visibleCount === 0 && productItems.length > 0) {
                    noMatch.classList.remove('d-none');
                } else {
                    noMatch.classList.add('d-none');
                }
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilter);
        }

        filterChips.forEach(chip => {
            chip.addEventListener('click', function() {
                filterChips.forEach(c => {
                    c.classList.remove('btn-primary', 'active');
                    c.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-primary', 'active');

                currentFilter = this.getAttribute('data-filter') || 'all';
                applyFilter();
            });
        });

        window.resetStoreFilters = function() {
            if (searchInput) searchInput.value = '';
            currentFilter = 'all';
            filterChips.forEach(c => {
                c.classList.remove('btn-primary', 'active');
                c.classList.add('btn-outline-secondary');
                if (c.getAttribute('data-filter') === 'all') {
                    c.classList.remove('btn-outline-secondary');
                    c.classList.add('btn-primary', 'active');
                }
            });
            applyFilter();
        };

        // Clickable Product Card Navigation
        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-stop-prop') || e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) {
                return;
            }
            const card = e.target.closest('.clickable-product-card');
            if (card) {
                const href = card.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            }
        });

        // Share Profile Handler
        const btnShare = document.getElementById('btnShareSellerProfile');
        if (btnShare) {
            btnShare.addEventListener('click', function() {
                if (navigator.share) {
                    navigator.share({
                        title: "{{ $seller->full_name ?? $seller->username }} - Profil Seller",
                        url: window.location.href
                    }).catch(() => {});
                } else if (navigator.clipboard) {
                    navigator.clipboard.writeText(window.location.href).then(() => {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Tautan profil toko berhasil disalin!',
                                showConfirmButton: false,
                                timer: 2000
                            });
                        } else {
                            alert('Tautan profil berhasil disalin!');
                        }
                    });
                }
            });
        }

        // Quick Add to Cart Handler
        document.addEventListener('click', async function(e) {
            const btn = e.target.closest('.btn-quick-cart-add');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            const productId = btn.getAttribute('data-product-id');
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                const response = await fetch(`/cart/add/${productId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ quantity: 1 })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    if (typeof window.updateCartBadgeCount === 'function') {
                        window.updateCartBadgeCount(data.cart_total_qty);
                    } else {
                        const badge = document.getElementById('cart-badge-count');
                        if (badge) {
                            badge.textContent = data.cart_total_qty;
                            badge.classList.remove('d-none');
                            badge.style.display = 'inline-block';
                        }
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: data.message || 'Produk ditambahkan ke keranjang!',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'warning',
                            title: data.message || 'Gagal menambahkan ke keranjang.',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                }
            } catch (err) {
                console.error('Quick add to cart error:', err);
            } finally {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        });
    });
</script>
@endpush
