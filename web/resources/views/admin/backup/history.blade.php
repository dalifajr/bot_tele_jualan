@extends('layouts.app')

@section('title', 'Backup & Restore - Riwayat')
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
        <a class="nav-link text-secondary" href="{{ route('admin.backup.index') }}">
            <i class="fas fa-chart-line me-1"></i> {{ __('Dashboard') }}
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-bold text-primary" href="{{ route('admin.backup.history') }}" style="border-bottom: 3px solid var(--bs-primary); margin-bottom: -2px;">
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

{{-- Backup History --}}
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-2"><i class="fas fa-history text-success me-2"></i>{{ __('Riwayat File Backup Lokal') }}</h5>
        <p class="text-muted small mb-4">{{ __('Kumpulan file backup yang tersimpan di server local. Sistem membatasi riwayat maksimal 10 file backup terbaru.') }}</p>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead>
                    <tr class="table-light">
                        <th>{{ __('Nama File / Tanggal') }}</th>
                        <th>{{ __('Ukuran / Tipe') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $item)
                    <tr>
                        <td>
                            <div class="fw-bold text-wrap" style="word-break: break-all;">{{ $item['filename'] }}</div>
                            <small class="text-muted"><i class="far fa-clock me-1"></i>{{ $item['created_at'] }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary rounded px-2">{{ $item['size'] }}</span>
                            <div class="text-muted small mt-1">{{ $item['type'] }}</div>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="{{ route('admin.backup.download', $item['filename']) }}" class="btn btn-sm btn-outline-primary border-0 p-2" title="{{ __('Unduh File') }}">
                                    <i class="fas fa-download fs-5"></i>
                                </a>
                                <form action="{{ route('admin.backup.destroy', $item['filename']) }}" method="POST" class="d-inline form-delete-backup">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-2" title="{{ __('Hapus File') }}">
                                        <i class="fas fa-trash fs-5"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center py-5 text-muted">
                            <i class="fas fa-folder-open fs-1 mb-2"></i><br>
                            {{ __('Belum ada file backup lokal yang tersimpan.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // SweetAlert delete backup file confirmation
    document.querySelectorAll('.form-delete-backup').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Hapus File Backup?',
                text: 'File backup yang dihapus dari server local tidak dapat diunduh kembali.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
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
    });
</script>
@endpush
