{{-- Tab 1: Ringkasan, Diagnostik Infrastruktur, Disk Breakdown & Top Tabel DB --}}

{{-- Row 1: Checklist Kesehatan & Rekomendasi + Breakdown Kapasitas Disk --}}
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-body mb-0"><i class="fas fa-stethoscope text-primary me-1.5"></i>{{ __('Diagnosis Kesehatan & Rekomendasi') }}</h6>
                        <small class="text-muted">{{ __('Evaluasi otomatis infrastruktur, performa, keamanan konfigurasi, dan antrean') }}</small>
                    </div>
                    <span class="badge bg-{{ $healthBadgeColor }}-subtle text-{{ $healthBadgeColor }} rounded-pill px-3 py-1.5 fw-bold">
                        {{ $healthStatus }} ({{ $healthScore }}/100)
                    </span>
                </div>

                <div class="d-flex flex-column gap-2.5">
                    @foreach($healthChecks as $chk)
                        @php
                            $cColor = $chk['status'] ?? 'primary';
                            $cIcon = $cColor === 'danger' ? 'fa-circle-xmark' : ($cColor === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check');
                        @endphp
                        <div class="d-flex align-items-start gap-3 p-3 rounded-4 border-start border-4 border-{{ $cColor }}" style="background: var(--surface-container-low);">
                            <div class="rounded-3 bg-{{ $cColor }}-subtle text-{{ $cColor }} d-flex align-items-center justify-content-center flex-shrink-0 mt-0.5" style="width: 34px; height: 34px;">
                                <i class="fas {{ $cIcon }}"></i>
                            </div>
                            <div class="overflow-hidden">
                                <div class="fw-bold text-body small">{{ $chk['title'] }}</div>
                                <div class="text-secondary" style="font-size: 0.78rem; line-height: 1.45;">{{ $chk['desc'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4 d-flex flex-column justify-content-between gap-3">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                        <div>
                            <h6 class="fw-bold text-body mb-0"><i class="fas fa-hard-drive text-primary me-1.5"></i>{{ __('Rincian Pemakaian Disk') }}</h6>
                            <small class="text-muted">{{ __(':used terpakai dari :total', ['used' => $metrics['disk_used_label'], 'total' => $metrics['disk_total_label']]) }}</small>
                        </div>
                        <span class="badge rounded-pill {{ $metrics['disk_used_percent'] >= 85 ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }} fw-bold">
                            {{ $metrics['disk_used_percent'] }}%
                        </span>
                    </div>

                    <div class="progress mb-3 rounded-pill" style="height: 10px;">
                        <div class="progress-bar {{ $metrics['disk_used_percent'] >= 90 ? 'bg-danger' : ($metrics['disk_used_percent'] >= 80 ? 'bg-warning' : 'bg-primary') }}"
                             role="progressbar"
                             style="width: {{ min(100, $metrics['disk_used_percent']) }}%"></div>
                    </div>

                    @php
                        $maxFolderBytes = max(1, (int) ($diskBreakdown[0]['bytes'] ?? 1));
                    @endphp
                    <div class="d-flex flex-column gap-2.5">
                        @foreach($diskBreakdown as $dirItem)
                            @php
                                $dirPct = min(100, round(($dirItem['bytes'] / $maxFolderBytes) * 100));
                            @endphp
                            <div class="p-2.5 rounded-3 border" style="border-color: var(--outline-variant) !important; background: var(--surface-container-low);">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                    <div class="overflow-hidden">
                                        <div class="fw-semibold small text-body text-truncate">{{ $dirItem['label'] }}</div>
                                        <div class="font-monospace text-muted" style="font-size: 0.68rem;">{{ $dirItem['path'] }}</div>
                                    </div>
                                    <span class="fw-bold font-monospace small text-primary flex-shrink-0">{{ $dirItem['size_label'] }}</span>
                                </div>
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar bg-primary" style="width: {{ $dirPct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($metrics['disk_used_percent'] >= 80)
                    <div class="p-3 rounded-4 bg-danger-subtle text-danger small d-flex align-items-center justify-content-between gap-2">
                        <span><i class="fas fa-broom me-1.5"></i>{{ __('Ruang disk menipis — bersihkan log & backup lama.') }}</span>
                        <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 flex-shrink-0 fw-semibold" onclick="switchHealthTab('pemeliharaan')">
                            {{ __('Bersihkan') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Row 2: Top 8 Tabel Database + Spesifikasi Server & Runtime PHP --}}
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h6 class="fw-bold text-body mb-0"><i class="fas fa-table text-primary me-1.5"></i>{{ __('Top Tabel Database Terbesar') }}</h6>
                        <small class="text-muted">{{ __(':count tabel total · Ukuran :size', ['count' => $metrics['db_table_count'], 'size' => $metrics['db_size_label']]) }}</small>
                    </div>
                    <span class="badge bg-secondary-subtle text-body font-monospace">{{ strtoupper($metrics['db_driver']) }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th>{{ __('Nama Tabel') }}</th>
                                <th class="text-end">{{ __('Estimasi Baris') }}</th>
                                <th class="text-end">{{ __('Ukuran') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topTables as $tbl)
                                <tr>
                                    <td class="font-monospace small fw-semibold text-body">{{ $tbl['name'] }}</td>
                                    <td class="text-end small text-secondary">{{ number_format($tbl['rows'], 0, ',', '.') }}</td>
                                    <td class="text-end small fw-bold font-monospace text-primary">{{ $tbl['size_label'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted small py-3">{{ __('Tidak ada informasi tabel tersedia.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-server text-primary me-1.5"></i>{{ __('Spesifikasi Server, Runtime & Integrasi') }}</h6>

                <div class="row g-2.5 small mb-3">
                    <div class="col-sm-6">
                        <div class="p-2.5 rounded-3 border h-100" style="border-color: var(--outline-variant) !important;">
                            <div class="text-muted" style="font-size: 0.72rem;">{{ __('Host & OS') }}</div>
                            <div class="fw-semibold text-body text-truncate" title="{{ $serverTelemetry['os'] }}">{{ $serverTelemetry['hostname'] }}</div>
                            <div class="text-secondary text-truncate" style="font-size: 0.72rem;">{{ $serverTelemetry['os'] }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2.5 rounded-3 border h-100" style="border-color: var(--outline-variant) !important;">
                            <div class="text-muted" style="font-size: 0.72rem;">{{ __('RAM & Swap') }}</div>
                            <div class="fw-semibold text-body">{{ $serverTelemetry['ram_used_label'] }} / {{ $serverTelemetry['ram_total_label'] }}</div>
                            <div class="text-secondary" style="font-size: 0.72rem;">Swap: {{ $serverTelemetry['swap_used_label'] }} / {{ $serverTelemetry['swap_total_label'] }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2.5 rounded-3 border h-100" style="border-color: var(--outline-variant) !important;">
                            <div class="text-muted" style="font-size: 0.72rem;">{{ __('PHP & Laravel') }}</div>
                            <div class="fw-semibold text-body">PHP {{ $metrics['php_version'] }} · Laravel {{ $metrics['laravel_version'] }}</div>
                            <div class="text-secondary" style="font-size: 0.72rem;">Memory: {{ $metrics['memory_limit'] }} · Upload: {{ $metrics['upload_max_filesize'] }} · OPcache: {{ $metrics['opcache_enabled'] ? 'ON' : 'OFF' }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-2.5 rounded-3 border h-100" style="border-color: var(--outline-variant) !important;">
                            <div class="text-muted" style="font-size: 0.72rem;">{{ __('Driver & Environment') }}</div>
                            <div class="fw-semibold text-body">{{ strtoupper($metrics['app_env']) }} · Debug {{ $metrics['app_debug'] ? 'ON' : 'OFF' }}</div>
                            <div class="text-secondary" style="font-size: 0.72rem;">{{ $metrics['cache_driver'] }} / {{ $metrics['session_driver'] }} / {{ $metrics['queue_driver'] }}</div>
                        </div>
                    </div>
                </div>

                {{-- Ekstensi PHP & Hak Tulis --}}
                <div class="mb-3">
                    <div class="text-muted fw-bold text-uppercase mb-1.5" style="font-size: 0.68rem; letter-spacing: 0.04em;">{{ __('Ekstensi PHP Wajib') }}</div>
                    <div class="d-flex flex-wrap gap-1.5">
                        @foreach($phpExtensions as $extName => $extLoaded)
                            <span class="badge rounded-pill {{ $extLoaded ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} font-monospace" style="font-size: 0.7rem;">
                                <i class="fas {{ $extLoaded ? 'fa-check' : 'fa-times' }} me-1"></i>{{ $extName }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div>
                    <div class="text-muted fw-bold text-uppercase mb-1.5" style="font-size: 0.68rem; letter-spacing: 0.04em;">{{ __('Izin Tulis Direktori & Environment') }}</div>
                    <div class="d-flex flex-wrap gap-1.5">
                        @foreach($writableChecks as $wLabel => $wOk)
                            <span class="badge rounded-pill {{ $wOk ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning-emphasis' }} font-monospace" style="font-size: 0.7rem;">
                                <i class="fas {{ $wOk ? 'fa-pen' : 'fa-lock' }} me-1"></i>{{ $wLabel }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
