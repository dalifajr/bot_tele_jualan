@extends('layouts.app')

@section('title', __('Top-Up Dompet Pelanggan'))
@section('page_subtitle', __('Verifikasi deposit saldo pembeli'))

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Menunggu Verifikasi') }}</small>
                <div class="fs-3 fw-bold text-warning mt-1">{{ $pendingCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Total Saldo Aktif Pelanggan') }}</small>
                <div class="fs-3 fw-bold text-primary mt-1">Rp {{ number_format($totalCustomerBalance, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Total Top-Up Terkonfirmasi') }}</small>
                <div class="fs-3 fw-bold text-success mt-1">Rp {{ number_format($totalPaidTopup, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 rounded-4 shadow-sm">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3 text-body"><i class="fas fa-coins text-primary me-1.5"></i>{{ __('Daftar Permintaan Top-Up Saldo') }}</h5>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th>{{ __('Referensi') }}</th>
                        <th>{{ __('Pelanggan') }}</th>
                        <th class="text-end">{{ __('Nominal Saldo') }}</th>
                        <th class="text-end">{{ __('Total Transfer') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topups as $topup)
                        <tr>
                            <td>
                                <div class="fw-bold font-monospace small text-body">{{ $topup->topup_ref }}</div>
                                <small class="text-muted">{{ $topup->created_at->format('d M Y H:i') }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold small text-body">{{ $topup->user->full_name ?? $topup->user->username ?? ('User #' . $topup->user_id) }}</div>
                                <small class="text-muted">{{ '@' . ($topup->user->username ?? '-') }}</small>
                            </td>
                            <td class="text-end fw-semibold">Rp {{ number_format($topup->amount, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold text-primary">Rp {{ number_format($topup->total_pay, 0, ',', '.') }}</td>
                            <td>
                                @if($topup->status === 'paid')
                                    <span class="badge bg-success-subtle text-success rounded-pill">{{ __('Terkonfirmasi') }}</span>
                                @elseif($topup->status === 'pending')
                                    <span class="badge bg-warning-subtle text-warning rounded-pill">{{ __('Menunggu') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill">{{ ucfirst($topup->status) }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($topup->status === 'pending')
                                    <div class="d-inline-flex gap-1.5">
                                        <form action="{{ route('admin.wallet.topup.confirm', $topup->id) }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                                                <i class="fas fa-check me-1"></i>{{ __('Konfirmasi') }}
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.wallet.topup.reject', $topup->id) }}" method="POST" class="m-0">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4 small">{{ __('Belum ada permintaan top-up saldo.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($topups->hasPages())
            <div class="mt-3">{{ $topups->links() }}</div>
        @endif
    </div>
</div>
@endsection
