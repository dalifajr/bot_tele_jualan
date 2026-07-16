<?php
$file = "d:/bot_tele_jualan/web/resources/views/admin/broadcast/index.blade.php";
$content = file_get_contents($file);

// Add the button at the top
$content = str_replace(
    "<h4 class=\"fw-bold mb-1\">Broadcast Telegram</h4>\n        <p class=\"text-muted mb-0\">Kirim pesan massal ke seluruh pelanggan</p>\n    </div>\n</div>",
    "<h4 class=\"fw-bold mb-1\">{{ __('Broadcast Telegram') }}</h4>\n        <p class=\"text-muted mb-0\">{{ __('Kirim pesan massal ke seluruh pelanggan') }}</p>\n    </div>\n    <a href=\"#broadcastHistorySection\" class=\"btn btn-outline-primary rounded-pill fw-bold\">\n        <i class=\"fas fa-history me-2\"></i>{{ __('Riwayat Broadcast') }}\n    </a>\n</div>",
    $content
);

// Append the history table below the form card.
$historyHtml = <<<HTML
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
                            @forelse(\$broadcastHistory as \$history)
                            <tr>
                                <td>
                                    <div class="small fw-bold text-dark">{{ \$history->created_at->format('d M Y') }}</div>
                                    <div class="small text-muted">{{ \$history->created_at->format('H:i') }}</div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" title="{{ strip_tags(\$history->message) }}">
                                        {!! Str::limit(strip_tags(\$history->message), 50) ?: '<em class="text-muted">' . __('Tidak ada teks') . '</em>' !!}
                                    </div>
                                </td>
                                <td>
                                    @if(\$history->media_type && \$history->media_path)
                                        <a href="{{ Storage::url(\$history->media_path) }}" target="_blank" class="badge bg-info text-decoration-none">
                                            <i class="fas fa-file-alt me-1"></i>{{ ucfirst(\$history->media_type) }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if(\$history->status == 'completed')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">{{ __('Selesai') }}</span>
                                    @elseif(\$history->status == 'failed')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ __('Gagal') }}</span>
                                    @elseif(\$history->status == 'cancelled')
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">{{ __('Dibatalkan') }}</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">{{ ucfirst(\$history->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-success fw-bold"><i class="fas fa-check me-1"></i>{{ \$history->sent_count }}</span>
                                        <span class="text-muted mx-1">|</span>
                                        <span class="text-danger fw-bold"><i class="fas fa-times me-1"></i>{{ \$history->failed_count }}</span>
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ __('dari') }} {{ \$history->total_targets }}</div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light rounded-pill border shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 12px;">
                                            <li>
                                                <button class="dropdown-item py-2" onclick="resendBroadcast({{ \$history->id }})">
                                                    <i class="fas fa-redo text-primary me-2"></i>{{ __('Resend') }}
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item py-2" onclick="resendWithEdit({{ \$history->id }}, `{{ base64_encode(\$history->message) }}`, '{{ \$history->media_path }}', '{{ \$history->media_type }}')">
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
                    {{ \$broadcastHistory->fragment('broadcastHistorySection')->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
HTML;

$content = str_replace("    </div>\n</div>\n@endsection", $historyHtml, $content);

// Add JS for Resend and Resend with Edit
$jsHtml = <<<HTML
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
            const response = await fetch(`/admin/broadcast/resend/\${jobId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            if (data.status === 'success') {
                currentJobId = data.job_id;
                document.getElementById('progressSection').classList.remove('d-none');
                const btnSend = document.getElementById('btnSend');
                btnSend.disabled = true;
                btnSend.innerHTML = __('Sedang Mengirim (Latar Belakang)...');
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
        text: '{{ __("Pesan telah dimuat ke dalam form. Anda bisa mengubah pesan lalu klik Mulai Kirim Broadcast.") }}',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}
HTML;

$content = str_replace("async function startBroadcast() {", $jsHtml . "\n\nasync function startBroadcast() {", $content);

// Modify startBroadcast JS to append existing media data
$jsHtml2 = <<<HTML
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
HTML;

$content = str_replace(
    "if (mediaFile) {\n        formData.append('media_file', mediaFile);\n    }",
    $jsHtml2,
    $content
);


// After successful startBroadcast, clear existing inputs
$jsHtml3 = <<<HTML
        if (document.getElementById('existingMediaPath')) document.getElementById('existingMediaPath').value = '';
        if (document.getElementById('existingMediaType')) document.getElementById('existingMediaType').value = '';
        if (document.getElementById('existingMediaIndicator')) document.getElementById('existingMediaIndicator').innerHTML = '';
HTML;

$content = str_replace("currentJobId = data.job_id;", "currentJobId = data.job_id;\n        " . $jsHtml3, $content);

// Localize existing texts in the view
$content = str_replace("Pesan Broadcast (Mendukung Format HTML)", "{{ __('Pesan Broadcast (Mendukung Format HTML)') }}", $content);
$content = str_replace("Attachment Media (Opsional)", "{{ __('Attachment Media (Opsional)') }}", $content);
$content = str_replace("Maksimal 50MB. Format didukung: Foto (JPG/PNG), Video (MP4), Dokumen (PDF/DOC/ZIP).", "{{ __('Maksimal 50MB. Format didukung: Foto (JPG/PNG), Video (MP4), Dokumen (PDF/DOC/ZIP).') }}", $content);
$content = str_replace("Progress Pengiriman", "{{ __('Progress Pengiriman') }}", $content);
$content = str_replace("Waktu Berjalan:", "{{ __('Waktu Berjalan:') }}", $content);
$content = str_replace("Estimasi Selesai (ETA):", "{{ __('Estimasi Selesai (ETA):') }}", $content);
$content = str_replace(">Berhasil:", ">{{ __('Berhasil:') }}", $content);
$content = str_replace(">Gagal:", ">{{ __('Gagal:') }}", $content);
$content = str_replace("Hentikan Broadcast", "{{ __('Hentikan Broadcast') }}", $content);
$content = str_replace("Broadcast Latar Belakang", "{{ __('Broadcast Latar Belakang') }}", $content);
$content = str_replace("Broadcast berjalan sepenuhnya di latar belakang. Anda dapat berpindah halaman atau menutup panel tanpa menghentikan pengiriman pesan. Progress akan tersimpan otomatis.", "{{ __('Broadcast berjalan sepenuhnya di latar belakang. Anda dapat berpindah halaman atau menutup panel tanpa menghentikan pengiriman pesan. Progress akan tersimpan otomatis.') }}", $content);
$content = str_replace("Mulai Kirim Broadcast", "{{ __('Mulai Kirim Broadcast') }}", $content);
$content = str_replace("'Sedang Mengirim (Latar Belakang)...'", "'<i class=\"fas fa-spinner fa-spin me-2\"></i>' + __('Sedang Mengirim (Latar Belakang)...')", $content);
$content = str_replace("'Mempersiapkan...'", "__('Mempersiapkan...')", $content);

// We need a JS helper for translations
$jsTrans = <<<HTML
function __(key) {
    const translations = {
        'Sedang Mengirim (Latar Belakang)...': '{{ __("Sedang Mengirim (Latar Belakang)...") }}',
        'Selesai': '{{ __("Selesai") }}',
        'Menghitung...': '{{ __("Menghitung...") }}',
        'Mempersiapkan...': '{{ __("Mempersiapkan...") }}'
    };
    return translations[key] || key;
}
HTML;
$content = str_replace("let pollInterval = null;", $jsTrans . "\nlet pollInterval = null;", $content);

file_put_contents($file, $content);
echo "View updated";
