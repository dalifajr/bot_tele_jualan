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

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($users->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">Telegram ID</th>
                        <th class="py-3 border-0">Nama Lengkap</th>
                        <th class="py-3 border-0">Username</th>
                        <th class="py-3 border-0">Role</th>
                        <th class="py-3 border-0">Bergabung</th>
                        <th class="py-3 border-0 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td class="px-4 fw-bold text-muted">{{ $user->telegram_id }}</td>
                        <td class="fw-bold text-primary">{{ $user->full_name ?? '-' }}</td>
                        <td>{{ $user->username ? '@'.$user->username : '-' }}</td>
                        <td>
                            <span class="badge bg-{{ $user->role === 'admin' ? 'primary' : 'secondary' }}-subtle text-{{ $user->role === 'admin' ? 'primary' : 'secondary' }} rounded-pill px-3">
                                {{ ucfirst($user->role ?? 'customer') }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="text-end px-4">
                            @if($user->id !== Auth::id())
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $user->id }}">
                                Edit Role
                            </button>
                            @else
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled>Edit Role</button>
                            @endif
                        </td>
                    </tr>

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
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $users->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-users text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada pelanggan.</p>
        </div>
        @endif
    </div>
</div>
@endsection
