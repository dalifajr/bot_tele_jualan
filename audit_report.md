# Laporan Audit Keamanan, Kualitas Kode, & Cacat Logika Sistem 🛡️

**Proyek**: Website & Bot Telegram Jualan  
**Tanggal Audit**: 22 Juli 2026  
**Auditor**: Antigravity Security Division  
**Status Proyek**: Produksi / Pra-Rilis  

---

## 🔴 PRIORITAS KRITIS (Critical Priority)

### 1. Fitur Restore Pada Bot Telegram Hanya "Stub" / Palsu (Logic Bug)
- **Lokasi Kode**: `src/app/bot/handlers/main.py` (`_handle_restore_upload`) dan `src/app/bot/services/restore_service.py`
- **Analisis & Masalah**: 
  Ketika Admin mengunggah file ZIP cadangan untuk dipulihkan (restore) melalui Bot Telegram:
  1. Bot mengunduh ZIP dan mengekstrak manifestnya.
  2. Bot memvalidasi data dan mendeteksi data duplikat menggunakan `_detect_restore_duplicates`.
  3. Bot menampilkan ringkasan data yang "Siap Diimpor" (jumlah user, produk, order, stock, dll.).
  4. **Namun, tidak ada satu pun baris kode yang sebenarnya menulis atau menyimpan data baru tersebut ke dalam database SQLite!** Setelah menampilkan ringkasan, alur (*flow*) langsung dibersihkan (`_clear_flow`) dan file temp dihapus.
- **Dampak**: Fitur restore pada Bot Telegram sama sekali tidak berfungsi secara fungsional. Admin akan mengira data telah dipulihkan padahal data tersebut diabaikan setelah validasi.
- **Rekomendasi**: Implementasikan fungsi `perform_restore(session, backup_data, duplicates)` di `restore_service.py` yang akan menyisipkan baris-baris entitas baru (yang bukan duplikat) ke database SQL, kemudian panggil fungsi tersebut sebelum alur diselesaikan.

---

## 🟠 PRIORITAS TINGGI (High Priority)

### 2. Risiko Lock Concurrency pada SQLite (Database Concurrency)
- **Lokasi Kode**: `src/app/db/database.py` & `web/config/database.php`
- **Analisis & Masalah**:
  Sistem ini berjalan menggunakan dua proses runtime yang berbeda dan terpisah:
  1. **Laravel Web Application** (melayani Admin Panel dan Seller Panel).
  2. **Python FastAPI + Telegram Bot Application** (melayani notifikasi listener Android dan chat Telegram).
  Kedua runtime ini membaca dan menulis secara simultan ke satu file database SQLite yang sama (`data/bot_jualan.db`). Meskipun SQLite telah dikonfigurasi dalam mode WAL (*Write-Ahead Logging*) dengan `busy_timeout=5000` pragma, SQLite secara arsitektur hanya mengizinkan **satu penulis (writer) aktif pada satu waktu**. Pada saat lalu lintas tinggi (misalnya banyak checkout dari bot bersamaan dengan ekspor data dari admin panel), salah satu runtime akan mengalami kegagalan dengan error `database is locked` (OperationalError).
- **Dampak**: Transaksi pembayaran atau checkout dapat gagal secara acak di sisi API/Bot jika website sedang sibuk memproses query tulis yang berat.
- **Rekomendasi**: Migrasikan database ke sistem database client-server seperti **PostgreSQL** atau **MySQL** jika transaksi harian mulai meningkat signifikan untuk menjamin konkurensi data yang andal dan mencegah penguncian transaksi.

---

## 🟡 PRIORITAS MENENGAH (Medium Priority)

### 3. Risiko Tabrakan (Collision) Kode Unik Pembayaran QRIS (Usability / Logic Flaw)
- **Lokasi Kode**: `src/app/bot/services/order_service.py` (`_generate_unique_code` dan `_resolve_pending_payment`)
- **Analisis & Masalah**:
  1. Fungsi `_generate_unique_code` menghasilkan angka acak antara `1` sampai `200` untuk ditambahkan ke subtotal pembayaran.
  2. Jika ada dua pesanan pending dengan nominal produk yang sama dan secara acak mendapatkan kode unik yang sama (probabilitas 1/200), dan konfirmasi pembayaran masuk dari Android listener tanpa menyertakan referensi transaksi (hanya nominal), pencocokan nominal akan mengembalikan status `ambiguous` (karena nominal cocok ke lebih dari satu order).
- **Dampak**: Pembayaran pelanggan tidak akan otomatis terproses, melainkan terhambat dan membutuhkan tindakan manual dari admin untuk menyelesaikan order.
- **Rekomendasi**: 
  - Tingkatkan rentang kode unik (misalnya `1` hingga `999`).
  - Dorong penggunaan `reference` wajib dari Android listener API agar pencocokan selalu menggunakan ID transaksi bank yang unik.

---

## 🟢 PRIORITAS RENDAH & INFORMASI (Low / Info Priority)

### 4. Hardcoded Path pada Eksekusi Perintah Subprocess (Resolved ✅)
- **Lokasi Kode**: `src/app/bot/services/order_service.py`
- **Analisis & Masalah**:
  Terdapat pemanggilan perintah shell eksternal php artisan (`vpn:create-for-order`) dengan parameter `cwd` yang di-hardcode ke folder lokal pengembang: `cwd="d:/bot_tele_jualan/web"`.
- **Dampak**: Jika sistem ini dipasang pada Linux VPS atau direktori yang berbeda di server produksi, fitur otomatisasi pembuatan VPN akan gagal total karena direktori `d:/bot_tele_jualan/web` tidak ditemukan.
- **Perbaikan yang Telah Dilakukan**: Saya telah mengganti hardcode path tersebut dengan merujuk secara dinamis ke `settings.project_root / "web"`.

### 5. Risiko Arbitrary File Upload & Stored XSS (Resolved dalam Patch Sebelumnya ✅)
- **Status**: Telah diperbaiki secara aman pada patch sebelumnya.
- **Detail**: Kerentanan pengunggahan file sewenang-wenang (Zip Slip) pada fitur restore cadangan media dan bypass ekstensi file keluhan telah ditutup dengan validasi `realpath` dan pengacakan nama berkas secara kriptografis.

---

*Laporan ini disusun secara objektif berdasarkan pembacaan statis terhadap seluruh arsitektur repositori Anda.*
