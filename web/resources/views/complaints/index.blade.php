@extends('layouts.app')

@section('title', 'Kelola Komplain')
@section('page_subtitle', 'Kelola Komplain')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Kelola Komplain') }}</h4>
        <p class="text-muted mb-0">{{ __('Daftar keluhan dan klaim garansi Anda') }}</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($complaints->count() > 0)
        <div class="table-responsive">
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
                        <td class="px-4 fw-bold text-primary">{{ $complaint->complaint_ref }}</td>
                        <td>{{ $complaint->order_ref_snapshot }}</td>
                        <td>{{ Str::limit($complaint->order->items->first()->product->name ?? 'Produk', 30) }}</td>
                        <td>
                            @php
                                $statusBadge = match($complaint->status) {
                                    'new' => ['bg' => 'danger-subtle', 'text' => 'danger', 'label' => 'Baru'],
                                    'review' => ['bg' => 'warning-subtle', 'text' => 'warning', 'label' => 'Ditinjau'],
                                    'done' => ['bg' => 'success-subtle', 'text' => 'success', 'label' => 'Selesai'],
                                    'rejected' => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => 'Ditolak'],
                                    'refund_requested' => ['bg' => 'info-subtle', 'text' => 'info', 'label' => 'Proses Refund'],
                                    default => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => $complaint->status]
                                };
                            @endphp
                            <span class="badge bg-{{ $statusBadge['bg'] }} text-{{ $statusBadge['text'] }} rounded-pill px-3 py-1">
                                {{ $statusBadge['label'] }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $complaint->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end px-4">
                            <a href="{{ route('customer.complaints.show', $complaint->id) }}" class="btn btn-sm btn-light text-info rounded-pill px-3" title="{{ __('Lihat Detail') }}">
                                {{ __('Detail') }} <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-top">
            {{ $complaints->withQueryString()->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-toolbox text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">{{ __('Anda belum pernah mengajukan komplain atau klaim garansi.') }}</p>
        </div>
        @endif
    </div>
</div>
@endsection
