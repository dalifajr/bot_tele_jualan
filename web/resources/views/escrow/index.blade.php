@extends('layouts.app')

@section('title', __('Layanan Rekber (Rekening Bersama)'))
@section('page_subtitle', __('Rekber'))

@section('content')
<div class="row g-3 mb-3 mb-md-4 align-items-center">
    <div class="col-12 col-md-8">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                <i class="fas fa-handshake fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1 text-body fs-5 fs-md-4">{{ __('Layanan Rekber (Rekening Bersama)') }}</h4>
                <p class="text-muted small mb-0">{{ __('Jembatan transaksi aman untuk jual beli produk digital dengan perantara resmi toko.') }}</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 text-md-end">
        <a href="{{ route('escrow.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold w-100 w-md-auto d-inline-flex align-items-center justify-content-center gap-2" style="min-height: 44px;">
            <i class="fas fa-plus"></i>
            <span>{{ __('Buat Transaksi Baru') }}</span>
        </a>
    </div>
</div>

{{-- Status Filter Tabs (Scrollable Segmented Pill) --}}
<div class="card border-0 shadow-sm mb-3 mb-md-4" style="border-radius: var(--radius-lg, 20px);">
    <div class="card-body p-2">
        <ul class="nav nav-pills gap-1 flex-nowrap overflow-x-auto pb-1" style="scrollbar-width: thin;">
            <li class="nav-item">
                <a href="{{ route('escrow.index') }}" class="nav-link rounded-pill py-1.5 px-3 small {{ empty($status) || $status === 'all' ? 'active' : '' }}">
                    {{ __('Semua Transaksi') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('escrow.index', ['status' => 'active']) }}" class="nav-link rounded-pill py-1.5 px-3 small {{ $status === 'active' ? 'active' : '' }}">
                    {{ __('Berlangsung') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('escrow.index', ['status' => \App\Models\EscrowTransaction::STATUS_WAITING_DEPOSIT]) }}" class="nav-link rounded-pill py-1.5 px-3 small {{ $status === \App\Models\EscrowTransaction::STATUS_WAITING_DEPOSIT ? 'active' : '' }}">
                    {{ __('Menunggu Bayar') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('escrow.index', ['status' => \App\Models\EscrowTransaction::STATUS_FUNDS_HELD]) }}" class="nav-link rounded-pill py-1.5 px-3 small {{ $status === \App\Models\EscrowTransaction::STATUS_FUNDS_HELD ? 'active' : '' }}">
                    {{ __('Dana Ditahan') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('escrow.index', ['status' => \App\Models\EscrowTransaction::STATUS_DELIVERED]) }}" class="nav-link rounded-pill py-1.5 px-3 small {{ $status === \App\Models\EscrowTransaction::STATUS_DELIVERED ? 'active' : '' }}">
                    {{ __('Pemeriksaan') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('escrow.index', ['status' => \App\Models\EscrowTransaction::STATUS_DISPUTED]) }}" class="nav-link rounded-pill py-1.5 px-3 small {{ $status === \App\Models\EscrowTransaction::STATUS_DISPUTED ? 'active text-white bg-danger' : 'text-danger' }}">
                    {{ __('Sengketa') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('escrow.index', ['status' => \App\Models\EscrowTransaction::STATUS_COMPLETED]) }}" class="nav-link rounded-pill py-1.5 px-3 small {{ $status === \App\Models\EscrowTransaction::STATUS_COMPLETED ? 'active' : '' }}">
                    {{ __('Selesai') }}
                </a>
            </li>
        </ul>
    </div>
</div>

{{-- Transactions List --}}
<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: var(--radius-lg, 20px);">
    <div class="card-body p-0">
        @if($transactions->isEmpty())
            <div class="text-center py-5 px-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--on-surface-variant); font-size: 1.4rem;">
                    <i class="fas fa-shield-halved"></i>
                </span>
                <h6 class="fw-bold text-body mb-1">{{ __('Belum Ada Transaksi Rekber') }}</h6>
                <p class="text-muted small mb-3">{{ __('Mulai transaksi jual beli yang aman dengan perantara resmi kami.') }}</p>
                <a href="{{ route('escrow.create') }}" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold">
                    <i class="fas fa-plus me-1"></i> {{ __('Buat Rekber Pertama') }}
                </a>
            </div>
        @else
            {{-- Desktop Table View --}}
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-secondary small border-bottom">
                            <th class="ps-4 py-3">{{ __('Kode & Barang') }}</th>
                            <th class="py-3">{{ __('Pihak Terkait') }}</th>
                            <th class="py-3">{{ __('Nominal & Biaya') }}</th>
                            <th class="py-3">{{ __('Status') }}</th>
                            <th class="py-3">{{ __('Terakhir Update') }}</th>
                            <th class="pe-4 py-3 text-end">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $trx)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-body">{{ $trx->title }}</div>
                                    <div class="d-flex align-items-center gap-2 mt-0.5">
                                        <code class="small text-primary fw-semibold">{{ $trx->transaction_code }}</code>
                                        @if($trx->creator_id === Auth::id())
                                            <span class="badge bg-secondary-subtle rounded-pill" style="font-size: 0.65rem;">{{ __('Inisiator') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="small">
                                        <span class="text-muted">{{ __('Pembeli:') }}</span> <strong class="text-body">{{ $trx->buyer_name }}</strong>
                                    </div>
                                    <div class="small">
                                        <span class="text-muted">{{ __('Penjual:') }}</span> <strong class="text-body">{{ $trx->seller_name }}</strong>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-body m3-tabular-nums">Rp {{ number_format($trx->amount, 0, ',', '.') }}</div>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        Fee: Rp {{ number_format($trx->fee_amount, 0, ',', '.') }}
                                        <span class="text-capitalize">({{ $trx->fee_payer === 'split' ? 'Split 50:50' : $trx->fee_payer }})</span>
                                    </small>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-{{ $trx->status_badge }} rounded-pill px-2.5 py-1.5 fw-semibold" style="font-size: 0.75rem;">
                                        {{ $trx->status_label }}
                                    </span>
                                </td>
                                <td class="py-3 text-muted small">
                                    {{ $trx->updated_at->diffForHumans() }}
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <a href="{{ route('escrow.show', $trx->transaction_code) }}" class="btn btn-sm btn-tonal rounded-pill px-3 fw-semibold">
                                        {{ __('Buka Ruang') }} <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Collapsible Compact List --}}
            <div class="d-md-none p-2.5 m3-collapsible-list" id="customerEscrowAccordion">
                @foreach($transactions as $trx)
                    <div class="m3-mobile-item-card m3-collapsible-card">
                        <div class="m3-compact-row">
                            <button type="button"
                                    class="m3-compact-trigger"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#customerEscrowCollapse-{{ $trx->id }}"
                                    aria-expanded="false"
                                    aria-controls="customerEscrowCollapse-{{ $trx->id }}">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-bold text-body text-truncate" style="font-size: 0.85rem;">{{ $trx->title }}</div>
                                    <div class="text-secondary text-truncate" style="font-size: 0.73rem;">
                                        <span class="font-monospace text-primary">{{ $trx->transaction_code }}</span> · {{ $trx->updated_at->diffForHumans() }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <div class="text-end">
                                        <div class="fw-bold text-body m3-tabular-nums" style="font-size: 0.8rem;">Rp {{ number_format($trx->amount, 0, ',', '.') }}</div>
                                        <span class="badge bg-{{ $trx->status_badge }} rounded-pill px-2 py-0" style="font-size: 0.64rem;">{{ $trx->status_label }}</span>
                                    </div>
                                    <i class="fas fa-chevron-down m3-compact-chevron"></i>
                                </div>
                            </button>
                        </div>

                        <div class="collapse" id="customerEscrowCollapse-{{ $trx->id }}" data-bs-parent="#customerEscrowAccordion">
                            <div class="m3-collapsible-body">
                                <div class="small text-secondary mb-2.5">
                                    <div class="d-flex flex-wrap align-items-center gap-1.5 mb-1">
                                        <span>{{ __('Pembeli:') }} <strong class="text-body">{{ $trx->buyer_name }}</strong></span>
                                        <span>&bull;</span>
                                        <span>{{ __('Penjual:') }} <strong class="text-body">{{ $trx->seller_name }}</strong></span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="m3-tabular-nums">Fee: Rp {{ number_format($trx->fee_amount, 0, ',', '.') }}</span>
                                        @if($trx->creator_id === Auth::id())
                                            <span class="badge bg-secondary-subtle text-body rounded-pill" style="font-size: 0.65rem;">{{ __('Inisiator') }}</span>
                                        @endif
                                    </div>
                                </div>

                                <a href="{{ route('escrow.show', $trx->transaction_code) }}" class="btn btn-sm btn-tonal m3-compact-action-btn w-100">
                                    <span>{{ __('Buka Ruang') }}</span>
                                    <i class="fas fa-arrow-right small"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($transactions->hasPages())
                <div class="px-3 px-sm-4 py-3 border-top">
                    {{ $transactions->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
