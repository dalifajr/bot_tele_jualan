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
    {{-- Desktop/Tablet Inline Form Simpan Rekening Baru (Hidden on Smartphone; opened via + button -> #addBankAccountModal) --}}
    <div class="col-12 col-lg-5 d-none d-md-block">
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2.5 mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: var(--primary-container); color: var(--on-primary-container);">
                        <i class="fas fa-plus"></i>
                    </span>
                    <div>
                        <h5 class="fw-bold mb-0 text-body fs-6">{{ __('Tambah Rekening Payout') }}</h5>
                        <div class="text-muted" style="font-size: 0.78rem;">{{ __('Mendukung Bank Nasional & E-Wallet') }}</div>
                    </div>
                </div>

                @include('seller.finance.partials.bank_account_form', ['idPrefix' => 'desktop'])
            </div>
        </div>
    </div>

    {{-- Daftar Rekening Tersimpan --}}
    <div class="col-12 col-lg-7">
        <div class="card overflow-hidden">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 px-3 px-sm-4 py-3">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <h5 class="fw-bold m-0 text-body fs-6 d-flex align-items-center gap-2 text-truncate">
                        <i class="fas fa-university text-primary"></i>
                        <span class="text-truncate">{{ __('Daftar Rekening Tersimpan') }}</span>
                    </h5>
                    <span class="badge bg-secondary-subtle rounded-pill px-2.5 py-1 fw-semibold m3-tabular-nums flex-shrink-0">
                        {{ $bankAccounts->count() }}
                    </span>
                </div>

                {{-- Smartphone "+" Trigger Button to open Add Bank Account Modal --}}
                <button type="button"
                        class="btn btn-primary rounded-pill d-md-none d-inline-flex align-items-center justify-content-center flex-shrink-0 p-0"
                        style="width: 40px; height: 40px;"
                        data-bs-toggle="modal"
                        data-bs-target="#addBankAccountModal"
                        title="{{ __('Tambah Rekening Payout') }}"
                        aria-label="{{ __('Tambah Rekening Payout') }}">
                    <i class="fas fa-plus"></i>
                </button>
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
                        <p class="text-muted small mb-3 mb-md-0">{{ __('Tambahkan rekening bank atau e-wallet untuk mulai menarik saldo.') }}</p>
                        <button type="button"
                                class="btn btn-primary rounded-pill px-4 py-2 fw-semibold d-md-none"
                                data-bs-toggle="modal"
                                data-bs-target="#addBankAccountModal">
                            <i class="fas fa-plus me-1.5"></i>{{ __('Tambah Rekening') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('modals')
{{-- Smartphone Modal: Tambah Rekening Payout --}}
<div class="modal fade m3-sheet-modal" id="addBankAccountModal" tabindex="-1" aria-labelledby="addBankAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: var(--radius-xl, 28px); border: 1px solid var(--outline-variant);">
            <div class="d-md-none m3-sheet-handle" aria-hidden="true"></div>
            <div class="modal-header border-0 px-4 pt-3 pt-md-4 pb-2">
                <div>
                    <h5 class="fw-bold mb-0 text-body" id="addBankAccountModalLabel">
                        <i class="fas fa-plus-circle text-primary me-2"></i>{{ __('Tambah Rekening Payout') }}
                    </h5>
                    <small class="text-muted">{{ __('Mendukung Bank Nasional & E-Wallet') }}</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="modal-body px-4 py-3">
                @include('seller.finance.partials.bank_account_form', ['idPrefix' => 'modal'])
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if($errors->has('bank_name') || $errors->has('account_number') || $errors->has('account_holder'))
    if (window.innerWidth < 768) {
        const modalEl = document.getElementById('addBankAccountModal');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
    @endif
});
</script>
@endpush
@endsection
