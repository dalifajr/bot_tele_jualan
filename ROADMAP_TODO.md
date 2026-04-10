# Roadmap TODO - Bot Telegram (2026)

## Phase 1 - UX and Interface Foundation

- [x] Standardisasi copy customer dan admin di layar utama, detail, status, dan flow utama.
- [x] Payment timeline customer + reminder sebelum expiry.
- [x] Command self-service cek status order (`/order_status <ORDER_REF>`).
- [x] Verifikasi E2E Phase 1 (lokal + manual):
  - [x] Checkout -> pending -> paid -> delivered sinkron customer/admin (logic API/service, via `ops/e2e_local_test.py`)
  - [x] Checkout -> pending -> expired sinkron reminder/expiry (logic API/service, via `ops/e2e_local_test.py`)
  - [x] Pesanan Saya: pagination, detail order, copy akun, back to page (regression service/callback via `ops/e2e_local_test.py`)
  - [x] Smoke-check konsistensi copy/CTA customer-admin (otomatis, via `ops/qa_copy_smoke.py`)
  - [x] Semua empty state + CTA konsisten di customer/admin (token regression via `ops/qa_copy_smoke.py`; manual UI panduan: `ops/qa_phase1_manual.md`)
- [x] Rapikan reusable pagination pattern untuk list lain (admin list akun/produk yang panjang)

## Phase 2 - Conversion and Retention

- [x] Quick reorder dari riwayat delivered (button + command)
- [x] Rekomendasi produk sederhana setelah delivered (upsell/cross-sell)
- [x] Restock subscription opt-in customer + notifikasi saat stok kembali
- [x] Campaign repeat order (voucher/loyalti ringan) + audit log

## Phase 3 - Reliability, Security, and Ops Scale

- [x] Retry queue (dead-letter) untuk notifikasi customer/admin yang gagal kirim
- [x] Migrasi bertahap role admin dari file ke permission berbasis database
- [x] Backup otomatis + prosedur restore teruji
- [x] Metrik operasional terjadwal (payment match success, timeout rate, notif failure, funnel)

## Notes

- Prioritas aktif sekarang: tutup QA Phase 1 sampai stabil.
- QA Phase 1 regresi lokal sudah lolos; manual Telegram live-check tetap direkomendasikan sebelum release production.
- Quick reorder sudah aktif via tombol detail pesanan dan command `/reorder <ORDER_REF>`.
- Perintah QA otomatis saat ini: `PYTHONPATH=src .venv/Scripts/python.exe ops/e2e_local_test.py`
- Perintah smoke-check copy/CTA: `PYTHONPATH=src .venv/Scripts/python.exe ops/qa_copy_smoke.py`
- Runbook manual QA Phase 1: `ops/qa_phase1_manual.md`
- Verifikasi backup workflow: `bash ops/backup_manager.sh backup`, `bash ops/backup_manager.sh list`, `bash ops/backup_manager.sh restore <file>`
