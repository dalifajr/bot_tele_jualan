@extends('layouts.app')

@section('title', 'Manajemen Pelanggan')
@section('page_subtitle', 'Pelanggan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Pelanggan</h4>
        <p class="text-muted mb-0">Daftar semua pengguna yang pernah berinteraksi</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-3">
        <form action="{{ route('admin.users.index') }}" method="GET" class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-0 bg-light" placeholder="Cari username, nama, email, atau ID Telegram..." value="{{ request('search') }}">
            </div>
            <button type="submit" class="btn btn-primary px-4 rounded-pill">Cari</button>
            @if(request('search'))
                <a href="{{ route('admin.users.index') }}" class="btn btn-light px-4 rounded-pill">Reset</a>
            @endif
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($users->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">Telegram ID</th>
                        <th class="py-3 border-0">Nama & Username</th>
                        <th class="py-3 border-0">Email</th>
                        <th class="py-3 border-0">Status & Role</th>
                        <th class="py-3 border-0">Bergabung</th>
                        <th class="py-3 border-0 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td class="px-4 fw-bold text-muted">{{ $user->telegram_id ?? '-' }}</td>
                        <td>
                            <div class="fw-bold text-primary">{{ $user->full_name ?? 'Unknown' }}</div>
                            <div class="small text-muted">{{ $user->username ? '@'.$user->username : '-' }}</div>
                        </td>
                        <td class="text-muted small">{{ $user->email ?? '-' }}</td>
                        <td>
                            <div class="mb-1">
                                @if($user->is_suspended)
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2"><i class="fas fa-ban me-1"></i>Ditangguhkan</span>
                                @else
                                    <span class="badge bg-success-subtle text-success rounded-pill px-2"><i class="fas fa-check me-1"></i>Aktif</span>
                                @endif
                            </div>
                            <span class="badge bg-{{ $user->role === 'admin' ? 'primary' : ($user->role === 'seller' ? 'info' : 'secondary') }}-subtle text-{{ $user->role === 'admin' ? 'primary' : ($user->role === 'seller' ? 'info' : 'secondary') }} rounded-pill px-2">
                                {{ ucfirst($user->role ?? 'customer') }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="text-end px-4">
                            @if($user->id !== Auth::id())
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy": "fixed"}' aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px; min-width: 200px;">
                                    <li>
                                        <button class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $user->id }}">
                                            <i class="fas fa-user-shield me-2 text-primary"></i> Ubah Detail Akses
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    
                                    @if($user->is_suspended)
                                        <li>
                                            <form action="{{ route('admin.users.unsuspend', $user->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2 text-success">
                                                    <i class="fas fa-unlock me-2"></i> Cabut Penangguhan
                                                </button>
                                            </form>
                                        </li>
                                    @else
                                        <li>
                                            <form action="{{ route('admin.users.suspend', $user->id) }}" method="POST">
                                                @csrf
                                                <button type="button" class="dropdown-item py-2 text-warning" onclick="confirmAction(event, 'Yakin ingin menangguhkan pengguna ini? Akses bot mereka akan diblokir.')">
                                                    <i class="fas fa-ban me-2"></i> Suspend Pengguna
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                    
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction(event, 'Yakin ingin menghapus permanen pengguna ini? Proses ini tidak dapat dibatalkan.')">
                                                <i class="fas fa-trash me-2"></i> Hapus Pengguna
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                            @else
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled>Ini Anda</button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $users->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-users text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Tidak ada pengguna yang ditemukan.</p>
        </div>
        @endif
    </div>
</div>

@push('modals')
@foreach($users as $user)
@if($user->id !== Auth::id())
{{-- Edit Role Modal --}}
<div class="modal fade" id="editUserModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Ubah Hak Akses & Detail Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <p class="mb-1 text-muted small">Nama Pengguna</p>
                        <h6 class="fw-bold text-primary">{{ $user->full_name ?? $user->username ?? 'Unknown' }}</h6>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Role Akses</label>
                        <select name="role" id="role_select_{{ $user->id }}" class="form-select" onchange="toggleSellerFields({{ $user->id }})" required>
                            <option value="customer" {{ $user->role === 'customer' ? 'selected' : '' }}>Customer (Biasa)</option>
                            <option value="seller" {{ $user->role === 'seller' ? 'selected' : '' }}>Seller (Penjual Mitra)</option>
                            <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin (Penuh)</option>
                        </select>
                    </div>

                    <div id="seller_fields_{{ $user->id }}" class="{{ $user->role === 'seller' ? '' : 'd-none' }}">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Saldo Dompet (Wallet Balance)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">Rp</span>
                                <input type="number" name="wallet_balance" class="form-control" value="{{ $user->wallet_balance ?? 0 }}" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Persentase Potongan Platform (Fee %)</label>
                            <div class="input-group">
                                <input type="number" name="platform_fee_percent" class="form-control" value="{{ $user->platform_fee_percent ?? 10 }}" min="0" max="100">
                                <span class="input-group-text bg-light text-muted">%</span>
                            </div>
                            <div class="form-text small text-muted">Komisi bagi hasil yang dipotong oleh platform saat transaksi lunas.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Jam Karantina Default (Save Hours)</label>
                            <div class="input-group">
                                <input type="number" name="seller_save_hours" class="form-control" value="{{ $user->seller_save_hours ?? 80 }}" min="0">
                                <span class="input-group-text bg-light text-muted">jam</span>
                            </div>
                            <div class="form-text small text-muted">Durasi karantina stok sebelum pindah otomatis dari *Simpan Akun* ke *Ready*.</div>
                        </div>
                    </div>

                    {{-- Hidden fallbacks if not seller to prevent validation failure --}}
                    <div id="seller_hidden_{{ $user->id }}" class="{{ $user->role === 'seller' ? 'd-none' : '' }}">
                        <input type="hidden" name="wallet_balance" id="hidden_balance_{{ $user->id }}" value="{{ $user->wallet_balance ?? 0 }}">
                        <input type="hidden" name="platform_fee_percent" id="hidden_fee_{{ $user->id }}" value="{{ $user->platform_fee_percent ?? 10 }}">
                        <input type="hidden" name="seller_save_hours" id="hidden_hours_{{ $user->id }}" value="{{ $user->seller_save_hours ?? 80 }}">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endforeach
@endpush

@push('scripts')
<script>
function toggleSellerFields(userId) {
    var roleSelect = document.getElementById('role_select_' + userId);
    var sellerFields = document.getElementById('seller_fields_' + userId);
    var sellerHidden = document.getElementById('seller_hidden_' + userId);
    
    // Find all inputs within sellerFields
    var inputs = sellerFields.getElementsByTagName('input');
    
    if (roleSelect.value === 'seller') {
        sellerFields.classList.remove('d-none');
        sellerHidden.classList.add('d-none');
        // Disable hidden fallbacks
        document.getElementById('hidden_balance_' + userId).disabled = true;
        document.getElementById('hidden_fee_' + userId).disabled = true;
        document.getElementById('hidden_hours_' + userId).disabled = true;
        // Enable visible inputs
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].disabled = false;
        }
    } else {
        sellerFields.classList.add('d-none');
        sellerHidden.classList.remove('d-none');
        // Enable hidden fallbacks to pass validation
        document.getElementById('hidden_balance_' + userId).disabled = false;
        document.getElementById('hidden_fee_' + userId).disabled = false;
        document.getElementById('hidden_hours_' + userId).disabled = false;
        // Disable visible inputs
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].disabled = true;
        }
    }
}

// Run for all on load to initialize correct disabled states
document.addEventListener("DOMContentLoaded", function() {
    @foreach($users as $user)
    @if($user->id !== Auth::id())
    toggleSellerFields({{ $user->id }});
    @endif
    @endforeach
});
</script>
@endpush
@endsection

@push('styles')
<style>
/* 
   Perbaikan untuk mengaktifkan scroll horizontal
*/
.table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch; /* Menghaluskan scroll pada perangkat iOS */
}

/* 
   Memberikan z-index tinggi agar dropdown 
   selalu berada di atas paginasi atau elemen footer lainnya 
*/
.dropdown-menu {
    z-index: 1060 !important;
}
</style>
@endpush
