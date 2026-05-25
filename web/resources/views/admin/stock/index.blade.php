@extends('layouts.app')

@section('title', 'Manajemen Stok')
@section('page_subtitle', 'Stok')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Stok</h4>
        <p class="text-muted mb-0">Kelola stok unit produk digital</p>
    </div>
    <div>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addStockModal">
            <i class="fas fa-plus me-2"></i>Tambah Stok
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($stockUnits->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">ID</th>
                        <th class="py-3 border-0">Produk</th>
                        <th class="py-3 border-0">Konten (Sebagian)</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">Ditambahkan</th>
                        <th class="py-3 border-0 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stockUnits as $unit)
                    <tr>
                        <td class="px-4 fw-bold text-muted">#{{ $unit->id }}</td>
                        <td>{{ Str::limit($unit->product->name ?? 'Unknown', 25) }}</td>
                        <td><code class="text-dark bg-light px-2 py-1 rounded">{{ Str::limit($unit->content ?? $unit->raw_text, 20) }}</code></td>
                        <td>
                            @if($unit->is_sold)
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Terjual</span>
                            @else
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">Tersedia</span>
                            @endif
                        </td>
                        <td class="text-secondary small">{{ $unit->created_at->format('d M Y') }}</td>
                        <td class="text-end px-4">
                            @if(!$unit->is_sold)
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#deleteStockModal{{ $unit->id }}">
                                Hapus
                            </button>
                            @else
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled>Hapus</button>
                            @endif
                        </td>
                    </tr>

                    @if(!$unit->is_sold)
                    {{-- Delete Modal --}}
                    <div class="modal fade" id="deleteStockModal{{ $unit->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-sm">
                            <div class="modal-content text-center" style="border-radius: 16px; border: none;">
                                <div class="modal-body p-4">
                                    <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
                                    <h5 class="fw-bold">Hapus Stok?</h5>
                                    <p class="text-muted small">Stok ini belum terjual. Yakin ingin menghapusnya?</p>
                                    <div class="d-flex gap-2 justify-content-center mt-4">
                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                                        <form action="{{ route('admin.stock.destroy', $unit->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger rounded-pill px-4">Ya, Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $stockUnits->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-cubes text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada stok unit.</p>
        </div>
        @endif
    </div>
</div>

{{-- Add Stock Modal --}}
<div class="modal fade" id="addStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Tambah Stok Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.stock.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Pilih Produk</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Pilih Produk --</option>
                            @php
                                $allProducts = \App\Models\Product::where('is_suspended', false)->get();
                            @endphp
                            @foreach($allProducts as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} (Rp {{ number_format($p->price, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Konten Stok (Pisahkan dengan baris baru)</label>
                        <textarea name="raw_text" class="form-control" rows="5" required placeholder="akun1@email.com:pass1&#10;akun2@email.com:pass2"></textarea>
                        <div class="form-text">Setiap baris baru akan dihitung sebagai 1 unit stok.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Stok</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
