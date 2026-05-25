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

                <div class="d-flex justify-content-between align-items-center mt-auto">
                    <span class="product-price">{{ $product->formatted_price }}</span>
                    <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-sm btn-primary rounded-pill px-3">
                        Detail <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
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
