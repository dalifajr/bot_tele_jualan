@extends('layouts.app')

@section('title', __('Layanan Rekber (Rekening Bersama)'))
@section('page_subtitle', __('Rekber'))

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12 col-md-8">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px;">
                <i class="fas fa-handshake fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-1">{{ __('Layanan Rekber (Rekening Bersama)') }}</h4>
                <p class="text-muted small mb-0">{{ __('Jembatan transaksi aman untuk jual beli produk digital di luar platform dengan perantara resmi toko.') }}</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 text-md-end d-flex align-items-center justify-content-md-end gap-2">
        <a href="{{ route('escrow.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
            <i class="fas fa-plus me-1.5"></i> {{ __('Buat Transaksi Baru') }}
        </a>
    </div>
</div>

{{-- Status Filter Tabs --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius: 14px;">
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
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($transactions->isEmpty())
            <div class="text-center py-5">
                <div class="rounded-circle bg-light d-inline-flex p-4 text-muted mb-3">
                    <i class="fas fa-shield-alt fs-1 opacity-50"></i>
                </div>
                <h6 class="fw-bold text-dark">{{ __('Belum Ada Transaksi Rekber') }}</h6>
                <p class="text-muted small mb-3">{{ __('Mulai transaksi jual beli yang aman dengan perantara resmi kami.') }}</p>
                <a href="{{ route('escrow.create') }}" class="btn btn-sm btn-primary rounded-pill px-4">
                    <i class="fas fa-plus me-1"></i> {{ __('Buat Rekber Pertama') }}
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-4 py-3 small text-muted text-uppercase" style="font-size: 0.72rem;">{{ __('Kode & Barang') }}</th>
                            <th class="py-3 small text-muted text-uppercase" style="font-size: 0.72rem;">{{ __('Pihak Terkait') }}</th>
                            <th class="py-3 small text-muted text-uppercase" style="font-size: 0.72rem;">{{ __('Nominal & Biaya') }}</th>
                            <th class="py-3 small text-muted text-uppercase" style="font-size: 0.72rem;">{{ __('Status') }}</th>
                            <th class="py-3 small text-muted text-uppercase" style="font-size: 0.72rem;">{{ __('Terakhir Update') }}</th>
                            <th class="pe-4 py-3 text-end small text-muted text-uppercase" style="font-size: 0.72rem;">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $trx)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark">{{ $trx->title }}</div>
                                    <div class="d-flex align-items-center gap-2 mt-0.5">
                                        <code class="small text-primary fw-semibold">{{ $trx->transaction_code }}</code>
                                        @if($trx->creator_id === Auth::id())
                                            <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;">{{ __('Inisiator') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="small">
                                        <span class="text-muted">{{ __('Pembeli:') }}</span> <strong class="text-dark">{{ $trx->buyer_name }}</strong>
                                    </div>
                                    <div class="small">
                                        <span class="text-muted">{{ __('Penjual:') }}</span> <strong class="text-dark">{{ $trx->seller_name }}</strong>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-dark">Rp {{ number_format($trx->amount, 0, ',', '.') }}</div>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        Fee: Rp {{ number_format($trx->fee_amount, 0, ',', '.') }}
                                        <span class="text-capitalize">({{ $trx->fee_payer === 'split' ? 'Split 50:50' : $trx->fee_payer }})</span>
                                    </small>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-{{ $trx->status_badge }} rounded-pill px-2.5 py-1.5" style="font-size: 0.75rem;">
                                        {{ $trx->status_label }}
                                    </span>
                                </td>
                                <td class="py-3 text-muted small">
                                    {{ $trx->updated_at->diffForHumans() }}
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <a href="{{ route('escrow.show', $trx->transaction_code) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                                        {{ __('Buka Ruang') }} <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($transactions->hasPages())
                <div class="p-3 border-top d-flex justify-content-center">
                    {{ $transactions->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
