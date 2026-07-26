@extends('layouts.app')

@section('title', 'Manajemen Produk')
@section('page_subtitle', 'Produk')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Manajemen Produk') }}</h4>
        <p class="text-muted mb-0">{{ __('Kelola katalog produk digital, filter seller, serta kelola hak akses kepemilikan.') }}</p>
    </div>
    <div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="fas fa-plus me-2"></i>{{ __('Tambah Produk') }}
        </button>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success shadow-sm rounded-4 mb-4">
    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
</div>
@endif

@if(session('info'))
<div class="alert alert-info shadow-sm rounded-4 mb-4">
    <i class="fas fa-info-circle me-2"></i>{{ session('info') }}
</div>
@endif

@if(session('error'))
<div class="alert alert-danger shadow-sm rounded-4 mb-4">
    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
</div>
@endif

@if ($errors->any())
<div class="alert alert-danger shadow-sm rounded-4 mb-4">
    <div class="fw-bold mb-1"><i class="fas fa-exclamation-circle me-2"></i>{{ __('Terdapat kesalahan pada input Anda:') }}</div>
    <ul class="mb-0 small">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- Filter & Search Card --}}
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('admin.products.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-0" placeholder="{{ __('Cari nama, deskripsi, atau ID produk...') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-store text-muted"></i></span>
                    <select name="seller_id" class="form-select bg-light border-0">
                        <option value="">{{ __('-- Semua Pemilik / Seller --') }}</option>
                        <option value="admin" {{ request('seller_id') === 'admin' ? 'selected' : '' }}>{{ __('Admin Utama') }}</option>
                        @foreach($sellers as $seller)
                            <option value="{{ $seller->id }}" {{ request('seller_id') == $seller->id ? 'selected' : '' }}>
                                {{ $seller->full_name ?? $seller->username }} (Seller)
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-3 flex-grow-1">
                    <i class="fas fa-filter me-1"></i> {{ __('Filter') }}
                </button>
                @if(request()->hasAny(['search', 'seller_id']))
                <a href="{{ route('admin.products.index') }}" class="btn btn-light rounded-pill text-muted px-3" title="{{ __('Reset Filter') }}">
                    <i class="fas fa-undo"></i>
                </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Product Catalog Card Grid --}}
@if($products->count() > 0)
<div class="row g-3 mb-4">
    @foreach($products as $product)
    <div class="col-12 col-sm-6 col-md-4 col-xl-3">
        <div class="card border-0 shadow-sm overflow-hidden h-100" style="border-radius: 14px;">
            <div class="px-3 py-2 text-white d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
                <div class="text-truncate me-2" style="max-width: 65%;">
                    <a href="{{ route('admin.products.manage', $product->id) }}" class="fw-bold text-white text-decoration-none text-truncate d-block small" title="{{ __('Kelola Stok & Worker') }}: {{ $product->name }}">
                        #{{ $product->id }} {{ $product->name }}
                    </a>
                </div>
                <div>
                    <span class="badge bg-white text-primary rounded-pill fw-bold shadow-sm" style="font-size: 0.75rem;">
                        {{ $product->formatted_price }}
                    </span>
                </div>
            </div>
            
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex flex-wrap gap-1 mb-2" style="font-size: 0.7rem;">
                        @if($product->is_suspended)
                            <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-0.5"><i class="fas fa-ban me-1"></i>{{ __('Suspended') }}</span>
                        @else
                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5"><i class="fas fa-check-circle me-1"></i>{{ __('Active') }}</span>
                        @endif

                        @if($product->creator_id === null)
                            <span class="badge bg-dark-subtle text-dark rounded-pill px-2 py-0.5" title="{{ __('Dikelola oleh Admin Utama') }}"><i class="fas fa-user-shield me-1"></i>{{ __('Admin') }}</span>
                        @else
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5" title="{{ __('Pemilik Produk: Seller') }}"><i class="fas fa-store me-1"></i>{{ Str::limit($product->creator->full_name ?? $product->creator->username, 12) }}</span>
                        @endif

                        @if($product->is_vpn)
                            <span class="badge bg-info-subtle text-info rounded-pill px-2 py-0.5"><i class="fas fa-network-wired me-1"></i>VPN ({{ strtoupper($product->vpn_protocol) }})</span>
                        @endif

                        @if($product->warranty_days > 0)
                            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2 py-0.5"><i class="fas fa-shield-alt me-1"></i>{{ $product->warranty_days }}H</span>
                        @endif
                    </div>

                    <p class="text-muted mb-2 text-truncate" style="font-size: 0.78rem;" title="{{ $product->description }}">
                        {{ $product->description ?: 'Tidak ada deskripsi.' }}
                    </p>

                    <div class="bg-light px-2 py-1.5 rounded-3 mb-2 d-flex justify-content-between align-items-center" style="font-size: 0.73rem;">
                        <span title="{{ __('Stok Ready') }}"><i class="fas fa-cubes text-info me-1"></i><strong>{{ $product->stockUnits->where('is_sold', false)->count() }}</strong></span>
                        <span title="{{ __('Stok Terjual') }}"><i class="fas fa-shopping-bag text-success me-1"></i><strong>{{ $product->stockUnits->where('is_sold', true)->count() }}</strong></span>
                        <span title="{{ __('Worker') }}"><i class="fas fa-users text-secondary me-1"></i><strong>{{ $product->workers->count() }}</strong></span>
                    </div>
                </div>

                <div class="border-top pt-2 mt-1 d-flex justify-content-end align-items-center gap-1">
                    <a href="{{ route('admin.products.manage', $product->id) }}" class="btn btn-sm btn-light text-primary rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="{{ __('Detail & Kelola Stok') }}">
                        <i class="fas fa-cog" style="font-size: 0.75rem;"></i>
                    </a>

                    <button class="btn btn-sm btn-light text-primary rounded-circle" data-bs-toggle="modal" data-bs-target="#editProductModal{{ $product->id }}" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="{{ __('Edit Produk') }}">
                        <i class="fas fa-edit" style="font-size: 0.75rem;"></i>
                    </button>

                    @if($product->creator_id !== null)
                    <button class="btn btn-sm btn-light text-warning rounded-circle" data-bs-toggle="modal" data-bs-target="#takeoverProductModal{{ $product->id }}" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="{{ __('Ambil Alih ke Admin') }}">
                        <i class="fas fa-user-shield" style="font-size: 0.75rem;"></i>
                    </button>
                    @else
                    <button class="btn btn-sm btn-light text-muted rounded-circle opacity-50" disabled style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="{{ __('Sudah Dikelola Admin Utama') }}">
                        <i class="fas fa-user-shield" style="font-size: 0.75rem;"></i>
                    </button>
                    @endif

                    <button class="btn btn-sm btn-light text-info rounded-circle" data-bs-toggle="modal" data-bs-target="#reassignProductModal{{ $product->id }}" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="{{ __('Limpahkan ke Seller Lain') }}">
                        <i class="fas fa-exchange-alt" style="font-size: 0.75rem;"></i>
                    </button>

                    <button class="btn btn-sm btn-light text-danger rounded-circle" data-bs-toggle="modal" data-bs-target="#deleteProductModal{{ $product->id }}" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="{{ __('Hapus Produk') }}">
                        <i class="fas fa-trash-alt" style="font-size: 0.75rem;"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="d-flex justify-content-center mb-4">
    {{ $products->links() }}
</div>
@else
<div class="card border-0 shadow-sm rounded-4 py-5 text-center">
    <div class="card-body">
        <i class="fas fa-box text-muted mb-3" style="font-size: 3rem;"></i>
        <h6 class="fw-bold text-secondary">{{ __('Belum ada produk ditemukan.') }}</h6>
        <p class="text-muted small mb-0">{{ __('Coba ubah kata kunci pencarian atau filter seller Anda.') }}</p>
    </div>
</div>
@endif

@push('modals')
{{-- Add Product Modal --}}
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Tambah Produk Baru') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.products.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Nama Produk') }}</label>
                        <input type="text" name="name" class="form-control" required placeholder="{{ __('Contoh: Netflix Premium 1 Bulan') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Harga (Rp)</label>
                        <input type="number" name="price" class="form-control" required placeholder="{{ __('Contoh: 35000') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Deskripsi') }}</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="{{ __('Informasi produk...') }}"></textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="enableWarrantyAdd" name="enable_warranty" value="1">
                        <label class="form-check-label text-muted small fw-bold" for="enableWarrantyAdd">{{ __('Aktifkan garansi?') }}</label>
                    </div>
                    <div class="mb-3" id="warrantyDaysAddContainer" style="display: none;">
                        <label class="form-label text-muted small fw-bold">Masa Garansi (Hari)</label>
                        <div class="input-group">
                            <input type="number" name="warranty_days" id="warrantyDaysAdd" class="form-control" placeholder="{{ __('Contoh: 3') }}" min="1">
                            <span class="input-group-text bg-light text-muted">{{ __('hari') }}</span>
                        </div>
                        <div class="form-text small">{{ __('Menahan saldo seller hingga masa garansi berakhir.') }}</div>
                    </div>
                    
                    <div class="form-check form-switch mb-3 border-top pt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="isVpnAdd" name="is_vpn" value="1">
                        <label class="form-check-label text-primary small fw-bold" for="isVpnAdd"><i class="fas fa-network-wired me-1"></i> {{ __('Jadikan Produk VPN?') }}</label>
                    </div>
                    
                    <div id="vpnOptionsAddContainer" style="display: none;" class="bg-light p-3 rounded mb-3">
                        <div class="mb-2">
                            <label class="form-label text-muted small fw-bold">{{ __('Protokol VPN') }}</label>
                            <select name="vpn_protocol" id="vpnProtocolAdd" class="form-select">
                                <option value="">{{ __('Pilih Protokol') }}</option>
                                <option value="vmess">{{ __('VMESS') }}</option>
                                <option value="vless">{{ __('VLESS') }}</option>
                                <option value="trojan">{{ __('TROJAN') }}</option>
                                <option value="shadowsocks">{{ __('SHADOWSOCKS') }}</option>
                                <option value="ssh">{{ __('SSH') }}</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-muted small fw-bold">{{ __('Durasi / Masa Aktif') }}</label>
                            <div class="input-group">
                                <input type="number" name="vpn_duration_days" id="vpnDurationAdd" class="form-control" placeholder="30" min="1">
                                <span class="input-group-text bg-white text-muted">{{ __('hari') }}</span>
                            </div>
                        </div>
                        <div class="form-text text-muted small"><i class="fas fa-info-circle"></i> {{ __('Stok untuk produk VPN tidak perlu ditambahkan secara manual. Saat pembeli melakukan checkout, sistem akan meng-generate akun secara otomatis di VPS sesuai durasi ini.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Tambahkan') }}</button>
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
                <h5 class="fw-bold">{{ __('Edit Produk') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.products.update', $product->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Nama Produk') }}</label>
                        <input type="text" name="name" class="form-control" value="{{ $product->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Harga (Rp)</label>
                        <input type="number" name="price" class="form-control" value="{{ $product->price }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Deskripsi') }}</label>
                        <textarea name="description" class="form-control" rows="3">{{ $product->description }}</textarea>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input toggle-warranty-edit" type="checkbox" role="switch" name="enable_warranty" value="1" id="enableWarrantyEdit{{ $product->id }}" data-target="warrantyDaysEditContainer{{ $product->id }}" {{ $product->warranty_days > 0 ? 'checked' : '' }}>
                        <label class="form-check-label text-muted small fw-bold" for="enableWarrantyEdit{{ $product->id }}">{{ __('Aktifkan garansi?') }}</label>
                    </div>
                    <div class="mb-3" id="warrantyDaysEditContainer{{ $product->id }}" style="display: {{ $product->warranty_days > 0 ? 'block' : 'none' }};">
                        <label class="form-label text-muted small fw-bold">Masa Garansi (Hari)</label>
                        <div class="input-group">
                            <input type="number" name="warranty_days" id="warrantyDaysEdit{{ $product->id }}" class="form-control" value="{{ $product->warranty_days > 0 ? $product->warranty_days : '' }}" min="1">
                            <span class="input-group-text bg-light text-muted">{{ __('hari') }}</span>
                        </div>
                        <div class="form-text small">{{ __('Menahan saldo seller hingga masa garansi berakhir.') }}</div>
                    </div>

                    <div class="form-check form-switch mb-3 border-top pt-3">
                        <input class="form-check-input toggle-vpn-edit" type="checkbox" role="switch" name="is_vpn" value="1" id="isVpnEdit{{ $product->id }}" data-target="vpnOptionsEditContainer{{ $product->id }}" {{ $product->is_vpn ? 'checked' : '' }}>
                        <label class="form-check-label text-primary small fw-bold" for="isVpnEdit{{ $product->id }}"><i class="fas fa-network-wired me-1"></i> {{ __('Jadikan Produk VPN?') }}</label>
                    </div>

                    <div id="vpnOptionsEditContainer{{ $product->id }}" style="display: {{ $product->is_vpn ? 'block' : 'none' }};" class="bg-light p-3 rounded mb-3">
                        <div class="mb-2">
                            <label class="form-label text-muted small fw-bold">{{ __('Protokol VPN') }}</label>
                            <select name="vpn_protocol" class="form-select">
                                <option value="">{{ __('Pilih Protokol') }}</option>
                                <option value="vmess" {{ $product->vpn_protocol == 'vmess' ? 'selected' : '' }}>{{ __('VMESS') }}</option>
                                <option value="vless" {{ $product->vpn_protocol == 'vless' ? 'selected' : '' }}>{{ __('VLESS') }}</option>
                                <option value="trojan" {{ $product->vpn_protocol == 'trojan' ? 'selected' : '' }}>{{ __('TROJAN') }}</option>
                                <option value="shadowsocks" {{ $product->vpn_protocol == 'shadowsocks' ? 'selected' : '' }}>{{ __('SHADOWSOCKS') }}</option>
                                <option value="ssh" {{ $product->vpn_protocol == 'ssh' ? 'selected' : '' }}>{{ __('SSH') }}</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-muted small fw-bold">{{ __('Durasi / Masa Aktif') }}</label>
                            <div class="input-group">
                                <input type="number" name="vpn_duration_days" class="form-control" value="{{ $product->vpn_duration_days > 0 ? $product->vpn_duration_days : '' }}" min="1">
                                <span class="input-group-text bg-white text-muted">{{ __('hari') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_suspended" value="1" id="suspend{{ $product->id }}" {{ $product->is_suspended ? 'checked' : '' }}>
                        <label class="form-check-label" for="suspend{{ $product->id }}">Suspend (Sembunyikan dari katalog)</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Simpan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Takeover Modal --}}
@if($product->creator_id !== null)
<div class="modal fade" id="takeoverProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center" style="border-radius: 16px; border: none;">
            <div class="modal-body p-4">
                <i class="fas fa-user-shield text-warning mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold">{{ __('Ambil Alih Produk ke Admin?') }}</h5>
                <p class="text-muted small mb-3">
                    {{ __('Anda akan mengambil alih kepemilikan produk') }} <strong>"{{ $product->name }}"</strong> {{ __('dari seller') }} <strong>"{{ $product->creator->full_name ?? $product->creator->username }}"</strong> {{ __('ke Admin Utama. Seluruh sisa stok belum terjual juga akan dialihkan ke Admin Utama.') }}
                </p>
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <form action="{{ route('admin.products.takeover', $product->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-warning text-dark rounded-pill px-4 fw-bold">{{ __('Ya, Ambil Alih') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Reassign / Transfer Modal --}}
<div class="modal fade" id="reassignProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold"><i class="fas fa-exchange-alt text-info me-2"></i>{{ __('Limpahkan Produk ke Seller Lain') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.products.reassign', $product->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        {{ __('Pindahkan kepemilikan produk') }} <strong>"{{ $product->name }}"</strong> {{ __('dan seluruh sisa stok belum terjual ke seller lain.') }}
                    </p>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Pilih Seller Tujuan') }}</label>
                        <select name="seller_id" class="form-select" required>
                            <option value="">{{ __('-- Pilih Seller --') }}</option>
                            @foreach($sellers as $seller)
                                @if($seller->id !== $product->creator_id)
                                    <option value="{{ $seller->id }}">
                                        {{ $seller->full_name ?? $seller->username }} ({{ $seller->username }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-info text-white rounded-pill px-4 fw-bold">{{ __('Limpahkan Produk') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div class="modal fade" id="deleteProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center" style="border-radius: 16px; border: none;">
            <div class="modal-body p-4">
                <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                <h5 class="fw-bold">{{ __('Hapus Produk?') }}</h5>
                <p class="text-muted small mb-3">{{ __('Menghapus produk akan turut menghapus semua stok yang terkait dengannya. Lanjutkan?') }}</p>
                
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
                        <a href="{{ route('admin.products.export-unsold', $product->id) }}" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                            <i class="fas fa-file-excel me-1"></i> Unduh Sisa Stok (.xlsx)
                        </a>
                    </div>
                </div>
                @endif

                <div class="d-flex gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST">
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
    document.getElementById('enableWarrantyAdd')?.addEventListener('change', function() {
        document.getElementById('warrantyDaysAddContainer').style.display = this.checked ? 'block' : 'none';
        if (!this.checked) {
            document.getElementById('warrantyDaysAdd').value = '';
        }
    });

    document.querySelectorAll('.toggle-warranty-edit').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var targetId = this.getAttribute('data-target');
            var container = document.getElementById(targetId);
            if (container) {
                container.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    var input = container.querySelector('input[type="number"]');
                    if (input) input.value = '';
                }
            }
        });
    });

    // VPN Togglers
    document.getElementById('isVpnAdd')?.addEventListener('change', function() {
        document.getElementById('vpnOptionsAddContainer').style.display = this.checked ? 'block' : 'none';
        if (!this.checked) {
            document.getElementById('vpnProtocolAdd').value = '';
            document.getElementById('vpnDurationAdd').value = '';
        }
    });

    document.querySelectorAll('.toggle-vpn-edit').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var targetId = this.getAttribute('data-target');
            var container = document.getElementById(targetId);
            if (container) {
                container.style.display = this.checked ? 'block' : 'none';
            }
        });
    });
</script>
@endpush
@endsection
