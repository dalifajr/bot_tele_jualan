@extends('layouts.app')

@section('title', __('Firewall & Anti-Fraud Center'))
@section('page_subtitle', __('Manajemen terpusat blokir IP, subnet CIDR & ID Telegram'))

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Aturan Firewall Aktif') }}</small>
                <div class="fs-3 fw-bold text-primary mt-1">{{ $activeRulesCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Login Gagal (24 Jam)') }}</small>
                <div class="fs-3 fw-bold text-danger mt-1">{{ $failedLogins24h }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3.5">
                <small class="text-secondary fw-semibold">{{ __('Akun Ditangguhkan') }}</small>
                <div class="fs-3 fw-bold text-warning mt-1">{{ $suspendedUsersCount }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-shield-virus text-danger me-1.5"></i>{{ __('Tambah Aturan Blokir Baru') }}</h6>
                <form action="{{ route('admin.security.firewall.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Tipe Target') }}</label>
                        <select name="type" class="form-select" required>
                            <option value="ip">{{ __('Alamat IP Tunggal (IPv4/IPv6)') }}</option>
                            <option value="subnet">{{ __('Rentang Subnet CIDR (contoh: 103.10.20.0/24)') }}</option>
                            <option value="telegram_id">{{ __('ID Telegram') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Nilai Target') }}</label>
                        <input type="text" name="value" class="form-control font-monospace" placeholder="192.168.1.50 / 103.10.20.0/24" value="{{ old('value') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Durasi Blokir') }}</label>
                        <select name="duration_days" class="form-select">
                            <option value="1">{{ __('1 Hari') }}</option>
                            <option value="7" selected>{{ __('7 Hari') }}</option>
                            <option value="30">{{ __('30 Hari') }}</option>
                            <option value="365">{{ __('1 Tahun') }}</option>
                            <option value="0">{{ __('Permanen') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">{{ __('Alasan') }}</label>
                        <input type="text" name="reason" class="form-control" placeholder="{{ __('Brute-force / Spam / Scraper') }}" value="{{ old('reason') }}">
                    </div>
                    <button type="submit" class="btn btn-danger w-100 rounded-pill fw-bold">
                        <i class="fas fa-ban me-1.5"></i>{{ __('Terapkan Blokir Firewall') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-radar text-warning me-1.5"></i>{{ __('IP Gagal Login Terbanyak (7 Hari)') }}</h6>
                @forelse($topSuspiciousIps as $row)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color: var(--outline-variant) !important;">
                        <div>
                            <div class="font-monospace fw-semibold small text-body">{{ $row->ip_address }}</div>
                            <small class="text-danger">{{ $row->fail_count }}x {{ __('gagal') }}</small>
                        </div>
                        <form action="{{ route('admin.security.firewall.store') }}" method="POST" class="m-0">
                            @csrf
                            <input type="hidden" name="type" value="ip">
                            <input type="hidden" name="value" value="{{ $row->ip_address }}">
                            <input type="hidden" name="duration_days" value="7">
                            <input type="hidden" name="reason" value="{{ __('Anomali gagal login berulang') }}">
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">{{ __('Blokir') }}</button>
                        </form>
                    </div>
                @empty
                    <div class="text-muted small">{{ __('Tidak ada anomali IP dalam 7 hari terakhir.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-list-check text-primary me-1.5"></i>{{ __('Daftar Aturan Firewall') }}</h6>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th>{{ __('Target') }}</th>
                                <th>{{ __('Alasan') }}</th>
                                <th>{{ __('Berlaku Hingga') }}</th>
                                <th class="text-end">{{ __('Hit') }}</th>
                                <th class="text-end">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rules as $rule)
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-body text-uppercase me-1" style="font-size: 0.65rem;">{{ $rule->type }}</span>
                                        <span class="font-monospace fw-semibold small text-body">{{ $rule->value }}</span>
                                    </td>
                                    <td class="small text-secondary">{{ $rule->reason ?: '-' }}</td>
                                    <td class="small text-nowrap">
                                        {{ $rule->expires_at ? $rule->expires_at->format('d M Y H:i') : __('Permanen') }}
                                    </td>
                                    <td class="text-end small fw-bold">{{ $rule->hit_count }}</td>
                                    <td class="text-end">
                                        <form action="{{ route('admin.security.firewall.destroy', $rule->id) }}" method="POST" class="m-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4 small">{{ __('Belum ada aturan firewall tersimpan.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($rules->hasPages())
                    <div class="mt-3">{{ $rules->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
