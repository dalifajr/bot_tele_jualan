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
@endphp

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
        {{-- Copy Public Link Button --}}
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="copyPublicLink('{{ $publicUrl }}')">
            <i class="fas fa-link me-1"></i> {{ __('Salin Link Penjual/Pihak Luar') }}
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
    {{-- Left Column: Transaksi Detail & Aksi --}}
    <div class="col-12 col-lg-7">
        {{-- Section 1: Ringkasan Nilai --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-file-invoice-dollar text-primary"></i> {{ __('Rincian Keuangan Rekber') }}
                </h6>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">{{ __('Nilai Barang') }}</small>
                        <strong class="text-dark fs-6">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">{{ __('Biaya Rekber') }}</small>
                        <strong class="text-info fs-6">Rp {{ number_format($transaction->fee_amount, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">{{ __('Total Bayar (Pembeli)') }}</small>
                        <strong class="text-primary fs-6">Rp {{ number_format($transaction->total_buyer_pay, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">{{ __('Total Cair (Penjual)') }}</small>
                        <strong class="text-success fs-6">Rp {{ number_format($transaction->total_seller_receive, 0, ',', '.') }}</strong>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top small text-muted d-flex flex-wrap gap-3">
                    <div><i class="fas fa-user me-1 text-primary"></i> <strong>{{ __('Pembeli:') }}</strong> {{ $transaction->buyer_name }} ({{ $transaction->buyer_contact }})</div>
                    <div><i class="fas fa-store me-1 text-info"></i> <strong>{{ __('Penjual:') }}</strong> {{ $transaction->seller_name }} ({{ $transaction->seller_contact }})</div>
                </div>
            </div>
        </div>

        {{-- Section 2: Instruksi Transfer Deposit (Jika menunggu deposit atau verifikasi) --}}
        @if(in_array($transaction->status, [\App\Models\EscrowTransaction::STATUS_WAITING_DEPOSIT, \App\Models\EscrowTransaction::STATUS_DEPOSIT_SUBMITTED]))
            <div class="card border-0 shadow-sm mb-4 border-start border-primary border-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-primary mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-building-columns"></i> {{ __('Instruksi Pembayaran Rekber Resmi Toko') }}
                        </h6>
                        <span class="badge bg-primary-subtle text-primary">{{ __('Rekening Escrow') }}</span>
                    </div>
                    <p class="text-muted small mb-3">
                        {{ __('Pembeli silakan mentransfer dana sesuai nominal di bawah ke rekening perantara resmi Dzulfikrialifajri Store:') }}
                    </p>

                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">{{ __('Total yang Harus Ditransfer:') }}</span>
                            <span class="fs-5 fw-bold text-primary">Rp {{ number_format($transaction->total_buyer_pay, 0, ',', '.') }}</span>
                        </div>
                        <div class="bg-white p-3 rounded-2 border small" style="white-space: pre-line;">
                            {!! nl2br(e($bankInfo)) !!}
                        </div>
                    </div>

                    @if($transaction->deposit_proof)
                        <div class="alert alert-info py-2 px-3 small d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <i class="fas fa-check-circle me-1"></i> {{ __('Bukti pembayaran telah diunggah. Menunggu verifikasi admin.') }}
                            </div>
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($transaction->deposit_proof) }}" target="_blank" class="fw-bold text-decoration-none">
                                <i class="fas fa-eye me-1"></i> {{ __('Lihat Bukti') }}
                            </a>
                        </div>
                    @endif

                    @if($isBuyer || $isAdmin)
                        <form action="{{ $isPublicView ? route('escrow.public.deposit', $transaction->public_token) : route('escrow.upload-deposit', $transaction->transaction_code) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <label class="form-label small fw-bold text-muted">{{ __('Unggah Bukti Transfer Bank') }}</label>
                            <div class="input-group mb-2">
                                <input type="file" name="deposit_proof" class="form-control rounded-start-3" accept="image/*,application/pdf" required>
                                <button type="submit" class="btn btn-primary px-4 rounded-end-3 fw-semibold">
                                    <i class="fas fa-upload me-1"></i> {{ __('Kirim Bukti') }}
                                </button>
                            </div>
                            <div class="form-text small text-muted">{{ __('Format JPG, PNG, WEBP, atau PDF. Maksimal 10MB.') }}</div>
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

    {{-- Right Column: Audit Timeline & Internal Chat Thread --}}
    <div class="col-12 col-lg-5">
        {{-- Card Tabs: Timeline & Diskusi --}}
        <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
            <div class="card-header bg-white border-bottom p-3">
                <ul class="nav nav-pills card-header-pills gap-2" id="rekberTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link rounded-pill py-1 px-3 small active" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button" role="tab">
                            <i class="fas fa-clock-rotate-left me-1"></i> {{ __('Jejak Waktu') }}
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link rounded-pill py-1 px-3 small" id="chat-tab" data-bs-toggle="tab" data-bs-target="#chat-pane" type="button" role="tab">
                            <i class="fas fa-comments me-1"></i> {{ __('Obrolan Ruang') }} ({{ $transaction->messages->count() }})
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-3">
                <div class="tab-content" id="rekberTabContent">
                    {{-- Pane 1: Timeline --}}
                    <div class="tab-pane fade show active" id="timeline-pane" role="tabpanel">
                        <div class="timeline ps-2 py-2">
                            @foreach($transaction->timelines as $t)
                                <div class="d-flex gap-3 mb-3 position-relative">
                                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary" style="width: 32px; height: 32px; min-width: 32px; z-index: 1;">
                                        <i class="fas fa-circle-dot" style="font-size: 0.65rem;"></i>
                                    </div>
                                    <div>
                                        <div class="small text-dark">{{ $t->description }}</div>
                                        <small class="text-muted" style="font-size: 0.7rem;">{{ $t->created_at->format('d M Y, H:i') }}</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Pane 2: Messages --}}
                    <div class="tab-pane fade" id="chat-pane" role="tabpanel">
                        <div class="overflow-y-auto mb-3 p-2 bg-light rounded-3" style="max-height: 350px; min-height: 200px;">
                            @forelse($transaction->messages as $msg)
                                <div class="mb-2 p-2.5 bg-white rounded-3 shadow-sm border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="small text-dark">{{ $msg->sender_name }}</strong>
                                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">{{ strtoupper($msg->sender_role) }}</span>
                                    </div>
                                    <p class="small text-muted mb-1 text-break">{{ $msg->message }}</p>
                                    @if($msg->attachment_path)
                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($msg->attachment_path) }}" target="_blank" class="small text-primary text-decoration-none d-inline-block">
                                            <i class="fas fa-paperclip me-1"></i> {{ __('Lampiran File') }}
                                        </a>
                                    @endif
                                    <div class="text-end text-muted" style="font-size: 0.65rem;">{{ $msg->created_at->format('H:i') }}</div>
                                </div>
                            @empty
                                <div class="text-center py-4 text-muted small">
                                    <i class="far fa-comments fs-3 mb-2 opacity-50"></i>
                                    <div>{{ __('Belum ada pesan di ruang rekber ini.') }}</div>
                                </div>
                            @endforelse
                        </div>

                        {{-- Send Message Form --}}
                        <form action="{{ $isPublicView ? route('escrow.public.message', $transaction->public_token) : route('escrow.send-message', $transaction->transaction_code) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="input-group mb-1">
                                <input type="text" name="message" class="form-control rounded-start-pill" placeholder="{{ __('Tulis pesan untuk pihak terkait & admin...') }}" required>
                                <button type="submit" class="btn btn-primary rounded-end-pill px-3">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
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

<script>
    function copyPublicLink(url) {
        navigator.clipboard.writeText(url).then(function() {
            Swal.fire({
                icon: 'success',
                title: 'Tautan Tersalin!',
                text: 'Kirimkan tautan ini kepada penjual/pembeli luar untuk mengakses ruang rekber.',
                timer: 2500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }).catch(function() {
            prompt('Salin link berikut:', url);
        });
    }
</script>
@endsection
