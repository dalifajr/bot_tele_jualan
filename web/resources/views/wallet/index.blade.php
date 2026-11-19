@extends('layouts.app')

@section('title', __('Dompet Saldo'))
@section('page_subtitle', __('Top-up saldo & checkout instan 1-klik'))

@section('content')
<div class="row g-4">
    {{-- Balance Overview & Top-Up Card --}}
    <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm mb-4" style="background: var(--primary-container); color: var(--on-primary-container);">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="small fw-semibold opacity-75 text-uppercase" style="letter-spacing: 0.05em;">{{ __('Saldo Dompet Tersedia') }}</span>
                        <h2 class="fw-bold mb-0 mt-1" style="font-size: 2rem;">Rp {{ number_format((int) ($user->customer_wallet_balance ?? 0), 0, ',', '.') }}</h2>
                    </div>
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: var(--primary); color: var(--on-primary, #fff);">
                        <i class="fas fa-wallet fs-5"></i>
                    </div>
                </div>
                <div class="row g-2 pt-2 border-top" style="border-color: rgba(var(--primary-rgb), 0.18) !important;">
                    <div class="col-6">
                        <small class="d-block opacity-75">{{ __('Total Masuk') }}</small>
                        <strong class="small">Rp {{ number_format($totalCredits, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-6">
                        <small class="d-block opacity-75">{{ __('Total Belanja') }}</small>
                        <strong class="small">Rp {{ number_format($totalDebits, 0, ',', '.') }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1 text-body">
                    <i class="fas fa-plus-circle text-primary me-1.5"></i>{{ __('Top-Up Saldo Instan') }}
                </h5>
                <p class="small text-secondary mb-3">{{ __('Isi saldo sekali untuk checkout 1-klik tanpa kode unik berulang.') }}</p>

                <form action="{{ route('wallet.topup.store') }}" method="POST" id="walletTopupForm">
                    @csrf
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach([25000, 50000, 100000, 250000, 500000] as $preset)
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold wallet-preset-btn" data-amount="{{ $preset }}">
                                Rp {{ number_format($preset, 0, ',', '.') }}
                            </button>
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Nominal Top-Up (Rp)') }}</label>
                        <input type="number" name="amount" id="walletTopupAmount" class="form-control form-control-lg fw-bold" min="10000" max="50000000" step="1000" value="{{ old('amount', 50000) }}" required>
                        <small class="text-muted">{{ __('Minimal top-up Rp 10.000') }}</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-2.5 fw-bold">
                        <i class="fas fa-bolt me-1.5"></i>{{ __('Buat Permintaan Top-Up') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Pending Top-ups & Transaction History --}}
    <div class="col-lg-7">
        @if($pendingTopups->isNotEmpty())
            <div class="card border-0 rounded-4 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 text-body">
                        <i class="fas fa-clock text-warning me-1.5"></i>{{ __('Menunggu Pembayaran Top-Up') }}
                    </h5>

                    @foreach($pendingTopups as $topup)
                        <div class="p-3 rounded-4 border mb-3" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <div>
                                    <span class="badge bg-warning-subtle text-warning rounded-pill fw-bold me-1.5">{{ __('Menunggu') }}</span>
                                    <strong class="font-monospace text-body small">{{ $topup->topup_ref }}</strong>
                                </div>
                                <small class="text-muted">
                                    {{ __('Berlaku hingga') }}: {{ optional($topup->expires_at)->format('H:i') }}
                                </small>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 my-2">
                                <div>
                                    <small class="text-secondary d-block">{{ __('Transfer Tepat Nominal (termasuk kode unik +Rp:code)', ['code' => $topup->unique_code]) }}</small>
                                    <div class="fs-4 fw-bold text-primary">Rp {{ number_format($topup->total_pay, 0, ',', '.') }}</div>
                                </div>
                                <form action="{{ route('wallet.topup.cancel', $topup->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                        <i class="fas fa-times me-1"></i>{{ __('Batalkan') }}
                                    </button>
                                </form>
                            </div>

                            @if($bankInfo)
                                <div class="p-2.5 rounded-3 small mt-2 border" style="background: var(--surface-container-lowest); border-color: var(--outline-variant) !important; white-space: pre-line;">{{ $bankInfo }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3 text-body">
                    <i class="fas fa-list-ul text-primary me-1.5"></i>{{ __('Riwayat Mutasi Dompet') }}
                </h5>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="text-muted small">
                                <th>{{ __('Waktu') }}</th>
                                <th>{{ __('Keterangan') }}</th>
                                <th class="text-end">{{ __('Nominal') }}</th>
                                <th class="text-end">{{ __('Saldo Akhir') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $tx)
                                <tr>
                                    <td class="small text-secondary text-nowrap">{{ $tx->created_at->format('d M Y H:i') }}</td>
                                    <td>
                                        <div class="fw-semibold text-body small">{{ $tx->description ?: ucfirst($tx->category) }}</div>
                                        @if($tx->reference)
                                            <small class="font-monospace text-muted">{{ $tx->reference }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end fw-bold text-nowrap {{ $tx->type === 'credit' ? 'text-success' : 'text-danger' }}">
                                        {{ $tx->type === 'credit' ? '+' : '-' }}Rp {{ number_format($tx->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="text-end small fw-semibold text-body text-nowrap">
                                        Rp {{ number_format($tx->balance_after, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4 small">
                                        <i class="fas fa-wallet fa-2x mb-2 opacity-25 d-block"></i>
                                        {{ __('Belum ada mutasi saldo dompet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($transactions->hasPages())
                    <div class="mt-3">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('walletTopupAmount');
    document.querySelectorAll('.wallet-preset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (input) input.value = this.dataset.amount;
        });
    });
});
</script>
@endsection
