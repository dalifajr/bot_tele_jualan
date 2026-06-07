@extends('layouts.app')

@section('title', 'Dompet & Keuangan')
@section('page_subtitle', 'Dompet')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Dompet & Keuangan Seller</h4>
        <p class="text-muted mb-0">Pantau saldo wallet komisi hasil penjualan Anda dan ajukan penarikan dana payout.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success small py-2 mb-4"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger small py-2 mb-4"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

{{-- Sub Navigation Tabs --}}
<ul class="nav nav-pills mb-4 gap-2">
    <li class="nav-item">
        <a class="nav-link active rounded-pill px-4" href="{{ route('seller.finance.index') }}">
            <i class="fas fa-wallet me-2"></i>Dompet & Penarikan
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-secondary rounded-pill px-4 bg-light" href="{{ route('seller.bank-accounts.index') }}">
            <i class="fas fa-university me-2"></i>Konfigurasi Rekening
        </a>
    </li>
</ul>

<div class="row g-4">
    {{-- Wallet Balance & Withdrawal Form --}}
    <div class="col-12 col-lg-5">
        {{-- Wallet Balance Card --}}
        <div class="card border-0 shadow-sm text-white mb-4 overflow-hidden" style="border-radius: 20px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
            <div class="card-body p-4 position-relative">
                <div class="position-absolute end-0 bottom-0 text-white" style="font-size: 8rem; transform: translate(20px, 20px); opacity: 0.1; pointer-events: none; z-index: 0;">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="position-relative" style="z-index: 1;">
                    <p class="small text-white-50 fw-bold mb-2">SALDO DOMPET SAYA</p>
                    <h2 class="fw-bold mb-1">Rp {{ number_format($user->wallet_balance, 0, ',', '.') }}</h2>
                    <div class="small text-white-50 mt-1">Potongan Platform: <strong>{{ $user->platform_fee_percent }}%</strong> per penjualan</div>
                </div>
            </div>
        </div>

        {{-- Held Balance Card --}}
        <div class="card border-0 shadow-sm text-white mb-4 overflow-hidden" style="border-radius: 20px; background: linear-gradient(135deg, hsl(35, 90%, 50%) 0%, hsl(45, 95%, 55%) 100%);">
            <div class="card-body p-4 position-relative">
                <div class="position-absolute end-0 bottom-0 text-white" style="font-size: 8rem; transform: translate(20px, 20px); opacity: 0.15; pointer-events: none; z-index: 0;">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="position-relative" style="z-index: 1;">
                    <p class="small text-white-50 fw-bold mb-2">SALDO TERTAHAN (GARANSI)</p>
                    <h2 class="fw-bold mb-1">Rp {{ number_format($heldBalance, 0, ',', '.') }}</h2>
                    <div class="small text-white-50 mt-1">Saldo komisi bersih yang ditangguhkan karena produk memiliki garansi.</div>
                </div>
            </div>
        </div>

        {{-- Withdrawal Form Card --}}
        <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
            <h5 class="fw-bold mb-3"><i class="fas fa-money-bill-wave text-primary me-2"></i>Tarik Saldo (Withdrawal)</h5>
            
            <form action="{{ route('seller.finance.withdraw') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">Nominal Penarikan (Rp)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted">Rp</span>
                        <input type="number" name="amount" class="form-control" placeholder="100000" min="10000" max="{{ $user->wallet_balance }}" required>
                    </div>
                    <div class="form-text small">Minimal penarikan Rp 10.000. Maksimal sesuai saldo Anda.</div>
                </div>

                @if($bankAccounts->isEmpty())
                <div class="alert alert-warning small rounded-4 p-3 mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Anda belum menyimpan rekening bank. Silakan simpan rekening bank terlebih dahulu di menu <strong><a href="{{ route('seller.bank-accounts.index') }}">Konfigurasi Rekening</a></strong>.
                </div>
                @else
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">Pilih Rekening Tujuan Payout</label>
                    <select name="bank_account_id" class="form-select" required>
                        <option value="" disabled selected>-- Pilih Rekening Payout --</option>
                        @foreach($bankAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ strtoupper($acc->bank_name) }} — {{ $acc->account_number }} (a.n. {{ $acc->account_holder }})</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <button type="submit" class="btn btn-primary rounded-pill w-100 py-2.5 fw-bold" {{ $user->wallet_balance < 10000 || $bankAccounts->isEmpty() ? 'disabled' : '' }}>
                    <i class="fas fa-paper-plane me-1"></i> Ajukan Penarikan Dana
                </button>
                @if($user->wallet_balance < 10000)
                    <div class="text-danger small mt-2 text-center"><i class="fas fa-exclamation-circle me-1"></i> Saldo Anda belum mencukupi untuk melakukan penarikan.</div>
                @elseif($bankAccounts->isEmpty())
                    <div class="text-danger small mt-2 text-center"><i class="fas fa-exclamation-circle me-1"></i> Hubungkan rekening bank payout terlebih dahulu.</div>
                @endif
            </form>
        </div>
    </div>

    {{-- Withdrawal History --}}
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 20px;">
            <div class="card-header border-0 bg-white px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0"><i class="fas fa-history text-primary me-2"></i>Riwayat Pengajuan Penarikan</h5>
            </div>
            <div class="card-body p-0">
                @if($withdrawals->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom">
                                <th class="px-4 py-3 border-0">Tanggal</th>
                                <th class="py-3 border-0">Tujuan Rekening</th>
                                <th class="py-3 border-0">Jumlah</th>
                                <th class="py-3 border-0">Status</th>
                                <th class="py-3 border-0 text-end px-4">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($withdrawals as $withdrawal)
                            <tr>
                                <td class="px-4 text-muted small">{{ $withdrawal->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    <div class="fw-bold">{{ strtoupper($withdrawal->bank_name) }}</div>
                                    <div class="small text-muted text-truncate" style="max-width: 150px;">{{ $withdrawal->account_number }} a.n. {{ $withdrawal->account_holder }}</div>
                                </td>
                                <td class="fw-bold text-dark">
                                    Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}
                                </td>
                                <td>
                                    @if($withdrawal->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2.5 py-1 small">Menunggu</span>
                                    @elseif($withdrawal->status === 'approved')
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 small">Selesai</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 small">Ditolak</span>
                                    @endif
                                </td>
                                <td class="text-end px-4">
                                    @if($withdrawal->status === 'approved')
                                        <button class="btn btn-sm btn-light rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#viewProofModal{{ $withdrawal->id }}">
                                            Struk
                                        </button>
                                    @elseif($withdrawal->status === 'rejected')
                                        <button class="btn btn-sm btn-light rounded-pill px-3 text-danger" data-bs-toggle="modal" data-bs-target="#viewRejectionModal{{ $withdrawal->id }}">
                                            Alasan
                                        </button>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-top">
                    {{ $withdrawals->appends(request()->except('page'))->links() }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-history text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">Belum ada pengajuan pencairan dana.</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Held Funds Details --}}
        <div class="card border-0 shadow-sm overflow-hidden mt-4" style="border-radius: 20px;">
            <div class="card-header border-0 bg-white px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold m-0"><i class="fas fa-lock text-warning me-2"></i>Rincian Saldo Tertahan</h5>
            </div>
            <div class="card-body p-0">
                @if($heldFunds->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small border-bottom">
                                <th class="px-4 py-3 border-0">Order Ref</th>
                                <th class="py-3 border-0">Produk</th>
                                <th class="py-3 border-0">Komisi Bersih</th>
                                <th class="py-3 border-0">Estimasi Cair</th>
                                <th class="py-3 border-0 text-end px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($heldFunds as $fund)
                            <tr>
                                <td class="px-4 fw-bold text-primary">{{ $fund->order->order_ref ?? 'N/A' }}</td>
                                <td>{{ Str::limit($fund->product->name ?? 'N/A', 20) }}</td>
                                <td class="fw-bold text-success">
                                    Rp {{ number_format($fund->amount, 0, ',', '.') }}
                                </td>
                                <td class="text-muted small">
                                    {{ $fund->release_at ? $fund->release_at->format('d M Y H:i') : '-' }}
                                </td>
                                <td class="text-end px-4">
                                    @if($fund->status === 'held')
                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2.5 py-1 small">Tertahan</span>
                                    @elseif($fund->status === 'released')
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 small">Dicairkan</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 small">Batal</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-top">
                    {{ $heldFunds->appends(request()->except('held_page'))->links() }}
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-lock text-muted mb-3" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-0">Belum ada saldo tertahan aktif.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('modals')
@foreach($withdrawals as $withdrawal)
@if($withdrawal->status === 'approved')
{{-- View Proof Modal --}}
<div class="modal fade" id="viewProofModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Struk Bukti Transfer Payout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                @if($withdrawal->proof_image_path)
                    <img src="{{ asset($withdrawal->proof_image_path) }}" alt="Bukti Transfer Payout" class="img-fluid rounded-4 shadow-sm mb-3" style="max-height: 400px; object-fit: contain;">
                @else
                    <p class="text-muted">Bukti transfer belum diunggah oleh admin.</p>
                @endif
                <div class="bg-light p-3 rounded-4 text-start mt-2">
                    <div class="row small py-1">
                        <div class="col-4 text-muted">Bank Tujuan</div>
                        <div class="col-8 fw-bold">{{ strtoupper($withdrawal->bank_name) }} — {{ $withdrawal->account_number }}</div>
                    </div>
                    <div class="row small py-1">
                        <div class="col-4 text-muted">Atas Nama</div>
                        <div class="col-8 fw-bold">{{ $withdrawal->account_holder }}</div>
                    </div>
                    <div class="row small py-1">
                        <div class="col-4 text-muted">Jumlah Dana</div>
                        <div class="col-8 fw-bold text-success">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</div>
                    </div>
                    <div class="row small py-1">
                        <div class="col-4 text-muted">Tanggal Diproses</div>
                        <div class="col-8 fw-bold">{{ $withdrawal->processed_at ? $withdrawal->processed_at->format('d M Y H:i') : '-' }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@elseif($withdrawal->status === 'rejected')
{{-- View Rejection Modal --}}
<div class="modal fade" id="viewRejectionModal{{ $withdrawal->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-danger">Pengajuan Payout Ditolak</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-danger-subtle text-danger p-3 rounded-4 mb-3">
                    <div class="fw-bold mb-1 small">Alasan Penolakan dari Admin:</div>
                    <div class="lh-sm">{{ $withdrawal->rejection_reason ?? 'Tidak ada alasan penolakan yang dicantumkan.' }}</div>
                </div>
                <div class="bg-light p-3 rounded-4 text-start">
                    <div class="row small py-1">
                        <div class="col-4 text-muted">Jumlah</div>
                        <div class="col-8 fw-bold text-danger">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</div>
                    </div>
                    <div class="row small py-1">
                        <div class="col-4 text-muted">Tanggal Ditolak</div>
                        <div class="col-8 fw-bold">{{ $withdrawal->processed_at ? $withdrawal->processed_at->format('d M Y H:i') : '-' }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endif
@endforeach
@endpush
@endsection
