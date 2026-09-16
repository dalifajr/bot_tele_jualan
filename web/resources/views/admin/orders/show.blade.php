@extends('layouts.app')

@section('title', __('Detail Pesanan Admin ') . $order->reference)
@section('page_subtitle', __('Pesanan'))

@section('content')
{{-- Header --}}
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-3">
    <div class="d-flex align-items-center gap-2 min-w-0">
        <a href="{{ route('admin.orders.index') }}" class="btn btn-light rounded-circle shadow-sm border d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;" title="{{ __('Kembali ke Daftar Pesanan') }}">
            <i class="fas fa-arrow-left text-body"></i>
        </a>
        <div class="min-w-0">
            <h5 class="fw-bold m-0 text-body text-truncate" style="font-size: 1.15rem;">{{ __('Detail Pesanan (Admin)') }}</h5>
            <small class="text-muted d-block text-truncate" style="font-size: 0.75rem;">{{ $order->reference }} &bull; {{ $order->created_at->format('d M Y, H:i') }}</small>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2 ms-auto ms-sm-0 flex-shrink-0">
        <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-1.5 fw-bold" style="font-size: 0.8rem;">
            {{ $order->status_label }}
        </span>
        <button class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#editOrderStatusModal" style="font-size: 0.8rem;">
            <i class="fas fa-edit"></i>
            <span>{{ __('Ubah Status') }}</span>
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-3"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-3"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

<div class="row g-3 g-md-4">
    {{-- Main Column (Left) --}}
    <div class="col-lg-8">
        {{-- Pending Payment Approval Banner --}}
        @if($order->status === 'pending_payment')
        <div class="card border-0 shadow-sm mb-3 bg-primary-subtle border-start border-primary border-4" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h6 class="fw-bold text-primary mb-1"><i class="fas fa-clock me-1"></i>{{ __('Menunggu Verifikasi Pembayaran') }}</h6>
                    <p class="text-secondary small mb-0">{{ __('Pelanggan telah membuat pesanan sebesar ') }} <b>{{ $order->formatted_total }}</b>.</p>
                </div>
                <div class="d-flex gap-2">
                    <form action="{{ route('admin.orders.accept', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Konfirmasi terima pembayaran untuk pesanan ini?');">
                        @csrf
                        <button type="submit" class="btn btn-success rounded-pill px-3 py-1.5 fw-bold">
                            <i class="fas fa-check me-1"></i>{{ __('Terima Pembayaran') }}
                        </button>
                    </form>
                    <form action="{{ route('admin.orders.reject', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Tolak dan batalkan pesanan ini?');">
                        @csrf
                        <button type="submit" class="btn btn-danger rounded-pill px-3 py-1.5 fw-bold">
                            <i class="fas fa-times me-1"></i>{{ __('Tolak Pesanan') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- Order Items Card --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-shopping-bag text-primary me-2"></i>{{ __('Item Pesanan') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="p-2.5 p-md-3 bg-light rounded-3 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2.5 mt-1">
                    <div class="d-flex align-items-center gap-2.5 min-w-0 flex-grow-1">
                        <div class="rounded-3 bg-white border p-1.5 d-flex align-items-center justify-content-center text-primary flex-shrink-0" style="width: 50px; height: 50px;">
                            @if($order->product && $order->product->image_url)
                                <img src="{{ $order->product->image_url }}" alt="{{ $order->product->name }}" class="img-fluid rounded-2" style="max-height: 42px; object-fit: contain;">
                            @else
                                <i class="fas fa-box fa-lg opacity-75"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="fw-bold text-dark mb-1 text-truncate" style="font-size: 0.92rem;" title="{{ $order->product->name ?? '-' }}">{{ $order->product->name ?? '-' }}</h6>
                            <div class="text-muted small" style="font-size: 0.78rem;">
                                {{ $order->quantity }} unit &times; Rp {{ number_format($order->subtotal / max(1, $order->quantity), 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                    <div class="text-end d-flex justify-content-between justify-content-sm-end align-items-center pt-2 pt-sm-0 border-top border-sm-top-0 flex-shrink-0">
                        <span class="text-muted small d-sm-none">{{ __('Subtotal:') }}</span>
                        <span class="fw-bold text-dark fs-6">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="border-top mt-3 pt-3">
                    <div class="d-flex justify-content-between mb-1.5 small text-muted">
                        <span>{{ __('Subtotal') }}</span>
                        <span class="fw-semibold text-dark">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($order->unique_code)
                    <div class="d-flex justify-content-between mb-1.5 small text-muted">
                        <span>{{ __('Kode Unik') }}</span>
                        <span class="fw-semibold text-dark">Rp {{ $order->unique_code }}</span>
                    </div>
                    @endif
                    <div class="d-flex justify-content-between pt-2 border-top fw-bold fs-6">
                        <span>{{ __('Total Pembayaran') }}</span>
                        <span class="text-primary">{{ $order->formatted_total }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sent Stock / Credentials & Admin Stock Replacement Section --}}
        @if($order->stockUnits && $order->stockUnits->count() > 0)
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fas fa-key text-success me-2"></i>{{ __('Data Akun / Lisensi Terkirim') }} ({{ $order->stockUnits->count() }} unit)
                    </h6>
                    <small class="text-muted">{{ __('Data kredensial yang diserahkan otomatis kepada pembeli') }}</small>
                </div>
                @if($order->status === 'delivered')
                <form action="{{ route('admin.orders.refund', $order->id) }}" method="POST" class="m-0" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini, merefund penuh (stok ditarik ke karantina), dan membatalkan/menghapus saldo tertahan seller?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                        <i class="fas fa-undo-alt me-1"></i> {{ __('Refund Penuh & Batalkan') }}
                    </button>
                </form>
                @endif
            </div>
            <div class="card-body p-3 p-md-4">
                @php $isMultiUnit = $order->stockUnits->count() > 1; @endphp
                <div class="d-flex flex-column gap-3 mt-1">
                    @foreach($order->stockUnits as $unit)
                    <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center gap-3">
                        <div class="text-break flex-grow-1" style="font-family: monospace; white-space: pre-wrap; font-size: 0.85rem;">
                            {!! \App\Services\TwoFactorService::renderWith2fa($unit->raw_text, true) !!}
                        </div>
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

                {{-- Bulk Action Bar --}}
                @if($isMultiUnit && $order->status === 'delivered')
                <div class="selected-actions-container mt-3 d-none" id="actions-{{ $order->id }}">
                    <div class="d-flex justify-content-between align-items-center bg-warning-subtle p-3 rounded-3 border border-warning">
                        <span class="small text-warning-emphasis fw-bold"><span class="checked-count" id="count-{{ $order->id }}">0</span> {{ __('akun terpilih:') }}</span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-warning rounded-pill px-3" onclick="submitBulkAction('{{ $order->id }}', 'replace')">
                                <i class="fas fa-sync-alt me-1"></i> {{ __('Ganti Akun Terpilih') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-danger rounded-pill px-3" onclick="submitBulkAction('{{ $order->id }}', 'refund')">
                                <i class="fas fa-undo-alt me-1"></i> {{ __('Refund Terpilih') }}
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Complaint Case Card if any --}}
        @if($order->complaintCase)
        <div class="card border-0 shadow-sm mb-3 border-start border-danger border-4" style="border-radius: 16px;">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-danger mb-0">
                        <i class="fas fa-shield-alt me-2"></i>{{ __('Pusat Komplain / Dispute') }}
                    </h6>
                    <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1">
                        {{ ucfirst($order->complaintCase->status) }}
                    </span>
                </div>
                <p class="text-muted small mb-3">{{ $order->complaintCase->reason }}</p>
                @if(Route::has('admin.complaints.show'))
                <a href="{{ route('admin.complaints.show', $order->complaintCase->id) }}" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                    <i class="fas fa-gavel me-1"></i>{{ __('Tinjau Kasus Komplain') }}
                </a>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Side Column (Right) --}}
    <div class="col-lg-4">
        {{-- Customer Info Card --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-user text-primary me-2"></i>{{ __('Informasi Pelanggan') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center gap-3 mb-3 mt-1">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold fs-5 flex-shrink-0" style="width: 44px; height: 44px;">
                        {{ strtoupper(substr($order->customer->full_name ?? $order->customer->username ?? 'P', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <h6 class="fw-bold mb-0 text-dark text-truncate">{{ $order->customer->full_name ?? $order->customer->username ?? '-' }}</h6>
                        <small class="text-muted text-truncate d-block">{{ $order->customer->username ? '@'.$order->customer->username : '-' }}</small>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">{{ __('Telegram ID:') }}</span>
                        <code>{{ $order->customer->telegram_id ?? '-' }}</code>
                    </div>
                    @if($order->customer && $order->customer->telegram_username)
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">{{ __('Telegram:') }}</span>
                        <a href="https://t.me/{{ $order->customer->telegram_username }}" target="_blank" class="text-decoration-none fw-semibold">
                            <i class="fab fa-telegram me-1"></i>Chat
                        </a>
                    </div>
                    @endif
                    @if(Route::has('admin.users.edit') && $order->customer)
                    <div class="text-end pt-2 border-top">
                        <a href="{{ route('admin.users.edit', $order->customer->id) }}" class="text-decoration-none small text-primary">
                            <i class="fas fa-external-link-alt me-1"></i>{{ __('Lihat Profil User') }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Seller Info Card --}}
        @php
            $seller = $order->product?->creator;
        @endphp
        @if($seller)
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-store text-success me-2"></i>{{ __('Penjual / Seller') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center gap-3 mb-2 mt-1">
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center fw-bold fs-5 flex-shrink-0" style="width: 44px; height: 44px;">
                        {{ strtoupper(substr($seller->full_name ?? $seller->username ?? 'S', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <h6 class="fw-bold mb-0 text-dark text-truncate">{{ $seller->full_name ?? $seller->username }}</h6>
                        <small class="text-muted text-truncate d-block">{{ $seller->username ? '@'.$seller->username : '-' }}</small>
                    </div>
                </div>
                <div class="p-2.5 bg-light rounded-3 small">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">{{ __('Telegram ID:') }}</span>
                        <code>{{ $seller->telegram_id ?? '-' }}</code>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Transaction Log & Timeline Card --}}
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-3 pt-md-4 px-3 px-md-4 pb-0">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="fas fa-history text-secondary me-2"></i>{{ __('Log Sistem') }}
                </h6>
            </div>
            <div class="card-body p-3 p-md-4">
                <ul class="list-unstyled mb-0 small">
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-clock text-muted mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibuat:') }}</span>
                            <div class="fw-semibold">{{ $order->created_at->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @if($order->paid_at)
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-check-circle text-success mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibayar:') }}</span>
                            <div class="fw-semibold text-success">{{ \Carbon\Carbon::parse($order->paid_at)->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($order->delivered_at)
                    <li class="d-flex align-items-start gap-2 mb-2.5">
                        <i class="fas fa-paper-plane text-primary mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dikirim:') }}</span>
                            <div class="fw-semibold text-primary">{{ \Carbon\Carbon::parse($order->delivered_at)->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                    @if($order->cancelled_at)
                    <li class="d-flex align-items-start gap-2">
                        <i class="fas fa-times-circle text-danger mt-1"></i>
                        <div>
                            <span class="text-muted">{{ __('Dibatalkan:') }}</span>
                            <div class="fw-semibold text-danger">{{ \Carbon\Carbon::parse($order->cancelled_at)->format('d M Y H:i:s') }}</div>
                        </div>
                    </li>
                    @endif
                </ul>

                @if($order->cancel_reason)
                <div class="alert alert-danger mt-3 small mb-0 py-2">
                    <b>{{ __('Alasan Batal:') }}</b> {{ $order->cancel_reason }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Edit Status Modal (Rendered at Root Body outside container) --}}
<div class="modal fade" id="editOrderStatusModal" tabindex="-1" aria-labelledby="editOrderStatusModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold" id="editOrderStatusModalLabel">{{ __('Ubah Status Pesanan') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.orders.update', $order->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <p class="mb-1 text-muted small">{{ __('No. Order') }}</p>
                        <h6 class="fw-bold text-primary font-monospace">{{ $order->reference }}</h6>
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
        const checked = document.querySelectorAll(`.stock-checkbox[data-order-id="${orderId}"]`);
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
