@extends('layouts.app')

@section('title', 'Manajemen Stok Akun')
@section('page_subtitle', 'Stok Akun')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Stok Akun Saya</h4>
        <p class="text-muted mb-0">Unggah stok akun baru dan kelola unit stok aktif milik Anda sendiri.</p>
    </div>
    <div>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#uploadStockModal">
            <i class="fas fa-plus me-1"></i> Unggah Stok Massal
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

{{-- Status Filter Tabs --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-2 d-flex gap-1 overflow-x-auto">
        <a href="{{ route('seller.stock.index') }}" class="btn rounded-pill px-4 {{ !$status ? 'btn-primary' : 'btn-light' }}">Semua Stok</a>
        <a href="{{ route('seller.stock.index', ['status' => 'ready']) }}" class="btn rounded-pill px-4 {{ $status === 'ready' ? 'btn-primary' : 'btn-light' }}">Stok Ready</a>
        <a href="{{ route('seller.stock.index', ['status' => 'saved_for_verification']) }}" class="btn rounded-pill px-4 {{ $status === 'saved_for_verification' ? 'btn-primary' : 'btn-light' }}">Karantina (Simpan Akun)</a>
        <a href="{{ route('seller.stock.index', ['status' => 'terjual']) }}" class="btn rounded-pill px-4 {{ $status === 'terjual' ? 'btn-primary' : 'btn-light' }}">Stok Terjual</a>
    </div>
</div>

{{-- Stock List Table --}}
<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($stocks->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">Produk</th>
                        <th class="py-3 border-0" style="min-width: 250px;">Detail Akun / Raw Text</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">Karantina Sampai</th>
                        <th class="py-3 border-0 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stocks as $stock)
                    <tr>
                        <td class="px-4">
                            <div class="fw-bold text-primary">{{ $stock->product->name }}</div>
                            <div class="small text-muted">Harga: Rp {{ number_format($stock->product->price, 0, ',', '.') }}</div>
                        </td>
                        <td>
                            <code class="small text-dark text-wrap d-block" style="max-width: 350px; background: #f8f9fa; padding: 6px 12px; border-radius: 8px; border: 1px solid #e9ecef; white-space: pre-wrap;">{{ Str::limit($stock->raw_text, 150) }}</code>
                            @if($stock->uploaded_by_id && $stock->uploaded_by_id !== $stock->seller_id)
                                <div class="mt-1 small text-muted">
                                    <i class="fas fa-info-circle text-info me-1"></i> Dialihkan dari uploader asli: 
                                    <strong>{{ $stock->uploader->full_name ?? $stock->uploader->username ?? 'Seller ID: '.$stock->uploaded_by_id }}</strong>
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($stock->is_sold)
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-1"><i class="fas fa-shopping-bag me-1"></i>Terjual</span>
                            @elseif($stock->stock_status === 'ready')
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1"><i class="fas fa-check-circle me-1"></i>Ready</span>
                            @elseif($stock->stock_status === 'saved_for_verification')
                                <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-1"><i class="fas fa-history fa-spin me-1"></i>Simpan Akun</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-1">{{ ucfirst($stock->stock_status) }}</span>
                            @endif
                        </td>
                        <td class="small text-secondary">
                            @if($stock->is_sold)
                                <span class="text-muted">-</span>
                            @elseif($stock->available_at)
                                @if($stock->available_at > now())
                                    <span class="text-warning fw-bold"><i class="far fa-clock me-1"></i> {{ $stock->available_at->diffForHumans() }}</span>
                                    <div class="small text-muted" style="font-size: 0.75rem;">({{ $stock->available_at->format('d M H:i') }})</div>
                                @else
                                    <span class="text-success"><i class="fas fa-check-circle me-1"></i>Selesai Karantina</span>
                                @endif
                            @else
                                <span class="text-muted">- (Langsung Ready)</span>
                            @endif
                        </td>
                        <td class="text-end px-4">
                            @if(!$stock->is_sold)
                                <form action="{{ route('seller.stock.destroy', $stock->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-2" title="Hapus Stok" onclick="confirmAction(event, 'Yakin ingin menghapus stok unit ini? Aksi ini permanen.')">
                                        <i class="fas fa-trash fa-fw"></i>
                                    </button>
                                </form>
                            @else
                                <button class="btn btn-sm btn-light rounded-pill px-3" disabled>Terjual</button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $stocks->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-cubes text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Tidak ada data stok yang ditemukan.</p>
        </div>
        @endif
    </div>
</div>

@push('modals')
{{-- Upload Stock Modal --}}
<div class="modal fade" id="uploadStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold"><i class="fas fa-cloud-upload-alt text-primary me-2"></i>Unggah Stok Akun Massal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('seller.stock.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Pilih Produk</label>
                        <select name="product_id" class="form-select" required>
                            <option value="" disabled selected>-- Pilih Produk Katalog --</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} (Harga: Rp {{ number_format($product->price, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                        <div class="form-text mt-1">Daftar produk di atas mencakup produk buatan Anda sendiri serta produk milik admin/seller lain yang mencantumkan Anda sebagai **Worker**.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Raw Text Stok Akun (Pisahkan dengan baris kosong)</label>
                        <textarea name="raw_text" class="form-control font-monospace small" rows="8" placeholder="Format pengunggahan massal. Contoh:

Akun #1
Username: user1
Password: pass1
Detail: email@example.com

Akun #2
Username: user2
Password: pass2
Detail: email2@example.com" required style="border-radius: 12px; font-size: 0.85rem; padding: 12px;"></textarea>
                        <div class="form-text mt-2 text-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            PENTING: Pisahkan masing-masing stok akun secara jelas dengan **menambahkan baris kosong (double enter)**. Setiap bagian terpisah akan diinput menjadi satu unit stok tersendiri.
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded-4 small text-muted">
                        <i class="fas fa-info-circle text-primary me-1"></i>
                        Stok yang baru diunggah akan otomatis masuk ke **karantina (Simpan Akun)** selama <strong>{{ Auth::user()->seller_save_hours ?? 80 }} jam</strong> sesuai dengan pengaturan profil Anda sebelum siap dijual (*Ready*).
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Mulai Unggah Stok</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush
@endsection
