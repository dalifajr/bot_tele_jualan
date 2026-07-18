@extends('layouts.app')

@section('title', 'Katalog Admin')
@section('page_subtitle', 'Detail Produk')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-light text-muted mb-2 px-3 rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> {{ __('Kembali ke Daftar') }}
        </a>
        <h4 class="fw-bold mb-1">{{ $product->name }}</h4>
        <p class="text-muted mb-0">{{ __('Kelola Harga, Stok, dan Data Akun') }}</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px; background: linear-gradient(135deg, #primary, #blue);">
            <div class="card-body p-4 text-center">
                <i class="fas fa-box fs-1 text-primary mb-3"></i>
                <h6 class="fw-bold text-muted mb-1">{{ __('Stok Ready') }}</h6>
                <h3 class="fw-bold mb-0">{{ $readyStockCount }} <span class="fs-6 text-muted fw-normal">{{ __('Unit') }}</span></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4 text-center">
                <i class="fas fa-check-circle fs-1 text-success mb-3"></i>
                <h6 class="fw-bold text-muted mb-1">{{ __('Akun Terjual') }}</h6>
                <h3 class="fw-bold mb-0">{{ $soldStockCount }} <span class="fs-6 text-muted fw-normal">{{ __('Unit') }}</span></h3>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden mb-4" style="border-radius: 16px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-4 border-bottom pb-3"><i class="fas fa-cogs text-secondary me-2"></i>{{ __('Aksi Katalog (Mirip Bot)') }}</h5>
        
        <div class="row g-3">
            <div class="col-md-4 col-sm-6">
                <button class="btn btn-outline-success w-100 py-3 rounded-3 text-start fw-bold" onclick="Swal.fire('Fitur Atur Harga dalam pengembangan')">
                    <i class="fas fa-money-bill-wave fa-lg mb-2 d-block"></i> {{ __('Atur Harga') }}
                </button>
            </div>
            <div class="col-md-4 col-sm-6">
                <button class="btn btn-outline-success w-100 py-3 rounded-3 text-start fw-bold" onclick="Swal.fire('Fitur GHS Bekas dalam pengembangan')">
                    <i class="fas fa-recycle fa-lg mb-2 d-block"></i> {{ __('Atur Harga GHS Bekas') }}
                </button>
            </div>
            <div class="col-md-4 col-sm-6">
                <button class="btn btn-outline-secondary w-100 py-3 rounded-3 text-start fw-bold" onclick="Swal.fire('Fitur Jam Awaiting dalam pengembangan')">
                    <i class="fas fa-clock fa-lg mb-2 d-block"></i> {{ __('Atur Jam Awaiting') }}
                </button>
            </div>

            <div class="col-md-4 col-sm-6">
                <a href="{{ route('admin.stock.index') }}" class="btn btn-outline-primary w-100 py-3 rounded-3 text-start fw-bold">
                    <i class="fas fa-envelope-open-text fa-lg mb-2 d-block"></i> {{ __('Tambah Stok Ready') }}
                </a>
            </div>
            <div class="col-md-4 col-sm-6">
                <button class="btn btn-outline-warning w-100 py-3 rounded-3 text-start fw-bold" onclick="Swal.fire('Fitur Awaiting Benefits dalam pengembangan')">
                    <i class="fas fa-hourglass-half fa-lg mb-2 d-block"></i> {{ __('Tambah Stok Awaiting') }}
                </button>
            </div>
            <div class="col-md-4 col-sm-6">
                <button class="btn btn-outline-info w-100 py-3 rounded-3 text-start fw-bold" onclick="Swal.fire('Fitur Simpan Akun dalam pengembangan')">
                    <i class="fas fa-save fa-lg mb-2 d-block"></i> {{ __('Simpan Akun') }}
                </button>
            </div>
            
            <div class="col-md-4 col-sm-6">
                <a href="{{ route('admin.stock.index') }}" class="btn btn-outline-secondary w-100 py-3 rounded-3 text-start fw-bold">
                    <i class="fas fa-clipboard-list fa-lg mb-2 d-block"></i> {{ __('Lihat List Akun') }}
                </a>
            </div>
            <div class="col-md-4 col-sm-6">
                <button class="btn btn-outline-dark w-100 py-3 rounded-3 text-start fw-bold" onclick="Swal.fire('Menampilkan Akun Terjual...')">
                    <i class="fas fa-receipt fa-lg mb-2 d-block"></i> {{ __('Akun Terjual') }}
                </button>
            </div>
            <div class="col-md-4 col-sm-6">
                <button class="btn btn-outline-secondary w-100 py-3 rounded-3 text-start fw-bold" onclick="Swal.fire('Pilih akun untuk melihat detail')">
                    <i class="fas fa-eye fa-lg mb-2 d-block"></i> {{ __('Lihat Detail Akun') }}
                </button>
            </div>
            
            <div class="col-md-4 col-sm-6">
                <button class="btn btn-outline-danger w-100 py-3 rounded-3 text-start fw-bold" onclick="Swal.fire({title: 'Hapus Akun', text: 'Fitur Hapus Akun dalam pengembangan', icon: 'error'})">
                    <i class="fas fa-trash-alt fa-lg mb-2 d-block"></i> {{ __('Hapus Akun') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
