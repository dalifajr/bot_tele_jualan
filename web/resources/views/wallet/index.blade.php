@extends('layouts.app')

@section('title', __('Dompet Saldo'))
@section('page_subtitle', __('Top-up saldo QRIS otomatis & checkout instan 1-klik'))

@section('content')
<div class="row g-3 g-lg-4">
    {{-- Balance Overview & Top-Up Card --}}
    <div class="col-12 col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm mb-3 mb-lg-4" style="background: var(--primary-container); color: var(--on-primary-container);">
            <div class="card-body p-3 p-sm-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div class="min-w-0">
                        <span class="small fw-semibold opacity-75 text-uppercase" style="letter-spacing: 0.05em; font-size: 0.72rem;">{{ __('Saldo Dompet Tersedia') }}</span>
                        <h2 class="fw-bold mb-0 mt-1 m3-tabular-nums" style="font-size: clamp(1.6rem, 5vw, 2rem); line-height: 1.2;">
                            Rp {{ number_format((int) ($user->customer_wallet_balance ?? 0), 0, ',', '.') }}
                        </h2>
                    </div>
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background: var(--primary); color: var(--on-primary, #fff);">
                        <i class="fas fa-wallet fs-5"></i>
                    </div>
                </div>
                <div class="row g-2 pt-2 border-top" style="border-color: rgba(var(--primary-rgb), 0.18) !important;">
                    <div class="col-6">
                        <small class="d-block opacity-75" style="font-size: 0.74rem;">{{ __('Total Masuk') }}</small>
                        <strong class="small m3-tabular-nums">Rp {{ number_format($totalCredits, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-6">
                        <small class="d-block opacity-75" style="font-size: 0.74rem;">{{ __('Total Belanja') }}</small>
                        <strong class="small m3-tabular-nums">Rp {{ number_format($totalDebits, 0, ',', '.') }}</strong>
                    </div>
                </div>

                {{-- Smartphone Action Button: Opens Modal Topup --}}
                <div class="d-md-none mt-3 pt-2">
                    <button type="button" class="btn btn-primary w-100 rounded-pill py-2.5 fw-bold d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#topupModal" style="min-height: 44px;">
                        <i class="fas fa-qrcode"></i>
                        <span>{{ __('Top-Up Saldo QRIS') }}</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Desktop Inline Topup Card --}}
        <div class="card border-0 rounded-4 shadow-sm d-none d-md-block">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1 text-body fs-6">
                    <i class="fas fa-qrcode text-primary me-1.5"></i>{{ __('Top-Up Saldo via QRIS') }}
                </h5>
                <p class="small text-secondary mb-3">
                    {{ __('Bayar via QRIS Dinamis dengan verifikasi otomatis real-time oleh sistem webhook, lalu nikmati checkout 1-klik tanpa kode unik.') }}
                </p>

                @include('wallet.partials.topup_form', ['idPrefix' => 'desktop'])
            </div>
        </div>
    </div>

    {{-- Pending QRIS Top-ups & Transaction History --}}
    <div class="col-12 col-lg-7">
        @if($pendingTopups->isNotEmpty())
            <div class="card border-0 rounded-4 shadow-sm mb-3 mb-lg-4">
                <div class="card-body p-3 p-sm-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h5 class="fw-bold mb-0 text-body fs-6">
                            <i class="fas fa-qrcode text-primary me-1.5"></i>{{ __('Selesaikan Pembayaran Top-Up') }}
                        </h5>
                        <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-semibold" style="font-size: 0.72rem;">
                            <i class="fas fa-sync fa-spin me-1"></i>{{ __('Cek Otomatis Aktif') }}
                        </span>
                    </div>

                    @foreach($pendingTopups as $topup)
                        <div class="p-3.5 rounded-4 border mb-3 wallet-pending-card"
                             data-topup-id="{{ $topup->id }}"
                             data-status-url="{{ route('wallet.topup.status', $topup->id) }}"
                             data-expires="{{ optional($topup->expires_at)->toIso8601String() }}"
                             style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning-subtle text-warning rounded-pill fw-bold">{{ __('Menunggu QRIS') }}</span>
                                    <strong class="font-monospace text-body small">{{ $topup->topup_ref }}</strong>
                                </div>
                                <small class="fw-bold text-danger wallet-countdown" data-expires="{{ optional($topup->expires_at)->toIso8601String() }}">
                                    <i class="far fa-clock me-1"></i>{{ optional($topup->expires_at)->format('H:i') }} {{ \App\Support\SystemTimezone::abbr() }}
                                </small>
                            </div>

                            <div class="row g-3 align-items-center">
                                @if(!empty($topup->dynamic_qris))
                                    <div class="col-sm-5 text-center">
                                        <div class="d-inline-flex flex-column align-items-center p-2.5 rounded-4 bg-white border shadow-sm">
                                            <canvas class="wallet-qris-canvas" id="walletQrisCanvas_{{ $topup->id }}" data-qris="{{ $topup->dynamic_qris }}" width="210" height="210"></canvas>
                                        </div>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fw-semibold btn-download-qris" data-canvas="walletQrisCanvas_{{ $topup->id }}" data-ref="{{ $topup->topup_ref }}">
                                                <i class="fas fa-download me-1"></i>{{ __('Unduh QRIS') }}
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                <div class="{{ !empty($topup->dynamic_qris) ? 'col-sm-7' : 'col-12' }}">
                                    <div class="p-3 rounded-4 border mb-2.5" style="background: var(--surface-container-lowest); border-color: var(--outline-variant) !important;">
                                        <small class="text-secondary d-block mb-0.5">{{ __('Nominal Saldo Masuk') }}</small>
                                        <div class="fw-bold text-body mb-2 m3-tabular-nums">Rp {{ number_format($topup->amount, 0, ',', '.') }}</div>

                                        <small class="text-secondary d-block mb-0.5">{{ __('Total Bayar Tepat (termasuk kode unik +Rp:code)', ['code' => $topup->unique_code]) }}</small>
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <div class="fs-4 fw-bold text-primary m3-tabular-nums">Rp {{ number_format($topup->total_pay, 0, ',', '.') }}</div>
                                            <button type="button" class="btn btn-sm btn-tonal rounded-pill px-2.5 py-1 fw-semibold btn-copy-amount" data-amount="{{ $topup->total_pay }}">
                                                <i class="fas fa-copy me-1"></i>{{ __('Salin') }}
                                            </button>
                                        </div>
                                    </div>

                                    @if(empty($topup->dynamic_qris) && $bankInfo)
                                        <div class="p-2.5 rounded-3 small mb-2.5 border" style="background: var(--surface-container-lowest); border-color: var(--outline-variant) !important; white-space: pre-line;">{{ $bankInfo }}</div>
                                    @endif

                                    <p class="small text-secondary mb-2.5" style="font-size: 0.76rem;">
                                        <i class="fas fa-circle-info text-primary me-1"></i>
                                        {{ __('Saldo masuk otomatis beberapa detik setelah pembayaran QRIS berhasil. Jika QRIS terkendala, hubungi Admin dengan kode') }} <strong class="font-monospace">{{ $topup->topup_ref }}</strong> {{ __('untuk konfirmasi manual.') }}
                                    </p>

                                    <form action="{{ route('wallet.topup.cancel', $topup->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                            <i class="fas fa-times me-1"></i>{{ __('Batalkan Top-Up') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Transaction History Card --}}
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-3 p-sm-4">
                <h5 class="fw-bold mb-3 text-body fs-6">
                    <i class="fas fa-list-ul text-primary me-1.5"></i>{{ __('Riwayat Mutasi Dompet') }}
                </h5>

                {{-- Desktop Table View --}}
                <div class="table-responsive d-none d-md-block">
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
                                    <td class="text-end fw-bold text-nowrap m3-tabular-nums {{ $tx->type === 'credit' ? 'text-success' : 'text-danger' }}">
                                        {{ $tx->type === 'credit' ? '+' : '-' }}Rp {{ number_format($tx->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="text-end small fw-semibold text-body text-nowrap m3-tabular-nums">
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

                {{-- Mobile Ultra-Compact 2-Line List --}}
                <div class="d-md-none d-flex flex-column gap-1.5">
                    @forelse($transactions as $tx)
                        <div class="m3-mobile-item-card is-ultra-compact">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="min-w-0 flex-grow-1">
                                    <div class="fw-semibold text-body text-truncate" style="font-size: 0.83rem;">{{ $tx->description ?: ucfirst($tx->category) }}</div>
                                    <div class="text-muted text-truncate" style="font-size: 0.72rem;">
                                        {{ $tx->created_at->format('d M, H:i') }}@if($tx->reference) · <span class="font-monospace">{{ $tx->reference }}</span>@endif
                                    </div>
                                </div>
                                <div class="text-end flex-shrink-0 m3-tabular-nums">
                                    <div class="fw-bold {{ $tx->type === 'credit' ? 'text-success' : 'text-danger' }}" style="font-size: 0.82rem;">
                                        {{ $tx->type === 'credit' ? '+' : '-' }}Rp {{ number_format($tx->amount, 0, ',', '.') }}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.69rem;">
                                        {{ __('Saldo:') }} Rp {{ number_format($tx->balance_after, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">
                            <i class="fas fa-wallet fa-2x mb-2 opacity-25 d-block"></i>
                            {{ __('Belum ada mutasi saldo dompet.') }}
                        </div>
                    @endforelse
                </div>

                @if($transactions->hasPages())
                    <div class="mt-3">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('modals')
{{-- Smartphone Top-Up Modal (M3 Sheet) --}}
<div class="modal fade m3-sheet-modal" id="topupModal" tabindex="-1" aria-labelledby="topupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: var(--radius-xl, 28px); border: 1px solid var(--outline-variant);">
            <div class="d-md-none m3-sheet-handle" aria-hidden="true"></div>
            <div class="modal-header border-0 px-4 pt-3 pt-md-4 pb-2">
                <div>
                    <h5 class="fw-bold mb-0 text-body" id="topupModalLabel">
                        <i class="fas fa-qrcode text-primary me-2"></i>{{ __('Top-Up Saldo via QRIS') }}
                    </h5>
                    <small class="text-muted">{{ __('Verifikasi otomatis real-time via webhook') }}</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="modal-body px-4 py-3">
                @include('wallet.partials.topup_form', ['idPrefix' => 'modal'])
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preset buttons support both desktop and modal inputs
    document.querySelectorAll('.wallet-preset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetSelector = this.dataset.target;
            const input = targetSelector ? document.querySelector(targetSelector) : document.querySelector('[name="amount"]');
            if (input) input.value = this.dataset.amount;
        });
    });

    // Render dynamic QRIS locally on canvas
    function renderAllQris() {
        document.querySelectorAll('.wallet-qris-canvas').forEach(canvas => {
            const payload = canvas.dataset.qris;
            if (!payload || canvas.dataset.rendered === '1') return;
            if (typeof window.renderLocalQrToCanvas === 'function') {
                canvas.dataset.rendered = '1';
                window.renderLocalQrToCanvas(canvas, payload, 210, {
                    dark: '#000000',
                    light: '#ffffff',
                    margin: 1
                }).catch(err => {
                    canvas.dataset.rendered = '0';
                    console.error('QRIS canvas render failed:', err);
                });
            }
        });
    }
    renderAllQris();
    setTimeout(renderAllQris, 400);

    // Download QRIS PNG
    document.querySelectorAll('.btn-download-qris').forEach(btn => {
        btn.addEventListener('click', function() {
            const canvas = document.getElementById(this.dataset.canvas);
            if (!canvas) return;
            const link = document.createElement('a');
            link.download = 'QRIS-' + (this.dataset.ref || 'TOPUP') + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    });

    // Copy exact amount
    document.querySelectorAll('.btn-copy-amount').forEach(btn => {
        btn.addEventListener('click', function() {
            const amt = this.dataset.amount;
            if (amt && navigator.clipboard) {
                navigator.clipboard.writeText(amt);
                const orig = this.innerHTML;
                this.innerHTML = '<i class="fas fa-check text-success me-1"></i>{{ __('Tersalin') }}';
                setTimeout(() => { this.innerHTML = orig; }, 1600);
            }
        });
    });

    // Countdown timers & live status polling for pending topups
    const pendingCards = document.querySelectorAll('.wallet-pending-card');
    if (pendingCards.length > 0) {
        const updateCountdowns = () => {
            document.querySelectorAll('.wallet-countdown').forEach(el => {
                const iso = el.dataset.expires;
                if (!iso) return;
                const diff = new Date(iso).getTime() - Date.now();
                if (diff <= 0) {
                    el.innerHTML = '<i class="far fa-clock me-1"></i>{{ __('Waktu Habis') }}';
                } else {
                    const mins = Math.floor(diff / 60000);
                    const secs = Math.floor((diff % 60000) / 1000);
                    el.innerHTML = `<i class="far fa-clock me-1"></i>${mins}m ${secs}s`;
                }
            });
        };
        updateCountdowns();
        setInterval(updateCountdowns, 1000);

        const pollPending = () => {
            pendingCards.forEach(card => {
                const url = card.dataset.statusUrl;
                if (!url) return;
                fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : null)
                    .then(data => {
                        if (!data) return;
                        if (data.status === 'paid') {
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'success',
                                    title: @json(__('Top-Up Berhasil!')),
                                    text: @json(__('Saldo dompet Anda telah bertambah secara otomatis.')),
                                    timer: 1800,
                                    showConfirmButton: false
                                }).then(() => window.location.reload());
                            } else {
                                window.location.reload();
                            }
                        } else if (data.status !== 'pending') {
                            window.location.reload();
                        }
                    })
                    .catch(() => {});
            });
        };
        setInterval(pollPending, 5000);
    }

    // Auto-open topup modal on smartphone if error occurred
    @if($errors->has('amount'))
    if (window.innerWidth < 768) {
        const modalEl = document.getElementById('topupModal');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }
    @endif
});
</script>
@endpush
@endsection
