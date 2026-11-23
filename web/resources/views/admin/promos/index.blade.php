@extends('layouts.app')

@section('title', __('Kelola Promo Dashboard'))
@section('page_subtitle', __('Pengaturan Kartu Promo & Info Dashboard'))

@section('content')
<style>
    .promo-order-btn {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        border: 1px solid var(--outline-variant, var(--bs-border-color));
        background: var(--surface-container-lowest, var(--bs-body-bg));
        color: var(--on-surface, var(--bs-body-color));
        transition: background-color 160ms ease, border-color 160ms ease, transform 120ms ease, opacity 160ms ease;
    }
    .promo-order-btn:hover:not(:disabled) {
        background: var(--primary-container, rgba(var(--bs-primary-rgb), 0.14));
        border-color: var(--primary, var(--bs-primary));
        color: var(--on-primary-container, var(--bs-primary));
    }
    .promo-order-btn:active:not(:disabled) {
        transform: scale(0.94);
    }
    .promo-order-btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }
    .promo-drag-handle {
        cursor: grab;
        color: var(--on-surface-variant, var(--bs-secondary-color));
        padding: 0.25rem;
        border-radius: 8px;
        user-select: none;
    }
    .promo-drag-handle:active {
        cursor: grabbing;
    }
    tr.promo-row-dragging {
        opacity: 0.45;
    }
    tr.promo-row-drop-target > td {
        border-top: 2px solid var(--primary, var(--bs-primary)) !important;
    }
    .promo-card-flash {
        background-color: var(--primary-container, rgba(var(--bs-primary-rgb), 0.14)) !important;
        transition: background-color 420ms ease;
    }
    tr.promo-row-flash > td {
        background-color: var(--primary-container, rgba(var(--bs-primary-rgb), 0.12)) !important;
        transition: background-color 420ms ease;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="m3-page-title mb-1">{{ __('Kelola Promo Dashboard') }}</h1>
        <p class="text-secondary small mb-0" style="font-size: 0.92rem;">{{ __('Atur kartu promosi dan geser urutan tampilan langsung menggunakan tombol panah atas/bawah atau seret baris.') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span id="promoOrderStatusPill" class="badge bg-success-subtle text-success rounded-pill px-3 py-2 d-none" style="font-size: 0.78rem;">
            <i class="fas fa-check-circle me-1"></i><span>{{ __('Urutan tersimpan') }}</span>
        </span>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5" target="_blank" style="font-size: 0.85rem;">
            <i class="fas fa-eye"></i>
            <span>{{ __('Lihat di Dashboard') }}</span>
        </a>
        <a href="{{ route('admin.promos.create') }}" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.85rem;">
            <i class="fas fa-plus"></i>
            <span>{{ __('Tambah Promo Baru') }}</span>
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success rounded-4 mb-4 d-flex align-items-center gap-2">
    <i class="fas fa-check-circle fs-5"></i>
    <div>{{ session('success') }}</div>
</div>
@endif

<div class="card border overflow-hidden" style="border-radius: var(--radius-xl, 24px); border-color: var(--outline-variant, var(--bs-border-color)) !important;">
    {{-- Desktop Table View --}}
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle mb-0">
            <thead style="background: var(--surface-container-high);">
                <tr class="text-secondary small">
                    <th class="ps-4 py-3" style="width: 170px;">{{ __('Urutan') }}</th>
                    <th class="py-3">{{ __('Preview Card') }}</th>
                    <th class="py-3">{{ __('Badge & Judul') }}</th>
                    <th class="py-3">{{ __('Tombol & Link') }}</th>
                    <th class="py-3 text-center">{{ __('Status') }}</th>
                    <th class="pe-4 py-3 text-end" style="width: 120px;">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody id="promoSortableBody">
                @forelse($promos as $promo)
                <tr class="promo-order-row" data-promo-id="{{ $promo->id }}" draggable="true">
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="promo-drag-handle d-none d-sm-inline-flex" title="{{ __('Seret untuk memindahkan urutan') }}" aria-hidden="true">
                                <i class="fas fa-grip-vertical"></i>
                            </span>
                            <span class="badge rounded-pill px-2.5 py-1 fw-bold font-monospace promo-pos-badge"
                                  style="background: var(--surface-container, var(--bs-tertiary-bg)); color: var(--on-surface, var(--bs-body-color)); border: 1px solid var(--outline-variant, var(--bs-border-color)); min-width: 38px;">
                                #{{ $loop->iteration }}
                            </span>
                            <div class="d-inline-flex align-items-center gap-1">
                                <button type="button"
                                        class="promo-order-btn btn-move-up"
                                        onclick="movePromoRow({{ $promo->id }}, 'up')"
                                        title="{{ __('Geser ke Atas') }}"
                                        aria-label="{{ __('Geser ke Atas') }}"
                                        {{ $loop->first ? 'disabled' : '' }}>
                                    <i class="fas fa-arrow-up" style="font-size: 0.78rem;"></i>
                                </button>
                                <button type="button"
                                        class="promo-order-btn btn-move-down"
                                        onclick="movePromoRow({{ $promo->id }}, 'down')"
                                        title="{{ __('Geser ke Bawah') }}"
                                        aria-label="{{ __('Geser ke Bawah') }}"
                                        {{ $loop->last ? 'disabled' : '' }}>
                                    <i class="fas fa-arrow-down" style="font-size: 0.78rem;"></i>
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="rounded-3 text-start position-relative overflow-hidden d-flex" style="width: 240px; font-size: 0.75rem; background: {{ $promo->gradient_style }}; border: 1px solid var(--outline-variant); min-height: 70px;">
                            @if($promo->image)
                                <div class="position-absolute end-0 top-0 bottom-0" style="width: 100px; pointer-events: none; z-index: 1;">
                                    <img src="{{ $promo->image_url }}" alt="{{ $promo->title }}" style="width: 100%; height: 100%; object-fit: cover; object-position: right center;">
                                    <div style="position: absolute; inset: 0; background: linear-gradient(to left, rgba({{ $promo->theme_rgb }}, 0.05) 0%, rgba({{ $promo->theme_rgb }}, 0.5) 45%, var(--surface-container) 95%); pointer-events: none; z-index: 2;"></div>
                                </div>
                            @endif
                            <div class="p-2 w-100 overflow-hidden position-relative" style="z-index: 3;">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="badge rounded-pill" style="background: {{ $promo->badge_color }}; color: #ffffff; font-size: 0.65rem;">
                                        {{ $promo->badge_text ?: 'INFO' }}
                                    </span>
                                    <i class="{{ $promo->icon }} opacity-50"></i>
                                </div>
                                <div class="fw-bold text-truncate text-body mb-0.5" style="max-width: 150px;">{{ $promo->title }}</div>
                                <div class="text-secondary small text-truncate" style="max-width: 150px;">{{ $promo->description }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold text-body" style="font-size: 0.9rem;">{{ __($promo->title) }}</div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="badge rounded-pill" style="background: {{ $promo->badge_color }}; color: #ffffff; font-size: 0.7rem;">
                                {{ __($promo->badge_text) }}
                            </span>
                            <small class="text-secondary"><i class="{{ $promo->icon }} me-1"></i>{{ __('Tema:') }} {{ ucfirst($promo->gradient_theme) }}</small>
                        </div>
                    </td>
                    <td>
                        <div class="fw-medium text-body" style="font-size: 0.85rem;">{{ __($promo->button_text) }}</div>
                        <small class="text-secondary text-truncate d-block" style="max-width: 200px;">{{ $promo->button_url }}</small>
                    </td>
                    <td class="text-center">
                        <form action="{{ route('admin.promos.toggle', $promo) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm rounded-pill px-3 py-1 fw-semibold border-0 {{ $promo->is_active ? 'btn-success-subtle text-success' : 'btn-secondary-subtle text-secondary' }}" title="{{ __('Klik untuk ubah status') }}">
                                <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i>
                                {{ $promo->is_active ? __('Aktif') : __('Nonaktif') }}
                            </button>
                        </form>
                    </td>
                    <td class="pe-4 text-end">
                        <div class="d-flex align-items-center justify-content-end gap-1">
                            <a href="{{ route('admin.promos.edit', $promo) }}" class="btn btn-sm btn-icon btn-light rounded-circle text-primary" title="{{ __('Edit') }}" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fas fa-pen" style="font-size: 0.8rem;"></i>
                            </a>
                            <form action="{{ route('admin.promos.destroy', $promo) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Hapus kartu promo ini secara permanen?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon btn-light rounded-circle text-danger" title="{{ __('Hapus') }}" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-trash" style="font-size: 0.8rem;"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="d-flex flex-column align-items-center justify-content-center">
                            <i class="fas fa-bullhorn text-secondary opacity-50 mb-3" style="font-size: 2.5rem;"></i>
                            <div class="fw-bold text-body mb-1">{{ __('Belum Ada Kartu Promo') }}</div>
                            <p class="text-secondary small mb-3">{{ __('Tambahkan kartu promosi atau pengumuman pertama Anda untuk ditampilkan di dashboard.') }}</p>
                            <a href="{{ route('admin.promos.create') }}" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold btn-sm">
                                <i class="fas fa-plus me-1"></i>{{ __('Tambah Promo Baru') }}
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Stacked Card List (< 768px) dengan tombol reorder sinkron --}}
    <div id="promoSortableMobile" class="d-md-none p-3 d-flex flex-column gap-3">
        @forelse($promos as $promo)
        <div class="m3-mobile-item-card promo-mobile-card p-3" data-promo-id="{{ $promo->id }}">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-1.5">
                    <span class="badge rounded-pill px-2.5 py-1 fw-bold font-monospace promo-pos-badge"
                          style="background: var(--surface-container-high); color: var(--on-surface); border: 1px solid var(--outline-variant);">
                        #{{ $loop->iteration }}
                    </span>
                    <button type="button"
                            class="promo-order-btn btn-move-up"
                            style="width: 38px; height: 38px;"
                            onclick="movePromoRow({{ $promo->id }}, 'up')"
                            title="{{ __('Geser ke Atas') }}"
                            aria-label="{{ __('Geser ke Atas') }}"
                            {{ $loop->first ? 'disabled' : '' }}>
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <button type="button"
                            class="promo-order-btn btn-move-down"
                            style="width: 38px; height: 38px;"
                            onclick="movePromoRow({{ $promo->id }}, 'down')"
                            title="{{ __('Geser ke Bawah') }}"
                            aria-label="{{ __('Geser ke Bawah') }}"
                            {{ $loop->last ? 'disabled' : '' }}>
                        <i class="fas fa-arrow-down"></i>
                    </button>
                </div>
                <form action="{{ route('admin.promos.toggle', $promo) }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm rounded-pill px-3 py-1 fw-semibold border-0 {{ $promo->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                        <i class="fas fa-circle me-1" style="font-size: 0.55rem;"></i>
                        {{ $promo->is_active ? __('Aktif') : __('Nonaktif') }}
                    </button>
                </form>
            </div>

            {{-- Compact Promo Visual Preview --}}
            <div class="rounded-3 position-relative overflow-hidden d-flex mb-2.5" style="background: {{ $promo->gradient_style }}; border: 1px solid var(--outline-variant); min-height: 76px;">
                @if($promo->image)
                    <div class="position-absolute end-0 top-0 bottom-0" style="width: 96px; pointer-events: none; z-index: 1;">
                        <img src="{{ $promo->image_url }}" alt="{{ $promo->title }}" style="width: 100%; height: 100%; object-fit: cover; object-position: right center;">
                        <div style="position: absolute; inset: 0; background: linear-gradient(to left, rgba({{ $promo->theme_rgb }}, 0.05) 0%, rgba({{ $promo->theme_rgb }}, 0.5) 45%, var(--surface-container) 95%); pointer-events: none; z-index: 2;"></div>
                    </div>
                @endif
                <div class="p-2.5 w-100 overflow-hidden position-relative" style="z-index: 3;">
                    <span class="badge rounded-pill mb-1" style="background: {{ $promo->badge_color }}; color: #ffffff; font-size: 0.66rem;">
                        {{ __($promo->badge_text ?: 'INFO') }}
                    </span>
                    <div class="fw-bold text-body small text-truncate" style="max-width: 75%;">{{ __($promo->title) }}</div>
                    <div class="text-secondary text-truncate" style="font-size: 0.74rem; max-width: 75%;">{{ $promo->description }}</div>
                </div>
            </div>

            <div class="small text-secondary mb-2.5">
                <div><i class="{{ $promo->icon }} me-1"></i><strong>{{ __($promo->button_text) }}</strong></div>
                <div class="font-monospace text-truncate" style="font-size: 0.72rem;">{{ $promo->button_url }}</div>
            </div>

            <div class="d-flex gap-2 pt-2 border-top" style="border-color: var(--outline-variant) !important;">
                <a href="{{ route('admin.promos.edit', $promo) }}" class="btn btn-sm btn-tonal rounded-pill flex-grow-1 fw-semibold d-inline-flex align-items-center justify-content-center gap-1" style="min-height: 40px;">
                    <i class="fas fa-pen"></i><span>{{ __('Edit Promo') }}</span>
                </a>
                <form action="{{ route('admin.promos.destroy', $promo) }}" method="POST" class="m-0" onsubmit="return confirm('{{ __('Hapus kartu promo ini secara permanen?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" style="min-height: 40px;" title="{{ __('Hapus') }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        @empty
        <div class="text-center py-4">
            <i class="fas fa-bullhorn text-secondary opacity-50 mb-3" style="font-size: 2.5rem;"></i>
            <div class="fw-bold text-body mb-1">{{ __('Belum Ada Kartu Promo') }}</div>
            <p class="text-secondary small mb-3">{{ __('Tambahkan kartu promosi atau pengumuman pertama Anda untuk ditampilkan di dashboard.') }}</p>
            <a href="{{ route('admin.promos.create') }}" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold btn-sm">
                <i class="fas fa-plus me-1"></i>{{ __('Tambah Promo Baru') }}
            </a>
        </div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const tbody = document.getElementById('promoSortableBody');
    const mobileList = document.getElementById('promoSortableMobile');
    const statusPill = document.getElementById('promoOrderStatusPill');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const reorderUrl = @json(route('admin.promos.reorder'));
    let isSavingOrder = false;
    let statusTimer = null;

    function showStatus(text, isError = false) {
        if (!statusPill) return;
        clearTimeout(statusTimer);
        statusPill.classList.remove('d-none', 'bg-success-subtle', 'text-success', 'bg-danger-subtle', 'text-danger');
        if (isError) {
            statusPill.classList.add('bg-danger-subtle', 'text-danger');
            statusPill.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i><span>${text}</span>`;
        } else {
            statusPill.classList.add('bg-success-subtle', 'text-success');
            statusPill.innerHTML = `<i class="fas fa-check-circle me-1"></i><span>${text}</span>`;
        }
        statusTimer = setTimeout(() => {
            statusPill.classList.add('d-none');
        }, 2200);
    }

    function syncRowNumbersAndButtons() {
        const updateList = (container, selector) => {
            if (!container) return;
            const items = Array.from(container.querySelectorAll(selector));
            items.forEach((item, idx) => {
                const badge = item.querySelector('.promo-pos-badge');
                if (badge) badge.textContent = '#' + (idx + 1);
                const upBtn = item.querySelector('.btn-move-up');
                const downBtn = item.querySelector('.btn-move-down');
                if (upBtn) upBtn.disabled = isSavingOrder || idx === 0;
                if (downBtn) downBtn.disabled = isSavingOrder || idx === items.length - 1;
            });
        };
        updateList(tbody, 'tr.promo-order-row');
        updateList(mobileList, '.promo-mobile-card');
    }

    function flashRow(row, cls = 'promo-row-flash') {
        if (!row) return;
        row.classList.remove(cls);
        void row.offsetWidth;
        row.classList.add(cls);
        setTimeout(() => row.classList.remove(cls), 550);
    }

    function swapInContainer(container, selector, promoId, direction) {
        if (!container) return null;
        const el = container.querySelector(`${selector}[data-promo-id="${promoId}"]`);
        if (!el) return null;
        const sibling = direction === 'up' ? el.previousElementSibling : el.nextElementSibling;
        if (!sibling || !sibling.matches(selector)) return null;
        if (direction === 'up') {
            container.insertBefore(el, sibling);
        } else {
            container.insertBefore(sibling, el);
        }
        return { el, sibling };
    }

    window.movePromoRow = async function (promoId, direction) {
        if (isSavingOrder || !tbody) return;
        const row = tbody.querySelector(`tr.promo-order-row[data-promo-id="${promoId}"]`);
        if (!row) return;

        const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling || !sibling.classList.contains('promo-order-row')) return;

        isSavingOrder = true;
        syncRowNumbersAndButtons();

        // Optimistic DOM move pada desktop & mobile sekaligus
        const dSwap = swapInContainer(tbody, 'tr.promo-order-row', promoId, direction);
        const mSwap = swapInContainer(mobileList, '.promo-mobile-card', promoId, direction);
        if (dSwap) flashRow(dSwap.el, 'promo-row-flash');
        if (mSwap) flashRow(mSwap.el, 'promo-card-flash');

        try {
            const res = await fetch(`/admin/promos/${promoId}/move`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ direction })
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                throw new Error(data.message || '{{ __('Gagal memperbarui urutan promo.') }}');
            }
            showStatus('{{ __('Urutan tersimpan') }}');
        } catch (err) {
            const revDir = direction === 'up' ? 'down' : 'up';
            swapInContainer(tbody, 'tr.promo-order-row', promoId, revDir);
            swapInContainer(mobileList, '.promo-mobile-card', promoId, revDir);
            showStatus(err.message || '{{ __('Gagal menyimpan urutan') }}', true);
        } finally {
            isSavingOrder = false;
            syncRowNumbersAndButtons();
        }
    };

    // Optional Desktop HTML5 Drag-and-Drop Reorder
    let draggedRow = null;
    let orderBeforeDrag = [];

    if (tbody) {
        tbody.addEventListener('dragstart', (e) => {
            const row = e.target.closest('tr.promo-order-row');
            if (!row || isSavingOrder) {
                e.preventDefault();
                return;
            }
            draggedRow = row;
            orderBeforeDrag = Array.from(tbody.querySelectorAll('tr.promo-order-row'));
            row.classList.add('promo-row-dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', row.dataset.promoId || '');
            }
        });

        tbody.addEventListener('dragover', (e) => {
            if (!draggedRow) return;
            e.preventDefault();
            const targetRow = e.target.closest('tr.promo-order-row');
            tbody.querySelectorAll('.promo-row-drop-target').forEach(el => el.classList.remove('promo-row-drop-target'));
            if (targetRow && targetRow !== draggedRow) {
                targetRow.classList.add('promo-row-drop-target');
            }
        });

        tbody.addEventListener('drop', async (e) => {
            if (!draggedRow) return;
            e.preventDefault();
            const targetRow = e.target.closest('tr.promo-order-row');
            tbody.querySelectorAll('.promo-row-drop-target').forEach(el => el.classList.remove('promo-row-drop-target'));
            if (!targetRow || targetRow === draggedRow) return;

            const rows = Array.from(tbody.querySelectorAll('tr.promo-order-row'));
            const fromIdx = rows.indexOf(draggedRow);
            const toIdx = rows.indexOf(targetRow);
            if (fromIdx < toIdx) {
                tbody.insertBefore(draggedRow, targetRow.nextSibling);
            } else {
                tbody.insertBefore(draggedRow, targetRow);
            }

            const orderedIds = Array.from(tbody.querySelectorAll('tr.promo-order-row'))
                .map(r => parseInt(r.dataset.promoId, 10))
                .filter(Number.isFinite);

            isSavingOrder = true;
            syncRowNumbersAndButtons();
            flashRow(draggedRow);

            try {
                const res = await fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ ordered_ids: orderedIds })
                });
                const data = await res.json();
                if (!res.ok || data.status !== 'success') {
                    throw new Error(data.message || '{{ __('Gagal menyimpan urutan promo.') }}');
                }
                showStatus('{{ __('Urutan tersimpan') }}');
            } catch (err) {
                orderBeforeDrag.forEach(r => tbody.appendChild(r));
                showStatus(err.message || '{{ __('Gagal menyimpan urutan') }}', true);
            } finally {
                isSavingOrder = false;
                syncRowNumbersAndButtons();
            }
        });

        tbody.addEventListener('dragend', () => {
            if (draggedRow) draggedRow.classList.remove('promo-row-dragging');
            tbody.querySelectorAll('.promo-row-drop-target').forEach(el => el.classList.remove('promo-row-drop-target'));
            draggedRow = null;
        });
    }
})();
</script>
@endpush
