@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page_subtitle', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Admin Dashboard</h4>
        <p class="text-muted mb-0">Ringkasan aktivitas toko Anda</p>
    </div>
    <div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-2"></i>Kelola Produk
        </a>
    </div>
</div>

{{-- Stat Cards --}}
<div class="row g-4 mb-5">
    <div class="col-md-3">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="stat-icon bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 1.5rem;">
                    <i class="fas fa-wallet"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 small">Total Pendapatan</h6>
                    <h4 class="fw-bold mb-0">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="stat-icon bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 1.5rem;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 small">Total Order</h6>
                    <h4 class="fw-bold mb-0">{{ number_format($totalOrders, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="stat-icon bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 1.5rem;">
                    <i class="fas fa-box"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 small">Total Produk</h6>
                    <h4 class="fw-bold mb-0">{{ number_format($totalProducts, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-0 shadow-sm h-100" style="border-radius: 16px;">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="stat-icon bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 1.5rem;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="text-muted mb-1 small">Total User</h6>
                    <h4 class="fw-bold mb-0">{{ number_format($totalUsers, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Recent Orders --}}
<div class="card border-0 shadow-sm" style="border-radius: 16px;">
    <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0">Pesanan Terbaru</h5>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Lihat Semua</a>
    </div>
    <div class="card-body p-0">
        @if($recentOrders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">No. Order</th>
                        <th class="py-3 border-0">Pelanggan</th>
                        <th class="py-3 border-0">Produk</th>
                        <th class="py-3 border-0">Total</th>
                        <th class="py-3 border-0">Status</th>
                        <th class="py-3 border-0">Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentOrders as $order)
                    <tr>
                        <td class="px-4 fw-bold text-primary">{{ $order->reference }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold">{{ $order->user->full_name ?? $order->user->username ?? 'User' }}</span>
                                <span class="small text-muted">{{ $order->user->telegram_id }}</span>
                            </div>
                        </td>
                        <td>{{ Str::limit($order->product->name ?? '-', 25) }}</td>
                        <td class="fw-bold">{{ $order->formatted_total }}</td>
                        <td>
                            <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3">
                                {{ $order->status_label }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $order->created_at->format('d M Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-receipt text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Belum ada pesanan.</p>
        </div>
        @endif
    </div>
</div>
@endsection
