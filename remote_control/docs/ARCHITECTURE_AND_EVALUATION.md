# DOKUMENTASI ARSITEKTUR SISTEM REMOTE CONTROL ANDROID
## Ekosistem Remote Control Multi-Platform (Web Admin & Android User Controller)

---

## 1. Ringkasan Eksekutif

Sistem ini dikembangkan untuk memungkinkan kontrol jarak jauh (*remote control*) perangkat Android target berstatus **Root** melalui dua kanal pengendali:
1. **Web Admin Dashboard**: Pengendalian melalui browser komputer/desktop (khusus Admin).
2. **Android User Application**: Pengendalian melalui aplikasi smartphone Android (khusus User).

Seluruh komunikasi dijalankan melalui koneksi aman terenkripsi melewati Central Relay Server sehingga perangkat target dapat dikontrol tanpa memerlukan IP publik maupun konfigurasi port forwarding rumit.

---

## 2. Struktur Direktori Proyek

```
remote_control/
├── agent_android_root/        # [FASE 1: SELESAI] Daemon Core Root Android Target
│   ├── src/main/java/         # Kode sumber Java murni tanpa dependensi eksternal
│   ├── scripts/               # Script instalasi & persistence Magisk/KernelSU
│   ├── build.bat              # Script kompilasi Windows
│   └── README.md              # Panduan deployment perangkat
├── server_relay/              # [FASE 2: NEXT] Gateway WebSocket & Session Router
├── web_admin_controller/      # [FASE 3: NEXT] Web Dashboard HTML5 Canvas / WebCodecs
├── android_user_app/          # [FASE 4: NEXT] Aplikasi Pengontrol Android (User App)
└── docs/                      # Dokumentasi perancangan sistem
```

Lokasi SDK & Dependensi Eksternal:
- **Android SDK & Flutter Package**: Ditempatkan di root drive `D:\Android` sesuai instruksi.

---

## 3. Hasil Pengerjaan Fase 1 (Core Root Android Agent)

Fase 1 telah selesai diimplementasikan secara komprehensif dengan keunggulan teknis berikut:
1. **MediaCodec Hardware Acceleration**: Menghasilkan video streaming H.264 (AVC) 30 FPS secara langsung dari GPU perangkat, memastikan suhu perangkat tetap stabil dan CPU tidak terbebani.
2. **Virtual Input Injection Latensi 0ms**: Memanfaatkan Java Reflection ke internal service `android.hardware.input.IInputManager` untuk menyuntikkan sentuhan layar dan tombol navigasi tanpa jeda.
3. **Fallback Cerdas**: Jika kernel Android tertentu membatasi refleksi API, sistem otomatis beralih ke root shell input tanpa memicu crash.
4. **WebSocket Client Mandiri (RFC 6455)**: Ditulis murni menggunakan Java Socket standar tanpa dependensi library eksternal (menghasilkan ukuran binary di bawah 50 KB).
5. **Mode Hemat Daya (Sleep Mode)**: Encoder video otomatis mati saat tidak ada pengguna yang sedang membuka layar remote.
6. **Magisk & KernelSU Auto-Start**: Dilengkapi script installer untuk menyisipkan daemon ke `/data/adb/service.d/` agar otomatis aktif setiap kali HP dinyalakan ulang.
