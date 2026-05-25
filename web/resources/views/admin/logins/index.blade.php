@extends('layouts.app')

@section('title', 'Notifikasi Login Web')
@section('page_subtitle', 'Sistem Keamanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Daftar Percobaan Login</h4>
        <p class="text-muted mb-0">Riwayat autentikasi masuk ke web admin.</p>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($loginTokens->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom bg-light">
                        <th class="px-4 py-3 border-0">Kode / Token</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">IP Address</th>
                        <th class="py-3 border-0">Browser</th>
                        <th class="py-3 border-0">Waktu Expired</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($loginTokens as $login)
                    <tr>
                        <td class="px-4 fw-bold text-muted">{{ $login->token }}</td>
                        <td>
                            @if($login->status === 'used')
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Digunakan</span>
                            @elseif($login->status === 'pending')
                                <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill">Menunggu Verifikasi</span>
                            @else
                                <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">{{ ucfirst($login->status) }}</span>
                            @endif
                        </td>
                        <td class="text-secondary small">{{ $login->ip_address ?? '-' }}</td>
                        <td class="text-secondary small text-truncate" style="max-width: 200px;" title="{{ $login->user_agent }}">{{ $login->user_agent ?? '-' }}</td>
                        <td class="text-secondary small">{{ $login->expires_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $loginTokens->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-shield-alt text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada riwayat percobaan login.</p>
        </div>
        @endif
    </div>
</div>
@endsection
