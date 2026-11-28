@extends('layouts.app')

@section('title', __('Katalog Produk'))
@section('page_subtitle', __(':count produk tersedia', ['count' => $products->count()]))
@section('meta_description', __('Lihat semua produk digital yang tersedia untuk dibeli'))

@section('content')
<div class="d-flex flex-column gap-3">
    {{-- Header Katalog (Figma M3 Style) --}}
    <div>
        <h1 class="m3-page-title mb-1">
            {{ __('Katalog produk') }}
        </h1>
        <p class="text-secondary small mb-0" style="font-size: 0.92rem;">
            {{ __('Temukan produk digital yang Anda butuhkan.') }}
        </p>
    </div>

    {{-- Search & Sort Bar --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="flex-grow-1" style="max-width: 640px;">
            <div class="m3-top-search-bar w-100" style="background: var(--surface-container-low); border: 1px solid var(--outline-variant);">
                <i class="fas fa-search"></i>
                <input type="text" id="catalogSearchInput" class="m3-top-search-input" placeholder="{{ __('Cari nama produk...') }}" value="{{ request('search') }}">
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @php
                $activeSort = $sort ?? request('sort', 'bestselling');
                $sortLabels = [
                    'bestselling' => __('Terlaris'),
                    'price_asc' => __('Harga Terendah'),
                    'price_desc' => __('Harga Tertinggi'),
                    'latest' => __('Terbaru'),
                ];
                $activeSortLabel = $sortLabels[$activeSort] ?? __('Terlaris');
            @endphp
            <div class="dropdown">
                <button class="btn btn-outline-secondary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-2" type="button" id="catalogSortBtn" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.8125rem; font-weight: 600; border-color: var(--outline-variant); color: var(--on-surface);">
                    <span id="catalogSortLabel">{{ __('Urutkan:') }} {{ $activeSortLabel }}</span>
                    <i class="fas fa-chevron-down small text-secondary"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius: var(--radius-md);">
                    <li><button type="button" class="dropdown-item catalog-sort-option {{ $activeSort === 'bestselling' ? 'active' : '' }}" data-sort="bestselling" data-label="{{ __('Terlaris') }}">{{ __('Terlaris') }}</button></li>
                    <li><button type="button" class="dropdown-item catalog-sort-option {{ $activeSort === 'price_asc' ? 'active' : '' }}" data-sort="price_asc" data-label="{{ __('Harga Terendah') }}">{{ __('Harga Terendah') }}</button></li>
                    <li><button type="button" class="dropdown-item catalog-sort-option {{ $activeSort === 'price_desc' ? 'active' : '' }}" data-sort="price_desc" data-label="{{ __('Harga Tertinggi') }}">{{ __('Harga Tertinggi') }}</button></li>
                    <li><button type="button" class="dropdown-item catalog-sort-option {{ $activeSort === 'latest' ? 'active' : '' }}" data-sort="latest" data-label="{{ __('Terbaru') }}">{{ __('Terbaru') }}</button></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Filter Chips & Counter --}}
    <div class="d-flex flex-column gap-2 mb-2">
        <div class="category-chips-wrapper" role="tablist" aria-label="{{ __('Kategori Produk') }}">
            <button type="button" class="category-filter-chip btn-primary active" data-filter="all">
                <i class="fas fa-check small"></i> <span>{{ __('Semua') }}</span>
            </button>
            <button type="button" class="category-filter-chip btn-outline-secondary" data-filter="ready">
                <span>{{ __('Ready Stok') }}</span>
            </button>
            <button type="button" class="category-filter-chip btn-outline-secondary" data-filter="account">
                <span>{{ __('Akun Digital') }}</span>
            </button>
            <button type="button" class="category-filter-chip btn-outline-secondary" data-filter="vpn">
                <span>{{ __('VPN / SSH') }}</span>
            </button>
        </div>
        <div class="text-secondary small fw-medium">
            <span id="catalogProductCount">{{ $products->count() }}</span> {{ __('produk') }}
        </div>
    </div>

    {{-- Skeleton Shimmer Grid Placeholder (Shows while loading / filter transitions) --}}
    <div class="row g-3 d-none" id="catalogSkeletonGrid">
        @for($s = 0; $s < 6; $s++)
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card product-card h-100 p-0" style="border-radius: var(--radius-lg); background: var(--surface-container-low);">
                <div class="skeleton-shimmer w-100" style="height: 160px; border-radius: var(--radius-lg) var(--radius-lg) 0 0;"></div>
                <div class="p-3">
                    <div class="skeleton-shimmer mb-2" style="height: 18px; width: 85%;"></div>
                    <div class="skeleton-shimmer mb-3" style="height: 14px; width: 50%;"></div>
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2">
                        <div class="skeleton-shimmer" style="height: 22px; width: 80px;"></div>
                        <div class="skeleton-shimmer rounded-circle" style="height: 38px; width: 38px;"></div>
                    </div>
                </div>
            </div>
        </div>
        @endfor
    </div>

    {{-- Products Grid (2 Columns on Mobile, 3 on Tablet, 4 on Desktop) --}}
    <div class="row g-2 g-md-3" id="catalogProductsRow">
        @forelse($products as $product)
        <div class="col-6 col-md-4 col-xl-3 product-item-col" 
             data-name="{{ strtolower($product->name) }}" 
             data-is-vpn="{{ $product->is_vpn ? '1' : '0' }}" 
             data-has-stock="{{ $product->stock_count > 0 ? '1' : '0' }}"
             data-price="{{ (int) $product->price }}"
             data-sales="{{ (int) ($product->sales_count ?? 0) }}"
             data-id="{{ (int) $product->id }}">
            <div class="card product-card h-100 position-relative clickable-product-card" data-href="{{ route('catalog.show', $product->id) }}" tabindex="0" role="link" aria-label="{{ $product->name }}" style="border-radius: var(--radius-lg, 18px) !important; background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important; cursor: pointer;">
                {{-- Stock Badge (Flat M3 Tonal Pill — Adaptive Light & Dark Mode) --}}
                <div class="product-badge" style="top: 8px; left: 8px; position: absolute; z-index: 5;">
                    @if($product->stock_count > 0)
                        <span class="badge m3-stock-badge-ready rounded-pill px-2 py-0.5 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.68rem;">
                            <span class="rounded-circle" style="width: 5px; height: 5px; background: currentColor;"></span>
                            <span>{{ $product->stock_count }} <span class="d-none d-sm-inline">{{ __('stok') }}</span></span>
                        </span>
                    @else
                        <span class="badge m3-stock-badge-empty rounded-pill px-2 py-0.5 fw-bold d-inline-flex align-items-center gap-1" style="font-size: 0.68rem;">
                            <span class="rounded-circle" style="width: 5px; height: 5px; background: currentColor;"></span>
                            <span>{{ __('Habis') }}</span>
                        </span>
                    @endif
                </div>

                {{-- Product Thumbnail / Icon (Figma M3 Style) --}}
                <div class="product-icon-wrapper position-relative overflow-hidden" style="height: 120px; background: var(--surface-container); border-radius: var(--radius-lg, 18px) var(--radius-lg, 18px) 0 0;">
                    @if($product->image_url)
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-100 h-100" style="object-fit: cover; transition: transform 0.35s ease;">
                    @else
                        @if($product->is_vpn)
                            <i class="fas fa-network-wired" style="font-size: 2.2rem; color: var(--primary); opacity: 0.85;"></i>
                        @else
                            <i class="fas fa-envelope-open-text" style="font-size: 2.2rem; color: var(--primary); opacity: 0.85;"></i>
                        @endif
                    @endif
                </div>

                <div class="card-body d-flex flex-column p-2.5 p-md-3">
                    <h6 class="fw-bold mb-1 text-body" style="font-size: 0.88rem; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6em;" title="{{ $product->name }}">{{ $product->name }}</h6>
                    @if($product->description)
                        <p class="text-secondary small mb-1.5 text-truncate d-none d-sm-block" style="font-size: 0.75rem;">{{ Str::limit($product->description, 45) }}</p>
                    @endif

                    <div class="d-flex align-items-center mb-2 small text-secondary" style="font-size: 0.7rem;">
                        @php
                            $storeSellerId = $product->creator_id ?: ($defaultOfficialSellerId ?? 1);
                        @endphp
                        @if($product->creator)
                            <a href="{{ route('sellers.show', $storeSellerId) }}" class="btn-stop-prop text-decoration-none text-secondary hover-primary text-truncate d-inline-flex align-items-center" style="max-width: 90%; font-size: 0.7rem;" title="{{ __('Kunjungi Toko') }}">
                                <i class="fas fa-store me-1 text-primary"></i><span class="text-truncate">{{ $product->creator->full_name ?? $product->creator->username }}</span>
                            </a>
                        @else
                            <a href="{{ route('sellers.show', $storeSellerId) }}" class="btn-stop-prop text-decoration-none text-secondary hover-primary text-truncate d-inline-flex align-items-center" style="max-width: 90%; font-size: 0.7rem;" title="{{ __('Kunjungi Toko Resmi') }}">
                                <i class="fas fa-store me-1 text-primary"></i><span>Official</span>
                            </a>
                        @endif
                    </div>

                    <div class="d-flex justify-content-between align-items-end mt-auto pt-1">
                        <div class="d-flex flex-column">
                            <span class="product-price mb-0" style="font-size: 0.95rem; font-weight: 700; color: var(--on-surface);">{{ $product->formatted_price }}</span>
                            <small class="text-secondary" style="font-size: 0.68rem;">
                                {{ $product->sales_count ?? 0 }} {{ __('terjual') }}
                            </small>
                        </div>
                        <div>
                            @if($product->stock_count > 0 && !$product->is_vpn)
                            <button type="button" class="btn-quick-cart-circle btn-quick-cart-add btn-stop-prop" 
                                    data-product-id="{{ $product->id }}" 
                                    data-is-vcc="{{ $product->is_vcc ? '1' : '0' }}"
                                    data-product-name="{{ $product->name }}"
                                    data-product-price="{{ $product->price }}"
                                    data-formatted-price="{{ $product->formatted_price }}"
                                    title="{{ __('Tambah ke Keranjang') }}" aria-label="{{ __('Tambah ke Keranjang') }}" style="width: 40px; height: 40px;">
                                <i class="fas fa-shopping-cart" style="font-size: 0.85rem;"></i>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @empty
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-store-slash text-secondary mb-3" style="font-size: 3.5rem;"></i>
                <h5 class="text-secondary">{{ __('Belum ada produk tersedia') }}</h5>
                <p class="text-secondary small">{{ __('Produk akan ditambahkan oleh admin melalui bot Telegram.') }}</p>
            </div>
        </div>
        @endforelse
    </div>

    <div id="noMatchFound" class="col-12 d-none text-center py-5">
        <i class="fas fa-search text-secondary mb-3" style="font-size: 3rem;"></i>
        <h5 class="text-secondary">{{ __('Tidak ada produk yang cocok') }}</h5>
        <p class="text-secondary small">{{ __('Coba gunakan kata kunci pencarian lain atau pilih kategori Semua.') }}</p>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="resetCatalogFilters()">
            {{ __('Reset Filter') }}
        </button>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('catalogSearchInput');
        const filterChips = document.querySelectorAll('.category-filter-chip');
        const productItems = document.querySelectorAll('.product-item-col');
        const noMatch = document.getElementById('noMatchFound');
        const countDisplay = document.getElementById('catalogProductCount');
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

            if (countDisplay) {
                countDisplay.textContent = visibleCount;
            }

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

        const skeletonGrid = document.getElementById('catalogSkeletonGrid');
        const productsRow = document.getElementById('catalogProductsRow');
        let shimmerTimer = null;

        function triggerFilterWithShimmer() {
            if (skeletonGrid && productsRow) {
                skeletonGrid.classList.remove('d-none');
                productsRow.classList.add('d-none');
                clearTimeout(shimmerTimer);
                shimmerTimer = setTimeout(() => {
                    applyFilter();
                    skeletonGrid.classList.add('d-none');
                    productsRow.classList.remove('d-none');
                }, 160);
            } else {
                applyFilter();
            }
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
                triggerFilterWithShimmer();
            });
        });

        window.resetCatalogFilters = function() {
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

        // Sort Handler (Client-side instant sort + URL state sync)
        const sortOptions = document.querySelectorAll('.catalog-sort-option');
        const sortLabelEl = document.getElementById('catalogSortLabel');
        const sortPrefix = "{{ __('Urutkan:') }} ";

        function applySort(sortMode) {
            if (!productsRow) return;
            const cols = Array.from(productsRow.querySelectorAll('.product-item-col'));
            cols.sort((a, b) => {
                const aStock = a.getAttribute('data-has-stock') === '1' ? 1 : 0;
                const bStock = b.getAttribute('data-has-stock') === '1' ? 1 : 0;
                if (aStock !== bStock) return bStock - aStock;

                const aPrice = parseInt(a.getAttribute('data-price') || '0', 10);
                const bPrice = parseInt(b.getAttribute('data-price') || '0', 10);
                const aSales = parseInt(a.getAttribute('data-sales') || '0', 10);
                const bSales = parseInt(b.getAttribute('data-sales') || '0', 10);
                const aId = parseInt(a.getAttribute('data-id') || '0', 10);
                const bId = parseInt(b.getAttribute('data-id') || '0', 10);

                if (sortMode === 'price_asc') return (aPrice - bPrice) || (bId - aId);
                if (sortMode === 'price_desc') return (bPrice - aPrice) || (bId - aId);
                if (sortMode === 'latest') return bId - aId;
                return (bSales - aSales) || (bId - aId);
            });
            cols.forEach(col => productsRow.appendChild(col));
        }

        sortOptions.forEach(opt => {
            opt.addEventListener('click', function() {
                const sortMode = this.getAttribute('data-sort') || 'bestselling';
                const label = this.getAttribute('data-label') || '';
                sortOptions.forEach(o => o.classList.remove('active'));
                this.classList.add('active');
                if (sortLabelEl && label) {
                    sortLabelEl.textContent = sortPrefix + label;
                }
                applySort(sortMode);
                try {
                    const url = new URL(window.location.href);
                    if (sortMode === 'bestselling') {
                        url.searchParams.delete('sort');
                    } else {
                        url.searchParams.set('sort', sortMode);
                    }
                    window.history.replaceState({}, '', url.toString());
                } catch (e) {}
            });
        });

        // Clickable Product Card Navigation (Mouse + Keyboard Accessibility)
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

        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const card = e.target.closest('.clickable-product-card');
            if (!card || e.target.closest('button') || e.target.closest('a')) return;
            e.preventDefault();
            const href = card.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        });

        // Quick Add to Cart Handler
        const isGuest = {{ Auth::guest() ? 'true' : 'false' }};
        document.addEventListener('click', async function(e) {
            const btn = e.target.closest('.btn-quick-cart-add');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            if (isGuest) {
                window.location.href = "{{ route('login', ['redirect' => route('catalog.index')]) }}";
                return;
            }

            const isVcc = btn.getAttribute('data-is-vcc') === '1';
            const productId = btn.getAttribute('data-product-id');
            const productName = btn.getAttribute('data-product-name');
            const productPrice = parseFloat(btn.getAttribute('data-product-price')) || 0;
            const formattedPrice = btn.getAttribute('data-formatted-price');

            if (isVcc && typeof window.openVccSelector === 'function') {
                window.openVccSelector({
                    productId: productId,
                    productName: productName,
                    price: productPrice,
                    formattedPrice: formattedPrice,
                    mode: 'cart'
                });
                return;
            }

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
                            title: data.message || '{{ __('Produk ditambahkan ke keranjang!') }}',
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
                            title: data.message || '{{ __('Gagal menambahkan ke keranjang.') }}',
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
@endsection
