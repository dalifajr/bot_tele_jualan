@extends('layouts.app')

@section('title', 'Pembayaran')
@section('page_subtitle', 'Selesaikan Pembayaran')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-white border-0 text-center pt-4 pb-0">
                <div class="mb-3">
                    <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-warning rounded-circle" style="width: 80px; height: 80px;">
                        <i class="fas fa-clock fs-1"></i>
                    </div>
                </div>
                <h4 class="fw-bold">Menunggu Pembayaran</h4>
                <p class="text-muted mb-0">Selesaikan pembayaran sebelum batas waktu berakhir.</p>
            </div>
            
            <div class="card-body p-4">
                <div class="bg-light rounded-3 p-3 mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Order Ref</span>
                        <span class="fw-bold font-monospace">{{ $order->order_ref }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Batas Bayar</span>
                        <span class="fw-bold text-danger" id="countdownTimer" data-expires="{{ $order->expires_at->toIso8601String() }}">
                            {{ $order->expires_at->format('d M Y, H:i') }} WIB
                        </span>
                    </div>
                    <hr class="border-secondary-subtle opacity-50">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Total Tagihan</span>
                        <span class="fs-4 fw-bold text-primary">{{ $order->formatted_total }}</span>
                    </div>
                </div>

                @if($dynamicQris)
                    <div class="text-center mb-4">
                        <h6 class="fw-bold mb-3">Scan QRIS Berikut:</h6>
                        <div class="d-inline-block bg-white p-3 rounded-3 shadow-sm border mb-3" id="qrcode"></div>
                        <p class="small text-muted mb-0">Atau salin kode di bawah ini jika menggunakan aplikasi m-Banking:</p>
                        <div class="input-group mt-2">
                            <input type="text" class="form-control font-monospace small bg-light" value="{{ $dynamicQris }}" id="qrisPayload" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyQris()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="alert alert-info text-center" role="alert">
                        <i class="fas fa-info-circle mb-2 fs-4"></i><br>
                        Silakan cek <strong>Bot Telegram</strong> Anda untuk instruksi pembayaran lengkap dan kode QRIS.
                    </div>
                @endif

                <div class="d-grid gap-2 mt-4">
                    <a href="{{ route('orders.show', $order->id) }}" class="btn btn-primary rounded-pill py-2">
                        <i class="fas fa-search me-1"></i> Cek Status Pembayaran
                    </a>
                    <a href="{{ route('catalog.index') }}" class="btn btn-light text-primary rounded-pill py-2">
                        Kembali ke Katalog
                    </a>
                    @if($order->status === 'pending_payment')
                    <form action="{{ route('orders.cancel', $order->id) }}" method="POST" class="d-grid mt-2" onsubmit="confirmAction(event, 'Yakin ingin membatalkan pesanan ini?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger rounded-pill py-2">
                            <i class="fas fa-times-circle me-1"></i> Batalkan Pesanan
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if($dynamicQris)
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var qrcode = new QRCode(document.getElementById("qrcode"), {
            text: "{{ $dynamicQris }}",
            width: 256,
            height: 256,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.M
        });
    });

    function copyQris() {
        var copyText = document.getElementById("qrisPayload");
        copyText.select();
        copyText.setSelectionRange(0, 99999); /* For mobile devices */
        navigator.clipboard.writeText(copyText.value);
        
        Swal.fire({
            icon: 'success',
            title: 'Disalin!',
            text: 'Payload QRIS berhasil disalin ke clipboard.',
            timer: 1500,
            showConfirmButton: false
        });
    }
</script>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Countdown Timer
        const countdownEl = document.getElementById('countdownTimer');
        if (countdownEl) {
            const expiresAt = new Date("{{ $order->expires_at->toIso8601String() }}").getTime();
            
            const timer = setInterval(() => {
                const now = new Date().getTime();
                const distance = expiresAt - now;
                
                if (distance < 0) {
                    clearInterval(timer);
                    countdownEl.innerText = "Waktu Habis";
                    // Reload to update status server-side if needed
                    setTimeout(() => location.reload(), 2000);
                } else {
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    countdownEl.innerText = `${minutes}m ${seconds}s`;
                }
            }, 1000);
        }

        // Realtime Status Polling
        const checkStatus = () => {
            fetch("{{ route('orders.status', $order->id) }}")
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'pending_payment') {
                        // Jika status berubah (paid/delivered/cancelled dll), redirect
                        window.location.href = "{{ route('orders.show', $order->id) }}";
                    }
                })
                .catch(err => console.error("Polling error:", err));
        };

        // Poll every 5 seconds
        setInterval(checkStatus, 5000);
    });
</script>
@endpush
