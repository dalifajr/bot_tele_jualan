@extends('layouts.app')

@section('title', __('Kelola Komplain'))
@section('page_subtitle', __('Kelola Komplain'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 mb-md-4">
    <div>
        <h1 class="m3-page-title mb-1 fs-5 fs-md-4 fw-bold text-body">{{ __('Kelola Komplain') }}</h1>
        <p class="text-secondary small mb-0">{{ __('Daftar keluhan dan klaim garansi Anda.') }}</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success rounded-4 small py-2.5 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fas fa-check-circle flex-shrink-0"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger rounded-4 small py-2.5 px-3 mb-3 d-flex align-items-center gap-2">
        <i class="fas fa-exclamation-circle flex-shrink-0"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: var(--radius-lg, 20px);">
    <div class="card-body p-0">
        @if($complaints->count() > 0)
        {{-- Desktop Table View --}}
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">{{ __('No. Komplain') }}</th>
                        <th class="py-3 border-0">{{ __('No. Pesanan') }}</th>
                        <th class="py-3 border-0">{{ __('Produk') }}</th>
                        <th class="py-3 border-0">{{ __('Status') }}</th>
                        <th class="py-3 border-0">{{ __('Tanggal') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($complaints as $complaint)
                    <tr>
                        <td class="px-4 fw-bold text-primary font-monospace small">{{ $complaint->complaint_ref }}</td>
                        <td class="font-monospace small">{{ $complaint->order_ref_snapshot }}</td>
                        <td class="small text-body">{{ Str::limit($complaint->order?->items?->first()?->product?->name ?? __('Produk'), 30) }}</td>
                        <td>
                            @php
                                $statusBadge = match($complaint->status) {
                                    'new' => ['bg' => 'danger-subtle', 'text' => 'danger', 'label' => __('Baru')],
                                    'review' => ['bg' => 'warning-subtle', 'text' => 'warning', 'label' => __('Ditinjau')],
                                    'done' => ['bg' => 'success-subtle', 'text' => 'success', 'label' => __('Selesai')],
                                    'rejected' => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => __('Ditolak')],
                                    'refund_requested' => ['bg' => 'info-subtle', 'text' => 'info', 'label' => __('Proses Refund')],
                                    default => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => $complaint->status]
                                };
                            @endphp
                            <span class="badge bg-{{ $statusBadge['bg'] }} text-{{ $statusBadge['text'] }} rounded-pill px-3 py-1 fw-semibold">
                                {{ $statusBadge['label'] }}
                            </span>
                        </td>
                        <td class="text-secondary small text-nowrap">{{ $complaint->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end px-4">
                            <a href="{{ route('customer.complaints.show', $complaint->id) }}" class="btn btn-sm btn-tonal rounded-pill px-3 fw-semibold" title="{{ __('Lihat Detail') }}">
                                {{ __('Detail') }} <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile Stacked Card List --}}
        <div class="d-md-none p-3 d-flex flex-column gap-2.5">
            @foreach($complaints as $complaint)
            @php
                $statusBadge = match($complaint->status) {
                    'new' => ['bg' => 'danger-subtle', 'text' => 'danger', 'label' => __('Baru')],
                    'review' => ['bg' => 'warning-subtle', 'text' => 'warning', 'label' => __('Ditinjau')],
                    'done' => ['bg' => 'success-subtle', 'text' => 'success', 'label' => __('Selesai')],
                    'rejected' => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => __('Ditolak')],
                    'refund_requested' => ['bg' => 'info-subtle', 'text' => 'info', 'label' => __('Proses Refund')],
                    default => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => $complaint->status]
                };
            @endphp
            <div class="m3-mobile-item-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="min-w-0">
                        <div class="fw-bold text-primary font-monospace small">{{ $complaint->complaint_ref }}</div>
                        <div class="small text-muted font-monospace">{{ $complaint->order_ref_snapshot }}</div>
                        <div class="fw-semibold text-body small mt-1">{{ $complaint->order?->items?->first()?->product?->name ?? __('Produk') }}</div>
                    </div>
                    <span class="badge bg-{{ $statusBadge['bg'] }} text-{{ $statusBadge['text'] }} rounded-pill px-2.5 py-1 fw-semibold flex-shrink-0">
                        {{ $statusBadge['label'] }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 mt-2" style="border-top: 1px solid var(--outline-variant);">
                    <small class="text-muted"><i class="far fa-clock me-1"></i>{{ $complaint->created_at->format('d M Y, H:i') }}</small>
                    <a href="{{ route('customer.complaints.show', $complaint->id) }}" class="btn btn-sm btn-tonal rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1" style="min-height: 38px;">
                        <span>{{ __('Lihat Detail') }}</span>
                        <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        <div class="px-3 px-sm-4 py-3 border-top">
            {{ $complaints->withQueryString()->links() }}
        </div>
        @else
        <div class="text-center py-5 px-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--on-surface-variant); font-size: 1.4rem;">
                <i class="fas fa-toolbox"></i>
            </span>
            <p class="fw-semibold text-body mb-1 small">{{ __('Belum ada komplain atau klaim garansi.') }}</p>
            <p class="text-muted small mb-0">{{ __('Riwayat pengajuan komplain Anda akan tampil di sini.') }}</p>
        </div>
        @endif
    </div>
</div>
@endsection
