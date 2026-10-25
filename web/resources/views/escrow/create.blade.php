@extends('layouts.app')

@section('title', __('Buat Transaksi Rekber Baru'))
@section('page_subtitle', __('Rekber / Baru'))

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="{{ route('escrow.index') }}" class="btn btn-sm btn-link text-muted p-0 text-decoration-none mb-1">
                    <i class="fas fa-arrow-left me-1"></i> {{ __('Kembali ke Daftar Rekber') }}
                </a>
                <h4 class="fw-bold mb-0 text-dark">{{ __('Inisiasi Transaksi Rekber') }}</h4>
            </div>
            <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1.5 rounded-pill">
                <i class="fas fa-shield-alt me-1"></i> {{ __('Escrow Terlindungi') }}
            </span>
        </div>

        <form action="{{ route('escrow.store') }}" method="POST" id="createEscrowForm">
            @csrf
            
            {{-- Card 1: Peran & Pihak Lawan --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="fas fa-users"></i> {{ __('1. Tentukan Peran & Pihak Transaksi') }}
                    </h6>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Peran Anda dalam Transaksi Ini') }} <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="creator_role" id="role_buyer" value="buyer" checked onchange="updateRoleLabels()">
                                <label class="btn btn-outline-primary w-100 py-2.5 rounded-3 text-start d-flex align-items-center gap-2" for="role_buyer">
                                    <i class="fas fa-shopping-cart fs-5"></i>
                                    <div>
                                        <div class="fw-bold small">{{ __('Saya adalah Pembeli') }}</div>
                                        <div class="text-muted" style="font-size: 0.72rem;">{{ __('Saya akan mentransfer dana ke rekber') }}</div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="creator_role" id="role_seller" value="seller" onchange="updateRoleLabels()">
                                <label class="btn btn-outline-primary w-100 py-2.5 rounded-3 text-start d-flex align-items-center gap-2" for="role_seller">
                                    <i class="fas fa-store fs-5"></i>
                                    <div>
                                        <div class="fw-bold small">{{ __('Saya adalah Penjual') }}</div>
                                        <div class="text-muted" style="font-size: 0.72rem;">{{ __('Saya yang menyerahkan barang/akun') }}</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted" id="partner_name_label">{{ __('Nama Penjual (Luar Platform)') }} <span class="text-danger">*</span></label>
                            <input type="text" name="partner_name" class="form-control rounded-3" placeholder="{{ __('Contoh: Budi Santoso / SellerX') }}" required value="{{ old('partner_name') }}">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted" id="partner_contact_label">{{ __('Kontak Penjual (No WA / Username Telegram)') }} <span class="text-danger">*</span></label>
                            <input type="text" name="partner_contact" class="form-control rounded-3" placeholder="{{ __('Contoh: @seller_tele atau 08123456789') }}" required value="{{ old('partner_contact') }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 2: Detail Barang / Layanan --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="fas fa-box-open"></i> {{ __('2. Spesifikasi Produk / Jasa') }}
                    </h6>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Judul Barang / Akun yang Ditransaksikan') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control rounded-3" placeholder="{{ __('Contoh: Akun GitHub Student Pack 2026') }}" required value="{{ old('title') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Deskripsi Spesifikasi & Ketentuan Garansi') }}</label>
                        <textarea name="description" class="form-control rounded-3" rows="3" placeholder="{{ __('Tuliskan rincian apa saja yang harus diserahkan (misal: email utama, first mail, garansi 7 hari, dsb)...') }}">{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label class="form-label small fw-bold text-muted">{{ __('Batas Waktu Pemeriksaan Akun (Inspeksi)') }}</label>
                        <div class="input-group">
                            <input type="number" name="inspection_hours" class="form-control rounded-start-3" value="{{ old('inspection_hours', 24) }}" min="1" max="168">
                            <span class="input-group-text bg-light text-muted">{{ __('Jam setelah penjual kirim data') }}</span>
                        </div>
                        <div class="form-text small text-muted">{{ __('Waktu yang diberikan kepada pembeli untuk login & memverifikasi akun sebelum dana dicairkan.') }}</div>
                    </div>
                </div>
            </div>

            {{-- Card 3: Nominal & Perhitungan Fee --}}
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="fas fa-money-bill-wave"></i> {{ __('3. Nilai Transaksi & Biaya Rekber') }}
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Nilai Transaksi Barang (Rp)') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted">Rp</span>
                                <input type="number" name="amount" id="amountInput" class="form-control form-control-lg fw-bold rounded-end-3" placeholder="0" min="10000" step="1000" required value="{{ old('amount', 50000) }}" oninput="calculateEscrowFee()">
                            </div>
                            <div class="form-text small text-muted">{{ __('Minimal transaksi Rp 10.000') }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Biaya Rekber Ditanggung Oleh') }} <span class="text-danger">*</span></label>
                            <select name="fee_payer" id="feePayerSelect" class="form-select form-select-lg rounded-3" onchange="calculateEscrowFee()">
                                <option value="buyer" selected>{{ __('Ditanggung Pembeli (Buyer)') }}</option>
                                <option value="seller">{{ __('Dipotong dari Penjual (Seller)') }}</option>
                                <option value="split">{{ __('Dibagi Dua (Split 50:50)') }}</option>
                            </select>
                        </div>
                    </div>

                    {{-- Live Fee Breakdown Box --}}
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">{{ __('Nilai Barang:') }}</span>
                            <span class="fw-semibold text-dark" id="displayAmount">Rp 50.000</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">{{ __('Biaya Admin Rekber (:percent%):', ['percent' => $feePercent]) }}</span>
                            <span class="fw-semibold text-info" id="displayFee">Rp 750</span>
                        </div>
                        <hr class="my-2 opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark">{{ __('Total yang Harus Ditransfer Pembeli:') }}</span>
                            <span class="fs-5 fw-bold text-primary" id="displayTotalBuyer">Rp 50.750</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">{{ __('Total Bersih yang Diterima Penjual:') }}</span>
                            <span class="fw-bold text-success" id="displayTotalSeller">Rp 50.000</span>
                        </div>
                    </div>

                    {{-- Optional Seller Bank Target --}}
                    <div class="mt-3 pt-3 border-top">
                        <label class="form-label small fw-bold text-muted">{{ __('Rekening Tujuan Pencairan Penjual (Bisa diisi nanti)') }}</label>
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <input type="text" name="seller_bank_name" class="form-control rounded-3" placeholder="{{ __('Bank / E-Wallet (BCA/Dana/dll)') }}" value="{{ old('seller_bank_name') }}">
                            </div>
                            <div class="col-12 col-md-4">
                                <input type="text" name="seller_account_number" class="form-control rounded-3" placeholder="{{ __('Nomor Rekening / No HP') }}" value="{{ old('seller_account_number') }}">
                            </div>
                            <div class="col-12 col-md-4">
                                <input type="text" name="seller_account_holder" class="form-control rounded-3" placeholder="{{ __('Atas Nama Pemilik') }}" value="{{ old('seller_account_holder') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-5">
                <a href="{{ route('escrow.index') }}" class="btn btn-light rounded-pill px-4">{{ __('Batal') }}</a>
                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-semibold shadow-sm">
                    <i class="fas fa-check me-1.5"></i> {{ __('Buat Tiket Rekber') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const feePercent = {{ $feePercent }};

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
            // buyer
            totalBuyer = amount + fee;
            totalSeller = amount;
        }

        document.getElementById('displayAmount').textContent = formatRupiah(amount);
        document.getElementById('displayFee').textContent = formatRupiah(fee);
        document.getElementById('displayTotalBuyer').textContent = formatRupiah(totalBuyer);
        document.getElementById('displayTotalSeller').textContent = formatRupiah(totalSeller);
    }

    function updateRoleLabels() {
        const isBuyer = document.getElementById('role_buyer').checked;
        const nameLabel = document.getElementById('partner_name_label');
        const contactLabel = document.getElementById('partner_contact_label');

        if (isBuyer) {
            nameLabel.innerHTML = '{{ __("Nama Penjual (Luar Platform)") }} <span class="text-danger">*</span>';
            contactLabel.innerHTML = '{{ __("Kontak Penjual (No WA / Username Telegram)") }} <span class="text-danger">*</span>';
        } else {
            nameLabel.innerHTML = '{{ __("Nama Pembeli (Luar Platform)") }} <span class="text-danger">*</span>';
            contactLabel.innerHTML = '{{ __("Kontak Pembeli (No WA / Username Telegram)") }} <span class="text-danger">*</span>';
        }
    }

    document.addEventListener('DOMContentLoaded', calculateEscrowFee);
</script>
@endsection
