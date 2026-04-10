# Bot Telegram Jualan

Implementasi awal bot Telegram untuk jualan digital dengan fitur:
- Multi role admin/customer berbasis `user_role.txt`
- Katalog produk dan stok, dengan aturan 1 blok pesan = 1 unit stok
- Checkout customer dengan nominal unik 3 digit
- Listener API untuk konfirmasi pembayaran otomatis dari Android listener
- Panel terminal alias `jualan` untuk manajemen service, konfigurasi, update, rollback

## Fitur Yang Sudah Diimplementasi

### Customer
- `/start`
- `/catalog`
- `/buy <product_id> <qty>`
- `/myorders`

### Admin
- `/admin_catalog`
- `/product_add Nama|Harga|Deskripsi`
- `/stock_add <product_id>` lalu kirim blok stok
- `/product_suspend <product_id>`
- `/product_unsuspend <product_id>`
- `/product_delete <product_id>`
- `/broadcast <pesan>`
- `/set_qris` lalu kirim gambar QRIS
- `/update_check`
- `/update_apply`

## Quick Start (Ubuntu 25)

1. Clone repo lalu masuk folder proyek.
2. Jalankan setup:
   ```bash
   chmod +x setup.sh
   ./setup.sh
   ```
3. Atur konfigurasi:
   ```bash
   jualan config
   ```
4. Cek status:
   ```bash
   jualan status
   ```

## Operasi Panel `jualan`

```bash
jualan start
jualan stop
jualan restart
jualan status
jualan logs
jualan config
jualan check-update
jualan update
jualan rollback
jualan uninstall
```

## Kontrak Listener Payment

Endpoint:
- `POST /listener/payment`

Payload minimum:

```json
{
  "secret": "<shared_secret>",
  "amount": 50123,
  "source_app": "DANA",
  "reference": "PAY-ORD2026...",
  "raw_text": "teks notifikasi mentah"
}
```

Respons akan mengembalikan status seperti `paid`, `not_found`, atau `ambiguous`.

## Struktur Inti

- `src/app/run_bot.py`: proses bot Telegram (polling)
- `src/app/run_api.py`: proses API listener payment
- `src/app/bot/handlers/main.py`: handler admin/customer
- `src/app/bot/services/`: service katalog, order, parser, broadcast
- `src/app/db/models.py`: model SQLite
- `ops/jualan`: panel operasi VPS
- `ops/update_manager.sh`: check/update/rollback dari GitHub

## Catatan Penting

- Database default: SQLite (fase awal).
- Untuk trafik tinggi disarankan migrasi ke PostgreSQL.
- Android listener saat ini sudah scaffold Flutter + native bridge, implementasi NotificationListenerService masih tahap lanjutan.
