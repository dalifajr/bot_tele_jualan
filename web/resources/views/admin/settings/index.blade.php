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
                <h5 class="fw-bold mb-1"><i class="fas fa-qrcode text-success me-2"></i>Konfigurasi QRIS Dinamis</h5>
                <p class="text-muted small mb-4">Sistem akan mengekstrak payload dari gambar QRIS yang diupload dan menggunakannya untuk generate QRIS dengan nominal dinamis pada saat checkout.</p>

                @php
                    $qrisPayload = $settings['qris_static_payload'] ?? null;
                    $qrisImagePath = $settings['qris_image_path'] ?? null;
                @endphp

                @if($qrisPayload)
                    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-check-circle fs-3 me-3 text-success"></i>
                            <div>
                                <h6 class="fw-bold mb-0 text-success">QRIS Dinamis Siap Digunakan</h6>
                                <small class="text-dark">Payload berhasil diekstrak dan siap untuk transaksi.</small>
                            </div>
                        </div>
                        
                        <div class="row align-items-center">
                            <div class="col-sm-4 text-center mb-3 mb-sm-0">
                                @if($qrisImagePath)
                                    <img src="{{ route('admin.settings.qris.image') }}" alt="QRIS Tersimpan" class="img-fluid rounded border p-2 bg-white" style="max-height: 150px;">
                                @else
                                    <div class="border rounded p-4 bg-white text-muted">
                                        <i class="fas fa-image fs-1 mb-2"></i><br>
                                        <small>Gambar disetup via Bot Telegram</small>
                                    </div>
                                @endif
                            </div>
                            <div class="col-sm-8">
                                <label class="form-label fw-bold small text-muted">PAYLOAD EKSTRAK (RAW)</label>
                                <textarea class="form-control form-control-sm text-muted mb-3" rows="3" readonly>{{ $qrisPayload }}</textarea>
                                
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="document.getElementById('qrisUploadForm').classList.toggle('d-none')">
                                        <i class="fas fa-exchange-alt me-1"></i> Ganti QRIS
                                    </button>
                                    <form action="{{ route('admin.settings.qris.delete') }}" method="POST" class="d-inline" id="formDeleteQris" onsubmit="confirmAction(event, 'Yakin ingin menghapus QRIS? QRIS statis dan payload akan dihapus dari sistem.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                            <i class="fas fa-trash me-1"></i> Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form action="{{ route('admin.settings.qris.upload') }}" method="POST" enctype="multipart/form-data" id="qrisUploadForm" class="d-none border-top pt-4 mt-2">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">UPLOAD GAMBAR QRIS BARU</label>
                            <input type="file" name="qris_image" class="form-control" accept="image/png, image/jpeg, image/jpg" required>
                            <small class="text-muted">Format: JPG, PNG. Maksimal 2MB. Gambar harus cukup jelas agar kode QR bisa diekstrak.</small>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-upload me-1"></i> Upload & Ekstrak</button>
                    </form>
                @else
                    <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fs-3 me-3 text-warning"></i>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">QRIS Belum Dikonfigurasi</h6>
                                <small class="text-dark">Sistem belum memiliki referensi QRIS untuk checkout. Silakan upload gambar QRIS.</small>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('admin.settings.qris.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">UPLOAD GAMBAR QRIS</label>
                            <input type="file" name="qris_image" class="form-control" accept="image/png, image/jpeg, image/jpg" required>
                            <small class="text-muted">Format: JPG, PNG. Maksimal 2MB. Sistem akan otomatis membaca payload QRIS.</small>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-upload me-2"></i>Upload & Ekstrak</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Konfigurasi Payment Lainnya --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <h5 class="fw-bold mb-1"><i class="fas fa-cog text-secondary me-2"></i>Pengaturan Payment Lainnya</h5>
                    <p class="text-muted small mb-3">Konfigurasi variabel dan parameter tambahan.</p>

                    @php
                        $hasPaymentSettings = false;
                    @endphp
                    @foreach($settings as $key => $val)
                        @if((str_contains(strtolower($key), 'payment') || str_contains(strtolower($key), 'api') || str_contains(strtolower($key), 'pay')) && !str_contains(strtolower($key), 'qris'))
                        @php $hasPaymentSettings = true; @endphp
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small">{{ strtoupper(str_replace(['_', '.'], ' ', $key)) }}</label>
                            <input type="text" name="settings[{{ $key }}]" class="form-control" value="{{ $val }}">
                        </div>
                        @endif
                    @endforeach
                    
                    @if(!$hasPaymentSettings)
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-info-circle fs-3 mb-2"></i>
                            <p class="mb-0">Tidak ada pengaturan parameter payment tambahan.</p>
                        </div>
                    @endif

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-secondary rounded-pill py-2 fw-bold">
                            <i class="fas fa-save me-2"></i>Simpan Variabel Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
