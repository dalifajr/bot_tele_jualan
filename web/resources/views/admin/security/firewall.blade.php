@extends('layouts.app')

@section('title', __('Firewall & Anti-Fraud Center'))
@section('page_subtitle', __('Pusat komando keamanan, deteksi anomali, blokir IP/CIDR & pengawasan sesi aktif'))

@section('content')
@php
    $threatBadgeClass = $threatLevel === 'high'
        ? 'bg-danger-subtle text-danger'
        : ($threatLevel === 'medium' ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary');
    $threatLabel = $threatLevel === 'high'
        ? __('Ancaman Tinggi')
        : ($threatLevel === 'medium' ? __('Waspada') : __('Status Aman'));
@endphp

{{-- Header Action Bar --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h4 class="fw-bold mb-0 text-body">
                <i class="fas fa-shield-virus text-danger me-1.5"></i>{{ __('Pusat Keamanan & Anti-Fraud') }}
            </h4>
            <span class="badge {{ $threatBadgeClass }} rounded-pill px-3 py-1.5 fw-bold">
                <i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>{{ $threatLabel }}
            </span>
        </div>
        <small class="text-secondary">{{ __('Pantau serangan login, blokir IP/subnet CIDR, deteksi spam multi-akun, dan cabut sesi aktif secara real-time.') }}</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.logins.index') }}" class="btn btn-sm btn-tonal rounded-pill px-3 fw-semibold">
            <i class="fas fa-map-location-dot me-1"></i>{{ __('Peta Login') }}
        </a>
        <form action="{{ route('admin.security.firewall.auto-block') }}" method="POST" class="m-0">
            @csrf
            <input type="hidden" name="threshold" value="5">
            <input type="hidden" name="duration_days" value="7">
            <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold">
                <i class="fas fa-bolt me-1"></i>{{ __('Auto-Blokir IP Agresif (≥5 Gagal)') }}
            </button>
        </form>
    </div>
</div>

{{-- Row 1: 6 Core Security KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3">
                <small class="text-secondary fw-semibold d-block">{{ __('Aturan Aktif') }}</small>
                <div class="fs-3 fw-bold text-primary mt-1">{{ $activeRulesCount }}</div>
                <small class="text-muted">{{ __(':hits blokir tertahan', ['hits' => number_format($totalBlockedHits)]) }}</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3">
                <small class="text-secondary fw-semibold d-block">{{ __('Login Gagal (24j)') }}</small>
                <div class="fs-3 fw-bold text-danger mt-1">{{ $failedLogins24h }}</div>
                <small class="text-muted">{{ __('Rasio gagal :rate%', ['rate' => $failRate24h]) }}</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3">
                <small class="text-secondary fw-semibold d-block">{{ __('Login Sukses (24j)') }}</small>
                <div class="fs-3 fw-bold text-success mt-1">{{ $successLogins24h }}</div>
                <small class="text-muted">{{ __('Akses terverifikasi') }}</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3">
                <small class="text-secondary fw-semibold d-block">{{ __('Sesi Aktif (24j)') }}</small>
                <div class="fs-3 fw-bold text-body mt-1">{{ $activeSessionsCount }}</div>
                <small class="text-muted">{{ __('Perangkat online') }}</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3">
                <small class="text-secondary fw-semibold d-block">{{ __('Adopsi 2FA') }}</small>
                <div class="fs-3 fw-bold text-primary mt-1">{{ $twoFactorAdoptionRate }}%</div>
                <small class="text-muted">{{ __(':count akun terlindungi', ['count' => $twoFactorUsersCount]) }}</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3">
                <small class="text-secondary fw-semibold d-block">{{ __('Akun Suspended') }}</small>
                <div class="fs-3 fw-bold text-warning mt-1">{{ $suspendedUsersCount }}</div>
                <small class="text-muted">{{ __(':count IP multi-akun', ['count' => $multiAccountIps->count()]) }}</small>
            </div>
        </div>
    </div>
</div>

{{-- Row 2: 7-Day Attack Trend Chart & Security Posture Checklist --}}
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold text-body mb-0">{{ __('Tren Percobaan Login & Serangan (7 Hari Terakhir)') }}</h6>
                        <small class="text-secondary">{{ __('Perbandingan upaya login gagal (indikasi brute-force) vs login berhasil.') }}</small>
                    </div>
                </div>
                <div style="height: 240px;"><canvas id="firewallAttackChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4 d-flex flex-column justify-content-between gap-3">
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
                    <label class="form-label small fw-bold text-body mb-1">{{ __('Batas Spam Registrasi / IP (24 Jam)') }}</label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="spam_registration_threshold" class="form-control fw-bold" min="2" max="50" value="{{ $securityPosture['spam_threshold'] }}" required>
                        <button type="submit" class="btn btn-primary fw-semibold">{{ __('Simpan') }}</button>
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">{{ __('Akun otomatis ditangguhkan bila 1 IP melebihi batas ini.') }}</small>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Row 3: Add Firewall Rule + Filterable Rules Table --}}
<div class="row g-4 mb-4">
    {{-- Desktop Inline Add Form --}}
    <div class="col-lg-4 d-none d-lg-block">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-ban text-danger me-1.5"></i>{{ __('Tambah Aturan Blokir Baru') }}</h6>
                @include('admin.security.partials.firewall_rule_form', ['idPrefix' => 'desktop'])
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex align-items-center justify-content-between flex-grow-1 flex-lg-grow-0 gap-2">
                        <h6 class="fw-bold text-body mb-0"><i class="fas fa-list-check text-primary me-1.5"></i>{{ __('Daftar Aturan Firewall') }}</h6>
                        <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold d-lg-none d-inline-flex align-items-center gap-1" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#addFirewallRuleModal">
                            <i class="fas fa-plus"></i><span>{{ __('Blokir Baru') }}</span>
                        </button>
                    </div>
                    <form action="{{ route('admin.security.firewall') }}" method="GET" class="d-flex flex-wrap gap-1.5 align-items-center w-100 w-lg-auto">
                        <select name="type" class="form-select form-select-sm flex-grow-1 flex-sm-grow-0" style="width: auto;" onchange="this.form.submit()">
                            <option value="all" {{ $typeFilter === 'all' ? 'selected' : '' }}>{{ __('Semua Tipe') }}</option>
                            <option value="ip" {{ $typeFilter === 'ip' ? 'selected' : '' }}>IP</option>
                            <option value="subnet" {{ $typeFilter === 'subnet' ? 'selected' : '' }}>Subnet CIDR</option>
                            <option value="telegram_id" {{ $typeFilter === 'telegram_id' ? 'selected' : '' }}>Telegram ID</option>
                        </select>
                        <input type="text" name="search" class="form-control form-control-sm flex-grow-1" style="min-width: 140px; max-width: 220px;" placeholder="{{ __('Cari target/alasan...') }}" value="{{ $search }}">
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">{{ __('Filter') }}</button>
                    </form>
                </div>

                {{-- Desktop Table --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th>{{ __('Target') }}</th>
                                <th>{{ __('Alasan') }}</th>
                                <th>{{ __('Status & Berlaku') }}</th>
                                <th class="text-end">{{ __('Hit') }}</th>
                                <th class="text-end">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rules as $rule)
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-body text-uppercase me-1" style="font-size: 0.65rem;">{{ $rule->type }}</span>
                                        <span class="font-monospace fw-semibold small text-body">{{ $rule->value }}</span>
                                    </td>
                                    <td class="small text-secondary">{{ $rule->reason ?: '-' }}</td>
                                    <td class="small text-nowrap">
                                        @if(!$rule->is_active)
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill">{{ __('Dijeda') }}</span>
                                        @elseif($rule->expires_at && $rule->expires_at->isPast())
                                            <span class="badge bg-warning-subtle text-warning rounded-pill">{{ __('Kedaluwarsa') }}</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger rounded-pill">{{ __('Aktif') }}</span>
                                        @endif
                                        <div class="text-muted mt-0.5" style="font-size: 0.72rem;">
                                            {{ $rule->expires_at ? $rule->expires_at->format('d M Y H:i') : __('Permanen') }}
                                        </div>
                                    </td>
                                    <td class="text-end small fw-bold m3-tabular-nums">{{ number_format($rule->hit_count) }}</td>
                                    <td class="text-end text-nowrap">
                                        <div class="d-inline-flex gap-1">
                                            <form action="{{ route('admin.security.firewall.toggle', $rule->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $rule->is_active ? 'btn-outline-secondary' : 'btn-outline-primary' }} rounded-pill px-2.5" title="{{ $rule->is_active ? __('Jeda Aturan') : __('Aktifkan Aturan') }}">
                                                    <i class="fas {{ $rule->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.security.firewall.destroy', $rule->id) }}" method="POST" class="m-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="{{ __('Hapus Aturan') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4 small">{{ __('Belum ada aturan firewall tersimpan.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Stacked Cards (< 768px) --}}
                <div class="d-md-none d-flex flex-column gap-2.5">
                    @forelse($rules as $rule)
                        <div class="m3-mobile-item-card p-3">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1.5">
                                <div class="min-w-0">
                                    <span class="badge bg-secondary-subtle text-body text-uppercase me-1" style="font-size: 0.65rem;">{{ $rule->type }}</span>
                                    <span class="font-monospace fw-bold small text-body text-break">{{ $rule->value }}</span>
                                </div>
                                @if(!$rule->is_active)
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill flex-shrink-0">{{ __('Dijeda') }}</span>
                                @elseif($rule->expires_at && $rule->expires_at->isPast())
                                    <span class="badge bg-warning-subtle text-warning rounded-pill flex-shrink-0">{{ __('Kedaluwarsa') }}</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger rounded-pill flex-shrink-0">{{ __('Aktif') }}</span>
                                @endif
                            </div>
                            <div class="small text-secondary mb-2">{{ $rule->reason ?: __('Tanpa catatan alasan') }}</div>
                            <div class="d-flex justify-content-between align-items-center small text-muted mb-2 m3-tabular-nums">
                                <span><i class="far fa-clock me-1"></i>{{ $rule->expires_at ? $rule->expires_at->format('d M Y H:i') : __('Permanen') }}</span>
                                <span class="fw-bold text-body">{{ number_format($rule->hit_count) }} hit</span>
                            </div>
                            <div class="d-flex gap-2 pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                                <form action="{{ route('admin.security.firewall.toggle', $rule->id) }}" method="POST" class="flex-grow-1 m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $rule->is_active ? 'btn-outline-secondary' : 'btn-outline-primary' }} rounded-pill w-100 fw-semibold" style="min-height: 40px;">
                                        <i class="fas {{ $rule->is_active ? 'fa-pause' : 'fa-play' }} me-1"></i>{{ $rule->is_active ? __('Jeda') : __('Aktifkan') }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.security.firewall.destroy', $rule->id) }}" method="POST" class="m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" style="min-height: 40px;" title="{{ __('Hapus Aturan') }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">{{ __('Belum ada aturan firewall tersimpan.') }}</div>
                    @endforelse
                </div>

                @if($rules->hasPages())
                    <div class="mt-3">{{ $rules->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Row 4: Threat Intelligence (Aggressive IPs, Targeted Accounts, Multi-Account Spam IPs) --}}
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-radar text-danger me-1.5"></i>{{ __('IP Agresif & Brute-Force (7 Hari)') }}</h6>
                @forelse($topSuspiciousIps as $row)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <div>
                            <div class="font-monospace fw-bold small text-body d-flex align-items-center gap-1.5">
                                <span>{{ $row->ip_address }}</span>
                                @if($row->is_blocked)
                                    <span class="badge bg-danger-subtle text-danger rounded-pill" style="font-size: 0.64rem;">{{ __('Diblokir') }}</span>
                                @endif
                            </div>
                            <small class="text-secondary d-block" style="font-size: 0.74rem;">
                                <span class="text-danger fw-semibold">{{ $row->fail_count }}x {{ __('gagal') }}</span>
                                · {{ __(':n akun target', ['n' => $row->unique_targets]) }}
                                @if($row->last_location || $row->last_country)
                                    · {{ $row->last_location ?: $row->last_country }}
                                @endif
                            </small>
                        </div>
                        @if(!$row->is_blocked)
                            <div class="d-flex gap-1">
                                <form action="{{ route('admin.security.firewall.store') }}" method="POST" class="m-0">
                                    @csrf
                                    <input type="hidden" name="type" value="ip">
                                    <input type="hidden" name="value" value="{{ $row->ip_address }}">
                                    <input type="hidden" name="duration_days" value="7">
                                    <input type="hidden" name="reason" value="{{ __('Anomali gagal login berulang') }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 fw-semibold" style="font-size: 0.72rem;">{{ __('Blokir IP') }}</button>
                                </form>
                                @if($row->subnet_24)
                                    <form action="{{ route('admin.security.firewall.store') }}" method="POST" class="m-0">
                                        @csrf
                                        <input type="hidden" name="type" value="subnet">
                                        <input type="hidden" name="value" value="{{ $row->subnet_24 }}">
                                        <input type="hidden" name="duration_days" value="7">
                                        <input type="hidden" name="reason" value="{{ __('Blokir subnet brute-force') }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-2 fw-semibold" style="font-size: 0.72rem;" title="{{ $row->subnet_24 }}">/24</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-muted small py-3 text-center">{{ __('Tidak ada anomali IP dalam 7 hari terakhir.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-crosshairs text-warning me-1.5"></i>{{ __('Target Akun Diserang') }}</h6>
                @forelse($targetedAccounts as $acc)
                    <div class="py-2 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <div class="fw-semibold small text-body text-truncate">{{ $acc->username_or_email }}</div>
                        <small class="text-secondary" style="font-size: 0.74rem;">
                            <span class="text-danger fw-bold">{{ $acc->fail_count }}x</span> {{ __('gagal') }} · {{ __(':ips IP berbeda', ['ips' => $acc->unique_ips]) }}
                        </small>
                    </div>
                @empty
                    <div class="text-muted small py-3 text-center">{{ __('Belum ada target akun diserang.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-users-slash text-primary me-1.5"></i>{{ __('Deteksi IP Multi-Akun (Anti-Spam)') }}</h6>
                @forelse($multiAccountIps as $mip)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <div>
                            <div class="font-monospace fw-semibold small text-body">{{ $mip->registration_ip }}</div>
                            <small class="text-secondary" style="font-size: 0.74rem;">
                                <strong>{{ $mip->account_count }}</strong> {{ __('akun terdaftar') }}
                                @if($mip->suspended_count > 0)
                                    · <span class="text-danger">{{ $mip->suspended_count }} {{ __('suspended') }}</span>
                                @endif
                            </small>
                        </div>
                        @if(!$mip->is_blocked)
                            <form action="{{ route('admin.security.firewall.store') }}" method="POST" class="m-0">
                                @csrf
                                <input type="hidden" name="type" value="ip">
                                <input type="hidden" name="value" value="{{ $mip->registration_ip }}">
                                <input type="hidden" name="duration_days" value="30">
                                <input type="hidden" name="reason" value="{{ __('Indikasi spam multi-akun registrasi') }}">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 fw-semibold" style="font-size: 0.72rem;">{{ __('Blokir') }}</button>
                            </form>
                        @else
                            <span class="badge bg-danger-subtle text-danger rounded-pill" style="font-size: 0.68rem;">{{ __('Diblokir') }}</span>
                        @endif
                    </div>
                @empty
                    <div class="text-muted small py-3 text-center">{{ __('Tidak ditemukan IP dengan pendaftaran multi-akun.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Row 5: Live Active Sessions Supervision + Suspended Accounts & Security Audit Feed --}}
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-laptop-medical text-primary me-1.5"></i>{{ __('Pengawasan Sesi Perangkat Aktif') }}</h6>
                {{-- Desktop Table --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table align-middle mb-0">
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

                {{-- Mobile Stacked Cards (< 768px) --}}
                <div class="d-md-none d-flex flex-column gap-2.5">
                    @forelse($recentSessions as $sess)
                        <div class="m3-mobile-item-card p-3">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                <div>
                                    <div class="fw-bold small text-body">{{ $sess->user->username ?? ('User #' . $sess->user_id) }}</div>
                                    <span class="badge bg-secondary-subtle text-body text-uppercase" style="font-size: 0.65rem;">{{ $sess->user->role ?? '-' }}</span>
                                </div>
                                <small class="text-secondary">{{ optional($sess->last_active_at)->diffForHumans() ?: '-' }}</small>
                            </div>
                            <div class="small fw-semibold text-body mt-1">{{ $sess->device_label ?: '-' }}</div>
                            <div class="small font-monospace text-secondary mb-2">{{ $sess->ip_address }} · {{ $sess->location_label ?: '-' }}</div>
                            <div class="pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                                @if(!$sess->isCurrent())
                                    <form action="{{ route('admin.security.firewall.revoke-session', $sess->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill w-100 fw-semibold" style="min-height: 40px;">
                                            <i class="fas fa-right-from-bracket me-1"></i>{{ __('Cabut Sesi Perangkat') }}
                                        </button>
                                    </form>
                                @else
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5">{{ __('Sesi Perangkat Anda Saat Ini') }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">{{ __('Belum ada sesi aktif tercatat.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-user-lock text-warning me-1.5"></i>{{ __('Akun Ditangguhkan Terbaru') }}</h6>
                @forelse($suspendedUsers as $su)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <div class="overflow-hidden">
                            <div class="fw-semibold small text-body text-truncate">{{ $su->username ?: $su->full_name }}</div>
                            <small class="text-secondary d-block text-truncate" style="font-size: 0.74rem;">{{ $su->suspension_reason ?: __('Ditangguhkan sistem') }}</small>
                        </div>
                        <form action="{{ route('admin.security.firewall.unsuspend', $su->id) }}" method="POST" class="m-0 flex-shrink-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 fw-semibold" style="font-size: 0.72rem;">{{ __('Pulihkan') }}</button>
                        </form>
                    </div>
                @empty
                    <div class="text-muted small">{{ __('Tidak ada akun yang sedang ditangguhkan.') }}</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-clock-rotate-left text-primary me-1.5"></i>{{ __('Log Peristiwa Keamanan Terakhir') }}</h6>
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

{{-- Modal Tambah Aturan Blokir Baru (M3 Bottom Sheet pada Smartphone) --}}
<div class="modal fade" id="addFirewallRuleModal" tabindex="-1" aria-labelledby="addFirewallRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-0 px-4 pt-3">
                <h5 class="modal-title fw-bold text-body" id="addFirewallRuleModalLabel">
                    <i class="fas fa-ban text-danger me-1.5"></i>{{ __('Tambah Aturan Blokir Baru') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="modal-body p-4">
                @include('admin.security.partials.firewall_rule_form', ['idPrefix' => 'modal'])
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($errors->hasAny(['type', 'value', 'duration_days', 'reason']))
        if (window.innerWidth < 992) {
            const fwModal = document.getElementById('addFirewallRuleModal');
            if (fwModal && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(fwModal).show();
            }
        }
    @endif

    if (typeof Chart === 'undefined') return;
    const canvas = document.getElementById('firewallAttackChart');
    if (!canvas) return;
    const primary = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#0288d1';

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: @json($trendLabels),
            datasets: [
                {
                    label: @json(__('Login Gagal')),
                    data: @json($trendFailed),
                    backgroundColor: '#dc2626',
                    borderRadius: 6
                },
                {
                    label: @json(__('Login Sukses')),
                    data: @json($trendSuccess),
                    backgroundColor: primary,
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
});
</script>
@endsection
