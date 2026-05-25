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
                                <h6 class="fw-bold mb-1">Informasi Broadcast</h6>
                                <p class="mb-0 small text-body">Broadcast akan dikirimkan ke seluruh pelanggan yang memiliki ID Telegram. Jangan tutup halaman ini sebelum proses pengiriman selesai agar *progress bar* dapat berjalan hingga tuntas.</p>
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
    
    // UI Update
    btnSend.disabled = true;
    btnSend.innerHTML = 'Mempersiapkan...';
    document.getElementById('progressSection').classList.remove('d-none');
    
    let targets = [];
    
    try {
        const response = await fetch("{{ route('admin.broadcast.prepare') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ message: message })
        });
        
        const data = await response.json();
        if (data.status !== 'success') throw new Error(data.message || 'Gagal menyiapkan broadcast');
        
        targets = data.targets;
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: error.message,
            confirmButtonColor: '#d33'
        });
        btnSend.disabled = false;
        btnSend.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Mulai Kirim Broadcast';
        return;
    }
    
    const total = targets.length;
    let success = 0;
    let failed = 0;
    
    btnSend.innerHTML = 'Sedang Mengirim...';
    document.getElementById('progressText').innerText = `0/${total}`;
    
    // Loop through targets one by one
    for (let i = 0; i < total; i++) {
        try {
            const res = await fetch("{{ route('admin.broadcast.send') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    telegram_id: targets[i],
                    message: message
                })
            });
            
            const resData = await res.json();
            if (resData.status === 'success') {
                success++;
            } else {
                failed++;
            }
        } catch (error) {
            failed++;
        }
        
        // Update Progress Bar
        const current = i + 1;
        const percentage = Math.round((current / total) * 100);
        
        document.getElementById('progressBar').style.width = percentage + '%';
        document.getElementById('progressBar').innerText = percentage + '%';
        document.getElementById('progressText').innerText = `${current}/${total}`;
        document.getElementById('successCount').innerText = success;
        document.getElementById('failedCount').innerText = failed;
    }
    
    // Finished
    btnSend.innerHTML = '<i class="fas fa-check-circle me-2"></i>Selesai';
    btnSend.classList.replace('btn-primary', 'btn-success');
    
    Swal.fire({
        icon: 'success',
        title: 'Broadcast Selesai!',
        html: `Berhasil terkirim: <b>${success}</b><br>Gagal terkirim: <b>${failed}</b>`,
        confirmButtonColor: '#198754'
    });
}
</script>
@endpush
