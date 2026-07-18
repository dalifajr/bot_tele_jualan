@extends('layouts.app')

@section('title', 'Manajemen Produk')
@section('page_subtitle', 'Produk')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Manajemen Produk') }}</h4>
        <p class="text-muted mb-0">{{ __('Kelola katalog produk digital') }}</p>
    </div>
    <div>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="fas fa-plus me-2"></i>{{ __('Tambah Produk') }}
        </button>
    </div>
</div>

@if ($errors->any())
<div class="alert alert-danger shadow-sm rounded-4 mb-4">
    <div class="fw-bold mb-1"><i class="fas fa-exclamation-circle me-2"></i>{{ __('Terdapat kesalahan pada input Anda:') }}</div>
    <ul class="mb-0 small">
        @foreach ($errors->{{ __('all() as $error)') }}
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($products->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">ID</th>
                        <th class="py-3 border-0">{{ __('Nama Produk') }}</th>
                        <th class="py-3 border-0">{{ __('Harga') }}</th>
                        <th class="py-3 border-0">{{ __('Status') }}</th>
                        <th class="py-3 border-0">{{ __('Dibuat') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    <tr>
                        <td class="px-4 fw-bold text-muted">#{{ $product->id }}</td>
                        <td class="fw-bold text-primary">{{ $product->name }}</td>
                        <td>{{ $product->formatted_price }}</td>
                        <td>
                            @if($product->{{ __('is_suspended)') }}
                            <span class="badge bg-danger-subtle text-danger rounded-pill px-3">{{ __('Suspended') }}</span>
                            @else
                            <span class="badge bg-success-subtle text-success rounded-pill px-3">{{ __('Active') }}</span>
                            @endif
                        </td>
                        <td class="text-secondary small">{{ $product->created_at->format('d M Y') }}</td>
                        <td class="px-4">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="{{ route('admin.products.manage', $product->id) }}" class="btn btn-sm btn-light text-primary rounded-pill px-3" title="{{ __('Detail & Aksi') }}">
                                    <i class="fas fa-cog"></i> {{ __('Aksi') }}
                                </a>
                                <button class="btn btn-sm btn-light text-primary rounded-circle" data-bs-toggle="modal" data-bs-target="#editProductModal{{ $product->id }}" title="{{ __('Edit') }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-light text-danger rounded-circle" data-bs-toggle="modal" data-bs-target="#deleteProductModal{{ $product->id }}" title="{{ __('Hapus') }}">
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
            <p class="text-muted mb-0">{{ __('Belum ada produk.') }}</p>
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
                        <label class="form-label text-muted small fw-bold">{{ __('Harga (Rp)') }}</label>
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
                        <label class="form-label text-muted small fw-bold">{{ __('Masa Garansi (Hari)') }}</label>
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
                        <label class="form-label text-muted small fw-bold">{{ __('Harga (Rp)') }}</label>
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
                        <label class="form-label text-muted small fw-bold">{{ __('Masa Garansi (Hari)') }}</label>
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
                        <label class="form-check-label" for="suspend{{ $product->id }}">{{ __('Suspend (Sembunyikan dari katalog)') }}</label>
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
                            <i class="fas fa-file-excel me-1"></i> {{ __('Unduh Sisa Stok (.xlsx)') }}
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
    document.getElementById('enableWarrantyAdd').addEventListener('change', function() {
        document.getElementById('warrantyDaysAddContainer').style.display = this.checked ? 'block' : 'none';
        if (!this.checked) {
            document.getElementById('warrantyDaysAdd').value = '';
        }
    });

    document.querySelectorAll('.toggle-warranty-edit').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            var targetId = this.getAttribute('data-target');
            var container = document.getElementById(targetId);
            container.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                container.querySelector('input[type="number"]').value = '';
            }
        });
    });

    // VPN Togglers
    document.getElementById('isVpnAdd').addEventListener('change', function() {
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
            container.style.display = this.checked ? 'block' : 'none';
        });
    });
</script>
@endpush
@endsection
