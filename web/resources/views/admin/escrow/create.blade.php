@extends('layouts.app')

@section('title', __('Admin: Inisiasi Rekber Baru'))
@section('page_subtitle', __('Admin / Rekber / Inisiasi Baru'))

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <a href="{{ route('admin.escrow.index') }}" class="btn btn-sm btn-link text-muted p-0 text-decoration-none mb-1">
                    <i class="fas fa-arrow-left me-1"></i> {{ __('Kembali ke Daftar Rekber') }}
                </a>
                <h4 class="fw-bold mb-0 text-dark">{{ __('Inisiasi Ruang Rekber oleh Admin') }}</h4>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill">
                <i class="fas fa-shield-halved me-1"></i> {{ __('Escrow Resmi Platform') }}
            </span>
        </div>

        <form action="{{ route('admin.escrow.store') }}" method="POST" id="adminCreateEscrowForm">
            @csrf

            {{-- Card 1: Pihak Pembeli & Penjual --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="fas fa-users"></i> {{ __('1. Tentukan Pihak Bertransaksi') }}
                    </h6>

                    <div class="row g-4">
                        {{-- Sisi Pembeli --}}
                        <div class="col-12 col-md-6 border-end-md">
                            <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-1.5">
                                <i class="fas fa-shopping-cart text-primary"></i> {{ __('Pihak Pembeli (Buyer)') }}
                            </h6>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Tipe Akun Pembeli') }}</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="buyer_type" id="buyer_type_reg" value="registered" checked onchange="toggleBuyerType()">
                                    <label class="btn btn-outline-primary btn-sm" for="buyer_type_reg">{{ __('User Terdaftar') }}</label>
                                    <input type="radio" class="btn-check" name="buyer_type" id="buyer_type_ext" value="external" onchange="toggleBuyerType()">
                                    <label class="btn btn-outline-primary btn-sm" for="buyer_type_ext">{{ __('Pihak Luar (Manual)') }}</label>
                                </div>
                            </div>

                            <div class="mb-3" id="buyer_select_group">
                                <label class="form-label small fw-bold text-muted">{{ __('Pilih Akun User Pembeli') }} <span class="text-danger">*</span></label>
                                <select name="buyer_user_id" id="buyer_user_id" class="form-select rounded-3" onchange="fillBuyerFromUser(this)">
                                    <option value="">{{ __('-- Pilih User Pembeli --') }}</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" data-name="{{ $u->full_name ?: $u->username }}" data-contact="{{ $u->telegram_id ? '@'.$u->username : ($u->email ?: $u->username) }}" {{ old('buyer_user_id') == $u->id ? 'selected' : '' }}>
                                            {{ $u->full_name ?: $u->username }} ({{ $u->email ?: 'ID: '.$u->id }}) - {{ strtoupper($u->role) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Nama Pembeli') }} <span class="text-danger">*</span></label>
                                <input type="text" name="buyer_name" id="buyer_name" class="form-control rounded-3" placeholder="{{ __('Nama lengkap / panggilan pembeli') }}" required value="{{ old('buyer_name') }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Kontak Pembeli (WA / Telegram)') }} <span class="text-danger">*</span></label>
                                <input type="text" name="buyer_contact" id="buyer_contact" class="form-control rounded-3" placeholder="{{ __('Contoh: 08123456789 atau @username_tele') }}" required value="{{ old('buyer_contact') }}">
                            </div>
                        </div>

                        {{-- Sisi Penjual --}}
                        <div class="col-12 col-md-6">
                            <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-1.5">
                                <i class="fas fa-store text-success"></i> {{ __('Pihak Penjual (Seller)') }}
                            </h6>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Tipe Akun Penjual') }}</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="seller_type" id="seller_type_ext" value="external" checked onchange="toggleSellerType()">
                                    <label class="btn btn-outline-success btn-sm" for="seller_type_ext">{{ __('Pihak Luar (Manual)') }}</label>
                                    <input type="radio" class="btn-check" name="seller_type" id="seller_type_reg" value="registered" onchange="toggleSellerType()">
                                    <label class="btn btn-outline-success btn-sm" for="seller_type_reg">{{ __('User Terdaftar') }}</label>
                                </div>
                            </div>

                            <div class="mb-3 d-none" id="seller_select_group">
                                <label class="form-label small fw-bold text-muted">{{ __('Pilih Akun User Penjual') }}</label>
                                <select name="seller_user_id" id="seller_user_id" class="form-select rounded-3" onchange="fillSellerFromUser(this)">
                                    <option value="">{{ __('-- Pilih User Penjual --') }}</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" data-name="{{ $u->full_name ?: $u->username }}" data-contact="{{ $u->telegram_id ? '@'.$u->username : ($u->email ?: $u->username) }}" {{ old('seller_user_id') == $u->id ? 'selected' : '' }}>
                                            {{ $u->full_name ?: $u->username }} ({{ $u->email ?: 'ID: '.$u->id }}) - {{ strtoupper($u->role) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Nama Penjual') }} <span class="text-danger">*</span></label>
                                <input type="text" name="seller_name" id="seller_name" class="form-control rounded-3" placeholder="{{ __('Nama lengkap / toko penjual') }}" required value="{{ old('seller_name') }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">{{ __('Kontak Penjual (WA / Telegram)') }} <span class="text-danger">*</span></label>
                                <input type="text" name="seller_contact" id="seller_contact" class="form-control rounded-3" placeholder="{{ __('Contoh: 08123456789 atau @username_tele') }}" required value="{{ old('seller_contact') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 2: Detail Transaksi & Spesifikasi --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="fas fa-box-open"></i> {{ __('2. Spesifikasi Produk & Durasi Inspeksi') }}
                    </h6>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Judul Transaksi Rekber') }} <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control rounded-3" placeholder="{{ __('Contoh: Transaksi Akun Cloud Google & VPS 1 Tahun') }}" required value="{{ old('title') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Deskripsi Spesifikasi & Rincian Penyerahan') }}</label>
                        <textarea name="description" class="form-control rounded-3" rows="3" placeholder="{{ __('Tuliskan rincian apa saja yang harus diserahkan (kredensial, first mail, link akses, dll)...') }}">{{ old('description') }}</textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Batas Waktu Pemeriksaan Akun (Jam)') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="inspection_hours" class="form-control rounded-start-3" value="{{ old('inspection_hours', 24) }}" min="1" max="168" required>
                                <span class="input-group-text bg-light text-muted">{{ __('Jam') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 3: Keuangan, Fee & Rekening Pencairan --}}
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                        <i class="fas fa-money-bill-wave"></i> {{ __('3. Nilai Transaksi & Rekening Penjual') }}
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Nilai Barang (Rp)') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-muted">Rp</span>
                                <input type="number" name="amount" id="amountInput" class="form-control form-control-lg fw-bold rounded-end-3" placeholder="0" min="10000" step="1000" required value="{{ old('amount', 50000) }}" oninput="calculateEscrowFee()">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-muted">{{ __('Penanggung Fee Rekber') }} <span class="text-danger">*</span></label>
                            <select name="fee_payer" id="feePayerSelect" class="form-select form-select-lg rounded-3" onchange="calculateEscrowFee()">
                                <option value="buyer" selected>{{ __('Ditanggung Pembeli (Buyer)') }}</option>
                                <option value="seller">{{ __('Dipotong dari Penjual (Seller)') }}</option>
                                <option value="split">{{ __('Dibagi Dua (Split 50:50)') }}</option>
                            </select>
                        </div>
                    </div>

                    {{-- Live Fee Breakdown Box --}}
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">{{ __('Nilai Pokok Barang:') }}</span>
                            <span class="fw-semibold text-dark" id="displayAmount">Rp 50.000</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">{{ __('Biaya Admin Rekber (:percent%):', ['percent' => $feePercent]) }}</span>
                            <span class="fw-semibold text-info" id="displayFee">Rp 750</span>
                        </div>
                        <hr class="my-2 opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark">{{ __('Estimasi Bayar Pembeli:') }}</span>
                            <span class="fs-5 fw-bold text-primary" id="displayTotalBuyer">Rp 50.750</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">{{ __('Estimasi Diterima Penjual:') }}</span>
                            <span class="fw-bold text-success" id="displayTotalSeller">Rp 50.000</span>
                        </div>
                        <div class="mt-2 pt-2 border-top small text-muted" style="font-size: 0.74rem;">
                            <i class="fas fa-magic text-warning me-1"></i> {{ __('* Sistem akan otomatis menyematkan kode unik (1-250) ke tagihan pembeli untuk rekonsiliasi otomatis Android Listener.') }}
                        </div>
                    </div>

                    {{-- Rekening Pencairan Penjual --}}
                    <div class="pt-2">
                        <label class="form-label small fw-bold text-muted">{{ __('Rekening Tujuan Pencairan Penjual (Dapat diisi saat penyerahan)') }}</label>
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
                <a href="{{ route('admin.escrow.index') }}" class="btn btn-light rounded-pill px-4">{{ __('Batal') }}</a>
                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-semibold shadow-sm">
                    <i class="fas fa-check-circle me-1.5"></i> {{ __('Buat Ruang Rekber') }}
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
            totalBuyer = amount + fee;
            totalSeller = amount;
        }

        document.getElementById('displayAmount').textContent = formatRupiah(amount);
        document.getElementById('displayFee').textContent = formatRupiah(fee);
        document.getElementById('displayTotalBuyer').textContent = formatRupiah(totalBuyer);
        document.getElementById('displayTotalSeller').textContent = formatRupiah(totalSeller);
    }

    function toggleBuyerType() {
        const isReg = document.getElementById('buyer_type_reg').checked;
        const group = document.getElementById('buyer_select_group');
        if (isReg) {
            group.classList.remove('d-none');
        } else {
            group.classList.add('d-none');
            document.getElementById('buyer_user_id').value = '';
        }
    }

    function toggleSellerType() {
        const isReg = document.getElementById('seller_type_reg').checked;
        const group = document.getElementById('seller_select_group');
        if (isReg) {
            group.classList.remove('d-none');
        } else {
            group.classList.add('d-none');
            document.getElementById('seller_user_id').value = '';
        }
    }

    function fillBuyerFromUser(select) {
        const opt = select.options[select.selectedIndex];
        if (opt && opt.value) {
            document.getElementById('buyer_name').value = opt.getAttribute('data-name') || '';
            document.getElementById('buyer_contact').value = opt.getAttribute('data-contact') || '';
        }
    }

    function fillSellerFromUser(select) {
        const opt = select.options[select.selectedIndex];
        if (opt && opt.value) {
            document.getElementById('seller_name').value = opt.getAttribute('data-name') || '';
            document.getElementById('seller_contact').value = opt.getAttribute('data-contact') || '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        calculateEscrowFee();
        toggleBuyerType();
        toggleSellerType();
    });
</script>
@endsection
