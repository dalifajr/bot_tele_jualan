# RENCANA REDESAIN ANTARMUKA WEB (MATERIAL DESIGN 3 / FIGMA REFERENCE)

Dokumen ini memuat arsitektur teknis, spesifikasi token desain, pemetaan komponen Blade, dan status eksekusi implementasi antarmuka berdasarkan file desain `reference design/Untitled.svg`.

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
| `--primary` | `#65558F` | `#D0BCFF` | Tombol CTA utama, status aktif, fokus indikator |
| `--on-primary` | `#FFFFFF` | `#381E72` | Teks di atas warna primary |
| `--primary-container` | `#EADDFF` | `#4F378B` | Highlight chip aktif, pill background |
| `--on-primary-container` | `#21005D` | `#EADDFF` | Teks & ikon di atas primary container |
| `--surface` | `#FEF7FF` | `#141218` | Latar belakang kanvas aplikasi |
| `--surface-container-low` | `#F3EDF7` | `#1D1B20` | Background sidebar drawer, kartu banner |
| `--surface-container` | `#ECE6F0` | `#211F26` | Background kartu metrik, list group item |
| `--surface-container-high` | `#E6E0E9` | `#2B2930` | Hover state, input background |
| `--outline` | `#79747E` | `#938F99` | Border input, pembatas outline |
| `--outline-variant` | `#CAC4D0` | `#49454F` | Divider halus, hairline border |
| `--error` | `#BA1A1A` | `#FFB4AB` | Badge notifikasi, stok habis, status batal |

---

## 3. Status Pelaksanaan Tahap Demi Tahap

- [x] **Tahap 1: Fondasi Token & CSS M3 (`web/public/css/app.css`)**
  - Menerapkan variabel token M3 Lavender Palette (Light & Dark Mode).
  - Menjaga proteksi icon Font Awesome.
  - Menstandarkan komponen atomik: Pill buttons, Outlined squircle inputs, Tonal cards.

- [x] **Tahap 2: Layout Navigasi Shell (`web/resources/views/layouts/app.blade.php`)**
  - Desktop Sidebar (264px membentang penuh) dengan Cloud Header, Segmented switch Belanja/Admin, Full pill active indicator, Telegram Help footer.
  - Top App Bar (56px) dengan Search Bar Full Pill 48px terpusat, Action buttons (Notification micro badge presisi, Keranjang Belanja, Dark mode switch, Avatar menu).
  - Shell Mobile ergonomis satu tangan.

- [x] **Tahap 3: Halaman Dashboard Utama (`web/resources/views/dashboard.blade.php`)**
  - Hero Greeting Banner squircle 28px dengan tombol pill CTA `Jelajahi katalog`.
  - Kartu Total Belanja / Saldo tebal dengan icon dompet dan link navigasi.
  - Kartu Metrik 3 Kolom: `Total pesanan`, `Selesai`, `Menunggu`.
  - Daftar Pesanan Terbaru baris kartu squircle 16px dengan status pill badge lembut.
  - Panel Akses Cepat & Complaint Banner di bagian bawah.

- [x] **Tahap 4: Halaman Katalog Produk (`web/resources/views/catalog/index.blade.php`)**
  - Kolom pencarian Full Pill + dropdown urutkan terlaris.
  - Filter chips kategori scrollable horizontal untuk mobile & desktop.
  - Counter produk real-time `:count produk`.
  - Grid kartu produk M3: Image media thumbnail dengan stok pill tag di atas, detail info, dan quick-add cart circle button di pojok bawah.

- [x] **Tahap 5: Halaman Profil & Keamanan (`web/resources/views/profile.blade.php`)**
  - Header akun squircle 28px dengan avatar lingkaran, badge role, status 2FA, link akun Telegram, tombol logout pill.
  - Tab navigasi sub-menu flat M3 (`Data profil`, `Sandi & 2FA`, `Integrasi bot`, `Keamanan IP`).
  - Row cards squircle 16px untuk ringkasan data akun dan toggle form pengeditan.
  - Security banner M3 untuk pengaturan 2FA Telegram OTP dan kata sandi baru.

- [x] **Tahap 6: Halaman Autentikasi (`web/resources/views/auth/login.blade.php`, `web/public/css/auth.css`)**
  - Split-card dialog modern radius 28px dengan segmented button switcher `Masuk` vs `Daftar`.
  - Input field squircle 12px dan tombol masuk pill M3.
  - Tombol full-width pill `Login dengan Telegram` yang kontras dan elegan.
  - Sisi kanan info store dengan latar primary container, greeting juragan, dan kontak WhatsApp/Telegram admin.

- [x] **Tahap 7: Testing & Build Android Listener**
  - Menjalankan automated test suite Laravel: **200 passed (1077 assertions, 27.41s)** tanpa regresi.
  - Build ulang aplikasi Android Listener (`android_listener`):
    - `app-release.apk` (Universal)
    - `app-arm64-v8a-release.apk` (Arsitektur 64-bit modern)
    - `app-armeabi-v7a-release.apk` (Arsitektur 32-bit)
    - `app-x86_64-release.apk` (Emulator / PC)

---

## 4. Analisis Risiko & Mitigasi
1. **Dark Mode Contrast**: Memetakan token warna dark mode secara ketat sesuai standar Material 3 Tonal Surface (`#141218`, `#1D1B20`, `#D0BCFF`) untuk menjamin rasio kontras WCAG AAA.
2. **Role Exposure**: Segmented button "Admin" hanya dimunculkan jika user yang sedang login memiliki hak akses admin atau seller.
3. **Responsivitas Layar Sempit**: Menggunakan CSS grid responsif dengan horizontal scroll wrapper pada area kategori & tabs agar tidak ada elemen terpotong.
4. **Service Integritas**: Tidak mengubah skema database, tabel model, atau controller bot Telegram.
