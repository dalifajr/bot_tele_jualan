@extends('layouts.app')

@section('title', __('Kelola Layanan Rekber'))
@section('page_subtitle', __('Admin / Rekber'))

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12 col-md-5">
        <h4 class="fw-bold mb-1 text-dark">{{ __('Kelola Layanan Rekber (Escrow)') }}</h4>
        <p class="text-muted small mb-0">{{ __('Kelola transaksi rekening bersama, verifikasi deposit masuk, pelepasan payout, dan penyelesaian sengketa.') }}</p>
    </div>
    <div class="col-12 col-md-7 text-md-end d-flex align-items-center justify-content-md-end gap-2 flex-wrap">
        <a href="{{ route('admin.escrow.create') }}" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
            <i class="fas fa-plus me-1"></i> {{ __('Inisiasi Rekber Baru') }}
        </a>
        <button type="button" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#escrowSettingsModal">
            <i class="fas fa-cog me-1"></i> {{ __('Tarif & Rekening') }}
        </button>
    </div>
</div>

{{-- Metric Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 14px;">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="fas fa-shield-halved fs-5"></i>
                </div>
                <div>
                    <small class="text-muted d-block">{{ __('Dana Diamankan') }}</small>
                    <span class="fs-5 fw-bold text-dark">Rp {{ number_format($holdingFunds, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 14px;">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="fas fa-receipt fs-5"></i>
                </div>
                <div>
                    <small class="text-muted d-block">{{ __('Verifikasi Deposit') }}</small>
                    <span class="fs-5 fw-bold text-dark">{{ $pendingDepositCount }} {{ __('Tiket') }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 14px;">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="fas fa-money-bill-transfer fs-5"></i>
                </div>
                <div>
                    <small class="text-muted d-block">{{ __('Menunggu Payout') }}</small>
                    <span class="fs-5 fw-bold text-dark">{{ $pendingPayoutCount }} {{ __('Tiket') }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100" style="border-radius: 14px;">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="fas fa-gavel fs-5"></i>
                </div>
                <div>
                    <small class="text-muted d-block">{{ __('Sengketa Terbuka') }}</small>
                    <span class="fs-5 fw-bold {{ $disputedCount > 0 ? 'text-danger' : 'text-dark' }}">{{ $disputedCount }} {{ __('Kasus') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters & Search --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius: 14px;">
    <div class="card-body p-3">
        <form action="{{ route('admin.escrow.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="{{ __('Cari kode, judul, pembeli, penjual...') }}" value="{{ $search }}">
                </div>
            </div>
            <div class="col-8 col-md-6">
                <div class="d-flex gap-1 flex-nowrap overflow-x-auto" style="scrollbar-width: thin;">
                    <a href="{{ route('admin.escrow.index', ['status' => 'all']) }}" class="btn btn-xs rounded-pill px-2.5 py-1 {{ empty($status) || $status === 'all' ? 'btn-primary' : 'btn-light' }}">{{ __('Semua') }}</a>
                    <a href="{{ route('admin.escrow.index', ['status' => \App\Models\EscrowTransaction::STATUS_DEPOSIT_SUBMITTED]) }}" class="btn btn-xs rounded-pill px-2.5 py-1 {{ $status === \App\Models\EscrowTransaction::STATUS_DEPOSIT_SUBMITTED ? 'btn-info text-white' : 'btn-light' }}">{{ __('Deposit') }} ({{ $pendingDepositCount }})</a>
                    <a href="{{ route('admin.escrow.index', ['status' => \App\Models\EscrowTransaction::STATUS_WAITING_PAYOUT]) }}" class="btn btn-xs rounded-pill px-2.5 py-1 {{ $status === \App\Models\EscrowTransaction::STATUS_WAITING_PAYOUT ? 'btn-warning text-dark' : 'btn-light' }}">{{ __('Payout') }} ({{ $pendingPayoutCount }})</a>
                    <a href="{{ route('admin.escrow.index', ['status' => \App\Models\EscrowTransaction::STATUS_DISPUTED]) }}" class="btn btn-xs rounded-pill px-2.5 py-1 {{ $status === \App\Models\EscrowTransaction::STATUS_DISPUTED ? 'btn-danger text-white' : 'btn-light text-danger' }}">{{ __('Sengketa') }} ({{ $disputedCount }})</a>
                    <a href="{{ route('admin.escrow.index', ['status' => \App\Models\EscrowTransaction::STATUS_COMPLETED]) }}" class="btn btn-xs rounded-pill px-2.5 py-1 {{ $status === \App\Models\EscrowTransaction::STATUS_COMPLETED ? 'btn-success text-white' : 'btn-light' }}">{{ __('Selesai') }}</a>
                </div>
            </div>
            <div class="col-4 col-md-2 text-end">
                <button type="submit" class="btn btn-sm btn-light rounded-pill px-3">{{ __('Filter') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Transactions Table --}}
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($transactions->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="fas fa-handshake-slash fs-2 mb-2 opacity-50"></i>
                <p class="small mb-0">{{ __('Tidak ada transaksi rekber ditemukan.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-4 py-3 small text-muted text-uppercase">{{ __('Kode & Produk') }}</th>
                            <th class="py-3 small text-muted text-uppercase">{{ __('Pembeli') }}</th>
                            <th class="py-3 small text-muted text-uppercase">{{ __('Penjual') }}</th>
                            <th class="py-3 small text-muted text-uppercase">{{ __('Nominal & Fee') }}</th>
                            <th class="py-3 small text-muted text-uppercase">{{ __('Status') }}</th>
                            <th class="pe-4 py-3 text-end small text-muted text-uppercase">{{ __('Aksi') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $trx)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark">{{ $trx->title }}</div>
                                    <code class="small text-primary">{{ $trx->transaction_code }}</code>
                                </td>
                                <td class="py-3 small">
                                    <strong class="text-dark">{{ $trx->buyer_name }}</strong>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $trx->buyer_contact }}</div>
                                </td>
                                <td class="py-3 small">
                                    <strong class="text-dark">{{ $trx->seller_name }}</strong>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $trx->seller_contact }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-dark">Rp {{ number_format($trx->amount, 0, ',', '.') }}</div>
                                    <small class="text-muted" style="font-size: 0.72rem;">
                                        Fee: Rp {{ number_format($trx->fee_amount, 0, ',', '.') }}
                                    </small>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-{{ $trx->status_badge }} rounded-pill px-2.5 py-1">
                                        {{ $trx->status_label }}
                                    </span>
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-light rounded-pill px-2.5 border" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Pilihan Aksi') }}">
                                            <i class="fas fa-ellipsis-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                                            <li>
                                                <a class="dropdown-item small py-2" href="{{ route('admin.escrow.show', $trx->transaction_code) }}">
                                                    <i class="fas fa-eye text-primary me-2"></i> {{ __('Detail & Kelola') }}
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item small py-2" href="{{ route('admin.escrow.edit', $trx->transaction_code) }}">
                                                    <i class="fas fa-edit text-warning me-2"></i> {{ __('Edit Transaksi') }}
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item small py-2" href="{{ route('escrow.public.show', $trx->public_token) }}" target="_blank">
                                                    <i class="fas fa-external-link-alt text-info me-2"></i> {{ __('Buka Ruang Publik') }}
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <form action="{{ route('admin.escrow.destroy', $trx->transaction_code) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi rekber {{ $trx->transaction_code }} beserta seluruh obrolan dan bukti lampiran?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item small py-2 text-danger">
                                                        <i class="fas fa-trash-alt me-2"></i> {{ __('Hapus Permanen') }}
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                    <a href="{{ route('admin.escrow.show', $trx->transaction_code) }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold ms-1">
                                        {{ __('Kelola') }}
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

{{-- Escrow Settings Modal --}}
<div class="modal fade" id="escrowSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 16px;">
            <div class="modal-header border-bottom p-3">
                <h6 class="modal-title fw-bold text-dark">
                    <i class="fas fa-sliders text-primary me-1.5"></i> {{ __('Pengaturan Tarif & Rekening Rekber') }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.escrow.settings') }}" method="POST">
                @csrf
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Persentase Biaya Rekber (%)') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.1" name="fee_percent" class="form-control rounded-start-3" value="{{ $feePercent }}" min="0" max="50" required>
                            <span class="input-group-text bg-light text-muted">%</span>
                        </div>
                        <div class="form-text small text-muted">{{ __('Biaya dihitung murni berdasarkan persentase nominal transaksi rekber.') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">{{ __('Informasi Rekening Bank Escrow Toko') }} <span class="text-danger">*</span></label>
                        <textarea name="bank_info" class="form-control rounded-3 font-monospace small" rows="4" placeholder="{{ __('Tuliskan nomor rekening bank resmi toko untuk menerima transfer deposit pembeli...') }}" required>{{ $bankInfo }}</textarea>
                        <div class="form-text small text-muted">{{ __('Informasi ini akan ditampilkan kepada pembeli saat instruksi pembayaran rekber.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Simpan Pengaturan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
