@extends('layouts.app')

@section('title', __('Tambah Promo Dashboard'))
@section('page_subtitle', __('Buat Kartu Promo Baru'))

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.promos.index') }}" class="btn btn-sm btn-link text-decoration-none text-secondary p-0 mb-2 d-inline-flex align-items-center gap-1.5 fw-semibold">
        <i class="fas fa-arrow-left"></i>
        <span>{{ __('Kembali ke Daftar Promo') }}</span>
    </a>
    <h1 class="m3-page-title mb-1">{{ __('Tambah Promo Dashboard') }}</h1>
    <p class="text-secondary small mb-0" style="font-size: 0.92rem;">{{ __('Isi informasi kartu promosi atau pengumuman yang akan ditampilkan pada dashboard.') }}</p>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-xl, 24px);">
            <form action="{{ route('admin.promos.store') }}" method="POST">
                @csrf

                {{-- Judul Promo --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Judul Promo / Info') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="promoTitleInput" class="form-control rounded-3 @error('title') is-invalid @enderror" placeholder="{{ __('Contoh: Diskon Lisensi s/d 30%') }}" value="{{ old('title') }}" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-3">
                    {{-- Badge Text --}}
                    <div class="col-md-7">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Teks Badge') }}</label>
                        <input type="text" name="badge_text" id="promoBadgeInput" class="form-control rounded-3 @error('badge_text') is-invalid @enderror" placeholder="{{ __('Contoh: PROMO SPESIAL') }}" value="{{ old('badge_text', 'PROMO SPESIAL') }}">
                        @error('badge_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Badge Color --}}
                    <div class="col-md-5">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Warna Badge') }}</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="badge_color" id="promoBadgeColorInput" class="form-control form-control-color rounded-3" value="{{ old('badge_color', '#0b57d0') }}" title="{{ __('Pilih Warna Badge') }}" style="width: 48px; height: 38px;">
                            <input type="text" id="promoBadgeColorText" class="form-control rounded-3 text-uppercase" value="{{ old('badge_color', '#0b57d0') }}" readonly style="font-size: 0.85rem;">
                        </div>
                    </div>
                </div>

                {{-- Deskripsi --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Deskripsi Singkat') }}</label>
                    <textarea name="description" id="promoDescInput" rows="3" class="form-control rounded-3 @error('description') is-invalid @enderror" placeholder="{{ __('Jelaskan promo atau informasi singkat dalam 1-2 kalimat...') }}">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-3">
                    {{-- FontAwesome Icon --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Ikon FontAwesome') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-tags" id="promoIconPreview"></i></span>
                            <input type="text" name="icon" id="promoIconInput" class="form-control rounded-end-3 @error('icon') is-invalid @enderror" placeholder="fas fa-tags" value="{{ old('icon', 'fas fa-tags') }}" required>
                        </div>
                        <small class="text-secondary" style="font-size: 0.72rem;">Contoh: <code>fas fa-tags</code>, <code>fas fa-shield-alt</code>, <code>fab fa-telegram</code>, <code>fas fa-headset</code></small>
                    </div>

                    {{-- Tema Gradien Latar Belakang --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Tema Latar M3') }} <span class="text-danger">*</span></label>
                        <select name="gradient_theme" id="promoThemeSelect" class="form-select rounded-3 @error('gradient_theme') is-invalid @enderror" required>
                            <option value="blue" {{ old('gradient_theme') === 'blue' ? 'selected' : '' }}>Blue (Default Android)</option>
                            <option value="green" {{ old('gradient_theme') === 'green' ? 'selected' : '' }}>Green (Keamanan / Proteksi)</option>
                            <option value="cyan" {{ old('gradient_theme') === 'cyan' ? 'selected' : '' }}>Cyan (Telegram / Notifikasi)</option>
                            <option value="purple" {{ old('gradient_theme') === 'purple' ? 'selected' : '' }}>Purple (Garansi / Layanan)</option>
                            <option value="amber" {{ old('gradient_theme') === 'amber' ? 'selected' : '' }}>Amber (Perhatian / Event)</option>
                            <option value="red" {{ old('gradient_theme') === 'red' ? 'selected' : '' }}>Red (Flash Sale / Mendesak)</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    {{-- Tombol Text --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Teks Tombol') }} <span class="text-danger">*</span></label>
                        <input type="text" name="button_text" id="promoBtnTextInput" class="form-control rounded-3 @error('button_text') is-invalid @enderror" placeholder="{{ __('Contoh: Klaim promo') }}" value="{{ old('button_text', 'Klaim promo') }}" required>
                        @error('button_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Tombol Link / URL --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Target URL / Tautan') }} <span class="text-danger">*</span></label>
                        <input type="text" name="button_url" class="form-control rounded-3 @error('button_url') is-invalid @enderror" placeholder="{{ __('Contoh: /catalog atau https://t.me/...') }}" value="{{ old('button_url', '/catalog') }}" required>
                        @error('button_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-4 align-items-center">
                    {{-- Urutan Tampilan --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Urutan Tampilan') }}</label>
                        <input type="number" name="order_index" class="form-control rounded-3" value="{{ old('order_index', 0) }}" min="0" max="999">
                        <small class="text-secondary" style="font-size: 0.72rem;">Angka lebih kecil tampil lebih dulu (misal: 1, 2, 3...)</small>
                    </div>

                    {{-- Status Aktif --}}
                    <div class="col-md-6 pt-md-3">
                        <div class="form-check form-switch fs-6">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActiveSwitch">{{ __('Aktifkan kartu promo ini langsung') }}</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top" style="border-color: var(--outline-variant) !important;">
                    <a href="{{ route('admin.promos.index') }}" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold">
                        {{ __('Batal') }}
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold">
                        <i class="fas fa-save me-1.5"></i>{{ __('Simpan Promo') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Live Card Preview --}}
    <div class="col-lg-4">
        <div class="sticky-top" style="top: 80px;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fas fa-magic text-primary"></i>
                <span class="fw-bold text-body" style="font-size: 0.95rem;">{{ __('Live Preview Card') }}</span>
            </div>
            
            <div class="m3-promo-card" id="liveCardPreview" style="background: linear-gradient(135deg, rgba(11, 87, 208, 0.08), var(--surface-container)); border: 1px solid var(--outline-variant); border-radius: var(--radius-xl, 20px); padding: 18px 20px;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge rounded-pill fw-bold" id="previewBadge" style="background: #0b57d0; color: #ffffff; font-size: 0.72rem; letter-spacing: 0.03em;">PROMO SPESIAL</span>
                    <i class="fas fa-tags text-primary opacity-50 fs-5" id="previewIcon"></i>
                </div>
                <h3 class="fw-bold text-body mb-1" id="previewTitle" style="font-size: 1rem; line-height: 1.35;">{{ __('Judul Promo') }}</h3>
                <p class="text-secondary small mb-3" id="previewDesc" style="font-size: 0.8125rem; line-height: 1.4;">{{ __('Deskripsi singkat kartu promosi Anda akan muncul di sini.') }}</p>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.8125rem;">
                    <span id="previewBtnText">{{ __('Klaim promo') }}</span>
                    <i class="fas fa-arrow-right" style="font-size: 0.7rem;"></i>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const titleInput = document.getElementById('promoTitleInput');
    const badgeInput = document.getElementById('promoBadgeInput');
    const badgeColorInput = document.getElementById('promoBadgeColorInput');
    const badgeColorText = document.getElementById('promoBadgeColorText');
    const descInput = document.getElementById('promoDescInput');
    const iconInput = document.getElementById('promoIconInput');
    const iconPreview = document.getElementById('promoIconPreview');
    const themeSelect = document.getElementById('promoThemeSelect');
    const btnTextInput = document.getElementById('promoBtnTextInput');

    const previewTitle = document.getElementById('previewTitle');
    const previewBadge = document.getElementById('previewBadge');
    const previewDesc = document.getElementById('previewDesc');
    const previewIcon = document.getElementById('previewIcon');
    const previewBtnText = document.getElementById('previewBtnText');
    const liveCard = document.getElementById('liveCardPreview');

    const themeGradients = {
        blue: 'linear-gradient(135deg, rgba(11, 87, 208, 0.08), var(--surface-container))',
        green: 'linear-gradient(135deg, rgba(56, 106, 33, 0.08), var(--surface-container))',
        cyan: 'linear-gradient(135deg, rgba(34, 158, 217, 0.1), var(--surface-container))',
        purple: 'linear-gradient(135deg, rgba(142, 68, 173, 0.08), var(--surface-container))',
        amber: 'linear-gradient(135deg, rgba(230, 81, 0, 0.08), var(--surface-container))',
        red: 'linear-gradient(135deg, rgba(179, 38, 30, 0.08), var(--surface-container))'
    };

    function updatePreview() {
        previewTitle.textContent = titleInput.value.trim() || 'Judul Promo';
        previewBadge.textContent = badgeInput.value.trim() || 'INFO';
        previewBadge.style.background = badgeColorInput.value;
        badgeColorText.value = badgeColorInput.value;
        previewDesc.textContent = descInput.value.trim() || 'Deskripsi singkat kartu promosi Anda akan muncul di sini.';
        previewBtnText.textContent = btnTextInput.value.trim() || 'Lihat Detail';

        const iconClass = iconInput.value.trim() || 'fas fa-tags';
        iconPreview.className = iconClass;
        previewIcon.className = iconClass + ' text-primary opacity-50 fs-5';

        const theme = themeSelect.value;
        if (themeGradients[theme]) {
            liveCard.style.background = themeGradients[theme];
        }
    }

    [titleInput, badgeInput, badgeColorInput, descInput, iconInput, themeSelect, btnTextInput].forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });

    updatePreview();
});
</script>
@endpush
