# Roadmap TODO - Bot Telegram (2026)

## Phase 1 - UX and Interface Foundation

- [x] Standardisasi copy customer dan admin di layar utama, detail, status, dan flow utama.
- [x] Payment timeline customer + reminder sebelum expiry.
- [x] Command self-service cek status order (`/order_status <ORDER_REF>`).
- [ ] Verifikasi E2E Phase 1 (manual):
  - [x] Checkout -> pending -> paid -> delivered sinkron customer/admin (logic API/service, via `ops/e2e_local_test.py`)
  - [x] Checkout -> pending -> expired sinkron reminder/expiry (logic API/service, via `ops/e2e_local_test.py`)
  - [ ] Pesanan Saya: pagination, detail order, copy akun, back to page (UI callback/manual masih perlu verifikasi)
  - [x] Smoke-check konsistensi copy/CTA customer-admin (otomatis, via `ops/qa_copy_smoke.py`)
  - [ ] Semua empty state + CTA konsisten di customer/admin (manual UI, panduan: `ops/qa_phase1_manual.md`)
- [ ] Rapikan reusable pagination pattern untuk list lain (admin list akun/produk yang panjang)

## Phase 2 - Conversion and Retention

- [ ] Quick reorder dari riwayat delivered (button + command)
- [ ] Rekomendasi produk sederhana setelah delivered (upsell/cross-sell)
- [ ] Restock subscription opt-in customer + notifikasi saat stok kembali
- [ ] Campaign repeat order (voucher/loyalti ringan) + audit log

## Phase 3 - Reliability, Security, and Ops Scale

- [ ] Retry queue (dead-letter) untuk notifikasi customer/admin yang gagal kirim
- [ ] Migrasi bertahap role admin dari file ke permission berbasis database
- [ ] Backup otomatis + prosedur restore teruji
- [ ] Metrik operasional terjadwal (payment match success, timeout rate, notif failure, funnel)

## Notes

- Prioritas aktif sekarang: tutup QA Phase 1 sampai stabil.
- Setelah QA lolos, lanjutkan quick reorder sebagai item Phase 2 pertama.
- Perintah QA otomatis saat ini: `PYTHONPATH=src .venv/Scripts/python.exe ops/e2e_local_test.py`
- Perintah smoke-check copy/CTA: `PYTHONPATH=src .venv/Scripts/python.exe ops/qa_copy_smoke.py`
- Runbook manual QA Phase 1: `ops/qa_phase1_manual.md`
