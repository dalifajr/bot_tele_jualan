{{-- Tab 3: Riwayat Login Web & Token Login Telegram --}}
@php
    $showTelegramSubTab = request()->filled('tg_page') && !request()->filled('log_page');
@endphp

{{-- Filter Pencarian Log Login --}}
<div class="card mb-4 overflow-hidden">
    <div class="sd-dashboard-filter-bar border-bottom-0 p-3">
        <form action="{{ route('admin.logins.index') }}" method="GET" class="row g-2 align-items-end w-100 m-0">
            <input type="hidden" name="tab" value="riwayat">
            <div class="col-lg-5 col-md-12">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Cari User / IP / Lokasi / Browser') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-filter text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="{{ __('Ketik username, email, IP address, atau lokasi...') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Status Autentikasi') }}</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>{{ __('Berhasil Login') }}</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>{{ __('Gagal / Ditolak') }}</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-secondary small fw-bold mb-1">{{ __('Tipe Perangkat') }}</label>
                <select name="device_type" class="form-select form-select-sm">
                    <option value="">{{ __('Semua Perangkat') }}</option>
                    @foreach(($deviceLabels ?? []) as $dl)
                        <option value="{{ $dl }}" {{ request('device_type') === $dl ? 'selected' : '' }}>{{ $dl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-1 col-md-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100" title="{{ __('Terapkan Filter') }}"><i class="fas fa-search"></i></button>
                    @if(request()->filled('search') || request()->filled('status') || request()->filled('device_type'))
                        <a href="{{ route('admin.logins.index', ['tab' => 'riwayat']) }}" class="btn btn-sm btn-light px-2.5" title="{{ __('Reset Filter') }}"><i class="fas fa-undo"></i></a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Sub-Tabs: Percobaan Web vs Token Login Telegram + Tombol Reset Pemblokiran --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <ul class="nav nav-pills gap-2 mb-0" id="loginSubTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ !$showTelegramSubTab ? 'active' : '' }} rounded-pill px-4 fw-medium" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs-pane" type="button" role="tab" aria-selected="{{ !$showTelegramSubTab ? 'true' : 'false' }}">
                <i class="fas fa-shield-alt me-2"></i>{{ __('Semua Percobaan Web') }}
                <span class="badge bg-secondary-subtle text-body rounded-pill ms-1" style="font-size: 0.7rem;">{{ number_format($loginLogs->total(), 0, ',', '.') }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $showTelegramSubTab ? 'active' : '' }} rounded-pill px-4 fw-medium" id="tg-tab" data-bs-toggle="tab" data-bs-target="#tg-pane" type="button" role="tab" aria-selected="{{ $showTelegramSubTab ? 'true' : 'false' }}">
                <i class="fab fa-telegram me-2"></i>{{ __('Login Telegram') }}
                @if(($tgPendingTokens ?? 0) > 0)
                    <span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size: 0.7rem;">{{ $tgPendingTokens }}</span>
                @endif
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

<div class="tab-content" id="loginSubTabsContent">
    {{-- Sub-tab 1: Semua Percobaan Login Web --}}
    <div class="tab-pane fade {{ !$showTelegramSubTab ? 'show active' : '' }}" id="logs-pane" role="tabpanel" tabindex="0">
        <div class="card overflow-hidden">
            <div class="card-body p-0">
                @if($loginLogs->count() > 0)
                {{-- Desktop Table --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">{{ __('Waktu') }}</th>
                                <th class="py-3">{{ __('Status') }}</th>
                                <th class="py-3">{{ __('User/Email') }}</th>
                                <th class="py-3">{{ __('IP & Lokasi') }}</th>
                                <th class="py-3">{{ __('Perangkat') }}</th>
                                <th class="py-3 text-end pe-4">{{ __('Aksi') }}</th>
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
                                <td class="px-4 text-secondary small font-monospace">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    @if($log->is_successful)
                                        <span class="badge bg-success-subtle text-success px-3 py-1.5 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ __('Berhasil') }}</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger px-3 py-1.5 rounded-pill"><i class="fas fa-times-circle me-1"></i>{{ __('Gagal') }}</span>
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
                                <td class="fw-bold text-body">
                                    {{ $log->username_or_email ?? '-' }}
                                    @if($isAccountSuspended)
                                        <div class="mt-1"><span class="badge bg-danger-subtle text-danger font-monospace" style="font-size: 0.65rem;"><i class="fas fa-user-slash me-1"></i>{{ __('Akun Ditangguhkan') }}</span></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-body font-monospace" style="font-size: 0.85rem;">
                                        {{ $log->ip_address }}
                                        @if($isIpBlocked)
                                            <span class="badge bg-danger text-white ms-1" style="font-size: 0.65rem;"><i class="fas fa-ban me-1"></i>{{ __('IP Diblokir') }}</span>
                                        @endif
                                    </div>
                                    <div class="text-secondary small"><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $log->location ?? 'Unknown' }}</div>
                                </td>
                                <td>
                                    <div class="text-body small fw-medium">{{ $log->device_type }}</div>
                                    <div class="text-secondary small" title="{{ $log->user_agent }}"><i class="fab me-1 {{ str_contains(strtolower($log->browser ?? ''), 'firefox') ? 'fa-firefox-browser text-warning' : (str_contains(strtolower($log->browser ?? ''), 'chrome') ? 'fa-chrome text-primary' : 'fa-globe text-secondary') }}"></i>{{ $log->browser ?? '-' }}</div>
                                    @if($effectiveFp)
                                        <div class="mt-1 d-flex align-items-center gap-1 flex-wrap">
                                            <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size: 0.65rem;" title="Fingerprint: {{ $effectiveFp }}"><i class="fas fa-fingerprint me-1"></i>{{ Str::limit($effectiveFp, 16) }}</span>
                                            @if($isDeviceBlocked)
                                                <span class="badge bg-danger text-white" style="font-size: 0.65rem;"><i class="fas fa-laptop me-1"></i>{{ __('Perangkat Diblokir') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-shield-alt me-1"></i>{{ __('Kelola Blokir') }}
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" style="z-index: 1050;">
                                            <li>
                                                <button type="button" class="dropdown-item text-danger py-2" onclick="openUnifiedBlockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}')">
                                                    <i class="fas fa-ban me-2 text-danger"></i>{{ __('Blokir Akses') }}
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item text-success py-2" onclick="openUnifiedUnblockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}', {{ $isIpBlocked ? 'true' : 'false' }}, {{ $isDeviceBlocked ? 'true' : 'false' }}, {{ $isAccountSuspended ? 'true' : 'false' }})">
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

                {{-- Mobile Collapsible Compact List (< 768px) --}}
                <div class="d-md-none p-2.5 m3-collapsible-list" id="hubLoginLogsAccordion">
                    @foreach($loginLogs as $log)
                    @php
                        $effectiveFp = $log->device_fingerprint;
                        $effectiveDevId = $log->device_id;
                        $isIpBlocked = \Illuminate\Support\Facades\Cache::has('blocked_ip:' . $log->ip_address);
                        $isDeviceBlocked = ($effectiveFp && \Illuminate\Support\Facades\Cache::has('blocked_device_fp:' . $effectiveFp))
                            || ($effectiveDevId && \Illuminate\Support\Facades\Cache::has('blocked_device_id:' . $effectiveDevId));
                        $isAccountSuspended = ($log->user && $log->user->is_suspended);
                    @endphp
                    <div class="m3-mobile-item-card m3-collapsible-card {{ $isDeviceBlocked ? 'border-danger' : '' }}">
                        <div class="m3-compact-row">
                            <button type="button"
                                    class="m3-compact-trigger"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#hubLoginLogCollapse-{{ $log->id }}"
                                    aria-expanded="false"
                                    aria-controls="hubLoginLogCollapse-{{ $log->id }}">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold text-body text-truncate" style="font-size: 0.84rem;">
                                        {{ $log->username_or_email ?? '-' }}
                                    </div>
                                    <div class="text-secondary text-truncate font-monospace" style="font-size: 0.72rem;">
                                        {{ $log->ip_address }} · {{ $log->created_at->format('d M, H:i') }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                                    @if($log->is_successful)
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Berhasil') }}</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Gagal') }}</span>
                                    @endif
                                    @if($isDeviceBlocked || $isIpBlocked || $isAccountSuspended)
                                        <span class="badge bg-danger text-white rounded-pill px-1.5 py-0.5" style="font-size: 0.6rem;"><i class="fas fa-ban"></i></span>
                                    @endif
                                    <i class="fas fa-chevron-down m3-compact-chevron"></i>
                                </div>
                            </button>
                        </div>

                        <div class="collapse" id="hubLoginLogCollapse-{{ $log->id }}" data-bs-parent="#hubLoginLogsAccordion">
                            <div class="m3-collapsible-body">
                                <div class="small text-secondary mb-2.5">
                                    @if($isDeviceBlocked || $isIpBlocked || $isAccountSuspended)
                                        <div class="mb-1.5 d-flex flex-wrap gap-1">
                                            @if($isDeviceBlocked)
                                                <span class="badge bg-danger text-white rounded-pill">{{ __('Perangkat Diblokir') }}</span>
                                            @elseif($isIpBlocked)
                                                <span class="badge bg-danger text-white rounded-pill">{{ __('IP Diblokir') }}</span>
                                            @elseif($isAccountSuspended)
                                                <span class="badge bg-danger text-white rounded-pill">{{ __('Suspended') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    <div><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $log->location ?? 'Unknown' }}</div>
                                    <div><i class="fas fa-laptop me-1"></i>{{ $log->device_type }} · {{ $log->browser ?? '-' }}</div>
                                    @if($effectiveFp)
                                        <span class="badge bg-secondary-subtle text-secondary font-monospace mt-1" style="font-size: 0.65rem;"><i class="fas fa-fingerprint me-1"></i>{{ Str::limit($effectiveFp, 18) }}</span>
                                    @endif
                                </div>
                                <div class="d-flex gap-1.5">
                                    <button type="button" class="btn btn-sm btn-outline-danger m3-compact-action-btn flex-grow-1"
                                            onclick="openUnifiedBlockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}')">
                                        <i class="fas fa-ban"></i><span>{{ __('Blokir') }}</span>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success m3-compact-action-btn flex-grow-1"
                                            onclick="openUnifiedUnblockModal('{{ $log->ip_address }}', '{{ $effectiveFp }}', '{{ $effectiveDevId }}', '{{ $log->user_id }}', '{{ $log->user ? $log->user->username : ($log->username_or_email ?? '') }}', {{ $isIpBlocked ? 'true' : 'false' }}, {{ $isDeviceBlocked ? 'true' : 'false' }}, {{ $isAccountSuspended ? 'true' : 'false' }})">
                                        <i class="fas fa-unlock"></i><span>{{ __('Unblock') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="px-4 py-3 border-top">
                    {{ $loginLogs->appends(['tab' => 'riwayat'])->links('pagination::bootstrap-5') }}
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

    {{-- Sub-tab 2: Token Login Telegram --}}
    <div class="tab-pane fade {{ $showTelegramSubTab ? 'show active' : '' }}" id="tg-pane" role="tabpanel" tabindex="0">
        <div class="card overflow-hidden">
            <div class="card-body p-0">
                @if($loginTokens->count() > 0)
                {{-- Desktop Table --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">{{ __('Kode / Token') }}</th>
                                <th class="py-3">{{ __('Status') }}</th>
                                <th class="py-3">{{ __('IP Address') }}</th>
                                <th class="py-3">{{ __('Browser') }}</th>
                                <th class="py-3">{{ __('Waktu Expired') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loginTokens as $login)
                            <tr>
                                <td class="px-4 fw-bold text-body font-monospace">{{ $login->token }}</td>
                                <td>
                                    @if($login->status === 'used')
                                        <span class="badge bg-success-subtle text-success px-3 py-1.5 rounded-pill">{{ __('Digunakan') }}</span>
                                    @elseif($login->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning px-3 py-1.5 rounded-pill">{{ __('Menunggu Verifikasi') }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary px-3 py-1.5 rounded-pill">{{ ucfirst($login->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-secondary small font-monospace">{{ $login->ip_address ?? '-' }}</td>
                                <td class="text-secondary small text-truncate" style="max-width: 200px;" title="{{ $login->user_agent }}">{{ $login->user_agent ?? '-' }}</td>
                                <td class="text-secondary small">{{ $login->expires_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Collapsible Compact List (< 768px) --}}
                <div class="d-md-none p-2.5 m3-collapsible-list" id="hubTgTokensAccordion">
                    @foreach($loginTokens as $login)
                    <div class="m3-mobile-item-card m3-collapsible-card">
                        <div class="m3-compact-row">
                            <button type="button"
                                    class="m3-compact-trigger"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#hubTgTokenCollapse-{{ $login->id }}"
                                    aria-expanded="false"
                                    aria-controls="hubTgTokenCollapse-{{ $login->id }}">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold font-monospace text-body text-truncate" style="font-size: 0.8rem;">{{ Str::limit($login->token, 22) }}</div>
                                    <div class="text-secondary text-truncate font-monospace" style="font-size: 0.72rem;">
                                        {{ $login->ip_address ?? '-' }} · {{ $login->expires_at->diffForHumans() }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    @if($login->status === 'used')
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Digunakan') }}</span>
                                    @elseif($login->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ __('Menunggu') }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5" style="font-size: 0.64rem;">{{ ucfirst($login->status) }}</span>
                                    @endif
                                    <i class="fas fa-chevron-down m3-compact-chevron"></i>
                                </div>
                            </button>
                        </div>
                        <div class="collapse" id="hubTgTokenCollapse-{{ $login->id }}" data-bs-parent="#hubTgTokensAccordion">
                            <div class="m3-collapsible-body small text-secondary">
                                <div class="font-monospace text-body text-break mb-1" style="font-size: 0.74rem;">{{ $login->token }}</div>
                                <div class="text-break">{{ $login->user_agent ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="px-4 py-3 border-top">
                    {{ $loginTokens->appends(['tab' => 'riwayat'])->links('pagination::bootstrap-5') }}
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
