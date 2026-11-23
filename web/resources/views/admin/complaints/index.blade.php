@extends('layouts.app')

@section('title', __('Kelola Komplain'))
@section('page_subtitle', __('Komplain'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 mb-md-4">
    <div>
        <h1 class="m3-page-title mb-1 fs-5 fs-md-4 fw-bold text-body">{{ __('Kelola Komplain') }}</h1>
        <p class="text-secondary small mb-0">{{ __('Daftar keluhan dari pelanggan.') }}</p>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: var(--radius-lg, 20px);">
    <div class="card-body p-0">
        @if($complaints->count() > 0)
        {{-- Desktop Table View --}}
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">{{ __('Ref') }}</th>
                        <th class="py-3 border-0">{{ __('Pelanggan') }}</th>
                        <th class="py-3 border-0">{{ __('No Pesanan') }}</th>
                        <th class="py-3 border-0">{{ __('Keluhan') }}</th>
                        <th class="py-3 border-0">{{ __('Status') }}</th>
                        <th class="py-3 border-0">{{ __('Tanggal') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($complaints as $complaint)
                    <tr>
                        <td class="px-4 fw-bold text-muted font-monospace small">{{ $complaint->complaint_ref }}</td>
                        <td class="fw-bold text-primary small">{{ $complaint->customer->full_name ?? $complaint->customer_username_snapshot ?? 'Unknown' }}</td>
                        <td class="font-monospace small">{{ $complaint->order_ref_snapshot }}</td>
                        <td class="small text-body">{{ Str::limit($complaint->complaint_text, 30) }}</td>
                        <td>
                            @if($complaint->status == 'new')
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1 fw-semibold">{{ __('Baru') }}</span>
                            @elseif($complaint->status == 'review')
                                <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-1 fw-semibold">{{ __('Ditinjau') }}</span>
                            @elseif($complaint->status == 'done')
                                <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1 fw-semibold">{{ __('Selesai') }}</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-1 fw-semibold">{{ ucfirst($complaint->status) }}</span>
                            @endif
                        </td>
                        <td class="text-secondary small text-nowrap">{{ $complaint->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end px-4">
                            <a href="{{ route('admin.complaints.show', $complaint->id) }}" class="btn btn-sm btn-tonal rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px;" title="{{ __('Lihat Detail & Resolusi') }}">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile Collapsible Compact List --}}
        <div class="d-md-none p-2.5 m3-collapsible-list" id="adminComplaintsAccordion">
            @foreach($complaints as $complaint)
            <div class="m3-mobile-item-card m3-collapsible-card">
                <div class="m3-compact-row">
                    <button type="button"
                            class="m3-compact-trigger"
                            data-bs-toggle="collapse"
                            data-bs-target="#adminComplaintCollapse-{{ $complaint->id }}"
                            aria-expanded="false"
                            aria-controls="adminComplaintCollapse-{{ $complaint->id }}">
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold text-body text-truncate" style="font-size: 0.84rem;">
                                <span class="font-monospace text-primary">{{ $complaint->complaint_ref }}</span> · {{ $complaint->customer->full_name ?? $complaint->customer_username_snapshot ?? 'Unknown' }}
                            </div>
                            <div class="text-secondary text-truncate" style="font-size: 0.72rem;">
                                <span class="font-monospace">{{ $complaint->order_ref_snapshot }}</span> · {{ $complaint->created_at->format('d M, H:i') }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            @if($complaint->status == 'new')
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-0.5" style="font-size: 0.66rem;">{{ __('Baru') }}</span>
                            @elseif($complaint->status == 'review')
                                <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-0.5" style="font-size: 0.66rem;">{{ __('Ditinjau') }}</span>
                            @elseif($complaint->status == 'done')
                                <span class="badge bg-success-subtle text-success rounded-pill px-2 py-0.5" style="font-size: 0.66rem;">{{ __('Selesai') }}</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5" style="font-size: 0.66rem;">{{ ucfirst($complaint->status) }}</span>
                            @endif
                            <i class="fas fa-chevron-down m3-compact-chevron"></i>
                        </div>
                    </button>
                </div>

                <div class="collapse" id="adminComplaintCollapse-{{ $complaint->id }}" data-bs-parent="#adminComplaintsAccordion">
                    <div class="m3-collapsible-body">
                        <p class="small text-secondary mb-2.5 lh-sm">{{ Str::limit($complaint->complaint_text, 120) }}</p>
                        <a href="{{ route('admin.complaints.show', $complaint->id) }}" class="btn btn-sm btn-tonal m3-compact-action-btn w-100">
                            <span>{{ __('Resolusi') }}</span>
                            <i class="fas fa-arrow-right small"></i>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="px-3 px-sm-4 py-3 border-top">
            {{ $complaints->links() }}
        </div>
        @else
        <div class="text-center py-5 px-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--surface-container); color: var(--on-surface-variant); font-size: 1.4rem;">
                <i class="fas fa-toolbox"></i>
            </span>
            <p class="text-muted mb-0 small">{{ __('Belum ada komplain saat ini.') }}</p>
        </div>
        @endif
    </div>
</div>
@endsection
