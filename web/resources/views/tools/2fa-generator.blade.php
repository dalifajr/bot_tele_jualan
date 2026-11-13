@extends('layouts.app')

@section('title', __('Generator Kode 2FA'))
@section('page_subtitle', __('Tool'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-shield-alt text-success me-2"></i>{{ __('Generator Kode 2FA (TOTP)') }}</h4>
        <p class="text-muted mb-0">{{ __('Hasilkan kode verifikasi 2 langkah secara real-time dan aman langsung di browser') }}</p>
    </div>
</div>

{{-- Navigation Tabs: Single vs Batch vs Saved Accounts (Android 17 / M3 Segmented Tabs) --}}
<div class="m3-segmented-tabs-wrapper mb-4">
    <ul class="nav nav-pills m3-segmented-tabs" id="generatorTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active m3-segmented-btn" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-selected="true">
                <i class="fas fa-key me-1.5"></i>
                <span class="d-none d-sm-inline">{{ __('Generator Tunggal') }}</span>
                <span class="d-inline d-sm-none">{{ __('Tunggal') }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link m3-segmented-btn" id="batch-tab" data-bs-toggle="tab" data-bs-target="#batch" type="button" role="tab" aria-selected="false">
                <i class="fas fa-list-check me-1.5"></i>
                <span class="d-none d-sm-inline">{{ __('Generator Massal') }}</span>
                <span class="d-inline d-sm-none">{{ __('Massal') }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link m3-segmented-btn" id="accounts-tab" data-bs-toggle="tab" data-bs-target="#accounts" type="button" role="tab" aria-selected="false">
                <i class="fas fa-user-shield me-1.5"></i>
                <span class="d-none d-sm-inline">{{ __('Akun Tersimpan') }}</span>
                <span class="d-inline d-sm-none">{{ __('Tersimpan') }}</span>
                <span class="badge m3-tab-counter ms-1" id="saved-accounts-count-badge">{{ isset($savedAccounts) ? $savedAccounts->count() : 0 }}</span>
            </button>
        </li>
    </ul>
</div>

<div class="tab-content" id="generatorTabsContent">
    {{-- TAB 1: SINGLE GENERATOR --}}
    <div class="tab-pane fade show active" id="single" role="tabpanel">
        <div class="row g-4">
            {{-- Input Form (Order 2 on Mobile, Order 1 on Desktop) --}}
            <div class="col-lg-6 order-2 order-lg-1">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">{{ __('Masukkan 2FA Secret Key') }}</h5>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">{{ __('Secret Key atau Link otpauth://') }}</label>
                            <div class="input-group">
                                <input type="text" id="single-secret-input" class="form-control form-control-lg font-monospace" placeholder="Contoh: JBSWY3DPEHPK3PXP" autocomplete="off" spellcheck="false">
                                <button class="btn btn-outline-secondary" type="button" onclick="pasteSecretInput()" title="{{ __('Tempel dari Clipboard') }}">
                                    <i class="fas fa-paste"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" onclick="clearSecretInput()" title="{{ __('Bersihkan') }}">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="form-text small text-muted mt-2">
                                <i class="fas fa-info-circle me-1"></i>{{ __('Mendukung Base32 dengan spasi, huruf kecil, tanda hubung, atau format URL otpauth://') }}
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <button type="button" class="btn btn-sm btn-light rounded-pill px-3" onclick="fillDemoSecret('JBSWY3DPEHPK3PXP')">
                                <i class="fas fa-magic me-1 text-warning"></i> {{ __('Coba Secret Demo 1') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-light rounded-pill px-3" onclick="fillDemoSecret('HXDMVJECJJWSRB3HWIZR4IFUGFTMXBOZ')">
                                <i class="fas fa-magic me-1 text-info"></i> {{ __('Coba Secret Demo 2') }}
                            </button>
                        </div>

                        <div class="alert alert-info border-0 rounded-3 small mb-0 d-flex align-items-start gap-2">
                            <i class="fas fa-lock fs-5 text-primary flex-shrink-0 mt-1"></i>
                            <div>
                                <strong>{{ __('Privasi & Keamanan Terjamin') }}</strong>
                                <p class="mb-0 text-muted mt-1">{{ __('Kalkulasi kode 2FA dilakukan 100% di browser Anda (client-side) menggunakan algoritma RFC 6238 standar. Secret key Anda tidak pernah dikirim atau disimpan di server.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Display 2FA Card (Order 1 on Mobile, Order 2 on Desktop) --}}
            <div class="col-lg-6 order-1 order-lg-2">
                <div class="card border-0 shadow-sm text-center h-100" style="border-radius: 16px;">
                    <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center">
                        <div class="text-secondary small fw-bold text-uppercase mb-2" style="letter-spacing: 1px;">
                            {{ __('Kode 2FA Saat Ini') }}
                        </div>

                        {{-- Circular Countdown Timer SVG (Responsive 85px-110px) --}}
                        <div class="position-relative d-inline-flex justify-content-center align-items-center my-2 my-md-3">
                            <svg width="90" height="90" viewBox="0 0 120 120" class="totp-svg-timer">
                                <circle cx="60" cy="60" r="45" stroke="#e9ecef" stroke-width="8" fill="none" />
                                <circle id="totp-timer-ring" cx="60" cy="60" r="45" stroke="#0d6efd" stroke-width="8" fill="none"
                                        stroke-linecap="round"
                                        style="stroke-dasharray: 283; stroke-dashoffset: 0; transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 0.8s linear, stroke 0.3s ease;" />
                            </svg>
                            <div class="position-absolute text-center">
                                <span id="totp-seconds-left" class="fw-bold fs-4 text-dark">30s</span>
                            </div>
                        </div>

                        {{-- Generated 6-digit Code (Instant Tap-to-Copy) --}}
                        <div class="my-2 my-md-3 text-center">
                            <div id="single-code-display" class="font-monospace fw-bolder text-primary display-4 user-select-all py-1 px-3 rounded-4 d-inline-block" role="button" onclick="copySingleTotpCode()" title="{{ __('Ketuk kode untuk menyalin cepat') }}" style="letter-spacing: 6px; cursor: pointer;">
                                ------
                            </div>
                            <div class="mt-1">
                                <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1 small" style="font-size: 0.72rem;">
                                    <i class="fas fa-hand-pointer me-1"></i>{{ __('Ketuk kode untuk salin cepat') }}
                                </span>
                            </div>
                            <div id="single-status-msg" class="small text-muted mt-2">
                                {{ __('Masukkan secret key untuk menghasilkan kode') }}
                            </div>
                        </div>

                        {{-- Copy & Save Action Buttons --}}
                        <div class="mt-2 w-100 d-flex flex-column gap-2" style="max-width: 280px;">
                            <button type="button" id="btn-copy-single" class="btn btn-primary rounded-pill w-100 py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" onclick="copySingleTotpCode()">
                                <i class="fas fa-copy"></i>
                                <span id="btn-copy-text">{{ __('Salin Kode (Copy)') }}</span>
                            </button>
                            <button type="button" id="btn-save-single" class="btn btn-outline-success rounded-pill w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2" onclick="promptSaveCurrentSecret()">
                                <i class="fas fa-bookmark"></i>
                                <span>{{ __('Simpan Akun F2A') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 2: BATCH / MULTI-ACCOUNT GENERATOR --}}
    <div class="tab-pane fade" id="batch" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-2">{{ __('Generator Kode 2FA Massal') }}</h5>
                <p class="text-muted small mb-3">{{ __('Tempel daftar akun atau kumpulan secret key untuk menghasilkan kode 2FA banyak akun sekaligus secara real-time.') }}</p>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">{{ __('Data Akun (1 baris per akun)') }}</label>
                    <textarea id="batch-input" class="form-control font-monospace" rows="6" placeholder="Contoh format:&#10;user1@gmail.com|pass123|JBSWY3DPEHPK3PXP&#10;user2@gmail.com|pass456|HXDMVJECJJWSRB3HWIZR4IFUGFTMXBOZ&#10;F2A: JBSWY3DPEHPK3PXP"></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="processBatchInput()">
                        <i class="fas fa-bolt me-2"></i>{{ __('Proses & Hasilkan Kode') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" onclick="document.getElementById('batch-input').value=''; document.getElementById('batch-results-container').classList.add('d-none');">
                        <i class="fas fa-trash me-1"></i>{{ __('Bersihkan') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Batch Results Table --}}
        <div id="batch-results-container" class="card border-0 shadow-sm d-none" style="border-radius: 16px;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="fas fa-list-check text-success me-2"></i>{{ __('Hasil Generator Massal (') }}<span id="batch-count">0</span>)</h6>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5" id="batch-timer-badge">30s</span>
                </div>

                {{-- Desktop Table View --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom">
                                <th style="width: 50px;">#</th>
                                <th>{{ __('Kredensial / Baris Asli') }}</th>
                                <th>{{ __('Secret Key') }}</th>
                                <th style="width: 160px;">{{ __('Kode 2FA') }}</th>
                                <th class="text-end" style="width: 100px;">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody id="batch-table-body"></tbody>
                    </table>
                </div>

                {{-- Mobile Batch Cards View --}}
                <div class="d-md-none d-flex flex-column gap-2.5" id="batch-cards-mobile"></div>
            </div>
        </div>
    </div>

    {{-- TAB 3: SAVED ACCOUNTS --}}
    <div class="tab-pane fade" id="accounts" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="fas fa-user-shield text-primary me-2"></i>{{ __('Daftar Akun 2FA Tersimpan') }}</h5>
                        <p class="text-muted small mb-0">{{ __('Pantau kode verifikasi realtime yang tersinkronisasi. Semua kode diperbarui otomatis.') }}</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="max-width: 250px;">
                            <span class="input-group-text bg-light border-end-0 rounded-start-pill"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="search-saved-accounts" class="form-control bg-light border-start-0 rounded-end-pill" placeholder="{{ __('Cari akun...') }}" oninput="filterSavedAccounts()">
                        </div>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-bold d-flex align-items-center gap-1.5 flex-shrink-0" onclick="promptAddNewAccount()">
                            <i class="fas fa-plus"></i>
                            <span>{{ __('Tambah Akun') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Container for Accounts Cards --}}
        <div id="saved-accounts-container">
            @if(!isset($savedAccounts) || $savedAccounts->isEmpty())
                <div id="saved-accounts-empty" class="card border-0 shadow-sm text-center py-5 px-3" style="border-radius: 16px;">
                    <div class="card-body">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3" style="width: 72px; height: 72px;">
                            <i class="fas fa-shield-cat fs-2"></i>
                        </div>
                        <h5 class="fw-bold mb-2">{{ __('Belum Ada Akun Tersimpan') }}</h5>
                        <p class="text-muted small mx-auto mb-4" style="max-width: 440px;">
                            {{ __('Simpan secret key dari menu Generator Tunggal atau klik tombol Tambah Akun di atas agar Anda tidak perlu memasukkan ulang secret key setiap kali membutuhkan kode 2FA.') }}
                        </p>
                        <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" onclick="promptAddNewAccount()">
                            <i class="fas fa-plus-circle me-1.5"></i>{{ __('Simpan Akun Pertama') }}
                        </button>
                    </div>
                </div>
            @else
                <div class="row g-3" id="saved-accounts-grid">
                    @foreach($savedAccounts as $account)
                        <div class="col-12 col-md-6 col-xl-4 saved-account-item" id="account-card-{{ $account->id }}" data-account-id="{{ $account->id }}" data-name="{{ strtolower($account->name) }}" data-issuer="{{ strtolower($account->issuer ?? '') }}">
                            <div class="card border-0 shadow-sm h-100" style="border-radius: 16px; transition: transform 0.2s, box-shadow 0.2s;" data-totp-container data-totp-secret="{{ $account->secret }}">
                                <div class="card-body p-3.5 d-flex flex-column justify-content-between">
                                    {{-- Card Header: Avatar, Name, Issuer, Actions --}}
                                    <div>
                                        <div class="d-flex justify-content-between align-items-start mb-2.5">
                                            <div class="d-flex align-items-center gap-2.5 overflow-hidden">
                                                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0 fw-bold shadow-sm" style="width: 42px; height: 42px; font-size: 1.15rem;">
                                                    {{ strtoupper(substr($account->name, 0, 1)) }}
                                                </div>
                                                <div class="overflow-hidden">
                                                    <h6 class="fw-bold mb-0 text-truncate text-dark account-name-label" title="{{ $account->name }}">{{ $account->name }}</h6>
                                                    @if($account->issuer)
                                                        <span class="badge bg-light text-secondary border small py-0.5 px-2 mt-0.5">{{ $account->issuer }}</span>
                                                    @else
                                                        <span class="text-muted small" style="font-size: 0.75rem;">{{ __('Akun 2FA') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light rounded-circle text-muted" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Pilihan') }}" style="width: 32px; height: 32px; padding: 0;">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                                                    <li>
                                                        <button class="dropdown-item small py-2 d-flex align-items-center gap-2" type="button" onclick="promptEditAccountName({{ $account->id }}, '{{ addslashes($account->name) }}')">
                                                            <i class="fas fa-edit text-warning"></i>{{ __('Ubah Nama') }}
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item small py-2 d-flex align-items-center gap-2" type="button" onclick="copySecretKey('{{ $account->secret }}')">
                                                            <i class="fas fa-key text-info"></i>{{ __('Salin Secret Key') }}
                                                        </button>
                                                    </li>
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <button class="dropdown-item small py-2 text-danger d-flex align-items-center gap-2" type="button" onclick="confirmDeleteAccount({{ $account->id }}, '{{ addslashes($account->name) }}')">
                                                            <i class="fas fa-trash-alt"></i>{{ __('Hapus Akun') }}
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        {{-- OTP Code Display with Tap-to-Copy --}}
                                        <div class="bg-light bg-opacity-75 rounded-4 p-3 my-2 text-center position-relative border border-light">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px;">{{ __('Kode Saat Ini') }}</span>
                                                <span class="badge bg-success text-white rounded-pill px-2.5 py-0.5 totp-timer fw-bold small">30s</span>
                                            </div>
                                            <div class="font-monospace fw-bolder fs-2 text-primary totp-code user-select-all py-1" role="button" onclick="copyTotpCode(this, '{{ $account->secret }}')" title="{{ __('Ketuk untuk salin') }}" style="letter-spacing: 5px; cursor: pointer;">
                                                ------
                                            </div>
                                            <div class="text-muted small" style="font-size: 0.72rem;">
                                                <i class="fas fa-hand-pointer me-1"></i>{{ __('Ketuk kode untuk salin') }}
                                            </div>
                                        </div>

                                        {{-- Secret Key Preview (Masked) --}}
                                        <div class="d-flex align-items-center justify-content-between px-1 mb-3">
                                            <div class="small font-monospace text-muted text-truncate me-2" style="font-size: 0.75rem;">
                                                <span class="text-secondary fw-semibold">Secret:</span>
                                                <span class="secret-masked font-monospace">•••• •••• {{ substr($account->secret, -4) }}</span>
                                            </div>
                                            <button type="button" class="btn btn-link btn-sm text-secondary p-0 text-decoration-none small" style="font-size: 0.75rem;" onclick="copySecretKey('{{ $account->secret }}')" title="{{ __('Salin Secret Key') }}">
                                                <i class="fas fa-copy me-1"></i>{{ __('Salin Key') }}
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Card Footer Action: Copy Button --}}
                                    <div class="pt-2 border-top">
                                        <button type="button" class="btn btn-primary rounded-pill w-100 py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" onclick="copyTotpCode(this, '{{ $account->secret }}')">
                                            <i class="fas fa-copy"></i>
                                            <span>{{ __('Salin Kode 2FA') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div id="no-search-results" class="card border-0 shadow-sm text-center py-4 px-3 d-none" style="border-radius: 16px;">
                    <p class="text-muted mb-0"><i class="fas fa-search me-2"></i>{{ __('Tidak ada akun tersimpan yang cocok dengan pencarian.') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const secretInput = document.getElementById('single-secret-input');
        const codeDisplay = document.getElementById('single-code-display');
        const statusMsg = document.getElementById('single-status-msg');

        function updateSingleCode() {
            if (!secretInput) return;
            const val = secretInput.value.trim();
            if (!val) {
                codeDisplay.textContent = '------';
                codeDisplay.classList.add('text-muted');
                codeDisplay.classList.remove('text-primary');
                statusMsg.textContent = '{{ __("Masukkan secret key untuk menghasilkan kode") }}';
                return;
            }

            if (typeof TotpEngine !== 'undefined') {
                const code = TotpEngine.compute(val);
                if (code) {
                    codeDisplay.textContent = code.slice(0, 3) + ' ' + code.slice(3);
                    codeDisplay.classList.remove('text-muted');
                    codeDisplay.classList.add('text-primary');
                    statusMsg.textContent = '{{ __("Kode berlaku selama 30 detik dan diperbarui otomatis") }}';
                } else {
                    codeDisplay.textContent = '------';
                    codeDisplay.classList.add('text-muted');
                    codeDisplay.classList.remove('text-primary');
                    statusMsg.textContent = '{{ __("Format Secret Key tidak valid (harus Base32)") }}';
                }
            }
        }

        if (secretInput) {
            secretInput.addEventListener('input', updateSingleCode);
            // Also tick every second to sync with global timer
            setInterval(updateSingleCode, 1000);
        }

        window.copySingleTotpCode = function () {
            const rawText = codeDisplay.textContent.replace(/\s+/g, '');
            if (!rawText || rawText === '------') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Perhatian',
                        text: 'Silakan masukkan secret key 2FA yang valid terlebih dahulu.'
                    });
                } else {
                    alert('Silakan masukkan secret key 2FA terlebih dahulu.');
                }
                return;
            }

            navigator.clipboard.writeText(rawText).then(() => {
                const copyBtn = document.getElementById('btn-copy-single');
                const copyText = document.getElementById('btn-copy-text');
                if (copyBtn && copyText) {
                    const originalText = copyText.textContent;
                    copyText.textContent = '{{ __("Tersalin!") }}';
                    copyBtn.classList.remove('btn-primary');
                    copyBtn.classList.add('btn-success');
                    setTimeout(() => {
                        copyText.textContent = originalText;
                        copyBtn.classList.remove('btn-success');
                        copyBtn.classList.add('btn-primary');
                    }, 1500);
                }

                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Kode ' + rawText + ' disalin!'
                    });
                }
            });
        };

        window.fillDemoSecret = function (secret) {
            if (secretInput) {
                secretInput.value = secret;
                updateSingleCode();
            }
        };

        window.clearSecretInput = function () {
            if (secretInput) {
                secretInput.value = '';
                updateSingleCode();
            }
        };

        window.pasteSecretInput = function () {
            navigator.clipboard.readText().then(text => {
                if (secretInput && text) {
                    secretInput.value = text.trim();
                    updateSingleCode();
                }
            }).catch(() => {
                alert('Tidak dapat mengakses clipboard browser.');
            });
        };

        // Batch processing logic
        let batchItems = [];
        window.processBatchInput = function () {
            const batchInput = document.getElementById('batch-input');
            const container = document.getElementById('batch-results-container');
            const tbody = document.getElementById('batch-table-body');
            const countSpan = document.getElementById('batch-count');

            if (!batchInput || !container || !tbody) return;

            const text = batchInput.value.trim();
            if (!text) {
                container.classList.add('d-none');
                return;
            }

            const lines = text.split(/\r\n|\n|\r/);
            batchItems = [];
            let validIndex = 0;

            lines.forEach((line) => {
                const trimmed = line.trim();
                if (!trimmed) return;

                validIndex++;
                let secret = null;

                // Match explicit label (F2A, 2FA, TOTP)
                const kvMatch = trimmed.match(/(?:f2a|2fa|totp|authenticator|two[\s_-]*factor|secret)\s*[:=]\s*([A-Za-z2-7\s=-]{8,64})/i);
                if (kvMatch) {
                    secret = kvMatch[1].replace(/[^A-Za-z2-7]/g, '').toUpperCase();
                } else if (trimmed.includes('|') || trimmed.includes(':')) {
                    const delimiter = trimmed.includes('|') ? '|' : ':';
                    const parts = trimmed.split(delimiter);
                    parts.forEach(part => {
                        const candidate = part.trim();
                        if (!candidate.includes('@') && candidate.length >= 16 && candidate.length <= 64) {
                            const clean = candidate.replace(/[^A-Za-z2-7]/g, '').toUpperCase();
                            if (clean.length === candidate.length) {
                                secret = clean;
                            }
                        }
                    });
                } else {
                    const candidate = trimmed.replace(/[^A-Za-z2-7]/g, '').toUpperCase();
                    if (candidate.length >= 8 && candidate.length <= 64) {
                        secret = candidate;
                    }
                }

                batchItems.push({
                    index: validIndex,
                    raw: trimmed,
                    secret: secret
                });
            });

            if (batchItems.length > 0) {
                countSpan.textContent = batchItems.length;
                renderBatchTable();
                container.classList.remove('d-none');
            } else {
                container.classList.add('d-none');
            }
        };

        function renderBatchTable() {
            const tbody = document.getElementById('batch-table-body');
            const timerBadge = document.getElementById('batch-timer-badge');
            if (!tbody || batchItems.length === 0) return;

            const now = TotpEngine.getNow();
            const remaining = 30 - (now % 30);
            if (timerBadge) {
                timerBadge.textContent = remaining + 's';
                if (remaining <= 5) {
                    timerBadge.className = 'badge bg-danger text-white rounded-pill px-3 py-1.5';
                } else {
                    timerBadge.className = 'badge bg-primary-subtle text-primary rounded-pill px-3 py-1.5';
                }
            }

            let html = '';
            let mobileHtml = '';
            batchItems.forEach(item => {
                let codeHtml = '<span class="badge bg-secondary-subtle text-secondary">-</span>';
                let actionHtml = '-';
                let computedCode = null;

                if (item.secret && typeof TotpEngine !== 'undefined') {
                    const code = TotpEngine.compute(item.secret, now);
                    if (code) {
                        computedCode = code;
                        codeHtml = `<span class="badge bg-success-subtle text-success fs-6 font-monospace px-2.5 py-1 border border-success-subtle" style="letter-spacing: 1px;">${code}</span>`;
                        actionHtml = `
                            <button type="button" class="btn btn-sm btn-light text-primary rounded-circle" title="Salin Kode" onclick="copySpecificCode('${code}', this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        `;
                    }
                }

                html += `
                    <tr>
                        <td class="text-muted small">${item.index}</td>
                        <td class="font-monospace small text-break" style="max-width: 320px;">${escapeHtml(item.raw)}</td>
                        <td class="font-monospace small text-secondary">${item.secret ? item.secret.substring(0, 16) + (item.secret.length > 16 ? '...' : '') : '<span class="text-muted">Tidak terdeteksi</span>'}</td>
                        <td>${codeHtml}</td>
                        <td class="text-end">${actionHtml}</td>
                    </tr>
                `;

                mobileHtml += `
                    <div class="card border border-subtle shadow-sm rounded-4 p-3 bg-light bg-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <span class="badge bg-light text-secondary border rounded-pill">#${item.index}</span>
                            ${item.secret ? `<span class="badge bg-primary-subtle text-primary font-monospace">${item.secret.substring(0, 10)}...</span>` : `<span class="badge bg-danger-subtle text-danger">No Secret</span>`}
                        </div>
                        <div class="small font-monospace text-muted text-break mb-2">${escapeHtml(item.raw)}</div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bolder fs-5 font-monospace ${computedCode ? 'text-success' : 'text-muted'}" ${computedCode ? `role="button" onclick="copySpecificCode('${computedCode}', this)" title="Ketuk untuk salin"` : ''}>${computedCode ? computedCode : '------'}</span>
                            ${computedCode ? `
                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold shadow-sm d-flex align-items-center gap-1.5" onclick="copySpecificCode('${computedCode}', this)">
                                    <i class="fas fa-copy"></i>
                                    <span>Salin</span>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            tbody.innerHTML = html;
            const mobileContainer = document.getElementById('batch-cards-mobile');
            if (mobileContainer) {
                mobileContainer.innerHTML = mobileHtml;
            }
        }

        // Periodically refresh batch table
        setInterval(function () {
            if (batchItems.length > 0 && !document.getElementById('batch-results-container').classList.contains('d-none')) {
                renderBatchTable();
            }
        }, 1000);

        window.copySpecificCode = function (code, btn) {
            navigator.clipboard.writeText(code).then(() => {
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-check text-success';
                    setTimeout(() => { icon.className = 'fas fa-copy'; }, 1500);
                }
            });
        };

        function escapeHtml(string) {
            return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // ==========================================
        // FITUR PENYIMPANAN AKUN 2FA (SAVED ACCOUNTS)
        // ==========================================

        // Auto open accounts tab if hash is present
        if (window.location.hash === '#accounts') {
            const accountsTabBtn = document.getElementById('accounts-tab');
            if (accountsTabBtn) {
                const tab = new bootstrap.Tab(accountsTabBtn);
                tab.show();
            }
        }

        // 1. Simpan secret key dari Generator Tunggal
        window.promptSaveCurrentSecret = function () {
            const secretInput = document.getElementById('single-secret-input');
            const rawVal = secretInput ? secretInput.value.trim() : '';

            if (!rawVal) {
                Swal.fire({
                    icon: 'warning',
                    title: '{{ __("Secret Key Belum Diisi") }}',
                    text: '{{ __("Silakan masukkan Secret Key 2FA terlebih dahulu sebelum menyimpan.") }}',
                    confirmButtonColor: '#0d6efd',
                    confirmButtonText: 'OK'
                });
                return;
            }

            let defaultName = '';
            let defaultIssuer = '';
            let cleanSecret = rawVal;

            // Cek apakah format otpauth URL
            if (rawVal.startsWith('otpauth://')) {
                try {
                    const url = new URL(rawVal);
                    const label = decodeURIComponent(url.pathname.replace(/^\/\/totp\//i, '').replace(/^\/totp\//i, ''));
                    if (label.includes(':')) {
                        const parts = label.split(':');
                        defaultIssuer = parts[0].trim();
                        defaultName = parts.slice(1).join(':').trim();
                    } else {
                        defaultName = label;
                    }
                    if (url.searchParams.get('issuer')) {
                        defaultIssuer = url.searchParams.get('issuer');
                    }
                    if (url.searchParams.get('secret')) {
                        cleanSecret = url.searchParams.get('secret');
                    }
                } catch (e) {}
            }

            cleanSecret = cleanSecret.toUpperCase().replace(/[^A-Z2-7]/g, '');

            if (typeof TotpEngine !== 'undefined') {
                const testCode = TotpEngine.compute(cleanSecret);
                if (!testCode) {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __("Secret Key Tidak Valid") }}',
                        text: '{{ __("Format secret key tidak sesuai standar Base32. Periksa kembali secret key Anda.") }}',
                        confirmButtonColor: '#0d6efd',
                        confirmButtonText: 'Tutup'
                    });
                    return;
                }
            }

            Swal.fire({
                title: '{{ __("Simpan Akun 2FA") }}',
                html: `
                    <div class="text-start mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">{{ __("Nama Akun") }} <span class="text-danger">*</span></label>
                        <input type="text" id="swal-acc-name" class="form-control" placeholder="{{ __("Contoh: Akun GitHub, Instagram Toko, dll.") }}" value="${escapeHtml(defaultName)}">
                    </div>
                    <div class="text-start mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">{{ __("Penerbit / Layanan (Opsional)") }}</label>
                        <input type="text" id="swal-acc-issuer" class="form-control" placeholder="{{ __("Contoh: GitHub, Google, Facebook") }}" value="${escapeHtml(defaultIssuer)}">
                    </div>
                    <div class="text-start">
                        <label class="form-label small fw-bold text-muted mb-1">{{ __("Secret Key") }}</label>
                        <input type="text" id="swal-acc-secret" class="form-control font-monospace bg-light" readonly value="${cleanSecret}">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Dismiss',
                focusConfirm: false,
                didOpen: () => {
                    const input = document.getElementById('swal-acc-name');
                    if (input) input.focus();
                },
                preConfirm: () => {
                    const name = document.getElementById('swal-acc-name').value.trim();
                    const issuer = document.getElementById('swal-acc-issuer').value.trim();
                    const secret = document.getElementById('swal-acc-secret').value.trim();
                    if (!name) {
                        Swal.showValidationMessage('{{ __("Nama akun wajib diisi!") }}');
                        return false;
                    }
                    return { name, issuer, secret };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    submitSaveAccount(result.value);
                }
            });
        };

        // 2. Tambah akun manual dari tab Akun Tersimpan
        window.promptAddNewAccount = function () {
            Swal.fire({
                title: '{{ __("Tambah Akun 2FA Baru") }}',
                html: `
                    <div class="text-start mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">{{ __("Nama Akun") }} <span class="text-danger">*</span></label>
                        <input type="text" id="swal-add-name" class="form-control" placeholder="{{ __("Contoh: Akun Email Kerja") }}">
                    </div>
                    <div class="text-start mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">{{ __("Secret Key atau Link otpauth://") }} <span class="text-danger">*</span></label>
                        <input type="text" id="swal-add-secret" class="form-control font-monospace" placeholder="JBSWY3DPEHPK3PXP">
                    </div>
                    <div class="text-start">
                        <label class="form-label small fw-bold text-muted mb-1">{{ __("Penerbit / Layanan (Opsional)") }}</label>
                        <input type="text" id="swal-add-issuer" class="form-control" placeholder="{{ __("Contoh: Google, Tokopedia, dll.") }}">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Dismiss',
                focusConfirm: false,
                didOpen: () => {
                    const input = document.getElementById('swal-add-name');
                    if (input) input.focus();
                },
                preConfirm: () => {
                    const name = document.getElementById('swal-add-name').value.trim();
                    let rawSecret = document.getElementById('swal-add-secret').value.trim();
                    const issuer = document.getElementById('swal-add-issuer').value.trim();

                    if (!name) {
                        Swal.showValidationMessage('{{ __("Nama akun wajib diisi!") }}');
                        return false;
                    }
                    if (!rawSecret) {
                        Swal.showValidationMessage('{{ __("Secret key wajib diisi!") }}');
                        return false;
                    }

                    // Ekstrak jika otpauth
                    if (rawSecret.startsWith('otpauth://')) {
                        try {
                            const url = new URL(rawSecret);
                            if (url.searchParams.get('secret')) {
                                rawSecret = url.searchParams.get('secret');
                            }
                        } catch (e) {}
                    }

                    const clean = rawSecret.toUpperCase().replace(/[^A-Z2-7]/g, '');
                    if (typeof TotpEngine !== 'undefined' && !TotpEngine.compute(clean)) {
                        Swal.showValidationMessage('{{ __("Secret key tidak valid (harus format Base32)!") }}');
                        return false;
                    }

                    return { name, issuer, secret: clean };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    submitSaveAccount(result.value);
                }
            });
        };

        // 3. Kirim AJAX simpan akun
        function submitSaveAccount(data) {
            Swal.fire({
                title: '{{ __("Menyimpan...") }}',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('{{ route("tools.2fa-generator.accounts.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '{{ __("Berhasil Disimpan!") }}',
                        text: res.message || '{{ __("Akun F2A berhasil disimpan.") }}',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.hash = 'accounts';
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '{{ __("Gagal Menyimpan") }}',
                        text: res.message || '{{ __("Terjadi kesalahan saat menyimpan akun.") }}',
                        confirmButtonColor: '#0d6efd'
                    });
                }
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: '{{ __("Terjadi Kesalahan") }}',
                    text: err.message || '{{ __("Gagal menghubungi server.") }}',
                    confirmButtonColor: '#0d6efd'
                });
            });
        }

        // 4. Ubah nama akun tersimpan
        window.promptEditAccountName = function (id, currentName) {
            Swal.fire({
                title: '{{ __("Ubah Nama Akun") }}',
                input: 'text',
                inputValue: currentName,
                inputAttributes: {
                    maxlength: 100,
                    placeholder: '{{ __("Masukkan nama akun baru") }}'
                },
                showCancelButton: true,
                confirmButtonText: 'Confirm',
                cancelButtonText: 'Dismiss',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return '{{ __("Nama akun tidak boleh kosong!") }}';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const newName = result.value.trim();
                    fetch('/tools/2fa-generator/accounts/' + id, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ name: newName })
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            const card = document.getElementById('account-card-' + id);
                            if (card) {
                                const nameLabel = card.querySelector('.account-name-label');
                                if (nameLabel) {
                                    nameLabel.textContent = newName;
                                    nameLabel.title = newName;
                                }
                                card.setAttribute('data-name', newName.toLowerCase());
                            }
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 2000,
                                timerProgressBar: true
                            });
                            Toast.fire({
                                icon: 'success',
                                title: res.message || '{{ __("Nama akun diperbarui!") }}'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '{{ __("Gagal") }}',
                                text: res.message || '{{ __("Gagal memperbarui nama akun.") }}'
                            });
                        }
                    })
                    .catch(err => {
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __("Error") }}',
                            text: err.message
                        });
                    });
                }
            });
        };

        // 5. Hapus akun tersimpan
        window.confirmDeleteAccount = function (id, name) {
            Swal.fire({
                title: '{{ __("Hapus Akun Ini?") }}',
                text: '{{ __("Apakah Anda yakin ingin menghapus akun") }} "' + name + '"? {{ __("Tindakan ini tidak dapat dibatalkan.") }}',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: '{{ __("Konfirmasi") }}',
                cancelButtonText: '{{ __("Batal") }}',
                reverseButtons: false,
                focusConfirm: false
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('/tools/2fa-generator/accounts/' + id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            const card = document.getElementById('account-card-' + id);
                            if (card) {
                                card.style.transition = 'all 0.3s ease';
                                card.style.opacity = '0';
                                card.style.transform = 'scale(0.9)';
                                setTimeout(() => {
                                    card.remove();
                                    const grid = document.getElementById('saved-accounts-grid');
                                    const items = document.querySelectorAll('.saved-account-item');
                                    const badge = document.getElementById('saved-accounts-count-badge');
                                    if (badge) badge.textContent = items.length;

                                    if (items.length === 0) {
                                        window.location.hash = 'accounts';
                                        window.location.reload();
                                    }
                                }, 300);
                            }
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 2000,
                                timerProgressBar: true
                            });
                            Toast.fire({
                                icon: 'success',
                                title: res.message || '{{ __("Akun berhasil dihapus!") }}'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '{{ __("Gagal") }}',
                                text: res.message || '{{ __("Gagal menghapus akun.") }}'
                            });
                        }
                    })
                    .catch(err => {
                        Swal.fire({
                            icon: 'error',
                            title: '{{ __("Error") }}',
                            text: err.message
                        });
                    });
                }
            });
        };

        // 6. Salin secret key ke clipboard
        window.copySecretKey = function (secret) {
            navigator.clipboard.writeText(secret).then(() => {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: '{{ __("Secret Key disalin ke clipboard!") }}'
                });
            });
        };

        // 7. Filter / pencarian live akun tersimpan
        window.filterSavedAccounts = function () {
            const searchInput = document.getElementById('search-saved-accounts');
            if (!searchInput) return;
            const query = searchInput.value.trim().toLowerCase();
            const items = document.querySelectorAll('.saved-account-item');
            const noResults = document.getElementById('no-search-results');
            let visibleCount = 0;

            items.forEach(item => {
                const name = item.getAttribute('data-name') || '';
                const issuer = item.getAttribute('data-issuer') || '';
                if (name.includes(query) || issuer.includes(query)) {
                    item.classList.remove('d-none');
                    visibleCount++;
                } else {
                    item.classList.add('d-none');
                }
            });

            if (noResults) {
                if (visibleCount === 0 && items.length > 0) {
                    noResults.classList.remove('d-none');
                } else {
                    noResults.classList.add('d-none');
                }
            }
        };
    });
</script>
@endpush
