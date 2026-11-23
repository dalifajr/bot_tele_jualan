{{-- Tab 6: Pengawasan Sesi Perangkat Aktif, Postur Keamanan & Log Audit Keamanan --}}
<div class="row g-4">
    {{-- Kolom Kiri: Pengawasan Sesi Perangkat Aktif --}}
    <div class="col-lg-7">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h6 class="fw-bold text-body mb-0"><i class="fas fa-laptop-medical text-primary me-1.5"></i>{{ __('Pengawasan Sesi Perangkat Aktif') }}</h6>
                        <small class="text-muted">{{ __('Cabut sesi perangkat mencurigakan secara real-time.') }}</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5">{{ $activeSessionsCount }} {{ __('aktif 24j') }}</span>
                </div>

                {{-- Desktop Table --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th>{{ __('Pengguna') }}</th>
                                <th>{{ __('Perangkat & Lokasi') }}</th>
                                <th>{{ __('Aktif Terakhir') }}</th>
                                <th class="text-end">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSessions as $sess)
                                <tr>
                                    <td>
                                        <div class="fw-semibold small text-body">{{ $sess->user->username ?? ('User #' . $sess->user_id) }}</div>
                                        <small class="text-muted text-uppercase" style="font-size: 0.68rem;">{{ $sess->user->role ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-body">{{ $sess->device_label ?: '-' }}</div>
                                        <small class="font-monospace text-secondary" style="font-size: 0.72rem;">{{ $sess->ip_address }} · {{ $sess->location_label ?: '-' }}</small>
                                    </td>
                                    <td class="small text-secondary text-nowrap">{{ optional($sess->last_active_at)->diffForHumans() ?: '-' }}</td>
                                    <td class="text-end">
                                        @if(!$sess->isCurrent())
                                            <form action="{{ route('admin.security.firewall.revoke-session', $sess->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <input type="hidden" name="active_tab" value="sesi-audit">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 fw-semibold" style="font-size: 0.72rem;">{{ __('Cabut') }}</button>
                                            </form>
                                        @else
                                            <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size: 0.68rem;">{{ __('Sesi Anda') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4 small">{{ __('Belum ada sesi aktif tercatat.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Collapsible Compact List (< 768px) --}}
                <div class="d-md-none m3-collapsible-list" id="hubSessionsAccordion">
                    @forelse($recentSessions as $sess)
                        <div class="m3-mobile-item-card m3-collapsible-card">
                            <div class="m3-compact-row">
                                <button type="button"
                                        class="m3-compact-trigger"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#hubSessCollapse-{{ $sess->id }}"
                                        aria-expanded="false"
                                        aria-controls="hubSessCollapse-{{ $sess->id }}">
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex align-items-center gap-1.5 min-w-0">
                                            <span class="fw-bold text-body text-truncate" style="font-size: 0.84rem;">{{ $sess->user->username ?? ('User #' . $sess->user_id) }}</span>
                                            <span class="badge bg-secondary-subtle text-body text-uppercase flex-shrink-0" style="font-size: 0.6rem;">{{ $sess->user->role ?? '-' }}</span>
                                        </div>
                                        <div class="text-secondary text-truncate font-monospace" style="font-size: 0.72rem;">
                                            {{ $sess->ip_address }} · {{ optional($sess->last_active_at)->diffForHumans() ?: '-' }}
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        @if($sess->isCurrent())
                                            <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Saat Ini') }}</span>
                                        @endif
                                        <i class="fas fa-chevron-down m3-compact-chevron"></i>
                                    </div>
                                </button>
                            </div>

                            <div class="collapse" id="hubSessCollapse-{{ $sess->id }}" data-bs-parent="#hubSessionsAccordion">
                                <div class="m3-collapsible-body">
                                    <div class="small text-secondary mb-2">
                                        <div class="fw-semibold text-body">{{ $sess->device_label ?: '-' }}</div>
                                        <div><i class="fas fa-map-marker-alt me-1 text-danger"></i>{{ $sess->location_label ?: '-' }}</div>
                                    </div>
                                    @if(!$sess->isCurrent())
                                        <form action="{{ route('admin.security.firewall.revoke-session', $sess->id) }}" method="POST" class="m-0">
                                            @csrf
                                            <input type="hidden" name="active_tab" value="sesi-audit">
                                            <button type="submit" class="btn btn-sm btn-outline-danger m3-compact-action-btn w-100">
                                                <i class="fas fa-right-from-bracket"></i><span>{{ __('Cabut Sesi Perangkat') }}</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1">{{ __('Sesi Perangkat Anda Saat Ini') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">{{ __('Belum ada sesi aktif tercatat.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Kolom Kanan: Postur Keamanan Sistem + Kebijakan Anti-Spam + Audit Feed --}}
    <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-body p-4 d-flex flex-column gap-3">
                <div>
                    <h6 class="fw-bold text-body mb-3"><i class="fas fa-clipboard-check text-primary me-1.5"></i>{{ __('Postur Keamanan Sistem') }}</h6>
                    <div class="d-flex flex-column gap-2 small">
                        <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                            <span class="text-secondary">{{ __('Cloudflare Turnstile') }}</span>
                            <span class="badge rounded-pill {{ $securityPosture['turnstile_active'] ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }}">
                                {{ $securityPosture['turnstile_active'] ? __('Aktif') : __('Nonaktif') }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                            <span class="text-secondary">{{ __('2FA Akun Admin Anda') }}</span>
                            <a href="{{ route('profile') }}#tab-keamanan" class="badge rounded-pill text-decoration-none {{ $securityPosture['admin_2fa_enabled'] ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' }}">
                                {{ $securityPosture['admin_2fa_enabled'] ? __('Terlindungi') : __('Belum Aktif') }}
                            </a>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                            <span class="text-secondary">{{ __('Adopsi 2FA Pengguna') }}</span>
                            <span class="badge rounded-pill bg-info-subtle text-info">
                                {{ $twoFactorAdoptionRate }}% ({{ $twoFactorUsersCount }} {{ __('akun') }})
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                            <span class="text-secondary">{{ __('Production Debug Mode') }}</span>
                            <span class="badge rounded-pill {{ $securityPosture['app_debug_off'] ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger' }}">
                                {{ $securityPosture['app_debug_off'] ? __('Aman (OFF)') : __('Bahaya (ON)') }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1.5">
                            <span class="text-secondary">{{ __('Mode Maintenance') }}</span>
                            <span class="badge rounded-pill {{ $securityPosture['maintenance_active'] ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary' }}">
                                {{ $securityPosture['maintenance_active'] ? __('Aktif') : __('Normal') }}
                            </span>
                        </div>
                    </div>
                </div>

                <form action="{{ route('admin.security.firewall.policy') }}" method="POST" class="p-3 rounded-4 border" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                    @csrf
                    <input type="hidden" name="active_tab" value="sesi-audit">
                    <label class="form-label small fw-bold text-body mb-1">{{ __('Batas Spam Registrasi / IP (24 Jam)') }}</label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="spam_registration_threshold" class="form-control fw-bold" min="2" max="50" value="{{ $securityPosture['spam_threshold'] }}" required>
                        <button type="submit" class="btn btn-primary fw-semibold">{{ __('Simpan') }}</button>
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">{{ __('Akun otomatis ditangguhkan bila 1 IP melebihi batas ini.') }}</small>
                </form>
            </div>
        </div>

        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                    <h6 class="fw-bold text-body mb-0"><i class="fas fa-clock-rotate-left text-primary me-1.5"></i>{{ __('Log Peristiwa Keamanan Terakhir') }}</h6>
                    <a href="{{ route('admin.audit-logs.index') }}" class="small text-primary text-decoration-none fw-semibold">{{ __('Semua Audit') }} <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                @forelse($recentSecurityEvents as $ev)
                    <div class="py-1.5 border-bottom small" style="border-color: var(--outline-variant) !important;">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold text-body font-monospace" style="font-size: 0.74rem;">{{ $ev->action }}</span>
                            <span class="text-muted" style="font-size: 0.7rem;">{{ \Carbon\Carbon::parse($ev->created_at)->diffForHumans() }}</span>
                        </div>
                        <div class="text-secondary text-truncate" style="font-size: 0.74rem;">{{ $ev->detail }}</div>
                    </div>
                @empty
                    <div class="text-muted small">{{ __('Belum ada catatan peristiwa keamanan.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
