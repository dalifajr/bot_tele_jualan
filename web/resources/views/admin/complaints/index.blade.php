@extends('layouts.app')

@section('title', 'Kelola Komplain')
@section('page_subtitle', 'Komplain')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Kelola Komplain') }}</h4>
        <p class="text-muted mb-0">{{ __('Daftar keluhan dari pelanggan') }}</p>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($complaints->count() > 0)
        <div class="table-responsive">
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
                        <td class="px-4 fw-bold text-muted">{{ $complaint->complaint_ref }}</td>
                        <td class="fw-bold text-primary">{{ $complaint->customer->full_name ?? $complaint->customer_username_snapshot ?? 'Unknown' }}</td>
                        <td>{{ $complaint->order_ref_snapshot }}</td>
                        <td>{{ Str::limit($complaint->complaint_text, 30) }}</td>
                        <td>
                            @if($complaint->{{ __('status == \'new\')') }}
                                <span class="badge bg-danger-subtle text-danger rounded-pill px-3">{{ __('Baru') }}</span>
                            @elseif($complaint->{{ __('status == \'review\')') }}
                                <span class="badge bg-warning-subtle text-warning rounded-pill px-3">{{ __('Ditinjau') }}</span>
                            @elseif($complaint->{{ __('status == \'done\')') }}
                                <span class="badge bg-success-subtle text-success rounded-pill px-3">{{ __('Selesai') }}</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3">{{ ucfirst($complaint->status) }}</span>
                            @endif
                        </td>
                        <td class="text-secondary small">{{ $complaint->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end px-4">
                            <a href="{{ route('admin.complaints.show', $complaint->id) }}" class="btn btn-sm btn-light text-primary rounded-circle" title="{{ __('Lihat Detail & Resolusi') }}">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $complaints->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-toolbox text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">{{ __('Belum ada komplain saat ini.') }}</p>
        </div>
        @endif
    </div>
</div>
@endsection
