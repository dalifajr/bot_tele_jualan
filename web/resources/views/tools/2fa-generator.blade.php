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

{{-- Navigation Tabs: Single vs Batch --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-2">
        <ul class="nav nav-pills nav-fill" id="generatorTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold py-2 rounded-pill" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab">
                    <i class="fas fa-key me-2"></i>{{ __('Generator Tunggal') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-2 rounded-pill" id="batch-tab" data-bs-toggle="tab" data-bs-target="#batch" type="button" role="tab">
                    <i class="fas fa-list-check me-2"></i>{{ __('Generator Massal (Multi-Akun)') }}
                </button>
            </li>
        </ul>
    </div>
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

                        {{-- Copy Button --}}
                        <div class="mt-2 w-100" style="max-width: 280px;">
                            <button type="button" id="btn-copy-single" class="btn btn-primary rounded-pill w-100 py-2.5 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" onclick="copySingleTotpCode()">
                                <i class="fas fa-copy"></i>
                                <span id="btn-copy-text">{{ __('Salin Kode (Copy)') }}</span>
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
    });
</script>
@endpush
