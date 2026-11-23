{{-- Tab 4: Firewall & Aturan Blokir IP / Subnet CIDR / ID Telegram --}}
<div class="row g-4">
    {{-- Desktop Inline Form --}}
    <div class="col-lg-4 d-none d-lg-block">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold text-body mb-3"><i class="fas fa-ban text-danger me-1.5"></i>{{ __('Tambah Aturan Blokir Baru') }}</h6>
                @include('admin.security.partials.firewall_rule_form', ['idPrefix' => 'hub_desktop'])

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

    <div class="col-12 col-lg-8">
        <div class="card border-0 rounded-4 shadow-sm h-100">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex align-items-center justify-content-between flex-grow-1 flex-lg-grow-0 gap-2">
                        <div>
                            <h6 class="fw-bold text-body mb-0"><i class="fas fa-list-check text-primary me-1.5"></i>{{ __('Daftar Aturan Firewall') }}</h6>
                            <small class="text-muted">{{ __(':active aturan aktif · :hits total blokir tertahan', ['active' => $activeRulesCount, 'hits' => number_format($totalBlockedHits)]) }}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger rounded-pill px-3 fw-semibold d-lg-none d-inline-flex align-items-center gap-1 flex-shrink-0" style="min-height: 38px;" data-bs-toggle="modal" data-bs-target="#addFirewallRuleModal">
                            <i class="fas fa-plus"></i><span>{{ __('Blokir Baru') }}</span>
                        </button>
                    </div>
                    <form action="{{ route('admin.security.firewall') }}" method="GET" class="d-flex flex-wrap gap-1.5 align-items-center w-100 w-lg-auto">
                        <input type="hidden" name="tab" value="firewall">
                        <select name="fw_type" class="form-select form-select-sm flex-grow-1 flex-sm-grow-0" style="width: auto;" onchange="this.form.submit()">
                            <option value="all" {{ ($fwTypeFilter ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('Semua Tipe') }}</option>
                            <option value="ip" {{ ($fwTypeFilter ?? 'all') === 'ip' ? 'selected' : '' }}>IP</option>
                            <option value="subnet" {{ ($fwTypeFilter ?? 'all') === 'subnet' ? 'selected' : '' }}>Subnet CIDR</option>
                            <option value="telegram_id" {{ ($fwTypeFilter ?? 'all') === 'telegram_id' ? 'selected' : '' }}>Telegram ID</option>
                        </select>
                        <input type="text" name="fw_search" class="form-control form-control-sm flex-grow-1" style="min-width: 140px; max-width: 220px;" placeholder="{{ __('Cari target/alasan...') }}" value="{{ $fwSearch ?? '' }}">
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3">{{ __('Filter') }}</button>
                        @if(($fwTypeFilter ?? 'all') !== 'all' || !empty($fwSearch))
                            <a href="{{ route('admin.security.firewall', ['tab' => 'firewall']) }}" class="btn btn-sm btn-light rounded-pill px-2.5" title="{{ __('Reset Filter') }}">
                                <i class="fas fa-undo"></i>
                            </a>
                        @endif
                    </form>
                </div>

                {{-- Desktop Table --}}
                <div class="table-responsive d-none d-md-block">
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
                                    <td class="text-end small fw-bold m3-tabular-nums">{{ number_format($rule->hit_count) }}</td>
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

                {{-- Mobile Stacked Cards (< 768px) --}}
                <div class="d-md-none d-flex flex-column gap-2.5">
                    @forelse($rules as $rule)
                        <div class="m3-mobile-item-card p-3">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1.5">
                                <div class="min-w-0">
                                    <span class="badge bg-secondary-subtle text-body text-uppercase me-1" style="font-size: 0.65rem;">{{ $rule->type }}</span>
                                    <span class="font-monospace fw-bold small text-body text-break">{{ $rule->value }}</span>
                                </div>
                                @if(!$rule->is_active)
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill flex-shrink-0">{{ __('Dijeda') }}</span>
                                @elseif($rule->expires_at && $rule->expires_at->isPast())
                                    <span class="badge bg-warning-subtle text-warning rounded-pill flex-shrink-0">{{ __('Kedaluwarsa') }}</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger rounded-pill flex-shrink-0">{{ __('Aktif') }}</span>
                                @endif
                            </div>
                            <div class="small text-secondary mb-2">{{ $rule->reason ?: __('Tanpa catatan alasan') }}</div>
                            <div class="d-flex justify-content-between align-items-center small text-muted mb-2 m3-tabular-nums">
                                <span><i class="far fa-clock me-1"></i>{{ $rule->expires_at ? $rule->expires_at->format('d M Y H:i') : __('Permanen') }}</span>
                                <span class="fw-bold text-body">{{ number_format($rule->hit_count) }} hit</span>
                            </div>
                            <div class="d-flex gap-2 pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                                <form action="{{ route('admin.security.firewall.toggle', $rule->id) }}" method="POST" class="flex-grow-1 m-0">
                                    @csrf
                                    <input type="hidden" name="active_tab" value="firewall">
                                    <button type="submit" class="btn btn-sm {{ $rule->is_active ? 'btn-outline-secondary' : 'btn-outline-primary' }} rounded-pill w-100 fw-semibold" style="min-height: 40px;">
                                        <i class="fas {{ $rule->is_active ? 'fa-pause' : 'fa-play' }} me-1"></i>{{ $rule->is_active ? __('Jeda') : __('Aktifkan') }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.security.firewall.destroy', $rule->id) }}" method="POST" class="m-0">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="active_tab" value="firewall">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" style="min-height: 40px;" title="{{ __('Hapus Aturan') }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4 small">{{ __('Belum ada aturan firewall tersimpan.') }}</div>
                    @endforelse
                </div>

                @if($rules->hasPages())
                    <div class="mt-3">{{ $rules->appends(['tab' => 'firewall'])->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('modals')
{{-- Modal Tambah Aturan Blokir Baru (M3 Bottom Sheet pada Smartphone) --}}
<div class="modal fade" id="addFirewallRuleModal" tabindex="-1" aria-labelledby="addFirewallRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered m3-sheet-modal">
        <div class="modal-content border-0 shadow-lg">
            <div class="m3-sheet-handle d-md-none"></div>
            <div class="modal-header border-0 pb-0 px-4 pt-3">
                <h5 class="modal-title fw-bold text-body" id="addFirewallRuleModalLabel">
                    <i class="fas fa-ban text-danger me-1.5"></i>{{ __('Tambah Aturan Blokir Baru') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Tutup') }}"></button>
            </div>
            <div class="modal-body p-4">
                @include('admin.security.partials.firewall_rule_form', ['idPrefix' => 'hub_modal'])
            </div>
        </div>
    </div>
</div>
@endpush
