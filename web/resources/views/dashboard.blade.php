@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_subtitle', 'Dashboard')
@section('meta_description', 'Dashboard akun Anda — lihat ringkasan pesanan dan aktivitas terbaru')

@section('content')
<div class="row g-4 mb-4">
    {{-- Welcome Banner --}}
    <div class="col-12">
        <div class="card border-0 text-white overflow-hidden" style="background: linear-gradient(135deg, #0d47a1 0%, #1976d2 50%, #42a5f5 100%); border-radius: 20px;">
            <div class="card-body p-4 p-lg-5 position-relative">
                <div class="position-absolute top-0 end-0 opacity-25" style="font-size: 10rem; margin-top: -30px; margin-right: -10px;">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h2 class="fw-bold mb-2">{{ __('Selamat datang,') }} {{ Str::limit(Auth::user()->full_name ?? Auth::user()->username ?? 'User', 25) }}! 👋</h2>
                <p class="mb-3 opacity-75">{{ __('Berikut ringkasan aktivitas akun Anda.') }}</p>
                <a href="{{ route('catalog.index') }}" class="btn btn-light text-primary fw-bold rounded-pill px-4">
                    <i class="fas fa-store me-2"></i>{{ __('Lihat Katalog') }}
                </a>
            </div>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(13, 71, 161, 0.1); color: #0d47a1;">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-label">{{ __('Total Pesanan') }}</div>
            <div class="stat-value">{{ $totalOrders ?? 0 }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(46, 125, 50, 0.1); color: #2e7d32;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-label">{{ __('Selesai') }}</div>
            <div class="stat-value">{{ $completedOrders ?? 0 }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(255, 152, 0, 0.1); color: #f57c00;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-label">{{ __('Menunggu') }}</div>
            <div class="stat-value">{{ $pendingOrders ?? 0 }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(156, 39, 176, 0.1); color: #9c27b0;">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-label">{{ __('Total Belanja') }}</div>
            <div class="stat-value">Rp {{ number_format($totalSpent ?? 0, 0, ',', '.') }}</div>
        </div>
    </div>
</div>

{{-- Recent Orders --}}
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>{{ __('Pesanan Terbaru') }}</h5>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">{{ __('Lihat Semua') }}</a>
            </div>
            <div class="card-body px-4 pb-4">
                @if(isset($recentOrders) && count($recentOrders) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="text-secondary small">
                                    <th class="border-0">{{ __('No. Order') }}</th>
                                    <th class="border-0">{{ __('Produk') }}</th>
                                    <th class="border-0">{{ __('Total') }}</th>
                                    <th class="border-0">{{ __('Status') }}</th>
                                    <th class="border-0">{{ __('Tanggal') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentOrders as $order)
                                <tr>
                                    <td class="fw-bold text-primary">{{ $order->reference }}</td>
                                    <td>{{ Str::limit($order->product->name ?? '-', 25) }}</td>
                                    <td>Rp {{ number_format($order->total_price ?? 0, 0, ',', '.') }}</td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                'pending_payment' => [__('Menunggu'), 'warning'],
                                                'paid' => [__('Dibayar'), 'info'],
                                                'delivered' => [__('Selesai'), 'success'],
                                                'cancelled' => [__('Dibatalkan'), 'danger'],
                                                'expired' => [__('Kedaluwarsa'), 'secondary'],
                                            ];
                                            [$label, $color] = $statusMap[$order->status] ?? [$order->status, 'secondary'];
                                        @endphp
                                        <span class="badge bg-{{ $color }}-subtle text-{{ $color }} rounded-pill px-3">{{ $label }}</span>
                                    </td>
                                    <td class="text-secondary small">{{ $order->created_at->format('d M Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-box-open text-muted mb-3" style="font-size: 3rem;"></i>
                        <p class="text-muted mb-3">{{ __('Belum ada pesanan') }}</p>
                        <a href="{{ route('catalog.index') }}" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-store me-2"></i>{{ __('Mulai Belanja') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-bolt text-warning me-2"></i>{{ __('Akses Cepat') }}</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <a href="{{ route('catalog.index') }}" class="quick-action-btn mb-3">
                    <div class="qa-icon" style="background: #e3f2fd;"><i class="fas fa-store text-primary"></i></div>
                    <div>
                        <div class="fw-bold">{{ __('Katalog Produk') }}</div>
                        <small class="text-muted">{{ __('Lihat produk yang tersedia') }}</small>
                    </div>
                    <i class="fas fa-chevron-right text-muted ms-auto"></i>
                </a>
                <a href="{{ route('orders.index') }}" class="quick-action-btn mb-3">
                    <div class="qa-icon" style="background: #e8f5e9;"><i class="fas fa-receipt text-success"></i></div>
                    <div>
                        <div class="fw-bold">{{ __('Riwayat Pesanan') }}</div>
                        <small class="text-muted">{{ __('Lihat pesanan sebelumnya') }}</small>
                    </div>
                    <i class="fas fa-chevron-right text-muted ms-auto"></i>
                </a>
                @if(config('telegram.bot_username'))
                <a href="https://t.me/{{ config('telegram.bot_username') }}" target="_blank" class="quick-action-btn">
                    <div class="qa-icon" style="background: #e1f5fe;"><i class="fab fa-telegram text-info"></i></div>
                    <div>
                        <div class="fw-bold">{{ __('Chat via Telegram') }}</div>
                        <small class="text-muted">{{ __('Beli langsung dari bot') }}</small>
                    </div>
                    <i class="fas fa-chevron-right text-muted ms-auto"></i>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
