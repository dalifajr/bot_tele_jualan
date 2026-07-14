@extends('layouts.app')

@section('title', 'Riwayat Percobaan Login')
@section('page_subtitle', 'Akun')

@section('content')
<div class="row g-4">
    <div class="col-lg-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="fas fa-shield-alt text-primary me-2"></i>Riwayat Percobaan Login</h4>
                <p class="text-muted mb-0">Catatan masuk ke akun Anda berdasarkan alamat IP dan perangkat.</p>
            </div>
            <a href="{{ route('profile') }}" class="btn btn-outline-secondary rounded-pill btn-sm px-3">
                <i class="fas fa-arrow-left me-1"></i>Kembali ke Profil
            </a>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
            <div class="card-body p-0">
                @if($loginLogs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom bg-light">
                                <th class="px-4 py-3 border-0">Waktu</th>
                                <th class="py-3 border-0">Status</th>
                                <th class="py-3 border-0">IP & Lokasi</th>
                                <th class="py-3 border-0">Perangkat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loginLogs as $log)
                            <tr>
                                <td class="px-4 text-secondary small">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($log->is_successful)
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Berhasil</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i>Gagal</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-medium text-dark">{{ $log->ip_address }}</div>
                                    <div class="text-secondary small"><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $log->location ?? 'Unknown' }}</div>
                                </td>
                                <td>
                                    <div class="text-dark small">{{ $log->device_type }}</div>
                                    <div class="text-secondary small" title="{{ $log->user_agent }}"><i class="fab fa-chrome text-primary me-1"></i>{{ $log->browser ?? '-' }}</div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-top">
                    {{ $loginLogs->links('pagination::bootstrap-5') }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-shield-alt text-muted mb-3" style="font-size: 3rem; opacity: 0.5;"></i>
                    <p class="text-muted mb-0">Belum ada riwayat percobaan login.</p>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
