@extends('layouts.app')

@section('title', 'Manajemen Produk')
@section('page_subtitle', 'Produk')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Produk</h4>
        <p class="text-muted mb-0">Kelola katalog produk digital</p>
    </div>
    <div>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="fas fa-plus me-2"></i>Tambah Produk
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($products->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">ID</th>
                        <th class="py-3 border-0">Nama Produk</th>
                        <th class="py-3 border-0">Harga</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">Dibuat</th>
                        <th class="py-3 border-0 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    <tr>
                        <td class="px-4 fw-bold text-muted">#{{ $product->id }}</td>
                        <td class="fw-bold text-primary">{{ $product->name }}</td>
                        <td>{{ $product->formatted_price }}</td>
                        <td>
                            @if($product->is_suspended)
                            <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Suspended</span>
                            @else
                            <span class="badge bg-success-subtle text-success rounded-pill px-3">Active</span>
                            @endif
                        </td>
                        <td class="text-secondary small">{{ $product->created_at->format('d M Y') }}</td>
                        <td class="px-4">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="{{ route('admin.products.manage', $product->id) }}" class="btn btn-sm btn-light text-primary rounded-pill px-3" title="Detail & Aksi">
                                    <i class="fas fa-cog"></i> Aksi
                                </a>
                                <button class="btn btn-sm btn-light text-primary rounded-circle" data-bs-toggle="modal" data-bs-target="#editProductModal{{ $product->id }}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-light text-danger rounded-circle" data-bs-toggle="modal" data-bs-target="#deleteProductModal{{ $product->id }}" title="Hapus">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>


                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $products->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-box text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada produk.</p>
        </div>
        @endif
    </div>
</div>

@push('modals')
{{-- Add Product Modal --}}
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Tambah Produk Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.products.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Nama Produk</label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Netflix Premium 1 Bulan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Harga (Rp)</label>
                        <input type="number" name="price" class="form-control" required placeholder="Contoh: 35000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Informasi produk..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Tambahkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($products as $product)
{{-- Edit Modal --}}
<div class="modal fade" id="editProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Edit Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.products.update', $product->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Nama Produk</label>
                        <input type="text" name="name" class="form-control" value="{{ $product->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Harga (Rp)</label>
                        <input type="number" name="price" class="form-control" value="{{ $product->price }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3">{{ $product->description }}</textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_suspended" id="suspend{{ $product->id }}" {{ $product->is_suspended ? 'checked' : '' }}>
                        <label class="form-check-label" for="suspend{{ $product->id }}">Suspend (Sembunyikan dari katalog)</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div class="modal fade" id="deleteProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center" style="border-radius: 16px; border: none;">
            <div class="modal-body p-4">
                <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold">Hapus Produk?</h5>
                <p class="text-muted small">Menghapus produk akan turut menghapus semua stok yang terkait dengannya. Lanjutkan?</p>
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger rounded-pill px-4">Ya, Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach
@endpush
@endsection
