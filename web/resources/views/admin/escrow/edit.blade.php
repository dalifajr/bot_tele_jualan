@extends('layouts.app')

@section('title', __('Admin: Edit Rekber ') . $transaction->transaction_code)
@section('page_subtitle', __('Admin / Rekber / Edit'))

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="{{ route('admin.escrow.show', $transaction->transaction_code) }}" class="btn btn-sm btn-link text-muted p-0 text-decoration-none mb-1">
                    <i class="fas fa-arrow-left me-1"></i> {{ __('Kembali ke Detail Rekber') }}
                </a>
                <h4 class="fw-bold mb-0 text-dark">{{ __('Edit Transaksi Rekber') }}: <span class="text-primary">{{ $transaction->transaction_code }}</span></h4>
            </div>
            <span class="badge bg-{{ $transaction->status_badge }} rounded-pill px-3 py-1.5 fw-semibold">
                {{ $transaction->status_label }}
            </span>
        </div>

        <form action="{{ route('admin.escrow.update', $transaction->transaction_code) }}" method="POST" id="adminEditEscrowForm">
            @csrf
            @method('PUT')

            {{-- Card 1: Status Override & Catatan Admin --}}
            <div class="card border-0 shadow-sm mb-3 border-start border-warning border-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-2 text-warning d-flex align-items-center gap-2">
                        <i class="fas fa-sliders"></i> {{ __('Override Status & Catatan Admin') }}
                    </h6>
                    <p class="small text-muted mb-3">
                        {{ __('Admin memiliki kewenangan mengubah status transaksi rekber jika terjadi kendala teknis atau kebutuhan pembatalan darurat.') }}
                    </p>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Status Transaksi Rekber') }} <span class="text-danger">*</span></label>
                            <select name="status" class="form-select rounded-3 fw-semibold">
                                <option value="waiting_deposit" {{ $transaction->status === 'waiting_deposit' ? 'selected' : '' }}>{{ __('Menunggu Pembayaran (waiting_deposit)') }}</option>
                                <option value="deposit_submitted" {{ $transaction->status === 'deposit_submitted' ? 'selected' : '' }}>{{ __('Deposit Diunggah / Butuh Verifikasi (deposit_submitted)') }}</option>
                                <option value="funds_held" {{ $transaction->status === 'funds_held' ? 'selected' : '' }}>{{ __('Dana Diamankan / Siap Kirim (funds_held)') }}</option>
                                <option value="delivered" {{ $transaction->status === 'delivered' ? 'selected' : '' }}>{{ __('Barang Diserahkan / Masa Inspeksi (delivered)') }}</option>
                                <option value="waiting_payout" {{ $transaction->status === 'waiting_payout' ? 'selected' : '' }}>{{ __('Menunggu Pencairan ke Penjual (waiting_payout)') }}</option>
                                <option value="completed" {{ $transaction->status === 'completed' ? 'selected' : '' }}>{{ __('Selesai & Dicairkan (completed)') }}</option>
                                <option value="disputed" {{ $transaction->status === 'disputed' ? 'selected' : '' }}>{{ __('Dalam Sengketa / Mediasi (disputed)') }}</option>
                                <option value="refunded" {{ $transaction->status === 'refunded' ? 'selected' : '' }}>{{ __('Dikembalikan ke Pembeli / Refund (refunded)') }}</option>
                                <option value="cancelled" {{ $transaction->status === 'cancelled' ? 'selected' : '' }}>{{ __('Dibatalkan (cancelled)') }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Catatan Khusus Admin') }}</label>
                            <input type="text" name="admin_notes" class="form-control rounded-3" value="{{ old('admin_notes', $transaction->admin_notes) }}" placeholder="{{ __('Catatan internal atau alasan perubahan status...') }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 2: Informasi Pihak Bertransaksi --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="fas fa-users"></i> {{ __('Data Pihak Pembeli & Penjual') }}
                    </h6>

                    <div class="row g-4">
                        <div class="col-12 col-md-6 border-end-md">
                            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-shopping-cart text-primary me-1"></i> {{ __('Pembeli (Buyer)') }}</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Nama Pembeli') }} <span class="text-danger">*</span></label>
                                <input type="text" name="buyer_name" class="form-control rounded-3" value="{{ old('buyer_name', $transaction->buyer_name) }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Kontak Pembeli') }} <span class="text-danger">*</span></label>
                                <input type="text" name="buyer_contact" class="form-control rounded-3" value="{{ old('buyer_contact', $transaction->buyer_contact) }}" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-store text-success me-1"></i> {{ __('Penjual (Seller)') }}</h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Nama Penjual') }} <span class="text-danger">*</span></label>
                                <input type="text" name="seller_name" class="form-control rounded-3" value="{{ old('seller_name', $transaction->seller_name) }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Kontak Penjual') }} <span class="text-danger">*</span></label>
                                <input type="text" name="seller_contact" class="form-control rounded-3" value="{{ old('seller_contact', $transaction->seller_contact) }}" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 3: Spesifikasi Produk & Durasi Inspeksi --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="fas fa-box-open"></i> {{ __('Spesifikasi Produk') }}
                    </h6>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Judul Transaksi Rekber') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control rounded-3" value="{{ old('title', $transaction->title) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Deskripsi Spesifikasi') }}</label>
                        <textarea name="description" class="form-control rounded-3" rows="3">{{ old('description', $transaction->description) }}</textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Batas Waktu Pemeriksaan Akun (Jam)') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="inspection_hours" class="form-control rounded-start-3" value="{{ old('inspection_hours', $transaction->inspection_hours) }}" min="1" max="168" required>
                                <span class="input-group-text bg-light text-muted">{{ __('Jam') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 4: Nilai Transaksi & Rekening Pencairan Penjual --}}
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="fas fa-money-bill-wave"></i> {{ __('Nilai Transaksi & Rekening Pencairan') }}
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Nilai Barang (Rp)') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted">Rp</span>
                                <input type="number" name="amount" id="amountInput" class="form-control form-control-lg fw-bold rounded-end-3" value="{{ old('amount', (int)$transaction->amount) }}" min="10000" step="1000" required oninput="calculateEscrowFee()">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Penanggung Fee Rekber') }} <span class="text-danger">*</span></label>
                            <select name="fee_payer" id="feePayerSelect" class="form-select form-select-lg rounded-3" onchange="calculateEscrowFee()">
                                <option value="buyer" {{ $transaction->fee_payer === 'buyer' ? 'selected' : '' }}>{{ __('Ditanggung Pembeli (Buyer)') }}</option>
                                <option value="seller" {{ $transaction->fee_payer === 'seller' ? 'selected' : '' }}>{{ __('Dipotong dari Penjual (Seller)') }}</option>
                                <option value="split" {{ $transaction->fee_payer === 'split' ? 'selected' : '' }}>{{ __('Dibagi Dua (Split 50:50)') }}</option>
                            </select>
                        </div>
                    </div>

                    {{-- Live Fee Breakdown Box --}}
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">{{ __('Nilai Pokok:') }}</span>
                            <span class="fw-semibold text-dark" id="displayAmount">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">{{ __('Biaya Admin Rekber (:percent%):', ['percent' => $feePercent]) }}</span>
                            <span class="fw-semibold text-info" id="displayFee">Rp {{ number_format($transaction->fee_amount, 0, ',', '.') }}</span>
                        </div>
                        <hr class="my-2 opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark">{{ __('Total Tagihan Pembeli (Termasuk Kode Unik):') }}</span>
                            <span class="fs-5 fw-bold text-primary" id="displayTotalBuyer">Rp {{ number_format($transaction->total_buyer_pay, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">{{ __('Total Bersih Pencairan Penjual:') }}</span>
                            <span class="fw-bold text-success" id="displayTotalSeller">Rp {{ number_format($transaction->total_seller_receive, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Rekening Pencairan Penjual --}}
                    <div class="pt-2">
                        <label class="form-label small fw-bold text-muted">{{ __('Rekening Tujuan Pencairan Penjual') }}</label>
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <input type="text" name="seller_bank_name" class="form-control rounded-3" placeholder="{{ __('Bank / E-Wallet') }}" value="{{ old('seller_bank_name', $transaction->seller_bank_name) }}">
                            </div>
                            <div class="col-12 col-md-4">
                                <input type="text" name="seller_account_number" class="form-control rounded-3" placeholder="{{ __('Nomor Rekening / No HP') }}" value="{{ old('seller_account_number', $transaction->seller_account_number) }}">
                            </div>
                            <div class="col-12 col-md-4">
                                <input type="text" name="seller_account_holder" class="form-control rounded-3" placeholder="{{ __('Atas Nama Pemilik') }}" value="{{ old('seller_account_holder', $transaction->seller_account_holder) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-5">
                <button type="button" class="btn btn-outline-danger rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#deleteEscrowModal">
                    <i class="fas fa-trash-alt me-1.5"></i> {{ __('Hapus Transaksi') }}
                </button>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.escrow.show', $transaction->transaction_code) }}" class="btn btn-light rounded-pill px-4">{{ __('Batal') }}</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-semibold shadow-sm">
                        <i class="fas fa-save me-1.5"></i> {{ __('Simpan Perubahan') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Delete Modal --}}
<div class="modal fade" id="deleteEscrowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header border-bottom p-3">
                <h6 class="modal-title fw-bold text-danger"><i class="fas fa-triangle-exclamation me-1.5"></i> {{ __('Konfirmasi Hapus Transaksi Rekber') }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px; font-size: 1.6rem;">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h6 class="fw-bold text-dark">{{ __('Hapus Permanen Transaksi Ini?') }}</h6>
                <p class="small text-muted mb-0">
                    {{ __('Transaksi') }} <code>{{ $transaction->transaction_code }}</code> {{ __('beserta seluruh pesan obrolan, riwayat audit timeline, dan bukti transfer lampiran akan dihapus permanen dari sistem.') }}
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                <form action="{{ route('admin.escrow.destroy', $transaction->transaction_code) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger rounded-pill px-4">{{ __('Ya, Hapus Permanen') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const feePercent = {{ $feePercent }};
    const currentUniqueCode = {{ (int)($transaction->unique_code ?: 0) }};

    function formatRupiah(num) {
        return 'Rp ' + Number(num).toLocaleString('id-ID');
    }

    function calculateEscrowFee() {
        const amount = parseFloat(document.getElementById('amountInput').value) || 0;
        const payer = document.getElementById('feePayerSelect').value;

        let fee = Math.round((amount * feePercent) / 100);
        let totalBuyer = amount;
        let totalSeller = amount;

        if (payer === 'seller') {
            totalBuyer = amount;
            totalSeller = Math.max(0, amount - fee);
        } else if (payer === 'split') {
            const halfFee = Math.round(fee / 2);
            totalBuyer = amount + halfFee;
            totalSeller = Math.max(0, amount - (fee - halfFee));
        } else {
            totalBuyer = amount + fee;
            totalSeller = amount;
        }

        totalBuyer += currentUniqueCode;

        document.getElementById('displayAmount').textContent = formatRupiah(amount);
        document.getElementById('displayFee').textContent = formatRupiah(fee);
        document.getElementById('displayTotalBuyer').textContent = formatRupiah(totalBuyer);
        document.getElementById('displayTotalSeller').textContent = formatRupiah(totalSeller);
    }
</script>
@endsection
