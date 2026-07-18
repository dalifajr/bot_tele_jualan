@extends('layouts.app')

@section('title', __('Manajemen Seller & Pendapatan'))
@section('page_subtitle', __('Sellers'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Manajemen Seller & Kemitraan') }}</h4>
        <p class="text-muted mb-0">{{ __('Daftar mitra seller, status penangguhan, dan detail saldo pendapatan') }}</p>
    </div>
</div>

{{-- Metrics Row --}}
<div class="row g-3 mb-4">
    <div class="col-xl col-md-4 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">{{ __('Total Seller Mitra') }}</div>
                <h3 class="fw-bold mb-0 text-primary">{{ $totalSellers }}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">{{ __('Seller Aktif') }}</div>
                <h3 class="fw-bold mb-0 text-success">{{ $activeSellers }}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-12">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">{{ __('Seller Ditangguhkan') }}</div>
                <h3 class="fw-bold mb-0 text-danger">{{ $suspendedSellers }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-3">
        <form action="{{ route('admin.sellers.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-0 bg-light" placeholder="{{ __('Cari username, nama, email, atau ID Telegram...') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2 col-6">
                <select name="status" class="form-select border-0 bg-light">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Aktif') }}</option>
                    <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>{{ __('Ditangguhkan') }}</option>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <select name="period" class="form-select border-0 bg-light">
                    <option value="7_days" {{ request('period') === '7_days' || !request('period') ? 'selected' : '' }}>{{ __('7 Hari Terakhir') }}</option>
                    <option value="30_days" {{ request('period') === '30_days' ? 'selected' : '' }}>{{ __('30 Hari Terakhir') }}</option>
                    <option value="6_months" {{ request('period') === '6_months' ? 'selected' : '' }}>{{ __('6 Bulan Terakhir') }}</option>
                    <option value="1_year" {{ request('period') === '1_year' ? 'selected' : '' }}>{{ __('1 Tahun Terakhir') }}</option>
                </select>
            </div>
            <div class="col-md-3 col-12 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary px-3 rounded-pill flex-fill">{{ __('Cari & Filter') }}</button>
                @if(request('search') || request('status') || request('period'))
                    <a href="{{ route('admin.sellers.index') }}" class="btn btn-light px-3 rounded-pill">{{ __('Reset') }}</a>
                @endif
            </div>
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
        @if($sellers->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">{{ __('No') }}</th>
                        <th class="py-3 border-0">{{ __('Nama Seller & Telegram') }}</th>
                        <th class="py-3 border-0">{{ __('Status') }}</th>
                        <th class="py-3 border-0 text-center">{{ __('Jumlah Produk') }}</th>
                        <th class="py-3 border-0">{{ __('Saldo Seller') }}</th>
                        <th class="py-3 border-0">{{ __('Pendapatan Bersih') }}</th>
                        <th class="py-3 border-0">{{ __('Kontribusi') }}</th>
                        <th class="py-3 border-0">{{ __('Tren Penjualan') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sellers as $seller)
                    <tr>
                        <td class="px-4 text-muted small fw-bold">{{ $loop->iteration + ($sellers->firstItem() - 1) }}</td>
                        <td>
                            <div class="fw-bold text-primary">{{ $seller->full_name ?? 'Unknown' }}</div>
                            <div class="small text-muted d-flex align-items-center gap-1">
                                <span>{{ $seller->username ? '@'.$seller->username : '-' }}</span>
                                @if($seller->telegram_id)
                                    <span class="badge bg-light text-secondary small py-0 px-1 border" style="font-size: 0.7rem;">
                                        <i class="fab fa-telegram-plane text-info me-1"></i>{{ $seller->telegram_id }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($seller->is_suspended)
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-2"><i class="fas fa-ban me-1"></i>{{ __('Ditangguhkan') }}</span>
                            @else
                                <span class="badge bg-success-subtle text-success rounded-pill px-2"><i class="fas fa-check me-1"></i>{{ __('Aktif') }}</span>
                            @endif
                        </td>
                        <td class="text-center fw-bold">{{ $seller->products_count }}</td>
                        <td>
                            <div class="d-flex flex-column gap-1" style="line-height: 1.2;">
                                <div>
                                    <span class="fw-bold text-success" style="font-size: 0.85rem;">Rp {{ number_format($seller->wallet_balance ?? 0, 0, ',', '.') }}</span>
                                </div>
                                <div>
                                    <span class="fw-bold text-warning" style="font-size: 0.85rem;">Rp {{ number_format($seller->held_balance ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary" style="font-size: 0.88rem;">Rp {{ number_format($seller->net_earnings ?? 0, 0, ',', '.') }}</div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $seller->contribution_percentage }}%</div>
                            <small class="text-muted" style="font-size: 0.72rem; display: block; white-space: nowrap;">{{ __('Komisi') }}: Rp {{ number_format($seller->commission_amount, 0, ',', '.') }}</small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <svg viewBox="0 0 100 30" width="70" height="22" style="overflow: visible; flex-shrink: 0;">
                                    <path d="{{ $seller->sparkline_path }}" fill="none" 
                                          stroke="{{ $seller->trend_direction === 'up' ? '#20c997' : ($seller->trend_direction === 'down' ? '#dc3545' : '#6c757d') }}" 
                                          stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div style="flex-shrink: 0;">
                                    @if($seller->trend_direction === 'up')
                                        <span class="badge bg-success-subtle text-success rounded-pill" style="font-size: 0.7rem; white-space: nowrap;">
                                            <i class="fas fa-arrow-up me-1"></i>+{{ $seller->percentage_change }}%
                                        </span>
                                    @elseif($seller->trend_direction === 'down')
                                        <span class="badge bg-danger-subtle text-danger rounded-pill" style="font-size: 0.7rem; white-space: nowrap;">
                                            <i class="fas fa-arrow-down me-1"></i>{{ $seller->percentage_change }}%
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" style="font-size: 0.7rem; white-space: nowrap;">
                                            <i class="fas fa-minus me-1"></i>0.0%
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="text-end px-4">
                            @if($seller->id !== Auth::id())
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy": "fixed"}' aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px; min-width: 200px;">
                                    <li>
                                        <button class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $seller->id }}">
                                            <i class="fas fa-user-shield me-2 text-primary"></i> {{ __('Ubah Detail Akses') }}
                                        </button>
                                    </li>
                                    <li>
                                        <form action="{{ route('admin.users.impersonate', $seller->id) }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="dropdown-item py-2 text-info">
                                                <i class="fas fa-user-secret me-2"></i> {{ __('Masuk sebagai') }}
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    
                                    @if($seller->is_suspended)
                                        <li>
                                            <form action="{{ route('admin.users.unsuspend', $seller->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2 text-success">
                                                    <i class="fas fa-unlock me-2"></i> {{ __('Cabut Penangguhan') }}
                                                </button>
                                            </form>
                                        </li>
                                    @else
                                        <li>
                                            <form action="{{ route('admin.users.suspend', $seller->id) }}" method="POST">
                                                @csrf
                                                <button type="button" class="dropdown-item py-2 text-warning" onclick="confirmSuspend(event)">
                                                    <i class="fas fa-ban me-2"></i> {{ __('Suspend Pengguna') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                    
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('admin.users.destroy', $seller->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction(event, 'Yakin ingin menghapus permanen seller ini? Data login & akses bot akan terhapus.')">
                                                <i class="fas fa-trash me-2"></i> {{ __('Hapus Pengguna') }}
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                            @else
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled>{{ __('Ini Anda') }}</button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $sellers->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-store text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">{{ __('Tidak ada seller yang ditemukan.') }}</p>
        </div>
        @endif
    </div>
</div>
@endsection

@push('modals')
@foreach($sellers as $user)
@if($user->id !== Auth::id())
{{-- Edit Role Modal --}}
<div class="modal fade" id="editUserModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Ubah Hak Akses & Detail Seller') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <p class="mb-1 text-muted small">{{ __('Nama Pengguna') }}</p>
                        <h6 class="fw-bold text-primary">{{ $user->full_name ?? $user->username ?? 'Unknown' }}</h6>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Role Akses') }}</label>
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
                            <div class="form-text small text-muted">{{ __('Komisi bagi hasil yang dipotong oleh platform saat transaksi lunas.') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Jam Karantina Default (Save Hours)</label>
                            <div class="input-group">
                                <input type="number" name="seller_save_hours" class="form-control" value="{{ $user->seller_save_hours ?? 80 }}" min="0">
                                <span class="input-group-text bg-light text-muted">{{ __('jam') }}</span>
                            </div>
                            <div class="form-text small text-muted">{{ __('Durasi karantina stok sebelum pindah otomatis dari *Simpan Akun* ke *Ready*.') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold d-block">{{ __('Akses Fitur Tool') }}</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="allowed_tools[]" value="github_checker" id="tool_github_{{ $user->id }}" {{ is_array($user->allowed_tools) && in_array('github_checker', $user->allowed_tools) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="tool_github_{{ $user->id }}">{{ __('GitHub Live Checker') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="allowed_tools[]" value="gmail_checker" id="tool_gmail_{{ $user->id }}" {{ is_array($user->allowed_tools) && in_array('gmail_checker', $user->allowed_tools) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="tool_gmail_{{ $user->id }}">{{ __('Gmail Live Checker') }}</label>
                            </div>
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
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Simpan Perubahan') }}</button>
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
    
    var inputs = sellerFields.getElementsByTagName('input');
    
    if (roleSelect.value === 'seller') {
        sellerFields.classList.remove('d-none');
        sellerHidden.classList.add('d-none');
        document.getElementById('hidden_balance_' + userId).disabled = true;
        document.getElementById('hidden_fee_' + userId).disabled = true;
        document.getElementById('hidden_hours_' + userId).disabled = true;
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].disabled = false;
        }
    } else {
        sellerFields.classList.add('d-none');
        sellerHidden.classList.remove('d-none');
        document.getElementById('hidden_balance_' + userId).disabled = false;
        document.getElementById('hidden_fee_' + userId).disabled = false;
        document.getElementById('hidden_hours_' + userId).disabled = false;
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].disabled = true;
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    @foreach($sellers as $user)
    @if($user->id !== Auth::id())
    toggleSellerFields({{ $user->id }});
    @endif
    @endforeach
});

function confirmSuspend(event) {
    event.preventDefault();
    let element = event.currentTarget;
    let form = element.closest('form');
    
    Swal.fire({
        title: 'Alasan Penangguhan',
        input: 'text',
        inputLabel: 'Masukkan alasan penangguhan (opsional):',
        inputPlaceholder: 'Tulis alasan di sini...',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Lanjut',
        denyButtonText: 'Lewati',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3085d6',
        denyButtonColor: '#6c757d',
        cancelButtonColor: '#d33',
        customClass: {
            input: 'form-control rounded-pill px-3',
            popup: 'rounded-4'
        }
    }).then((result) => {
        if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
            return;
        }
        
        let reason = '';
        if (result.isConfirmed) {
            reason = result.value || '';
        } else if (result.isDenied) {
            reason = '';
        } else {
            return;
        }
        
        Swal.fire({
            title: 'Konfirmasi Penangguhan',
            text: 'Apakah Anda yakin ingin menangguhkan pengguna ini? Akses bot mereka akan diblokir.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Tangguhkan!',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            customClass: {
                popup: 'rounded-4'
            }
        }).then((confirmResult) => {
            if (confirmResult.isConfirmed) {
                let loader = document.getElementById('pageLoader');
                if (loader) {
                    loader.classList.remove('fade-out');
                }
                if (typeof startTopLoadingBar === 'function') {
                    startTopLoadingBar();
                }
                
                let inputReason = document.createElement('input');
                inputReason.type = 'hidden';
                inputReason.name = 'reason';
                inputReason.value = reason;
                form.appendChild(inputReason);
                
                form.submit();
            }
        });
    });
}
</script>
@endpush

@push('styles')
<style>
.table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
}
.dropdown-menu {
    z-index: 1060 !important;
}
</style>
@endpush
