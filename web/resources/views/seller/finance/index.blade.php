@extends('layouts.app')

@section('title', __('Dompet & Keuangan'))
@section('page_subtitle', __('Dompet'))

@section('content')
@php
    $walletBalance = (int) ($user->wallet_balance ?? 0);
    $heldBalanceInt = (int) ($heldBalance ?? 0);
    $canWithdraw = $walletBalance >= 10000 && $bankAccounts->isNotEmpty();
@endphp

<div class="mb-3 mb-md-4">
    <h4 class="fw-bold mb-1 text-body">{{ __('Dompet & Keuangan Seller') }}</h4>
    <p class="text-muted small mb-0">{{ __('Pantau saldo wallet komisi hasil penjualan Anda dan ajukan penarikan dana payout.') }}</p>
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
            <a class="nav-link active fw-semibold" href="{{ route('seller.finance.index') }}" aria-current="page">
                <i class="fas fa-wallet"></i>
                <span class="text-truncate">{{ __('Dompet & Penarikan') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-semibold" href="{{ route('seller.bank-accounts.index') }}">
                <i class="fas fa-university"></i>
                <span class="text-truncate">{{ __('Konfigurasi Rekening') }}</span>
            </a>
        </li>
    </ul>
</div>

<div class="row g-3 g-lg-4">
    {{-- Left Column: Balance Overview Cards & Withdrawal Form --}}
    <div class="col-12 col-lg-5">
        <div class="row g-3 mb-3 mb-lg-4">
            {{-- Wallet Balance Card (Flat Material You Primary Tonal Container) --}}
            <div class="col-12">
                <div class="card seller-stat-card-gradient seller-stat-card-finance-wallet">
                    <div class="card-body p-3 p-sm-4">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <span class="d-block small fw-bold text-uppercase opacity-75" style="letter-spacing: 0.06em; font-size: 0.72rem;">
                                    {{ __('Saldo Dompet Saya') }}
                                </span>
                                <h2 class="fw-bold mb-0 mt-1 m3-tabular-nums" style="font-size: clamp(1.6rem, 5vw, 2rem); line-height: 1.2;">
                                    Rp {{ number_format($walletBalance, 0, ',', '.') }}
                                </h2>
                            </div>
                            <span class="m3-finance-icon-badge is-primary" aria-hidden="true">
                                <i class="fas fa-wallet"></i>
                            </span>
                        </div>
                        <div class="mt-3 pt-2.5 d-flex flex-wrap justify-content-between align-items-center gap-2 small" style="border-top: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <span class="opacity-75">{{ __('Potongan Platform') }}</span>
                            <strong class="fw-semibold">
                                {{ !empty($user->commission_per_product) ? __('Per-Produk (Sesuai Katalog)') : ($user->platform_fee_percent . '% ' . __('per penjualan')) }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Held Balance Card (Flat Material You Warning Tonal Container) --}}
            <div class="col-12">
                <div class="card seller-stat-card-gradient seller-stat-card-finance-held">
                    <div class="card-body p-3 p-sm-4">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <span class="d-block small fw-bold text-uppercase opacity-75" style="letter-spacing: 0.06em; font-size: 0.72rem;">
                                    {{ __('Saldo Tertahan (Garansi)') }}
                                </span>
                                <h2 class="fw-bold mb-0 mt-1 m3-tabular-nums" style="font-size: clamp(1.6rem, 5vw, 2rem); line-height: 1.2;">
                                    Rp {{ number_format($heldBalanceInt, 0, ',', '.') }}
                                </h2>
                            </div>
                            <span class="m3-finance-icon-badge is-warning" aria-hidden="true">
                                <i class="fas fa-shield-halved"></i>
                            </span>
                        </div>
                        <div class="mt-3 pt-2.5 d-flex flex-wrap justify-content-between align-items-center gap-2 small" style="border-top: 1px solid rgba(180, 83, 9, 0.22);">
                            <span class="opacity-85">{{ __('Komisi bersih yang ditangguhkan hingga masa garansi produk selesai.') }}</span>
                            @if($heldFunds->total() > 0)
                                <a href="#heldFundsSection" class="fw-bold text-decoration-none ms-auto" style="color: inherit;">
                                    {{ __('Lihat Rincian') }} <i class="fas fa-arrow-down ms-1 small"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Withdrawal Form Card --}}
        <div class="card">
            <div class="card-body p-3 p-sm-4">
                <div class="d-flex align-items-center gap-2.5 mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: var(--primary-container); color: var(--on-primary-container);">
                        <i class="fas fa-money-bill-transfer"></i>
                    </span>
                    <div>
                        <h5 class="fw-bold mb-0 text-body fs-6">{{ __('Tarik Saldo (Withdrawal)') }}</h5>
                        <div class="text-muted" style="font-size: 0.78rem;">{{ __('Minimal Rp 10.000 • Maksimal sesuai saldo tersedia') }}</div>
                    </div>
                </div>

                <form action="{{ route('seller.finance.withdraw') }}" method="POST" id="sellerWithdrawForm">
                    @csrf
                    <div class="mb-3">
                        <label for="withdrawAmountInput" class="form-label text-muted small fw-semibold mb-1.5">{{ __('Nominal Penarikan (Rp)') }}</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text fw-bold fs-6">Rp</span>
                            <input
                                type="number"
                                id="withdrawAmountInput"
                                name="amount"
                                class="form-control fw-bold m3-tabular-nums"
                                inputmode="numeric"
                                placeholder="100000"
                                min="10000"
                                max="{{ $walletBalance }}"
                                value="{{ old('amount') }}"
                                required
                                {{ $walletBalance < 10000 ? 'disabled' : '' }}
                            >
                        </div>

                        {{-- Quick Amount Chips --}}
                        <div class="d-flex flex-wrap gap-1.5 mt-2">
                            @php
                                $q25 = (int) floor($walletBalance * 0.25);
                                $q50 = (int) floor($walletBalance * 0.50);
                            @endphp
                            <button type="button" class="btn btn-sm m3-quick-amount-chip" data-withdraw-amount="{{ $q25 }}" {{ $q25 < 10000 ? 'disabled' : '' }}>
                                25%
                            </button>
                            <button type="button" class="btn btn-sm m3-quick-amount-chip" data-withdraw-amount="{{ $q50 }}" {{ $q50 < 10000 ? 'disabled' : '' }}>
                                50%
                            </button>
                            <button type="button" class="btn btn-sm m3-quick-amount-chip" data-withdraw-amount="{{ $walletBalance }}" {{ $walletBalance < 10000 ? 'disabled' : '' }}>
                                {{ __('Tarik Semua') }} (Rp {{ number_format($walletBalance, 0, ',', '.') }})
                            </button>
                        </div>
                    </div>

                    @if($bankAccounts->isEmpty())
                        <div class="p-3 rounded-4 mb-3 small d-flex align-items-start gap-2.5" style="background: var(--cm-sys-color-status-warning-container); color: var(--cm-sys-color-status-warning); border: 1px solid rgba(180, 83, 9, 0.2);">
                            <i class="fas fa-exclamation-triangle mt-0.5 flex-shrink-0"></i>
                            <div>
                                {{ __('Anda belum menyimpan rekening bank. Silakan simpan rekening tujuan terlebih dahulu di menu') }}
                                <a href="{{ route('seller.bank-accounts.index') }}" class="fw-bold text-decoration-underline" style="color: inherit;">{{ __('Konfigurasi Rekening') }}</a>.
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1.5">
                                <label for="withdrawBankAccount" class="form-label text-muted small fw-semibold mb-0">{{ __('Pilih Rekening Tujuan Payout') }}</label>
                                <a href="{{ route('seller.bank-accounts.index') }}" class="small fw-semibold text-decoration-none">
                                    <i class="fas fa-plus me-1"></i>{{ __('Tambah Rekening') }}
                                </a>
                            </div>
                            <select id="withdrawBankAccount" name="bank_account_id" class="form-select" required>
                                <option value="" disabled {{ old('bank_account_id') ? '' : 'selected' }}>{{ __('-- Pilih Rekening Payout --') }}</option>
                                @foreach($bankAccounts as $acc)
                                    <option value="{{ $acc->id }}" {{ (string) old('bank_account_id') === (string) $acc->id ? 'selected' : '' }}>
                                        {{ strtoupper($acc->bank_name) }} — {{ $acc->account_number }} (a.n. {{ $acc->account_holder }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary rounded-pill w-100 py-2.5 fw-bold d-inline-flex align-items-center justify-content-center gap-2" style="min-height: 46px;" {{ !$canWithdraw ? 'disabled' : '' }}>
                        <i class="fas fa-paper-plane"></i>
                        <span>{{ __('Ajukan Penarikan Dana') }}</span>
                    </button>

                    @if($walletBalance < 10000)
                        <div class="mt-3 p-2.5 px-3 rounded-4 small d-flex align-items-center gap-2" style="background: var(--error-container); color: var(--on-error-container);">
                            <i class="fas fa-circle-info flex-shrink-0"></i>
                            <span>{{ __('Saldo Anda belum mencukupi minimal penarikan Rp 10.000.') }}</span>
                        </div>
                    @elseif($bankAccounts->isEmpty())
                        <div class="mt-3 p-2.5 px-3 rounded-4 small d-flex align-items-center gap-2" style="background: var(--error-container); color: var(--on-error-container);">
                            <i class="fas fa-circle-info flex-shrink-0"></i>
                            <span>{{ __('Hubungkan rekening bank payout terlebih dahulu.') }}</span>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>

    {{-- Right Column: Withdrawal History & Held Funds Details --}}
    <div class="col-12 col-lg-7">
        {{-- Withdrawal History Card --}}
        <div class="card overflow-hidden">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 px-3 px-sm-4 py-3">
                <h5 class="fw-bold m-0 text-body fs-6 d-flex align-items-center gap-2">
                    <i class="fas fa-clock-rotate-left text-primary"></i>
                    <span>{{ __('Riwayat Pengajuan Penarikan') }}</span>
                </h5>
                <span class="badge bg-secondary-subtle rounded-pill px-2.5 py-1 fw-semibold m3-tabular-nums">
                    {{ $withdrawals->total() }}
                </span>
            </div>

            <div class="card-body p-0">
                @if($withdrawals->count() > 0)
                    {{-- Desktop Table View --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small">
                                    <th class="ps-4 py-3 border-0">{{ __('Tanggal') }}</th>
                                    <th class="py-3 border-0">{{ __('Tujuan Rekening') }}</th>
                                    <th class="py-3 border-0 text-end">{{ __('Jumlah') }}</th>
                                    <th class="py-3 border-0 text-center">{{ __('Status') }}</th>
                                    <th class="py-3 border-0 text-end pe-4">{{ __('Detail') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($withdrawals as $withdrawal)
                                    <tr>
                                        <td class="ps-4 text-muted small text-nowrap">
                                            {{ $withdrawal->created_at->format('d M Y') }}<br>
                                            <span style="font-size: 0.72rem;">{{ $withdrawal->created_at->format('H:i') }}</span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-body small">{{ strtoupper($withdrawal->bank_name) }}</div>
                                            <div class="small text-muted font-monospace" style="font-size: 0.76rem;">{{ $withdrawal->account_number }}</div>
                                            <div class="small text-muted text-truncate" style="max-width: 200px; font-size: 0.74rem;">a.n. {{ $withdrawal->account_holder }}</div>
                                        </td>
                                        <td class="fw-bold text-body text-end text-nowrap m3-tabular-nums">
                                            Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            @if($withdrawal->status === 'pending')
                                                <span class="badge bg-warning-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Menunggu') }}</span>
                                            @elseif($withdrawal->status === 'approved')
                                                <span class="badge bg-success-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Selesai') }}</span>
                                            @else
                                                <span class="badge bg-danger-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Ditolak') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            @if($withdrawal->status === 'approved')
                                                <button type="button" class="btn btn-sm btn-tonal rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#viewProofModal{{ $withdrawal->id }}">
                                                    <i class="fas fa-receipt me-1"></i>{{ __('Struk') }}
                                                </button>
                                            @elseif($withdrawal->status === 'rejected')
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#viewRejectionModal{{ $withdrawal->id }}">
                                                    <i class="fas fa-circle-info me-1"></i>{{ __('Alasan') }}
                                                </button>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile Stacked Card List --}}
                    <div class="d-md-none p-3 d-flex flex-column gap-2.5">
                        @foreach($withdrawals as $withdrawal)
                            <div class="m3-mobile-item-card">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div class="min-w-0">
                                        <div class="fw-bold text-body">{{ strtoupper($withdrawal->bank_name) }}</div>
                                        <div class="small text-muted font-monospace">{{ $withdrawal->account_number }}</div>
                                        <div class="small text-muted" style="font-size: 0.78rem;">a.n. {{ $withdrawal->account_holder }}</div>
                                    </div>
                                    <div class="flex-shrink-0">
                                        @if($withdrawal->status === 'pending')
                                            <span class="badge bg-warning-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Menunggu') }}</span>
                                        @elseif($withdrawal->status === 'approved')
                                            <span class="badge bg-success-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Selesai') }}</span>
                                        @else
                                            <span class="badge bg-danger-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Ditolak') }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center gap-2 pt-2.5 mt-2" style="border-top: 1px solid var(--outline-variant);">
                                    <div>
                                        <div class="fw-bold text-body fs-6 m3-tabular-nums">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</div>
                                        <div class="text-muted" style="font-size: 0.74rem;">
                                            <i class="far fa-clock me-1"></i>{{ $withdrawal->created_at->format('d M Y, H:i') }}
                                        </div>
                                    </div>

                                    @if($withdrawal->status === 'approved')
                                        <button type="button" class="btn btn-sm btn-tonal rounded-pill px-3 py-1.5 fw-semibold" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#viewProofModal{{ $withdrawal->id }}">
                                            <i class="fas fa-receipt me-1"></i>{{ __('Lihat Struk') }}
                                        </button>
                                    @elseif($withdrawal->status === 'rejected')
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1.5 fw-semibold" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#viewRejectionModal{{ $withdrawal->id }}">
                                            <i class="fas fa-circle-info me-1"></i>{{ __('Lihat Alasan') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($withdrawals->hasPages())
                        <div class="px-3 px-sm-4 py-3 border-top">
                            {{ $withdrawals->appends(request()->except('page'))->links() }}
                        </div>
                    @endif
                @else
                    <div class="text-center py-5 px-3">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--on-surface-variant); font-size: 1.4rem;">
                            <i class="fas fa-receipt"></i>
                        </span>
                        <p class="fw-semibold text-body mb-1 small">{{ __('Belum ada pengajuan pencairan dana.') }}</p>
                        <p class="text-muted small mb-0">{{ __('Riwayat penarikan saldo Anda akan tampil di sini.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Held Funds Details Card --}}
        <div class="card overflow-hidden mt-3 mt-lg-4" id="heldFundsSection">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 px-3 px-sm-4 py-3">
                <h5 class="fw-bold m-0 text-body fs-6 d-flex align-items-center gap-2">
                    <i class="fas fa-shield-halved text-warning"></i>
                    <span>{{ __('Rincian Saldo Tertahan') }}</span>
                </h5>
                <span class="badge bg-warning-subtle rounded-pill px-2.5 py-1 fw-semibold m3-tabular-nums">
                    {{ $heldFunds->total() }}
                </span>
            </div>

            <div class="card-body p-0">
                @if($heldFunds->count() > 0)
                    {{-- Desktop Table View --}}
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small">
                                    <th class="ps-4 py-3 border-0">{{ __('Order Ref') }}</th>
                                    <th class="py-3 border-0">{{ __('Produk') }}</th>
                                    <th class="py-3 border-0 text-end">{{ __('Komisi Bersih') }}</th>
                                    <th class="py-3 border-0">{{ __('Estimasi Cair') }}</th>
                                    <th class="py-3 border-0 text-end pe-4">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($heldFunds as $fund)
                                    <tr>
                                        <td class="ps-4 fw-bold text-primary font-monospace small text-nowrap">{{ $fund->order->order_ref ?? 'N/A' }}</td>
                                        <td class="text-body small">{{ Str::limit($fund->product->name ?? 'N/A', 28) }}</td>
                                        <td class="fw-bold text-success text-end text-nowrap m3-tabular-nums">
                                            Rp {{ number_format($fund->amount, 0, ',', '.') }}
                                        </td>
                                        <td class="text-muted small text-nowrap">
                                            {{ $fund->release_at ? $fund->release_at->format('d M Y, H:i') : '-' }}
                                        </td>
                                        <td class="text-end pe-4">
                                            @if($fund->status === 'held')
                                                <span class="badge bg-warning-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Tertahan') }}</span>
                                            @elseif($fund->status === 'released')
                                                <span class="badge bg-success-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Dicairkan') }}</span>
                                            @else
                                                <span class="badge bg-danger-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Batal') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile Stacked Card List --}}
                    <div class="d-md-none p-3 d-flex flex-column gap-2.5">
                        @foreach($heldFunds as $fund)
                            <div class="m3-mobile-item-card">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-1.5">
                                    <div class="min-w-0">
                                        <div class="fw-bold text-primary font-monospace small">{{ $fund->order->order_ref ?? 'N/A' }}</div>
                                        <div class="fw-semibold text-body small">{{ $fund->product->name ?? 'N/A' }}</div>
                                    </div>
                                    <div class="flex-shrink-0">
                                        @if($fund->status === 'held')
                                            <span class="badge bg-warning-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Tertahan') }}</span>
                                        @elseif($fund->status === 'released')
                                            <span class="badge bg-success-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Dicairkan') }}</span>
                                        @else
                                            <span class="badge bg-danger-subtle rounded-pill px-2.5 py-1 fw-semibold">{{ __('Batal') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-2 pt-2 mt-2" style="border-top: 1px solid var(--outline-variant);">
                                    <div class="text-muted" style="font-size: 0.74rem;">
                                        <i class="far fa-calendar-check me-1"></i>{{ __('Estimasi Cair:') }}
                                        <strong class="text-body">{{ $fund->release_at ? $fund->release_at->format('d M Y, H:i') : '-' }}</strong>
                                    </div>
                                    <div class="fw-bold text-success text-nowrap m3-tabular-nums">
                                        Rp {{ number_format($fund->amount, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($heldFunds->hasPages())
                        <div class="px-3 px-sm-4 py-3 border-top">
                            {{ $heldFunds->appends(request()->except('held_page'))->links() }}
                        </div>
                    @endif
                @else
                    <div class="text-center py-5 px-3">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--on-surface-variant); font-size: 1.4rem;">
                            <i class="fas fa-shield-halved"></i>
                        </span>
                        <p class="fw-semibold text-body mb-1 small">{{ __('Belum ada saldo tertahan aktif.') }}</p>
                        <p class="text-muted small mb-0">{{ __('Komisi dari produk bergaransi yang sedang ditahan akan tampil di sini.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('modals')
@foreach($withdrawals as $withdrawal)
@if($withdrawal->status === 'approved')
{{-- View Proof Modal (M3 Dialog) --}}
<div class="modal fade" id="viewProofModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--radius-xl, 28px); border: 1px solid var(--outline-variant);">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <h5 class="fw-bold mb-0 text-body">{{ __('Struk Bukti Transfer Payout') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="modal-body px-4 py-3 text-center">
                @if($withdrawal->proof_image_path)
                    <img src="{{ asset($withdrawal->proof_image_path) }}" alt="{{ __('Bukti Transfer Payout') }}" class="img-fluid rounded-4 border mb-3" style="max-height: 360px; object-fit: contain; border-color: var(--outline-variant) !important;">
                @else
                    <div class="p-4 rounded-4 mb-3 text-muted small" style="background: var(--surface-container-low); border: 1px solid var(--outline-variant);">
                        <i class="fas fa-image fa-2x mb-2 opacity-50 d-block"></i>
                        {{ __('Bukti transfer belum diunggah oleh admin.') }}
                    </div>
                @endif

                <div class="p-3 text-start" style="background-color: var(--surface-container-low); border: 1px solid var(--outline-variant); border-radius: var(--radius-md, 16px);">
                    <div class="d-flex justify-content-between gap-2 small py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <span class="text-muted">{{ __('Bank Tujuan') }}</span>
                        <span class="fw-bold text-end text-body">{{ strtoupper($withdrawal->bank_name) }} — {{ $withdrawal->account_number }}</span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 small py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <span class="text-muted">{{ __('Atas Nama') }}</span>
                        <span class="fw-bold text-end text-body">{{ $withdrawal->account_holder }}</span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 small py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <span class="text-muted">{{ __('Jumlah Dana') }}</span>
                        <span class="fw-bold text-success text-end m3-tabular-nums">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 small pt-1.5">
                        <span class="text-muted">{{ __('Tanggal Diproses') }}</span>
                        <span class="fw-bold text-end text-body">{{ $withdrawal->processed_at ? $withdrawal->processed_at->format('d M Y, H:i') : '-' }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button type="button" class="btn btn-tonal px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@elseif($withdrawal->status === 'rejected')
{{-- View Rejection Modal (M3 Dialog) --}}
<div class="modal fade" id="viewRejectionModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--radius-xl, 28px); border: 1px solid var(--outline-variant);">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <h5 class="fw-bold mb-0 text-danger">{{ __('Pengajuan Payout Ditolak') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="p-3 mb-3" style="background-color: var(--error-container); color: var(--on-error-container); border-radius: var(--radius-md, 16px);">
                    <div class="fw-bold mb-1 small">{{ __('Alasan Penolakan dari Admin:') }}</div>
                    <div class="small lh-base">{{ $withdrawal->rejection_reason ?? __('Tidak ada alasan penolakan yang dicantumkan.') }}</div>
                </div>
                <div class="p-3 text-start" style="background-color: var(--surface-container-low); border: 1px solid var(--outline-variant); border-radius: var(--radius-md, 16px);">
                    <div class="d-flex justify-content-between gap-2 small py-1.5 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <span class="text-muted">{{ __('Jumlah') }}</span>
                        <span class="fw-bold text-danger text-end m3-tabular-nums">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 small pt-1.5">
                        <span class="text-muted">{{ __('Tanggal Ditolak') }}</span>
                        <span class="fw-bold text-end text-body">{{ $withdrawal->processed_at ? $withdrawal->processed_at->format('d M Y, H:i') : '-' }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button type="button" class="btn btn-tonal px-4 rounded-pill fw-semibold" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>
@endif
@endforeach
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const amountInput = document.getElementById('withdrawAmountInput');
    if (!amountInput) return;

    document.querySelectorAll('[data-withdraw-amount]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            const val = parseInt(this.dataset.withdrawAmount || '0', 10);
            if (val >= 10000) {
                amountInput.value = val;
                amountInput.focus();
            }
        });
    });
});
</script>
@endpush
@endsection
