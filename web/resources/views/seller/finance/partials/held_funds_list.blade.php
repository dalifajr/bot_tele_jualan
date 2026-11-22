@if($heldFunds->count() > 0)
    <div class="d-flex justify-content-between align-items-center mb-2.5 small text-muted">
        <span>{{ __('Menampilkan') }} <strong class="text-body">{{ $heldFunds->firstItem() }}–{{ $heldFunds->lastItem() }}</strong> {{ __('dari') }} <strong class="text-body">{{ $heldFunds->total() }}</strong></span>
        @if($heldFunds->lastPage() > 1)
            <span class="badge bg-secondary-subtle rounded-pill px-2.5 py-1 fw-semibold m3-tabular-nums">
                {{ __('Hal.') }} {{ $heldFunds->currentPage() }}/{{ $heldFunds->lastPage() }}
            </span>
        @endif
    </div>

    <div class="d-flex flex-column gap-2.5">
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
        <div class="mt-3 pt-2.5 border-top m3-held-funds-pagination">
            {{ $heldFunds->appends(request()->except(['held_page', 'partial']))->links() }}
        </div>
    @endif
@else
    <div class="text-center py-4 px-2">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--on-surface-variant); font-size: 1.4rem;">
            <i class="fas fa-shield-halved"></i>
        </span>
        <p class="fw-semibold text-body mb-1 small">{{ __('Belum ada saldo tertahan aktif.') }}</p>
        <p class="text-muted small mb-0">{{ __('Komisi dari produk bergaransi yang sedang ditahan akan tampil di sini.') }}</p>
    </div>
@endif
