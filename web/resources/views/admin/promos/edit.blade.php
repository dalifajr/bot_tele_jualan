@extends('layouts.app')

@section('title', __('Edit Promo Dashboard'))
@section('page_subtitle', __('Edit Kartu Promo'))

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.promos.index') }}" class="btn btn-sm btn-link text-decoration-none text-secondary p-0 mb-2 d-inline-flex align-items-center gap-1.5 fw-semibold">
        <i class="fas fa-arrow-left"></i>
        <span>{{ __('Kembali ke Daftar Promo') }}</span>
    </a>
    <h1 class="m3-page-title mb-1">{{ __('Edit Promo Dashboard') }}</h1>
    <p class="text-secondary small mb-0" style="font-size: 0.92rem;">{{ __('Perbarui data dan tampilan kartu promosi dashboard.') }}</p>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm p-4" style="border-radius: var(--radius-xl, 24px);">
            <form action="{{ route('admin.promos.update', $promo) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- Judul Promo --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Judul Promo / Info') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="promoTitleInput" class="form-control rounded-3 @error('title') is-invalid @enderror" placeholder="{{ __('Contoh: Diskon Lisensi s/d 30%') }}" value="{{ old('title', $promo->title) }}" required>
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Foto Promo (Opsional) --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Foto / Banner Promo (Opsional)') }}</label>
                    
                    @if($promo->image)
                        <div class="d-flex align-items-center gap-3 p-2.5 rounded-3 border bg-light mb-2" id="currentImageContainer">
                            <img src="{{ $promo->image_url }}" alt="{{ $promo->title }}" class="rounded-2" style="width: 70px; height: 50px; object-fit: cover;">
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="small fw-semibold text-truncate">{{ basename($promo->image) }}</div>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="remove_image" id="removeImageCheckbox" value="1">
                                    <label class="form-check-label small text-danger fw-semibold" for="removeImageCheckbox">
                                        <i class="fas fa-trash-alt me-1"></i>{{ __('Hapus foto promo ini') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif

                    <input type="file" name="image" id="promoImageInput" class="form-control rounded-3 @error('image') is-invalid @enderror" accept="image/*">
                    <small class="text-secondary" style="font-size: 0.72rem;">{{ __('Pilih foto baru untuk mengganti gambar saat ini. Gambar akan tampil di sebelah kiri kartu dengan gradien transisi lebar yang halus.') }}</small>
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-3">
                    {{-- Badge Text --}}
                    <div class="col-md-7">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Teks Badge') }}</label>
                        <input type="text" name="badge_text" id="promoBadgeInput" class="form-control rounded-3 @error('badge_text') is-invalid @enderror" placeholder="{{ __('Contoh: PROMO SPESIAL') }}" value="{{ old('badge_text', $promo->badge_text) }}">
                        @error('badge_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Badge Color --}}
                    <div class="col-md-5">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Warna Badge') }}</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="badge_color" id="promoBadgeColorInput" class="form-control form-control-color rounded-3" value="{{ old('badge_color', $promo->badge_color) }}" title="{{ __('Pilih Warna Badge') }}" style="width: 48px; height: 38px;">
                            <input type="text" id="promoBadgeColorText" class="form-control rounded-3 text-uppercase" value="{{ old('badge_color', $promo->badge_color) }}" readonly style="font-size: 0.85rem;">
                        </div>
                    </div>
                </div>

                {{-- Deskripsi --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Deskripsi Singkat') }}</label>
                    <textarea name="description" id="promoDescInput" rows="3" class="form-control rounded-3 @error('description') is-invalid @enderror" placeholder="{{ __('Jelaskan promo atau informasi singkat dalam 1-2 kalimat...') }}">{{ old('description', $promo->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row g-3 mb-3">
                    {{-- FontAwesome Icon --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Ikon FontAwesome') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="{{ $promo->icon }}" id="promoIconPreview"></i></span>
                            <input type="text" name="icon" id="promoIconInput" class="form-control rounded-end-3 @error('icon') is-invalid @enderror" placeholder="fas fa-tags" value="{{ old('icon', $promo->icon) }}" required>
                        </div>
                        <small class="text-secondary" style="font-size: 0.72rem;">Contoh: <code>fas fa-tags</code>, <code>fas fa-shield-alt</code>, <code>fab fa-telegram</code>, <code>fas fa-headset</code></small>
                    </div>

                    {{-- Tema Gradien Latar Belakang --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Tema Latar M3') }} <span class="text-danger">*</span></label>
                        <select name="gradient_theme" id="promoThemeSelect" class="form-select rounded-3 @error('gradient_theme') is-invalid @enderror" required>
                            <option value="blue" {{ old('gradient_theme', $promo->gradient_theme) === 'blue' ? 'selected' : '' }}>Blue (Default Android)</option>
                            <option value="green" {{ old('gradient_theme', $promo->gradient_theme) === 'green' ? 'selected' : '' }}>Green (Keamanan / Proteksi)</option>
                            <option value="cyan" {{ old('gradient_theme', $promo->gradient_theme) === 'cyan' ? 'selected' : '' }}>Cyan (Telegram / Notifikasi)</option>
                            <option value="purple" {{ old('gradient_theme', $promo->gradient_theme) === 'purple' ? 'selected' : '' }}>Purple (Garansi / Layanan)</option>
                            <option value="amber" {{ old('gradient_theme', $promo->gradient_theme) === 'amber' ? 'selected' : '' }}>Amber (Perhatian / Event)</option>
                            <option value="red" {{ old('gradient_theme', $promo->gradient_theme) === 'red' ? 'selected' : '' }}>Red (Flash Sale / Mendesak)</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    {{-- Tombol Text --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Teks Tombol') }} <span class="text-danger">*</span></label>
                        <input type="text" name="button_text" id="promoBtnTextInput" class="form-control rounded-3 @error('button_text') is-invalid @enderror" placeholder="{{ __('Contoh: Klaim promo') }}" value="{{ old('button_text', $promo->button_text) }}" required>
                        @error('button_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Tombol Link / URL --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Target URL / Tautan') }} <span class="text-danger">*</span></label>
                        <input type="text" name="button_url" class="form-control rounded-3 @error('button_url') is-invalid @enderror" placeholder="{{ __('Contoh: /catalog atau https://t.me/...') }}" value="{{ old('button_url', $promo->button_url) }}" required>
                        @error('button_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3 mb-4 align-items-center">
                    {{-- Urutan Tampilan --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-body" style="font-size: 0.88rem;">{{ __('Urutan Tampilan') }}</label>
                        <input type="number" name="order_index" class="form-control rounded-3" value="{{ old('order_index', $promo->order_index) }}" min="0" max="999">
                        <small class="text-secondary" style="font-size: 0.72rem;">Angka lebih kecil tampil lebih dulu (misal: 1, 2, 3...)</small>
                    </div>

                    {{-- Status Aktif --}}
                    <div class="col-md-6 pt-md-3">
                        <div class="form-check form-switch fs-6">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', $promo->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActiveSwitch">{{ __('Aktifkan kartu promo ini di dashboard') }}</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top" style="border-color: var(--outline-variant) !important;">
                    <a href="{{ route('admin.promos.index') }}" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-semibold">
                        {{ __('Batal') }}
                    </a>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold">
                        <i class="fas fa-save me-1.5"></i>{{ __('Simpan Perubahan') }}
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
            
            <div class="m3-promo-card position-relative overflow-hidden" id="liveCardPreview" style="background: {{ $promo->gradient_style }}; border: 1px solid var(--outline-variant); border-radius: var(--radius-xl, 20px); min-height: 180px; display: flex;">
                {{-- Right Image Container with Full-Bleed Seamless Alpha Mask to the Left --}}
                <div id="previewImgContainer" class="position-absolute inset-0 w-100 h-100 {{ $promo->image ? '' : 'd-none' }}" style="overflow: hidden; pointer-events: none; z-index: 1;">
                    <img id="previewImgEl" src="{{ $promo->image_url ?? '' }}" alt="Promo Preview" style="width: 100%; height: 100%; object-fit: cover; object-position: right center; -webkit-mask-image: linear-gradient(to right, transparent 0%, transparent 18%, rgba(0, 0, 0, 0.25) 42%, rgba(0, 0, 0, 0.8) 72%, #000 100%); mask-image: linear-gradient(to right, transparent 0%, transparent 18%, rgba(0, 0, 0, 0.25) 42%, rgba(0, 0, 0, 0.8) 72%, #000 100%);">
                    <div class="preview-gradient-mask" id="previewGradientMask" style="position: absolute; inset: 0; background: linear-gradient(to left, rgba({{ $promo->theme_rgb }}, 0.04) 0%, rgba({{ $promo->theme_rgb }}, 0.35) 45%, rgba({{ $promo->theme_rgb }}, 0.8) 75%, var(--surface-container) 98%); pointer-events: none; z-index: 2;"></div>
                </div>

                <div class="p-3.5 d-flex flex-column justify-content-between w-100 position-relative" style="z-index: 3;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge rounded-pill fw-bold" id="previewBadge" style="background: {{ $promo->badge_color }}; color: #ffffff; font-size: 0.72rem; letter-spacing: 0.03em;">{{ $promo->badge_text ?: 'INFO' }}</span>
                            <i class="{{ $promo->icon }} text-primary opacity-50 fs-5" id="previewIcon"></i>
                        </div>
                        <h3 class="fw-bold text-body mb-1 promo-title-overlap" id="previewTitle" style="font-size: 1rem; line-height: 1.35; max-width: 82%;">{{ $promo->title }}</h3>
                        <p class="text-secondary small mb-3 promo-desc-overlap" id="previewDesc" style="font-size: 0.8125rem; line-height: 1.4; max-width: 78%;">{{ $promo->description }}</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5" style="font-size: 0.8125rem;">
                            <span id="previewBtnText">{{ $promo->button_text }}</span>
                            <i class="fas fa-arrow-right" style="font-size: 0.7rem;"></i>
                        </button>
                    </div>
                </div>
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
    const imageInput = document.getElementById('promoImageInput');
    const removeImgCheckbox = document.getElementById('removeImageCheckbox');

    const previewTitle = document.getElementById('previewTitle');
    const previewBadge = document.getElementById('previewBadge');
    const previewDesc = document.getElementById('previewDesc');
    const previewIcon = document.getElementById('previewIcon');
    const previewBtnText = document.getElementById('previewBtnText');
    const liveCard = document.getElementById('liveCardPreview');
    const previewImgContainer = document.getElementById('previewImgContainer');
    const previewImgEl = document.getElementById('previewImgEl');
    const previewGradientMask = document.getElementById('previewGradientMask');

    const initialImgSrc = "{{ $promo->image_url ?? '' }}";

    const themeGradients = {
        blue: 'linear-gradient(135deg, rgba(11, 87, 208, 0.08), var(--surface-container))',
        green: 'linear-gradient(135deg, rgba(56, 106, 33, 0.08), var(--surface-container))',
        cyan: 'linear-gradient(135deg, rgba(34, 158, 217, 0.1), var(--surface-container))',
        purple: 'linear-gradient(135deg, rgba(142, 68, 173, 0.08), var(--surface-container))',
        amber: 'linear-gradient(135deg, rgba(230, 81, 0, 0.08), var(--surface-container))',
        red: 'linear-gradient(135deg, rgba(179, 38, 30, 0.08), var(--surface-container))'
    };

    const themeRgbs = {
        blue: '11, 87, 208',
        green: '56, 106, 33',
        cyan: '34, 158, 217',
        purple: '142, 68, 173',
        amber: '230, 81, 0',
        red: '179, 38, 30'
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
        const rgb = themeRgbs[theme] || '11, 87, 208';
        if (themeGradients[theme]) {
            liveCard.style.background = themeGradients[theme];
        }

        if (previewGradientMask) {
            previewGradientMask.style.background = `linear-gradient(to left, rgba(${rgb}, 0.05) 0%, rgba(${rgb}, 0.45) 40%, rgba(${rgb}, 0.85) 75%, var(--surface-container) 100%)`;
        }
    }

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImgEl.src = e.target.result;
                    previewImgContainer.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            } else if (removeImgCheckbox && removeImgCheckbox.checked) {
                previewImgContainer.classList.add('d-none');
            } else if (initialImgSrc) {
                previewImgEl.src = initialImgSrc;
                previewImgContainer.classList.remove('d-none');
            }
        });
    }

    if (removeImgCheckbox) {
        removeImgCheckbox.addEventListener('change', function () {
            if (this.checked && (!imageInput.files || !imageInput.files[0])) {
                previewImgContainer.classList.add('d-none');
            } else if (!this.checked && initialImgSrc) {
                previewImgEl.src = initialImgSrc;
                previewImgContainer.classList.remove('d-none');
            }
        });
    }

    [titleInput, badgeInput, badgeColorInput, descInput, iconInput, themeSelect, btnTextInput].forEach(el => {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });

    updatePreview();
});
</script>
@endpush
