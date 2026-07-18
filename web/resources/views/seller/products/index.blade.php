@extends('layouts.app')

@section('title', 'Manajemen Produk Saya')
@section('page_subtitle', 'Produk')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Manajemen Katalog Produk') }}</h4>
        <p class="text-muted mb-0">{{ __('Kelola produk buatan Anda sendiri dan kelola hak akses para worker Anda.') }}</p>
    </div>
    <div>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createProductModal">
            <i class="fas fa-plus me-1"></i> {{ __('Buat Produk Baru') }}
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

{{-- Section 1: My Owned Products --}}
<div class="mb-5">
    <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-box-open me-2"></i>{{ __('Produk Buatan Saya (Milik Sendiri)') }}</h5>
    
    @if($myProducts->count() > 0)
    <div class="row g-4">
        @foreach($myProducts as $product)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="border-radius: 20px;">
                <div class="bg-primary p-3 text-white d-flex justify-content-between align-items-center">
                    <div class="text-truncate" style="max-width: 60%;">
                        <h6 class="fw-bold m-0 text-truncate" title="{{ $product->name }}">{{ $product->name }}</h6>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="badge bg-white text-primary rounded-pill fw-bold">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                        <button class="btn btn-sm btn-light text-primary rounded-circle p-0" data-bs-toggle="modal" data-bs-target="#editProductModal{{ $product->id }}" title="{{ __('Edit Info Produk') }}" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fas fa-edit" style="font-size: 0.7rem;"></i>
                        </button>
                        <button class="btn btn-sm btn-light text-danger rounded-circle p-0" data-bs-toggle="modal" data-bs-target="#deleteProductModal{{ $product->id }}" title="{{ __('Hapus Produk') }}" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fas fa-trash-alt" style="font-size: 0.7rem;"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="mb-3">
                        <p class="text-muted small mb-2">{{ Str::limit($product->description ?: 'Tidak ada deskripsi produk.', 120) }}</p>
                        
                        <div class="d-flex gap-3 small text-secondary mt-3">
                            <span><i class="fas fa-cubes text-info me-1"></i> <strong>{{ $product->stockUnits()->where('is_sold', false)->count() }}</strong> {{ __('unit ready') }}</span>
                            <span><i class="fas fa-shopping-bag text-success me-1"></i> <strong>{{ $product->stockUnits()->where('is_sold', true)->count() }}</strong> {{ __('unit terjual') }}</span>
                        </div>
                    </div>
                    
                    {{-- Workers section --}}
                    <div class="border-top pt-3 mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-muted">Daftar Worker ({{ $product->workers->count() }})</span>
                            <button class="btn btn-sm btn-link text-decoration-none p-0 fw-bold" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#addWorkerModal{{ $product->id }}">
                                <i class="fas fa-user-plus me-1"></i> {{ __('Tambah Worker') }}
                            </button>
                        </div>
                        
                        @if($product->workers->count() > 0)
                            <div class="d-flex flex-column gap-2 max-height-150 overflow-y-auto pr-1">
                                @foreach($product->workers as $worker
                                <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded-3 small">
                                    <div class="d-flex align-items-center gap-2 text-truncate" style="max-width: 70%;">
                                        <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center fw-bold" style="width: 24px; height: 24px; font-size: 0.7rem; flex-shrink: 0;">
                                            {{ strtoupper(substr($worker->full_name ?? $worker->username ?? 'W', 0, 1)) }}
                                        </div>
                                        <span class="fw-bold text-truncate">{{ $worker->full_name ?? $worker->username }}</span>
                                    </div>
                                    
                                    {{-- Kick button --}}
                                    <form action="{{ route('seller.products.workers.destroy', [$product->id, $worker->id]) }}" method="POST" class="m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 py-0 px-2" title="{{ __('Kick Worker') }}" onclick="confirmAction(event, 'Yakin ingin menghapus worker ini dari produk Anda? Seluruh stok milik worker tersebut pada produk ini akan dialihkan kepemilikannya ke dompet Anda, namun data uploader asli tetap dipertahankan.')">
                                            <i class="fas fa-user-slash"></i>
                                        </button>
                                    </form>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted small italic m-0">{{ __('Belum ada worker yang ditugaskan.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-4 bg-white shadow-sm rounded-4">
        <i class="fas fa-box text-muted mb-2 fs-3"></i>
        <p class="text-muted small mb-0">{{ __('Anda belum pernah membuat produk mandiri apapun.') }}</p>
    </div>
    @endif
</div>

{{-- Section 2: Products Worked By Me --}}
<div class="mb-4">
    <h5 class="fw-bold mb-3 text-info"><i class="fas fa-briefcase me-2"></i>{{ __('Katalog Kerja Saya (Sebagai Worker)') }}</h5>
    
    @if($workedProducts->count() > 0)
    <div class="row g-4">
        @foreach($workedProducts as $product)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm overflow-hidden h-100" style="border-radius: 20px; background: rgba(248,249,250,0.8);">
                <div class="bg-info p-3 text-white d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold m-0 text-truncate" style="max-width: 70%;">{{ $product->name }}</h6>
                    <span class="badge bg-white text-info rounded-pill fw-bold">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div class="mb-3">
                        <p class="text-muted small mb-2">{{ Str::limit($product->description ?: 'Tidak ada deskripsi produk.', 120) }}</p>
                        <div class="small text-muted mb-2">{{ __('Pemilik Produk:') }} <strong>{{ $product->creator->full_name ?? $product->creator->username ?? 'Admin Utama' }}</strong></div>
                        
                        <div class="d-flex gap-3 small text-secondary mt-3">
                            {{-- Filter stok seller ini saja pada produk global --}}
                            <span><i class="fas fa-boxes text-info me-1"></i> {{ __('Stok Anda:') }} <strong>{{ $product->stockUnits()->where('seller_id', Auth::id())->where('is_sold', false)->count() }}</strong> {{ __('unit ready') }}</span>
                        </div>
                    </div>
                    
                    <div class="border-top pt-3 mt-2 text-center">
                        <a href="{{ route('seller.stock.index') }}" class="btn btn-outline-info btn-sm rounded-pill px-4 w-100">
                            <i class="fas fa-plus me-1"></i> {{ __('Unggah Stok Saya') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-4 bg-white shadow-sm rounded-4">
        <i class="fas fa-briefcase text-muted mb-2 fs-3"></i>
        <p class="text-muted small mb-0">{{ __('Anda tidak terdaftar sebagai worker pada produk eksternal manapun saat ini.') }}</p>
    </div>
    @endif
</div>

@push('modals')
{{-- Create Product Modal --}}
<div class="modal fade" id="createProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Buat Produk Mandiri Baru') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('seller.products.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Nama Produk') }}</label>
                        <input type="text" name="name" class="form-control" placeholder="{{ __('Contoh: GitHub Students Pack Premium') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Harga Produk (Rupiah)') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">Rp</span>
                            <input type="number" name="price" class="form-control" placeholder="70000" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Deskripsi Produk (Opsional)') }}</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="{{ __('Masukkan deskripsi detail mengenai produk digital ini...') }}"></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="enableWarrantyAdd" name="enable_warranty" value="1">
                        <label class="form-check-label text-muted small fw-bold" for="enableWarrantyAdd">{{ __('Aktifkan garansi?') }}</label>
                    </div>
                    <div class="mb-3" id="warrantyDaysAddContainer" style="display: none;">
                        <label class="form-label text-muted small fw-bold">{{ __('Masa Garansi (Hari)') }}</label>
                        <div class="input-group">
                            <input type="number" name="warranty_days" id="warrantyDaysAdd" class="form-control" placeholder="{{ __('Contoh: 3') }}" min="1" disabled>
                            <span class="input-group-text bg-light text-muted">{{ __('hari') }}</span>
                        </div>
                        <div class="form-text small">{{ __('Menahan saldo komisi Anda hingga masa garansi berakhir.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Buat Produk') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Worker Modals --}}
@foreach($myProducts as $product)
<div class="modal fade" id="addWorkerModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Tambah Worker ke Produk Anda') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('seller.products.workers.store', $product->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3 bg-light p-3 rounded-4 mb-3">
                        <p class="mb-1 text-muted small">{{ __('Produk Saya') }}</p>
                        <h6 class="fw-bold text-primary m-0">{{ $product->name }}</h6>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Pilih Seller Mitra (Worker)') }}</label>
                        <select name="user_id" class="form-select" required>
                            <option value="" disabled selected>{{ __('-- Pilih Seller Mitra --') }}</option>
                            @foreach($allSellers as $seller)
                                @if(!$product->workers->contains($seller->id))
                                    <option value="{{ $seller->id }}">{{ $seller->full_name ?? $seller->username }} (ID: {{ $seller->telegram_id }})</option>
                                @endif
                            @endforeach
                        </select>
                        <div class="form-text mt-2 small text-muted">
                            <i class="fas fa-info-circle text-info me-1"></i>
                            {{ __('Worker yang terpilih akan diberi akses untuk ikut mengunggah stok dagang milik mereka sendiri pada produk Anda ini.') }}
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Tugaskan Worker') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

@foreach($myProducts as $product)
{{-- Edit Product Modal --}}
<div class="modal fade" id="editProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Edit Informasi Produk') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('seller.products.update', $product->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Nama Produk') }}</label>
                        <input type="text" name="name" class="form-control" value="{{ $product->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Harga Produk (Rupiah)') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">Rp</span>
                            <input type="number" name="price" class="form-control" value="{{ $product->price }}" min="0" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Deskripsi Produk (Opsional)') }}</label>
                        <textarea name="description" class="form-control" rows="3">{{ $product->description }}</textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input toggle-warranty-edit" type="checkbox" role="switch" name="enable_warranty" value="1" id="enableWarrantyEdit{{ $product->id }}" data-target="warrantyDaysEditContainer{{ $product->id }}" {{ $product->warranty_days > 0 ? 'checked' : '' }}>
                        <label class="form-check-label text-muted small fw-bold" for="enableWarrantyEdit{{ $product->id }}">{{ __('Aktifkan garansi?') }}</label>
                    </div>
                    <div class="mb-3" id="warrantyDaysEditContainer{{ $product->id }}" style="display: {{ $product->warranty_days > 0 ? 'block' : 'none' }};">
                        <label class="form-label text-muted small fw-bold">{{ __('Masa Garansi (Hari)') }}</label>
                        <div class="input-group">
                            <input type="number" name="warranty_days" id="warrantyDaysEditInput{{ $product->id }}" class="form-control" value="{{ $product->warranty_days > 0 ? $product->warranty_days : '' }}" min="1" {{ $product->warranty_days > 0 ? '' : 'disabled' }}>
                            <span class="input-group-text bg-light text-muted">{{ __('hari') }}</span>
                        </div>
                        <div class="form-text small">{{ __('Menahan saldo komisi Anda hingga masa garansi berakhir.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Simpan Perubahan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Product Modal --}}
<div class="modal fade" id="deleteProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center" style="border-radius: 16px; border: none;">
            <div class="modal-body p-4">
                <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold">{{ __('Hapus Produk?') }}</h5>
                <p class="text-muted small mb-3">{{ __('Menghapus produk ini akan menghapus semua stok terkait milik Anda secara permanen. Lanjutkan?') }}</p>

                @php
                    $unsoldStockCount = $product->stockUnits()->where('is_sold', false)->count();
                @endphp
                @if($unsoldStockCount > 0)
                <div class="alert alert-warning border-0 rounded-3 text-start small mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-exclamation-circle text-warning fs-5"></i>
                        <span class="fw-bold">{{ __('Perhatian: Sisa Stok Aktif') }}</span>
                    </div>
                    {{ __('Terdapat') }} <strong>{{ $unsoldStockCount }}</strong> {{ __('sisa stok aktif yang belum terjual. Anda disarankan untuk mengunduh sisa stok tersebut sebelum menghapus produk:') }}
                    <div class="mt-2 text-center">
                        <a href="{{ route('seller.products.export-unsold', $product->id) }}" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                            <i class="fas fa-file-excel me-1"></i> {{ __('Unduh Sisa Stok (.xlsx)') }}
                        </a>
                    </div>
                </div>
                @endif

                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <form action="{{ route('seller.products.destroy', $product->id) }}" method="POST" class="m-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger rounded-pill px-4">{{ __('Ya, Hapus') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach

<script>
    document.getElementById('enableWarrantyAdd').addEventListener('change', function() {
        var container = document.getElementById('warrantyDaysAddContainer');
        container.style.display = this.checked ? 'block' : 'none';
        var input = document.getElementById('warrantyDaysAdd');
        if (this.checked) {
            input.removeAttribute('disabled');
            if (input.value === '') {
                input.value = '3';
            }
        } else {
            input.setAttribute('disabled', 'disabled');
            input.value = '';
        }
    });

    document.querySelectorAll('.toggle-warranty-edit').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var targetId = this.getAttribute('data-target');
            var container = document.getElementById(targetId);
            container.style.display = this.checked ? 'block' : 'none';
            var input = container.querySelector('input[type="number"]');
            if (this.checked) {
                input.removeAttribute('disabled');
                if (input.value === '0' || input.value === '') {
                    input.value = '3';
                }
            } else {
                input.setAttribute('disabled', 'disabled');
                input.value = '';
            }
        });
    });
</script>
@endpush
@endsection

@push('styles')
<style>
.max-height-150 {
    max-height: 150px;
}
.overflow-y-auto {
    overflow-y: auto !important;
}
</style>
@endpush
