@extends('layouts.app')

@section('title', 'Kelola Kupon Diskon')
@section('page_subtitle', 'Kupon & Promosi')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Kupon & Promosi</h4>
        <p class="text-muted mb-0">Kelola kode diskon, persentase potongan harga, dan batasan penggunaan kupon.</p>
    </div>
    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createCouponModal">
        <i class="fas fa-plus-circle me-1"></i> Tambah Kupon
    </button>
</div>

{{-- Flash Messages --}}
@if(session('success'))
<div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2 mb-4" style="border-radius: 12px;">
    <i class="fas fa-check-circle fs-5"></i>
    <div>{{ session('success') }}</div>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 12px;">
    <div class="d-flex align-items-center gap-2 mb-2">
        <i class="fas fa-exclamation-circle fs-5"></i>
        <strong class="small">Periksa input Anda kembali:</strong>
    </div>
    <ul class="mb-0 small ps-4">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- Coupon Cards & Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="p-3 bg-primary-subtle text-primary rounded-3">
                    <i class="fas fa-ticket-alt fs-4"></i>
                </div>
                <div>
                    <h6 class="text-muted small mb-1">TOTAL KUPON</h6>
                    <h4 class="fw-bold mb-0">{{ $coupons->total() }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="p-3 bg-success-subtle text-success rounded-3">
                    <i class="fas fa-check-double fs-4"></i>
                </div>
                <div>
                    <h6 class="text-muted small mb-1">KUPON AKTIF</h6>
                    <h4 class="fw-bold mb-0">{{ \App\Models\Coupon::where('is_active', true)->count() }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="p-3 bg-warning-subtle text-warning rounded-3">
                    <i class="fas fa-chart-line fs-4"></i>
                </div>
                <div>
                    <h6 class="text-muted small mb-1">PENGGUNAAN KUPON</h6>
                    <h4 class="fw-bold mb-0">{{ \App\Models\Coupon::sum('used_qty') }} kali</h4>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Coupons Table --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead class="table-light">
                    <tr>
                        <th class="px-4 py-3">Kode Kupon</th>
                        <th class="py-3">Tipe & Nilai</th>
                        <th class="py-3">Min Belanja</th>
                        <th class="py-3">Limit Penggunaan</th>
                        <th class="py-3">Tgl Kadaluarsa</th>
                        <th class="py-3">Status</th>
                        <th class="px-4 py-3 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coupons as $coupon)
                    <tr>
                        <td class="px-4">
                            <span class="badge bg-primary-subtle text-primary font-monospace fs-6 px-3 py-2" style="border-radius: 8px;">
                                {{ $coupon->code }}
                            </span>
                        </td>
                        <td>
                            @if($coupon->type === 'percent')
                                <span class="fw-bold">{{ $coupon->value }}%</span> 
                                <span class="text-muted small">(Maks Rp{{ number_format($coupon->max_discount, 0, ',', '.') }})</span>
                            @else
                                <span class="fw-bold">Rp{{ number_format($coupon->value, 0, ',', '.') }}</span>
                            @endif
                        </td>
                        <td>
                            Rp{{ number_format($coupon->min_spend, 0, ',', '.') }}
                        </td>
                        <td>
                            <div class="progress" style="height: 6px; width: 100px; margin-bottom: 2px;">
                                @php
                                    $percent = $coupon->qty > 0 ? min(100, ($coupon->used_qty / $coupon->qty) * 100) : 0;
                                @endphp
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%"></div>
                            </div>
                            <span class="small text-muted">{{ $coupon->used_qty }} / {{ $coupon->qty > 0 ? $coupon->qty : '∞' }}</span>
                        </td>
                        <td>
                            @if($coupon->expires_at)
                                <span class="{{ $coupon->expires_at->isPast() ? 'text-danger fw-bold' : '' }}">
                                    {{ $coupon->expires_at->format('d M Y, H:i') }}
                                </span>
                            @else
                                <span class="text-muted">Tidak kadaluarsa</span>
                            @endif
                        </td>
                        <td>
                            @if($coupon->is_active && (!$coupon->expires_at || !$coupon->expires_at->isPast()) && ($coupon->qty == 0 || $coupon->used_qty < $coupon->qty))
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">Aktif</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1">Tidak Aktif</span>
                            @endif
                        </td>
                        <td class="px-4 text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editCouponModal-{{ $coupon->id }}">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form action="{{ route('admin.coupons.destroy', $coupon->id) }}" method="POST" class="d-inline" onsubmit="confirmAction(event, 'Hapus kupon ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
                                    <i class="fas fa-trash-alt"></i> Hapus
                                </button>
                            </form>
                        </td>
                    </tr>

                    {{-- Edit Coupon Modal --}}
                    <div class="modal fade" id="editCouponModal-{{ $coupon->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                                <div class="modal-header bg-light border-0">
                                    <h5 class="modal-title fw-bold">Edit Kupon: {{ $coupon->code }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="{{ route('admin.coupons.update', $coupon->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-body p-4">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">KODE KUPON (UPPERCASE)</label>
                                            <input type="text" name="code" class="form-control" value="{{ $coupon->code }}" required style="border-radius: 10px;">
                                        </div>
                                        <div class="row g-3 mb-3">
                                            <div class="col-6">
                                                <label class="form-label small fw-bold">TIPE DISKON</label>
                                                <select name="type" class="form-select" required style="border-radius: 10px;">
                                                    <option value="fixed" {{ $coupon->type === 'fixed' ? 'selected' : '' }}>Nominal Tetap (Rp)</option>
                                                    <option value="percent" {{ $coupon->type === 'percent' ? 'selected' : '' }}>Persentase (%)</option>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small fw-bold">NILAI DISKON</label>
                                                <input type="number" name="value" class="form-control" value="{{ $coupon->value }}" required min="1" style="border-radius: 10px;">
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-3">
                                            <div class="col-6">
                                                <label class="form-label small fw-bold">MIN. BELANJA (Rp)</label>
                                                <input type="number" name="min_spend" class="form-control" value="{{ $coupon->min_spend }}" required min="0" style="border-radius: 10px;">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small fw-bold">MAKS. DISKON (Rp, Opsional)</label>
                                                <input type="number" name="max_discount" class="form-control" value="{{ $coupon->max_discount }}" min="1" style="border-radius: 10px;">
                                            </div>
                                        </div>
                                        <div class="row g-3 mb-3">
                                            <div class="col-6">
                                                <label class="form-label small fw-bold">KUOTA PENGGUNAAN</label>
                                                <input type="number" name="qty" class="form-control" value="{{ $coupon->qty }}" required min="0" style="border-radius: 10px;">
                                                <span class="small text-muted">Isi 0 untuk tanpa batas</span>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small fw-bold">TGL KADALUARSA</label>
                                                <input type="datetime-local" name="expires_at" class="form-control" value="{{ $coupon->expires_at ? $coupon->expires_at->format('Y-m-d\TH:i') : '' }}" style="border-radius: 10px;">
                                            </div>
                                        </div>
                                        <div class="form-check form-switch mt-3">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive-{{ $coupon->id }}" value="1" {{ $coupon->is_active ? 'checked' : '' }}>
                                            <label class="form-check-label small fw-bold" for="editIsActive-{{ $coupon->id }}">Aktifkan Kupon</label>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light border-0">
                                        <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fas fa-ticket-alt fs-2 mb-2 text-secondary"></i><br>
                            Belum ada kupon diskon yang dibuat.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Pagination --}}
<div class="d-flex justify-content-center">
    {{ $coupons->links() }}
</div>

{{-- Create Coupon Modal --}}
<div class="modal fade" id="createCouponModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">Tambah Kupon Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.coupons.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">KODE KUPON (UPPERCASE)</label>
                        <input type="text" name="code" class="form-control" placeholder="MISAL: PROMOHEBAT" required style="border-radius: 10px;">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">TIPE DISKON</label>
                            <select name="type" class="form-select" required style="border-radius: 10px;">
                                <option value="fixed" selected>Nominal Tetap (Rp)</option>
                                <option value="percent">Persentase (%)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">NILAI DISKON</label>
                            <input type="number" name="value" class="form-control" placeholder="10000 atau 10" required min="1" style="border-radius: 10px;">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">MIN. BELANJA (Rp)</label>
                            <input type="number" name="min_spend" class="form-control" value="0" required min="0" style="border-radius: 10px;">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">MAKS. DISKON (Rp, Opsional)</label>
                            <input type="number" name="max_discount" class="form-control" placeholder="Hanya untuk Persentase" min="1" style="border-radius: 10px;">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">KUOTA PENGGUNAAN</label>
                            <input type="number" name="qty" class="form-control" value="100" required min="0" style="border-radius: 10px;">
                            <span class="small text-muted">Isi 0 untuk tanpa batas</span>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">TGL KADALUARSA</label>
                            <input type="datetime-local" name="expires_at" class="form-control" style="border-radius: 10px;">
                        </div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="createIsActive" value="1" checked>
                        <label class="form-check-label small fw-bold" for="createIsActive">Aktifkan Kupon</label>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Buat Kupon</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
