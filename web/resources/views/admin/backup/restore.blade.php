@extends('layouts.app')

@section('title', 'Backup & Restore - Pemulihan')
@section('page_subtitle', 'Backup & Restore')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Backup & Restore</h4>
        <p class="text-muted mb-0">Kelola cadangan data system, unduh snapshot, dan atur otomatisasi jadwal backup</p>
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
        <a class="nav-link text-secondary" href="{{ route('admin.backup.index') }}">
            <i class="fas fa-chart-line me-1"></i> Dashboard
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-secondary" href="{{ route('admin.backup.history') }}">
            <i class="fas fa-history me-1"></i> Riwayat Backup
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-bold text-primary" href="{{ route('admin.backup.restore.show') }}" style="border-bottom: 3px solid var(--bs-primary); margin-bottom: -2px;">
            <i class="fas fa-undo me-1"></i> Pemulihan Data (Restore)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-secondary" href="{{ route('admin.backup.settings.show') }}">
            <i class="fas fa-cog me-1"></i> Pengaturan & Jadwal
        </a>
    </li>
</ul>

{{-- Restore Card --}}
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-2"><i class="fas fa-upload text-danger me-2"></i>Pemulihan Data (Restore)</h5>
        <p class="text-muted small mb-4">Pulihkan database dari file ZIP cadangan yang telah diunduh sebelumnya.</p>
        
        <form action="{{ route('admin.backup.restore') }}" method="POST" enctype="multipart/form-data" id="formRestore">
            @csrf
            <div class="mb-4">
                <label class="form-label fw-bold small text-muted">FILE CADANGAN (ZIP)</label>
                <input type="file" name="backup_file" class="form-control form-control-lg" accept=".zip" required style="border-radius: 10px;">
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold small text-muted d-block">MODE PEMULIHAN</label>
                <div class="form-check form-check-inline me-4">
                    <input class="form-check-input" type="radio" name="mode" id="modeOverwrite" value="overwrite" checked>
                    <label class="form-check-label small fw-bold" for="modeOverwrite">Full Overwrite (Hapus & Timpa)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="mode" id="modeMerge" value="merge">
                    <label class="form-check-label small fw-bold text-muted" for="modeMerge">Smart Merge (Skip Duplikat)</label>
                </div>
            </div>

            <div class="alert alert-warning border-0 p-3 mb-4 d-flex align-items-start gap-2" style="border-radius: 12px; font-size: 0.85rem;">
                <i class="fas fa-exclamation-triangle text-warning mt-1 fs-5"></i>
                <div>
                    <strong>Peringatan Penting:</strong> Proses restore akan me-lock database untuk penulisan. Bot Telegram akan mengalami delay selama proses berlangsung.
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-danger rounded-pill py-3 fw-bold fs-6">
                    <i class="fas fa-undo me-2"></i>Jalankan Pemulihan Database
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // SweetAlert restore confirmation
    document.getElementById('formRestore').addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Konfirmasi Restore Database',
            text: 'Apakah Anda yakin ingin memulihkan database? Seluruh data aktif Anda akan terpengaruh sesuai dengan mode yang dipilih. Tindakan ini tidak dapat dibatalkan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Jalankan Restore!',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'rounded-4',
                confirmButton: 'btn btn-danger rounded-pill px-4',
                cancelButton: 'btn btn-secondary rounded-pill px-4 ms-2'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                const pageLoader = document.getElementById('pageLoader');
                if (pageLoader) {
                    pageLoader.classList.remove('fade-out');
                }
                if (typeof startTopLoadingBar === 'function') {
                    startTopLoadingBar();
                }
                this.submit();
            }
        });
    });
</script>
@endpush
