@extends('layouts.app')

@section('title', __('Katalog Produk'))
@section('page_subtitle', __('Katalog'))
@section('meta_description', 'Lihat semua produk digital yang tersedia untuk dibeli')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Katalog Produk') }}</h4>
        <p class="text-muted mb-0">{{ __(':count produk tersedia', ['count' => $products->count()]) }}</p>
    </div>
    <div class="d-flex align-items-center gap-2" style="max-width: 320px; width: 100%;">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-body border-end-0 rounded-start-pill ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="catalogSearchInput" class="form-control border-start-0 rounded-end-pill pe-3" placeholder="{{ __('Cari nama produk...') }}">
        </div>
    </div>
</div>

{{-- Horizontal Scroll Category Filter Chips --}}
<div class="category-chips-wrapper mb-3 pb-1 d-flex gap-2 overflow-x-auto text-nowrap" style="-webkit-overflow-scrolling: touch; scrollbar-width: none;">
    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold category-filter-chip active" data-filter="all">
        <i class="fas fa-th-large me-1"></i> {{ __('Semua') }}
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold category-filter-chip" data-filter="ready">
        <i class="fas fa-check-circle text-success me-1"></i> {{ __('Ready Stok') }}
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold category-filter-chip" data-filter="account">
        <i class="fas fa-user-shield text-info me-1"></i> {{ __('Akun Digital') }}
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold category-filter-chip" data-filter="vpn">
        <i class="fas fa-network-wired text-warning me-1"></i> {{ __('VPN / SSH') }}
    </button>
</div>

<div class="row g-2 g-md-4" id="catalogProductsRow">
    @forelse($products as $product)
    <div class="col-6 col-lg-4 col-xl-3 product-item-col" 
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

            {{-- Product Icon --}}
            <div class="product-icon-wrapper">
                <i class="fas fa-box-open"></i>
            </div>

            <div class="card-body d-flex flex-column p-2 p-md-3">
                <h6 class="fw-bold mb-1" style="font-size: 0.9rem; line-height: 1.3;">{{ Str::limit($product->name, 28) }}</h6>
                @if($product->description)
                    <p class="text-muted small mb-2 flex-grow-1 d-none d-sm-block" style="font-size: 0.78rem;">{{ Str::limit($product->description, 50) }}</p>
                @endif

                <div class="d-flex justify-content-between align-items-center mb-2 small text-muted" style="font-size: 0.72rem;">
                    @if($product->creator)
                        <span class="text-truncate" style="max-width: 65%;"><i class="fas fa-store me-1 text-info"></i>{{ $product->creator->full_name ?? $product->creator->username }}</span>
                    @else
                        <span><i class="fas fa-store me-1 text-primary"></i>Admin</span>
                    @endif
                    @if(isset($product->sales_count) && $product->sales_count > 0)
                        <span class="fw-bold text-success">{{ $product->sales_count }} <span class="d-none d-sm-inline">{{ __('terjual') }}</span></span>
                    @endif
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-1 mt-auto pt-2 border-top">
                    <span class="product-price mb-1 mb-sm-0" style="font-size: 0.95rem;">{{ $product->formatted_price }}</span>
                    <div class="d-flex gap-1 align-items-center">
                        @if($product->stock_count > 0)
                            @if(!$product->is_vpn)
                            <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 fw-bold btn-quick-cart-add btn-stop-prop" data-product-id="{{ $product->id }}" title="{{ __('Tambah ke Keranjang') }}" style="font-size: 0.75rem;">
                                <i class="fas fa-cart-plus me-1"></i><span class="d-none d-sm-inline">+ {{ __('Keranjang') }}</span><span class="d-sm-none">+</span>
                            </button>
                            @endif
                            <button type="button" class="btn btn-xs btn-primary rounded-pill px-2.5 px-md-3 py-1 fw-bold btn-stop-prop" data-bs-toggle="modal" data-bs-target="#checkoutModal{{ $product->id }}" style="font-size: 0.75rem;">
                                {{ __('Beli') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($product->stock_count > 0)
    @push('modals')
    {{-- Checkout Modal --}}
    <div class="modal fade" id="checkoutModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold">{{ __('Checkout Produk') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('checkout.store', $product->id) }}" method="POST">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">{{ __('Produk') }}</label>
                            <p class="mb-0 fw-bold">{{ $product->name }}</p>
                            <p class="text-primary fw-bold">{{ $product->formatted_price }}</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Kuantitas (QTY)</label>
                            <div class="input-group mb-1">
                                <button class="btn btn-outline-secondary px-3" type="button" onclick="this.parentNode.querySelector('input[type=number]').stepDown()">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" name="quantity" class="form-control text-center fw-bold" value="1" min="1" max="{{ $product->stock_count }}" {{ $product->is_vpn ? '' : 'required' }}>
                                <button class="btn btn-outline-secondary px-3" type="button" onclick="this.parentNode.querySelector('input[type=number]').stepUp()">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="btn btn-primary px-3 fw-bold" type="button" onclick="this.parentNode.querySelector('input[type=number]').value = {{ $product->stock_count }}">
                                    {{ __('Take All') }}
                                </button>
                            </div>
                            <div class="form-text">{{ __('Maksimal pembelian: :count unit.', ['count' => $product->stock_count]) }}</div>
                        </div>

                        @if($product->is_vpn)
                            <div class="bg-primary-subtle p-3 rounded-3 mb-3">
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-user-shield me-2"></i>{{ __('Konfigurasi Akun VPN') }}</h6>
                                <p class="text-muted small mb-3">{{ __('Sistem akan otomatis membuatkan akun VPN Anda.') }}</p>
                                
                                <div class="mb-2">
                                    <label class="form-label text-dark small fw-bold">{{ __('Username VPN') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="vpn_username" class="form-control form-control-sm" required placeholder="{{ __('Contoh: user123') }}" pattern="[a-zA-Z0-9_-]+" title="{{ __('Hanya huruf, angka, dash, dan underscore') }}">
                                    <div class="form-text" style="font-size: 0.7rem;">(Sistem akan menambahkan 4 huruf acak di akhir untuk mencegah duplikasi)</div>
                                </div>
                                
                                @if($product->vpn_protocol === 'ssh')
                                <div class="mb-2">
                                    <label class="form-label text-dark small fw-bold">{{ __('Password SSH') }} <span class="text-danger">*</span></label>
                                    <input type="password" name="vpn_password" class="form-control form-control-sm" required placeholder="{{ __('Masukkan password') }}">
                                </div>
                                @endif
                                <div class="form-text text-muted" style="font-size: 0.7rem;">{{ __('Masa Aktif:') }} <strong>{{ $product->vpn_duration_days }} {{ __('Hari') }}</strong></div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Lanjutkan Pembayaran') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endpush
    @endif

    @empty
    <div class="col-12">
        <div class="text-center py-5">
            <i class="fas fa-store-slash text-muted mb-3" style="font-size: 4rem;"></i>
            <h5 class="text-muted">{{ __('Belum ada produk tersedia') }}</h5>
            <p class="text-muted">{{ __('Produk akan ditambahkan oleh admin melalui bot Telegram.') }}</p>
        </div>
    </div>
    @endforelse
</div>

<div id="noMatchFound" class="col-12 d-none text-center py-5">
    <i class="fas fa-search text-muted mb-3" style="font-size: 3rem;"></i>
    <h6 class="fw-bold text-muted">{{ __('Tidak ada produk yang cocok dengan pencarian / filter Anda') }}</h6>
    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 mt-2" onclick="resetCatalogFilters()">{{ __('Reset Filter') }}</button>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('catalogSearchInput');
        const filterChips = document.querySelectorAll('.category-filter-chip');
        const productItems = document.querySelectorAll('.product-item-col');
        const noMatch = document.getElementById('noMatchFound');
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
                    const badge = document.getElementById('cart-badge-count');
                    if (badge) {
                        badge.textContent = data.cart_total_qty;
                        badge.style.display = 'inline-block';
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

