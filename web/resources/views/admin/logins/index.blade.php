@extends('layouts.app')

@section('title', __('Notifikasi Login Web'))
@section('page_subtitle', __('Sistem Keamanan'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Daftar Percobaan Login') }}</h4>
        <p class="text-muted mb-0">{{ __('Riwayat autentikasi masuk, diagram analitik keamanan, dan pengelolaan pemblokiran') }}</p>
    </div>
</div>

{{-- KPI Metric Cards Keamanan --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem;">{{ __('Total Percobaan') }}</span>
                    <h4 class="fw-bold mb-0 text-body">{{ number_format($totalLoginAttempts ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem;">{{ __('Login Berhasil') }}</span>
                    <h4 class="fw-bold mb-0 text-success">{{ number_format($successLoginCount ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem;">{{ __('Login Gagal / Ditolak') }}</span>
                    <h4 class="fw-bold mb-0 text-danger">{{ number_format($failedLoginCount ?? 0, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-3 p-md-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-bold text-uppercase d-block text-truncate" style="font-size: 0.72rem;">{{ __('Tingkat Keberhasilan') }}</span>
                    <h4 class="fw-bold mb-0 text-body">{{ $successRate ?? 0 }}%</h4>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Analytics Charts Row --}}
<div class="row g-3 mb-4">
    <!-- Chart: Login Trend -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm h-100 rounded-4">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-chart-line text-primary me-2"></i>{{ __('Tren Percobaan Login (7 Hari Terakhir)') }}</h5>
                <span class="text-muted small">{{ __('Perbandingan frekuensi login berhasil vs gagal') }}</span>
            </div>
            <div class="card-body px-3 pb-2 pt-2">
                <div id="loginTrendChart" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>

    <!-- Chart: Device Distribution -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm h-100 rounded-4">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4">
                <h5 class="fw-bold mb-1 text-body"><i class="fas fa-laptop text-info me-2"></i>{{ __('Distribusi Perangkat Pengguna') }}</h5>
                <span class="text-muted small">{{ __('Persentase platform perangkat yang mengakses sistem') }}</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center p-3">
                @if(!empty($deviceData) && array_sum($deviceData) > 0)
                    <div id="deviceDistChart" style="min-height: 280px; width: 100%;"></div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2 text-secondary opacity-50"></i>
                        <p class="small mb-0">{{ __('Belum ada data perangkat yang tercatat.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <ul class="nav nav-pills gap-2 mb-0" id="loginTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill px-4 fw-medium" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-pane" type="button" role="tab" aria-selected="true">
                <i class="fas fa-shield-alt me-2"></i>{{ __('Semua Percobaan Web') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-4 fw-medium" id="tg-tab" data-bs-toggle="tab" data-bs-target="#tg-pane" type="button" role="tab" aria-selected="false">
                <i class="fab fa-telegram me-2"></i>{{ __('Login Telegram') }}
            </button>
        </li>
    </ul>

    <form action="{{ route('admin.logins.unblock-all-devices') }}" method="POST" class="d-inline" onclick="confirmAction(event, '{{ __('Reset seluruh pemblokiran IP dan Perangkat di sistem?') }}')">
        @csrf
        <button type="submit" class="btn btn-outline-warning btn-sm rounded-pill px-3 py-2 fw-medium">
            <i class="fas fa-sync-alt me-1"></i>{{ __('Reset Semua Pemblokiran') }}
        </button>
    </form>
</div>

<div class="tab-content" id="loginTabsContent">
    <div class="tab-pane fade show active" id="logs-pane" role="tabpanel" tabindex="0">
        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
            <div class="card-body p-0">
                @if($loginLogs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom bg-light">
                                <th class="px-4 py-3 border-0">{{ __('Waktu') }}</th>
                                <th class="py-3 border-0">{{ __('Status') }}</th>
                                <th class="py-3 border-0">{{ __('User/Email') }}</th>
                                <th class="py-3 border-0">{{ __('IP & Lokasi') }}</th>
                                <th class="py-3 border-0">{{ __('Perangkat') }}</th>
                                <th class="py-3 border-0 text-end pe-4">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loginLogs as $log)
                            @php
                                $effectiveFp = $log->device_fingerprint;
                                $effectiveDevId = $log->device_id;
                                $isIpBlocked = \Illuminate\Support\Facades\Cache::has('blocked_ip:' . $log->ip_address);
                                $isDeviceBlocked = ($effectiveFp && \Illuminate\Support\Facades\Cache::has('blocked_device_fp:' . $effectiveFp))
                                    || ($effectiveDevId && \Illuminate\Support\Facades\Cache::has('blocked_device_id:' . $effectiveDevId));
                                $isAccountSuspended = ($log->user && $log->user->is_suspended);
                            @endphp
                            <tr class="{{ $isDeviceBlocked ? 'table-danger bg-danger bg-opacity-10' : '' }}">
                                <td class="px-4 text-secondary small">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($log->is_successful)
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ __('Berhasil') }}</span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i>{{ __('Gagal') }}</span>
                                    @endif
                                    @if($isDeviceBlocked)
                                        <div class="mt-1">
                                            <span class="badge bg-danger text-white rounded-pill px-2 py-1 small" title="{{ __('Perangkat ini diblokir') }}"><i class="fas fa-laptop me-1"></i>{{ __('Perangkat Diblokir') }}</span>
                                        </div>
                                    @elseif($isIpBlocked)
                                        <div class="mt-1">
                                            <span class="badge bg-danger text-white rounded-pill px-2 py-1 small" title="{{ __('IP ini diblokir') }}"><i class="fas fa-ban me-1"></i>{{ __('IP Diblokir') }}</span>
                                        </div>
                                    @elseif($isAccountSuspended)
                                        <div class="mt-1">
                                            <span class="badge bg-danger text-white rounded-pill px-2 py-1 small" title="{{ __('Akun ini ditangguhkan') }}"><i class="fas fa-user-slash me-1"></i>{{ __('Akun Ditangguhkan') }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="fw-bold text-muted">
                                    {{ $log->username_or_email ?? '-' }}
                                    @if($isAccountSuspended)
                                        <div class="mt-1"><span class="badge bg-danger bg-opacity-10 text-danger border border-danger font-monospace" style="font-size: 0.65rem;"><i class="fas fa-user-slash me-1"></i>{{ __('Akun Ditangguhkan') }}</span></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-medium text-dark">
                                        {{ $log->ip_address }}
                                        @if($isIpBlocked)
                                            <span class="badge bg-danger text-white ms-1" style="font-size: 0.65rem;"><i class="fas fa-ban me-1"></i>{{ __('IP Diblokir') }}</span>
                                        @endif
                                    </div>
                                    <div class="text-secondary small"><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $log->location ?? 'Unknown' }}</div>
                                </td>
                                <td>
                                    <div class="text-dark small">{{ $log->device_type }}</div>
                                    <div class="text-secondary small" title="{{ $log->user_agent }}"><i class="fab me-1 {{ str_contains(strtolower($log->browser ?? ''), 'firefox') ? 'fa-firefox-browser text-warning' : (str_contains(strtolower($log->browser ?? ''), 'chrome') ? 'fa-chrome text-primary' : 'fa-globe text-secondary') }}"></i>{{ $log->browser ?? '-' }}</div>
                                    @if($effectiveFp)
                                        <div class="mt-1 d-flex align-items-center gap-1 flex-wrap">
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border font-monospace" style="font-size: 0.65rem;" title="Fingerprint: {{ $effectiveFp }}"><i class="fas fa-fingerprint me-1"></i>{{ Str::limit($effectiveFp, 16) }}</span>
                                            @if($isDeviceBlocked)
                                                <span class="badge bg-dark text-white" style="font-size: 0.65rem;"><i class="fas fa-laptop me-1"></i>{{ __('Perangkat Diblokir') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-shield-alt me-1"></i>{{ __('Kelola Blokir') }}
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 12px; z-index: 1050;">
                                            <li>
                                                <button type="button" class="dropdown-item text-danger py-2" onclick="openUnifiedBlockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}')">
                                                    <i class="fas fa-ban me-2 text-danger"></i>{{ __('Blokir Akses') }}
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item text-success py-2" onclick="openUnifiedUnblockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}', {{ \Illuminate\Support\Facades\Cache::has('blocked_ip:' . $log->ip_address) ? 'true' : 'false' }}, {{ $isDeviceBlocked ? 'true' : 'false' }}, {{ ($log->user && $log->user->is_suspended) ? 'true' : 'false' }})">
                                                    <i class="fas fa-unlock me-2 text-success"></i>{{ __('Buka Blokir (Unblock)') }}
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-top">
                    {{ $loginLogs->links('pagination::bootstrap-5') }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-shield-alt text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">{{ __('Belum ada riwayat percobaan login.') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tg-pane" role="tabpanel" tabindex="0">
        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
            <div class="card-body p-0">
                @if($loginTokens->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom bg-light">
                                <th class="px-4 py-3 border-0">{{ __('Kode / Token') }}</th>
                                <th class="py-3 border-0">{{ __('Status') }}</th>
                                <th class="py-3 border-0">{{ __('IP Address') }}</th>
                                <th class="py-3 border-0">{{ __('Browser') }}</th>
                                <th class="py-3 border-0">{{ __('Waktu Expired') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loginTokens as $login)
                            <tr>
                                <td class="px-4 fw-bold text-muted">{{ $login->token }}</td>
                                <td>
                                    @if($login->status === 'used')
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">{{ __('Digunakan') }}</span>
                                    @elseif($login->status === 'pending')
                                        <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill">{{ __('Menunggu Verifikasi') }}</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">{{ ucfirst($login->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-secondary small">{{ $login->ip_address ?? '-' }}</td>
                                <td class="text-secondary small text-truncate" style="max-width: 200px;" title="{{ $login->user_agent }}">{{ $login->user_agent ?? '-' }}</td>
                                <td class="text-secondary small">{{ $login->expires_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-top">
                    {{ $loginTokens->links('pagination::bootstrap-5') }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fab fa-telegram text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">{{ __('Belum ada riwayat percobaan login telegram.') }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openUnifiedBlockModal(ip, deviceFp, deviceId, userId, username) {
        Swal.fire({
            title: '{{ __("Kelola Pemblokiran Akses") }}',
            html: `
                <div class="text-start fs-6 p-2">
                    <p class="text-muted small mb-3">{{ __("Centang opsi pemblokiran yang ingin diterapkan dan tentukan durasinya:") }}</p>
                    
                    ${ip ? `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_block_ip" checked>
                        <label class="form-check-label fw-bold" for="swal_block_ip">
                            <i class="fas fa-network-wired text-danger me-1"></i> {{ __("Blokir IP Address") }} (${ip})
                        </label>
                    </div>
                    ` : ''}

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_block_device" checked>
                        <label class="form-check-label fw-bold" for="swal_block_device">
                            <i class="fas fa-laptop text-dark me-1"></i> {{ __("Blokir Perangkat (Fingerprint & Cookie)") }}
                        </label>
                        <div class="text-muted small ms-4">{{ __("Perangkat tetap diblokir meskipun pengguna berganti IP publik / VPN.") }}</div>
                    </div>

                    ${userId ? `
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="swal_suspend_account">
                        <label class="form-check-label fw-bold text-danger" for="swal_suspend_account">
                            <i class="fas fa-user-slash me-1"></i> {{ __("Tangguhkan Akun User") }} (@${username})
                        </label>
                    </div>
                    ` : ''}

                    <div class="mt-3">
                        <label class="form-label fw-bold small text-muted mb-1">{{ __("Durasi Pemblokiran") }}</label>
                        <select id="swal_duration" class="form-select">
                            <option value="1">1 {{ __("Hari") }}</option>
                            <option value="7">7 {{ __("Hari") }}</option>
                            <option value="30" selected>30 {{ __("Hari") }}</option>
                            <option value="365">1 {{ __("Tahun (365 Hari)") }}</option>
                        </select>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-ban me-1"></i> {{ __("Terapkan Pemblokiran") }}',
            cancelButtonText: '{{ __("Batal") }}',
            preConfirm: () => {
                const blockIp = document.getElementById('swal_block_ip') ? document.getElementById('swal_block_ip').checked : false;
                const blockDevice = document.getElementById('swal_block_device') ? document.getElementById('swal_block_device').checked : false;
                const suspendAccount = document.getElementById('swal_suspend_account') ? document.getElementById('swal_suspend_account').checked : false;
                const duration = document.getElementById('swal_duration').value;

                if (!blockIp && !blockDevice && !suspendAccount) {
                    Swal.showValidationMessage('{{ __("Pilih minimal satu opsi yang ingin diblokir!") }}');
                    return false;
                }

                return { blockIp, blockDevice, suspendAccount, duration };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("admin.logins.unified-block") }}';

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);

                if (ip) {
                    const ipInput = document.createElement('input');
                    ipInput.type = 'hidden';
                    ipInput.name = 'ip_address';
                    ipInput.value = ip;
                    form.appendChild(ipInput);
                }

                if (deviceFp) {
                    const fpInput = document.createElement('input');
                    fpInput.type = 'hidden';
                    fpInput.name = 'device_fingerprint';
                    fpInput.value = deviceFp;
                    form.appendChild(fpInput);
                }

                if (deviceId) {
                    const devInput = document.createElement('input');
                    devInput.type = 'hidden';
                    devInput.name = 'device_id';
                    devInput.value = deviceId;
                    form.appendChild(devInput);
                }

                if (userId) {
                    const uInput = document.createElement('input');
                    uInput.type = 'hidden';
                    uInput.name = 'user_id';
                    uInput.value = userId;
                    form.appendChild(uInput);
                }

                const bIp = document.createElement('input');
                bIp.type = 'hidden';
                bIp.name = 'block_ip';
                bIp.value = result.value.blockIp ? '1' : '0';
                form.appendChild(bIp);

                const bDev = document.createElement('input');
                bDev.type = 'hidden';
                bDev.name = 'block_device';
                bDev.value = result.value.blockDevice ? '1' : '0';
                form.appendChild(bDev);

                const sAcc = document.createElement('input');
                sAcc.type = 'hidden';
                sAcc.name = 'suspend_account';
                sAcc.value = result.value.suspendAccount ? '1' : '0';
                form.appendChild(sAcc);

                const dur = document.createElement('input');
                dur.type = 'hidden';
                dur.name = 'duration';
                dur.value = result.value.duration;
                form.appendChild(dur);

                document.body.appendChild(form);
                const loader = document.getElementById('pageLoader');
                if (loader) loader.classList.remove('fade-out');
                form.submit();
            }
        });
    }

    function openUnifiedUnblockModal(ip, deviceFp, deviceId, userId, username, isIpBlocked, isDeviceBlocked, isAccountSuspended) {
        Swal.fire({
            title: '{{ __("Buka Pemblokiran (Unblock)") }}',
            html: `
                <div class="text-start fs-6 p-2">
                    <p class="text-muted small mb-3">{{ __("Centang opsi pemblokiran yang ingin dibuka:") }}</p>
                    
                    ${ip ? `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_unblock_ip" ${isIpBlocked ? 'checked' : ''}>
                        <label class="form-check-label fw-bold" for="swal_unblock_ip">
                            <i class="fas fa-network-wired text-success me-1"></i> {{ __("Buka Blokir IP Address") }} (${ip})
                        </label>
                    </div>
                    ` : ''}

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_unblock_device" ${isDeviceBlocked ? 'checked' : ''}>
                        <label class="form-check-label fw-bold" for="swal_unblock_device">
                            <i class="fas fa-laptop text-success me-1"></i> {{ __("Buka Blokir Perangkat (Fingerprint & Cookie)") }}
                        </label>
                    </div>

                    ${userId ? `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="swal_unsuspend_account" ${isAccountSuspended ? 'checked' : ''}>
                        <label class="form-check-label fw-bold text-success" for="swal_unsuspend_account">
                            <i class="fas fa-user-check me-1"></i> {{ __("Aktifkan Kembali Akun User") }} (@${username})
                        </label>
                    </div>
                    ` : ''}
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-unlock me-1"></i> {{ __("Proses Buka Blokir") }}',
            cancelButtonText: '{{ __("Batal") }}',
            preConfirm: () => {
                const unblockIp = document.getElementById('swal_unblock_ip') ? document.getElementById('swal_unblock_ip').checked : false;
                const unblockDevice = document.getElementById('swal_unblock_device') ? document.getElementById('swal_unblock_device').checked : false;
                const unsuspendAccount = document.getElementById('swal_unsuspend_account') ? document.getElementById('swal_unsuspend_account').checked : false;

                if (!unblockIp && !unblockDevice && !unsuspendAccount) {
                    Swal.showValidationMessage('{{ __("Pilih minimal satu opsi yang ingin dibuka!") }}');
                    return false;
                }

                return { unblockIp, unblockDevice, unsuspendAccount };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("admin.logins.unified-unblock") }}';

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);

                if (ip) {
                    const ipInput = document.createElement('input');
                    ipInput.type = 'hidden';
                    ipInput.name = 'ip_address';
                    ipInput.value = ip;
                    form.appendChild(ipInput);
                }

                if (deviceFp) {
                    const fpInput = document.createElement('input');
                    fpInput.type = 'hidden';
                    fpInput.name = 'device_fingerprint';
                    fpInput.value = deviceFp;
                    form.appendChild(fpInput);
                }

                if (deviceId) {
                    const devInput = document.createElement('input');
                    devInput.type = 'hidden';
                    devInput.name = 'device_id';
                    devInput.value = deviceId;
                    form.appendChild(devInput);
                }

                if (userId) {
                    const uInput = document.createElement('input');
                    uInput.type = 'hidden';
                    uInput.name = 'user_id';
                    uInput.value = userId;
                    form.appendChild(uInput);
                }

                const uIp = document.createElement('input');
                uIp.type = 'hidden';
                uIp.name = 'unblock_ip';
                uIp.value = result.value.unblockIp ? '1' : '0';
                form.appendChild(uIp);

                const uDev = document.createElement('input');
                uDev.type = 'hidden';
                uDev.name = 'unblock_device';
                uDev.value = result.value.unblockDevice ? '1' : '0';
                form.appendChild(uDev);

                const uAcc = document.createElement('input');
                uAcc.type = 'hidden';
                uAcc.name = 'unsuspend_account';
                uAcc.value = result.value.unsuspendAccount ? '1' : '0';
                form.appendChild(uAcc);

                document.body.appendChild(form);
                const loader = document.getElementById('pageLoader');
                if (loader) loader.classList.remove('fade-out');
                form.submit();
            }
        });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Login Trend Multi-Area Chart (Berhasil vs Gagal)
    const loginTrendOptions = {
        series: [
            {
                name: "{{ __('Login Berhasil') }}",
                data: @json($loginSuccessTrend ?? [])
            },
            {
                name: "{{ __('Login Gagal') }}",
                data: @json($loginFailedTrend ?? [])
            }
        ],
        chart: {
            type: 'area',
            height: 280,
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit'
        },
        colors: ['#198754', '#dc3545'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: [3, 3] },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [20, 100]
            }
        },
        xaxis: {
            categories: @json($loginTrendLabels ?? []),
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                formatter: function (val) {
                    return Math.round(val);
                }
            },
            min: 0
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right'
        },
        tooltip: {
            theme: 'dark'
        }
    };
    const loginTrendChart = new ApexCharts(document.querySelector("#loginTrendChart"), loginTrendOptions);
    loginTrendChart.render();

    // 2. Device Distribution Donut Chart
    @if(!empty($deviceData) && array_sum($deviceData) > 0)
    const deviceDistOptions = {
        series: @json($deviceData ?? []),
        labels: @json($deviceLabels ?? []),
        chart: {
            type: 'donut',
            height: 280,
            fontFamily: 'inherit'
        },
        colors: ['#0d6efd', '#20c997', '#ffc107', '#0dcaf0', '#6c757d'],
        legend: {
            position: 'bottom',
            fontSize: '12px'
        },
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                return Math.round(val) + "%";
            }
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: "{{ __('Total Akses') }}",
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                            }
                        }
                    }
                }
            }
        },
        tooltip: {
            theme: 'dark'
        }
    };
    const deviceDistChart = new ApexCharts(document.querySelector("#deviceDistChart"), deviceDistOptions);
    deviceDistChart.render();
    @endif
});
</script>
@endpush
