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
                    </div>
                @else
                    <div class="alert alert-info text-center" role="alert">
                        <i class="fas fa-info-circle mb-2 fs-4"></i><br>
                        Silakan cek <strong>Bot Telegram</strong> Anda untuk instruksi pembayaran lengkap dan kode QRIS.
                    </div>
                @endif

                @if($snapToken)
                    <div class="text-center mb-3">
                        <span class="text-muted small d-block mb-2">— ATAU —</span>
                        <div class="d-grid gap-2">
                            <button id="pay-button" class="btn btn-success rounded-pill py-2 fw-bold">
                                <i class="fas fa-credit-card me-1"></i> Bayar Otomatis (Midtrans)
                            </button>
                        </div>
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
@if($snapToken)
<script src="{{ env('MIDTRANS_IS_PRODUCTION', false) ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" data-client-key="{{ env('MIDTRANS_CLIENT_KEY') }}"></script>
<script>
    document.getElementById('pay-button').onclick = function(){
        snap.pay('{{ $snapToken }}', {
            onSuccess: function(result){
                Swal.fire({
                    icon: 'success',
                    title: 'Pembayaran Berhasil!',
                    text: 'Pembayaran Anda telah sukses diverifikasi.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = "{{ route('orders.show', $order->id) }}";
                });
            },
            onPending: function(result){
                Swal.fire({
                    icon: 'info',
                    title: 'Menunggu Pembayaran',
                    text: 'Silakan selesaikan pembayaran sesuai petunjuk.',
                });
            },
            onError: function(result){
                Swal.fire({
                    icon: 'error',
                    title: 'Pembayaran Gagal',
                    text: 'Terjadi kesalahan saat memproses pembayaran.',
                });
            },
            onClose: function(){
                Swal.fire({
                    icon: 'warning',
                    title: 'Pembayaran Dibatalkan',
                    text: 'Anda menutup popup pembayaran sebelum selesai.',
                });
            }
        });
    };
</script>
@endif

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
