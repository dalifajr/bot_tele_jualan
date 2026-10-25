@extends('layouts.app')

@section('title', __('Admin: Kelola Rekber ') . $transaction->transaction_code)
@section('page_subtitle', __('Admin / Rekber / Detail'))

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('admin.escrow.index') }}" class="btn btn-sm btn-link text-muted p-0 text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Daftar Rekber') }}
            </a>
            <span class="text-muted">/</span>
            <span class="badge bg-{{ $transaction->status_badge }} rounded-pill px-3 py-1.5 fw-semibold">
                {{ $transaction->status_label }}
            </span>
            <code class="fw-bold text-primary">{{ $transaction->transaction_code }}</code>
        </div>
        <h4 class="fw-bold text-dark mb-1">{{ $transaction->title }}</h4>
        <p class="text-muted small mb-0">{{ $transaction->description ?: __('Tidak ada deskripsi tambahan.') }}</p>
    </div>
    <div class="col-12 col-lg-4 text-lg-end d-flex align-items-center justify-content-lg-end gap-2">
        <a href="{{ route('escrow.public.show', $transaction->public_token) }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="fas fa-external-link-alt me-1"></i> {{ __('Pratinjau Ruang Publik') }}
        </a>
    </div>
</div>

<div class="row g-4">
    {{-- Main Column: Detail & Aksi Admin --}}
    <div class="col-12 col-lg-7">
        {{-- Card 1: Data Finansial & Rekening --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-money-check-dollar text-primary"></i> {{ __('Data Finansial & Rekening Pencairan') }}
                </h6>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">{{ __('Nilai Barang') }}</small>
                        <strong class="text-dark fs-6">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">{{ __('Fee Platform') }}</small>
                        <strong class="text-info fs-6">Rp {{ number_format($transaction->fee_amount, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">{{ __('Masuk dari Pembeli') }}</small>
                        <strong class="text-primary fs-6">Rp {{ number_format($transaction->total_buyer_pay, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">{{ __('Keluar ke Penjual') }}</small>
                        <strong class="text-success fs-6">Rp {{ number_format($transaction->total_seller_receive, 0, ',', '.') }}</strong>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border">
                    <div class="small fw-bold text-dark mb-1">{{ __('Rekening Tujuan Pencairan Penjual:') }}</div>
                    <div class="small text-muted">
                        <strong>{{ __('Bank / E-Wallet:') }}</strong> {{ $transaction->seller_bank_name ?: '-' }} |
                        <strong>{{ __('No. Rekening:') }}</strong> <code>{{ $transaction->seller_account_number ?: '-' }}</code> |
                        <strong>{{ __('Atas Nama:') }}</strong> {{ $transaction->seller_account_holder ?: '-' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Aksi Admin - Verifikasi Deposit Masuk --}}
        @if($transaction->status === \App\Models\EscrowTransaction::STATUS_DEPOSIT_SUBMITTED)
            <div class="card border-0 shadow-sm border-start border-info border-4 mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-info mb-2 d-flex align-items-center gap-2">
                        <i class="fas fa-receipt"></i> {{ __('Tindakan Admin: Verifikasi Mutasi Deposit Masuk') }}
                    </h6>
                    <p class="small text-muted mb-3">
                        {{ __('Pembeli telah mengunggah bukti pembayaran sebesar') }} <strong>Rp {{ number_format($transaction->total_buyer_pay, 0, ',', '.') }}</strong>. {{ __('Silakan periksa mutasi rekening Anda sebelum memverifikasi.') }}
                    </p>

                    @if($transaction->deposit_proof)
                        <div class="mb-3">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($transaction->deposit_proof) }}" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3">
                                <i class="fas fa-eye me-1"></i> {{ __('Buka Bukti Transfer Pembeli') }}
                            </a>
                        </div>
                    @endif

                    <form action="{{ route('admin.escrow.verify-deposit', $transaction->transaction_code) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" onclick="return confirm('Apakah Anda yakin dana mutasi rekening sudah masuk dan sah?')">
                            <i class="fas fa-check-circle me-1.5"></i> {{ __('Verifikasi Sah & Amankan Dana') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Card 3: Aksi Admin - Pencairan Dana ke Penjual (Payout) --}}
        @if($transaction->status === \App\Models\EscrowTransaction::STATUS_WAITING_PAYOUT)
            <div class="card border-0 shadow-sm border-start border-warning border-4 mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-warning mb-2 d-flex align-items-center gap-2">
                        <i class="fas fa-money-bill-transfer"></i> {{ __('Tindakan Admin: Pencairan Dana (Payout) ke Penjual') }}
                    </h6>
                    <p class="small text-muted mb-3">
                        {{ __('Pembeli telah mengonfirmasi bahwa produk/akun sesuai. Silakan transfer dana bersih sebesar') }}
                        <strong class="text-success fs-6">Rp {{ number_format($transaction->total_seller_receive, 0, ',', '.') }}</strong>
                        {{ __('ke rekening penjual di atas, lalu unggah bukti transfer.') }}
                    </p>

                    <form action="{{ route('admin.escrow.payout', $transaction->transaction_code) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">{{ __('Unggah Bukti Transfer Keluar (Payout)') }} <span class="text-danger">*</span></label>
                            <input type="file" name="payout_proof" class="form-control rounded-3" accept="image/*,application/pdf" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">{{ __('Catatan Admin (Opsional)') }}</label>
                            <input type="text" name="admin_notes" class="form-control rounded-3" placeholder="{{ __('Nomor referensi mutasi bank atau catatan...') }}">
                        </div>
                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold shadow-sm" onclick="return confirm('Pastikan dana sudah benar-benar terkirim ke penjual sebelum menyimpan.')">
                            <i class="fas fa-paper-plane me-1.5"></i> {{ __('Konfirmasi Dana Telah Ditransfer') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Card 4: Aksi Admin - Arbitrase Sengketa --}}
        @if($transaction->status === \App\Models\EscrowTransaction::STATUS_DISPUTED)
            <div class="card border-0 shadow-sm border-start border-danger border-4 mb-4" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-danger mb-2 d-flex align-items-center gap-2">
                        <i class="fas fa-gavel"></i> {{ __('Tindakan Admin: Putusan Arbitrase Sengketa') }}
                    </h6>
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="small fw-bold text-danger mb-1">{{ __('Alasan Komplain:') }}</div>
                        <div class="small text-muted">{{ $transaction->dispute_reason }}</div>
                    </div>

                    <form action="{{ route('admin.escrow.resolve-dispute', $transaction->transaction_code) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">{{ __('Pilih Keputusan Arbitrase') }} <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="decision" id="dec_seller" value="release_to_seller" checked>
                                    <label class="btn btn-outline-success w-100 py-2 rounded-3 text-start" for="dec_seller">
                                        <div class="fw-bold small">{{ __('Menangkan Penjual') }}</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ __('Rilis dana ke penjual') }}</div>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="decision" id="dec_buyer" value="refund_to_buyer">
                                    <label class="btn btn-outline-danger w-100 py-2 rounded-3 text-start" for="dec_buyer">
                                        <div class="fw-bold small">{{ __('Menangkan Pembeli') }}</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">{{ __('Kembalikan dana (Refund)') }}</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">{{ __('Alasan / Catatan Putusan Admin') }} <span class="text-danger">*</span></label>
                            <textarea name="dispute_resolution" class="form-control rounded-3" rows="3" placeholder="{{ __('Tuliskan alasan keputusan berdasarkan bukti chat & validitas akun...') }}" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-semibold" onclick="return confirm('Apakah Anda yakin dengan keputusan arbitrase ini?')">
                            <i class="fas fa-gavel me-1.5"></i> {{ __('Terapkan Putusan Arbitrase') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Section Serah Terima Barang --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-box-archive text-secondary"></i> {{ __('Data Barang / Akun yang Diserahkan') }}
                </h6>
                @if($transaction->delivery_proof)
                    <div class="p-3 bg-light rounded-3 border font-monospace small" style="white-space: pre-wrap; word-break: break-all;">
                        {{ $transaction->delivery_proof }}
                    </div>
                @else
                    <p class="small text-muted mb-0">{{ __('Penjual belum menyerahkan data barang/akun.') }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Right Column: Audit Trail & Chat History --}}
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-comments text-primary me-1"></i> {{ __('Diskusi Ruang Rekber') }}</h6>
            </div>
            <div class="card-body p-3">
                <div class="overflow-y-auto mb-3 p-2 bg-light rounded-3" style="max-height: 350px; min-height: 180px;">
                    @forelse($transaction->messages as $msg)
                        <div class="mb-2 p-2.5 bg-white rounded-3 shadow-sm border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="small text-dark">{{ $msg->sender_name }}</strong>
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">{{ strtoupper($msg->sender_role) }}</span>
                            </div>
                            <p class="small text-muted mb-1 text-break">{{ $msg->message }}</p>
                            @if($msg->attachment_path)
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($msg->attachment_path) }}" target="_blank" class="small text-primary text-decoration-none">
                                    <i class="fas fa-paperclip me-1"></i> {{ __('Lampiran File') }}
                                </a>
                            @endif
                            <div class="text-end text-muted" style="font-size: 0.65rem;">{{ $msg->created_at->format('H:i') }}</div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">{{ __('Belum ada obrolan.') }}</div>
                    @endforelse
                </div>

                {{-- Admin Chat Input --}}
                <form action="{{ route('escrow.send-message', $transaction->transaction_code) }}" method="POST">
                    @csrf
                    <div class="input-group">
                        <input type="text" name="message" class="form-control rounded-start-pill" placeholder="{{ __('Kirim instruksi sebagai Admin...') }}" required>
                        <button type="submit" class="btn btn-primary rounded-end-pill px-3">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Timeline Card --}}
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-header bg-white border-bottom p-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-clock-rotate-left text-info me-1"></i> {{ __('Audit Trail Timeline') }}</h6>
            </div>
            <div class="card-body p-3">
                <div class="timeline ps-2">
                    @foreach($transaction->timelines as $t)
                        <div class="d-flex gap-3 mb-3">
                            <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary" style="width: 28px; height: 28px; min-width: 28px;">
                                <i class="fas fa-check" style="font-size: 0.6rem;"></i>
                            </div>
                            <div>
                                <div class="small text-dark">{{ $t->description }}</div>
                                <small class="text-muted" style="font-size: 0.7rem;">{{ $t->created_at->format('d M Y, H:i') }}</small>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
