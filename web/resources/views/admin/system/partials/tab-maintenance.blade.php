{{-- Tab 3: Pemeliharaan, Scheduler Artisan, Pembersih Ruang Disk & Mode Maintenance --}}

@if(session('artisan_output'))
    <div class="card border-0 rounded-4 shadow-sm mb-4 bg-dark text-light">
        <div class="card-body p-3.5">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-info"><i class="fas fa-terminal me-1.5"></i>{{ __('Output Perintah Artisan Terakhir') }}</span>
            </div>
            <pre class="mb-0 small text-light" style="max-height: 220px; overflow-y: auto; white-space: pre-wrap;">{{ session('artisan_output') }}</pre>
        </div>
    </div>
@endif

{{-- Row 1: 6 Aksi Scheduler & Pemeliharaan Artisan 1-Klik --}}
<div class="card border-0 rounded-4 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h6 class="fw-bold text-body mb-0"><i class="fas fa-wand-magic-sparkles text-primary me-1.5"></i>{{ __('Aksi Scheduler & Pemeliharaan Laravel (1-Klik)') }}</h6>
                <small class="text-muted">{{ __('Eksekusi langsung perintah pemulihan order, pencairan dana, migrasi DB, dan pembersihan cache') }}</small>
            </div>
        </div>

        <div class="row g-3">
            @foreach($artisanActions as $aKey => $aSpec)
                @php
                    $badgeCount = null;
                    if ($aKey === 'release_expired') $badgeCount = $metrics['expired_stuck_orders'];
                    elseif ($aKey === 'release_held') $badgeCount = $metrics['ready_held_funds'];
                    elseif ($aKey === 'deliver_paid') $badgeCount = $metrics['paid_stuck_orders'];
                    elseif ($aKey === 'clean_tokens') $badgeCount = $metrics['expired_tokens'];
                @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="p-3.5 rounded-4 border h-100 d-flex flex-column justify-content-between gap-3" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                        <div>
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                <span class="fw-bold small text-body">{{ __($aSpec['label']) }}</span>
                                @if($badgeCount !== null)
                                    <span class="badge rounded-pill {{ $badgeCount > 0 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success' }}">
                                        {{ $badgeCount }}
                                    </span>
                                @endif
                            </div>
                            <div class="font-monospace text-primary mb-1.5" style="font-size: 0.72rem;">php artisan {{ $aSpec['command'] }}</div>
                            <p class="text-secondary mb-0" style="font-size: 0.76rem; line-height: 1.45;">{{ __($aSpec['description']) }}</p>
                        </div>

                        <form action="{{ route('admin.system.health.artisan') }}" method="POST" class="m-0">
                            @csrf
                            <input type="hidden" name="active_tab" value="pemeliharaan">
                            <input type="hidden" name="action" value="{{ $aKey }}">
                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill w-100 fw-semibold">
                                <i class="fas fa-play me-1"></i>{{ __('Jalankan Sekarang') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Row 2: Pembersih Ruang Disk (Log & Prune Backup VPS) + Sakelar Mode Maintenance --}}
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold text-body mb-0"><i class="fas fa-broom text-danger me-1.5"></i>{{ __('Pembersih Ruang Disk & Prune Backup VPS') }}</h6>
                        <small class="text-muted">{{ __('Setara perintah "jualan prune-backups" & pembersihan log untuk mencegah disk penuh') }}</small>
                    </div>
                    <a href="{{ route('admin.backup.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                        <i class="fas fa-database me-1"></i>{{ __('Menu Backup & Restore') }}
                    </a>
                </div>

                {{-- Aksi 1: Kosongkan Log Laravel --}}
                <div class="p-3 rounded-4 border mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                    <div>
                        <div class="fw-bold small text-body">{{ __('Kosongkan Log Laravel') }}</div>
                        <small class="text-secondary">{{ __('Ukuran saat ini:') }} <strong class="font-monospace text-body">{{ $metrics['log_size_label'] }}</strong> · {{ __('Juga menghapus file log rotasi > 3 hari.') }}</small>
                    </div>
                    <form action="{{ route('admin.system.health.logs.clear') }}" method="POST" class="m-0">
                        @csrf
                        <input type="hidden" name="active_tab" value="pemeliharaan">
                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" onclick="confirmAction(event, '{{ __('Kosongkan file storage/logs/laravel.log sekarang?') }}')">
                            <i class="fas fa-trash-can me-1"></i>{{ __('Kosongkan Log') }}
                        </button>
                    </form>
                </div>

                {{-- Aksi 2: Prune Backup VPS (ops/backups) --}}
                <div class="p-3 rounded-4 border" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2.5">
                        <div>
                            <div class="fw-bold small text-body">{{ __('Prune Arsip Backup VPS (ops/backups)') }}</div>
                            <small class="text-secondary">
                                {{ __(':count file tersimpan · Total :size', ['count' => $opsBackups['count'], 'size' => $opsBackups['total_size_label']]) }}
                            </small>
                        </div>
                        <form action="{{ route('admin.system.health.backups.prune') }}" method="POST" class="d-flex align-items-center gap-1.5 m-0">
                            @csrf
                            <input type="hidden" name="active_tab" value="pemeliharaan">
                            <label class="small text-muted mb-0 text-nowrap">{{ __('Simpan terbaru:') }}</label>
                            <input type="number" name="keep" class="form-control form-control-sm text-center fw-bold" style="width: 68px;" min="1" max="100" value="5" required>
                            <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold" onclick="confirmAction(event, '{{ __('Hapus arsip backup ops yang lebih lama dari jumlah simpan terbaru?') }}')">
                                <i class="fas fa-scissors me-1"></i>{{ __('Prune') }}
                            </button>
                        </form>
                    </div>

                    @if(!empty($opsBackups['files']))
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr class="text-muted" style="font-size: 0.72rem;">
                                        <th>{{ __('Nama Arsip') }}</th>
                                        <th class="text-end">{{ __('Ukuran') }}</th>
                                        <th class="text-end">{{ __('Tanggal') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($opsBackups['files'] as $bf)
                                        <tr style="font-size: 0.76rem;">
                                            <td class="font-monospace text-body">{{ $bf['name'] }}</td>
                                            <td class="text-end font-monospace text-secondary">{{ $bf['size_label'] }}</td>
                                            <td class="text-end text-muted">{{ $bf['modified_at'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="small text-muted">{{ __('Belum ada file snapshot di direktori ops/backups.') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4 d-flex flex-column justify-content-between gap-3">
                <div>
                    <h6 class="fw-bold text-body mb-2"><i class="fas fa-cone-striped text-warning me-1.5"></i>{{ __('Sakelar Mode Maintenance Website') }}</h6>
                    <p class="text-secondary small mb-3">
                        {{ __('Saat aktif, pengunjung dan pelanggan akan melihat halaman pemeliharaan, sementara Admin tetap dapat mengakses panel penuh untuk perbaikan.') }}
                    </p>

                    <div class="p-3.5 rounded-4 border mb-3 text-center" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                        <div class="small text-muted mb-1">{{ __('Status Website Saat Ini') }}</div>
                        @if($metrics['maintenance_active'])
                            <div class="fs-5 fw-bold text-warning mb-1"><i class="fas fa-pause-circle me-1"></i>{{ __('MODE MAINTENANCE AKTIF') }}</div>
                            <small class="text-muted">{{ __('Akses pelanggan sedang ditutup sementara.') }}</small>
                        @else
                            <div class="fs-5 fw-bold text-success mb-1"><i class="fas fa-check-circle me-1"></i>{{ __('WEBSITE ONLINE (NORMAL)') }}</div>
                            <small class="text-muted">{{ __('Seluruh pelanggan dapat berbelanja normal.') }}</small>
                        @endif
                    </div>
                </div>

                <form action="{{ route('admin.system.health.maintenance') }}" method="POST" class="m-0">
                    @csrf
                    <input type="hidden" name="active_tab" value="pemeliharaan">
                    <button type="submit"
                            class="btn {{ $metrics['maintenance_active'] ? 'btn-success' : 'btn-warning' }} w-100 rounded-pill fw-bold"
                            onclick="confirmAction(event, '{{ $metrics['maintenance_active'] ? __('Nonaktifkan Mode Maintenance dan buka kembali akses pelanggan?') : __('Aktifkan Mode Maintenance sekarang? Pelanggan tidak dapat checkout selama aktif.') }}')">
                        <i class="fas {{ $metrics['maintenance_active'] ? 'fa-globe' : 'fa-wrench' }} me-1.5"></i>
                        {{ $metrics['maintenance_active'] ? __('Buka Kembali Website (Matikan Maintenance)') : __('Aktifkan Mode Maintenance') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
