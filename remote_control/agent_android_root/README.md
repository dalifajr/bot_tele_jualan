# Remote Control Agent (Android Root Daemon)

Modul ini merupakan agen inti (*core daemon*) yang berjalan langsung di sistem operasi Android target dengan hak akses **Root (UID 0)**. Agen ini bertindak sebagai jembatan penangkap layar (*screen capture*) dan penyuntik sentuhan (*input injector*) tanpa memerlukan persetujuan dialog manual pengguna.

---

## 1. Arsitektur & Prinsip Kerja

Berbeda dengan aplikasi Android biasa yang berjalan di dalam *sandbox* aplikasi (`com.example.app`), agen ini dieksekusi melalui binary sistem Android `/system/bin/app_process`:
- **Hak Akses Penuh (Root)**: Berjalan di bawah user `root`, memiliki akses ke seluruh internal service Android (`SurfaceControl`, `InputManager`, driver kernel `/dev/input/event*`, serta shell Linux).
- **Efisiensi Hardware (GPU)**: Menggunakan hardware encoder `MediaCodec` H.264 (AVC) bawaan chipset HP sehingga beban CPU rendah dan tidak membuat perangkat panas.
- **Koneksi Keluar (Outbound WebSocket)**: HP target menginisiasi koneksi keluar ke Central Relay Server. Dengan pendekatan ini, HP target tetap dapat dikontrol dari mana saja meskipun berada di balik jaringan seluler (CGNAT / tanpa IP publik).
- **Mode Hemat Baterai (Standby)**: Encoder video H.264 otomatis dimatikan (*sleep mode*) ketika tidak ada Controller (Web Admin atau User) yang sedang membuka layar, dan aktif seketika saat sesi remote dimulai.

---

## 2. Struktur Modul

```
agent_android_root/
├── src/main/java/com/remote/agent/
│   ├── Main.java                 # Entry point daemon & manajemen siklus hidup koneksi
│   ├── DeviceConfig.java         # Konfigurasi ID, Token, Resolusi, Bitrate, URL WebSocket
│   ├── ScreenEncoder.java        # MediaCodec H.264 hardware encoder via SurfaceControl
│   ├── InputDispatcher.java      # Virtual Input Injection (Touch & Keyevent) latensi 0ms
│   ├── AgentWebSocketClient.java # RFC 6455 WebSocket Client murni tanpa dependensi eksternal
│   └── ShellExecutor.java        # Eksekusi perintah Linux root jarak jauh
├── scripts/
│   ├── install_daemon.sh         # Script pemasangan ke /data/local/tmp dan Magisk service.d
│   ├── start_daemon.sh           # Menjalankan daemon di latar belakang (background runner)
│   └── stop_daemon.sh            # Menghentikan proses daemon
└── build.bat                     # Script kompilasi di Windows
```

---

## 3. Cara Kompilasi (Build ke JAR / DEX)

1. Jalankan `build.bat` untuk mengompilasi file Java menjadi bytecode `.class`.
2. Gunakan tool `d8` dari Android SDK (`D:\Android`) untuk mengonversi `.class` menjadi Dalvik Executable (`classes.dex` terbungkus `remote_agent.jar`):
   ```bash
   d8 --output build/remote_agent.jar build/classes/com/remote/agent/*.class
   ```

---

## 4. Cara Instalasi & Deploy ke HP Android Target

### Opsi A: Melalui ADB (Kabel Data / Wi-Fi Debugging)
```bash
# 1. Kirim binary jar ke perangkat
adb push build/remote_agent.jar /data/local/tmp/remote_agent.jar

# 2. Masuk ke shell root
adb shell
su

# 3. Jalankan script instalasi
sh /data/local/tmp/remote_agent.jar
```

### Opsi B: Melalui Aplikasi Terminal HP (Termux / Kali Nethunter)
1. Salin file `remote_agent.jar` ke memori internal HP.
2. Buka Termux, ketik:
   ```bash
   su
   mkdir -p /data/local/tmp/remote_control
   cp /sdcard/Download/remote_agent.jar /data/local/tmp/remote_control/
   chmod 755 /data/local/tmp/remote_control/remote_agent.jar
   ```

---

## 5. Cara Menjalankan Agen

### A. Menjalankan Manual untuk Pengujian
```bash
su
export CLASSPATH=/data/local/tmp/remote_control/remote_agent.jar
app_process /data/local/tmp/remote_control com.remote.agent.Main --url ws://IP_SERVER_VPS:8080/ws/device --id ANDROID_01
```

### B. Menjalankan di Background
```bash
sh /data/local/tmp/remote_control/start_daemon.sh ws://IP_SERVER_VPS:8080/ws/device ANDROID_01
```

### C. Auto-Start Otomatis Saat HP Booting (Magisk / KernelSU / APatch)
Script `install_daemon.sh` secara otomatis memasang berkas auto-start di:
`/data/adb/service.d/remote_agent_boot.sh`
Sehingga setiap kali HP dinyalakan ulang (*reboot*), agen akan otomatis menyala di latar belakang dan langsung terhubung ke server tanpa perlu tindakan fisik apa pun!

---

## 6. Protokol Kontrol yang Didukung

| Tipe Pesan | Payload JSON | Aksi di Perangkat |
| :--- | :--- | :--- |
| **Touch Down** | `{"type":"touch","action":"down","x":540,"y":1200}` | Menekan layar pada koordinat (540, 1200) |
| **Touch Move** | `{"type":"touch","action":"move","x":540,"y":800}` | Menggeser sentuhan layar (*drag/scroll*) |
| **Touch Up** | `{"type":"touch","action":"up","x":540,"y":800}` | Melepas sentuhan jari |
| **Tombol Fisik** | `{"type":"key","code":4}` | Menekan tombol BACK (Kode 4) atau HOME (Kode 3) |
| **Gestur Swipe** | `{"type":"swipe","startX":300,"startY":1500,"endX":300,"endY":500,"duration":300}` | Scroll cepat berdurasi 300ms |
| **Shell Root** | `{"type":"shell","command":"reboot"}` | Mengeksekusi command Linux root seketika |
