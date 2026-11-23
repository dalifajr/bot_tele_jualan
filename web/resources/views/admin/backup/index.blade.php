@extends('layouts.app')

@section('title', __('Backup & Restore - Dashboard'))
@section('page_subtitle', __('Backup & Restore'))

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

{{-- Sub Navigation Tabs (M3 Scrollable Pill Strip) --}}
<div class="mb-4">
    <div class="m3-tab-strip">
        <a class="m3-tab-pill active" href="{{ route('admin.backup.index') }}">
            <i class="fas fa-chart-line"></i><span>{{ __('Dashboard') }}</span>
        </a>
        <a class="m3-tab-pill" href="{{ route('admin.backup.history') }}">
            <i class="fas fa-history"></i><span>{{ __('Riwayat Backup') }}</span>
        </a>
        <a class="m3-tab-pill" href="{{ route('admin.backup.restore.show') }}">
            <i class="fas fa-undo"></i><span>{{ __('Pemulihan Data (Restore)') }}</span>
        </a>
        <a class="m3-tab-pill" href="{{ route('admin.backup.settings.show') }}">
            <i class="fas fa-cog"></i><span>{{ __('Pengaturan & Jadwal') }}</span>
        </a>
    </div>
</div>

{{-- Database Statistics --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle p-2.5 p-md-3 bg-primary-subtle text-primary">
                    <i class="fas fa-hdd fs-4"></i>
                </div>
                <div class="min-w-0">
                    <small class="text-muted fw-bold d-block text-uppercase text-truncate">{{ __('Ukuran DB') }}</small>
                    <h4 class="fw-bold m-0 text-primary m3-tabular-nums">{{ $dbSizeFormatted }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle p-2.5 p-md-3 bg-success-subtle text-success">
                    <i class="fas fa-database fs-4"></i>
                </div>
                <div class="min-w-0">
                    <small class="text-muted fw-bold d-block text-uppercase text-truncate">{{ __('Total Record') }}</small>
                    <h4 class="fw-bold m-0 text-success m3-tabular-nums">{{ number_format($totalRecords, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle p-2.5 p-md-3 bg-warning-subtle text-warning">
                    <i class="fas fa-clock fs-4"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block text-uppercase">{{ __('Auto-Backup') }}</small>
                    <h5 class="fw-bold m-0 mt-1">
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
        <h5 class="fw-bold mb-2"><i class="fas fa-download text-primary me-2"></i>{{ __('Pencadangan Manual (Export)') }}</h5>
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
                        <i class="fas fa-file-code me-2 fs-5"></i> {{ __('Download JSON (Bot Compatible)') }}
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
    <div class="card-body p-3 p-md-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-user-shield text-secondary me-2"></i>{{ __('Riwayat Aktivitas Backup & Restore (Log Audit)') }}</h5>
        {{-- Desktop Table --}}
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead>
                    <tr style="background: var(--surface-container-high);">
                        <th>{{ __('Waktu (WIB)') }}</th>
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
                            <span class="badge bg-warning-subtle text-warning-emphasis text-uppercase px-2">{{ __('Auto Backup') }}</span>
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

        {{-- Mobile Collapsible Compact List (< 768px) --}}
        <div class="d-md-none m3-collapsible-list" id="adminBackupAuditAccordion">
            @forelse($logs as $log)
            <div class="m3-mobile-item-card m3-collapsible-card">
                <div class="m3-compact-row">
                    <button type="button"
                            class="m3-compact-trigger"
                            data-bs-toggle="collapse"
                            data-bs-target="#backupAuditCollapse-{{ $log->id }}"
                            aria-expanded="false"
                            aria-controls="backupAuditCollapse-{{ $log->id }}">
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold text-body text-truncate" style="font-size: 0.83rem;">
                                @if($log->actor)
                                    {{ $log->actor->full_name ?: $log->actor->username }}
                                @else
                                    <span class="text-success"><i class="fas fa-robot me-1"></i>{{ __('System Cron') }}</span>
                                @endif
                            </div>
                            <div class="text-secondary text-truncate font-monospace" style="font-size: 0.71rem;">
                                {{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i') }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            @if($log->action === 'backup_restore')
                                <span class="badge bg-danger text-uppercase rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Restore') }}</span>
                            @elseif($log->action === 'backup_create')
                                <span class="badge bg-primary text-uppercase rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Export') }}</span>
                            @elseif($log->action === 'system_auto_backup')
                                <span class="badge bg-warning-subtle text-warning-emphasis text-uppercase rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Auto Backup') }}</span>
                            @else
                                <span class="badge bg-secondary text-uppercase rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Delete') }}</span>
                            @endif
                            <i class="fas fa-chevron-down m3-compact-chevron"></i>
                        </div>
                    </button>
                </div>
                <div class="collapse" id="backupAuditCollapse-{{ $log->id }}" data-bs-parent="#adminBackupAuditAccordion">
                    <div class="m3-collapsible-body small text-secondary text-break">
                        {{ $log->detail ?: '-' }}
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-4 text-muted small">{{ __('Belum ada catatan aktivitas log audit backup.') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
