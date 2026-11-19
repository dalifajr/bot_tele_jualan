{{-- Tab 5: Intelijen Ancaman, Anti-Spam Multi-Akun & Akun Ditangguhkan --}}
<div class="row g-4 mb-4">
    {{-- Radar IP Agresif & Brute-Force (7 Hari) --}}
    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h6 class="fw-bold text-body mb-0"><i class="fas fa-radar text-danger me-1.5"></i>{{ __('IP Agresif & Brute-Force (7 Hari)') }}</h6>
                        <small class="text-muted">{{ __('IP dengan percobaan gagal terbanyak beserta aksi blokir IP/subnet /24') }}</small>
                    </div>
                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5">{{ $topSuspiciousIps->count() }} IP</span>
                </div>

                @forelse($topSuspiciousIps as $row)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2.5 border-bottom" style="border-color: var(--outline-variant) !important;">
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
                            <div class="d-flex gap-1 align-items-center">
                                <form action="{{ route('admin.security.firewall.store') }}" method="POST" class="m-0">
                                    @csrf
                                    <input type="hidden" name="active_tab" value="ancaman">
                                    <input type="hidden" name="type" value="ip">
                                    <input type="hidden" name="value" value="{{ $row->ip_address }}">
                                    <input type="hidden" name="duration_days" value="7">
                                    <input type="hidden" name="reason" value="{{ __('Anomali gagal login berulang') }}">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 fw-semibold" style="font-size: 0.72rem;">{{ __('Blokir IP') }}</button>
                                </form>
                                @if($row->subnet_24)
                                    <form action="{{ route('admin.security.firewall.store') }}" method="POST" class="m-0">
                                        @csrf
                                        <input type="hidden" name="active_tab" value="ancaman">
                                        <input type="hidden" name="type" value="subnet">
                                        <input type="hidden" name="value" value="{{ $row->subnet_24 }}">
                                        <input type="hidden" name="duration_days" value="7">
                                        <input type="hidden" name="reason" value="{{ __('Blokir subnet brute-force') }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-2 fw-semibold" style="font-size: 0.72rem;" title="{{ $row->subnet_24 }}">/24</button>
                                    </form>
                                @endif
                                <button type="button" class="btn btn-sm btn-light rounded-pill px-2" style="font-size: 0.72rem;" title="{{ __('Opsi Blokir Lanjutan') }}" onclick="openUnifiedBlockModal('{{ $row->ip_address }}', '', '', '', '')">
                                    <i class="fas fa-sliders-h"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-muted small py-4 text-center">
                        <i class="fas fa-check-circle text-success me-1"></i>{{ __('Tidak ada anomali IP dalam 7 hari terakhir.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Target Akun Diserang --}}
    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h6 class="fw-bold text-body mb-0"><i class="fas fa-crosshairs text-warning me-1.5"></i>{{ __('Target Akun Diserang') }}</h6>
                        <small class="text-muted">{{ __('Akun / username yang paling sering menjadi target kegagalan login') }}</small>
                    </div>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5">{{ $targetedAccounts->count() }} {{ __('akun') }}</span>
                </div>

                @forelse($targetedAccounts as $acc)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-2.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <div class="overflow-hidden">
                            <div class="fw-semibold small text-body text-truncate">
                                <i class="fas fa-user-lock text-warning me-1.5"></i>{{ $acc->username_or_email }}
                            </div>
                            <small class="text-secondary" style="font-size: 0.74rem;">
                                <span class="text-danger fw-bold">{{ $acc->fail_count }}x</span> {{ __('gagal') }} · {{ __(':ips IP berbeda', ['ips' => $acc->unique_ips]) }}
                            </small>
                        </div>
                        <a href="{{ route('admin.logins.index', ['tab' => 'riwayat', 'search' => $acc->username_or_email, 'status' => 'failed']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 flex-shrink-0" style="font-size: 0.72rem;">
                            <i class="fas fa-search me-1"></i>{{ __('Log') }}
                        </a>
                    </div>
                @empty
                    <div class="text-muted small py-4 text-center">
                        <i class="fas fa-shield-alt text-secondary me-1"></i>{{ __('Belum ada target akun diserang.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Row 2: Deteksi IP Multi-Akun (Anti-Spam) & Akun Ditangguhkan Terbaru --}}
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-users-slash text-primary me-1.5"></i>{{ __('Deteksi IP Multi-Akun (Anti-Spam)') }}</h6>
                @forelse($multiAccountIps as $mip)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-2.5 border-bottom" style="border-color: var(--outline-variant) !important;">
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
                                <input type="hidden" name="active_tab" value="ancaman">
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

    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                    <h6 class="fw-bold text-body mb-0"><i class="fas fa-user-lock text-warning me-1.5"></i>{{ __('Akun Ditangguhkan Terbaru') }}</h6>
                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5">{{ $suspendedUsersCount }} {{ __('total') }}</span>
                </div>
                @forelse($suspendedUsers as $su)
                    <div class="d-flex justify-content-between align-items-center gap-2 py-2.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <div class="overflow-hidden">
                            <div class="fw-semibold small text-body text-truncate">{{ $su->username ?: $su->full_name }}</div>
                            <small class="text-secondary d-block text-truncate" style="font-size: 0.74rem;">
                                {{ $su->suspension_reason ?: __('Ditangguhkan sistem') }}
                                @if($su->registration_ip)
                                    · <span class="font-monospace">{{ $su->registration_ip }}</span>
                                @endif
                            </small>
                        </div>
                        <form action="{{ route('admin.security.firewall.unsuspend', $su->id) }}" method="POST" class="m-0 flex-shrink-0">
                            @csrf
                            <input type="hidden" name="active_tab" value="ancaman">
                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 fw-semibold" style="font-size: 0.72rem;">{{ __('Pulihkan') }}</button>
                        </form>
                    </div>
                @empty
                    <div class="text-muted small py-3 text-center">{{ __('Tidak ada akun yang sedang ditangguhkan.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
