@extends('layouts.app')

@section('title', __('Keranjang Belanja'))
@section('page_subtitle', __('Keranjang'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('catalog.index') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Katalog') }}">
            <i class="fas fa-arrow-left text-body"></i>
        </a>
        <div>
            <h5 class="fw-bold m-0 text-body" style="font-size: 1.15rem;">{{ __('Keranjang Belanja') }}</h5>
            <small class="text-muted" style="font-size: 0.75rem;">{{ __('Kelola dan periksa produk pilihan Anda') }}</small>
        </div>
    </div>
    <a href="{{ route('catalog.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold d-none d-sm-inline-flex align-items-center gap-1.5" style="font-size: 0.8rem;">
        <i class="fas fa-plus"></i>
        <span>{{ __('Lanjut Belanja') }}</span>
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4 d-none d-md-block"><i class="fas fa-shopping-cart text-primary me-2"></i>{{ __('Keranjang Belanja Anda') }}</h5>

                @if($cartItems->isEmpty())
                <div class="text-center py-5 text-muted">
                    <div class="mb-3">
                        <i class="fas fa-shopping-basket fs-1 text-secondary opacity-50"></i>
                    </div>
                    <h6 class="fw-bold">{{ __('Keranjang Anda Masih Kosong') }}</h6>
                    <p class="small text-muted mb-4">{{ __('Temukan produk digital berkualitas di katalog kami.') }}</p>
                    <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-search me-1"></i> {{ __('Telusuri Produk') }}
                    </a>
                </div>
                @else
                {{-- Desktop Table View --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table align-middle border-0 mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th scope="col" class="border-0 pb-3" style="width: 50%;">{{ __('PRODUK') }}</th>
                                <th scope="col" class="border-0 pb-3 text-center" style="width: 25%;">{{ __('JUMLAH') }}</th>
                                <th scope="col" class="border-0 pb-3 text-end" style="width: 25%;">{{ __('SUBTOTAL') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cartItems as $item)
                            @if($item->product)
                            @php
                                $stockCount = $item->product->stockUnits()->where('is_sold', false)->whereNull('sold_order_id')->count();
                            @endphp
                            <tr class="align-middle" data-cart-item-id="{{ $item->id }}" data-price="{{ $item->product->price }}" data-qty="{{ $item->quantity }}" data-max-stock="{{ $stockCount }}">
                                <td class="border-secondary-subtle py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center justify-content-center bg-light rounded-3 text-primary" style="width: 50px; height: 50px; border: 1px solid var(--bs-border-color);">
                                            <i class="fas fa-box-open fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-1"><a href="{{ route('catalog.show', $item->product->id) }}" class="text-decoration-none text-body">{{ $item->product->name }}</a></h6>
                                            <span class="text-primary fw-bold small">{{ $item->product->formatted_price }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="border-secondary-subtle py-3 text-center">
                                    <div class="d-inline-flex align-items-center bg-light rounded-pill border p-1" style="height: 38px;">
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none px-2 py-0 text-muted btn-stepper-touch" data-action="decrement" data-id="{{ $item->id }}" {{ $item->quantity <= 1 ? 'disabled' : '' }}>
                                            <i class="fas fa-minus fs-6"></i>
                                        </button>
                                        <span class="px-2 fw-bold text-dark cart-qty-val-{{ $item->id }}" style="min-width: 24px;">{{ $item->quantity }}</span>
                                        <button type="button" class="btn btn-sm btn-link text-decoration-none px-2 py-0 text-muted btn-stepper-touch" data-action="increment" data-id="{{ $item->id }}">
                                            <i class="fas fa-plus fs-6"></i>
                                        </button>
                                    </div>
                                    <div class="mt-1">
                                        <form action="{{ route('cart.remove', $item->id) }}" method="POST" class="d-inline m-0" onsubmit="confirmAction(event, 'Hapus produk ini dari keranjang?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link text-danger text-decoration-none p-0 small" style="font-size: 0.8rem;">
                                                <i class="fas fa-trash-alt me-1"></i> {{ __('Hapus') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <td class="border-secondary-subtle py-3 text-end fw-bold cart-subtotal-val-{{ $item->id }}">
                                    Rp{{ number_format($item->product->price * $item->quantity, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Cart Cards View --}}
                <div class="d-md-none">
                    @foreach($cartItems as $item)
                    @if($item->product)
                    @php
                        $stockCount = $item->product->stockUnits()->where('is_sold', false)->whereNull('sold_order_id')->count();
                    @endphp
                    <div class="card border border-secondary-subtle rounded-4 p-3 mb-3 shadow-none bg-body" data-cart-item-id="{{ $item->id }}" data-price="{{ $item->product->price }}" data-qty="{{ $item->quantity }}" data-max-stock="{{ $stockCount }}">
                        <div class="d-flex gap-3 mb-3">
                            <div class="d-flex align-items-center justify-content-center bg-light rounded-3 text-primary flex-shrink-0" style="width: 54px; height: 54px; border: 1px solid var(--glass-border);">
                                <i class="fas fa-box-open fs-4"></i>
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="fw-bold mb-1 text-truncate">
                                    <a href="{{ route('catalog.show', $item->product->id) }}" class="text-decoration-none text-body">{{ $item->product->name }}</a>
                                </h6>
                                <span class="text-primary fw-bold" style="font-size: 0.9rem;">{{ $item->product->formatted_price }}</span>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                            <div class="d-flex align-items-center gap-2">
                                <div class="d-inline-flex align-items-center bg-light rounded-pill border p-1" style="height: 38px;">
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none px-2 py-0 text-muted btn-stepper-touch" data-action="decrement" data-id="{{ $item->id }}" {{ $item->quantity <= 1 ? 'disabled' : '' }}>
                                        <i class="fas fa-minus fs-6"></i>
                                    </button>
                                    <span class="px-2 fw-bold text-dark cart-qty-val-{{ $item->id }}" style="min-width: 24px; font-size: 0.9rem;">{{ $item->quantity }}</span>
                                    <button type="button" class="btn btn-sm btn-link text-decoration-none px-2 py-0 text-muted btn-stepper-touch" data-action="increment" data-id="{{ $item->id }}">
                                        <i class="fas fa-plus fs-6"></i>
                                    </button>
                                </div>
                                <form action="{{ route('cart.remove', $item->id) }}" method="POST" class="d-inline m-0" onsubmit="confirmAction(event, 'Hapus produk ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle p-2" title="{{ __('Hapus') }}">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small d-block" style="font-size: 0.7rem;">Subtotal</span>
                                <span class="fw-bold text-body cart-subtotal-val-{{ $item->id }}" style="font-size: 0.95rem;">Rp{{ number_format($item->product->price * $item->quantity, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    @if(!$cartItems->isEmpty())
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">{{ __('Ringkasan Belanja') }}</h5>
                
                <div class="d-flex justify-content-between mb-3 text-muted">
                    <span>Total Barang (<span class="cart-summary-total-qty">{{ $cartItems->sum('quantity') }} unit</span>)</span>
                    <span class="cart-summary-total-price">Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>
                
                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="fw-bold">{{ __('Estimasi Total') }}</span>
                    <span class="fs-4 fw-bold text-primary cart-summary-total-price">Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
                </div>

                <div class="d-grid gap-2">
                    {{-- Desktop Only Checkout Button (Hidden on Mobile to eliminate duplication with Sticky Bar) --}}
                    <a href="{{ route('cart.checkout') }}" class="btn btn-success rounded-pill py-3 fw-bold d-none d-md-block">
                        <i class="fas fa-shopping-bag me-2"></i>{{ __('Lanjut ke Checkout') }}
                    </a>
                    <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary rounded-pill py-2">
                        <i class="fas fa-arrow-left me-2"></i>{{ __('Lanjut Belanja') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile Sticky Checkout Bar (Single primary checkout button on mobile) --}}
    <div class="mobile-sticky-action-bar d-md-none">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div>
                <span class="text-muted d-block" style="font-size: 0.68rem; line-height: 1;">Total (<span class="cart-summary-total-qty">{{ $cartItems->sum('quantity') }} unit</span>)</span>
                <span class="fw-bold text-primary cart-summary-total-price" style="font-size: 1.1rem;">Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
            </div>
            <a href="{{ route('cart.checkout') }}" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center gap-2" style="font-size: 0.85rem;">
                <i class="fas fa-shopping-bag"></i> {{ __('Checkout') }}
            </a>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const updateDebounceTimers = {};

        function formatRupiah(num) {
            return 'Rp' + Number(num).toLocaleString('id-ID');
        }

        function updateCartItemQty(itemId, newQty) {
            const elements = document.querySelectorAll(`[data-cart-item-id="${itemId}"]`);
            if (!elements.length) return;
            
            const price = parseInt(elements[0].getAttribute('data-price')) || 0;
            const maxStock = parseInt(elements[0].getAttribute('data-max-stock')) || 999999;
            
            // Boundary checks
            if (newQty < 1) newQty = 1;
            if (newQty > maxStock) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'warning',
                        title: `Stok hanya tersedia ${maxStock} unit.`,
                        showConfirmButton: false,
                        timer: 2500
                    });
                }
                newQty = maxStock;
            }
            
            // 1. Instant On-Device DOM Update (0ms)
            document.querySelectorAll(`.cart-qty-val-${itemId}`).forEach(el => el.textContent = newQty);
            
            document.querySelectorAll(`.btn-stepper-touch[data-id="${itemId}"][data-action="decrement"]`).forEach(btn => {
                btn.disabled = (newQty <= 1);
            });
            
            const itemSubtotal = price * newQty;
            document.querySelectorAll(`.cart-subtotal-val-${itemId}`).forEach(el => {
                el.textContent = formatRupiah(itemSubtotal);
            });
            
            elements.forEach(el => el.setAttribute('data-qty', newQty));
            
            // 2. Recalculate full cart summary instantly
            recalculateCartSummary();
            
            // 3. Debounced AJAX Sync to backend
            clearTimeout(updateDebounceTimers[itemId]);
            updateDebounceTimers[itemId] = setTimeout(() => {
                syncCartItemWithServer(itemId, newQty);
            }, 350);
        }

        function recalculateCartSummary() {
            let grandTotal = 0;
            let totalItems = 0;
            const countedIds = new Set();
            
            document.querySelectorAll('[data-cart-item-id]').forEach(el => {
                const id = el.getAttribute('data-cart-item-id');
                if (!countedIds.has(id)) {
                    countedIds.add(id);
                    const price = parseInt(el.getAttribute('data-price')) || 0;
                    const qty = parseInt(el.getAttribute('data-qty')) || 0;
                    grandTotal += (price * qty);
                    totalItems += qty;
                }
            });
            
            document.querySelectorAll('.cart-summary-total-price').forEach(el => el.textContent = formatRupiah(grandTotal));
            document.querySelectorAll('.cart-summary-total-qty').forEach(el => el.textContent = `${totalItems} unit`);
            
            const badge = document.getElementById('cart-badge-count');
            if (badge) {
                if (totalItems > 0) {
                    badge.textContent = totalItems;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        async function syncCartItemWithServer(itemId, quantity) {
            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

                const response = await fetch(`/cart/update/${itemId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ quantity: quantity })
                });
                
                const data = await response.json();
                if (!response.ok || !data.success) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: data.message || 'Gagal menyinkronkan keranjang.',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                    if (data.available_stock !== undefined) {
                        updateCartItemQty(itemId, data.available_stock);
                    }
                }
            } catch (err) {
                console.error('Cart sync error:', err);
            }
        }

        // Event delegation for touch steppers
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-stepper-touch');
            if (!btn) return;
            e.preventDefault();
            
            const itemId = btn.getAttribute('data-id');
            const action = btn.getAttribute('data-action');
            const currentQtyEl = document.querySelector(`.cart-qty-val-${itemId}`);
            let currentQty = parseInt(currentQtyEl ? currentQtyEl.textContent : 1) || 1;
            
            if (action === 'increment') {
                updateCartItemQty(itemId, currentQty + 1);
            } else if (action === 'decrement') {
                updateCartItemQty(itemId, Math.max(1, currentQty - 1));
            }
        });
    });
</script>
@endpush
