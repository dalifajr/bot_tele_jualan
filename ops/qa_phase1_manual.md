# Manual QA Runbook - Phase 1 UX

Dokumen ini dipakai untuk menutup item manual QA di `ROADMAP_TODO.md`.

## Tujuan

- Verifikasi alur UI callback customer untuk menu `Pesanan Saya`.
- Verifikasi konsistensi empty state dan CTA pada layar customer/admin yang paling sering dipakai.

## Prasyarat

1. Bot berjalan normal (`src/app/bot/app.py`) dengan token aktif.
2. Listener payment aktif (`src/app/api/main.py`) jika skenario melibatkan perubahan status dari callback payment.
3. Ada minimal 2 akun Telegram:
   - Akun admin (terdaftar di `user_role.txt`)
   - Akun customer biasa
4. Database berisi kombinasi data order:
   - Minimal 1 order `delivered`
   - Minimal 1 order `pending_payment`
   - Cukup banyak order untuk menguji pagination (ideal 11+ order)

## Skenario A - Pesanan Saya (Pagination + Detail + Copy + Back)

1. Login sebagai customer, buka menu utama (`/start`).
2. Klik tombol `📦 Pesanan Saya`.
3. Pastikan daftar order tampil dengan urutan terbaru di atas.
4. Jika total order > 10:
   - Pastikan tombol navigasi halaman muncul.
   - Klik tombol ke halaman berikutnya, lalu kembali ke halaman sebelumnya.
5. Klik salah satu order untuk membuka detail order.
6. Jika status order `delivered`:
   - Pastikan blok data akun tampil.
   - Klik tombol `📋 Copy Akun`.
   - Pastikan bot mengirim teks mentah akun yang siap di-copy.
7. Klik tombol kembali ke list halaman.
8. Pastikan kembali ke halaman list asal (bukan reset ke halaman 1 jika berasal dari halaman lain).

Kriteria lulus:

- Tidak ada error callback.
- Navigasi halaman konsisten.
- Detail order sesuai data database.
- Tombol `Copy Akun` menghasilkan payload akun yang benar.
- Tombol kembali mempertahankan konteks halaman.

## Skenario B - Empty State dan CTA Konsisten

### Customer Flow

1. Katalog kosong (`Produk`):
   - Buka menu produk saat stok kosong.
   - Pastikan pesan empty state jelas dan footer customer konsisten.
2. Pesanan kosong (`Pesanan Saya`):
   - Gunakan customer tanpa riwayat order.
   - Pastikan pesan empty state + CTA mengarah ke tindakan berikutnya (`Belanja Sekarang`).
3. Order detail tidak ditemukan:
   - Akses callback order dengan ref tidak valid.
   - Pastikan pesan fallback jelas dan mengarahkan ke menu yang benar.

### Admin Flow

1. Buka panel admin, masuk ke menu yang datanya kosong (contoh list produk/list akun/list admin event jika belum ada data).
2. Pastikan setiap empty state:
   - Judul jelas.
   - Instruksi next action jelas.
   - Footer admin konsisten (`Pilih aksi admin lewat tombol di bawah.`).
3. Uji akun non-admin menekan callback admin.
4. Pastikan teks penolakan akses admin konsisten dan tidak bocor ke flow customer.

Kriteria lulus:

- Semua empty state tidak ambigu.
- CTA selalu tersedia dan relevan.
- Footer customer/admin konsisten sesuai konteks.
- Tidak ada frase lama yang bertentangan gaya baru.

## Bukti Uji

Isi tabel ini setelah test:

| Tanggal | Penguji | Skenario | Status | Catatan |
| --- | --- | --- | --- | --- |
| YYYY-MM-DD | nama | Skenario A | PASS/FAIL |  |
| YYYY-MM-DD | nama | Skenario B | PASS/FAIL |  |

## Catatan Eksekusi Cepat

- Script otomatis terkait logic service/API: `PYTHONPATH=src .venv/Scripts/python.exe ops/e2e_local_test.py`
- Script smoke copy/CTA: `PYTHONPATH=src .venv/Scripts/python.exe ops/qa_copy_smoke.py`
