@extends('layouts.app')

@section('title', 'Katalog Produk')
@section('page_subtitle', 'Katalog')
@section('meta_description', 'Lihat semua produk digital yang tersedia untuk dibeli')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Katalog Produk</h4>
        <p class="text-muted mb-0">{{ $products->count() }} produk tersedia</p>
    </div>
</div>

<div class="row g-4">
    @forelse($products as $product)
    <div class="col-sm-6 col-lg-4 col-xl-3">
        <div class="card product-card h-100 position-relative">
            {{-- Stock Badge --}}
            <div class="product-badge">
                @if($product->stock_count > 0)
                    <span class="badge bg-success-subtle text-success rounded-pill px-3">
                        <i class="fas fa-check-circle me-1"></i>{{ $product->stock_count }} stok
                    </span>
                @else
                    <span class="badge bg-danger-subtle text-danger rounded-pill px-3">
                        <i class="fas fa-times-circle me-1"></i>Habis
                    </span>
                @endif
            </div>

            {{-- Product Icon --}}
            <div class="product-icon-wrapper">
                <i class="fas fa-box-open"></i>
            </div>

            <div class="card-body d-flex flex-column">
                <h6 class="fw-bold mb-1">{{ Str::limit($product->name, 30) }}</h6>
                @if($product->description)
                    <p class="text-muted small mb-3 flex-grow-1">{{ Str::limit($product->description, 60) }}</p>
                @else
                    <p class="text-muted small mb-3 flex-grow-1">Produk digital</p>
                @endif

                <div class="mb-3 small">
                    @if($product->creator)
                        <span class="text-muted"><i class="fas fa-store me-1 text-info"></i>Seller: <strong>{{ $product->creator->full_name ?? $product->creator->username }}</strong></span>
                    @else
                        <span class="text-muted"><i class="fas fa-store me-1 text-primary"></i>Seller: <strong>Admin Utama</strong></span>
                    @endif
                </div>

                <div class="d-flex justify-content-between align-items-center mt-auto">
                    <span class="product-price">{{ $product->formatted_price }}</span>
                    <div class="d-flex gap-2">
                        @if($product->stock_count > 0)
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#checkoutModal{{ $product->id }}">
                            Beli <i class="fas fa-shopping-cart ms-1"></i>
                        </button>
                        @endif
                        <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            Detail
                        </a>
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
                    <h5 class="fw-bold">Checkout Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('checkout.store', $product->id) }}" method="POST">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Produk</label>
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
                                    Take All
                                </button>
                            </div>
                            <div class="form-text">Maksimal pembelian: {{ $product->stock_count }} unit.</div>
                        </div>

                        @if($product->is_vpn)
                            <div class="bg-primary-subtle p-3 rounded-3 mb-3">
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-user-shield me-2"></i>Konfigurasi Akun VPN</h6>
                                <p class="text-muted small mb-3">Sistem akan otomatis membuatkan akun VPN Anda.</p>
                                
                                <div class="mb-2">
                                    <label class="form-label text-dark small fw-bold">Username VPN <span class="text-danger">*</span></label>
                                    <input type="text" name="vpn_username" class="form-control form-control-sm" required placeholder="Contoh: user123" pattern="[a-zA-Z0-9_-]+" title="Hanya huruf, angka, dash, dan underscore">
                                    <div class="form-text" style="font-size: 0.7rem;">(Sistem akan menambahkan 4 huruf acak di akhir untuk mencegah duplikasi)</div>
                                </div>
                                
                                @if($product->vpn_protocol === 'ssh')
                                <div class="mb-2">
                                    <label class="form-label text-dark small fw-bold">Password SSH <span class="text-danger">*</span></label>
                                    <input type="password" name="vpn_password" class="form-control form-control-sm" required placeholder="Masukkan password">
                                </div>
                                @endif
                                <div class="form-text text-muted" style="font-size: 0.7rem;">Masa Aktif: <strong>{{ $product->vpn_duration_days }} Hari</strong></div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Lanjutkan Pembayaran</button>
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
            <h5 class="text-muted">Belum ada produk tersedia</h5>
            <p class="text-muted">Produk akan ditambahkan oleh admin melalui bot Telegram.</p>
        </div>
    </div>
    @endforelse
</div>
@endsection
