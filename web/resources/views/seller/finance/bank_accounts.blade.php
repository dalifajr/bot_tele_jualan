@extends('layouts.app')

@section('title', 'Konfigurasi Rekening Payout')
@section('page_subtitle', 'Keuangan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Konfigurasi Rekening Payout</h4>
        <p class="text-muted mb-0">Simpan informasi rekening bank atau e-wallet Anda untuk mempermudah proses pencairan dana (payout).</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

{{-- Sub Navigation Tabs --}}
<ul class="nav nav-pills mb-4 gap-2">
    <li class="nav-item">
        <a class="nav-link text-secondary rounded-pill px-4 bg-light" href="{{ route('seller.finance.index') }}">
            <i class="fas fa-wallet me-2"></i>Dompet & Penarikan
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active rounded-pill px-4" href="{{ route('seller.bank-accounts.index') }}">
            <i class="fas fa-university me-2"></i>Konfigurasi Rekening
        </a>
    </li>
</ul>

<div class="row g-4">
    {{-- Form Simpan Rekening Baru --}}
    <div class="col-12 col-md-5">
        <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
            <h5 class="fw-bold mb-3"><i class="fas fa-plus-circle text-primary me-2"></i>Tambah Rekening Payout</h5>
            
            <form action="{{ route('seller.bank-accounts.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">Nama Bank / E-Wallet Tujuan</label>
                    <input type="text" name="bank_name" class="form-control" placeholder="Contoh: BCA, Mandiri, DANA, GoPay" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">Nomor Rekening / Akun E-Wallet</label>
                    <input type="text" name="account_number" class="form-control" placeholder="Contoh: 8012345678" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">Nama Pemilik Rekening (Sesuai Bank)</label>
                    <input type="text" name="account_holder" class="form-control" placeholder="Contoh: Dzulfikri Alifajri" required>
                </div>

                <button type="submit" class="btn btn-primary rounded-pill w-100 py-2.5 fw-bold">
                    <i class="fas fa-save me-1"></i> Simpan Rekening
                </button>
            </form>
        </div>
    </div>

    {{-- Daftar Rekening Tersimpan --}}
    <div class="col-12 col-md-7">
        <div class="card border-0 shadow-sm overflow-hidden mb-4" style="border-radius: 20px;">
            <div class="card-header border-0 bg-white px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0"><i class="fas fa-university text-primary me-2"></i>Daftar Rekening Tersimpan</h5>
            </div>
            <div class="card-body p-0">
                @if($bankAccounts->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom">
                                <th class="px-4 py-3 border-0">Nama Bank</th>
                                <th class="py-3 border-0">Nomor Rekening</th>
                                <th class="py-3 border-0">Atas Nama</th>
                                <th class="py-3 border-0 text-end px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bankAccounts as $acc)
                            <tr>
                                <td class="px-4 fw-bold text-dark">{{ strtoupper($acc->bank_name) }}</td>
                                <td><code>{{ $acc->account_number }}</code></td>
                                <td class="fw-medium">{{ $acc->account_holder }}</td>
                                <td class="text-end px-4">
                                    <form action="{{ route('seller.bank-accounts.destroy', $acc->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-2" title="Hapus Rekening" onclick="confirmAction(event, 'Yakin ingin menghapus rekening bank payout ini?')">
                                            <i class="fas fa-trash fa-fw"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-university text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">Belum ada rekening payout yang disimpan.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
