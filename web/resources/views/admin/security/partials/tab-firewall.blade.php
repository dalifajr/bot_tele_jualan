{{-- Tab 4: Firewall & Aturan Blokir IP / Subnet CIDR / ID Telegram --}}
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-ban text-danger me-1.5"></i>{{ __('Tambah Aturan Blokir Baru') }}</h6>
                <form action="{{ route('admin.security.firewall.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="active_tab" value="firewall">
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

                <hr class="my-4" style="border-color: var(--outline-variant);">

                <div class="p-3 rounded-4 border" style="background: var(--surface-container-low); border-color: var(--outline-variant) !important;">
                    <div class="fw-bold small text-body mb-1">
                        <i class="fas fa-bolt text-danger me-1"></i>{{ __('Mitigasi Cepat Brute-Force') }}
                    </div>
                    <p class="text-muted mb-2" style="font-size: 0.75rem;">
                        {{ __('Blokir otomatis seluruh IP yang gagal login minimal 5 kali dalam 24 jam terakhir selama 7 hari.') }}
                    </p>
                    <form action="{{ route('admin.security.firewall.auto-block') }}" method="POST" class="m-0">
                        @csrf
                        <input type="hidden" name="active_tab" value="firewall">
                        <input type="hidden" name="threshold" value="5">
                        <input type="hidden" name="duration_days" value="7">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100 rounded-pill fw-semibold">
                            <i class="fas fa-bolt me-1"></i>{{ __('Auto-Blokir IP Agresif (≥5 Gagal)') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold text-body mb-0"><i class="fas fa-list-check text-primary me-1.5"></i>{{ __('Daftar Aturan Firewall') }}</h6>
                        <small class="text-muted">{{ __(':active aturan aktif · :hits total blokir tertahan', ['active' => $activeRulesCount, 'hits' => number_format($totalBlockedHits)]) }}</small>
                    </div>
                    <form action="{{ route('admin.security.firewall') }}" method="GET" class="d-flex flex-wrap gap-1.5 align-items-center">
                        <input type="hidden" name="tab" value="firewall">
                        <select name="fw_type" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="all" {{ ($fwTypeFilter ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('Semua Tipe') }}</option>
                            <option value="ip" {{ ($fwTypeFilter ?? 'all') === 'ip' ? 'selected' : '' }}>IP</option>
                            <option value="subnet" {{ ($fwTypeFilter ?? 'all') === 'subnet' ? 'selected' : '' }}>Subnet CIDR</option>
                            <option value="telegram_id" {{ ($fwTypeFilter ?? 'all') === 'telegram_id' ? 'selected' : '' }}>Telegram ID</option>
                        </select>
                        <input type="text" name="fw_search" class="form-control form-control-sm" style="width: 170px;" placeholder="{{ __('Cari target/alasan...') }}" value="{{ $fwSearch ?? '' }}">
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">{{ __('Filter') }}</button>
                        @if(($fwTypeFilter ?? 'all') !== 'all' || !empty($fwSearch))
                            <a href="{{ route('admin.security.firewall', ['tab' => 'firewall']) }}" class="btn btn-sm btn-light rounded-pill px-2.5" title="{{ __('Reset Filter') }}">
                                <i class="fas fa-undo"></i>
                            </a>
                        @endif
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th>{{ __('Target') }}</th>
                                <th>{{ __('Alasan') }}</th>
                                <th>{{ __('Status & Berlaku') }}</th>
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
                                        @if(!$rule->is_active)
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill">{{ __('Dijeda') }}</span>
                                        @elseif($rule->expires_at && $rule->expires_at->isPast())
                                            <span class="badge bg-warning-subtle text-warning rounded-pill">{{ __('Kedaluwarsa') }}</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger rounded-pill">{{ __('Aktif') }}</span>
                                        @endif
                                        <div class="text-muted mt-0.5" style="font-size: 0.72rem;">
                                            {{ $rule->expires_at ? $rule->expires_at->format('d M Y H:i') : __('Permanen') }}
                                        </div>
                                    </td>
                                    <td class="text-end small fw-bold">{{ number_format($rule->hit_count) }}</td>
                                    <td class="text-end text-nowrap">
                                        <div class="d-inline-flex gap-1">
                                            <form action="{{ route('admin.security.firewall.toggle', $rule->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <input type="hidden" name="active_tab" value="firewall">
                                                <button type="submit" class="btn btn-sm {{ $rule->is_active ? 'btn-outline-secondary' : 'btn-outline-primary' }} rounded-pill px-2.5" title="{{ $rule->is_active ? __('Jeda Aturan') : __('Aktifkan Aturan') }}">
                                                    <i class="fas {{ $rule->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.security.firewall.destroy', $rule->id) }}" method="POST" class="m-0">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="active_tab" value="firewall">
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2.5" title="{{ __('Hapus Aturan') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
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
                    <div class="mt-3">{{ $rules->appends(['tab' => 'firewall'])->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
