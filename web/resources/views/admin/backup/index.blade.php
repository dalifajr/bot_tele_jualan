@extends('layouts.app')

@section('title', 'Backup & Restore - Dashboard')
@section('page_subtitle', 'Backup & Restore')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Backup & Restore') }}</h4>
        <p class="text-muted mb-0">{{ __('Kelola cadangan data system, unduh snapshot, dan atur otomatisasi jadwal backup') }}</p>
    </div>
</div>

{{-- Flash Messages --}}
@if(session('success'))
<div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2 mb-4" style="border-radius: 12px;">
    <i class="fas fa-check-circle fs-5"></i>
    <div>{{ session('success') }}</div>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-2 mb-4" style="border-radius: 12px;">
    <i class="fas fa-exclamation-circle fs-5"></i>
    <div>{{ session('error') }}</div>
</div>
@endif

{{-- Sub Navigation Tabs --}}
<ul class="nav nav-tabs nav-tabs-bordered mb-4" id="backupTabs" style="border-bottom: 2px solid var(--bs-border-color);">
    <li class="nav-item">
        <a class="nav-link active fw-bold text-primary" href="{{ route('admin.backup.index') }}" style="border-bottom: 3px solid var(--bs-primary); margin-bottom: -2px;">
            <i class="fas fa-chart-line me-1"></i> {{ __('Dashboard') }}
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-secondary" href="{{ route('admin.backup.history') }}">
            <i class="fas fa-history me-1"></i> {{ __('Riwayat Backup') }}
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-secondary" href="{{ route('admin.backup.restore.show') }}">
            <i class="fas fa-undo me-1"></i> Pemulihan Data (Restore)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-secondary" href="{{ route('admin.backup.settings.show') }}">
            <i class="fas fa-cog me-1"></i> {{ __('Pengaturan & Jadwal') }}
        </a>
    </li>
</ul>

{{-- Database Statistics --}}
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 bg-primary-subtle text-primary">
                    <i class="fas fa-hdd fs-3"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block text-uppercase">{{ __('Ukuran Database') }}</small>
                    <h3 class="fw-bold m-0 text-primary">{{ $dbSizeFormatted }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 bg-success-subtle text-success">
                    <i class="fas fa-database fs-3"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block text-uppercase">{{ __('Total Record Data') }}</small>
                    <h3 class="fw-bold m-0 text-success">{{ number_format($totalRecords, 0, ',', '.') }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 bg-warning-subtle text-warning">
                    <i class="fas fa-clock fs-3"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block text-uppercase">{{ __('Auto-Backup') }}</small>
                    <h5 class="fw-bold m-0">
                        @if($autoBackupEnabled === '1')
                        <span class="badge bg-success-subtle text-success rounded-pill px-3">{{ __('Aktif') }}</span>
                        @else
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-3">{{ __('Non-Aktif') }}</span>
                        @endif
                    </h5>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-2"><i class="fas fa-download text-primary me-2"></i>Pencadangan Manual (Export)</h5>
        <p class="text-muted small mb-4">{{ __('Unduh salinan data sistem Anda saat ini. Kami menyarankan untuk mengunduh Snapshot secara berkala sebelum melakukan perubahan besar.') }}</p>
        
        <div class="row g-3">
            <div class="col-md-6">
                <a href="{{ route('admin.backup.download', 'snapshot') }}" class="btn btn-primary rounded-pill py-3 fw-bold text-start px-4 d-flex align-items-center justify-content-between h-100">
                    <div>
                        <i class="fas fa-file-archive me-2 fs-5"></i> {{ __('Download Snapshot') }}
                        <small class="d-block fw-light text-white-50 mt-1" style="font-size: 0.75rem;">{{ __('Mencakup database (SQLite/SQL) dan seluruh file QRIS/media lokal') }}</small>
                    </div>
                    <i class="fas fa-chevron-right fs-5"></i>
                </a>
            </div>
            <div class="col-md-6">
                <a href="{{ route('admin.backup.download', 'json') }}" class="btn btn-outline-primary rounded-pill py-3 fw-bold text-start px-4 d-flex align-items-center justify-content-between h-100">
                    <div>
                        <i class="fas fa-file-code me-2 fs-5"></i> Download JSON (Bot Compatible)
                        <small class="d-block fw-light text-muted mt-1" style="font-size: 0.75rem;">{{ __('Database tables dalam JSON ZIP. Kompatibel dengan fitur restore Bot Telegram') }}</small>
                    </div>
                    <i class="fas fa-chevron-right fs-5"></i>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Audit Logs --}}
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-user-shield text-secondary me-2"></i>Riwayat Aktivitas Backup & Restore (Log Audit)</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead>
                    <tr class="table-light">
                        <th>Waktu (WIB)</th>
                        <th>{{ __('User Admin') }}</th>
                        <th>{{ __('Aksi Aktivitas') }}</th>
                        <th>{{ __('Detail Log') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</td>
                        <td class="fw-bold">
                            @if($log->actor)
                            {{ $log->actor->full_name ?: $log->actor->username }}
                            @else
                            <span class="text-success"><i class="fas fa-robot me-1"></i>{{ __('System Cron') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($log->action === 'backup_restore')
                            <span class="badge bg-danger text-uppercase px-2">{{ __('Restore') }}</span>
                            @elseif($log->action === 'backup_create')
                            <span class="badge bg-primary text-uppercase px-2">{{ __('Export') }}</span>
                            @elseif($log->action === 'system_auto_backup')
                            <span class="badge bg-warning text-uppercase px-2 text-dark">{{ __('Auto Backup') }}</span>
                            @else
                            <span class="badge bg-secondary text-uppercase px-2">{{ __('Delete') }}</span>
                            @endif
                        </td>
                        <td class="text-muted text-wrap" style="max-width: 350px;">{{ $log->detail }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">{{ __('Belum ada catatan aktivitas log audit backup.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
