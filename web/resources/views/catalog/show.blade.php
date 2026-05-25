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
                    @if($stockCount > 0)
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

                <h5 class="fw-bold mb-2">Deskripsi</h5>
                <p class="text-muted">{{ $product->description ?: 'Tidak ada deskripsi tersedia.' }}</p>
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
                    <div class="alert alert-info border-0 rounded-3 mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        Untuk membeli produk ini, gunakan bot Telegram kami.
                    </div>
                    @if(config('telegram.bot_username'))
                        <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank"
                           class="btn btn-primary w-100 rounded-pill py-3 fw-bold">
                            <i class="fab fa-telegram me-2"></i>Beli via Telegram
                        </a>
                    @endif
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
