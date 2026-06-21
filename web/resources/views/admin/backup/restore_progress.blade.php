@extends('layouts.app')

@section('title', 'Backup & Restore - Progress Pemulihan')
@section('page_subtitle', 'Backup & Restore')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Backup & Restore</h4>
        <p class="text-muted mb-0">Pemulihan data cadangan secara real-time</p>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 16px; background-color: var(--bs-card-bg);">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-1">
                    <i class="fas fa-sync-alt fa-spin text-danger me-2" id="restoreIcon"></i>
                    Status: <span id="statusText" class="text-danger">Sedang memproses pemulihan data...</span>
                </h5>
                <p class="text-muted small mb-0">
                    Berkas: <strong class="text-body">{{ $filename }}</strong> &bull; Mode: <strong class="text-body">{{ $mode === 'overwrite' ? 'Full Overwrite' : 'Smart Merge' }}</strong>
                </p>
            </div>
            <div class="fs-4 fw-bold text-danger" id="restorePercent">5%</div>
        </div>

        <div class="progress mb-4" style="height: 12px; border-radius: 6px;">
            <div id="restoreProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger" role="progressbar" style="width: 5%;" aria-valuenow="5" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <h6 class="fw-bold small text-muted mb-2">LIVE EXECUTION LOGS</h6>
        <div id="logConsole" class="bg-dark text-light p-3 font-monospace mb-4" style="height: 350px; overflow-y: auto; border-radius: 12px; font-size: 0.85rem; line-height: 1.5; border: 1px solid #343a40;">
            <div class="text-secondary">[System] Memulai streaming pemulihan data...</div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('admin.backup.index') }}" id="btnDone" class="btn btn-secondary rounded-pill px-4 py-2 fw-bold d-none">
                <i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', async function () {
        const consoleBox = document.getElementById('logConsole');
        const progressBar = document.getElementById('restoreProgressBar');
        const percentText = document.getElementById('restorePercent');
        const statusText = document.getElementById('statusText');
        const restoreIcon = document.getElementById('restoreIcon');
        const btnDone = document.getElementById('btnDone');

        function appendLog(message, type = 'info') {
            const time = new Date().toLocaleTimeString('id-ID');
            let colorClass = 'text-light';
            if (type === 'error') colorClass = 'text-danger fw-bold';
            if (type === 'success') colorClass = 'text-success fw-bold';
            if (type === 'system') colorClass = 'text-warning';

            const line = document.createElement('div');
            line.className = colorClass;
            line.innerHTML = `[${time}] ${message}`;
            consoleBox.appendChild(line);
            consoleBox.scrollTop = consoleBox.scrollHeight;
        }

        const runUrl = "{{ route('admin.backup.restore.run') }}?file={{ urlencode($file) }}&filename={{ urlencode($filename) }}&mode={{ urlencode($mode) }}";
        const statusUrl = "{{ route('admin.backup.restore.status') }}";
        
        let lastLoggedCount = 0;

        async function pollStatus() {
            try {
                const res = await fetch(statusUrl);
                if (!res.ok) {
                    setTimeout(pollStatus, 1000);
                    return;
                }
                const data = await res.json();
                
                const percent = data.percent;
                const message = data.message;
                const logs = data.logs || [];
                const status = data.status;

                // Render new log lines
                if (logs.length > lastLoggedCount) {
                    for (let i = lastLoggedCount; i < logs.length; i++) {
                        let logMsg = logs[i];
                        let cleanMsg = logMsg;
                        let match = logMsg.match(/^\[\d{2}:\d{2}:\d{2}\]\s*(.*)/);
                        if (match) {
                            cleanMsg = match[1];
                        }
                        appendLog(cleanMsg, percent === 100 ? 'success' : (percent === -1 ? 'error' : 'info'));
                    }
                    lastLoggedCount = logs.length;
                }

                if (percent === -1 || status === 'failed') {
                    progressBar.classList.remove('bg-danger', 'progress-bar-striped', 'progress-bar-animated');
                    progressBar.classList.add('bg-warning');
                    progressBar.style.width = '100%';
                    percentText.innerText = 'Gagal';
                    statusText.innerText = "Pemulihan Gagal!";
                    statusText.className = "text-warning";
                    restoreIcon.className = "fas fa-exclamation-triangle text-warning";
                    btnDone.classList.remove('d-none');
                    btnDone.classList.remove('btn-secondary');
                    btnDone.classList.add('btn-warning');
                    return;
                }

                progressBar.style.width = percent + '%';
                percentText.innerText = percent + '%';

                if (percent === 100 || status === 'completed') {
                    progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                    progressBar.classList.add('bg-success');
                    statusText.innerText = "Pemulihan Selesai!";
                    statusText.className = "text-success";
                    restoreIcon.className = "fas fa-check-circle text-success";
                    btnDone.classList.remove('d-none');
                    btnDone.classList.remove('btn-secondary');
                    btnDone.classList.add('btn-success');
                    
                    Swal.fire({
                        title: 'Restore Berhasil!',
                        text: 'Seluruh database dan berkas media berhasil dipulihkan secara penuh.',
                        icon: 'success',
                        confirmButtonText: 'Kembali',
                        customClass: {
                            popup: 'rounded-4',
                            confirmButton: 'btn btn-success rounded-pill px-4'
                        },
                        buttonsStyling: false
                    });
                    return;
                }

                setTimeout(pollStatus, 1000);
            } catch (e) {
                setTimeout(pollStatus, 1500);
            }
        }

        // Start polling status immediately
        pollStatus();
    });
</script>
@endpush
