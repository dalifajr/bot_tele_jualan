@extends('layouts.app')

@section('title', __('Manajemen Pelanggan'))
@section('page_subtitle', __('Pelanggan'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Manajemen Pelanggan') }}</h4>
        <p class="text-muted mb-0">{{ __('Daftar semua pengguna yang pernah berinteraksi') }}</p>
    </div>
</div>

{{-- Metrics Row --}}
<div class="row g-3 mb-4">
    <div class="col-xl col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">{{ __('Customer (Pelanggan)') }}</div>
                <h3 class="fw-bold mb-0 text-primary">{{ $customerCount }}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">{{ __('Seller (Penjual Mitra)') }}</div>
                <h3 class="fw-bold mb-0 text-info">{{ $sellerCount }}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">{{ __('Admin') }}</div>
                <h3 class="fw-bold mb-0 text-body m3-tabular-nums">{{ $adminCount }}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-3 col-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 text-center">
                <div class="text-secondary small fw-bold mb-1">{{ __('Suspended') }}</div>
                <h3 class="fw-bold mb-0 text-danger">{{ $suspendedCount }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-3">
        <form action="{{ route('admin.users.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-0 bg-light" placeholder="{{ __('Cari username, nama, email, atau ID Telegram...') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3 col-6">
                <select name="role" class="form-select border-0 bg-light">
                    <option value="">{{ __('Semua Role / Status') }}</option>
                    <option value="customer" {{ request('role') === 'customer' ? 'selected' : '' }}>{{ __('Customer (Biasa)') }}</option>
                    <option value="seller" {{ request('role') === 'seller' ? 'selected' : '' }}>{{ __('Seller (Penjual Mitra)') }}</option>
                    <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>{{ __('Admin (Penuh)') }}</option>
                    <option value="suspended" {{ request('role') === 'suspended' ? 'selected' : '' }}>{{ __('Suspended') }}</option>
                </select>
            </div>
            <div class="col-md-4 col-6 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary px-3 rounded-pill flex-fill">{{ __('Cari & Filter') }}</button>
                @if(request('search') || request('role'))
                    <a href="{{ route('admin.users.index') }}" class="btn btn-light px-3 rounded-pill">{{ __('Reset') }}</a>
                @endif
                <button type="button" class="btn btn-success px-3 rounded-pill" title="{{ __('Ekspor ke Excel') }}" onclick="confirmExport()">
                    <i class="fas fa-file-excel me-1"></i>{{ __('Ekspor') }}
                </button>
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
        @if($users->count() > 0)
        {{-- Desktop Table View --}}
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">{{ __('Telegram ID') }}</th>
                        <th class="py-3 border-0">{{ __('Nama & Username') }}</th>
                        <th class="py-3 border-0">{{ __('Email') }}</th>
                        <th class="py-3 border-0">{{ __('Status & Role') }}</th>
                        <th class="py-3 border-0">{{ __('Bergabung') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td class="px-4 fw-bold text-muted">{{ $user->telegram_id ?? '-' }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0 overflow-hidden" style="width: 36px; height: 36px;">
                                    @if($user->avatar)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($user->avatar) }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                    @else
                                        {{ strtoupper(substr($user->full_name ?? $user->username ?? 'U', 0, 1)) }}
                                    @endif
                                </div>
                                <div>
                                    <div class="fw-bold text-primary">{{ $user->full_name ?? 'Unknown' }}</div>
                                    <div class="small text-muted">{{ $user->username ? '@'.$user->username : '-' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-muted small">
                            <div>{{ $user->email ?? '-' }}</div>
                            <div class="mt-0.5">
                                <span class="badge bg-primary-subtle text-primary rounded-pill fw-semibold" style="font-size: 0.7rem;">
                                    <i class="fas fa-wallet me-1"></i>Rp {{ number_format((int) ($user->customer_wallet_balance ?? 0), 0, ',', '.') }}
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="mb-1">
                                @if($user->is_suspended)
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2"><i class="fas fa-ban me-1"></i>{{ __('Ditangguhkan') }}</span>
                                @else
                                    <span class="badge bg-success-subtle text-success rounded-pill px-2"><i class="fas fa-check me-1"></i>{{ __('Aktif') }}</span>
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
                                            <i class="fas fa-user-shield me-2 text-primary"></i> {{ __('Ubah Detail Akses') }}
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button"
                                                class="dropdown-item py-2 text-success btn-open-user-inject"
                                                data-user-id="{{ $user->id }}"
                                                data-user-label="{{ ($user->full_name ?: $user->username) . ($user->username ? ' (@' . $user->username . ')' : '') }}"
                                                data-balance="Rp {{ number_format((int) ($user->customer_wallet_balance ?? 0), 0, ',', '.') }}">
                                            <i class="fas fa-bolt me-2"></i> {{ __('Injeksi / Kelola Saldo') }}
                                        </button>
                                    </li>
                                    <li>
                                        <form action="{{ route('admin.users.impersonate', $user->id) }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="dropdown-item py-2 text-info">
                                                <i class="fas fa-user-secret me-2"></i> {{ __('Login Sebagai') }}
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    
                                    @if($user->is_suspended)
                                        <li>
                                            <form action="{{ route('admin.users.unsuspend', $user->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2 text-success">
                                                    <i class="fas fa-unlock me-2"></i> {{ __('Cabut Penangguhan') }}
                                                </button>
                                            </form>
                                        </li>
                                    @else
                                        <li>
                                            <form action="{{ route('admin.users.suspend', $user->id) }}" method="POST">
                                                @csrf
                                                 <button type="button" class="dropdown-item py-2 text-warning" onclick="confirmSuspend(event)">
                                                     <i class="fas fa-ban me-2"></i> {{ __('Suspend Pengguna') }}
                                                 </button>
                                            </form>
                                        </li>
                                    @endif
                                    
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction(event, '{{ __('Yakin ingin menghapus permanen pengguna ini? Proses ini tidak dapat dibatalkan.') }}')">
                                                <i class="fas fa-trash me-2"></i> {{ __('Hapus Pengguna') }}
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                            @else
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold btn-open-user-inject"
                                    data-user-id="{{ $user->id }}"
                                    data-user-label="{{ ($user->full_name ?: $user->username) . ($user->username ? ' (@' . $user->username . ')' : '') }}"
                                    data-balance="Rp {{ number_format((int) ($user->customer_wallet_balance ?? 0), 0, ',', '.') }}">
                                <i class="fas fa-bolt me-1"></i>{{ __('Injeksi Saldo') }}
                            </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile Stacked Card List (< 768px) --}}
        <div class="d-md-none p-3 d-flex flex-column gap-2.5">
            @foreach($users as $user)
            <div class="m3-mobile-item-card p-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2.5 min-w-0">
                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0 overflow-hidden" style="width: 40px; height: 40px;">
                            @if($user->avatar)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($user->avatar) }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                {{ strtoupper(substr($user->full_name ?? $user->username ?? 'U', 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-body text-truncate">{{ $user->full_name ?? 'Unknown' }}</div>
                            <div class="small text-secondary text-truncate">
                                {{ $user->username ? '@'.$user->username : '-' }}
                                @if($user->telegram_id)
                                    · <span class="font-monospace">ID {{ $user->telegram_id }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                        @if($user->is_suspended)
                            <span class="badge bg-danger-subtle text-danger rounded-pill px-2">{{ __('Ditangguhkan') }}</span>
                        @else
                            <span class="badge bg-success-subtle text-success rounded-pill px-2">{{ __('Aktif') }}</span>
                        @endif
                        <span class="badge bg-{{ $user->role === 'admin' ? 'primary' : ($user->role === 'seller' ? 'info' : 'secondary') }}-subtle text-{{ $user->role === 'admin' ? 'primary' : ($user->role === 'seller' ? 'info' : 'secondary') }} rounded-pill px-2">
                            {{ ucfirst($user->role ?? 'customer') }}
                        </span>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center py-2 px-2.5 rounded-3 mb-2" style="background: var(--surface-container-high);">
                    <div class="small text-secondary text-truncate me-2">
                        <i class="far fa-envelope me-1"></i>{{ $user->email ?? '-' }}
                    </div>
                    <span class="badge bg-primary-subtle text-primary rounded-pill fw-bold flex-shrink-0 m3-tabular-nums">
                        Rp {{ number_format((int) ($user->customer_wallet_balance ?? 0), 0, ',', '.') }}
                    </span>
                </div>

                <div class="small text-muted mb-2">
                    <i class="far fa-calendar me-1"></i>{{ __('Bergabung:') }} {{ $user->created_at->format('d M Y') }}
                </div>

                <div class="d-flex gap-2 pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                    <button type="button"
                            class="btn btn-sm btn-tonal rounded-pill flex-grow-1 fw-semibold btn-open-user-inject"
                            style="min-height: 40px;"
                            data-user-id="{{ $user->id }}"
                            data-user-label="{{ ($user->full_name ?: $user->username) . ($user->username ? ' (@' . $user->username . ')' : '') }}"
                            data-balance="Rp {{ number_format((int) ($user->customer_wallet_balance ?? 0), 0, ',', '.') }}">
                        <i class="fas fa-bolt me-1"></i>{{ __('Saldo') }}
                    </button>
                    @if($user->id !== Auth::id())
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill flex-grow-1 fw-semibold" style="min-height: 40px;" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $user->id }}">
                        <i class="fas fa-user-shield me-1"></i>{{ __('Akses') }}
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" style="min-height: 40px;" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px; min-width: 200px;">
                            <li>
                                <form action="{{ route('admin.users.impersonate', $user->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 text-info">
                                        <i class="fas fa-user-secret me-2"></i> {{ __('Login Sebagai') }}
                                    </button>
                                </form>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            @if($user->is_suspended)
                                <li>
                                    <form action="{{ route('admin.users.unsuspend', $user->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item py-2 text-success">
                                            <i class="fas fa-unlock me-2"></i> {{ __('Cabut Penangguhan') }}
                                        </button>
                                    </form>
                                </li>
                            @else
                                <li>
                                    <form action="{{ route('admin.users.suspend', $user->id) }}" method="POST">
                                        @csrf
                                        <button type="button" class="dropdown-item py-2 text-warning" onclick="confirmSuspend(event)">
                                            <i class="fas fa-ban me-2"></i> {{ __('Suspend Pengguna') }}
                                        </button>
                                    </form>
                                </li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction(event, '{{ __('Yakin ingin menghapus permanen pengguna ini? Proses ini tidak dapat dibatalkan.') }}')">
                                        <i class="fas fa-trash me-2"></i> {{ __('Hapus Pengguna') }}
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <div class="px-4 py-3 border-top">
            {{ $users->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-users text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">{{ __('Tidak ada pengguna yang ditemukan.') }}</p>
        </div>
        @endif
    </div>
</div>

@push('modals')
@foreach($users as $user)
@if($user->id !== Auth::id())
{{-- Edit Role Modal --}}
<div class="modal fade" id="editUserModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-body">{{ __('Ubah Hak Akses & Detail Pengguna') }}</h5>
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
                            <option value="customer" {{ $user->role === 'customer' ? 'selected' : '' }}>{{ __('Customer (Biasa)') }}</option>
                            <option value="seller" {{ $user->role === 'seller' ? 'selected' : '' }}>{{ __('Seller (Penjual Mitra)') }}</option>
                            <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>{{ __('Admin (Penuh)') }}</option>
                        </select>
                    </div>

                    <div id="seller_fields_{{ $user->id }}" class="{{ $user->role === 'seller' ? '' : 'd-none' }}">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">{{ __('Saldo Dompet (Wallet Balance)') }}</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">Rp</span>
                                <input type="number" name="wallet_balance" class="form-control" value="{{ $user->wallet_balance ?? 0 }}" min="0">
                            </div>
                        </div>

                        <div class="mb-3 p-3 rounded-3 border" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <label class="form-check-label fw-bold text-body mb-0" for="user_comm_per_prod_{{ $user->id }}">
                                        {{ __('Skema Komisi Khusus Per-Produk') }}
                                    </label>
                                    <div class="text-muted small" style="font-size: 0.75rem;">
                                        {{ __('Aktifkan jika potongan komisi diatur per-produk di Katalog Admin.') }}
                                    </div>
                                </div>
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" role="switch" name="commission_per_product" id="user_comm_per_prod_{{ $user->id }}" value="1" {{ !empty($user->commission_per_product) ? 'checked' : '' }} onchange="toggleUserCommissionScheme({{ $user->id }})">
                                </div>
                            </div>
                            <div id="user_per_prod_alert_{{ $user->id }}" class="{{ !empty($user->commission_per_product) ? '' : 'd-none' }} mt-2">
                                <div class="badge bg-primary-subtle text-primary border border-primary-subtle small fw-normal d-block text-start p-2" style="white-space: normal; line-height: 1.4;">
                                    <i class="fas fa-info-circle me-1"></i> {{ __('Mode Per-Produk Aktif: Potongan komisi dihitung sesuai setelan masing-masing produk di Katalog Admin (input Fee % di bawah menjadi nilai cadangan jika produk belum disetel).') }}
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold" id="user_seller_fee_label_{{ $user->id }}">
                                {{ !empty($user->commission_per_product) ? __('Fee Default Platform (Cadangan %)') : __('Persentase Potongan Platform (Fee %)') }}
                            </label>
                            <div class="input-group">
                                <input type="number" name="platform_fee_percent" class="form-control" value="{{ $user->platform_fee_percent ?? 10 }}" min="0" max="100">
                                <span class="input-group-text bg-light text-muted">%</span>
                            </div>
                            <div class="form-text small text-muted">{{ __('Komisi bagi hasil yang dipotong oleh platform saat transaksi lunas.') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">{{ __('Jam Karantina Default (Save Hours)') }}</label>
                            <div class="input-group">
                                <input type="number" name="seller_save_hours" class="form-control" value="{{ $user->seller_save_hours ?? 80 }}" min="0">
                                <span class="input-group-text bg-light text-muted">{{ __('jam') }}</span>
                            </div>
                            <div class="form-text small text-muted">{{ __('Durasi karantina stok sebelum pindah otomatis dari *Simpan Akun* ke *Ready*.') }}</div>
                        </div>
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
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="allowed_tools[]" value="2fa_generator" id="tool_2fa_{{ $user->id }}" {{ is_array($user->allowed_tools) && in_array('2fa_generator', $user->allowed_tools) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="tool_2fa_{{ $user->id }}">{{ __('2FA Code Generator') }}</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="allowed_tools[]" value="rekber" id="tool_rekber_{{ $user->id }}" {{ $user->canUseRekber() ? 'checked' : '' }}>
                            <label class="form-check-label small" for="tool_rekber_{{ $user->id }}">{{ __('Layanan Rekber (Escrow)') }}</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="allowed_tools[]" value="reseller_api" id="tool_reseller_api_{{ $user->id }}" {{ is_array($user->allowed_tools) && in_array('reseller_api', $user->allowed_tools) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="tool_reseller_api_{{ $user->id }}">{{ __('API Reseller (H2H)') }}</label>
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

{{-- Modal Injeksi / Potong Saldo User Langsung dari Kelola Pelanggan --}}
<div class="modal fade" id="injectBalanceUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-body"><i class="fas fa-bolt text-primary me-1.5"></i>{{ __('Injeksi / Kelola Saldo User') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <form action="{{ route('admin.wallet.manual-credit') }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" id="uInjUserId">
                <input type="hidden" name="redirect_to" value="back">
                <div class="modal-body p-4">
                    <div class="p-3 rounded-4 border mb-3 small" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">{{ __('Target Akun') }}</span>
                            <strong class="text-body" id="uInjUserLabel">-</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">{{ __('Saldo Dompet Saat Ini') }}</span>
                            <strong class="text-primary" id="uInjBalance">Rp 0</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Jenis Aksi') }}</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="action_type" id="uInjCredit" value="credit" checked autocomplete="off">
                            <label class="btn btn-sm btn-outline-primary fw-semibold py-2" for="uInjCredit">
                                <i class="fas fa-plus-circle me-1"></i>{{ __('Injeksi Saldo (+)') }}
                            </label>
                            <input type="radio" class="btn-check" name="action_type" id="uInjDebit" value="debit" autocomplete="off">
                            <label class="btn btn-sm btn-outline-danger fw-semibold py-2" for="uInjDebit">
                                <i class="fas fa-minus-circle me-1"></i>{{ __('Potong Saldo (-)') }}
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Nominal Saldo (Rp)') }}</label>
                        <input type="number" name="amount" class="form-control fw-bold" min="1000" max="50000000" step="1000" placeholder="50000" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Catatan / Alasan') }}</label>
                        <input type="text" name="note" class="form-control" placeholder="{{ __('Bonus / Kompensasi / Transfer manual') }}" required>
                    </div>

                    <div class="form-check">
                        <input type="hidden" name="notify_telegram" value="0">
                        <input class="form-check-input" type="checkbox" name="notify_telegram" value="1" id="uInjNotifyTg" checked>
                        <label class="form-check-label small text-secondary" for="uInjNotifyTg">{{ __('Kirim notifikasi Telegram ke user') }}</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-check me-1"></i>{{ __('Proses Sekarang') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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

function toggleUserCommissionScheme(userId) {
    var switchInput = document.getElementById('user_comm_per_prod_' + userId);
    var alertBox = document.getElementById('user_per_prod_alert_' + userId);
    var label = document.getElementById('user_seller_fee_label_' + userId);
    if (!switchInput) return;

    if (switchInput.checked) {
        if (alertBox) alertBox.classList.remove('d-none');
        if (label) label.innerText = "{{ __('Fee Default Platform (Cadangan %)') }}";
    } else {
        if (alertBox) alertBox.classList.add('d-none');
        if (label) label.innerText = "{{ __('Persentase Potongan Platform (Fee %)') }}";
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

function confirmSuspend(event) {
    event.preventDefault();
    let element = event.currentTarget;
    let form = element.closest('form');
    
    // First popup: Ask for reason (optional)
    Swal.fire({
        title: '{{ __('Alasan Penangguhan') }}',
        input: 'text',
        inputLabel: '{{ __('Masukkan alasan penangguhan (opsional):') }}',
        inputPlaceholder: '{{ __('Tulis alasan di sini...') }}',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '{{ __('Lanjut') }}',
        denyButtonText: '{{ __('Lewati') }}',
        cancelButtonText: '{{ __('Batal') }}',
        confirmButtonColor: '#3085d6',
        denyButtonColor: '#6c757d',
        cancelButtonColor: '#d33',
        customClass: {
            input: 'form-control rounded-pill px-3',
            popup: 'rounded-4'
        }
    }).then((result) => {
        // If Cancel was clicked, abort
        if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
            return;
        }
        
        let reason = '';
        if (result.isConfirmed) {
            reason = result.value || '';
        } else if (result.isDenied) {
            reason = '';
        } else {
            // Clicked backdrop or escape
            return;
        }
        
        // Second popup: Confirmation (M3 Alert Dialog)
        Swal.fire({
            title: '{{ __('Konfirmasi Penangguhan') }}',
            text: '{{ __('Apakah Anda yakin ingin menangguhkan pengguna ini? Akses bot mereka akan diblokir.') }}',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '{{ __('Konfirmasi') }}',
            cancelButtonText: '{{ __('Batal') }}',
            reverseButtons: false,
            focusConfirm: false
        }).then((confirmResult) => {
            if (confirmResult.isConfirmed) {
                // Show page loader
                let loader = document.getElementById('pageLoader');
                if (loader) {
                    loader.classList.remove('fade-out');
                }
                if (typeof startTopLoadingBar === 'function') {
                    startTopLoadingBar();
                }
                
                // Add reason input dynamically to the form
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
function confirmExport() {
    Swal.fire({
        title: '{{ __('Konfirmasi Ekspor Data') }}',
        html: '<p class="text-muted small mb-3">{{ __('Untuk keamanan, masukkan password admin Anda sebelum mengekspor data pengguna.') }}</p>',
        input: 'password',
        inputPlaceholder: '{{ __('Masukkan password Anda...') }}',
        inputAttributes: { autocomplete: 'current-password' },
        showCancelButton: true,
        showDenyButton: false,
        confirmButtonText: '<i class="fas fa-file-excel me-1"></i> {{ __('Ekspor') }}',
        cancelButtonText: '{{ __('Batal') }}',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        allowOutsideClick: true,
        allowEscapeKey: true,
        customClass: { input: 'form-control', popup: 'rounded-4' },
        didOpen: () => {
            const cancelBtn = Swal.getCancelButton();
            if (cancelBtn) {
                cancelBtn.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    Swal.close();
                };
            }
        },
        inputValidator: (value) => {
            if (!value) return '{{ __('Password wajib diisi!') }}';
        }
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = "{{ route('admin.users.export', request()->all()) }}";
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = "{{ csrf_token() }}";
        const pwdInput = document.createElement('input');
        pwdInput.type = 'hidden';
        pwdInput.name = 'password';
        pwdInput.value = result.value;
        form.appendChild(csrfInput);
        form.appendChild(pwdInput);
        document.body.appendChild(form);
        form.submit();
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const injModalEl = document.getElementById('injectBalanceUserModal');
    const injUserId = document.getElementById('uInjUserId');
    const injUserLabel = document.getElementById('uInjUserLabel');
    const injBalance = document.getElementById('uInjBalance');

    document.querySelectorAll('.btn-open-user-inject').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!injModalEl) return;
            if (injUserId) injUserId.value = this.dataset.userId || '';
            if (injUserLabel) injUserLabel.textContent = this.dataset.userLabel || '-';
            if (injBalance) injBalance.textContent = this.dataset.balance || 'Rp 0';
            bootstrap.Modal.getOrCreateInstance(injModalEl).show();
        });
    });
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
