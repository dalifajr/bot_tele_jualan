@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_subtitle', 'Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard-academic.css') }}">
@endpush

@section('content')
<div class="main-content position-relative" data-pex="v791liz-0">
    <div class="main-background" data-pex="v791liz-1"></div>
    <div class="container-fluid position-relative px-4 py-4" style="z-index: 1;" data-pex="v791liz-2">
        <!-- Hero Section -->
        <div class="position-relative mb-5" data-pex="v791liz-3">
            <!-- Background Banner -->
            <div class="rounded-4 p-5 text-white shadow-sm overflow-hidden position-relative" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%); min-height: 220px;" data-pex="v791liz-4">
                <!-- Abstract Pattern -->
                <div style="position: absolute; top: 0; right: 0; bottom: 0; left: 0; opacity: 0.1; background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;" data-pex="v791liz-5"></div>
                <div style="position: absolute; bottom: -50px; left: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.1); border-radius: 50%;" data-pex="v791liz-6"></div>
                
                <div class="position-relative z-1" data-pex="v791liz-7">
                    <h1 class="fw-bold mb-2 text-white" data-pex="v791liz-8">Selamat Datang, {{ Auth::user()->full_name ?? Auth::user()->username }}</h1>
                    <p class="mb-0 fs-5 opacity-75 fw-light text-white" data-pex="v791liz-9">Role: {{ ucfirst(Auth::user()->role) }} | Email/ID Telegram: {{ Auth::user()->email ?? Auth::user()->telegram_id ?? '-' }}</p>
                </div>
            </div>

            <!-- Floating Stats Cards -->
            <div class="container-fluid px-4" style="margin-top: -60px;" data-pex="v791liz-10">
                <div class="row g-4" data-pex="v791liz-11">
                    <!-- Card 1 -->
                    <div class="col-md-4" data-pex="v791liz-12">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden lift-hover" data-pex="v791liz-13">
                            <div class="card-body p-4 position-relative" data-pex="v791liz-14">
                                <div class="d-flex justify-content-between align-items-start mb-2" data-pex="v791liz-15">
                                    <div data-pex="v791liz-16">
                                        <h2 class="display-5 fw-bold text-dark mb-0" data-pex="v791liz-17">{{ $totalOrders ?? 0 }}</h2>
                                        <div class="text-muted small text-uppercase fw-bold" data-pex="v791liz-18">Total Pesanan</div>
                                    </div>
                                    <div class="bg-primary-subtle rounded-3 p-3 text-primary" data-pex="v791liz-19">
                                        <i class="fas fa-shopping-cart fa-2x" data-pex="v791liz-20"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="col-md-4" data-pex="v791liz-21">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden lift-hover" data-pex="v791liz-22">
                            <div class="card-body p-4 position-relative" data-pex="v791liz-23">
                                <div class="d-flex justify-content-between align-items-start mb-2" data-pex="v791liz-24">
                                    <div data-pex="v791liz-25">
                                        <h2 class="display-5 fw-bold text-dark mb-0" data-pex="v791liz-26">{{ $completedOrders ?? 0 }}</h2>
                                        <div class="text-muted small text-uppercase fw-bold" data-pex="v791liz-27">Pesanan Selesai</div>
                                    </div>
                                    <div class="bg-warning-subtle rounded-3 p-3 text-warning-emphasis" data-pex="v791liz-28">
                                        <i class="fas fa-check-circle fa-2x" data-pex="v791liz-29"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3 -->
                    <div class="col-md-4" data-pex="v791liz-30">
                        <div class="card border-0 shadow-sm h-100 overflow-hidden lift-hover" data-pex="v791liz-31">
                            <div class="card-body p-4 position-relative" data-pex="v791liz-32">
                                <div class="d-flex justify-content-between align-items-start mb-2" data-pex="v791liz-33">
                                    <div data-pex="v791liz-34" class="overflow-hidden pe-2" style="max-width: calc(100% - 60px);">
                                        <h2 class="fw-bold text-dark mb-0 text-truncate" data-pex="v791liz-35" style="font-size: 1.5rem;" title="Rp {{ number_format($totalSpent ?? 0, 0, ',', '.') }}">Rp {{ number_format($totalSpent ?? 0, 0, ',', '.') }}</h2>
                                        <div class="text-muted small text-uppercase fw-bold" data-pex="v791liz-36">Total Belanja</div>
                                    </div>
                                    <div class="bg-success-subtle rounded-3 p-3 text-success flex-shrink-0" data-pex="v791liz-37">
                                        <i class="fas fa-wallet fa-2x" data-pex="v791liz-38"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5" data-pex="v791liz-39">
            <!-- Akses Cepat Menu -->
            <div class="col-lg-12" data-pex="v791liz-40">
                <h4 class="fw-bold mb-3" data-pex="v791liz-41"><i class="fas fa-th-large text-primary me-2" data-pex="v791liz-42"></i> Menu Akses Cepat</h4>
                <div class="row g-3" data-pex="v791liz-43">
                    <div class="col-6 col-md-3" data-pex="v791liz-44">
                        <a href="{{ route('catalog.index') }}" class="card border-0 shadow-sm text-center text-decoration-none h-100 lift-hover text-dark bg-white" data-pex="v791liz-45">
                            <div class="card-body py-4" data-pex="v791liz-46">
                                <div class="bg-primary-subtle rounded-circle d-inline-flex p-3 mb-3 text-primary" data-pex="v791liz-47">
                                    <i class="fas fa-store fa-2x" data-pex="v791liz-48"></i>
                                </div>
                                <div class="fw-bold" data-pex="v791liz-49">Katalog Produk</div>
                                <div class="small text-muted" data-pex="v791liz-50">Jelajahi Produk</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3" data-pex="v791liz-51">
                        <a href="{{ route('orders.index') }}" class="card border-0 shadow-sm text-center text-decoration-none h-100 lift-hover text-dark bg-white" data-pex="v791liz-52">
                            <div class="card-body py-4" data-pex="v791liz-53">
                                <div class="bg-warning-subtle rounded-circle d-inline-flex p-3 mb-3 text-warning-emphasis" data-pex="v791liz-54">
                                    <i class="fas fa-receipt fa-2x" data-pex="v791liz-55"></i>
                                </div>
                                <div class="fw-bold" data-pex="v791liz-56">Riwayat Pesanan</div>
                                <div class="small text-muted" data-pex="v791liz-57">Status Pesanan</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3" data-pex="v791liz-58">
                        <a href="{{ route('customer.complaints.index') }}" class="card border-0 shadow-sm text-center text-decoration-none h-100 lift-hover text-dark bg-white" data-pex="v791liz-59">
                            <div class="card-body py-4" data-pex="v791liz-60">
                                <div class="bg-success-subtle rounded-circle d-inline-flex p-3 mb-3 text-success" data-pex="v791liz-61">
                                    <i class="fas fa-exclamation-triangle fa-2x" data-pex="v791liz-62"></i>
                                </div>
                                <div class="fw-bold" data-pex="v791liz-63">Komplain</div>
                                <div class="small text-muted" data-pex="v791liz-64">Pusat Bantuan</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-md-3" data-pex="v791liz-65">
                        <a href="{{ config('telegram.bot_username') ? 'https://t.me/' . config('telegram.bot_username') : '#' }}" target="_blank" class="card border-0 shadow-sm text-center text-decoration-none h-100 lift-hover text-dark bg-white" data-pex="v791liz-66">
                            <div class="card-body py-4" data-pex="v791liz-67">
                                <div class="bg-info-subtle rounded-circle d-inline-flex p-3 mb-3 text-info-emphasis" data-pex="v791liz-68">
                                    <i class="fab fa-telegram fa-2x" data-pex="v791liz-69"></i>
                                </div>
                                <div class="fw-bold" data-pex="v791liz-70">Chat Bot</div>
                                <div class="small text-muted" data-pex="v791liz-71">Telegram Akses</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>



        <!-- Jadwal Kuliah Hari Ini -> Pesanan Terbaru -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold"><i class="fas fa-history text-primary me-2"></i>Pesanan Terbaru</h5>
                        <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Lihat Semua</a>
                    </div>
                    <div class="card-body p-4">
                        @if(isset($recentOrders) && count($recentOrders) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr class="text-secondary small">
                                            <th class="border-0">No. Order</th>
                                            <th class="border-0">Produk</th>
                                            <th class="border-0">Total</th>
                                            <th class="border-0">Status</th>
                                            <th class="border-0">Tanggal</th>
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
                                                        'pending_payment' => ['Menunggu', 'warning'],
                                                        'paid' => ['Dibayar', 'info'],
                                                        'delivered' => ['Selesai', 'success'],
                                                        'cancelled' => ['Dibatalkan', 'danger'],
                                                        'expired' => ['Kedaluwarsa', 'secondary'],
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
                            <div class="text-center py-4">
                                <p class="text-muted mb-0">Belum ada pesanan terbaru.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

