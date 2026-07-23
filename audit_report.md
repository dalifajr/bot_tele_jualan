# Laporan Audit Keamanan, Kualitas Kode, & Cacat Logika Sistem 🛡️

**Proyek**: Website & Bot Telegram Jualan  
**Tanggal Audit**: 23 Juli 2026  
**Auditor**: Antigravity Security & Architecture Division  
**Status Proyek**: Produksi / Pra-Rilis  

---

## Executive Summary

Audit mendalam telah dilakukan terhadap seluruh basis kode aplikasi web berbasis **Laravel 13.11.2 / PHP 8.4** dan **Bot Telegram / FastAPI Python 3.12**. Audit ini mencakup analisis keamanan otentikasi & otorisasi, integritas transaksi database, cacat logika alur bisnis, serta potensi kerentanan sistem.

Seluruh temuan telah dikategorikan berdasarkan skala prioritas dampaknya terhadap kerahasiaan (*confidentiality*), integritas (*integrity*), dan ketersediaan (*availability*) sistem.

---

## 🔴 PRIORITAS KRITIS (Critical Priority)

### 1. Fitur Restore Pada Bot Telegram Hanya "Stub" / Impoten (Logic Bug)
- **Lokasi Kode**: `src/app/bot/handlers/main.py` (`_handle_restore_upload`) & `src/app/bot/services/restore_service.py`
- **Analisis & Masalah**:
  Ketika Admin mengunggah file ZIP cadangan untuk dipulihkan (*restore*) melalui Bot Telegram:
  1. Bot mengunduh ZIP dan mengekstrak manifestnya.
  2. Bot memvalidasi data dan mendeteksi data duplikat menggunakan `_detect_restore_duplicates`.
  3. Bot menampilkan ringkasan data yang "Siap Diimpor" (jumlah user, produk, order, stock, dll.).
  4. **Masalah**: Tidak ada satu pun baris kode yang melakukan eksekusi `INSERT` atau `UPDATE` data ke database SQLite `bot_jualan.db`. Setelah menampilkan statistik, alur (*flow*) langsung dibersihkan (`_clear_flow`) dan berkas temp dihapus.
- **Dampak**: Admin meyakini proses restore cadangan telah sukses padahal tidak ada perubahan data sama sekali pada database.
- **Rekomendasi Mitigation**:
  Implementasikan fungsi pemulihan nyata di `restore_service.py` (misalnya `execute_restore(session, entity_data)`) yang melakukan iterasi dan menyimpan entitas non-duplikat ke database sebelum alur diselesaikan.

### 2. Presedensi Operator SQL pada Query Otorisasi Seller (Potensi IDOR / Logic Flaw)
- **Lokasi Kode**: `web/app/Http/Controllers/SellerController.php` (misal pada `updateStock`)
- **Analisis & Masalah**:
  Pada pencarian entitas stok milik seller:
  ```php
  $stock = StockUnit::where('uploaded_by_id', $sellerId)
      ->orWhere('seller_id', $sellerId)
      ->findOrFail($id);
  ```
  Dalam Eloquent Laravel, ekspresi `orWhere` tanpa pembungkus callback `where(function($q)...)` menghasilkan klausa SQL:
  `WHERE uploaded_by_id = ? OR seller_id = ? AND id = ?`
  Berdasarkan aturan presedensi operator SQL (`AND` diproses sebelum `OR`), query ini dievaluasi sebagai:
  `(uploaded_by_id = seller_id) OR (seller_id = seller_id AND id = target_id)`
- **Dampak**: Jika seorang seller memiliki baris stok lain di database (`uploaded_by_id = seller_id` bernilai `TRUE`), klausa `WHERE` dapat mengevaluasi `TRUE` terlepas dari nilai `id` yang diminta, berpotensi memicu kegagalan isolasi data stok antar-seller.
- **Rekomendasi Mitigation**:
  Bungkus klausa `OR` dalam pengelompokan fungsi anonymous:
  ```php
  $stock = StockUnit::where(function($q) use ($sellerId) {
      $q->where('uploaded_by_id', $sellerId)
        ->orWhere('seller_id', $sellerId);
  })->findOrFail($id);
  ```

---

## 🟠 PRIORITAS TINGGI (High Priority)

### 3. Concurrency Lock & Transaction Contention pada SQLite
- **Lokasi Kode**: `src/app/db/database.py` & `web/config/database.php`
- **Analisis & Masalah**:
  Sistem beroperasi dengan dua runtime terpisah yang mengakses satu file database SQLite (`bot_jualan.db`) secara bersamaan:
  1. Web App Laravel (melayani Admin & Seller Panel).
  2. Python FastAPI & Telegram Bot (melayani Webhook Listener & Bot Polling).
  Meskipun SQLite menggunakan mode `WAL` (*Write-Ahead Logging*), SQLite arsitektural hanya mengizinkan **1 proses penulis (writer) aktif pada satu waktu**. Pada lalu lintas tinggi (misalnya pembayaran massal dari bot bersamaan dengan ekspor data dari admin panel), salah satu runtime akan mengalami kegagalan transaksi `OperationalError: database is locked`.
- **Dampak**: Kegagalan transaksi otomatis, pembatalan order secara mendadak, atau kegagalan webhook listener Android.
- **Rekomendasi Mitigation**:
  Migrasikan database produksi ke RDBMS client-server yang mendukung konkurensi tingkat baris (Row-Level Locking) seperti **PostgreSQL** atau **MySQL / MariaDB**.

### 4. Sensitivitas Rahasia Kunci Aplikasi (`APP_KEY`) pada Background Job Webhook
- **Lokasi Kode**: `web/routes/web.php` (Line 23) & `web/app/Http/Controllers/AdminController.php` (`runBroadcastBackground`)
- **Analisis & Masalah**:
  Endpoint `/admin/broadcast/run-bg/{jobId}` ditempatkan di luar grup middleware otentikasi `admin`. Endpoint ini mengandalkan `hash_hmac('sha256', $jobId, config('app.key'))`. Jika `APP_KEY` pada `.env` terkespos/ter-commit ke repositori Git, pihak luar dapat menghitung token HMAC yang valid dan memicu eksekusi massal broadcast job.
- **Dampak**: Eksekusi pemrosesan latar belakang yang tidak sah atau DoS (*Denial of Service*) pada server bot Telegram.
- **Rekomendasi Mitigation**:
  - Pastikan `.env` terdaftar di `.gitignore` dan tidak pernah di-commit.
  - Masukkan rute `/admin/broadcast/run-bg/{jobId}` ke dalam kerangka otentikasi internal atau gunakan token sesi admin.

---

## 🟡 PRIORITAS MENENGAH (Medium Priority)

### 5. Potensi Benturan (*Collision*) Kode Unik Pembayaran QRIS
- **Lokasi Kode**: `src/app/bot/services/order_service.py` (`_generate_unique_code` dan `_resolve_pending_payment`)
- **Analisis & Masalah**:
  Sistem menghasilkan kode unik nominal antara `1` sampai `200` untuk membedakan transfer QRIS/Bank yang masuk. Jika terdapat 2 pesanan pending dengan harga produk sama dan secara acak mendapatkan digit kode unik yang sama (probabilitas 1/200), rekonsiliasi otomatis listener Android akan menandai transaksi sebagai `ambiguous`.
- **Dampak**: Pembayaran tidak otomatis terverifikasi dan membutuhkan intervensi manual dari Admin.
- **Rekomendasi Mitigation**:
  - Perluas rentang acak kode unik menjadi `1..999`.
  - Prioritaskan pencocokan via `reference_id` atau ID transaksi unik dari mutasi bank.

### 6. Minimnya Rate Limiting pada API Public / Telegram Helper
- **Lokasi Kode**: `web/routes/web.php` (`/api/check-telegram-id`, `/auth/telegram/webapp`)
- **Analisis & Masalah**:
  Beberapa rute pengecekan akun Telegram belum memiliki pembatas laju pemanggilan (`throttle middleware`).
- **Dampak**: Potensi enumerasi akun user atau pemuatan berlebih (*enumeration / brute force*).
- **Rekomendasi Mitigation**:
  Tambahkan middleware `throttle:10,1` pada rute API publik pendukung otentikasi Telegram.

---

## 🟢 PRIORITAS RENDAH & STATUS PERBAIKAN (Low Priority & Remediation Log)

### 7. Hardcoded Path pada Eksekusi Subprocess VPN (SOLVED ✅)
- **Status**: **Telah Diperbaiki**.
- **Detail**: Jalur eksekusi script `php artisan vpn:create-for-order` sebelumnya di-hardcode ke `d:/bot_tele_jualan/web`. Perbaikan telah menggantinya dengan rujukan dinamis `settings.project_root / "web"`.

### 8. Sanitasi Pengunggahan Berkas Media & Cadangan Restore ZIP (SOLVED ✅)
- **Status**: **Telah Diperbaiki**.
- **Detail**: Kerentanan pengunggahan file sewenang-wenang (Zip Slip) dan bypass ekstensi file pada lampiran keluhan telah ditutup menggunakan sanitasi `realpath` dan enkripsi nama berkas.

---

## Ringkasan Matriks Risiko & Tindakan

| No | Komponen / Fitur | Tingkat Keparahan | Status | Tindakan Utama |
|---|---|---|---|---|
| 1 | Restore Bot Telegram | 🔴 Critical | Perlu Perbaikan | Implementasi fungsi penulisan SQL pada `restore_service.py` |
| 2 | Presedensi Query Seller Stock | 🔴 Critical | Perlu Perbaikan | Refactor query dengan `where(function($q)...)` |
| 3 | Lock Concurrency SQLite | 🟠 High | Disarankan | Migrasi ke MySQL / PostgreSQL |
| 4 | Endpoint Background Broadcast | 🟠 High | Perlu Perbaikan | Amankan rute atau lindungi secret key `APP_KEY` |
| 5 | Kode Unik Nominal QRIS | 🟡 Medium | Perlu Perbaikan | Perluas rentang unik `1..999` & matching via reference |
| 6 | Rate Limiting Telegram API | 🟡 Medium | Perlu Perbaikan | Tambahkan middleware `throttle` pada web.php |
| 7 | Subprocess CWD Path | 🟢 Low | ✅ Fixed | Path diubah menjadi dinamis |
| 8 | Zip Slip & Upload Sanitization | 🟢 Low | ✅ Fixed | Sanitasi path & random name applied |

---
*Laporan ini disusun secara komprehensif untuk penguatan arsitektur dan keamanan sistem secara berkelanjutan.*
