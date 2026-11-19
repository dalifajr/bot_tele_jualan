@extends('layouts.app')

@section('title', __('Favorit & Alert Stok'))
@section('page_subtitle', __('Produk pilihan dan pengingat restok otomatis'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0 text-body"><i class="fas fa-heart text-danger me-1.5"></i>{{ __('Produk Favorit Saya') }}</h4>
        <small class="text-secondary">{{ __('Aktifkan pengingat agar langsung dikabari via notifikasi & Telegram saat produk di-restok.') }}</small>
    </div>
    <a href="{{ route('catalog.index') }}" class="btn btn-outline-primary rounded-pill px-3.5 fw-semibold btn-sm">
        <i class="fas fa-store me-1"></i>{{ __('Jelajahi Katalog') }}
    </a>
</div>

<div class="row g-3">
    @forelse($wishlists as $wish)
        @php
            $product = $wish->product;
            $readyCount = $product->is_vpn ? 99 : (int) ($readyStockCounts[$product->id] ?? 0);
        @endphp
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-body p-3.5 d-flex flex-column justify-content-between gap-3">
                    <div>
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <a href="{{ route('catalog.show', $product->id) }}" class="fw-bold text-body text-decoration-none fs-6">
                                {{ $product->name }}
                            </a>
                            <form action="{{ route('wishlist.toggle', $product->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light rounded-circle text-danger" style="width: 34px; height: 34px; padding: 0;" title="{{ __('Hapus dari Favorit') }}">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </form>
                        </div>

                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span class="fw-bold text-primary fs-5">Rp {{ number_format((int) $product->price, 0, ',', '.') }}</span>
                            @if($readyCount > 0)
                                <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-bold">
                                    {{ $product->is_vpn ? __('Tersedia') : __(':count stok ready', ['count' => $readyCount]) }}
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fw-bold">{{ __('Stok Habis') }}</span>
                            @endif
                        </div>

                        @if($product->description)
                            <p class="small text-secondary mb-0" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ strip_tags($product->description) }}
                            </p>
                        @endif
                    </div>

                    <div class="d-flex gap-2 pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                        <form action="{{ route('wishlist.restock-alert', $product->id) }}" method="POST" class="m-0 flex-grow-1">
                            @csrf
                            <button type="submit" class="btn btn-sm w-100 rounded-pill fw-semibold {{ $wish->notify_restock ? 'btn-primary' : 'btn-outline-secondary' }}">
                                <i class="fas {{ $wish->notify_restock ? 'fa-bell' : 'fa-bell-slash' }} me-1"></i>
                                {{ $wish->notify_restock ? __('Alert Restok Aktif') : __('Kabari Saat Ready') }}
                            </button>
                        </form>
                        <a href="{{ route('catalog.show', $product->id) }}" class="btn btn-sm btn-tonal rounded-pill px-3 fw-semibold">
                            {{ __('Lihat') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 rounded-4 shadow-sm">
                <div class="card-body py-5 text-center">
                    <i class="far fa-heart fa-3x text-muted opacity-50 mb-3"></i>
                    <h6 class="fw-bold text-body">{{ __('Daftar Favorit Masih Kosong') }}</h6>
                    <p class="small text-secondary mb-3">{{ __('Simpan produk incaran Anda dari katalog dan nyalakan notifikasi restok otomatis.') }}</p>
                    <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
                        {{ __('Buka Katalog Produk') }}
                    </a>
                </div>
            </div>
        </div>
    @endforelse
</div>
@endsection
