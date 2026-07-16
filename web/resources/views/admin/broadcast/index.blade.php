@extends('layouts.app')

@section('title', 'Broadcast Pesan')
@section('page_subtitle', 'Broadcast')

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
                        <textarea id="broadcastMessage" name="message" class="form-control" rows="8" placeholder="<b>Halo Pelanggan Setia!</b><br>Dapatkan promo menarik hari ini..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small">{{ __('Attachment Media (Opsional)') }}</label>
                        <input type="file" id="mediaFile" name="media_file" class="form-control" accept="image/*,video/mp4,.pdf,.doc,.docx,.zip">
                        <div class="form-text">{{ __('Maksimal 50MB. Format didukung: Foto (JPG/PNG), Video (MP4), Dokumen (PDF/DOC/ZIP).') }}</div>
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
                                <th class="border-0 text-end">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($broadcastHistory as $history)
                            <tr>
                                <td>
                                    <div class="small fw-bold text-dark">{{ $history->created_at->format('d M Y') }}</div>
                                    <div class="small text-muted">{{ $history->created_at->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" title="{{ strip_tags($history->message) }}">
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
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light rounded-pill border shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 12px;">
                                            <li>
                                                <button class="dropdown-item py-2" onclick="resendBroadcast({{ $history->id }})">
                                                    <i class="fas fa-redo text-primary me-2"></i>{{ __('Resend') }}
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item py-2" onclick="resendWithEdit({{ $history->id }}, `{{ base64_encode($history->message) }}`, '{{ $history->media_path }}', '{{ $history->media_type }}')">
                                                    <i class="fas fa-edit text-warning me-2"></i>{{ __('Resend with Edit') }}
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
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
            document.getElementById('etaTime').innerText = 'Selesai';
        } else {
            document.getElementById('etaTime').innerText = 'Menghitung...';
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
    
    btnSend.innerHTML = '<i class="fas fa-check-circle me-2"></i>Selesai';
    btnSend.classList.replace('btn-primary', 'btn-success');
    
    Swal.fire({
        icon: status === 'completed' ? 'success' : 'error',
        title: status === 'completed' ? 'Broadcast Selesai!' : 'Broadcast Gagal',
        html: `Laporan Pengiriman Latar Belakang:<br>Berhasil terkirim: <b>${success}</b><br>Gagal terkirim: <b>${failed}</b>`,
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
                throw new Error(data.message || 'Gagal mengirim ulang broadcast');
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

function resendWithEdit(jobId, b64Message, mediaPath, mediaType) {
    const message = decodeURIComponent(escape(window.atob(b64Message)));
    document.getElementById('broadcastMessage').value = message;
    
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
        text: '{{ __("Pesan telah dimuat ke dalam form. Anda bisa mengubah pesan lalu klik {{ __('Mulai Kirim Broadcast') }}.") }}',
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
            text: 'Pesan atau file media tidak boleh kosong!',
            confirmButtonColor: '#3085d6'
        });
    }
    
    btnSend.disabled = true;
    btnSend.innerHTML = __('Mempersiapkan...');
    document.getElementById('broadcastMessage').disabled = true;
    document.getElementById('mediaFile').disabled = true;
    
    const formData = new FormData();
    formData.append('message', message);
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
        if (data.status !== 'success') throw new Error(data.message || 'Gagal memulai broadcast');
        
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
            title: 'Broadcast Dimulai',
            text: 'Proses pengiriman pesan sekarang berjalan di latar belakang. Anda dapat berpindah halaman dengan aman!',
            timer: 4000,
            showConfirmButton: true,
            confirmButtonColor: '#0d6efd'
        });
        
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
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
        title: '{{ __('Hentikan Broadcast') }}?',
        text: 'Apakah Anda yakin ingin membatalkan dan menghentikan pengiriman broadcast yang sedang berjalan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hentikan!',
        cancelButtonText: 'Batal',
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
                    title: 'Berhasil Dihentikan',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'rounded-4 border-0 shadow-lg'
                    }
                });
            } else {
                throw new Error(data.message || 'Gagal menghentikan broadcast');
            }
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: e.message,
                customClass: {
                    popup: 'rounded-4 border-0 shadow-lg'
                }
            });
        }
    }
}
</script>
@endpush
