@extends('layouts.app')

@section('title', 'Dashboard Seller')
@section('page_subtitle', 'Dashboard')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-1">Selamat Datang di Portal Seller, {{ $user->full_name ?? $user->username }}!</h4>
    <p class="text-muted mb-0">Kelola produk Anda, kelola stok akun, dan pantau penghasilan penjualan Anda secara langsung.</p>
</div>

{{-- Stats Row --}}
<div class="row g-4 mb-4">
    {{-- Card Wallet Balance --}}
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm text-white" style="border-radius: 20px; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
            <div class="card-body p-4 position-relative overflow-hidden">
                <div class="position-absolute end-0 bottom-0 opacity-10" style="font-size: 8rem; transform: translate(20px, 20px);">
                    <i class="fas fa-wallet"></i>
                </div>
                <p class="small text-white-50 fw-bold mb-2">SALDO DOMPET SAYA</p>
                <h2 class="fw-bold mb-3">Rp {{ number_format($user->wallet_balance, 0, ',', '.') }}</h2>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white-10">
                    <span class="small text-white-50">Komisi Platform: <strong>{{ $user->platform_fee_percent }}%</strong></span>
                    <a href="{{ route('seller.finance.index') }}" class="btn btn-light btn-sm rounded-pill px-3 fw-bold text-primary">Tarik Saldo <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

    {{-- Card Monthly Earnings --}}
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 20px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px);">
            <div class="card-body p-4 position-relative overflow-hidden">
                <div class="position-absolute end-0 bottom-0 text-success opacity-10" style="font-size: 7rem; transform: translate(15px, 15px);">
                    <i class="fas fa-coins"></i>
                </div>
                <p class="small text-muted fw-bold mb-2">TOTAL PENDAPATAN KOTOR</p>
                <h2 class="fw-bold text-success mb-3">Rp {{ number_format($monthlyEarnings, 0, ',', '.') }}</h2>
                <span class="small text-muted"><i class="fas fa-info-circle text-primary me-1"></i>Akumulasi pendapatan kotor dari produk milik Anda yang terjual.</span>
            </div>
        </div>
    </div>

    {{-- Card Active Stock --}}
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 20px; background: rgba(255,255,255,0.7); backdrop-filter: blur(10px);">
            <div class="card-body p-4 position-relative overflow-hidden">
                <div class="position-absolute end-0 bottom-0 text-info opacity-10" style="font-size: 7rem; transform: translate(15px, 15px);">
                    <i class="fas fa-boxes"></i>
                </div>
                <p class="small text-muted fw-bold mb-2">STATUS STOK PENJUALAN</p>
                <h2 class="fw-bold text-info mb-3">{{ $readyStockCount }} <span class="fs-6 text-muted fw-normal">unit ready</span></h2>
                <div class="d-flex gap-2">
                    <span class="badge bg-warning-subtle text-warning rounded-pill px-2 small">{{ $savedStockCount }} karantina</span>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 small">{{ $soldStockCount }} terjual</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Quick Start Guide --}}
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
            <h5 class="fw-bold mb-3"><i class="fas fa-compass text-primary me-2"></i>Panduan Cepat Operasional Seller</h5>
            <div class="d-flex flex-column gap-3 mt-2">
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">1</div>
                    <div>
                        <h6 class="fw-bold mb-1">Miliki atau Ikut Serta dalam Katalog Produk</h6>
                        <p class="text-muted small mb-0">Anda dapat membuat produk baru secara mandiri di halaman <strong>Produk Saya</strong>, atau ditambahkan sebagai <strong>Worker</strong> oleh Admin pada produk global.</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">2</div>
                    <div>
                        <h6 class="fw-bold mb-1">Unggah Stok Akun Digital Anda</h6>
                        <p class="text-muted small mb-0">Unggah akun/stok Anda secara massal di menu <strong>Stok Akun</strong>. Akun yang baru diunggah akan masuk ke status <strong>Karantina (*Simpan Akun*)</strong>.</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">3</div>
                    <div>
                        <h6 class="fw-bold mb-1">Pindah Otomatis ke Status Ready</h6>
                        <p class="text-muted small mb-0">Stok Anda akan otomatis berpindah ke status <strong>Ready</strong> setelah jam cooldown karantina habis. Anda dapat mengatur jam karantina tersendiri di menu <strong>Pengaturan</strong>.</p>
                    </div>
                </div>
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; flex-shrink: 0;">4</div>
                    <div>
                        <h6 class="fw-bold mb-1">Terima Komisi & Tarik Saldo Ke Rekening</h6>
                        <p class="text-muted small mb-0">Setiap pembeli membeli stok Anda, Anda menerima notifikasi Telegram dan dana masuk ke saldo Dompet setelah dipotong platform fee. Anda dapat mengajukan pencairan dana di menu <strong>Dompet & Keuangan</strong>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Finance Overview --}}
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
            <h5 class="fw-bold mb-3"><i class="fas fa-hand-holding-usd text-primary me-2"></i>Status Payout Terakhir</h5>
            <div class="text-center py-4 bg-light rounded-4 mb-3">
                <i class="fas fa-history text-muted mb-2 fs-3"></i>
                <div class="small text-muted">Total Pengajuan Penarikan:</div>
                <h5 class="fw-bold m-0 text-primary">{{ $pendingWithdrawalCount + $approvedWithdrawalCount }} Permintaan</h5>
            </div>
            <div class="d-flex flex-column gap-2 small">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted"><i class="fas fa-spinner fa-spin text-warning me-1"></i> Menunggu Verifikasi Admin</span>
                    <span class="fw-bold">{{ $pendingWithdrawalCount }} Permintaan</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted"><i class="fas fa-check-circle text-success me-1"></i> Penarikan Disetujui</span>
                    <span class="fw-bold text-success">{{ $approvedWithdrawalCount }} Selesai</span>
                </div>
            </div>
            <div class="mt-4 text-center">
                <a href="{{ route('seller.finance.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-4">Lihat Dompet Saya</a>
            </div>
        </div>
    </div>
</div>
@endsection
