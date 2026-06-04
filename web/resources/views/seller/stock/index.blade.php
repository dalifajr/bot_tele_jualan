@extends('layouts.app')

@section('title', 'Manajemen Stok')
@section('page_subtitle', 'Stok Saya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Stok Saya</h4>
        <p class="text-muted mb-0">Kelola stok unit produk digital Anda</p>
    </div>
    <div>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addStockModal">
            <i class="fas fa-plus me-2"></i>Tambah Stok
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

{{-- Stock Metrics Row --}}
<div class="row g-3 mb-4">
    <div class="col-xl col-md-4 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">Total Stok</div>
                <h3 class="fw-bold mb-0 text-primary">{{ $totalStock }}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">Ready</div>
                <h3 class="fw-bold mb-0 text-success">{{ $readyStock }}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">Awaiting</div>
                <h3 class="fw-bold mb-0 text-warning">{{ $awaitingStock }}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">Simpan Akun</div>
                <h3 class="fw-bold mb-0 text-info">{{ $savedStock }}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">Terjual</div>
                <h3 class="fw-bold mb-0 text-secondary">{{ $soldStock }}</h3>
            </div>
        </div>
    </div>
</div>

{{-- Filters Row --}}
<div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
    <div class="card-body p-3">
        <form action="{{ route('seller.stock.index') }}" method="GET" class="row g-2 align-items-end">
            {{-- Status Filter --}}
            <input type="hidden" name="status" value="{{ request('status') }}">
            
            {{-- Product Filter --}}
            <div class="col-md-4 col-6">
                <label class="form-label text-muted small fw-bold mb-1">Produk</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">Semua Produk</option>
                    @foreach($products as $fp)
                    <option value="{{ $fp->id }}" {{ request('product_id') == $fp->id ? 'selected' : '' }}>{{ $fp->name }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Search --}}
            <div class="col-md-5 col-6">
                <label class="form-label text-muted small fw-bold mb-1">Pencarian</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Username, konten, kata kunci..." value="{{ request('search') }}">
            </div>
            {{-- Submit --}}
            <div class="col-md-3 col-12">
                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill flex-fill"><i class="fas fa-search me-1"></i>Filter</button>
                    <a href="{{ route('seller.stock.index', ['status' => request('status')]) }}" class="btn btn-sm btn-outline-secondary rounded-pill" title="Reset"><i class="fas fa-times"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden mb-4" style="border-radius: 16px;">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
        <ul class="nav nav-tabs border-bottom-0" style="margin-bottom: -1px;">
            <li class="nav-item">
                <a class="nav-link {{ request('status') === null && !request('product_id') && !request('search') ? 'active border-primary border-bottom-0 text-primary fw-bold' : 'text-muted' }}" href="{{ request()->fullUrlWithQuery(['status' => null]) }}">Semua</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('status') === 'ready' ? 'active border-primary border-bottom-0 text-primary fw-bold' : 'text-muted' }}" href="{{ request()->fullUrlWithQuery(['status' => 'ready']) }}">Ready</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('status') === 'saved_for_verification' ? 'active border-primary border-bottom-0 text-primary fw-bold' : 'text-muted' }}" href="{{ request()->fullUrlWithQuery(['status' => 'saved_for_verification']) }}">Simpan Akun</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('status') === 'terjual' ? 'active border-primary border-bottom-0 text-primary fw-bold' : 'text-muted' }}" href="{{ request()->fullUrlWithQuery(['status' => 'terjual']) }}">Terjual</a>
            </li>
        </ul>
    </div>
    <div class="card-body p-0 border-top">
        @if($stocks->count() > 0)
        @if(request('status') !== 'terjual')
        {{-- Bulk Action Bar --}}
        <div id="bulk-actions-bar" class="bg-light border-bottom p-3 d-none align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark"><span id="selected-count">0</span> akun terpilih</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#bulkMoveModal">
                    <i class="fas fa-exchange-alt me-1"></i>Ubah Status Masal
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                    <i class="fas fa-trash-alt me-1"></i>Hapus Masal
                </button>
            </div>
        </div>
        @endif
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        @if(request('status') !== 'terjual')
                        <th class="px-4 py-3 border-0" style="width: 40px;">
                            <input type="checkbox" id="select-all-stocks" class="form-check-input">
                        </th>
                        <th class="py-3 border-0">ID</th>
                        @else
                        <th class="px-4 py-3 border-0">ID</th>
                        @endif
                        <th class="py-3 border-0">Produk</th>
                        <th class="py-3 border-0">Username</th>
                        @if(request('status') === 'terjual')
                        <th class="py-3 border-0">Pembeli</th>
                        <th class="py-3 border-0">ID Telegram</th>
                        <th class="py-3 border-0">Waktu Transaksi</th>
                        @else
                        <th class="py-3 border-0">Konten (Sebagian)</th>
                        <th class="py-3 border-0">Status</th>
                        @if(request('status') === 'saved_for_verification')
                        <th class="py-3 border-0">Dapat Diverifikasi Pada</th>
                        @else
                        <th class="py-3 border-0">Ditambahkan</th>
                        @endif
                        @endif
                        <th class="py-3 border-0 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stocks as $unit)
                    @php
                        $extractedUsername = '-';
                        if (preg_match('/Username:\s*([^\n]+)/i', $unit->raw_text, $matches)) {
                            $extractedUsername = trim($matches[1]);
                        }
                    @endphp
                    <tr>
                        @if(request('status') !== 'terjual')
                        <td class="px-4">
                            <input type="checkbox" value="{{ $unit->id }}" class="form-check-input stock-checkbox" {{ $unit->is_sold ? 'disabled' : '' }}>
                        </td>
                        <td class="fw-bold text-muted">#{{ $unit->id }}</td>
                        @else
                        <td class="px-4 fw-bold text-muted">#{{ $unit->id }}</td>
                        @endif
                        <td>{{ Str::limit($unit->product->name ?? 'Unknown', 25) }}</td>
                        <td class="fw-medium text-dark">{{ Str::limit($extractedUsername, 20) }}</td>
                        
                        @if(request('status') === 'terjual')
                        <td>
                            @if($unit->order && $unit->order->customer)
                                {{ $unit->order->customer->full_name ?? $unit->order->customer->username ?? 'Unknown User' }}
                                @if($unit->order->customer->username)
                                    <br><small class="text-muted">{{ '@'.$unit->order->customer->username }}</small>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($unit->order && $unit->order->customer)
                                <code>{{ $unit->order->customer->telegram_id }}</code>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-secondary small">
                            {{ $unit->order ? $unit->order->created_at->format('d M Y H:i') : '-' }}
                        </td>
                        @else
                        <td><code class="text-dark bg-light px-2 py-1 rounded">{{ Str::limit($unit->content ?? $unit->raw_text, 20) }}</code></td>
                        <td>
                            @if($unit->is_sold)
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Terjual</span>
                            @else
                                @php
                                    $statusBadge = match($unit->stock_status) {
                                        'ready' => ['bg' => 'success-subtle', 'text' => 'success', 'label' => 'Ready'],
                                        'saved_for_verification' => ['bg' => 'info-subtle', 'text' => 'info', 'label' => 'Simpan Akun'],
                                        'saved_ready_notified' => ['bg' => 'primary-subtle', 'text' => 'primary', 'label' => 'Siap Diajukan'],
                                        default => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => $unit->stock_status]
                                    };
                                @endphp
                                <span class="badge bg-{{ $statusBadge['bg'] }} text-{{ $statusBadge['text'] }} rounded-pill px-3">{{ $statusBadge['label'] }}</span>
                            @endif
                        </td>
                        @if(request('status') === 'saved_for_verification')
                        <td class="text-secondary small fw-bold">
                            @php
                                $verifyAt = $unit->available_at ?: $unit->created_at->addHours((int)Auth::user()->seller_save_hours);
                            @endphp
                            @if($unit->stock_status === 'saved_ready_notified' || $verifyAt->isPast())
                                <span class="text-success"><i class="fas fa-check-circle me-1"></i>Siap Diajukan</span>
                            @else
                                <span class="verification-countdown" data-timestamp="{{ $verifyAt->timestamp }}" data-id="{{ $unit->id }}">
                                    {{ $verifyAt->format('d M Y H:i') }}
                                </span>
                            @endif
                        </td>
                        @else
                        <td class="text-secondary small">{{ $unit->created_at->format('d M Y') }}</td>
                        @endif
                        @endif
                        <td class="text-end px-4">
                            <div class="d-flex gap-2 justify-content-end">
                                <button class="btn btn-sm btn-light text-info rounded-circle" data-bs-toggle="modal" data-bs-target="#detailStockModal{{ $unit->id }}" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-light text-primary rounded-circle" data-bs-toggle="modal" data-bs-target="#moveStockModal{{ $unit->id }}" title="Pindahkan / Ubah Status">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                                @if(!$unit->is_sold)
                                <button class="btn btn-sm btn-light text-danger rounded-circle" data-bs-toggle="modal" data-bs-target="#deleteStockModal{{ $unit->id }}" title="Hapus">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                @else
                                <button class="btn btn-sm btn-light text-secondary rounded-circle" disabled title="Tidak bisa dihapus"><i class="fas fa-trash-alt"></i></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $stocks->withQueryString()->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-cubes text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada stok unit.</p>
        </div>
        @endif
    </div>
</div>

@push('modals')
{{-- Add Stock Modal --}}
<div class="modal fade" id="addStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Tambah Stok Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('seller.stock.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Pilih Produk</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Pilih Produk --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} (Rp {{ number_format($p->price, 0, ',', '.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Status Awal Stok</label>
                        <select name="stock_status" class="form-select" required>
                            <option value="ready">Ready</option>
                            <option value="saved_for_verification">Simpan Akun (Karantina)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Data Akun (Mendukung Multi-baris)</label>
                        <textarea name="raw_text" class="form-control" rows="5" required placeholder="Username: x&#10;Password: y&#10;2FA: z&#10;&#10;Username: a&#10;Password: b..."></textarea>
                        <div class="form-text">Pisahkan antar akun dengan <b>baris kosong (Enter 2x)</b> jika ingin menginput banyak akun sekaligus.</div>
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

@foreach($stocks as $unit)
{{-- Detail Stock Modal --}}
<div class="modal fade" id="detailStockModal{{ $unit->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Detail Data Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Produk</label>
                    <p class="mb-0">{{ $unit->product->name ?? '-' }}</p>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-bold text-muted small">Isi Data</label>
                    <div class="bg-light rounded-3 p-3 text-break" style="max-height: 300px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; font-size: 0.85rem;">{{ $unit->raw_text }}</div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- Move Stock Modal --}}
<div class="modal fade" id="moveStockModal{{ $unit->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Ubah Status / Pindah Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('seller.stock.move', $unit->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Pindah ke Produk</label>
                        <select name="product_id" class="form-select">
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ $unit->product_id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Ubah Status</label>
                        <select name="stock_status" class="form-select">
                            <option value="ready" {{ $unit->stock_status === 'ready' && !$unit->is_sold ? 'selected' : '' }}>Ready</option>
                            <option value="saved_for_verification" {{ $unit->stock_status === 'saved_for_verification' && !$unit->is_sold ? 'selected' : '' }}>Simpan Akun</option>
                            <option value="terjual" {{ $unit->is_sold ? 'selected' : '' }}>Terjual</option>
                        </select>
                        <div class="form-text">Mengubah ke status "Simpan Akun" akan menjadwal ulang akun ini sesuai dengan konfigurasi jam karantina profil Anda.</div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                    <form action="{{ route('seller.stock.destroy', $unit->id) }}" method="POST">
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

@if(request('status') !== 'terjual')
{{-- Bulk Move Modal --}}
<div class="modal fade" id="bulkMoveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Ubah Status Masal (<span class="bulk-selected-count">0</span> Akun)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('seller.stock.bulkMove') }}" method="POST" id="bulk-move-form">
                @csrf
                <input type="hidden" name="ids" id="bulk-move-ids">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Pindahkan ke Produk (Opsional)</label>
                        <select name="product_id" class="form-select">
                            <option value="">-- Pertahankan Produk Asli --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Ubah Status Ke</label>
                        <select name="stock_status" class="form-select" required>
                            <option value="ready">Ready</option>
                            <option value="saved_for_verification">Simpan Akun</option>
                        </select>
                        <div class="form-text">Mengubah status massal akan menjadwal ulang masa karantina akun-akun tersebut sesuai pengaturan jam karantina Anda.</div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Terapkan Masal</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Delete Modal --}}
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center" style="border-radius: 16px; border: none;">
            <div class="modal-body p-4">
                <i class="fas fa-trash-alt text-danger mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold">Hapus Masal?</h5>
                <p class="text-muted small">Anda akan menghapus <span class="bulk-selected-count">0</span> akun yang dipilih secara permanen.</p>
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <form action="{{ route('seller.stock.bulkDestroy') }}" method="POST" id="bulk-delete-form">
                        @csrf
                        <input type="hidden" name="ids" id="bulk-delete-ids">
                        <button type="submit" class="btn btn-danger rounded-pill px-4">Ya, Hapus Masal</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Bulk Action Logic
        const selectAllCheckbox = document.getElementById('select-all-stocks');
        const stockCheckboxes = document.querySelectorAll('.stock-checkbox');
        const bulkActionBar = document.getElementById('bulk-actions-bar');
        const selectedCountSpan = document.getElementById('selected-count');
        const bulkSelectedCountSpans = document.querySelectorAll('.bulk-selected-count');
        const bulkMoveIdsInput = document.getElementById('bulk-move-ids');
        const bulkDeleteIdsInput = document.getElementById('bulk-delete-ids');

        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.stock-checkbox:checked');
            const count = checkedBoxes.length;

            if (count > 0) {
                if (bulkActionBar) {
                    bulkActionBar.classList.remove('d-none');
                    bulkActionBar.classList.add('d-flex');
                }
                if (selectedCountSpan) selectedCountSpan.textContent = count;
                bulkSelectedCountSpans.forEach(span => span.textContent = count);

                const ids = Array.from(checkedBoxes).map(cb => cb.value);
                const idsString = JSON.stringify(ids);
                if (bulkMoveIdsInput) bulkMoveIdsInput.value = idsString;
                if (bulkDeleteIdsInput) bulkDeleteIdsInput.value = idsString;
            } else {
                if (bulkActionBar) {
                    bulkActionBar.classList.add('d-none');
                    bulkActionBar.classList.remove('d-flex');
                }
                if (bulkMoveIdsInput) bulkMoveIdsInput.value = '';
                if (bulkDeleteIdsInput) bulkDeleteIdsInput.value = '';
            }
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                stockCheckboxes.forEach(cb => {
                    if (!cb.disabled) {
                        cb.checked = isChecked;
                    }
                });
                updateBulkActions();
            });
        }

        stockCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                if (selectAllCheckbox) {
                    const allUnsoldCount = document.querySelectorAll('.stock-checkbox:not(:disabled)').length;
                    const checkedUnsoldCount = document.querySelectorAll('.stock-checkbox:checked:not(:disabled)').length;
                    selectAllCheckbox.checked = (allUnsoldCount === checkedUnsoldCount && allUnsoldCount > 0);
                }
                updateBulkActions();
            });
        });

        // Countdown Logic
        const countdownElements = document.querySelectorAll('.verification-countdown');
        let notificationShown = false; // Prevents spamming toasts if multiple accounts are ready
        
        if (countdownElements.length > 0) {
            function updateCountdowns() {
                const now = Math.floor(Date.now() / 1000);
                let newReadyCount = 0;
                
                countdownElements.forEach(el => {
                    if (el.dataset.ready === 'true') return;
                    
                    const targetTimestamp = parseInt(el.dataset.timestamp);
                    const diff = targetTimestamp - now;
                    
                    if (diff <= 0) {
                        el.dataset.ready = 'true';
                        el.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Siap Diverifikasi</span>';
                        newReadyCount++;
                    } else {
                        const hours = Math.floor(diff / 3600);
                        const minutes = Math.floor((diff % 3600) / 60);
                        const seconds = diff % 60;
                        
                        let timeString = '';
                        if (hours > 0) timeString += hours + 'j ';
                        if (minutes > 0 || hours > 0) timeString += minutes + 'm ';
                        timeString += seconds + 'd';
                        
                        el.innerHTML = '<span class="text-info"><i class="fas fa-clock me-1"></i>' + timeString + '</span>';
                    }
                });
                
                if (newReadyCount > 0 && !notificationShown && typeof Swal !== 'undefined') {
                    notificationShown = true;
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                    });
                    Toast.fire({
                        icon: 'info',
                        title: 'Ada stok akun yang sudah siap diverifikasi!'
                    });
                }
            }
            
            updateCountdowns();
            setInterval(updateCountdowns, 1000);
        }
    });
</script>
@endpush
@endsection
