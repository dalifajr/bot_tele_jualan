@extends('layouts.app')

@section('title', __('Kelola Promo Dashboard'))
@section('page_subtitle', __('Pengaturan Kartu Promo & Info Dashboard'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="m3-page-title mb-1">{{ __('Kelola Promo Dashboard') }}</h1>
        <p class="text-secondary small mb-0" style="font-size: 0.92rem;">{{ __('Atur kartu promosi, pengumuman, dan informasi yang muncul pada carousel dashboard belanja.') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2">
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

<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: var(--radius-xl, 24px);">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light" style="background: var(--surface-container-high);">
                <tr class="text-secondary small">
                    <th class="ps-4 py-3" style="width: 60px;">#</th>
                    <th class="py-3">{{ __('Urutan') }}</th>
                    <th class="py-3">{{ __('Preview Card') }}</th>
                    <th class="py-3">{{ __('Badge & Judul') }}</th>
                    <th class="py-3">{{ __('Tombol & Link') }}</th>
                    <th class="py-3 text-center">{{ __('Status') }}</th>
                    <th class="pe-4 py-3 text-end" style="width: 140px;">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($promos as $promo)
                <tr>
                    <td class="ps-4 text-secondary small">{{ $loop->iteration }}</td>
                    <td>
                        <span class="badge rounded-pill bg-light text-dark border px-2.5 py-1">
                            #{{ $promo->order_index }}
                        </span>
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
                        <div class="fw-bold text-body" style="font-size: 0.9rem;">{{ $promo->title }}</div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="badge rounded-pill" style="background: {{ $promo->badge_color }}; color: #ffffff; font-size: 0.7rem;">
                                {{ $promo->badge_text }}
                            </span>
                            <small class="text-secondary"><i class="{{ $promo->icon }} me-1"></i>Tema: {{ ucfirst($promo->gradient_theme) }}</small>
                        </div>
                    </td>
                    <td>
                        <div class="fw-medium text-body" style="font-size: 0.85rem;">{{ $promo->button_text }}</div>
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
                    <td colspan="7" class="text-center py-5">
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

    @if($promos->hasPages())
    <div class="p-3 border-top" style="border-color: var(--outline-variant) !important;">
        {{ $promos->links() }}
    </div>
    @endif
</div>
@endsection
