@extends('layouts.app')

@section('title', 'Broadcast Pesan')
@section('page_subtitle', 'Broadcast')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Broadcast Telegram</h4>
        <p class="text-muted mb-0">Kirim pesan massal ke seluruh pelanggan</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form id="broadcastForm" class="no-loader" onsubmit="event.preventDefault(); startBroadcast();">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small">Pesan Broadcast (Mendukung Format HTML)</label>
                        <textarea id="broadcastMessage" name="message" class="form-control" rows="8" required placeholder="<b>Halo Pelanggan Setia!</b><br>Dapatkan promo menarik hari ini..."></textarea>
                    </div>

                    <!-- Progress Bar Section (Hidden initially) -->
                    <div id="progressSection" class="d-none mb-4">
                        <h6 class="fw-bold mb-2">Progress Pengiriman <span id="progressText" class="text-primary float-end">0/0</span></h6>
                        <div class="progress" style="height: 20px; border-radius: 10px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small">
                            <span class="text-success"><i class="fas fa-check-circle me-1"></i>Berhasil: <span id="successCount" class="fw-bold">0</span></span>
                            <span class="text-danger"><i class="fas fa-times-circle me-1"></i>Gagal: <span id="failedCount" class="fw-bold">0</span></span>
                        </div>
                    </div>
                    
                    <div class="alert alert-info border-0 rounded-4 mb-4">
                        <div class="d-flex gap-3">
                            <i class="fas fa-info-circle fs-4 mt-1"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Broadcast Latar Belakang</h6>
                                <p class="mb-0 small text-body">Broadcast berjalan sepenuhnya di latar belakang. Anda dapat berpindah halaman atau menutup panel tanpa menghentikan pengiriman pesan. Progress akan tersimpan otomatis.</p>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" id="btnSend" class="btn btn-primary rounded-pill py-2 fw-bold">
                            <i class="fas fa-paper-plane me-2"></i>Mulai Kirim Broadcast
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
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

function setupActiveBroadcastUI(job) {
    const btnSend = document.getElementById('btnSend');
    const messageTextarea = document.getElementById('broadcastMessage');
    
    messageTextarea.value = job.message;
    messageTextarea.disabled = true;
    
    btnSend.disabled = true;
    btnSend.innerHTML = 'Sedang Mengirim (Latar Belakang)...';
    
    document.getElementById('progressSection').classList.remove('d-none');
    
    updateProgressUI(job.sent + job.failed, job.total, job.sent, job.failed);
}

function updateProgressUI(current, total, success, failed) {
    const percentage = total > 0 ? Math.round((current / total) * 100) : 0;
    
    document.getElementById('progressBar').style.width = percentage + '%';
    document.getElementById('progressBar').innerText = percentage + '%';
    document.getElementById('progressText').innerText = `${current}/${total}`;
    document.getElementById('successCount').innerText = success;
    document.getElementById('failedCount').innerText = failed;
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
                
                updateProgressUI(processed, job.total, job.sent, job.failed);
                
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
        btnSend.disabled = false;
        btnSend.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Mulai Kirim Broadcast';
        btnSend.classList.replace('btn-success', 'btn-primary');
        document.getElementById('progressSection').classList.add('d-none');
    });
}

async function startBroadcast() {
    const btnSend = document.getElementById('btnSend');
    const message = document.getElementById('broadcastMessage').value;
    
    if (!message.trim()) {
        return Swal.fire({
            icon: 'warning',
            title: 'Oops...',
            text: 'Pesan tidak boleh kosong!',
            confirmButtonColor: '#3085d6'
        });
    }
    
    btnSend.disabled = true;
    btnSend.innerHTML = 'Mempersiapkan...';
    document.getElementById('broadcastMessage').disabled = true;
    
    try {
        const response = await fetch("{{ route('admin.broadcast.start') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ message: message })
        });
        
        const data = await response.json();
        if (data.status !== 'success') throw new Error(data.message || 'Gagal memulai broadcast');
        
        currentJobId = data.job_id;
        document.getElementById('progressSection').classList.remove('d-none');
        btnSend.innerHTML = 'Sedang Mengirim (Latar Belakang)...';
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
        btnSend.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Mulai Kirim Broadcast';
    }
}
</script>
@endpush
