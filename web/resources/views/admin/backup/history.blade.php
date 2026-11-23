@extends('layouts.app')

@section('title', __('Backup & Restore - Riwayat'))
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
        <a class="m3-tab-pill" href="{{ route('admin.backup.index') }}">
            <i class="fas fa-chart-line"></i><span>{{ __('Dashboard') }}</span>
        </a>
        <a class="m3-tab-pill active" href="{{ route('admin.backup.history') }}">
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

{{-- Backup History --}}
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-3 p-md-4">
        <h5 class="fw-bold mb-2"><i class="fas fa-history text-success me-2"></i>{{ __('Riwayat File Backup Lokal') }}</h5>
        <p class="text-muted small mb-4">{{ __('Kumpulan file backup yang tersimpan di server local. Sistem membatasi riwayat maksimal 10 file backup terbaru.') }}</p>

        {{-- Desktop Table --}}
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead>
                    <tr style="background: var(--surface-container-high);">
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

        {{-- Mobile Stacked Cards (< 768px) --}}
        <div class="d-md-none d-flex flex-column gap-2.5">
            @forelse($history as $item)
            <div class="m3-mobile-item-card p-3">
                <div class="fw-bold small text-body font-monospace mb-1" style="word-break: break-all;">{{ $item['filename'] }}</div>
                <div class="d-flex justify-content-between align-items-center small text-secondary mb-2">
                    <span><i class="far fa-clock me-1"></i>{{ $item['created_at'] }}</span>
                    <span class="badge bg-secondary-subtle text-body rounded-pill m3-tabular-nums">{{ $item['size'] }} · {{ $item['type'] }}</span>
                </div>
                <div class="d-flex gap-2 pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                    <a href="{{ route('admin.backup.download', $item['filename']) }}" class="btn btn-sm btn-tonal rounded-pill flex-grow-1 fw-semibold d-inline-flex align-items-center justify-content-center gap-1" style="min-height: 40px;">
                        <i class="fas fa-download"></i><span>{{ __('Unduh Backup') }}</span>
                    </a>
                    <form action="{{ route('admin.backup.destroy', $item['filename']) }}" method="POST" class="m-0 form-delete-backup">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" style="min-height: 40px;" title="{{ __('Hapus File') }}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <div class="text-center py-5 text-muted small">
                <i class="fas fa-folder-open fs-1 mb-2 opacity-50"></i><br>
                {{ __('Belum ada file backup lokal yang tersimpan.') }}
            </div>
            @endforelse
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
                title: '{{ __('Hapus File Backup?') }}',
                text: '{{ __('File backup yang dihapus dari server local tidak dapat diunduh kembali.') }}',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: '{{ __('Konfirmasi') }}',
                cancelButtonText: '{{ __('Batal') }}',
                reverseButtons: false,
                focusConfirm: false
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
