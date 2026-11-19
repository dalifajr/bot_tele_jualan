{{-- Tab 4: Update & Rollback GitHub, Konfigurasi Turnstile & Reset Password Admin --}}

{{-- Row 1: GitHub Update Manager (setara jualan check-update / update / rollback) --}}
<div class="card border-0 rounded-4 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h6 class="fw-bold text-body mb-0"><i class="fab fa-github text-body me-1.5"></i>{{ __('Manajer Pembaruan Kode (GitHub Update & Rollback)') }}</h6>
                <small class="text-muted">{{ __('Setara perintah "jualan check-update", "jualan update", dan "jualan rollback" — otomatis mencadangkan .env & menjalankan migrasi') }}</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" id="btnCheckGitUpdate" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                    <i class="fas fa-magnifying-glass me-1"></i>{{ __('Cek Pembaruan') }}
                </button>
                <form action="{{ route('admin.system.health.update') }}" method="POST" class="m-0">
                    @csrf
                    <input type="hidden" name="active_tab" value="pembaruan">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold" {{ !$bridgeStatus['available'] ? 'disabled' : '' }} onclick="confirmAction(event, '{{ __('Terapkan update terbaru dari GitHub sekarang? Backup otomatis akan dibuat sebelum pull.') }}')">
                        <i class="fas fa-cloud-arrow-down me-1"></i>{{ __('Terapkan Update') }}
                    </button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" {{ (!$bridgeStatus['available'] || empty($gitInfo['rollback_commit'])) ? 'disabled' : '' }} data-bs-toggle="modal" data-bs-target="#rollbackConfirmModal">
                    <i class="fas fa-rotate-left me-1"></i>{{ __('Rollback') }}
                </button>
            </div>
        </div>

        @if(!$bridgeStatus['available'])
            <div class="p-3 rounded-4 border mb-3 d-flex flex-column gap-2" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                <div class="d-flex align-items-start gap-2.5 small">
                    <i class="fas fa-lock text-warning mt-0.5"></i>
                    <div>
                        <div class="fw-bold text-body">{{ __('Tombol "Terapkan Update" & "Rollback" memerlukan VPS Ops Bridge') }}</div>
                        <div class="text-secondary" style="font-size: 0.76rem;">
                            {{ __('Jalankan perintah sekali pakai di terminal VPS sebagai root (atau jalankan "sudo jualan update" / "sudo jualan repair" sekali) agar pembaruan 1-klik dari web langsung aktif:') }}
                        </div>
                        @if(!empty($bridgeStatus['reason']))
                            <div class="text-danger font-monospace mt-1" style="font-size: 0.72rem;">{{ $bridgeStatus['reason'] }}</div>
                        @endif
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap ps-4">
                    <code class="px-2.5 py-1 rounded-3 bg-dark text-light user-select-all" style="font-size: 0.75rem;" id="updateBridgeInstallCmd">{{ $bridgeStatus['install_command'] }}</code>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold" onclick="copyTextValue('updateBridgeInstallCmd', this)">
                        <i class="fas fa-copy me-1"></i>{{ __('Salin') }}
                    </button>
                </div>
            </div>
        @endif

        <div class="row g-3 small mb-3">
            <div class="col-md-3">
                <div class="p-3 rounded-4 border h-100" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                    <div class="text-muted mb-1">{{ __('Branch Aktif') }}</div>
                    <div class="fw-bold font-monospace text-body">{{ $gitInfo['branch'] }}</div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="p-3 rounded-4 border h-100" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                    <div class="text-muted mb-1">{{ __('Commit Lokal Saat Ini') }}</div>
                    <div class="fw-bold font-monospace text-primary">{{ $gitInfo['commit_short'] }}</div>
                    <div class="text-secondary text-truncate" style="font-size: 0.74rem;" title="{{ $gitInfo['commit_subject'] }}">{{ $gitInfo['commit_subject'] }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 rounded-4 border h-100" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                    <div class="text-muted mb-1">{{ __('Target Rollback & Status Tree') }}</div>
                    <div class="fw-bold font-monospace text-body">
                        {{ $gitInfo['rollback_short'] ?: __('Belum ada') }}
                        @if($gitInfo['is_dirty'])
                            <span class="badge bg-warning-subtle text-warning-emphasis ms-1">{{ __('Ada perubahan lokal') }}</span>
                        @else
                            <span class="badge bg-success-subtle text-success ms-1">{{ __('Bersih') }}</span>
                        @endif
                    </div>
                    <div class="text-muted" style="font-size: 0.72rem;">{{ $gitInfo['commit_date'] }}</div>
                </div>
            </div>
        </div>

        {{-- Output Live Check Update / Background Ops Task --}}
        <div class="rounded-4 p-3 bg-dark text-light">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-semibold text-info">
                    <i class="fas fa-terminal me-1.5"></i>{{ __('Konsol Tugas & Cek Update') }}
                </span>
                <div class="d-flex align-items-center gap-2">
                    <span id="opsTaskStatusBadge" class="badge rounded-pill {{ $taskStatus['status'] === 'running' ? 'bg-warning text-dark' : ($taskStatus['status'] === 'done' ? 'bg-success' : ($taskStatus['status'] === 'failed' ? 'bg-danger' : 'bg-secondary')) }}">
                        {{ strtoupper($taskStatus['status']) }}
                    </span>
                    <button type="button" id="btnRefreshTaskStatus" class="btn btn-sm btn-outline-light rounded-pill px-2.5 py-0" style="font-size: 0.72rem;">
                        <i class="fas fa-rotate"></i>
                    </button>
                </div>
            </div>
            <pre id="opsTaskConsole" class="mb-0 small text-light" style="max-height: 220px; overflow-y: auto; white-space: pre-wrap;">{{ $taskStatus['output'] ?: __('Klik "Cek Pembaruan" untuk memeriksa commit terbaru dari GitHub, atau pantau progres tugas update/repair di sini.') }}</pre>
        </div>
    </div>
</div>

{{-- Row 2: Konfigurasi Cloudflare Turnstile + Reset Password Admin --}}
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                    <div>
                        <h6 class="fw-bold text-body mb-0"><i class="fas fa-cloud text-warning me-1.5"></i>{{ __('Konfigurasi Cloudflare Turnstile') }}</h6>
                        <small class="text-muted">{{ __('Setara perintah "jualan turnstile-config" — simpan langsung ke web/.env') }}</small>
                    </div>
                    <span class="badge rounded-pill {{ $turnstileInfo['configured'] ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' }}">
                        {{ $turnstileInfo['configured'] ? __('Aktif') : __('Belum Diatur') }}
                    </span>
                </div>

                <form action="{{ route('admin.system.health.turnstile') }}" method="POST">
                    @csrf
                    <input type="hidden" name="active_tab" value="pembaruan">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Turnstile Site Key') }}</label>
                        <input type="text" name="site_key" class="form-control font-monospace" value="{{ old('site_key', $turnstileInfo['site_key']) }}" placeholder="0x4AAAAAAA..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">
                            {{ __('Turnstile Secret Key') }}
                            @if($turnstileInfo['secret_masked'])
                                <span class="text-muted fw-normal">({{ __('Saat ini:') }} <code>{{ $turnstileInfo['secret_masked'] }}</code>)</span>
                            @endif
                        </label>
                        <input type="password" name="secret_key" class="form-control font-monospace" placeholder="{{ __('Masukkan Secret Key Cloudflare Turnstile') }}" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-pill w-100 fw-semibold">
                        <i class="fas fa-floppy-disk me-1.5"></i>{{ __('Simpan Kunci Turnstile') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="mb-3">
                    <h6 class="fw-bold text-body mb-0"><i class="fas fa-key text-danger me-1.5"></i>{{ __('Reset Password Akun Admin') }}</h6>
                    <small class="text-muted">{{ __('Setara perintah "jualan reset-password" — wajib verifikasi kata sandi Anda saat ini') }}</small>
                </div>

                <form action="{{ route('admin.system.health.reset-admin-password') }}" method="POST">
                    @csrf
                    <input type="hidden" name="active_tab" value="pembaruan">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Pilih Akun Admin') }}</label>
                        <select name="target_admin_id" class="form-select" required>
                            @foreach($adminUsers as $adm)
                                <option value="{{ $adm->id }}">
                                    {{ '@' . $adm->username }} — {{ $adm->full_name ?: ($adm->email ?: ('ID #' . $adm->id)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold text-muted">{{ __('Password Baru (min. 8)') }}</label>
                            <input type="password" name="new_password" class="form-control" minlength="8" autocomplete="new-password" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small fw-semibold text-muted">{{ __('Konfirmasi Password Baru') }}</label>
                            <input type="password" name="new_password_confirmation" class="form-control" minlength="8" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-danger">{{ __('Verifikasi Kata Sandi Admin Anda Saat Ini') }}</label>
                        <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-outline-danger rounded-pill w-100 fw-semibold">
                        <i class="fas fa-user-shield me-1.5"></i>{{ __('Reset Password Admin') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Rollback dengan Verifikasi Password --}}
<div class="modal fade" id="rollbackConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-1">
                <h6 class="modal-title fw-bold text-danger"><i class="fas fa-rotate-left me-1.5"></i>{{ __('Konfirmasi Rollback Commit') }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <form action="{{ route('admin.system.health.rollback') }}" method="POST">
                @csrf
                <input type="hidden" name="active_tab" value="pembaruan">
                <div class="modal-body pt-2">
                    <p class="small text-secondary mb-3">
                        {{ __('Kode proyek akan dikembalikan ke commit sebelumnya (:commit), lalu migrasi & restart service akan dijalankan.', ['commit' => $gitInfo['rollback_short'] ?: '-']) }}
                    </p>
                    <label class="form-label small fw-semibold text-body">{{ __('Masukkan Kata Sandi Admin Anda') }}</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-light rounded-pill px-3" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold">{{ __('Ya, Jalankan Rollback') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
