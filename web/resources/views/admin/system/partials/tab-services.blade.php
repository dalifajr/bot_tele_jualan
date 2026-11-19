{{-- Tab 2: Layanan VPS Systemd, Mode Migrasi Bot <-> Web & Repair Otomatis (Impor Panel Jualan) --}}

{{-- Status Banner VPS Ops Bridge --}}
@if(!$bridgeStatus['available'])
    <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: var(--surface-container-low); border: 1px solid var(--outline-variant) !important;">
        <div class="card-body p-3.5 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-3 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                    <i class="fas fa-terminal"></i>
                </div>
                <div>
                    <div class="fw-bold text-body small">
                        {{ $bridgeStatus['is_linux'] ? __('VPS Ops Bridge Belum Diaktifkan di Server') : __('Mode Pengembangan Lokal (Non-Linux)') }}
                    </div>
                    <div class="text-secondary" style="font-size: 0.78rem;">
                        {{ __('Jalankan perintah sekali pakai di terminal VPS sebagai root agar tombol Start/Stop/Restart systemd, Update GitHub, dan Journalctl aktif 1-klik dari web secara aman (least-privilege sudoers):') }}
                    </div>
                    <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                        <code class="px-2.5 py-1.5 rounded-3 bg-dark text-light user-select-all" style="font-size: 0.76rem;" id="bridgeInstallCmd">{{ $bridgeStatus['install_command'] }}</code>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold" onclick="copyTextValue('bridgeInstallCmd', this)">
                            <i class="fas fa-copy me-1"></i>{{ __('Salin') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-success border-0 rounded-4 d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 small fw-semibold">
        <div>
            <i class="fas fa-check-circle me-1.5"></i>{{ __('VPS Ops Bridge Aktif — Kontrol systemd, journalctl, update GitHub, dan repair dapat dieksekusi langsung dari panel ini.') }}
        </div>
        <form action="{{ route('admin.system.health.website-restart') }}" method="POST" class="m-0">
            @csrf
            <input type="hidden" name="active_tab" value="layanan">
            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold" onclick="confirmAction(event, '{{ __('Restart PHP-FPM dan reload Nginx sekarang?') }}')">
                <i class="fas fa-rotate me-1"></i>{{ __('Restart Website (PHP-FPM + Nginx)') }}
            </button>
        </form>
    </div>
@endif

{{-- Row 1: Daftar Layanan Systemd VPS --}}
<div class="card border-0 rounded-4 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h6 class="fw-bold text-body mb-0"><i class="fas fa-gears text-primary me-1.5"></i>{{ __('Kontrol Layanan VPS (Panel Jualan)') }}</h6>
                <small class="text-muted">{{ __('Pantau dan kendalikan service bot Telegram, API, web server, database, dan backup timer') }}</small>
            </div>
            <div class="d-flex gap-2">
                <form action="{{ route('admin.system.health.repair') }}" method="POST" class="m-0">
                    @csrf
                    <input type="hidden" name="active_tab" value="layanan">
                    <button type="submit" class="btn btn-sm btn-warning rounded-pill px-3 fw-semibold" onclick="confirmAction(event, '{{ __('Jalankan Diagnosa & Perbaikan Otomatis (repair_system_and_bot)?') }}')">
                        <i class="fas fa-screwdriver-wrench me-1"></i>{{ __('Diagnosa & Repair Otomatis') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th>{{ __('Layanan') }}</th>
                        <th>{{ __('Unit Systemd') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Boot') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($services as $svc)
                        @php
                            $st = $svc['state'] ?? 'unknown';
                            $badgeCls = $st === 'active'
                                ? 'bg-success-subtle text-success'
                                : ($st === 'failed' ? 'bg-danger-subtle text-danger' : ($st === 'local' ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary'));
                            $stLabel = $st === 'active'
                                ? __('Aktif')
                                : ($st === 'inactive' ? __('Berhenti') : ($st === 'failed' ? __('Gagal') : ($st === 'local' ? __('Lokal') : ucfirst($st))));
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 34px; height: 34px;">
                                        <i class="{{ $svc['icon'] }}"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small text-body">{{ $svc['label'] }}</div>
                                        <div class="text-muted" style="font-size: 0.72rem;">{{ __($svc['description']) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="font-monospace small text-secondary">{{ $svc['unit'] }}</td>
                            <td>
                                <span class="badge rounded-pill {{ $badgeCls }} px-2.5 py-1 fw-semibold">
                                    {{ $stLabel }}
                                </span>
                                @if(!empty($svc['substate']))
                                    <small class="text-muted d-block" style="font-size: 0.68rem;">{{ $svc['substate'] }}</small>
                                @endif
                            </td>
                            <td class="small font-monospace text-secondary">{{ $svc['enabled'] ?: '-' }}</td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <form action="{{ route('admin.system.health.service-action') }}" method="POST" class="m-0">
                                        @csrf
                                        <input type="hidden" name="active_tab" value="layanan">
                                        <input type="hidden" name="unit" value="{{ $svc['key'] }}">
                                        <input type="hidden" name="action" value="start">
                                        <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-2.5" {{ !$bridgeStatus['available'] ? 'disabled' : '' }} title="{{ __('Mulai Layanan') }}">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.system.health.service-action') }}" method="POST" class="m-0">
                                        @csrf
                                        <input type="hidden" name="active_tab" value="layanan">
                                        <input type="hidden" name="unit" value="{{ $svc['key'] }}">
                                        <input type="hidden" name="action" value="restart">
                                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-2.5" {{ !$bridgeStatus['available'] ? 'disabled' : '' }} title="{{ __('Restart Layanan') }}">
                                            <i class="fas fa-rotate"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.system.health.service-action') }}" method="POST" class="m-0">
                                        @csrf
                                        <input type="hidden" name="active_tab" value="layanan">
                                        <input type="hidden" name="unit" value="{{ $svc['key'] }}">
                                        <input type="hidden" name="action" value="stop">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" {{ !$bridgeStatus['available'] ? 'disabled' : '' }} title="{{ __('Hentikan Layanan') }}" onclick="confirmAction(event, '{{ __('Yakin menghentikan layanan ini?') }}')">
                                            <i class="fas fa-stop"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Row 2: Mode Migrasi Command Bot <-> Website + Referensi CLI VPS --}}
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold text-body mb-0"><i class="fab fa-telegram text-primary me-1.5"></i>{{ __('Mode Migrasi Bot Telegram ↔ Website') }}</h6>
                        <small class="text-muted">{{ __('Setara perintah "jualan toggle-bot-commands" & "jualan bot-commands-status"') }}</small>
                    </div>
                    <span class="badge rounded-pill px-3 py-1.5 fw-bold {{ $botMigration['disabled'] ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success' }}">
                        {{ $botMigration['disabled'] ? __('Migrasi Web Aktif (Command Bot Nonaktif)') : __('Command Bot Aktif (Normal)') }}
                    </span>
                </div>

                <div class="d-flex flex-column gap-2 mb-3">
                    @foreach($botMigration['checks'] as $chk)
                        <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom small" style="border-color: var(--outline-variant) !important;">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas {{ $chk['ok'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' }}"></i>
                                <span class="fw-semibold text-body">{{ $chk['label'] }}</span>
                            </div>
                            <span class="font-monospace text-secondary" style="font-size: 0.74rem;">{{ $chk['detail'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <small class="text-muted">
                        {{ $botMigration['disabled']
                            ? __('Pelanggan yang mengirim perintah bot akan diarahkan otomatis ke Mini WebApp.')
                            : __('Pelanggan dapat berbelanja langsung melalui bot Telegram maupun website.') }}
                    </small>

                    <form action="{{ route('admin.system.health.bot-commands') }}" method="POST" class="m-0">
                        @csrf
                        <input type="hidden" name="active_tab" value="layanan">
                        <input type="hidden" name="mode" value="{{ $botMigration['disabled'] ? 'enable' : 'disable' }}">
                        <button type="submit"
                                class="btn btn-sm {{ $botMigration['disabled'] ? 'btn-success' : 'btn-warning' }} rounded-pill px-3 fw-semibold"
                                onclick="confirmAction(event, '{{ $botMigration['disabled'] ? __('Aktifkan kembali perintah pemesanan di bot Telegram?') : __('Nonaktifkan perintah bot dan alihkan seluruh pelanggan ke website?') }}')">
                            <i class="fas {{ $botMigration['disabled'] ? 'fa-toggle-on' : 'fa-toggle-off' }} me-1"></i>
                            {{ $botMigration['disabled'] ? __('Aktifkan Command Bot') : __('Aktifkan Mode Migrasi Web') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-2"><i class="fas fa-book-open text-secondary me-1.5"></i>{{ __('Referensi Perintah CLI VPS Khusus Root') }}</h6>
                <p class="text-muted small mb-3">
                    {{ __('Perintah infrastruktur destruktif/bootstrap (ubah domain SSL Nginx atau uninstall) sengaja hanya dijalankan via SSH VPS demi mencegah panel web terputus.') }}
                </p>

                <div class="d-flex flex-column gap-2 small">
                    <div class="p-2.5 rounded-3 border" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                        <div class="fw-semibold text-body mb-1">{{ __('Ubah Domain & SSL Certbot') }}</div>
                        <code class="text-primary user-select-all">sudo jualan website-domain domainbaru.com</code>
                    </div>
                    <div class="p-2.5 rounded-3 border" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                        <div class="fw-semibold text-body mb-1">{{ __('Buka Menu Interaktif Panel Jualan') }}</div>
                        <code class="text-primary user-select-all">sudo jualan</code>
                    </div>
                    <div class="p-2.5 rounded-3 border" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                        <div class="fw-semibold text-body mb-1">{{ __('Pasang / Perbarui Izin VPS Ops Bridge') }}</div>
                        <code class="text-primary user-select-all">{{ $bridgeStatus['install_command'] }}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
