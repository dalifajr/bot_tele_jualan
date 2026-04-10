# Android Listener (Flutter + Native Bridge)

Scaffold awal aplikasi listener notifikasi pembayaran.

## Scope Tahap Ini

- UI konfigurasi endpoint dan secret
- Tombol enable listener (arah ke native bridge)
- Placeholder method channel untuk akses NotificationListenerService

## Endpoint Target

- `POST /listener/payment`

Catatan koneksi:
- Jika endpoint masih `http://` (tanpa TLS), aplikasi Android membutuhkan `usesCleartextTraffic=true` (sudah diaktifkan di manifest proyek ini).
- Untuk produksi sangat disarankan pakai `https://` agar trafik terenkripsi.

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

## Next

1. Implement `NotificationListenerService` native Android.
2. Parse nominal rupiah dari notifikasi aplikasi payment.
3. Kirim event ke endpoint bot dengan retry + idempotency.
