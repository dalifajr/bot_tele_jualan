@extends('layouts.app')

@section('title', 'Manajemen Pesanan')
@section('page_subtitle', 'Pesanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Pesanan</h4>
        <p class="text-muted mb-0">Daftar semua transaksi</p>
    </div>
</div>

{{-- Status Filter --}}
<div class="mb-4 d-flex flex-wrap gap-2">
    <a href="{{ route('admin.orders.index') }}"
       class="btn btn-sm rounded-pill px-3 {{ is_null($status) ? 'btn-primary' : 'btn-outline-secondary' }}">
        Semua
    </a>
    @foreach(['pending_payment' => 'Pending', 'paid' => 'Paid', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'expired' => 'Expired'] as $key => $label)
    <a href="{{ route('admin.orders.index', ['status' => $key]) }}"
       class="btn btn-sm rounded-pill px-3 {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($orders->count() > 0)
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
                        <th class="py-3 border-0 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    <tr>
                        <td class="px-4 fw-bold text-primary">{{ $order->reference }}</td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold">{{ $order->user->full_name ?? $order->user->username ?? 'User' }}</span>
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
                        <td class="text-end px-4">
                            <div class="d-flex gap-2 justify-content-end">
                                @if($order->status === 'pending_payment')
                                <form action="{{ route('admin.orders.accept', $order->id) }}" method="POST" class="m-0" onsubmit="return confirm('Konfirmasi terima pembayaran untuk pesanan ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light text-success rounded-circle border-success" title="Terima Pembayaran">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.orders.reject', $order->id) }}" method="POST" class="m-0" onsubmit="return confirm('Tolak dan batalkan pesanan ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle border-danger" title="Tolak Pesanan">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                @endif
                                <button class="btn btn-sm btn-light text-info rounded-circle" data-bs-toggle="modal" data-bs-target="#detailOrderModal{{ $order->id }}" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-light text-primary rounded-circle" data-bs-toggle="modal" data-bs-target="#editOrderModal{{ $order->id }}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>


                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-top">
            {{ $orders->withQueryString()->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="fas fa-receipt text-muted mb-3" style="font-size: 3rem;"></i>
            <p class="text-muted mb-0">Tidak ada pesanan.</p>
        </div>
        @endif
    </div>
</div>

@push('modals')
@foreach($orders as $order)
{{-- Detail Order Modal --}}
<div class="modal fade" id="detailOrderModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Detail Pesanan #{{ $order->order_ref }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">Informasi Pelanggan</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted" style="width: 120px;">Nama</td><td class="fw-bold">{{ $order->user->full_name ?? '-' }}</td></tr>
                            <tr><td class="text-muted">Username</td><td>{{ $order->user->username ? '@'.$order->user->username : '-' }}</td></tr>
                            <tr><td class="text-muted">Telegram ID</td><td><code>{{ $order->user->telegram_id ?? '-' }}</code></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">Rincian Transaksi</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted" style="width: 120px;">Produk</td><td class="fw-bold">{{ $order->product->name ?? '-' }}</td></tr>
                            <tr><td class="text-muted">Subtotal</td><td>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td></tr>
                            <tr><td class="text-muted">Kode Unik</td><td>Rp {{ $order->unique_code }}</td></tr>
                            <tr><td class="text-muted">Total Bayar</td><td class="fw-bold text-primary">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td></tr>
                            <tr><td class="text-muted">Status</td>
                                <td>
                                    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }}">
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    @if($order->stockUnits && $order->stockUnits->count() > 0)
                    <div class="col-12 mt-2">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">Data Akun yang Dikirim ({{ $order->stockUnits->count() }} unit)</h6>
                        <div class="bg-light rounded-3 p-3 text-break" style="max-height: 250px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; font-size: 0.85rem;">
@foreach($order->stockUnits as $unit)
{{ $unit->raw_text }}
@if(!$loop->last)

----------------------------------------

@endif
@endforeach
</div>
                    </div>
                    @endif

                    <div class="col-12 mt-2">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">Log Sistem</h6>
                        <div class="d-flex flex-wrap gap-3 small">
                            <div><span class="text-muted">Dibuat:</span> <br><b>{{ $order->created_at->format('d M Y H:i:s') }}</b></div>
                            @if($order->paid_at)
                            <div><span class="text-muted">Dibayar:</span> <br><b class="text-success">{{ \Carbon\Carbon::parse($order->paid_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                            @if($order->delivered_at)
                            <div><span class="text-muted">Dikirim:</span> <br><b class="text-primary">{{ \Carbon\Carbon::parse($order->delivered_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                            @if($order->cancelled_at)
                            <div><span class="text-muted">Dibatalkan:</span> <br><b class="text-danger">{{ \Carbon\Carbon::parse($order->cancelled_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                        </div>
                        @if($order->cancel_reason)
                        <div class="alert alert-danger mt-3 small mb-0">
                            <b>Alasan Batal:</b> {{ $order->cancel_reason }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Status Modal --}}
<div class="modal fade" id="editOrderModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Ubah Status Pesanan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.orders.update', $order->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <p class="mb-1 text-muted small">No. Order</p>
                        <h6 class="fw-bold text-primary">{{ $order->reference }}</h6>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">Status Baru</label>
                        <select name="status" class="form-select" required>
                            <option value="pending_payment" {{ $order->status == 'pending_payment' ? 'selected' : '' }}>Pending Payment</option>
                            <option value="paid" {{ $order->status == 'paid' ? 'selected' : '' }}>Paid (Lunas)</option>
                            <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }}>Delivered (Selesai)</option>
                            <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled (Dibatalkan)</option>
                            <option value="expired" {{ $order->status == 'expired' ? 'selected' : '' }}>Expired (Kedaluwarsa)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endpush
@endsection
