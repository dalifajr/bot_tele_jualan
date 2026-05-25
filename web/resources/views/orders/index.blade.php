@extends('layouts.app')

@section('title', 'Riwayat Pesanan')
@section('page_subtitle', 'Pesanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Riwayat Pesanan</h4>
        <p class="text-muted mb-0">Daftar seluruh pesanan Anda</p>
    </div>
</div>

{{-- Status Filter --}}
<div class="mb-4 d-flex flex-wrap gap-2">
    <a href="{{ route('orders.index') }}"
       class="btn btn-sm rounded-pill px-3 {{ is_null($status) ? 'btn-primary' : 'btn-outline-secondary' }}">
        Semua
    </a>
    @foreach(['pending_payment' => 'Menunggu', 'paid' => 'Dibayar', 'delivered' => 'Selesai', 'cancelled' => 'Dibatalkan', 'expired' => 'Kedaluwarsa'] as $key => $label)
    <a href="{{ route('orders.index', ['status' => $key]) }}"
       class="btn btn-sm rounded-pill px-3 {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($orders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">No. Order</th>
                        <th class="py-3 border-0">Produk</th>
                        <th class="py-3 border-0">Qty</th>
                        <th class="py-3 border-0">Total</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">Tanggal</th>
                        <th class="py-3 border-0"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    <tr>
                        <td class="px-4 fw-bold text-primary">{{ $order->reference }}</td>
                        <td>{{ Str::limit($order->product->name ?? '-', 25) }}</td>
                        <td>{{ $order->quantity }}</td>
                        <td class="fw-bold">{{ $order->formatted_total }}</td>
                        <td>
                            <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3">
                                {{ $order->status_label }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $order->created_at->format('d M Y H:i') }}</td>
                        <td>
                            <a href="{{ route('orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                Detail
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-4 py-3">
            {{ $orders->withQueryString()->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-box-open text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-3">
                @if($status)
                    Tidak ada pesanan dengan status "{{ $status }}".
                @else
                    Belum ada pesanan.
                @endif
            </p>
            <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-store me-2"></i>Mulai Belanja
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
