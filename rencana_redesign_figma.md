# RENCANA REDESAIN ANTARMUKA WEB (MATERIAL DESIGN 3 / FIGMA REFERENCE)

Dokumen ini memuat arsitektur teknis, spesifikasi token desain, pemetaan komponen Blade, dan tahapan eksekusi implementasi antarmuka berdasarkan file desain `reference design/Untitled.svg`.

---

## 1. Visi Desain & Identitas Sistem
Mengadopsi **Material Design 3 (M3) Expressive / Android 17 Material You** dengan nuansa pastel lavender yang elegan, berorientasi UX, dan bersih:
- **Tipografi**: Universal Google Sans (Display untuk Headlines, Sans untuk UI Body, Mono untuk kode).
- **Hierarki Radius (Concentric Radii)**:
  - `--radius-xl` (28px): Hero Greeting Card, Floating Modal, Bottom Sheet
  - `--radius-lg` (20px - 24px): Kartu Saldo Utama, Kartu Metrik 3-Kolom, Container Banner
  - `--radius-md` (16px): List Item Pesanan, Kartu Produk Katalog, Tabel Data
  - `--radius-sm` (12px): Input Form Field, Sub-cards, Quick-Action Containers
  - `--radius-pill` (9999px): Tombol Utama (Pill Button), Filter Chips, Search Input, Status Badges
- **Tonal Elevation**: Zero heavy drop shadow. Menggunakan tonal surface containers (`--surface-container-low`, `--surface-container`, `--surface-container-high`).

---

## 2. Spesifikasi Token Desain (Light & Dark Mode)

| Token CSS | Light Mode | Dark Mode | Fungsi |
| :--- | :--- | :--- | :--- |
| `--md-sys-color-primary` | `#65558F` | `#D0BCFF` | Tombol CTA utama, status aktif, fokus indikator |
| `--md-sys-color-on-primary` | `#FFFFFF` | `#381E72` | Teks di atas warna primary |
| `--md-sys-color-primary-container` | `#EADDFF` | `#4F378B` | Highlight chip aktif, pill background |
| `--md-sys-color-on-primary-container` | `#21005D` | `#EADDFF` | Teks & ikon di atas primary container |
| `--md-sys-color-surface` | `#FEF7FF` | `#141218` | Latar belakang kanvas aplikasi |
| `--md-sys-color-surface-container-low` | `#F3EDF7` | `#1D1B20` | Background sidebar drawer, kartu banner |
| `--md-sys-color-surface-container` | `#ECE6F0` | `#211F26` | Background kartu metrik, list group item |
| `--md-sys-color-surface-container-high` | `#E6E0E9` | `#2B2930` | Hover state, input background |
| `--md-sys-color-outline` | `#79747E` | `#938F99` | Border input, pembatas outline |
| `--md-sys-color-outline-variant` | `#CAC4D0` | `#49454F` | Divider halus, hairline border |
| `--md-sys-color-error` | `#BA1A1A` | `#FFB4AB` | Badge notifikasi, stok habis, status batal |

---

## 3. Peta Jalan Eksekusi Bertahap

### Tahap 1: Fondasi Token & CSS (`web/public/css/app.css`)
- Menerapkan variabel token M3 Lavender Palette (Light & Dark Mode).
- Menjaga proteksi icon Font Awesome.
- Menstandarkan komponen atomik: Pill buttons, Outlined squircle inputs, Tonal cards.

### Tahap 2: Layout Navigasi Shell (`web/resources/views/layouts/app.blade.php`)
- **Desktop Sidebar (264px)**:
  - Header dengan icon cloud squircle + nama store.
  - Segmented button switcher `Belanja` vs `Admin` (khusus role admin/seller).
  - Item menu dengan active indicator Full Pill lavender (`#E8DEF8`).
  - Help card Telegram di footer sidebar.
- **Top App Bar (56px)**:
  - Search bar Full Pill `48px` terpusat.
  - Notification icon dengan badge merah berdempetan presisi di sudut kanan atas.
  - Cart icon, Dark mode toggle, Avatar dropdown.
- **Mobile Navigation**:
  - Top bar ringkas dan navigasi bawah ergonomis satu tangan.

### Tahap 3: Halaman Dashboard Utama (`web/resources/views/dashboard.blade.php`)
- Hero Greeting Banner squircle 28px dengan tombol pill CTA `Jelajahi katalog`.
- Kartu Total Belanja / Saldo tebal dengan icon dompet.
- Kartu Metrik (3 Kolom Desktop / 2x2 Mobile): `Total pesanan`, `Selesai`, `Menunggu`.
- Daftar Pesanan Terbaru: Baris kartu squircle 16px dengan status pill badge.
- Panel Akses Cepat & Banner Bantuan Komplain di bagian bawah.

### Tahap 4: Halaman Katalog Produk (`web/resources/views/catalog/index.blade.php`)
- Kolom pencarian Full Pill + dropdown sort.
- Filter chip horizontal scrollable sentuh mulus.
- Kartu produk M3: Image media dengan stok pill tag di atas, detail info, dan quick-add cart button di pojok bawah.

### Tahap 5: Halaman Profil & Keamanan (`web/resources/views/profile.blade.php`)
- Banner akun dengan avatar lingkaran, badge role, status 2FA.
- Tab navigasi sub-menu flat.
- Form data profil squircle dan panduan keamanan.

### Tahap 6: Halaman Autentikasi (`login.blade.php`, `register.blade.php`)
- Split-card dialog modern: Sisi kiri form masuk/daftar, sisi kanan info store dan kontak admin.
- Tombol alternatif Login dengan Telegram.

### Tahap 7: Testing & Sinkronisasi Installer
- Menjalankan test suite Laravel (`php artisan test`) untuk memastikan seluruh 200 test tetap lulus.
- Sinkronisasi perubahan ke installer dan updater (`ops/website_installer.sh` & `ops/update_manager.sh`).

---

## 4. Analisis Risiko & Mitigasi
1. **Dark Mode Contrast**: Memetakan token warna dark mode secara ketat sesuai standar Material 3 Tonal Surface (`#141218`, `#1D1B20`, `#D0BCFF`) untuk menjamin rasio kontras WCAG AAA.
2. **Role Exposure**: Segmented button "Admin" hanya dimunculkan jika user yang sedang login memiliki hak akses admin atau seller.
3. **Responsivitas Layar Sempit**: Menggunakan CSS grid responsif dengan horizontal scroll wrapper pada area kategori & tabs agar tidak ada elemen terpotong.
4. **Service Integritas**: Tidak mengubah skema database, tabel model, atau controller bot Telegram.

