@extends('layouts.app')

@section('title', 'Konfigurasi Sistem')
@section('page_subtitle', 'Konfigurasi Sistem')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Konfigurasi Sistem</h4>
        <p class="text-muted mb-0">Kelola pengaturan payment, waktu bot, dan konfigurasi lainnya</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">

        {{-- Konfigurasi Waktu Bot --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST" id="formTimerBot">
                    @csrf
                    <h5 class="fw-bold mb-1"><i class="fas fa-clock text-primary me-2"></i>Atur Waktu Bot</h5>
                    <p class="text-muted small mb-3">Atur durasi waktu tunggu untuk status stok. Perubahan akan langsung berlaku untuk akun stok yang baru ditambahkan.</p>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Awaiting Benefits → Ready (Jam)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-warning-subtle border-0"><i class="fas fa-hourglass-half text-warning"></i></span>
                                <input type="number" name="settings[github_pack.awaiting_hours]" class="form-control" value="{{ $settings['github_pack.awaiting_hours'] ?? 78 }}" min="1" max="720" required>
                                <span class="input-group-text bg-light border-0 small text-muted">jam</span>
                            </div>
                            <div class="form-text">Default: 78 jam. Berapa lama akun di <em>awaiting benefits</em> sebelum otomatis menjadi <em>ready</em>.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Simpan Akun → Siap Diajukan (Jam)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-info-subtle border-0"><i class="fas fa-save text-info"></i></span>
                                <input type="number" name="settings[github_pack.save_hours]" class="form-control" value="{{ $settings['github_pack.save_hours'] ?? 80 }}" min="1" max="720" required>
                                <span class="input-group-text bg-light border-0 small text-muted">jam</span>
                            </div>
                            <div class="form-text">Default: 80 jam. Berapa lama akun <em>simpan akun</em> ditahan sebelum bisa diajukan verifikasi.</div>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold">
                            <i class="fas fa-save me-2"></i>Simpan Pengaturan Waktu
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Konfigurasi Payment / QRIS --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <h5 class="fw-bold mb-1"><i class="fas fa-credit-card text-success me-2"></i>Pengaturan Payment</h5>
                    <p class="text-muted small mb-3">Konfigurasi untuk QRIS dan API pembayaran.</p>

                    {{-- Upload QRIS --}}
                    <div class="mb-4 p-3 border rounded bg-light">
                        <label class="form-label fw-bold text-muted small"><i class="fas fa-qrcode me-1"></i> UPLOAD GAMBAR QRIS</label>
                        <input type="file" id="qrisImageInput" class="form-control mb-2" accept="image/*">
                        <small class="text-muted d-block mb-3">Upload gambar QRIS statis Anda (dari e-Wallet atau Bank). Sistem akan otomatis membaca barcode untuk mendukung QRIS Dinamis pada saat pelanggan checkout.</small>
                        <div id="qrisPreview" class="text-center mb-3 d-none">
                            <canvas id="qrisCanvas" style="max-width: 100%; height: auto; border: 1px solid #ccc; border-radius: 8px;"></canvas>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-code"></i></span>
                            <input type="text" name="settings[qris_static_payload]" id="qrisPayloadInput" class="form-control font-monospace text-muted" style="font-size: 0.8rem;" value="{{ $settings['qris_static_payload'] ?? '' }}" placeholder="Payload QRIS akan terisi otomatis" readonly>
                        </div>
                    </div>

                    @php
                        $hasPaymentSettings = false;
                    @endphp
                    @foreach($settings as $key => $val)
                        @if($key !== 'qris_static_payload' && (str_contains(strtolower($key), 'qris') || str_contains(strtolower($key), 'payment') || str_contains(strtolower($key), 'api') || str_contains(strtolower($key), 'pay')))
                        @php $hasPaymentSettings = true; @endphp
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">{{ strtoupper(str_replace(['_', '.'], ' ', $key)) }}</label>
                            <input type="text" name="settings[{{ $key }}]" class="form-control" value="{{ $val }}">
                        </div>
                        @endif
                    @endforeach
                    
                    @if(!$hasPaymentSettings && !isset($settings['qris_static_payload']))
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-info-circle fs-3 mb-2"></i>
                            <p class="mb-0">Belum ada pengaturan payment tambahan di database.</p>
                        </div>
                    @endif

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success rounded-pill py-2 fw-bold">
                            <i class="fas fa-save me-2"></i>Simpan Pengaturan Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('qrisImageInput');
        const canvas = document.getElementById('qrisCanvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        const payloadInput = document.getElementById('qrisPayloadInput');
        const previewDiv = document.getElementById('qrisPreview');

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                const img = new Image();
                img.onload = function() {
                    // Set canvas size matching image
                    canvas.width = img.width;
                    canvas.height = img.height;
                    
                    // Draw image to canvas
                    ctx.drawImage(img, 0, 0);
                    previewDiv.classList.remove('d-none');
                    canvas.style.display = 'inline-block';

                    // Get image data
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    
                    // Use jsQR to decode
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: "dontInvert",
                    });

                    if (code) {
                        payloadInput.value = code.data;
                        Swal.fire({
                            icon: 'success',
                            title: 'QRIS Terbaca!',
                            text: 'Payload QRIS berhasil diekstrak. Silakan simpan pengaturan.',
                            confirmButtonColor: '#198754'
                        });
                    } else {
                        // Fallback: try inversion
                        const codeInverted = jsQR(imageData.data, imageData.width, imageData.height, {
                            inversionAttempts: "invertFirst",
                        });
                        
                        if (codeInverted) {
                            payloadInput.value = codeInverted.data;
                            Swal.fire({
                                icon: 'success',
                                title: 'QRIS Terbaca!',
                                text: 'Payload QRIS berhasil diekstrak (Inverted). Silakan simpan pengaturan.',
                                confirmButtonColor: '#198754'
                            });
                        } else {
                            payloadInput.value = '';
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Membaca QRIS',
                                text: 'Gambar tidak memiliki QR code yang valid, atau tidak terbaca dengan jelas.',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    }
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    });
</script>
@endpush
