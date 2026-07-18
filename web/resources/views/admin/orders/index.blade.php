@extends('layouts.app')

@section('title', 'Manajemen Pesanan')
@section('page_subtitle', 'Pesanan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">{{ __('Manajemen Pesanan') }}</h4>
        <p class="text-muted mb-0">{{ __('Daftar semua transaksi') }}</p>
    </div>
</div>

{{-- Status Filter --}}
<div class="mb-3 d-flex flex-wrap gap-2">
    <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}"
       class="btn btn-sm rounded-pill px-3 {{ is_null($status) ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ __('Semua') }}
    </a>
    @foreach(['pending_payment' => 'Pending', 'paid' => 'Paid', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'expired' => 'Expired'] as $key => $label
    <a href="{{ request()->fullUrlWithQuery(['status' => $key]) }}"
       class="btn btn-sm rounded-pill px-3 {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
        {{ $label }}
    </a>
    @endforeach
</div>

{{-- Filters Row --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
    <div class="card-body p-3">
        <form action="{{ route('admin.orders.index') }}" method="GET" class="row g-2 align-items-center">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-0 bg-light" placeholder="{{ __('Cari No. Order, nama pelanggan, username, ID Telegram, nama produk...') }}" value="{{ request('search') }}">
                </div>
            </div>

            {{-- Product Filter --}}
            <div class="col-md-3 col-6">
                <select name="product_id" class="form-select border-0 bg-light">
                    <option value="">{{ __('Semua Produk') }}</option>
                    @php
                        $filterProducts = \App\Models\Product::orderBy('name')->get();
                    @endphp
                    @foreach($filterProducts as $fp)
                        <option value="{{ $fp->id }}" {{ request('product_id') == $fp->id ? 'selected' : '' }}>
                            {{ $fp->is_suspended ? '🔴' : '✅' }} {{ $fp->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Buttons --}}
            <div class="col-md-3 col-6 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn btn-primary px-3 rounded-pill flex-fill">{{ __('Cari & Filter') }}</button>
                @if(request('search') || request('product_id'))
                    <a href="{{ route('admin.orders.index', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-light px-3 rounded-pill">{{ __('Reset') }}</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 16px;">
    <div class="card-body p-0">
        @if($orders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="text-secondary small border-bottom">
                        <th class="px-4 py-3 border-0">{{ __('No. Order') }}</th>
                        <th class="py-3 border-0">{{ __('Pelanggan') }}</th>
                        <th class="py-3 border-0">{{ __('Produk') }}</th>
                        <th class="py-3 border-0">{{ __('Total') }}</th>
                        <th class="py-3 border-0">{{ __('Status') }}</th>
                        <th class="py-3 border-0">{{ __('Tanggal') }}</th>
                        <th class="py-3 border-0 text-end px-4">{{ __('Aksi') }}</th>
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
                                <form action="{{ route('admin.orders.accept', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Konfirmasi terima pembayaran untuk pesanan ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light text-success rounded-circle border-success" title="{{ __('Terima Pembayaran') }}">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.orders.reject', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Tolak dan batalkan pesanan ini?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle border-danger" title="{{ __('Tolak Pesanan') }}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                @endif
                                <button class="btn btn-sm btn-light text-info rounded-circle" data-bs-toggle="modal" data-bs-target="#detailOrderModal{{ $order->id }}" title="{{ __('Detail') }}">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-light text-primary rounded-circle" data-bs-toggle="modal" data-bs-target="#editOrderModal{{ $order->id }}" title="{{ __('Edit') }}">
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
            <p class="text-muted mb-0">{{ __('Tidak ada pesanan.') }}</p>
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
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">{{ __('Informasi Pelanggan') }}</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted" style="width: 120px;">{{ __('Nama') }}</td><td class="fw-bold">{{ $order->user->full_name ?? '-' }}</td></tr>
                            <tr><td class="text-muted">{{ __('Username') }}</td><td>{{ $order->user->username ? '@'.$order->user->username : '-' }}</td></tr>
                            <tr><td class="text-muted">{{ __('Telegram ID') }}</td><td><code>{{ $order->user->telegram_id ?? '-' }}</code></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">{{ __('Rincian Transaksi') }}</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted" style="width: 120px;">{{ __('Produk') }}</td><td class="fw-bold">{{ $order->product->name ?? '-' }}</td></tr>
                            <tr><td class="text-muted">{{ __('Subtotal') }}</td><td>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td></tr>
                            <tr><td class="text-muted">{{ __('Kode Unik') }}</td><td>Rp {{ $order->unique_code }}</td></tr>
                            <tr><td class="text-muted">{{ __('Total Bayar') }}</td><td class="fw-bold text-primary">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td></tr>
                            <tr><td class="text-muted">{{ __('Status') }}</td>
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
                        @php $isMultiUnit = $order->stockUnits->count() > 1; @endphp
                        <div class="d-flex flex-column gap-3" style="max-height: 300px; overflow-y: auto;">
                            @foreach($order->stockUnits as $unit)
                            <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center gap-3">
                                <div class="text-break flex-grow-1" style="font-family: monospace; white-space: pre-wrap; font-size: 0.85rem;">{{ $unit->raw_text }}</div>
                                @if($order->status === 'delivered')
                                <div class="flex-shrink-0">
                                    @if($isMultiUnit)
                                        <input type="checkbox" class="form-check-input stock-checkbox" data-order-id="{{ $order->id }}" value="{{ $unit->id }}" onchange="updateStockCheckboxes('{{ $order->id }}')" style="width: 1.5rem; height: 1.5rem; border-radius: 4px; cursor: pointer; border: 2px solid #ccc;">
                                    @else
                                        <form action="{{ route('admin.orders.replace-stock', [$order->id, $unit->id]) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin mengganti akun ini dengan stok baru milik seller yang sama?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning text-warning-emphasis border-warning rounded-pill px-3">
                                                <i class="fas fa-sync-alt me-1"></i> {{ __('Ganti Akun') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                        
                        @if($isMultiUnit && $order->status === 'delivered')
                        <div class="selected-actions-container mt-3 d-none" id="actions-{{ $order->id }}">
                            <div class="d-flex justify-content-between align-items-center bg-warning-subtle p-3 rounded-3 border border-warning">
                                <span class="small text-warning-emphasis fw-bold"><span class="checked-count" id="count-{{ $order->id }}">0</span> {{ __('akun terpilih:') }}</span>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-warning rounded-pill px-3" onclick="submitBulkAction('{{ $order->id }}', 'replace')">
                                        <i class="fas fa-sync-alt me-1"></i> {{ __('Ganti Akun') }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger rounded-pill px-3" onclick="submitBulkAction('{{ $order->id }}', 'refund')">
                                        <i class="fas fa-undo-alt me-1"></i> {{ __('Refund Terpilih') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif

                    <div class="col-12 mt-2">
                        <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">{{ __('Log Sistem') }}</h6>
                        <div class="d-flex flex-wrap gap-3 small">
                            <div><span class="text-muted">{{ __('Dibuat:') }}</span> <br><b>{{ $order->created_at->format('d M Y H:i:s') }}</b></div>
                            @if($order->paid_at
                            <div><span class="text-muted">{{ __('Dibayar:') }}</span> <br><b class="text-success">{{ \Carbon\Carbon::parse($order->paid_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                            @if($order->delivered_at
                            <div><span class="text-muted">{{ __('Dikirim:') }}</span> <br><b class="text-primary">{{ \Carbon\Carbon::parse($order->delivered_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                            @if($order->cancelled_at
                            <div><span class="text-muted">{{ __('Dibatalkan:') }}</span> <br><b class="text-danger">{{ \Carbon\Carbon::parse($order->cancelled_at)->format('d M Y H:i:s') }}</b></div>
                            @endif
                        </div>
                        @if($order->cancel_reason
                        <div class="alert alert-danger mt-3 small mb-0">
                            <b>{{ __('Alasan Batal:') }}</b> {{ $order->cancel_reason }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                @if($order->status === 'delivered')
                <form action="{{ route('admin.orders.refund', $order->id) }}" method="POST" class="m-0 d-inline" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini, merefund penuh (stok ditarik ke karantina), dan membatalkan/menghapus saldo tertahan seller?');">
                    @csrf
                    <button type="submit" class="btn btn-danger rounded-pill px-4">
                        <i class="fas fa-undo-alt me-1"></i> {{ __('Refund & Batalkan') }}
                    </button>
                </form>
                @endif
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Status Modal --}}
<div class="modal fade" id="editOrderModal{{ $order->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">{{ __('Ubah Status Pesanan') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.orders.update', $order->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <p class="mb-1 text-muted small">{{ __('No. Order') }}</p>
                        <h6 class="fw-bold text-primary">{{ $order->reference }}</h6>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">{{ __('Status Baru') }}</label>
                        <select name="status" class="form-select" required>
                            <option value="pending_payment" {{ $order->status == 'pending_payment' ? 'selected' : '' }}>{{ __('Pending Payment') }}</option>
                            <option value="paid" {{ $order->status == 'paid' ? 'selected' : '' }}>Paid (Lunas)</option>
                            <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }}>Delivered (Selesai)</option>
                            <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled (Dibatalkan)</option>
                            <option value="expired" {{ $order->status == 'expired' ? 'selected' : '' }}>Expired (Kedaluwarsa)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">{{ __('Simpan Perubahan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endpush

@push('scripts')
<script>
    function updateStockCheckboxes(orderId) {
        const checkboxes = document.querySelectorAll(`.stock-checkbox[data-order-id="${orderId}"]`);
        const checked = document.querySelectorAll(`.stock-checkbox[data-order-id="${orderId}"]:checked`);
        const container = document.getElementById(`actions-${orderId}`);
        const countSpan = document.getElementById(`count-${orderId}`);
        
        if (container && countSpan) {
            if (checked.length > 0) {
                countSpan.textContent = checked.length;
                container.classList.remove('d-none');
            } else {
                container.classList.add('d-none');
            }
        }
    }

    function submitBulkAction(orderId, actionType) {
        const checked = document.querySelectorAll(`.stock-checkbox[data-order-id="${orderId}"]:checked`);
        if (checked.length === 0) return;
        
        const ids = Array.from(checked).map(cb => cb.value);
        
        let confirmMsg = '';
        let url = '';
        
        if (actionType === 'replace') {
            confirmMsg = `Apakah Anda yakin ingin mengganti ${ids.length} akun terpilih dengan stok baru dari seller?`;
            url = `/admin/orders/${orderId}/replace-stock-bulk`;
        } else {
            confirmMsg = `Apakah Anda yakin ingin melakukan refund sebagian untuk ${ids.length} akun terpilih ini? (Dana tertahan seller akan dikurangi)`;
            url = `/admin/orders/${orderId}/refund-bulk`;
        }
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Konfirmasi Aksi',
                text: confirmMsg,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: actionType === 'refund' ? '#dc3545' : '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Lanjutkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeBulkFormSubmit(url, ids);
                }
            });
        } else {
            if (confirm(confirmMsg)) {
                executeBulkFormSubmit(url, ids);
            }
        }
    }

    function executeBulkFormSubmit(url, ids) {
        const loader = document.getElementById('pageLoader');
        if (loader) loader.classList.remove('d-none');
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }
        
        const idsInput = document.createElement('input');
        idsInput.type = 'hidden';
        idsInput.name = 'stock_unit_ids';
        idsInput.value = JSON.stringify(ids);
        form.appendChild(idsInput);
        
        document.body.appendChild(form);
        form.submit();
    }
</script>
@endpush

@endsection
