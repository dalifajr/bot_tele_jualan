@extends('layouts.app')

@section('title', __('Ruang Rekber: ') . $transaction->transaction_code)
@section('page_subtitle', __('Ruang Transaksi'))

@section('content')
@php
    $isPublicView = isset($isPublic) && $isPublic;
    $currentUser = Auth::user();
    $isBuyer = $currentUser ? $transaction->isBuyer($currentUser) : ($transaction->creator_role === 'seller');
    $isSeller = $currentUser ? $transaction->isSeller($currentUser) : ($transaction->creator_role === 'buyer');
    $isAdmin = $currentUser && $currentUser->role === 'admin';
    $publicUrl = route('escrow.public.show', $transaction->public_token);
    $totalMessages = $transaction->messages->count();
@endphp

<style>
    /* Floating Action Button (FAB) Chat Rekber */
    .escrow-chat-fab {
        position: fixed;
        bottom: 28px;
        right: 28px;
        z-index: 1045;
        width: 60px;
        height: 60px;
        border-radius: 50% !important;
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
        border: 2px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 10px 25px -4px rgba(13, 110, 253, 0.5), 0 4px 10px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        cursor: pointer;
        transition: all 0.28s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .escrow-chat-fab:hover {
        transform: translateY(-4px) scale(1.08);
        box-shadow: 0 14px 28px -3px rgba(13, 110, 253, 0.6), 0 6px 14px rgba(0, 0, 0, 0.15);
        color: #ffffff;
    }
    .escrow-chat-fab:active {
        transform: scale(0.94);
    }
    @media (max-width: 768px) {
        .escrow-chat-fab {
            bottom: calc(20px + env(safe-area-inset-bottom, 0px));
            right: 20px;
            width: 56px;
            height: 56px;
        }
    }

    /* Chat bubble styles */
    .chat-bubble {
        max-width: 82%;
        padding: 10px 14px;
        border-radius: 16px;
        position: relative;
        font-size: 0.88rem;
        line-height: 1.4;
    }
    .chat-bubble-me {
        background: #e7f1ff;
        color: #0b409c;
        border-bottom-right-radius: 4px;
        align-self: flex-end;
        border: 1px solid rgba(13, 110, 253, 0.2);
    }
    .chat-bubble-other {
        background: #ffffff;
        color: #212529;
        border-bottom-left-radius: 4px;
        align-self: flex-start;
        border: 1px solid #dee2e6;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }
    .chat-bubble-admin {
        background: #fff8e6;
        color: #7c4a00;
        border: 1px solid #ffd166;
        border-bottom-left-radius: 4px;
        align-self: flex-start;
    }

    /* Payment Nav Pills */
    .nav-payment .nav-link {
        border-radius: 12px;
        font-weight: 600;
        color: #495057;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 10px 18px;
        transition: all 0.2s ease;
    }
    .nav-payment .nav-link:hover {
        background-color: #e9ecef;
    }
    .nav-payment .nav-link.active {
        background-color: #0d6efd;
        color: #ffffff;
        border-color: #0d6efd;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
    }
</style>

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="d-flex align-items-center gap-2 mb-1">
            @if(!$isPublicView)
                <a href="{{ route('escrow.index') }}" class="btn btn-sm btn-link text-muted p-0 text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> {{ __('Daftar') }}
                </a>
                <span class="text-muted">/</span>
            @endif
            <span class="badge bg-{{ $transaction->status_badge }} rounded-pill px-3 py-1.5 fw-semibold" style="font-size: 0.8rem;">
                {{ $transaction->status_label }}
            </span>
            <code class="fw-bold text-primary">{{ $transaction->transaction_code }}</code>
        </div>
        <h4 class="fw-bold text-dark mb-1">{{ $transaction->title }}</h4>
        <p class="text-muted small mb-0">{{ $transaction->description ?: __('Tidak ada deskripsi tambahan.') }}</p>
    </div>
    <div class="col-12 col-lg-4 text-lg-end d-flex align-items-center justify-content-lg-end gap-2">
        {{-- Tombol Buka Chat (Shortcut Dekat Header) --}}
        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm d-inline-flex align-items-center gap-1.5" data-bs-toggle="offcanvas" data-bs-target="#escrowChatDrawer">
            <i class="fas fa-comments"></i>
            <span>{{ __('Obrolan Ruang') }}</span>
            @if($totalMessages > 0)
                <span class="badge bg-white text-primary rounded-pill px-1.5">{{ $totalMessages }}</span>
            @endif
        </button>

        {{-- Copy Public Link Button --}}
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="copyText('{{ $publicUrl }}', 'Tautan ruang rekber berhasil disalin!')">
            <i class="fas fa-link me-1"></i> {{ __('Salin Tautan') }}
        </button>
    </div>
</div>

{{-- Visual Stepper --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-3 p-md-4">
        @php
            $steps = [
                ['key' => 'waiting_deposit', 'label' => __('Transfer Deposit'), 'icon' => 'fa-wallet'],
                ['key' => 'deposit_submitted', 'label' => __('Verifikasi Admin'), 'icon' => 'fa-receipt'],
                ['key' => 'funds_held', 'label' => __('Dana Diamankan'), 'icon' => 'fa-shield-halved'],
                ['key' => 'delivered', 'label' => __('Cek Akun/Barang'), 'icon' => 'fa-box-check'],
                ['key' => 'completed', 'label' => __('Selesai & Payout'), 'icon' => 'fa-check-double'],
            ];

            $statusRank = [
                'waiting_deposit' => 1,
                'deposit_submitted' => 2,
                'funds_held' => 3,
                'delivered' => 4,
                'waiting_payout' => 5,
                'completed' => 5,
                'disputed' => 3,
                'refunded' => 5,
                'cancelled' => 0,
            ];

            $currentRank = $statusRank[$transaction->status] ?? 1;
        @endphp

        <div class="d-flex justify-content-between position-relative px-2">
            @foreach($steps as $index => $st)
                @php
                    $stepNum = $index + 1;
                    $isPassed = $currentRank > $stepNum || ($currentRank === $stepNum && $transaction->status === 'completed');
                    $isCurrent = $currentRank === $stepNum && $transaction->status !== 'completed';
                @endphp
                <div class="text-center flex-fill position-relative" style="z-index: 2;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold shadow-sm mb-2 {{ $isPassed ? 'bg-success text-white' : ($isCurrent ? 'bg-primary text-white ring-2' : 'bg-light text-muted border') }}" style="width: 38px; height: 38px;">
                        @if($isPassed)
                            <i class="fas fa-check"></i>
                        @else
                            <i class="fas {{ $st['icon'] }}" style="font-size: 0.85rem;"></i>
                        @endif
                    </div>
                    <div class="small fw-semibold {{ $isCurrent ? 'text-primary' : ($isPassed ? 'text-success' : 'text-muted') }}" style="font-size: 0.72rem;">
                        {{ $st['label'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Left Column: Transaksi Detail & Aksi Pembayaran / Serah Terima --}}
    <div class="col-12 col-lg-8">
        {{-- Section 1: Ringkasan Nilai --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-file-invoice-dollar text-primary"></i> {{ __('Rincian Keuangan Rekber') }}
                    </h6>
                    <span class="badge bg-light text-dark border">
                        {{ __('Penanggung Fee:') }} 
                        <strong class="text-primary">{{ ucfirst($transaction->fee_payer) }}</strong>
                    </span>
                </div>

                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <small class="text-muted d-block mb-1">{{ __('Nilai Barang') }}</small>
                            <strong class="text-dark fs-6">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <small class="text-muted d-block mb-1">{{ __('Biaya Rekber') }}</small>
                            <strong class="text-info fs-6">Rp {{ number_format($transaction->fee_amount, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-primary-subtle rounded-3 border border-primary-subtle h-100">
                            <small class="text-primary d-block mb-1 fw-semibold">{{ __('Total Bayar (Pembeli)') }}</small>
                            <strong class="text-primary fs-5">Rp {{ number_format($transaction->total_buyer_pay, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle h-100">
                            <small class="text-success d-block mb-1 fw-semibold">{{ __('Total Cair (Penjual)') }}</small>
                            <strong class="text-success fs-5">Rp {{ number_format($transaction->total_seller_receive, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Instruksi Pembayaran Deposit (QRIS Dinamis & Rekening Bank) --}}
        @if(in_array($transaction->status, [\App\Models\EscrowTransaction::STATUS_WAITING_DEPOSIT, \App\Models\EscrowTransaction::STATUS_DEPOSIT_SUBMITTED]))
            <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold text-primary mb-1 d-flex align-items-center gap-2">
                                <i class="fas fa-wallet"></i> {{ __('Pembayaran Deposit Rekber') }}
                            </h5>
                            <p class="text-muted small mb-0">
                                {{ __('Pembeli silakan melakukan pembayaran sesuai nominal di bawah ke rekening perantara resmi toko.') }}
                            </p>
                        </div>
                        <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-semibold">
                            {{ __('Nominal: Rp ') . number_format($transaction->total_buyer_pay, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Metode Pembayaran Tab: QRIS vs Transfer Bank --}}
                    <ul class="nav nav-payment nav-pills gap-2 mb-3" id="paymentMethodTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active d-flex align-items-center gap-2" id="qris-tab" data-bs-toggle="pill" data-bs-target="#qris-method" type="button" role="tab" aria-selected="true">
                                <i class="fas fa-qrcode"></i>
                                <span>{{ __('QRIS (Otomatis & Instan)') }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2" id="bank-tab" data-bs-toggle="pill" data-bs-target="#bank-method" type="button" role="tab" aria-selected="false">
                                <i class="fas fa-building-columns"></i>
                                <span>{{ __('Transfer Rekening Bank') }}</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content border rounded-3 p-3 p-md-4 bg-light mb-4" id="paymentMethodTabContent">
                        {{-- Pane 1: QRIS --}}
                        <div class="tab-pane fade show active" id="qris-method" role="tabpanel" aria-labelledby="qris-tab">
                            @if(!empty($dynamicQris))
                                <div class="text-center py-2">
                                    <div class="mb-2">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 fw-semibold">
                                            <i class="fas fa-check-circle me-1"></i> {{ __('QRIS Dinamis Terisi Nominal Otomatis') }}
                                        </span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-3">{{ __('Scan Kode QRIS di Bawah Ini:') }}</h6>

                                    <div class="d-inline-block bg-white p-3 rounded-4 shadow-sm border mb-3 position-relative">
                                        <div id="qrcode" class="d-flex justify-content-center"></div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="small text-muted mb-1">{{ __('Total Nominal Tagihan Rekber:') }}</div>
                                        <div class="fs-4 fw-bold text-primary font-monospace">Rp {{ number_format($transaction->total_buyer_pay, 0, ',', '.') }}</div>
                                    </div>

                                    <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="downloadQrisImage()">
                                            <i class="fas fa-download me-1"></i> {{ __('Unduh Kode QR') }}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="copyText('{{ $transaction->total_buyer_pay }}', 'Nominal berhasil disalin!')">
                                            <i class="fas fa-copy me-1"></i> {{ __('Salin Nominal') }}
                                        </button>
                                    </div>

                                    <div class="alert alert-light border small text-start mb-0 mx-auto" style="max-width: 520px;">
                                        <strong><i class="fas fa-info-circle text-primary me-1"></i> {{ __('Cara Pembayaran via QRIS:') }}</strong>
                                        <ol class="mb-0 ps-3 mt-1 text-muted">
                                            <li>{{ __('Buka aplikasi m-Banking (BCA, Mandiri, BRI, BNI, Seabank, dll) atau e-Wallet (GoPay, OVO, Dana, ShopeePay).') }}</li>
                                            <li>{{ __('Pilih menu Scan / QRIS, lalu scan kode QR di atas (atau unggah gambar QR).') }}</li>
                                            <li>{{ __('Nominal otomatis terkunci Rp :amount. Pastikan nama merchant sesuai.', ['amount' => number_format($transaction->total_buyer_pay, 0, ',', '.')]) }}</li>
                                            <li>{{ __('Selesaikan pembayaran dan simpan tangkapan layar bukti transaksi.') }}</li>
                                        </ol>
                                    </div>
                                </div>
                            @elseif(!empty($qrisImagePath))
                                <div class="text-center py-2">
                                    <div class="mb-2">
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1.5 fw-semibold">
                                            <i class="fas fa-info-circle me-1"></i> {{ __('QRIS Resmi Toko') }}
                                        </span>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-3">{{ __('Scan Kode QRIS di Bawah Ini:') }}</h6>

                                    <div class="d-inline-block bg-white p-3 rounded-4 shadow-sm border mb-3">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($qrisImagePath) }}" alt="QRIS" class="img-fluid rounded-3" style="max-width: 260px;">
                                    </div>

                                    <div class="alert alert-warning small text-center mb-0 mx-auto" style="max-width: 520px;">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        {{ __('Harap masukkan nominal transfer secara manual dan tepat sebesar:') }}
                                        <strong class="text-dark d-block fs-5 mt-1">Rp {{ number_format($transaction->total_buyer_pay, 0, ',', '.') }}</strong>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-info text-center py-4 mb-0">
                                    <i class="fas fa-qrcode fs-2 mb-2 text-primary opacity-75"></i>
                                    <h6 class="fw-bold">{{ __('Metode QRIS Sedang Disiapkan') }}</h6>
                                    <p class="small text-muted mb-0">{{ __('Silakan gunakan opsi Transfer Rekening Bank pada tab sebelah untuk menyelesaikan pembayaran rekber.') }}</p>
                                </div>
                            @endif
                        </div>

                        {{-- Pane 2: Transfer Bank --}}
                        <div class="tab-pane fade" id="bank-method" role="tabpanel" aria-labelledby="bank-tab">
                            <div class="p-3 bg-white rounded-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted small fw-semibold">{{ __('Nominal yang Harus Ditransfer:') }}</span>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fs-5 fw-bold text-primary font-monospace">Rp {{ number_format($transaction->total_buyer_pay, 0, ',', '.') }}</span>
                                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-0.5" style="font-size: 0.72rem;" onclick="copyText('{{ $transaction->total_buyer_pay }}', 'Nominal berhasil disalin!')">
                                            <i class="fas fa-copy"></i> {{ __('Salin') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="p-3 bg-light rounded-3 border small mb-3" style="white-space: pre-line; line-height: 1.6;">
                                    {!! nl2br(e($bankInfo)) !!}
                                </div>

                                <div class="alert alert-light border small text-muted mb-0">
                                    <i class="fas fa-shield-alt text-success me-1"></i>
                                    {{ __('Dana deposit akan disimpan secara aman di rekening perantara resmi Dzulfikrialifajri Store dan baru diteruskan ke penjual setelah pembeli mengonfirmasi kepuasan atas produk/akun yang diterima.') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Bukti Pembayaran Status & Upload Form --}}
                    @if($transaction->deposit_proof)
                        <div class="alert alert-info py-2.5 px-3 small d-flex align-items-center justify-content-between mb-3 rounded-3">
                            <div>
                                <i class="fas fa-check-circle me-1.5 text-info"></i> {{ __('Bukti pembayaran telah diunggah. Menunggu verifikasi mutasi oleh Admin.') }}
                            </div>
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($transaction->deposit_proof) }}" target="_blank" class="fw-bold text-decoration-none btn btn-sm btn-light py-1 px-3 rounded-pill border">
                                <i class="fas fa-eye me-1"></i> {{ __('Lihat Bukti') }}
                            </a>
                        </div>
                    @endif

                    @if($isBuyer || $isAdmin)
                        <form action="{{ $isPublicView ? route('escrow.public.deposit', $transaction->public_token) : route('escrow.upload-deposit', $transaction->transaction_code) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <label class="form-label small fw-bold text-dark mb-1">
                                <i class="fas fa-cloud-arrow-up text-primary me-1"></i> {{ __('Unggah Bukti Pembayaran (QRIS / Bukti Transfer)') }}
                            </label>
                            <div class="input-group mb-2">
                                <input type="file" name="deposit_proof" class="form-control rounded-start-3" accept="image/*,application/pdf" required>
                                <button type="submit" class="btn btn-primary px-4 rounded-end-3 fw-semibold">
                                    <i class="fas fa-upload me-1"></i> {{ __('Kirim Bukti') }}
                                </button>
                            </div>
                            <div class="form-text small text-muted">{{ __('Mendukung format gambar JPG, PNG, WEBP, atau file PDF (Maksimal 10MB).') }}</div>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- Section 3: Serah Terima Barang / Akun --}}
        @if(in_array($transaction->status, [\App\Models\EscrowTransaction::STATUS_FUNDS_HELD, \App\Models\EscrowTransaction::STATUS_DELIVERED, \App\Models\EscrowTransaction::STATUS_WAITING_PAYOUT, \App\Models\EscrowTransaction::STATUS_COMPLETED, \App\Models\EscrowTransaction::STATUS_DISPUTED]))
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                        <i class="fas fa-key text-success"></i> {{ __('Data Serah Terima Barang / Akun') }}
                    </h6>

                    @if($transaction->delivery_proof)
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-success"><i class="fas fa-check-circle me-1"></i> {{ __('Diserahkan oleh Penjual') }}</span>
                                <span class="text-muted small">{{ $transaction->delivered_at ? $transaction->delivered_at->format('d M Y H:i') : '' }}</span>
                            </div>
                            <div class="p-3 bg-white rounded-2 border font-monospace small" style="white-space: pre-wrap; word-break: break-all;">
                                {{ $transaction->delivery_proof }}
                            </div>
                        </div>

                        {{-- Action for Buyer: Confirm Complete or Dispute --}}
                        @if($transaction->status === \App\Models\EscrowTransaction::STATUS_DELIVERED && ($isBuyer || $isAdmin))
                            <div class="p-3 bg-primary-subtle rounded-3 border border-primary-subtle mb-3">
                                <h6 class="fw-bold text-primary mb-1">{{ __('Periksa Akun / Produk Anda') }}</h6>
                                <p class="small text-muted mb-3">
                                    {{ __('Silakan login dan pastikan spesifikasi akun sesuai. Jika sudah sesuai, klik tombol di bawah untuk menyetujui rilis dana ke penjual.') }}
                                </p>
                                <div class="d-flex flex-wrap gap-2">
                                    <form action="{{ $isPublicView ? route('escrow.public.confirm', $transaction->public_token) : route('escrow.confirm-complete', $transaction->transaction_code) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold" onclick="return confirm('Apakah Anda yakin akun sudah valid dan dana siap dirilis ke penjual?')">
                                            <i class="fas fa-check-double me-1.5"></i> {{ __('Konfirmasi Selesai & Rilis Dana') }}
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-outline-danger rounded-pill px-4 fw-semibold" data-bs-toggle="modal" data-bs-target="#disputeModal">
                                        <i class="fas fa-exclamation-triangle me-1.5"></i> {{ __('Ada Masalah? Ajukan Sengketa') }}
                                    </button>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="alert alert-warning py-3 px-3 small mb-3">
                            <i class="fas fa-shield-halved me-1 text-warning"></i>
                            {{ __('Dana telah aman di rekening perantara toko. Penjual diwajibkan menyerahkan data akun/produk di bawah:') }}
                        </div>

                        @if($isSeller || $isAdmin)
                            <form action="{{ $isPublicView ? route('escrow.public.deliver', $transaction->public_token) : route('escrow.upload-delivery', $transaction->transaction_code) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">{{ __('Data Kredensial / Akun / Keterangan Penyerahan') }} <span class="text-danger">*</span></label>
                                    <textarea name="delivery_text" class="form-control font-monospace rounded-3" rows="4" placeholder="{{ __('Tuliskan email, username, password, link akses, atau instruksi login...') }}" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">{{ __('Lampiran File / Screenshot (Opsional)') }}</label>
                                    <input type="file" name="delivery_file" class="form-control rounded-3" accept="image/*,application/pdf,application/zip,text/plain">
                                </div>

                                <div class="p-3 bg-light rounded-3 mb-3 border">
                                    <label class="form-label small fw-bold text-muted mb-2">{{ __('Rekening Pencairan Penjual (Wajib Diisi untuk Menerima Dana)') }}</label>
                                    <div class="row g-2">
                                        <div class="col-12 col-md-4">
                                            <input type="text" name="seller_bank_name" class="form-control form-control-sm rounded-2" placeholder="{{ __('Bank / E-Wallet') }}" value="{{ $transaction->seller_bank_name }}" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <input type="text" name="seller_account_number" class="form-control form-control-sm rounded-2" placeholder="{{ __('Nomor Rekening') }}" value="{{ $transaction->seller_account_number }}" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <input type="text" name="seller_account_holder" class="form-control form-control-sm rounded-2" placeholder="{{ __('Atas Nama') }}" value="{{ $transaction->seller_account_holder }}" required>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold shadow-sm">
                                    <i class="fas fa-paper-plane me-1.5"></i> {{ __('Serahkan Data ke Pembeli') }}
                                </button>
                            </form>
                        @else
                            <p class="text-muted small mb-0">{{ __('Menunggu penjual menyerahkan barang/akun ke sistem rekber...') }}</p>
                        @endif
                    @endif
                </div>
            </div>
        @endif

        {{-- Sengketa Notice --}}
        @if($transaction->status === \App\Models\EscrowTransaction::STATUS_DISPUTED)
            <div class="card border-0 shadow-sm border-start border-danger border-4 mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-danger mb-2 d-flex align-items-center gap-2">
                        <i class="fas fa-gavel"></i> {{ __('Transaksi Sedang Dalam Mediasi / Sengketa') }}
                    </h6>
                    <p class="small text-muted mb-2">
                        <strong>{{ __('Alasan Komplain:') }}</strong> {{ $transaction->dispute_reason }}
                    </p>
                    <p class="small text-muted mb-0">
                        {{ __('Dana dibekukan secara aman. Admin sedang memeriksa riwayat bukti obrolan dan data akun untuk mengambil keputusan arbitrase.') }}
                    </p>
                </div>
            </div>
        @endif

        {{-- Section 4: Bukti Pencairan Selesai --}}
        @if($transaction->status === \App\Models\EscrowTransaction::STATUS_COMPLETED && $transaction->payout_proof)
            <div class="card border-0 shadow-sm border-start border-success border-4 mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-success mb-2 d-flex align-items-center gap-2">
                        <i class="fas fa-circle-check"></i> {{ __('Transaksi Selesai & Dana Telah Dicairkan') }}
                    </h6>
                    <p class="small text-muted mb-2">
                        {{ __('Admin telah mentransfer dana ke rekening penjual (:bank - :acc a.n :holder) pada :time.', [
                            'bank' => $transaction->seller_bank_name,
                            'acc' => $transaction->seller_account_number,
                            'holder' => $transaction->seller_account_holder,
                            'time' => $transaction->payout_at ? $transaction->payout_at->format('d M Y H:i') : ''
                        ]) }}
                    </p>
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($transaction->payout_proof) }}" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">
                        <i class="fas fa-file-invoice me-1"></i> {{ __('Lihat Bukti Transfer Admin') }}
                    </a>
                </div>
            </div>
        @endif
    </div>

    {{-- Right Column: Pihak Terlibat & Timeline Jejak Waktu --}}
    <div class="col-12 col-lg-4">
        {{-- Card 1: Pihak Terlibat --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-users text-primary"></i> {{ __('Pihak Terlibat Transaksi') }}
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-dark text-truncate">{{ $transaction->buyer_name }}</span>
                            <span class="badge bg-primary-subtle text-primary" style="font-size: 0.65rem;">{{ __('PEMBELI') }}</span>
                        </div>
                        <div class="small text-muted text-truncate">{{ $transaction->buyer_contact }}</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-dark text-truncate">{{ $transaction->seller_name }}</span>
                            <span class="badge bg-info-subtle text-info" style="font-size: 0.65rem;">{{ __('PENJUAL') }}</span>
                        </div>
                        <div class="small text-muted text-truncate">{{ $transaction->seller_contact }}</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div>
                        <div class="small fw-bold text-dark">{{ __('Dzulfikrialifajri Store') }}</div>
                        <div class="small text-muted" style="font-size: 0.75rem;">{{ __('Perantara Rekber Resmi (Admin)') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Akses Obrolan Ruang Rekber --}}
        <div class="card border-0 shadow-sm mb-4 bg-primary text-white" style="border-radius: 16px;">
            <div class="card-body p-3.5">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-comments fs-4"></i>
                        <h6 class="fw-bold mb-0 text-white">{{ __('Obrolan Ruang Rekber') }}</h6>
                    </div>
                    <span class="badge bg-white text-primary rounded-pill px-2.5 py-1 fw-bold">{{ $totalMessages }} {{ __('Pesan') }}</span>
                </div>
                <p class="small text-white-50 mb-3">
                    {{ __('Diskusikan serah terima akun, pertanyaan produk, atau konfirmasi langsung dengan pihak terkait & admin.') }}
                </p>
                <button type="button" class="btn btn-light text-primary w-100 rounded-pill fw-bold shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#escrowChatDrawer">
                    <i class="fas fa-comment-dots me-1.5"></i> {{ __('Buka Ruang Obrolan') }}
                </button>
            </div>
        </div>

        {{-- Card 3: Jejak Waktu (Audit Timeline) --}}
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-clock-rotate-left text-primary"></i> {{ __('Jejak Waktu Rekber') }}
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="timeline ps-2 py-1">
                    @forelse($transaction->timelines as $t)
                        <div class="d-flex gap-3 mb-3 position-relative">
                            <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary" style="width: 30px; height: 30px; min-width: 30px; z-index: 1;">
                                <i class="fas fa-circle-dot" style="font-size: 0.65rem;"></i>
                            </div>
                            <div>
                                <div class="small text-dark">{{ $t->description }}</div>
                                <small class="text-muted" style="font-size: 0.7rem;">{{ $t->created_at->format('d M Y, H:i') }}</small>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted small">{{ __('Belum ada riwayat timeline.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- FLOATING ACTION BUTTON (FAB) BUNDAR UNTUK CHAT RUANG REKBER --}}
<button type="button" 
        class="escrow-chat-fab" 
        data-bs-toggle="offcanvas" 
        data-bs-target="#escrowChatDrawer" 
        aria-controls="escrowChatDrawer" 
        title="{{ __('Buka Obrolan Ruang Rekber') }}">
    <i class="fas fa-comment-dots fs-4"></i>
    @if($totalMessages > 0)
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light shadow-sm" style="font-size: 0.72rem;">
            {{ $totalMessages }}
        </span>
    @endif
</button>

{{-- OFFCANVAS / DRAWER OBROLAN RUANG REKBER --}}
<div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="escrowChatDrawer" aria-labelledby="escrowChatDrawerLabel" style="width: 440px; max-width: 100vw;">
    <div class="offcanvas-header border-bottom bg-light py-3">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="fas fa-comments"></i>
            </div>
            <div>
                <h6 class="offcanvas-title fw-bold text-dark mb-0" id="escrowChatDrawerLabel">{{ __('Obrolan Ruang Rekber') }}</h6>
                <small class="text-muted font-monospace" style="font-size: 0.75rem;">{{ $transaction->transaction_code }}</small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-3 bg-light" style="overflow-y: hidden;">
        {{-- Area Pesan Chat (Scrollable) --}}
        <div class="flex-grow-1 overflow-y-auto pe-1 mb-3 d-flex flex-column gap-2.5" id="chatMessagesContainer">
            @forelse($transaction->messages as $msg)
                @php
                    $isMyMsg = false;
                    if ($currentUser) {
                        $isMyMsg = ($msg->sender_id === $currentUser->id);
                    } else {
                        // Public token view: cek apakah pengirim sesuai creator role partner
                        $isMyMsg = ($msg->sender_role === ($transaction->creator_role === 'buyer' ? 'seller' : 'buyer'));
                    }
                    $isAdminMsg = ($msg->sender_role === 'admin');
                @endphp

                <div class="d-flex flex-column {{ $isMyMsg ? 'align-items-end' : 'align-items-start' }}">
                    <div class="small text-muted mb-1 px-1" style="font-size: 0.7rem;">
                        <strong class="{{ $isAdminMsg ? 'text-danger' : ($isMyMsg ? 'text-primary' : 'text-dark') }}">{{ $msg->sender_name }}</strong>
                        <span class="badge {{ $isAdminMsg ? 'bg-danger-subtle text-danger' : ($isMyMsg ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary') }} ms-1" style="font-size: 0.62rem;">
                            {{ strtoupper($msg->sender_role) }}
                        </span>
                    </div>

                    <div class="chat-bubble {{ $isMyMsg ? 'chat-bubble-me' : ($isAdminMsg ? 'chat-bubble-admin' : 'chat-bubble-other') }}">
                        <p class="mb-1 text-break">{{ $msg->message }}</p>
                        @if($msg->attachment_path)
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($msg->attachment_path) }}" target="_blank" class="small text-decoration-none d-inline-flex align-items-center gap-1 mt-1 p-1.5 rounded bg-white bg-opacity-75 border">
                                <i class="fas fa-paperclip"></i>
                                <span>{{ __('Lampiran File') }}</span>
                            </a>
                        @endif
                        <div class="text-end text-muted mt-1" style="font-size: 0.65rem;">
                            {{ $msg->created_at->format('H:i') }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-muted my-auto">
                    <div class="rounded-circle bg-white border p-3 d-inline-flex mb-2 shadow-sm">
                        <i class="far fa-comments fs-3 text-primary opacity-50"></i>
                    </div>
                    <div class="fw-semibold small text-dark">{{ __('Belum ada pesan obrolan.') }}</div>
                    <p class="small text-muted mb-0 px-4" style="font-size: 0.78rem;">
                        {{ __('Gunakan ruang obrolan ini untuk berdiskusi dengan aman di bawah pengawasan Admin.') }}
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Form Kirim Pesan --}}
        <div class="bg-white rounded-3 p-2.5 shadow-sm border mt-auto">
            <form action="{{ $isPublicView ? route('escrow.public.message', $transaction->public_token) : route('escrow.send-message', $transaction->transaction_code) }}" method="POST" enctype="multipart/form-data" id="escrowChatForm">
                @csrf
                <div class="input-group mb-2">
                    <input type="text" name="message" id="chatInputMessage" class="form-control border-0 rounded-start-pill bg-light px-3" placeholder="{{ __('Tulis pesan Anda...') }}" required autocomplete="off">
                    <button type="submit" class="btn btn-primary rounded-end-pill px-3.5 shadow-sm">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center justify-content-between px-1">
                    <label class="btn btn-xs btn-outline-secondary rounded-pill px-2.5 py-1 small cursor-pointer mb-0" style="font-size: 0.72rem;">
                        <i class="fas fa-paperclip me-1"></i> <span id="fileAttachmentLabel">{{ __('Lampiran') }}</span>
                        <input type="file" name="attachment" id="chatAttachmentInput" class="d-none" accept="image/*,application/pdf" onchange="updateAttachmentLabel(this)">
                    </label>
                    <span class="text-muted" style="font-size: 0.7rem;"><i class="fas fa-lock me-1 text-success"></i>{{ __('Diawasi Admin') }}</span>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Dispute Modal --}}
<div class="modal fade" id="disputeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header border-bottom p-3">
                <h6 class="modal-title fw-bold text-danger">
                    <i class="fas fa-gavel me-1.5"></i> {{ __('Ajukan Sengketa / Komplain Rekber') }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ $isPublicView ? route('escrow.public.dispute', $transaction->public_token) : route('escrow.open-dispute', $transaction->transaction_code) }}" method="POST">
                @csrf
                <div class="modal-body p-3">
                    <p class="small text-muted mb-3">
                        {{ __('Pengajuan sengketa akan membekukan pelepasan dana secara otomatis. Admin akan menengahi dan memverifikasi data kedua belah pihak.') }}
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Jelaskan Kendala atau Ketidaksesuaian Akun') }} <span class="text-danger">*</span></label>
                        <textarea name="dispute_reason" class="form-control rounded-3" rows="3" placeholder="{{ __('Contoh: Password salah, email sudah dikunci penjual, spesifikasi tidak sesuai...') }}" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-semibold">{{ __('Buka Sengketa') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if(!empty($dynamicQris))
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const qrContainer = document.getElementById("qrcode");
        if (qrContainer) {
            new QRCode(qrContainer, {
                text: "{{ $dynamicQris }}",
                width: 240,
                height: 240,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M
            });
        }
    });

    function downloadQrisImage() {
        const qrContainer = document.getElementById("qrcode");
        if (!qrContainer) return;
        const img = qrContainer.querySelector('img');
        const canvas = qrContainer.querySelector('canvas');
        let dataUrl = null;

        if (img && img.src) {
            dataUrl = img.src;
        } else if (canvas) {
            dataUrl = canvas.toDataURL("image/png");
        }

        if (dataUrl) {
            const link = document.createElement('a');
            link.download = "QRIS-Rekber-{{ $transaction->transaction_code }}.png";
            link.href = dataUrl;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            alert('Gambar QRIS sedang dimuat. Silakan coba sesaat lagi.');
        }
    }
</script>
@endif

<script>
    function copyText(text, successMsg) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showToast(successMsg || 'Berhasil disalin!');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        prompt('Salin teks berikut:', text);
    }

    function showToast(msg) {
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: msg,
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            alert(msg);
        }
    }

    function updateAttachmentLabel(input) {
        const label = document.getElementById('fileAttachmentLabel');
        if (input.files && input.files.length > 0) {
            label.textContent = input.files[0].name.substring(0, 15) + '...';
        } else {
            label.textContent = 'Lampiran';
        }
    }

    // Auto-scroll chat drawer to bottom on open
    document.addEventListener('DOMContentLoaded', function() {
        const chatDrawer = document.getElementById('escrowChatDrawer');
        const msgContainer = document.getElementById('chatMessagesContainer');
        const chatInput = document.getElementById('chatInputMessage');

        if (chatDrawer && msgContainer) {
            chatDrawer.addEventListener('shown.bs.offcanvas', function () {
                msgContainer.scrollTop = msgContainer.scrollHeight;
                if (chatInput) {
                    chatInput.focus();
                }
            });
        }
    });
</script>
@endpush
