@extends('layouts.app')

@section('title', 'Backup & Restore - Pengaturan')
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
        <a class="nav-link text-secondary" href="{{ route('admin.backup.restore.show') }}">
            <i class="fas fa-undo me-1"></i> Pemulihan Data (Restore)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-bold text-primary" href="{{ route('admin.backup.settings.show') }}" style="border-bottom: 3px solid var(--bs-primary); margin-bottom: -2px;">
            <i class="fas fa-cog me-1"></i> Pengaturan & Jadwal
        </a>
    </li>
</ul>

<div class="row g-4">
    {{-- Auto Backup Configuration --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="fas fa-cog text-warning me-2"></i>Pengaturan Auto-Backup Terjadwal</h5>
                <p class="text-muted small mb-4">Buat jadwal pencadangan otomatis. Sistem akan mem-backup database ke folder lokal dan mengirimkannya ke Bot Telegram Admin.</p>

                <form action="{{ route('admin.backup.settings.update') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="auto_backup_enabled" id="autoBackupSwitch" value="1" {{ $autoBackupEnabled === '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold small" for="autoBackupSwitch">Aktifkan Auto-Backup Terjadwal</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">INTERVAL JADWAL BACKUP</label>
                        <select name="auto_backup_schedule" class="form-select">
                            <option value="daily" {{ $autoBackupSchedule === 'daily' ? 'selected' : '' }}>Setiap Hari (Jam 00:00 WIB)</option>
                            <option value="weekly" {{ $autoBackupSchedule === 'weekly' ? 'selected' : '' }}>Setiap Minggu (Hari Minggu)</option>
                            <option value="monthly" {{ $autoBackupSchedule === 'monthly' ? 'selected' : '' }}>Setiap Bulan (Tanggal 1)</option>
                        </select>
                    </div>

                    <div class="alert alert-info border-0 p-3 mb-4 d-flex align-items-center gap-2" style="border-radius: 10px; font-size: 0.8rem;">
                        <i class="fas fa-info-circle text-info"></i>
                        <div>
                            Last run: <strong>{{ $autoBackupLastRun }}</strong>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning rounded-pill py-2 fw-bold">
                            <i class="fas fa-save me-2"></i>Simpan Konfigurasi Auto-Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Database Record Details --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-chart-bar text-info me-2"></i>Informasi Record Tabel Database</h5>
                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead>
                            <tr class="table-light">
                                <th>Nama Tabel</th>
                                <th>Deskripsi Data</th>
                                <th class="text-end">Jumlah Record</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tablesStats as $stat)
                            <tr>
                                <td class="fw-bold"><code>{{ $stat['table'] }}</code></td>
                                <td class="text-muted">{{ $stat['label'] }}</td>
                                <td class="text-end fw-bold">{{ number_format($stat['count'], 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="border-radius: 16px; border-left: 5px solid var(--bs-danger) !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold mb-2 text-danger"><i class="fas fa-trash-alt me-2"></i>Kosongkan Seluruh Data (Wipe Database)</h5>
                        <p class="text-muted small mb-0">Tindakan ini akan mengosongkan seluruh tabel database (produk, stok, pesanan, riwayat, log, dll) secara permanen. Pengaturan sistem dan akun administrator Anda akan tetap dipertahankan agar Anda tidak terputus dari panel admin ini.</p>
                    </div>
                    <div>
                        <button type="button" id="btnWipeData" class="btn btn-danger rounded-pill px-4 py-2 fw-bold">
                            <i class="fas fa-exclamation-triangle me-2"></i>Hapus Semua Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('btnWipeData').addEventListener('click', function() {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Tindakan ini akan menghapus SELURUH data produk, stok, pesanan, komplain, log, dan riwayat! Pengaturan bot dan akun admin tetap dipertahankan agar Anda bisa tetap login.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus Semua Data!',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'rounded-4',
                confirmButton: 'btn btn-danger rounded-pill px-4 me-2',
                cancelButton: 'btn btn-secondary rounded-pill px-4'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading indicator
                Swal.fire({
                    title: 'Memulai Proses...',
                    text: 'Sedang menghubungi server...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Send POST request
                fetch("{{ route('admin.backup.wipe.run') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error("HTTP error " + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.success && data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        throw new Error(data.message || "Gagal memulai proses pembersihan.");
                    }
                })
                .catch(err => {
                    Swal.fire({
                        title: 'Error!',
                        text: err.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
            }
        });
    });
</script>
@endpush
