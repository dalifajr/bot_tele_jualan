@extends('layouts.app')

@section('title', 'Detail Pesanan ' . $order->reference)
@section('page_subtitle', 'Detail Pesanan')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-receipt text-primary me-2"></i>
                        Pesanan {{ $order->reference }}
                    </h5>
                    <span class="badge bg-{{ $order->status_color }}-subtle text-{{ $order->status_color }} rounded-pill px-3 py-2">
                        {{ $order->status_label }}
                    </span>
                </div>
            </div>
            <div class="card-body px-4 pb-4">
                {{-- Flash Messages --}}
                @if(session('success'))
                <div class="alert alert-success border-0 shadow-sm d-flex align-items-center gap-2 mb-4" style="border-radius: 12px;">
                    <i class="fas fa-check-circle fs-5"></i>
                    <div>{{ session('success') }}</div>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center gap-2 mb-4" style="border-radius: 12px;">
                    <i class="fas fa-exclamation-circle fs-5"></i>
                    <div>{{ session('error') }}</div>
                </div>
                @endif

                <div class="mb-4">
                    <span class="text-muted small d-block mb-2">{{ __('Item Pesanan:') }}</span>
                    <div class="list-group list-group-flush border rounded-3 overflow-hidden mb-3">
                        @foreach($order->{{ __('items as $item)') }}
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold text-dark">{{ $item->product->name ?? '-' }}</span>
                                    <small class="text-muted d-block">{{ $item->quantity }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }}</small>
                                </div>
                                <span class="fw-bold text-dark">Rp {{ number_format($item->unit_price * $item->quantity, 0, ',', '.') }}</span>
                            </div>

                            {{-- Product Review Section --}}
                            @if($order->status === 'delivered' && $item->product)
                                @php
                                    $hasReview = \App\Models\Review::where('order_id', $order->id)
                                        ->where('product_id', $item->product_id)
                                        ->first();
                                @endphp
                                @if($hasReview)
                                    <div class="mt-3 p-2 bg-success-subtle rounded-3" style="font-size: 0.85rem;">
                                        <i class="fas fa-check-circle text-success me-1"></i> {{ __('Anda telah memberikan ulasan:') }}
                                        <span class="text-warning ms-1">
                                            @for($i=1; $i<=5; $i++)
                                                <i class="fa{{ $i <= $hasReview->rating ? 's' : 'r' }} fa-star"></i>
                                            @endfor
                                        </span>
                                        @if($hasReview->{{ __('comment)') }}
                                            <p class="mb-0 mt-1 text-secondary italic">"{{ $hasReview->comment }}"</p>
                                        @endif
                                    </div>
                                @else
                                    <div class="mt-3 p-3 bg-light rounded-3" style="font-size: 0.85rem;">
                                        <h6 class="fw-bold text-dark small mb-2"><i class="fas fa-star text-warning me-1"></i> {{ __('Berikan Ulasan & Rating:') }}</h6>
                                        <form action="{{ route('reviews.store') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="order_id" value="{{ $order->id }}">
                                            <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                            
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <span class="small text-muted">{{ __('Rating:') }}</span>
                                                <div class="rating-input d-flex gap-1 text-warning">
                                                    @for($star = 1; $star <= 5; $star++)
                                                    <input type="radio" name="rating" id="star-{{ $order->id }}-{{ $item->product_id }}-{{ $star }}" value="{{ $star }}" class="d-none" required>
                                                    <label for="star-{{ $order->id }}-{{ $item->product_id }}-{{ $star }}" style="cursor: pointer;" onclick="highlightStars({{ $order->id }}, {{ $item->product_id }}, {{ $star }})">
                                                        <i class="far fa-star fs-5" id="icon-{{ $order->id }}-{{ $item->product_id }}-{{ $star }}"></i>
                                                    </label>
                                                    @endfor
                                                </div>
                                            </div>

                                            <div class="input-group input-group-sm">
                                                <input type="text" name="comment" class="form-control" placeholder="{{ __('Tulis ulasan Anda (opsional)...') }}">
                                                <button class="btn btn-primary" type="submit">{{ __('Kirim') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

                <hr>

                <h6 class="fw-bold mb-3">{{ __('Timeline') }}</h6>
                <div class="order-timeline">
                    <div class="timeline-step completed">
                        <div class="fw-bold small">{{ __('Pesanan Dibuat') }}</div>
                        <div class="text-muted small">{{ $order->created_at->format('d M Y H:i:s') }}</div>
                    </div>

                    @if($order->{{ __('status === \'delivered\')') }}
                    <div class="timeline-step completed">
                        <div class="fw-bold small">{{ __('Pembayaran Diterima') }}</div>
                        <div class="text-muted small">{{ __('Pembayaran telah diverifikasi') }}</div>
                    </div>
                    <div class="timeline-step completed">
                        <div class="fw-bold small">{{ __('Produk Dikirim') }}</div>
                        <div class="text-muted small">
                            {{ $order->delivered_at ? $order->delivered_at->format('d M Y H:i:s') : '-' }}
                        </div>
                    </div>
                    @elseif($order->{{ __('status === \'paid\')') }}
                    <div class="timeline-step completed">
                        <div class="fw-bold small">{{ __('Pembayaran Diterima') }}</div>
                        <div class="text-muted small">{{ __('Menunggu pengiriman produk') }}</div>
                    </div>
                    <div class="timeline-step active">
                        <div class="fw-bold small">{{ __('Menunggu Pengiriman') }}</div>
                        <div class="text-muted small">{{ __('Produk sedang diproses') }}</div>
                    </div>
                    @elseif($order->{{ __('status === \'pending_payment\')') }}
                    <div class="timeline-step active">
                        <div class="fw-bold small">{{ __('Menunggu Pembayaran') }}</div>
                        <div class="text-muted small">
                            @if($order->expires_at)
                                Batas waktu: {{ $order->expires_at->format('d M Y H:i:s') }}
                            @else
                                Segera lakukan pembayaran
                            @endif
                        </div>
                    </div>
                    @elseif(in_array($order->{{ __('status, [\'cancelled\', \'expired\']))') }}
                    <div class="timeline-step">
                        <div class="fw-bold small text-danger">
                            {{ $order->status === 'cancelled' ? 'Dibatalkan' : 'Kedaluwarsa' }}
                        </div>
                        <div class="text-muted small">
                            {{ $order->cancel_reason ?: 'Tidak ada alasan' }}
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Stock content for delivered orders --}}
                @if($order->status === 'delivered')
                    @if($order->stockUnits && $order->stockUnits->count() > 0)
                    <hr>
                    <h6 class="fw-bold mb-3"><i class="fas fa-key text-success me-2"></i>{{ __('Detail Akun yang Dibeli') }}</h6>
                    <div class="bg-body-secondary rounded-3 p-3 text-break" style="max-height: 300px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; font-size: 0.9rem;">
@foreach($order->stockUnits as $unit)
{{ $unit->raw_text }}
@if(!$loop->last)

----------------------------------------

@endif
@endforeach
                    </div>
                    @endif

                    @if($order->vpnAccounts && $order->vpnAccounts->count() > 0)
                    <hr>
                    <h6 class="fw-bold mb-3"><i class="fas fa-network-wired text-primary me-2"></i>{{ __('Konfigurasi Akun VPN') }}</h6>
                    @foreach($order->{{ __('vpnAccounts as $vpn)') }}
                        <div class="card mb-3 border-primary-subtle shadow-sm" style="border-radius: 12px;">
                            <div class="card-header bg-primary-subtle border-0">
                                <h6 class="mb-0 fw-bold text-primary">Protokol: {{ strtoupper($vpn->protocol) }} ({{ $vpn->username }})</h6>
                            </div>
                            <div class="card-body bg-light">
                                <div class="mb-2">
                                    <span class="text-muted small">{{ __('Masa Aktif:') }}</span>
                                    <strong class="d-block">{{ $vpn->expired_at ? $vpn->expired_at->format('d M Y') : '-' }}</strong>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted small">{{ __('Status:') }}</span>
                                    <span class="badge {{ $vpn->status === 'active' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($vpn->status) }}</span>
                                </div>
                                <hr>
                                <span class="text-muted small d-block mb-2">{{ __('Konfigurasi Output / Link:') }}</span>
                                <div class="bg-dark text-light rounded-3 p-3 text-break" style="max-height: 250px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; font-size: 0.85rem;">{{ $vpn->config_link ?: 'Tidak ada konfigurasi ditemukan.' }}</div>
                            </div>
                        </div>
                    @endforeach
                    @endif
                @endif

                {{-- Modul Komplain / Garansi --}}
                @if($order->{{ __('status === \'delivered\')') }}
                <hr>
                <div class="mt-4">
                    @if($order->{{ __('complaintCase)') }}
                        <div class="card bg-body-tertiary border-0 shadow-sm" style="border-radius: 12px;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold mb-0 text-dark">
                                        <i class="fas fa-toolbox text-warning me-2"></i>
                                        {{ __('Tiket Komplain & Garansi') }}
                                    </h6>
                                    @php
                                        $statusBadge = match($order->complaintCase->status) {
                                            'new' => ['bg' => 'danger-subtle', 'text' => 'danger', 'label' => 'Baru'],
                                            'review' => ['bg' => 'warning-subtle', 'text' => 'warning', 'label' => 'Ditinjau'],
                                            'done' => ['bg' => 'success-subtle', 'text' => 'success', 'label' => 'Selesai'],
                                            'rejected' => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => 'Ditolak'],
                                            default => ['bg' => 'secondary-subtle', 'text' => 'secondary', 'label' => $order->complaintCase->status]
                                        };
                                    @endphp
                                    <span class="badge bg-{{ $statusBadge['bg'] }} text-{{ $statusBadge['text'] }} rounded-pill px-3 py-1.5 small">
                                        {{ $statusBadge['label'] }}
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted small d-block">{{ __('No Tiket:') }}</span>
                                    <span class="fw-bold text-secondary">#{{ $order->complaintCase->complaint_ref }}</span>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted small d-block">{{ __('Detail Masalah:') }}</span>
                                    <p class="mb-0 text-dark small bg-body p-2.5 rounded border border-light-subtle">{{ $order->complaintCase->complaint_text }}</p>
                                </div>
                                @if($order->complaintCase->status === 'rejected' && $order->complaintCase->{{ __('rejected_reason)') }}
                                <div class="alert alert-danger mb-0 py-2.5 px-3 rounded-3 mt-2">
                                    <h6 class="fw-bold mb-1 small"><i class="fas fa-times-circle me-1"></i>{{ __('Alasan Penolakan Admin:') }}</h6>
                                    <p class="mb-0 small">{{ $order->complaintCase->rejected_reason }}</p>
                                </div>
                                @endif
                                @if($order->complaintCase->status === 'done' && $order->complaintCase->{{ __('refund_note)') }}
                                <div class="alert alert-success mb-0 py-2.5 px-3 rounded-3 mt-2">
                                    <h6 class="fw-bold mb-1 small"><i class="fas fa-check-circle me-1"></i>{{ __('Catatan Resolusi Admin:') }}</h6>
                                    <p class="mb-0 small">{{ $order->complaintCase->refund_note }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    @else
                        @if($order->{{ __('is_warranty_active)') }}
                        <div class="d-flex align-items-center justify-content-between bg-light-subtle border border-dashed border-secondary-subtle rounded-3 p-3 mt-3">
                            <div>
                                <h6 class="fw-bold mb-1"><i class="fas fa-shield-alt text-primary me-2"></i>{{ __('Garansi Toko Aktif') }}</h6>
                                <p class="text-muted small mb-0">{{ __('Batas akhir:') }} <strong>{{ $order->warranty_expires_at->format('d M Y H:i') }}</strong></p>
                                <p class="text-muted small mb-0 mt-1">{{ __('Apakah akun digital yang Anda beli bermasalah? Ajukan klaim garansi di sini.') }}</p>
                            </div>
                            <button type="button" class="btn btn-warning rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#complaintModal">
                                <i class="fas fa-toolbox me-2"></i>{{ __('Ajukan Komplain') }}
                            </button>
                        </div>
                        @else
                        <div class="d-flex align-items-center justify-content-between bg-body-secondary border border-dashed border-secondary-subtle rounded-3 p-3 mt-3">
                            <div>
                                <h6 class="fw-bold mb-1 text-secondary"><i class="fas fa-shield-alt me-2 text-secondary"></i>{{ __('Garansi Toko Kedaluwarsa') }}</h6>
                                @if($order->{{ __('warranty_expires_at)') }}
                                <p class="text-muted small mb-0">{{ __('Garansi berakhir pada:') }} <strong>{{ $order->warranty_expires_at->format('d M Y H:i') }}</strong></p>
                                @else
                                <p class="text-muted small mb-0">{{ __('Produk ini tidak memiliki garansi atau pesanan belum selesai.') }}</p>
                                @endif
                            </div>
                            <button type="button" class="btn btn-secondary rounded-pill px-4" disabled>
                                <i class="fas fa-toolbox me-2"></i>{{ __('Klaim Berakhir') }}
                            </button>
                        </div>
                        @endif
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3">{{ __('Ringkasan') }}</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ __('Harga Satuan') }}</span>
                    <span>Rp {{ number_format(($order->total_price / max(1, $order->quantity)), 0, ',', '.') }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">{{ __('Jumlah') }}</span>
                    <span>× {{ $order->quantity }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">{{ __('Total') }}</span>
                    <span class="fw-bold product-price">{{ $order->formatted_total }}</span>
                </div>
            </div>
        </div>

        <div class="mt-3">
            @php
                $seller = $order->product->creator ?? null;
            @endphp
            @if($seller && $seller->{{ __('id !== Auth::id())') }}
                <a href="{{ route('chat.index', ['contact_id' => $seller->id]) }}" class="btn btn-outline-primary w-100 rounded-pill mb-2">
                    <i class="fas fa-comments me-2"></i>{{ __('Chat dengan Penjual') }}
                </a>
            @endif
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary w-100 rounded-pill mb-2">
                <i class="fas fa-arrow-left me-2"></i>{{ __('Kembali ke Riwayat') }}
            </a>
            @if($order->{{ __('status === \'pending_payment\')') }}
            <a href="{{ route('checkout.success', $order->order_ref) }}" class="btn btn-success w-100 rounded-pill mb-2">
                <i class="fas fa-qrcode me-2"></i>{{ __('Bayar Sekarang (QRIS)') }}
            </a>
            <form action="{{ route('orders.cancel', $order->id) }}" method="POST" onsubmit="confirmAction(event, 'Apakah Anda yakin ingin membatalkan pesanan ini?');">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100 rounded-pill">
                    <i class="fas fa-times-circle me-2"></i>{{ __('Batalkan Pesanan') }}
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

@if($order->status === 'delivered' && !$order->complaintCase)
@push('modals')
<div class="modal fade" id="complaintModal" tabindex="-1" aria-labelledby="complaintModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold" id="complaintModalLabel"><i class="fas fa-toolbox text-warning me-2"></i>{{ __('Ajukan Klaim Garansi') }}</h5>
                <button type="button" class="btn-close" data-bs-toggle="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('orders.complaint', $order->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 small border-0 mb-3" style="border-radius: 8px;">
                        <i class="fas fa-info-circle me-1"></i> <b>{{ __('Kebijakan Garansi:') }}</b> {{ __('Harap jelaskan kendala yang dialami secara rinci (misal: kredensial salah, pack belum diklaim, dsb).') }}
                    </div>
                    <div class="mb-3">
                        <label for="complaint_text" class="form-label text-muted small fw-bold">{{ __('Deskripsi Masalah / Keluhan') }}</label>
                        <textarea class="form-control" id="complaint_text" name="complaint_text" rows="4" required minlength="10" maxlength="1000" placeholder="{{ __('Tuliskan keluhan Anda di sini (minimal 10 karakter)...') }}"></textarea>
                    </div>
                    <div class="mb-0">
                        <label for="attachment" class="form-label text-muted small fw-bold">{{ __('Unggah Foto Bukti (Opsional)') }}</label>
                        <input class="form-control" type="file" id="attachment" name="attachment" accept="image/*">
                        <div class="form-text">{{ __('Hanya menerima format gambar (jpg, png). Maksimal 10MB.') }}</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4">{{ __('Kirim Pengaduan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush
@endif

@push('scripts')
<script>
    function highlightStars(orderId, productId, rating) {
        for (let i = 1; i <= 5; i++) {
            const starIcon = document.getElementById(`icon-${orderId}-${productId}-${i}`);
            if (starIcon) {
                if (i <= rating) {
                    starIcon.classList.remove('far');
                    starIcon.classList.add('fas');
                } else {
                    starIcon.classList.remove('fas');
                    starIcon.classList.add('far');
                }
            }
        }
        const radioButton = document.getElementById(`star-${orderId}-${productId}-${rating}`);
        if (radioButton) {
            radioButton.checked = true;
        }
    }
</script>
@endpush
@endsection
