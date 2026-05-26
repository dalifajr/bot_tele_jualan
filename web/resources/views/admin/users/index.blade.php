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
                            <span class="badge bg-{{ $user->role === 'admin' ? 'primary' : 'secondary' }}-subtle text-{{ $user->role === 'admin' ? 'primary' : 'secondary' }} rounded-pill px-2">
                                {{ ucfirst($user->role ?? 'customer') }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="text-end px-4">
                            @if($user->id !== Auth::id())
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px; min-width: 200px;">
                                    <li>
                                        <button class="dropdown-item py-2" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $user->id }}">
                                            <i class="fas fa-user-shield me-2 text-primary"></i> Ubah Role
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
                                                <button type="submit" class="dropdown-item py-2 text-warning" onclick="return confirm('Yakin ingin menangguhkan pengguna ini? Akses bot mereka akan diblokir.')">
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
                                            <button type="submit" class="dropdown-item py-2 text-danger" onclick="return confirm('Yakin ingin menghapus permanen pengguna ini? Proses ini tidak dapat dibatalkan.')">
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
                <h5 class="fw-bold">Ubah Hak Akses Pengguna</h5>
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
                        <select name="role" class="form-select" required>
                            <option value="customer" {{ $user->role === 'customer' ? 'selected' : '' }}>Customer (Biasa)</option>
                            <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Admin (Penuh)</option>
                        </select>
                        <div class="form-text mt-2">
                            <i class="fas fa-info-circle text-primary me-1"></i>
                            Menjadikan pengguna sebagai Admin akan memberikan akses penuh ke Panel Web ini dan menu Bot Admin Telegram secara bersamaan.
                        </div>
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
@endsection
