@extends('layouts.app')

@section('title', $product->name)
@section('page_subtitle', 'Detail Produk')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="product-icon-wrapper" style="height: 200px; border-radius: 16px 16px 0 0;">
                <i class="fas fa-box-open" style="font-size: 4rem;"></i>
            </div>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h3 class="fw-bold mb-1">{{ $product->name }}</h3>
                        <span class="text-muted">ID: #{{ $product->id }}</span>
                    </div>
                    @if($product->is_vpn)
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
                            <i class="fas fa-network-wired me-1"></i>VPN Produk ({{ strtoupper($product->vpn_protocol) }})
                        </span>
                    @elseif($stockCount > 0)
                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">
                            <i class="fas fa-check-circle me-1"></i>{{ $stockCount }} stok tersedia
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2">
                            <i class="fas fa-times-circle me-1"></i>Stok habis
                        </span>
                    @endif
                </div>

                <hr>

                <div class="mb-4">
                    <span class="text-muted small d-block mb-1">Seller</span>
                    @if($product->creator)
                        <h6 class="fw-bold"><i class="fas fa-store text-info me-1"></i>{{ $product->creator->full_name ?? $product->creator->username }}</h6>
                    @else
                        <h6 class="fw-bold"><i class="fas fa-store text-primary me-1"></i>Admin Utama</h6>
                    @endif
                </div>

                <h5 class="fw-bold mb-2">Deskripsi</h5>
                <p class="text-muted">{{ $product->description ?: 'Tidak ada deskripsi tersedia.' }}</p>

                <hr class="my-4">

                <h5 class="fw-bold mb-3"><i class="fas fa-star text-warning me-2"></i>Ulasan & Rating</h5>
                @php
                    $reviews = \App\Models\Review::with('user')->where('product_id', $product->id)->orderBy('created_at', 'desc')->get();
                    $avgRating = $reviews->avg('rating');
                @endphp

                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="display-4 fw-bold text-primary">{{ $avgRating ? number_format($avgRating, 1) : '0.0' }}</div>
                    <div>
                        <div class="text-warning fs-5">
                            @for($i = 1; $i <= 5; $i++)
                                <i class="fa{{ $i <= round($avgRating) ? 's' : 'r' }} fa-star"></i>
                            @endfor
                        </div>
                        <span class="text-muted small">Berdasarkan {{ $reviews->count() }} ulasan</span>
                    </div>
                </div>

                <div class="reviews-list">
                    @forelse($reviews as $rev)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <span class="fw-bold text-dark small">{{ $rev->user->full_name ?? $rev->user->username ?? 'Pelanggan' }}</span>
                                <span class="text-warning ms-2" style="font-size: 0.8rem;">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="fa{{ $i <= $rev->rating ? 's' : 'r' }} fa-star"></i>
                                    @endfor
                                </span>
                            </div>
                            <span class="text-muted small" style="font-size: 0.75rem;">{{ $rev->created_at->format('d M Y') }}</span>
                        </div>
                        @if($rev->comment)
                            <p class="mb-0 text-secondary small italic">"{{ $rev->comment }}"</p>
                        @endif
                    </div>
                    @empty
                    <p class="text-muted small mb-0">Belum ada ulasan untuk produk ini.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <div class="mb-4">
                    <span class="text-muted small">Harga</span>
                    <div class="product-price" style="font-size: 2rem;">{{ $product->formatted_price }}</div>
                </div>

                @if($stockCount > 0)
                    <div class="alert alert-info border-0 rounded-3 mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Anda dapat membeli produk ini langsung di website atau melalui bot Telegram kami.
                    </div>
                    @if(config('telegram.bot_username'))
                        <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank"
                           class="btn btn-primary w-100 rounded-pill py-3 fw-bold mb-3">
                            <i class="fab fa-telegram me-2"></i>Beli via Telegram
                        </a>
                    @endif
                    <form action="{{ route('checkout.store', $product->id) }}" method="POST">
                        @csrf
                        
                        @if($product->is_vpn)
                            <div class="bg-primary-subtle p-3 rounded-3 mb-3">
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-user-shield me-2"></i>Konfigurasi Akun VPN</h6>
                                <p class="text-muted small mb-3">Sistem akan otomatis membuatkan akun VPN Anda.</p>
                                
                                <div class="mb-2">
                                    <label class="form-label text-dark small fw-bold">Username VPN <span class="text-danger">*</span></label>
                                    <input type="text" name="vpn_username" class="form-control form-control-sm" required placeholder="Contoh: user123" pattern="[a-zA-Z0-9_-]+" title="Hanya huruf, angka, dash, dan underscore">
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

                        <div class="mb-3 d-flex align-items-center justify-content-between bg-light p-2 rounded-pill px-3">
                            <label for="quantity" class="text-muted small fw-bold mb-0">Jumlah Beli:</label>
                            <input type="number" id="quantity" name="quantity" class="form-control form-control-sm border-0 bg-transparent text-end fw-bold px-0" value="1" min="1" {{ !$product->is_vpn ? 'max=' . $stockCount : '' }} required style="width: 70px; outline: none; box-shadow: none;">
                        </div>
                        <button type="submit" class="btn btn-success w-100 rounded-pill py-3 fw-bold mb-2">
                            <i class="fas fa-shopping-bag me-2"></i>Beli Sekarang (via Website)
                        </button>
                        
                        @if(!$product->is_vpn)
                        <button type="submit" formaction="{{ route('cart.add', $product->id) }}" class="btn btn-outline-primary w-100 rounded-pill py-3 fw-bold">
                            <i class="fas fa-cart-plus me-2"></i>Tambah ke Keranjang
                        </button>
                        @endif
                    </form>
                @else
                    <div class="alert alert-warning border-0 rounded-3 mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Stok sedang habis. Silakan cek kembali nanti.
                    </div>
                @endif

                <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary w-100 rounded-pill py-2 mt-3">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Katalog
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
