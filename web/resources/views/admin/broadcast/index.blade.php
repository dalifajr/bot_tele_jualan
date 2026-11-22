@extends('layouts.app')

@section('title', __('Broadcast Pesan'))
@section('page_subtitle', __('Broadcast'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Broadcast Telegram') }}</h4>
        <p class="text-muted mb-0">{{ __('Kirim pesan massal ke seluruh pelanggan') }}</p>
    </div>
    <a href="#broadcastHistorySection" class="btn btn-outline-primary rounded-pill fw-bold">
        <i class="fas fa-history me-2"></i>{{ __('Riwayat Broadcast') }}
    </a>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form id="broadcastForm" class="no-loader" onsubmit="event.preventDefault(); startBroadcast();">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small">{{ __('Pesan Broadcast (Mendukung Format HTML)') }}</label>
                        <textarea id="broadcastMessage" name="message" class="form-control" rows="8" placeholder="<b>{{ __('Halo Pelanggan Setia!') }}</b><br>{{ __('Dapatkan promo menarik hari ini...') }}"></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small">{{ __('Attachment Media (Opsional)') }}</label>
                        <input type="file" id="mediaFile" name="media_file" class="form-control" accept="image/*,video/mp4,.pdf,.doc,.docx,.zip">
                        <div class="form-text">{{ __('Maksimal 50MB. Format didukung: Foto (JPG/PNG), Video (MP4), Dokumen (PDF/DOC/ZIP).') }}</div>
                    </div>

                    <div class="p-3 rounded-4 border mb-4" style="background: var(--surface-container, var(--bs-tertiary-bg)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="fw-bold small text-body">
                                    <i class="fas fa-eye text-primary me-1"></i>{{ __('Pelacakan Laporan Dibaca (Web & Telegram)') }}
                                </div>
                                <div class="text-muted" style="font-size: 0.78rem;">
                                    {{ __('Notifikasi website terlacak otomatis. Kalkulasi bertahap dijalankan pada 10 menit, 30 menit, 1 jam, dan 1 hari setelah broadcast selesai.') }}
                                </div>
                            </div>
                            <div class="form-check form-switch m-0 flex-shrink-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="enableReadButton" name="enable_read_button" value="1" checked>
                            </div>
                        </div>
                        <label class="form-check-label small fw-semibold text-body d-block mb-3" for="enableReadButton" style="cursor: pointer;">
                            {{ __('Sertakan tombol "✅ Tandai Dibaca" pada pesan Telegram') }}
                        </label>

                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label small text-muted mb-1">{{ __('Label Tombol Link CTA (Opsional)') }}</label>
                                <input type="text" id="ctaLabel" name="cta_label" class="form-control form-control-sm" maxlength="80" placeholder="{{ __('Contoh: Lihat Promo Sekarang') }}">
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small text-muted mb-1">{{ __('URL Tautan CTA (Opsional, klik dihitung dibaca)') }}</label>
                                <input type="url" id="ctaUrl" name="cta_url" class="form-control form-control-sm" maxlength="500" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar Section (Hidden initially) -->
                    <div id="progressSection" class="d-none mb-4">
                        <h6 class="fw-bold mb-2">{{ __('Progress Pengiriman') }} <span id="progressText" class="text-primary float-end">0/0</span></h6>
                        <div class="progress" style="height: 20px; border-radius: 10px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-secondary">
                            <span><i class="fas fa-history me-1"></i>{{ __('Waktu Berjalan:') }} <span id="elapsedTime" class="fw-bold text-dark">-</span></span>
                            <span><i class="fas fa-hourglass-half me-1"></i>{{ __('Estimasi Selesai (ETA):') }} <span id="etaTime" class="fw-bold text-dark">-</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <div class="d-flex gap-3 small">
                                <span class="text-success"><i class="fas fa-check-circle me-1"></i>{{ __('Berhasil:') }} <span id="successCount" class="fw-bold">0</span></span>
                                <span class="text-danger"><i class="fas fa-times-circle me-1"></i>{{ __('Gagal:') }} <span id="failedCount" class="fw-bold">0</span></span>
                            </div>
                            <button type="button" id="btnCancelBroadcast" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold shadow-sm" onclick="cancelBroadcast()">
                                <i class="fas fa-stop me-1"></i>{{ __('Hentikan Broadcast') }}
                            </button>
                        </div>
                    </div>
                    
                    <div class="alert alert-info border-0 rounded-4 mb-4">
                        <div class="d-flex gap-3">
                            <i class="fas fa-info-circle fs-4 mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1">{{ __('Broadcast Latar Belakang') }}</h6>
                                <p class="mb-0 small text-body">{{ __('Broadcast berjalan sepenuhnya di latar belakang. Anda dapat berpindah halaman atau menutup panel tanpa menghentikan pengiriman pesan. Progress akan tersimpan otomatis.') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" id="btnSend" class="btn btn-primary rounded-pill py-2 fw-bold">
                            <i class="fas fa-paper-plane me-2"></i>{{ __('Mulai Kirim Broadcast') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5" id="broadcastHistorySection">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4"><i class="fas fa-history text-primary me-2"></i>{{ __('Riwayat Broadcast') }}</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="border-0">{{ __('Tanggal') }}</th>
                                <th class="border-0">{{ __('Isi Pesan') }}</th>
                                <th class="border-0">{{ __('Attachment') }}</th>
                                <th class="border-0">{{ __('Status') }}</th>
                                <th class="border-0">{{ __('Terkirim / Gagal') }}</th>
                                <th class="border-0">{{ __('Dibaca / Dilihat') }}</th>
                                <th class="border-0 text-end">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($broadcastHistory as $history)
                            @php
                                $snaps = is_array($history->read_snapshots) ? $history->read_snapshots : [];
                                $lastCp = null;
                                foreach (['24h' => '1 Hari', '1h' => '1 Jam', '30m' => '30m', '10m' => '10m'] as $cpKey => $cpLabel) {
                                    if (isset($snaps[$cpKey])) {
                                        $lastCp = $cpLabel;
                                        break;
                                    }
                                }
                            @endphp
                            <tr id="bc-row-{{ $history->id }}">
                                <td>
                                    <div class="small fw-bold text-body">{{ $history->created_at->format('d M Y') }}</div>
                                    <div class="small text-muted">{{ $history->created_at->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 240px;" title="{{ strip_tags($history->message) }}">
                                        {!! Str::limit(strip_tags($history->message), 50) ?: '<em class="text-muted">' . __('Tidak ada teks') . '</em>' !!}
                                    </div>
                                </td>
                                <td>
                                    @if($history->media_type && $history->media_path)
                                        <a href="{{ Storage::url($history->media_path) }}" target="_blank" class="badge bg-info text-decoration-none">
                                            <i class="fas fa-file-alt me-1"></i>{{ ucfirst($history->media_type) }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($history->status == 'completed')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">{{ __('Selesai') }}</span>
                                    @elseif($history->status == 'failed')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ __('Gagal') }}</span>
                                    @elseif($history->status == 'cancelled')
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">{{ __('Dibatalkan') }}</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">{{ ucfirst($history->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-success fw-bold"><i class="fas fa-check me-1"></i>{{ $history->sent_count }}</span>
                                        <span class="text-muted mx-1">|</span>
                                        <span class="text-danger fw-bold"><i class="fas fa-times me-1"></i>{{ $history->failed_count }}</span>
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ __('dari') }} {{ $history->total_targets }}</div>
                                    </div>
                                </td>
                                <td>
                                    <button type="button"
                                            class="btn btn-sm btn-light border rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1.5"
                                            onclick="openBroadcastAnalytics({{ $history->id }})"
                                            title="{{ __('Lihat Laporan Pembacaan Bertahap') }}">
                                        <i class="fas fa-eye text-primary"></i>
                                        <span class="fw-bold text-body" id="bc-read-count-{{ $history->id }}">{{ (int) $history->read_count }}</span>
                                        <span class="text-muted small" id="bc-read-rate-{{ $history->id }}">({{ $history->read_rate }}%)</span>
                                    </button>
                                    <div class="mt-1">
                                        <span class="badge bg-primary-subtle text-primary rounded-pill" style="font-size: 0.66rem;" id="bc-read-cp-{{ $history->id }}">
                                            @if($lastCp)
                                                <i class="fas fa-clock me-1"></i>{{ __('Tahap:') }} {{ __($lastCp) }}
                                            @elseif($history->status === 'completed')
                                                <i class="fas fa-hourglass-half me-1"></i>{{ __('Menunggu 10m') }}
                                            @else
                                                {{ __('Belum Selesai') }}
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light rounded-pill border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border" style="border-radius: 14px;">
                                            <li>
                                                <button class="dropdown-item py-2" onclick="openBroadcastAnalytics({{ $history->id }})">
                                                    <i class="fas fa-chart-line text-primary me-2"></i>{{ __('Laporan Dibaca') }}
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item py-2" onclick="resendBroadcast({{ $history->id }})">
                                                    <i class="fas fa-redo text-success me-2"></i>{{ __('Resend') }}
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item py-2" onclick="resendWithEdit({{ $history->id }}, `{{ base64_encode($history->message) }}`, '{{ $history->media_path }}', '{{ $history->media_type }}', {{ $history->enable_read_button ? 'true' : 'false' }}, `{{ base64_encode((string) $history->cta_label) }}`, `{{ base64_encode((string) $history->cta_url) }}`)">
                                                    <i class="fas fa-edit text-warning me-2"></i>{{ __('Resend with Edit') }}
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-history fs-3 mb-2 opacity-50"></i>
                                    <p class="mb-0">{{ __('Belum ada riwayat broadcast.') }}</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    {{ $broadcastHistory->fragment('broadcastHistorySection')->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="broadcastAnalyticsModal" tabindex="-1" aria-labelledby="broadcastAnalyticsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border" style="border-radius: 28px; background: var(--surface-container-lowest, var(--bs-body-bg)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div>
                    <h5 class="modal-title fw-bold text-body mb-0" id="broadcastAnalyticsModalLabel">
                        <i class="fas fa-chart-line text-primary me-2"></i>{{ __('Laporan Pembacaan Broadcast') }}
                    </h5>
                    <small class="text-muted" id="bcAnalyticsSubtitle">{{ __('Kalkulasi bertahap 10 menit, 30 menit, 1 jam, dan 1 hari') }}</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3" id="bcAnalyticsBody">
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                    <div class="small">{{ __('Memuat laporan pembacaan...') }}</div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted" id="bcAnalyticsUpdatedAt">-</small>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnRecalculateBc" onclick="recalculateCurrentBroadcast()">
                        <i class="fas fa-rotate-right me-1"></i>{{ __('Hitung Ulang Sekarang') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function __(key) {
    const translations = {
        'Sedang Mengirim (Latar Belakang)...': '{{ __("Sedang Mengirim (Latar Belakang)...") }}',
        'Selesai': '{{ __("Selesai") }}',
        'Menghitung...': '{{ __("Menghitung...") }}',
        'Mempersiapkan...': '{{ __("Mempersiapkan...") }}'
    };
    return translations[key] || key;
}
let pollInterval = null;
let currentJobId = null;

document.addEventListener('DOMContentLoaded', function() {
    checkActiveBroadcast();
});

async function checkActiveBroadcast() {
    try {
        const response = await fetch("{{ route('admin.broadcast.active') }}");
        const data = await response.json();
        
        if (data.status === 'success' && data.has_active) {
            currentJobId = data.job.id;
            setupActiveBroadcastUI(data.job);
            startPolling(data.job.id);
        }
    } catch (e) {
        console.error("Gagal memeriksa broadcast aktif:", e);
    }
}

function formatDuration(seconds) {
    if (seconds <= 0) return '0s';
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    let parts = [];
    if (hrs > 0) parts.push(hrs + 'j');
    if (mins > 0) parts.push(mins + 'm');
    if (secs > 0 || parts.length === 0) parts.push(secs + 's');
    
    return parts.join(' ');
}

function setupActiveBroadcastUI(job) {
    const btnSend = document.getElementById('btnSend');
    const messageTextarea = document.getElementById('broadcastMessage');
    const mediaFileInput = document.getElementById('mediaFile');
    
    messageTextarea.value = job.message;
    messageTextarea.disabled = true;
    if (mediaFileInput) mediaFileInput.disabled = true;
    
    btnSend.disabled = true;
    btnSend.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + __('Sedang Mengirim (Latar Belakang)...');
    
    document.getElementById('progressSection').classList.remove('d-none');
    
    updateProgressUI(job.sent + job.failed, job.total, job.sent, job.failed, job);
}

function updateProgressUI(current, total, success, failed, job) {
    const percentage = total > 0 ? Math.round((current / total) * 100) : 0;
    
    document.getElementById('progressBar').style.width = percentage + '%';
    document.getElementById('progressBar').innerText = percentage + '%';
    document.getElementById('progressText').innerText = `${current}/${total}`;
    document.getElementById('successCount').innerText = success;
    document.getElementById('failedCount').innerText = failed;

    if (job && (job.created_at || job.updated_at)) {
        const startTime = new Date(job.created_at || job.updated_at).getTime();
        const now = new Date().getTime();
        const elapsedSeconds = Math.max(1, Math.round((now - startTime) / 1000));
        
        document.getElementById('elapsedTime').innerText = formatDuration(elapsedSeconds);
        
        const processed = success + failed;
        const remaining = total - processed;
        const speed = processed / elapsedSeconds; // messages per second
        
        if (processed > 0 && remaining > 0 && speed > 0) {
            const etaSeconds = Math.round(remaining / speed);
            document.getElementById('etaTime').innerText = formatDuration(etaSeconds);
        } else if (remaining === 0) {
            document.getElementById('etaTime').innerText = '{{ __('Selesai') }}';
        } else {
            document.getElementById('etaTime').innerText = '{{ __('Menghitung...') }}';
        }
    } else {
        document.getElementById('elapsedTime').innerText = '-';
        document.getElementById('etaTime').innerText = '-';
    }
}

function startPolling(jobId) {
    if (pollInterval) clearInterval(pollInterval);
    
    pollInterval = setInterval(async () => {
        try {
            const response = await fetch(`/admin/broadcast/status/${jobId}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                const job = data.job;
                const processed = job.sent + job.failed;
                
                updateProgressUI(processed, job.total, job.sent, job.failed, job);
                
                if (job.status === 'completed' || job.status === 'failed') {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    finishBroadcastUI(job.status, job.sent, job.failed);
                }
            }
        } catch (e) {
            console.error("Error polling status:", e);
        }
    }, 1500);
}

function finishBroadcastUI(status, success, failed) {
    const btnSend = document.getElementById('btnSend');
    const messageTextarea = document.getElementById('broadcastMessage');
    const mediaFileInput = document.getElementById('mediaFile');
    
    btnSend.innerHTML = '<i class="fas fa-check-circle me-2"></i>{{ __('Selesai') }}';
    btnSend.classList.replace('btn-primary', 'btn-success');
    
    Swal.fire({
        icon: status === 'completed' ? 'success' : 'error',
        title: status === 'completed' ? '{{ __('Broadcast Selesai!') }}' : '{{ __('Broadcast Gagal') }}',
        html: `{{ __('Laporan Pengiriman Latar Belakang:') }}<br>{{ __('Berhasil terkirim:') }} <b>${success}</b><br>{{ __('Gagal terkirim:') }} <b>${failed}</b>`,
        confirmButtonColor: status === 'completed' ? '#198754' : '#dc3545'
    }).then(() => {
        // Reset form
        messageTextarea.disabled = false;
        messageTextarea.value = '';
        if (mediaFileInput) {
            mediaFileInput.disabled = false;
            mediaFileInput.value = '';
        }
        btnSend.disabled = false;
        btnSend.innerHTML = '<i class="fas fa-paper-plane me-2"></i>{{ __('Mulai Kirim Broadcast') }}';
        btnSend.classList.replace('btn-success', 'btn-primary');
        document.getElementById('progressSection').classList.add('d-none');
    });
}

async function resendBroadcast(jobId) {
    const result = await Swal.fire({
        title: '{{ __("Resend Broadcast?") }}',
        text: '{{ __("Broadcast akan dikirim ulang ke semua pelanggan dengan isi pesan dan lampiran yang sama. Lanjutkan?") }}',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '{{ __("Ya, Kirim Ulang!") }}',
        cancelButtonText: '{{ __("Batal") }}',
        customClass: {
            popup: 'rounded-4 border-0 shadow-lg',
            confirmButton: 'btn btn-primary rounded-pill px-4 me-2',
            cancelButton: 'btn btn-secondary rounded-pill px-4'
        },
        buttonsStyling: false
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch(`/admin/broadcast/resend/${jobId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            if (data.status === 'success') {
                currentJobId = data.job_id;
                if (document.getElementById('existingMediaPath')) document.getElementById('existingMediaPath').value = '';
        if (document.getElementById('existingMediaType')) document.getElementById('existingMediaType').value = '';
        if (document.getElementById('existingMediaIndicator')) document.getElementById('existingMediaIndicator').innerHTML = '';
                document.getElementById('progressSection').classList.remove('d-none');
                const btnSend = document.getElementById('btnSend');
                btnSend.disabled = true;
                btnSend.innerHTML = __('<i class="fas fa-spinner fa-spin me-2"></i>' + __('Sedang Mengirim (Latar Belakang)...'));
                updateProgressUI(0, data.total, 0, 0);
                startPolling(data.job_id);
                
                Swal.fire({
                    icon: 'success',
                    title: '{{ __("Resend Dimulai") }}',
                    text: '{{ __("Broadcast sedang dikirim ulang di latar belakang.") }}',
                    timer: 3000,
                    showConfirmButton: false
                });
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                throw new Error(data.message || '{{ __('Gagal mengirim ulang broadcast') }}');
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: '{{ __("Gagal") }}',
                text: e.message
            });
        }
    }
}

function resendWithEdit(jobId, b64Message, mediaPath, mediaType, enableReadButton = true, b64CtaLabel = '', b64CtaUrl = '') {
    const message = decodeURIComponent(escape(window.atob(b64Message)));
    document.getElementById('broadcastMessage').value = message;

    const readToggle = document.getElementById('enableReadButton');
    if (readToggle) readToggle.checked = !!enableReadButton;
    const ctaLabelEl = document.getElementById('ctaLabel');
    if (ctaLabelEl) ctaLabelEl.value = b64CtaLabel ? decodeURIComponent(escape(window.atob(b64CtaLabel))) : '';
    const ctaUrlEl = document.getElementById('ctaUrl');
    if (ctaUrlEl) ctaUrlEl.value = b64CtaUrl ? decodeURIComponent(escape(window.atob(b64CtaUrl))) : '';
    
    // Create hidden inputs for existing media
    let existingPathInput = document.getElementById('existingMediaPath');
    if (!existingPathInput) {
        existingPathInput = document.createElement('input');
        existingPathInput.type = 'hidden';
        existingPathInput.id = 'existingMediaPath';
        existingPathInput.name = 'existing_media_path';
        document.getElementById('broadcastForm').appendChild(existingPathInput);
    }
    existingPathInput.value = mediaPath;

    let existingTypeInput = document.getElementById('existingMediaType');
    if (!existingTypeInput) {
        existingTypeInput = document.createElement('input');
        existingTypeInput.type = 'hidden';
        existingTypeInput.id = 'existingMediaType';
        existingTypeInput.name = 'existing_media_type';
        document.getElementById('broadcastForm').appendChild(existingTypeInput);
    }
    existingTypeInput.value = mediaType;
    
    // Add visual indicator that media is attached
    let mediaIndicator = document.getElementById('existingMediaIndicator');
    if (!mediaIndicator) {
        mediaIndicator = document.createElement('div');
        mediaIndicator.id = 'existingMediaIndicator';
        mediaIndicator.className = 'mt-2 small text-primary fw-bold';
        const fileInput = document.getElementById('mediaFile');
        fileInput.parentNode.insertBefore(mediaIndicator, fileInput.nextSibling);
    }
    
    if (mediaPath) {
        mediaIndicator.innerHTML = '<i class="fas fa-paperclip me-1"></i> {{ __("Menggunakan media sebelumnya:") }} ' + mediaType + '. <br><span class="text-muted fw-normal">{{ __("Pilih file baru di atas jika ingin menggantinya.") }}</span>';
    } else {
        mediaIndicator.innerHTML = '';
        existingPathInput.value = '';
        existingTypeInput.value = '';
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    Swal.fire({
        icon: 'info',
        title: '{{ __("Siap Diedit") }}',
        text: '{{ __("Pesan telah dimuat ke dalam form. Anda bisa mengubah pesan lalu klik Mulai Kirim Broadcast.") }}',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

async function startBroadcast() {
    const btnSend = document.getElementById('btnSend');
    const message = document.getElementById('broadcastMessage').value;
    const mediaFile = document.getElementById('mediaFile').files[0];
    
    if (!message.trim() && !mediaFile) {
        return Swal.fire({
            icon: 'warning',
            title: 'Oops...',
            text: '{{ __('Pesan atau file media tidak boleh kosong!') }}',
            confirmButtonColor: '#3085d6'
        });
    }
    
    btnSend.disabled = true;
    btnSend.innerHTML = __('Mempersiapkan...');
    document.getElementById('broadcastMessage').disabled = true;
    document.getElementById('mediaFile').disabled = true;
    
    const formData = new FormData();
    formData.append('message', message);
    const readBtnToggle = document.getElementById('enableReadButton');
    formData.append('enable_read_button', (readBtnToggle && readBtnToggle.checked) ? '1' : '0');
    const ctaLabelVal = (document.getElementById('ctaLabel')?.value || '').trim();
    const ctaUrlVal = (document.getElementById('ctaUrl')?.value || '').trim();
    if (ctaLabelVal) formData.append('cta_label', ctaLabelVal);
    if (ctaUrlVal) formData.append('cta_url', ctaUrlVal);
    if (mediaFile) {
        formData.append('media_file', mediaFile);
    } else {
        const existingPath = document.getElementById('existingMediaPath');
        const existingType = document.getElementById('existingMediaType');
        if (existingPath && existingPath.value) {
            formData.append('existing_media_path', existingPath.value);
            formData.append('existing_media_type', existingType.value);
        }
    }
    
    try {
        const response = await fetch("{{ route('admin.broadcast.start') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        });
        
        const data = await response.json();
        if (data.status !== 'success') throw new Error(data.message || '{{ __('Gagal memulai broadcast') }}');
        
        currentJobId = data.job_id;
                if (document.getElementById('existingMediaPath')) document.getElementById('existingMediaPath').value = '';
        if (document.getElementById('existingMediaType')) document.getElementById('existingMediaType').value = '';
        if (document.getElementById('existingMediaIndicator')) document.getElementById('existingMediaIndicator').innerHTML = '';
        document.getElementById('progressSection').classList.remove('d-none');
        btnSend.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>' + __('Sedang Mengirim (Latar Belakang)...');
        updateProgressUI(0, data.total, 0, 0);
        
        startPolling(data.job_id);
        
        Swal.fire({
            icon: 'info',
            title: '{{ __('Broadcast Dimulai') }}',
            text: '{{ __('Proses pengiriman pesan sekarang berjalan di latar belakang. Anda dapat berpindah halaman dengan aman!') }}',
            timer: 4000,
            showConfirmButton: true,
            confirmButtonColor: '#0d6efd'
        });
        
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: '{{ __('Gagal') }}',
            text: error.message,
            confirmButtonColor: '#d33'
        });
        btnSend.disabled = false;
        document.getElementById('broadcastMessage').disabled = false;
        document.getElementById('mediaFile').disabled = false;
        btnSend.innerHTML = '<i class="fas fa-paper-plane me-2"></i>{{ __('Mulai Kirim Broadcast') }}';
    }
}

async function cancelBroadcast() {
    if (!currentJobId) return;

    const result = await Swal.fire({
        title: '{{ __('Hentikan Broadcast?') }}',
        text: '{{ __('Apakah Anda yakin ingin membatalkan dan menghentikan pengiriman broadcast yang sedang berjalan?') }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '{{ __('Ya, Hentikan!') }}',
        cancelButtonText: '{{ __('Batal') }}',
        customClass: {
            popup: 'rounded-4 border-0 shadow-lg',
            confirmButton: 'btn btn-danger rounded-pill px-4 me-2',
            cancelButton: 'btn btn-secondary rounded-pill px-4'
        },
        buttonsStyling: false
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch(`/admin/broadcast/cancel/${currentJobId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '{{ __('Berhasil Dihentikan') }}',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'rounded-4 border-0 shadow-lg'
                    }
                });
            } else {
                throw new Error(data.message || '{{ __('Gagal menghentikan broadcast') }}');
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: '{{ __('Gagal') }}',
                text: e.message,
                customClass: {
                    popup: 'rounded-4 border-0 shadow-lg'
                }
            });
        }
    }
}
let activeAnalyticsJobId = null;

function renderBroadcastAnalytics(data) {
    const body = document.getElementById('bcAnalyticsBody');
    const subtitle = document.getElementById('bcAnalyticsSubtitle');
    const updatedAt = document.getElementById('bcAnalyticsUpdatedAt');

    if (subtitle) {
        subtitle.textContent = `Broadcast #${data.job_id}` + (data.completed_at ? ` • {{ __('Selesai:') }} ${data.completed_at}` : '');
    }
    if (updatedAt) {
        updatedAt.textContent = data.last_calculated_at
            ? `{{ __('Terakhir dihitung:') }} ${data.last_calculated_at}`
            : '{{ __('Belum pernah dihitung') }}';
    }

    const timelineRows = (data.timeline || []).map(cp => {
        const badge = cp.captured
            ? `<span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1">{{ __('Tercatat') }}</span>`
            : `<span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1">{{ __('Terjadwal') }}</span>`;

        const metric = cp.captured
            ? `<div class="fw-bold text-body">${cp.count} <span class="text-primary small">(${cp.rate}%)</span></div>
               <div class="text-muted" style="font-size: 0.72rem;">Web: ${cp.web} &bull; Telegram: ${cp.telegram}</div>`
            : `<div class="text-muted small">{{ __('Jadwal:') }} ${cp.due_at}</div>`;

        return `
            <div class="d-flex justify-content-between align-items-center p-2.5 rounded-3 border" style="background: var(--surface-container-lowest, var(--bs-body-bg)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
                <div>
                    <div class="fw-semibold small text-body">${cp.label}</div>
                    <div class="text-muted" style="font-size: 0.72rem;">${cp.captured && cp.captured_at ? '{{ __('Direkam:') }} ' + cp.captured_at : '{{ __('Menunggu jadwal kalkulasi') }}'}</div>
                </div>
                <div class="text-end d-flex align-items-center gap-3">
                    <div>${metric}</div>
                    ${badge}
                </div>
            </div>
        `;
    }).join('');

    body.innerHTML = `
        <div class="row g-2.5 mb-3">
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-4 border h-100" style="background: var(--surface-container, var(--bs-tertiary-bg)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
                    <div class="text-muted small mb-1">{{ __('Terkirim Telegram') }}</div>
                    <div class="fs-4 fw-bold text-body">${data.sent_count}</div>
                    <div class="text-muted" style="font-size: 0.72rem;">{{ __('dari') }} ${data.total_targets} {{ __('target') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-4 border h-100" style="background: var(--primary-container, rgba(var(--bs-primary-rgb), 0.12)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
                    <div class="small mb-1 fw-semibold" style="color: var(--on-primary-container, var(--bs-primary));">{{ __('Pembaca Unik') }}</div>
                    <div class="fs-4 fw-bold" style="color: var(--on-primary-container, var(--bs-primary));">${data.read_count} <span class="fs-6">(${data.read_rate}%)</span></div>
                    <div style="font-size: 0.72rem; color: var(--on-primary-container, var(--bs-primary)); opacity: 0.85;">{{ __('Terkonfirmasi') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-4 border h-100" style="background: var(--surface-container, var(--bs-tertiary-bg)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
                    <div class="text-muted small mb-1"><i class="fas fa-globe me-1 text-primary"></i>{{ __('Baca via Web') }}</div>
                    <div class="fs-4 fw-bold text-body">${data.web_read_count}</div>
                    <div class="text-muted" style="font-size: 0.72rem;">{{ __('Notifikasi situs') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-4 border h-100" style="background: var(--surface-container, var(--bs-tertiary-bg)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
                    <div class="text-muted small mb-1"><i class="fab fa-telegram-plane me-1 text-info"></i>{{ __('Baca via Telegram') }}</div>
                    <div class="fs-4 fw-bold text-body">${data.telegram_read_count}</div>
                    <div class="text-muted" style="font-size: 0.72rem;">{{ __('Tombol / Klik CTA') }}</div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <h6 class="fw-bold small text-uppercase text-muted mb-2" style="letter-spacing: 0.04em;">
                <i class="fas fa-clock me-1"></i>{{ __('Timeline Kalkulasi Bertahap') }}
            </h6>
            <div class="d-flex flex-column gap-2">${timelineRows}</div>
        </div>

        <div class="p-3 rounded-4 small text-muted border" style="background: var(--surface-container, var(--bs-tertiary-bg)); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
            <i class="fas fa-circle-info text-primary me-1"></i>
            {{ __('Catatan: Telegram Bot API tidak menyediakan centang biru pasif untuk chat privat. Angka pembaca unik dihitung dari notifikasi website yang dibuka serta tombol konfirmasi baca / klik CTA di Telegram (deduplikasi per pengguna).') }}
        </div>
    `;

    // Sync table row numbers inline
    const countEl = document.getElementById(`bc-read-count-${data.job_id}`);
    const rateEl = document.getElementById(`bc-read-rate-${data.job_id}`);
    const cpEl = document.getElementById(`bc-read-cp-${data.job_id}`);
    if (countEl) countEl.textContent = data.read_count;
    if (rateEl) rateEl.textContent = `(${data.read_rate}%)`;
    if (cpEl && Array.isArray(data.timeline)) {
        const capturedList = data.timeline.filter(t => t.captured);
        if (capturedList.length > 0) {
            const latest = capturedList[capturedList.length - 1];
            cpEl.innerHTML = `<i class="fas fa-clock me-1"></i>{{ __('Tahap:') }} ${latest.label}`;
        }
    }
}

async function openBroadcastAnalytics(jobId) {
    activeAnalyticsJobId = jobId;
    const modalEl = document.getElementById('broadcastAnalyticsModal');
    const body = document.getElementById('bcAnalyticsBody');
    body.innerHTML = `<div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><div class="small">{{ __('Memuat laporan pembacaan...') }}</div></div>`;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    try {
        const res = await fetch(`/admin/broadcast/analytics/${jobId}`, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.status === 'success' && data.analytics) {
            renderBroadcastAnalytics(data.analytics);
        } else {
            throw new Error(data.message || '{{ __('Gagal memuat laporan pembacaan.') }}');
        }
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger mb-0 rounded-4">${err.message}</div>`;
    }
}

async function recalculateCurrentBroadcast() {
    if (!activeAnalyticsJobId) return;
    const btn = document.getElementById('btnRecalculateBc');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>{{ __('Menghitung...') }}';

    try {
        const res = await fetch(`/admin/broadcast/analytics/${activeAnalyticsJobId}/recalculate`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (data.status === 'success' && data.analytics) {
            renderBroadcastAnalytics(data.analytics);
        } else {
            throw new Error(data.message || '{{ __('Gagal menghitung ulang.') }}');
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: '{{ __('Gagal') }}', text: err.message });
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}
</script>
@endpush
