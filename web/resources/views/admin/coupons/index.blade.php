@extends('layouts.app')

@section('title', __('Kelola Kupon Diskon'))
@section('page_subtitle', __('Kupon & Promosi'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Kupon & Promosi') }}</h4>
        <p class="text-muted mb-0">{{ __('Kelola kode diskon, batasan produk, dan kuota pemakaian kupon.') }}</p>
    </div>
    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#createCouponModal">
        <i class="fas fa-plus-circle me-1"></i> {{ __('Tambah Kupon') }}
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
        <strong class="small">{{ __('Periksa input Anda kembali:') }}</strong>
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
                    <h6 class="text-muted small mb-1">{{ __('TOTAL KUPON') }}</h6>
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
                    <h6 class="text-muted small mb-1">{{ __('KUPON AKTIF') }}</h6>
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
                    <h6 class="text-muted small mb-1">{{ __('PENGGUNAAN KUPON') }}</h6>
                    <h4 class="fw-bold mb-0">{{ \App\Models\Coupon::sum('used_qty') }} {{ __('kali') }}</h4>
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
                        <th class="px-4 py-3">{{ __('Kode Kupon') }}</th>
                        <th class="py-3">{{ __('Tipe & Nilai') }}</th>
                        <th class="py-3">{{ __('Pembuat') }}</th>
                        <th class="py-3">{{ __('Berlaku Untuk') }}</th>
                        <th class="py-3">{{ __('Min Belanja') }}</th>
                        <th class="py-3">{{ __('Limit Penggunaan') }}</th>
                        <th class="py-3">{{ __('Tgl Kadaluarsa') }}</th>
                        <th class="py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Aksi') }}</th>
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
                                <span class="fw-bold text-success">{{ $coupon->value }}%</span> 
                                <span class="text-muted small">({{ __('Maks') }} Rp{{ number_format($coupon->max_discount, 0, ',', '.') }})</span>
                            @else
                                <span class="fw-bold text-success">Rp{{ number_format($coupon->value, 0, ',', '.') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($coupon->creator)
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge {{ $coupon->creator->role === 'admin' ? 'bg-primary' : 'bg-info' }} rounded-pill px-2 py-1">
                                        {{ $coupon->creator->role === 'admin' ? __('Admin') : __('Seller') }}
                                    </span>
                                    <span class="small fw-semibold text-truncate" style="max-width: 120px;" title="{{ $coupon->creator->full_name ?? $coupon->creator->username }}">
                                        {{ $coupon->creator->full_name ?? $coupon->creator->username }}
                                    </span>
                                </div>
                            @else
                                <span class="badge bg-secondary rounded-pill px-2 py-1">{{ __('Platform') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($coupon->products->count() > 0)
                                <span class="badge bg-info-subtle text-info rounded-pill px-2 py-1" data-bs-toggle="tooltip" data-bs-html="true" title="{{ $coupon->products->pluck('name')->implode('<br>') }}">
                                    <i class="fas fa-boxes me-1"></i>{{ $coupon->products->count() }} {{ __('Produk') }}
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1">
                                    <i class="fas fa-globe me-1"></i>{{ __('Semua Produk') }}
                                </span>
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
                                <span class="text-muted">{{ __('Tidak kadaluarsa') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($coupon->is_active && (!$coupon->expires_at || !$coupon->expires_at->isPast()) && ($coupon->qty == 0 || $coupon->used_qty < $coupon->qty))
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1">{{ __('Aktif') }}</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1">{{ __('Tidak Aktif') }}</span>
                            @endif
                        </td>
                        <td class="px-4 text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editCouponModal-{{ $coupon->id }}">
                                <i class="fas fa-edit"></i> {{ __('Edit') }}
                            </button>
                            <form action="{{ route('admin.coupons.destroy', $coupon->id) }}" method="POST" class="d-inline" onsubmit="confirmAction(event, 'Hapus kupon ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
                                    <i class="fas fa-trash-alt"></i> {{ __('Hapus') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="fas fa-ticket-alt fs-2 mb-2 text-secondary"></i><br>
                            {{ __('Belum ada kupon diskon yang dibuat.') }}
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
@endsection

@push('modals')
{{-- Create Coupon Modal --}}
<div class="modal fade" id="createCouponModal" tabindex="-1" aria-labelledby="createCouponModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 bg-primary text-white p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white bg-opacity-20 p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fas fa-ticket-alt fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="createCouponModalLabel">{{ __('Tambah Kupon Baru') }}</h5>
                        <p class="small text-white-50 mb-0">{{ __('Tentukan kode promo, diskon, dan cakupan produk.') }}</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.coupons.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('KODE KUPON (UPPERCASE)') }} <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control font-monospace" placeholder="{{ __('MISAL: PROMODISKON') }}" required style="border-radius: 10px; text-transform: uppercase;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('TIPE DISKON') }} <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required style="border-radius: 10px;">
                                <option value="fixed" selected>{{ __('Nominal Tetap (Rp)') }}</option>
                                <option value="percent">{{ __('Persentase (%)') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('NILAI DISKON') }} <span class="text-danger">*</span></label>
                            <input type="number" name="value" class="form-control" placeholder="{{ __('10000 atau 10') }}" required min="1" style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('MIN. BELANJA (Rp)') }} <span class="text-danger">*</span></label>
                            <input type="number" name="min_spend" class="form-control" value="0" required min="0" style="border-radius: 10px;">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('MAKS. DISKON (Rp, Opsional)') }}</label>
                            <input type="number" name="max_discount" class="form-control" placeholder="{{ __('Hanya untuk tipe Persentase') }}" min="1" style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('KUOTA PENGGUNAAN') }} <span class="text-danger">*</span></label>
                            <input type="number" name="qty" class="form-control" value="100" required min="0" style="border-radius: 10px;">
                            <span class="small text-muted">{{ __('Isi 0 untuk tanpa batas kuota') }}</span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('TGL KADALUARSA') }}</label>
                            <input type="datetime-local" name="expires_at" class="form-control" style="border-radius: 10px;">
                            <span class="small text-muted">{{ __('Kosongkan jika tidak ada batas waktu') }}</span>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="createIsActive" value="1" checked>
                                <label class="form-check-label small fw-bold" for="createIsActive">{{ __('Aktifkan Kupon Ini') }}</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- Product Scope Selection --}}
                    <div class="mb-3">
                        <label class="form-label small fw-bold d-block">{{ __('CAKUPAN PRODUK') }} <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scope" id="createScopeAll" value="all" checked onchange="toggleScopeProducts('create', false)">
                                <label class="form-check-label small fw-semibold" for="createScopeAll">
                                    <i class="fas fa-globe text-muted me-1"></i> {{ __('Semua Produk (Global)') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scope" id="createScopeSpecific" value="specific" onchange="toggleScopeProducts('create', true)">
                                <label class="form-check-label small fw-semibold" for="createScopeSpecific">
                                    <i class="fas fa-boxes text-info me-1"></i> {{ __('Hanya Produk Tertentu') }}
                                </label>
                            </div>
                        </div>

                        {{-- Specific Products Box --}}
                        <div id="createSpecificProductsBox" class="border rounded-3 p-3 bg-light d-none">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-secondary">{{ __('Pilih Produk yang Memenuhi Syarat:') }}</span>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleAllCheckboxes('createProductCheck', true)">{{ __('Pilih Semua') }}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllCheckboxes('createProductCheck', false)">{{ __('Hapus Semua') }}</button>
                                </div>
                            </div>
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="{{ __('Cari nama produk...') }}" oninput="filterProductList(this, 'createProductList')">
                            <div id="createProductList" class="overflow-auto bg-white border rounded p-2" style="max-height: 200px;">
                                @foreach($products as $prod)
                                <div class="form-check py-1 product-item-row" data-name="{{ strtolower($prod->name) }}">
                                    <input class="form-check-input createProductCheck" type="checkbox" name="product_ids[]" value="{{ $prod->id }}" id="createProd-{{ $prod->id }}">
                                    <label class="form-check-label small d-flex justify-content-between align-items-center w-100 cursor-pointer" for="createProd-{{ $prod->id }}">
                                        <span>{{ $prod->name }}</span>
                                        <span class="text-muted">Rp{{ number_format($prod->price, 0, ',', '.') }}</span>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Buat Kupon') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Coupon Modals --}}
@foreach($coupons as $coupon)
<div class="modal fade" id="editCouponModal-{{ $coupon->id }}" tabindex="-1" aria-labelledby="editCouponModalLabel-{{ $coupon->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-0 bg-secondary-subtle p-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px;">
                        <i class="fas fa-edit text-primary fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-dark" id="editCouponModalLabel-{{ $coupon->id }}">{{ __('Edit Kupon:') }} {{ $coupon->code }}</h5>
                        <p class="small text-muted mb-0">{{ __('Perbarui rincian potongan harga atau batasan kupon.') }}</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.coupons.update', $coupon->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('KODE KUPON (UPPERCASE)') }} <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control font-monospace" value="{{ $coupon->code }}" required style="border-radius: 10px; text-transform: uppercase;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('TIPE DISKON') }} <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required style="border-radius: 10px;">
                                <option value="fixed" {{ $coupon->type === 'fixed' ? 'selected' : '' }}>{{ __('Nominal Tetap (Rp)') }}</option>
                                <option value="percent" {{ $coupon->type === 'percent' ? 'selected' : '' }}>{{ __('Persentase (%)') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('NILAI DISKON') }} <span class="text-danger">*</span></label>
                            <input type="number" name="value" class="form-control" value="{{ $coupon->value }}" required min="1" style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('MIN. BELANJA (Rp)') }} <span class="text-danger">*</span></label>
                            <input type="number" name="min_spend" class="form-control" value="{{ $coupon->min_spend }}" required min="0" style="border-radius: 10px;">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('MAKS. DISKON (Rp, Opsional)') }}</label>
                            <input type="number" name="max_discount" class="form-control" value="{{ $coupon->max_discount }}" min="1" style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('KUOTA PENGGUNAAN') }} <span class="text-danger">*</span></label>
                            <input type="number" name="qty" class="form-control" value="{{ $coupon->qty }}" required min="0" style="border-radius: 10px;">
                            <span class="small text-muted">{{ __('Isi 0 untuk tanpa batas') }}</span>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">{{ __('TGL KADALUARSA') }}</label>
                            <input type="datetime-local" name="expires_at" class="form-control" value="{{ $coupon->expires_at ? $coupon->expires_at->format('Y-m-d\TH:i') : '' }}" style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive-{{ $coupon->id }}" value="1" {{ $coupon->is_active ? 'checked' : '' }}>
                                <label class="form-check-label small fw-bold" for="editIsActive-{{ $coupon->id }}">{{ __('Aktifkan Kupon') }}</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- Product Scope Selection for Edit --}}
                    @php
                        $hasSpecific = $coupon->products->count() > 0;
                        $assignedIds = $coupon->products->pluck('id')->toArray();
                    @endphp
                    <div class="mb-3">
                        <label class="form-label small fw-bold d-block">{{ __('CAKUPAN PRODUK') }} <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scope" id="editScopeAll-{{ $coupon->id }}" value="all" {{ !$hasSpecific ? 'checked' : '' }} onchange="toggleScopeProducts('edit-{{ $coupon->id }}', false)">
                                <label class="form-check-label small fw-semibold" for="editScopeAll-{{ $coupon->id }}">
                                    <i class="fas fa-globe text-muted me-1"></i> {{ __('Semua Produk (Global)') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="scope" id="editScopeSpecific-{{ $coupon->id }}" value="specific" {{ $hasSpecific ? 'checked' : '' }} onchange="toggleScopeProducts('edit-{{ $coupon->id }}', true)">
                                <label class="form-check-label small fw-semibold" for="editScopeSpecific-{{ $coupon->id }}">
                                    <i class="fas fa-boxes text-info me-1"></i> {{ __('Hanya Produk Tertentu') }}
                                </label>
                            </div>
                        </div>

                        {{-- Specific Products Box --}}
                        <div id="edit-{{ $coupon->id }}SpecificProductsBox" class="border rounded-3 p-3 bg-light {{ !$hasSpecific ? 'd-none' : '' }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-secondary">{{ __('Pilih Produk yang Memenuhi Syarat:') }}</span>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleAllCheckboxes('editProductCheck-{{ $coupon->id }}', true)">{{ __('Pilih Semua') }}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAllCheckboxes('editProductCheck-{{ $coupon->id }}', false)">{{ __('Hapus Semua') }}</button>
                                </div>
                            </div>
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="{{ __('Cari nama produk...') }}" oninput="filterProductList(this, 'editProductList-{{ $coupon->id }}')">
                            <div id="editProductList-{{ $coupon->id }}" class="overflow-auto bg-white border rounded p-2" style="max-height: 200px;">
                                @foreach($products as $prod)
                                <div class="form-check py-1 product-item-row" data-name="{{ strtolower($prod->name) }}">
                                    <input class="form-check-input editProductCheck-{{ $coupon->id }}" type="checkbox" name="product_ids[]" value="{{ $prod->id }}" id="editProd-{{ $coupon->id }}-{{ $prod->id }}" {{ in_array($prod->id, $assignedIds) ? 'checked' : '' }}>
                                    <label class="form-check-label small d-flex justify-content-between align-items-center w-100 cursor-pointer" for="editProd-{{ $coupon->id }}-{{ $prod->id }}">
                                        <span>{{ $prod->name }}</span>
                                        <span class="text-muted">Rp{{ number_format($prod->price, 0, ',', '.') }}</span>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Simpan Perubahan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endpush

@push('scripts')
<script>
    function toggleScopeProducts(prefix, show) {
        const box = document.getElementById(prefix + 'SpecificProductsBox');
        if (box) {
            if (show) {
                box.classList.remove('d-none');
            } else {
                box.classList.add('d-none');
            }
        }
    }

    function toggleAllCheckboxes(className, check) {
        document.querySelectorAll('.' + className).forEach(cb => {
            cb.checked = check;
        });
    }

    function filterProductList(input, containerId) {
        const query = input.value.toLowerCase().trim();
        const container = document.getElementById(containerId);
        if (!container) return;
        const rows = container.querySelectorAll('.product-item-row');
        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            if (name.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endpush
