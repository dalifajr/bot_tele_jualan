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
            <p class="text-muted mb-0">Belum ada pelanggan.</p>
        </div>
        @endif
    </div>
</div>
@endsection
