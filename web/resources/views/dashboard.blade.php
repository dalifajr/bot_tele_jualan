@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_subtitle', 'Dashboard')
@section('meta_description', 'Dashboard akun Anda — lihat ringkasan pesanan dan aktivitas terbaru')

@section('content')
<style>
.lift-hover { transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s ease; }
.lift-hover:hover { transform: translateY(-8px); box-shadow: 0 1rem 3rem rgba(0,0,0,.1) !important; z-index: 10; }
.transition-hover { transition: all 0.2s ease; }
.transition-hover:hover { background-color: rgba(255,255,255,0.2) !important; transform: translateX(5px); }
</style>

<div class="position-relative mb-5">
    <!-- Background Banner -->
    <div class="rounded-4 p-4 p-md-5 text-white shadow-sm overflow-hidden position-relative" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); min-height: 220px;">
        <!-- Abstract Pattern -->
        <div style="position: absolute; top: 0; right: 0; bottom: 0; left: 0; opacity: 0.1; background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;"></div>
        <div style="position: absolute; top: -50px; right: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        
        <div class="position-relative z-1 d-flex justify-content-between align-items-start">
            <div>
                <h1 class="fw-bold mb-2 text-white fs-2">Selamat Datang, {{ strtoupper(Str::limit(Auth::user()->full_name ?? Auth::user()->username ?? 'User', 25)) }}</h1>
                <p class="mb-0 fs-5 opacity-75 fw-light text-white">
                    Pelanggan | ID: {{ Auth::user()->id }}
                    <br />
                    <span class="fs-6 opacity-75">-</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Floating Stats Cards -->
    <div class="px-2 px-md-4" style="margin-top: -60px; position: relative; z-index: 2;">
        <div class="row g-4">
            <!-- Card 1 -->
            <div class="col-md-6 col-xl-3">
                <a href="{{ route('orders.index') }}" class="card border-0 shadow-sm h-100 text-decoration-none lift-hover overflow-hidden" style="border-radius: 16px;">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="bg-primary-subtle rounded-3 p-3 text-primary">
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                            <span class="badge bg-primary rounded-pill px-3 py-2">{{ $totalOrders ?? 0 }}</span>
                        </div>
                        <h3 class="h5 fw-bold text-body mb-1">Total Pesanan</h3>
                        <p class="text-muted small mb-0">Semua riwayat pesanan</p>
                    </div>
                </a>
            </div>
            <!-- Card 2 -->
            <div class="col-md-6 col-xl-3">
                <a href="{{ route('orders.index') }}" class="card border-0 shadow-sm h-100 text-decoration-none lift-hover overflow-hidden" style="border-radius: 16px;">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="bg-success-subtle rounded-3 p-3 text-success">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                            <span class="badge bg-success rounded-pill px-3 py-2">{{ $completedOrders ?? 0 }}</span>
                        </div>
                        <h3 class="h5 fw-bold text-body mb-1">Selesai</h3>
                        <p class="text-muted small mb-0">Pesanan telah dikirim</p>
                    </div>
                </a>
            </div>
            <!-- Card 3 -->
            <div class="col-md-6 col-xl-3">
                <a href="{{ route('orders.index') }}" class="card border-0 shadow-sm h-100 text-decoration-none lift-hover overflow-hidden" style="border-radius: 16px;">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="bg-warning-subtle rounded-3 p-3 text-warning-emphasis">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2">{{ $pendingOrders ?? 0 }}</span>
                        </div>
                        <h3 class="h5 fw-bold text-body mb-1">Menunggu</h3>
                        <p class="text-muted small mb-0">Pesanan belum selesai</p>
                    </div>
                </a>
            </div>
            <!-- Card 4 -->
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 overflow-hidden" style="border-radius: 16px;">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="bg-info-subtle rounded-3 p-3 text-info-emphasis">
                                <i class="fas fa-wallet fa-2x"></i>
                            </div>
                        </div>
                        <h3 class="h5 fw-bold text-body mb-1">Total Belanja</h3>
                        <div class="mt-2">
                            <h4 class="fw-bold text-info-emphasis mb-0">Rp {{ number_format($totalSpent ?? 0, 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Orders Section -->
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="fw-bold m-0">
                <i class="fas fa-history text-primary me-2"></i>
                Pesanan Terbaru
            </h4>
            <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Lihat Semua</a>
        </div>
        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
            <div class="card-body p-0">
                @if(isset($recentOrders) && count($recentOrders) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="text-secondary small">
                                    <th class="border-0 px-4 py-3">No. Order</th>
                                    <th class="border-0 py-3">Produk</th>
                                    <th class="border-0 py-3">Total</th>
                                    <th class="border-0 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentOrders as $order)
                                <tr>
                                    <td class="fw-bold text-primary px-4 py-3">{{ $order->reference }}</td>
                                    <td class="py-3">{{ Str::limit($order->product->name ?? '-', 25) }}</td>
                                    <td class="py-3">Rp {{ number_format($order->total_price ?? 0, 0, ',', '.') }}</td>
                                    <td class="py-3">
                                        @php
                                            $statusMap = [
                                                'pending_payment' => ['Menunggu', 'warning'],
                                                'paid' => ['Dibayar', 'info'],
                                                'delivered' => ['Selesai', 'success'],
                                                'cancelled' => ['Dibatalkan', 'danger'],
                                                'expired' => ['Kedaluwarsa', 'secondary'],
                                            ];
                                            [$label, $color] = $statusMap[$order->status] ?? [$order->status, 'secondary'];
                                        @endphp
                                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} rounded-pill px-3 py-2">{{ $label }}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-box-open text-muted mb-3" style="font-size: 3rem;"></i>
                        <p class="text-muted mb-3">Belum ada pesanan terbaru</p>
                        <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-store me-2"></i>Mulai Belanja
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Sidebar Widget -->
    <div class="col-lg-4 mt-4 mt-lg-0">
        <div class="card border-0 shadow-sm text-white bg-primary overflow-hidden" style="border-radius: 16px;">
            <div class="card-body p-4 position-relative">
                <div style="position: absolute; right: -20px; bottom: -20px; font-size: 8rem; opacity: 0.1;">
                    <i class="fas fa-bolt"></i>
                </div>
                <h5 class="fw-bold mb-4 text-white">Akses Cepat</h5>
                
                <a href="{{ route('catalog.index') }}" class="text-decoration-none text-white d-block">
                    <div class="d-flex justify-content-between align-items-center mb-3 p-3 rounded bg-white bg-opacity-10 transition-hover">
                        <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fas fa-store"></i>
                            </div>
                            <span class="fw-bold">Katalog Produk</span>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
                
                <a href="{{ route('orders.index') }}" class="text-decoration-none text-white d-block">
                    <div class="d-flex justify-content-between align-items-center mb-3 p-3 rounded bg-white bg-opacity-10 transition-hover">
                        <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <span class="fw-bold">Riwayat Pesanan</span>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
                
                @if(config('telegram.bot_username'))
                <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank" class="text-decoration-none text-white d-block">
                    <div class="d-flex justify-content-between align-items-center p-3 rounded bg-white bg-opacity-10 transition-hover">
                        <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <i class="fab fa-telegram"></i>
                            </div>
                            <span class="fw-bold">Chat via Telegram</span>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
