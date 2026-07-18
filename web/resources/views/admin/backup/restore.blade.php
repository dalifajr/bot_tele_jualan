@extends('layouts.app')

@section('title', 'Backup & Restore - Pemulihan')
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
        <a class="nav-link text-secondary" href="{{ route('admin.backup.history') }}">
            <i class="fas fa-history me-1"></i> {{ __('Riwayat Backup') }}
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-bold text-primary" href="{{ route('admin.backup.restore.show') }}" style="border-bottom: 3px solid var(--bs-primary); margin-bottom: -2px;">
            <i class="fas fa-undo me-1"></i> Pemulihan Data (Restore)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-secondary" href="{{ route('admin.backup.settings.show') }}">
            <i class="fas fa-cog me-1"></i> {{ __('Pengaturan & Jadwal') }}
        </a>
    </li>
</ul>

{{-- Restore Card --}}
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-2"><i class="fas fa-upload text-danger me-2"></i>Pemulihan Data (Restore)</h5>
        <p class="text-muted small mb-4">{{ __('Pulihkan database dari file ZIP cadangan yang telah diunduh sebelumnya.') }}</p>
        
        <form action="{{ route('admin.backup.restore') }}" method="POST" enctype="multipart/form-data" id="formRestore">
            @csrf
            <div class="mb-4">
                <label class="form-label fw-bold small text-muted">FILE CADANGAN (ZIP)</label>
                <input type="file" name="backup_file" class="form-control form-control-lg" accept=".zip" required style="border-radius: 10px;">
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold small text-muted d-block">{{ __('MODE PEMULIHAN') }}</label>
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
                    <strong>{{ __('Peringatan Penting:') }}</strong> {{ __('Proses restore akan me-lock database untuk penulisan. Bot Telegram akan mengalami delay selama proses berlangsung.') }}
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-danger rounded-pill py-3 fw-bold fs-6">
                    <i class="fas fa-undo me-2"></i>{{ __('Jalankan Pemulihan Database') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Upload Progress Modal --}}
<div class="modal fade" id="uploadProgressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true" style="z-index: 1070 !important;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; background: var(--bs-body-bg); backdrop-filter: none; -webkit-backdrop-filter: none; opacity: 1;">
            <div class="modal-body p-4 text-center">
                <div class="spinner-border text-danger mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">{{ __('Loading...') }}</span>
                </div>
                <h5 class="fw-bold mb-2">{{ __('Mengunggah File Cadangan') }}</h5>
                <p class="text-muted small mb-4">{{ __('Mohon tunggu, berkas sedang dikirim ke server...') }}</p>
                <div class="progress mb-3" style="height: 10px; border-radius: 5px;">
                    <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div id="uploadProgressPercent" class="fw-bold text-danger">0%</div>
            </div>
        </div>
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
                // Close SweetAlert completely before showing Bootstrap modal
                Swal.close();

                // Small delay to let SweetAlert's overlay fully close
                setTimeout(() => {
                const uploadModal = new bootstrap.Modal(document.getElementById('uploadProgressModal'));
                uploadModal.show();

                const formData = new FormData(this);
                const xhr = new XMLHttpRequest();

                xhr.open('POST', this.action, true);
                
                // Track upload progress
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        document.getElementById('uploadProgressBar').style.width = percentComplete + '%';
                        document.getElementById('uploadProgressPercent').innerText = percentComplete + '%';
                        if (percentComplete === 100) {
                            document.querySelector('#uploadProgressModal h5').innerText = "Memproses Berkas...";
                            document.querySelector('#uploadProgressModal p').innerText = "Menyiapkan laman pemulihan...";
                        }
                    }
                });

                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success && response.redirect_url) {
                                window.location.href = response.redirect_url;
                            } else {
                                uploadModal.hide();
                                Swal.fire('Error', 'Gagal memproses pengunggahan.', 'error');
                            }
                        } catch (err) {
                            uploadModal.hide();
                            Swal.fire('Error', 'Terjadi kesalahan sistem parsing response.', 'error');
                        }
                    } else {
                        uploadModal.hide();
                        let errorMsg = 'Gagal mengunggah berkas backup.';
                        if (xhr.status === 413) {
                            errorMsg = 'Ukuran berkas terlalu besar (Limit Nginx/PHP).';
                        } else {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMsg = response.message || errorMsg;
                            } catch(e) {}
                        }
                        Swal.fire('Error', errorMsg, 'error');
                    }
                };

                xhr.onerror = function() {
                    uploadModal.hide();
                    Swal.fire('Error', 'Koneksi jaringan terputus.', 'error');
                };

                xhr.send(formData);
                }, 300); // end setTimeout
            }
        });
    });
</script>
@endpush
