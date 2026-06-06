@extends('layouts.app')

@section('title', 'Backup & Restore')
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

{{-- Database Statistics --}}
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 bg-primary-subtle text-primary">
                    <i class="fas fa-hdd fs-3"></i>
                </div>
                <div>
                    <small class="text-muted fw-bold d-block text-uppercase">Ukuran Database</small>
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
                    <small class="text-muted fw-bold d-block text-uppercase">Total Record Data</small>
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
                    <small class="text-muted fw-bold d-block text-uppercase">Auto-Backup</small>
                    <h5 class="fw-bold m-0">
                        @if($autoBackupEnabled === '1')
                        <span class="badge bg-success-subtle text-success rounded-pill px-3">Aktif ({{ ucfirst($autoBackupSchedule) }})</span>
                        @else
                        <span class="badge bg-danger-subtle text-danger rounded-pill px-3">Non-Aktif</span>
                        @endif
                    </h5>
                    <small class="text-muted">Last run: {{ $autoBackupLastRun }}</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- Download & Action Card --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="fas fa-download text-primary me-2"></i>Pencadangan Manual (Export)</h5>
                <p class="text-muted small mb-4">Unduh salinan data sistem Anda saat ini. Kami menyarankan untuk mengunduh Snapshot secara berkala sebelum melakukan perubahan besar.</p>
                
                <div class="d-grid gap-3">
                    <a href="{{ route('admin.backup.download', 'snapshot') }}" class="btn btn-primary rounded-pill py-2 fw-bold text-start px-4 d-flex align-items-center justify-content-between">
                        <div>
                            <i class="fas fa-file-archive me-2"></i> Download Snapshot
                            <small class="d-block fw-light text-white-50 mt-1" style="font-size: 0.75rem;">Mencakup database SQLite dan seluruh file QRIS/media lokal</small>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </a>

                    <a href="{{ route('admin.backup.download', 'json') }}" class="btn btn-outline-primary rounded-pill py-2 fw-bold text-start px-4 d-flex align-items-center justify-content-between">
                        <div>
                            <i class="fas fa-file-code me-2"></i> Download JSON (Bot Compatible)
                            <small class="d-block fw-light text-muted mt-1" style="font-size: 0.75rem;">Database tables dalam JSON ZIP. Kompatibel dengan fitur restore Bot Telegram</small>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Restore Card --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="fas fa-upload text-danger me-2"></i>Pemulihan Data (Restore)</h5>
                <p class="text-muted small mb-3">Pulihkan database dari file ZIP cadangan yang telah diunduh sebelumnya.</p>
                
                <form action="{{ route('admin.backup.restore') }}" method="POST" enctype="multipart/form-data" id="formRestore">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">FILE CADANGAN (ZIP)</label>
                        <input type="file" name="backup_file" class="form-control" accept=".zip" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted d-block">MODE PEMULIHAN</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="mode" id="modeOverwrite" value="overwrite" checked>
                            <label class="form-check-input-label small fw-bold" for="modeOverwrite">Full Overwrite (Hapus & Timpa)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="mode" id="modeMerge" value="merge">
                            <label class="form-check-input-label small fw-bold text-muted" for="modeMerge">Smart Merge (Skip Duplikat)</label>
                        </div>
                    </div>

                    <div class="alert alert-warning border-0 p-3 mb-3 d-flex align-items-start gap-2" style="border-radius: 10px; font-size: 0.8rem;">
                        <i class="fas fa-exclamation-triangle text-warning mt-1"></i>
                        <div>
                            <strong>Peringatan Concurrency:</strong> Proses restore akan me-lock database untuk penulisan. Bot Telegram akan mengalami delay selama proses berlangsung.
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger rounded-pill py-2 fw-bold">
                            <i class="fas fa-undo me-2"></i>Jalankan Pemulihan Database
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- Auto Backup Configuration --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="fas fa-cog text-warning me-2"></i>Pengaturan Auto-Backup Terjadwal</h5>
                <p class="text-muted small mb-4">Buat jadwal pencadangan otomatis. Sistem akan mem-backup database ke folder lokal dan mengirimkannya ke Bot Telegram Admin.</p>

                <form action="{{ route('admin.backup.settings.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
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

                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning rounded-pill py-2 fw-bold">
                            <i class="fas fa-save me-2"></i>Simpan Konfigurasi Auto-Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Database Record Details --}}
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-chart-bar text-info me-2"></i>Informasi Record Tabel Database</h5>
                <div class="table-responsive">
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

    {{-- Backup History --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2"><i class="fas fa-history text-success me-2"></i>Riwayat File Backup Lokal</h5>
                <p class="text-muted small mb-3">Kumpulan file backup yang tersimpan di server local. Sistem membatasi riwayat maksimal 10 file backup terbaru.</p>

                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead>
                            <tr class="table-light">
                                <th>Nama File / Tanggal</th>
                                <th>Ukuran / Tipe</th>
                                <th class="text-end">Aksi</th>
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
                                        <a href="{{ route('admin.backup.download', $item['filename']) }}" class="btn btn-sm btn-outline-primary border-0 p-1" title="Unduh File">
                                            <i class="fas fa-download fs-5"></i>
                                        </a>
                                        <form action="{{ route('admin.backup.destroy', $item['filename']) }}" method="POST" class="d-inline form-delete-backup">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Hapus File">
                                                <i class="fas fa-trash fs-5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">
                                    <i class="fas fa-folder-open fs-1 mb-2"></i><br>
                                    Belum ada file backup lokal yang tersimpan.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
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
                        <th>User Admin</th>
                        <th>Aksi Aktivitas</th>
                        <th>Detail Log</th>
                        <th>IP Address</th>
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
                            <span class="text-success"><i class="fas fa-robot me-1"></i>System Cron</span>
                            @endif
                        </td>
                        <td>
                            @if($log->action === 'backup_restore')
                            <span class="badge bg-danger text-uppercase px-2">Restore</span>
                            @elseif($log->action === 'backup_create')
                            <span class="badge bg-primary text-uppercase px-2">Export</span>
                            @elseif($log->action === 'system_auto_backup')
                            <span class="badge bg-warning text-uppercase px-2 text-dark">Auto Backup</span>
                            @else
                            <span class="badge bg-secondary text-uppercase px-2">Delete</span>
                            @endif
                        </td>
                        <td class="text-muted text-wrap" style="max-width: 320px;">{{ $log->detail }}</td>
                        <td><code>{{ $log->ip_address }}</code></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">Belum ada catatan aktivitas log audit backup.</td>
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
