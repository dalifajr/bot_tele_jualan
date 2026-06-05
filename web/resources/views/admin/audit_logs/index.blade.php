@extends('layouts.app')

@section('title', 'Log Audit Sistem')
@section('page_subtitle', 'Audit Logs')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-1 text-body">Log Audit Sistem</h1>
        <p class="text-muted mb-0">Pelacakan riwayat aktivitas administratif dan perubahan sistem</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form action="{{ route('admin.audit-logs.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-9 col-sm-8">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Cari berdasarkan aksi, detail, atau nama pelaku..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3 col-sm-4 d-grid">
                <button type="submit" class="btn btn-primary rounded-pill"><i class="fas fa-filter me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-body p-0">
        @if($logs->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0" style="width: 180px;">Waktu</th>
                        <th class="py-3 border-0" style="width: 180px;">Pelaku (Actor)</th>
                        <th class="py-3 border-0" style="width: 180px;">Aksi</th>
                        <th class="py-3 border-0">Detail Perubahan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td class="px-4 text-secondary small">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i:s') }}</td>
                        <td>
                            @if($log->actor)
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    {{ strtoupper(substr($log->actor->username ?? 'U', 0, 1)) }}
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="fw-bold text-body" style="font-size: 0.85rem;">{{ $log->actor->full_name ?? $log->actor->username }}</span>
                                    <span class="text-muted small">@if($log->actor->role === 'admin') Admin @else Seller @endif</span>
                                </div>
                            </div>
                            @else
                            <div class="d-flex align-items-center gap-2 text-muted">
                                <div class="avatar bg-secondary-subtle text-secondary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    S
                                </div>
                                <span class="small fw-semibold">SYSTEM / BOT</span>
                            </div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-3">{{ $log->action }}</span>
                        </td>
                        <td>
                            <div class="text-muted text-wrap" style="max-width: 450px; font-size: 0.85rem;">
                                {{ $log->detail }}
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-history text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada data log audit.</p>
        </div>
        @endif
    </div>
    
    @if($logs->hasPages())
    <div class="card-footer bg-transparent border-0 px-4 py-3">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
