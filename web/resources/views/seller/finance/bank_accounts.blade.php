@extends('layouts.app')

@section('title', __('Konfigurasi Rekening Payout'))
@section('page_subtitle', __('Keuangan'))

@section('content')
<div class="mb-3 mb-md-4">
    <h4 class="fw-bold mb-1 text-body">{{ __('Konfigurasi Rekening Payout') }}</h4>
    <p class="text-muted small mb-0">{{ __('Simpan informasi rekening bank atau e-wallet Anda untuk mempermudah proses pencairan dana (payout).') }}</p>
</div>

@if(session('success'))
    <div class="alert alert-success rounded-4 small py-2.5 px-3 mb-3 mb-md-4 d-flex align-items-center gap-2">
        <i class="fas fa-check-circle flex-shrink-0"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger rounded-4 small py-2.5 px-3 mb-3 mb-md-4 d-flex align-items-center gap-2">
        <i class="fas fa-exclamation-circle flex-shrink-0"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

{{-- Sub Navigation Tabs (M3 Full-Width Segmented Pill on Mobile) --}}
<div class="mb-4">
    <ul class="nav nav-pills m3-segmented-nav" role="tablist">
        <li class="nav-item">
            <a class="nav-link fw-semibold" href="{{ route('seller.finance.index') }}">
                <i class="fas fa-wallet"></i>
                <span class="text-truncate">{{ __('Dompet & Penarikan') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active fw-semibold" href="{{ route('seller.bank-accounts.index') }}" aria-current="page">
                <i class="fas fa-university"></i>
                <span class="text-truncate">{{ __('Konfigurasi Rekening') }}</span>
            </a>
        </li>
    </ul>
</div>

<div class="row g-3 g-lg-4">
    {{-- Form Simpan Rekening Baru --}}
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-body p-3 p-sm-4">
                <div class="d-flex align-items-center gap-2.5 mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: var(--primary-container); color: var(--on-primary-container);">
                        <i class="fas fa-plus"></i>
                    </span>
                    <div>
                        <h5 class="fw-bold mb-0 text-body fs-6">{{ __('Tambah Rekening Payout') }}</h5>
                        <div class="text-muted" style="font-size: 0.78rem;">{{ __('Mendukung Bank Nasional & E-Wallet') }}</div>
                    </div>
                </div>

                <form action="{{ route('seller.bank-accounts.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="bankNameInput" class="form-label text-muted small fw-semibold mb-1.5">{{ __('Nama Bank / E-Wallet Tujuan') }}</label>
                        <input id="bankNameInput" type="text" name="bank_name" class="form-control" placeholder="{{ __('Contoh: BCA, Mandiri, DANA, GoPay') }}" value="{{ old('bank_name') }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="accountNumberInput" class="form-label text-muted small fw-semibold mb-1.5">{{ __('Nomor Rekening / Akun E-Wallet') }}</label>
                        <input id="accountNumberInput" type="text" name="account_number" class="form-control font-monospace" inputmode="numeric" placeholder="{{ __('Contoh: 8012345678') }}" value="{{ old('account_number') }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="accountHolderInput" class="form-label text-muted small fw-semibold mb-1.5">{{ __('Nama Pemilik Rekening (Sesuai Bank)') }}</label>
                        <input id="accountHolderInput" type="text" name="account_holder" class="form-control" placeholder="{{ __('Contoh: Dzulfikri Alifajri') }}" value="{{ old('account_holder') }}" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold rounded-pill d-inline-flex align-items-center justify-content-center gap-2" style="min-height: 46px;">
                        <i class="fas fa-save"></i>
                        <span>{{ __('Simpan Rekening') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Daftar Rekening Tersimpan --}}
    <div class="col-12 col-lg-7">
        <div class="card overflow-hidden">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 px-3 px-sm-4 py-3">
                <h5 class="fw-bold m-0 text-body fs-6 d-flex align-items-center gap-2">
                    <i class="fas fa-university text-primary"></i>
                    <span>{{ __('Daftar Rekening Tersimpan') }}</span>
                </h5>
                <span class="badge bg-secondary-subtle rounded-pill px-2.5 py-1 fw-semibold m3-tabular-nums">
                    {{ $bankAccounts->count() }}
                </span>
            </div>

            <div class="card-body p-0">
                @if($bankAccounts->count() > 0)
                    {{-- Desktop Table View --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small">
                                    <th class="ps-4 py-3 border-0">{{ __('Nama Bank') }}</th>
                                    <th class="py-3 border-0">{{ __('Nomor Rekening') }}</th>
                                    <th class="py-3 border-0">{{ __('Atas Nama') }}</th>
                                    <th class="py-3 border-0 text-end pe-4">{{ __('Aksi') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bankAccounts as $acc)
                                    <tr>
                                        <td class="ps-4 fw-bold text-body">{{ strtoupper($acc->bank_name) }}</td>
                                        <td><code class="fw-semibold">{{ $acc->account_number }}</code></td>
                                        <td class="fw-medium text-body">{{ $acc->account_holder }}</td>
                                        <td class="text-end pe-4">
                                            <form action="{{ route('seller.bank-accounts.destroy', $acc->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" title="{{ __('Hapus Rekening') }}" onclick="confirmAction(event, '{{ __('Yakin ingin menghapus rekening bank payout ini?') }}')">
                                                    <i class="fas fa-trash me-1"></i>{{ __('Hapus') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile Stacked Card List --}}
                    <div class="d-md-none p-3 d-flex flex-column gap-2.5">
                        @foreach($bankAccounts as $acc)
                            <div class="m3-mobile-item-card d-flex justify-content-between align-items-center gap-3">
                                <div class="min-w-0">
                                    <div class="fw-bold text-body">{{ strtoupper($acc->bank_name) }}</div>
                                    <div class="small font-monospace text-primary fw-semibold">{{ $acc->account_number }}</div>
                                    <div class="small text-muted text-truncate">a.n. {{ $acc->account_holder }}</div>
                                </div>
                                <form action="{{ route('seller.bank-accounts.destroy', $acc->id) }}" method="POST" class="flex-shrink-0 m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill d-inline-flex align-items-center justify-content-center" style="min-width: 40px; min-height: 40px;" title="{{ __('Hapus Rekening') }}" aria-label="{{ __('Hapus Rekening') }}" onclick="confirmAction(event, '{{ __('Yakin ingin menghapus rekening bank payout ini?') }}')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5 px-3">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--on-surface-variant); font-size: 1.4rem;">
                            <i class="fas fa-university"></i>
                        </span>
                        <p class="fw-semibold text-body mb-1 small">{{ __('Belum ada rekening payout yang disimpan.') }}</p>
                        <p class="text-muted small mb-0">{{ __('Tambahkan rekening bank atau e-wallet untuk mulai menarik saldo.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
