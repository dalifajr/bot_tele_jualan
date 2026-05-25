@extends('layouts.app')

@section('title', 'Laporan Operasional')
@section('page_subtitle', 'Laporan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Laporan Operasional</h4>
        <p class="text-muted mb-0">Ringkasan aktivitas dan pendapatan</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius: 16px; background: linear-gradient(135deg, #primary, #blue);">
            <div class="card-body p-4 text-center">
                <i class="fas fa-wallet fs-1 text-primary mb-3"></i>
                <h6 class="fw-bold text-muted mb-1">Total Pendapatan</h6>
                <h3 class="fw-bold mb-0">Rp {{ number_format($totalSales, 0, ',', '.') }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4 text-center">
                <i class="fas fa-shopping-cart fs-1 text-success mb-3"></i>
                <h6 class="fw-bold text-muted mb-1">Pesanan Sukses</h6>
                <h3 class="fw-bold mb-0">{{ number_format($deliveredOrders) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4 text-center">
                <i class="fas fa-times-circle fs-1 text-danger mb-3"></i>
                <h6 class="fw-bold text-muted mb-1">Pesanan Batal</h6>
                <h3 class="fw-bold mb-0">{{ number_format($cancelledOrders) }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4 text-center">
                <i class="fas fa-users fs-1 text-info mb-3"></i>
                <h6 class="fw-bold text-muted mb-1">Total Pelanggan</h6>
                <h3 class="fw-bold mb-0">{{ number_format($totalUsers) }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="fas fa-clock text-primary me-2"></i>5 Pesanan Sukses Terakhir</h6>
    </div>
    <div class="card-body p-0">
        @if($latestOrders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">No Pesanan</th>
                        <th class="py-3 border-0">Pelanggan</th>
                        <th class="py-3 border-0">Total Harga</th>
                        <th class="py-3 border-0">Tanggal Selesai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($latestOrders as $order)
                    <tr>
                        <td class="px-4 fw-bold text-muted">{{ $order->order_ref }}</td>
                        <td class="fw-bold text-primary">{{ $order->customer->full_name ?? 'Unknown' }}</td>
                        <td class="text-success fw-bold">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                        <td class="text-secondary small">{{ $order->delivered_at ? $order->delivered_at->format('d M Y H:i') : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-5">
            <p class="text-muted mb-0">Belum ada pesanan sukses.</p>
        </div>
        @endif
    </div>
</div>
@endsection
