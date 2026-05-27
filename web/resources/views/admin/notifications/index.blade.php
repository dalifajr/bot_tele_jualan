@extends('layouts.app')

@section('title', 'Semua Notifikasi Sistem')
@section('page_subtitle', 'Notifikasi')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Pusat Notifikasi</h4>
        <p class="text-muted mb-0">Daftar semua notifikasi sistem yang memerlukan tindakan Anda.</p>
    </div>
</div>

<div class="row">
    {{-- Akun Siap Diverifikasi --}}
    @if($readyToVerify->count() > 0)
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-white border-0 pt-4 pb-2">
                <h5 class="fw-bold text-primary mb-0"><i class="fas fa-clipboard-check me-2"></i>Akun Siap Diverifikasi ({{ $readyToVerify->count() }})</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    @foreach($readyToVerify as $stock)
                    <a href="{{ route('admin.stock.index', ['status' => 'saved_for_verification', 'search' => $stock->id]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 border-0 rounded mb-2 bg-light">
                        <div>
                            <h6 class="mb-1 fw-bold">Stock ID #{{ $stock->id }} - {{ $stock->product->name ?? 'Unknown' }}</h6>
                            <small class="text-muted">Siap sejak {{ \Carbon\Carbon::parse($stock->available_at ?? $stock->created_at->addHours(\App\Models\BotSetting::where('key', 'github_pack.save_hours')->value('value') ?? 80))->diffForHumans() }}</small>
                        </div>
                        <span class="btn btn-sm btn-primary rounded-pill px-3">Verifikasi</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Pesanan Pending --}}
    @if($pendingOrders->count() > 0)
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-white border-0 pt-4 pb-2">
                <h5 class="fw-bold text-warning mb-0"><i class="fas fa-shopping-cart me-2"></i>Pesanan Pending ({{ $pendingOrders->count() }})</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    @foreach($pendingOrders as $order)
                    <a href="{{ route('admin.orders.index', ['search' => $order->order_ref]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 border-0 rounded mb-2 bg-light">
                        <div>
                            <h6 class="mb-1 fw-bold">{{ $order->order_ref }} - Rp {{ number_format($order->total_price, 0, ',', '.') }}</h6>
                            <small class="text-muted">Dari {{ $order->customer->full_name ?? $order->customer->username ?? 'Unknown' }} &bull; {{ $order->created_at->diffForHumans() }}</small>
                        </div>
                        <span class="btn btn-sm btn-warning rounded-pill px-3 text-dark">Proses</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Percobaan Login --}}
    @if($pendingLogins->count() > 0)
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-white border-0 pt-4 pb-2">
                <h5 class="fw-bold text-info mb-0"><i class="fas fa-sign-in-alt me-2"></i>Percobaan Login Web ({{ $pendingLogins->count() }})</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    @foreach($pendingLogins as $login)
                    <a href="{{ route('admin.logins.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 border-0 rounded mb-2 bg-light">
                        <div>
                            <h6 class="mb-1 fw-bold">Pengguna ID: {{ $login->telegram_id }}</h6>
                            <small class="text-muted">IP: {{ $login->ip_address }} &bull; {{ $login->created_at->diffForHumans() }}</small>
                        </div>
                        <span class="btn btn-sm btn-info rounded-pill px-3 text-white">Konfirmasi</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($readyToVerify->isEmpty() && $pendingOrders->isEmpty() && $pendingLogins->isEmpty())
    <div class="col-12 text-center py-5">
        <i class="fas fa-check-circle text-success mb-3" style="font-size: 4rem;"></i>
        <h4 class="fw-bold">Semua Bersih!</h4>
        <p class="text-muted">Tidak ada notifikasi sistem baru yang memerlukan tindakan saat ini.</p>
    </div>
    @endif
</div>
@endsection
