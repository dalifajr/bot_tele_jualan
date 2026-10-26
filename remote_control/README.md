# CYBERCONTROL :: Platform Remote Control Android Root Multi-Platform

Sistem kontrol jarak jauh (Remote Control) berkinerja tinggi untuk perangkat Android berakar (Root) dengan latensi ultra-rendah (< 50ms), hardware-accelerated video encoding (GPU H.264), injeksi input langsung tingkat kernel/framework (`IInputManager`), interactive root terminal shell, serta dikendalikan melalui **Web Admin Dashboard** dan **Aplikasi Android Pengendali (Flutter)**.

---

## 1. Arsitektur Komponen Sistem

```
+--------------------------------------------------------------------------+
|                       ANDROID ROOT TARGET (DAEMON)                       |
|   +---------------------+   +---------------------+   +--------------+   |
|   |  ScreenEncoder GPU  |   |   InputDispatcher   |   | ShellExecutor|   |
|   |  MediaCodec H.264   |   |   IInputManager     |   | Asynchronous |   |
|   +----------+----------+   +----------+----------+   +-------+------+   |
|              |                         ^                      ^          |
|              +-----------+             |                      |          |
|                          v             |                      |          |
|                 +-----------------------------------+         |          |
|                 |   AgentWebSocketClient (RFC6455)  |---------+          |
|                 +-----------------+-----------------+                    |
+-----------------------------------|--------------------------------------+
                                    | ws://.../ws/device (Binary + JSON)
                                    v
+--------------------------------------------------------------------------+
|                  CENTRAL RELAY & GATEWAY SERVER (FastAPI)                |
|   - SessionManager (Device Registry & Controller Attachment)             |
|   - Zero-Copy Binary Frame Broadcast to Controllers                      |
|   - Fast Command Multiplexing (Touch, Key, Swipe, Shell)                 |
|   - Sleep Mode Automatic Power Saving saat controller terputus           |
|   - REST API Telemetri (/api/devices, /api/health)                       |
+-------------------+-----------------------------------+------------------+
                    |                                   |
                    | ws://.../ws/controller            | ws://.../ws/controller
                    v                                   v
+--------------------------------------+   +-------------------------------+
|      WEB ADMIN DASHBOARD (HTML5)     |   |   ANDROID USER APP (FLUTTER)  |
| - JMuxer H.264 MSE Video Decoding    |   | - D:\Android\remote_controller|
| - 0ms Touch & Drag Gesture Mapping   |   | - Low-latency Gesture Control |
| - Android Nav Keys (Back, Home, etc) |   | - Hardware Nav Keys Bar       |
| - Interactive Root Terminal Shell    |   | - Bottom Sheet Root Terminal  |
| - Quick Tools (Text, URL, Reboot)    |   | - Device Discovery via REST   |
+--------------------------------------+   +-------------------------------+
```

---

## 2. Struktur Direktori Proyek

```
d:\bot_tele_jualan\remote_control\
├── agent_android_root\          # Fase 1: Daemon Agen Android Root
│   ├── build\remote_agent.jar   # Hasil kompilasi bytecode DEX teroptimasi
│   ├── src\main\java\com\remote\agent\
│   │   ├── Main.java            # Entry point app_process & auto-reconnect
│   │   ├── DeviceConfig.java    # Resolusi 720p, 30 FPS, Bitrate 1.8 Mbps
│   │   ├── ScreenEncoder.java   # GPU H.264 encoder via MediaCodec & SurfaceControl
│   │   ├── InputDispatcher.java # Injeksi input 0ms via IInputManager reflection
│   │   ├── AgentWebSocketClient.java # RFC 6455 WebSocket client mandiri
│   │   └── ShellExecutor.java   # Root terminal runner dengan streaming stdout/err
│   ├── scripts\
│   │   ├── install_daemon.sh    # Script pasang Magisk / KernelSU service.d
│   │   ├── start_daemon.sh      # Script start daemon manual
│   │   └── stop_daemon.sh       # Script stop daemon
│   └── build.bat                # Script kompilasi otomatis javac + d8
│
├── server_relay\                # Fase 2: Central Relay Server & Gateway
│   ├── config.py                # Konfigurasi port, host, token auth
│   ├── session_manager.py       # Pengelola sesi & broadcast frame
│   ├── server.py                # WebSocket multiplexer & REST API FastAPI
│   ├── run_server.bat           # Runner Windows
│   ├── run_server.sh            # Runner Linux / VPS
│   └── static\                  # Fase 3: Web Admin Controller Dashboard
│       ├── index.html           # UI Dashboard Modern Dark Cyber
│       ├── style.css            # Desain visual neon cyan & emerald
│       ├── app.js               # Engine decoder JMuxer & touch tracker
│       └── jmuxer.min.js        # Video demuxer H.264 MSE lokal
│
└── docs\                        # Dokumentasi & Evaluasi
    └── ARCHITECTURE_AND_EVALUATION.md

D:\Android\                      # SDK Android & Flutter Mobile App
├── platforms\android-13\android.jar
├── build-tools\android-13\d8.bat
├── platform-tools\adb.exe
└── remote_controller_app\       # Fase 4: Aplikasi Mobile Pengendali Flutter
    ├── pubspec.yaml
    ├── run_app.bat
    └── lib\
        ├── main.dart
        ├── config\constants.dart
        ├── models\device_session.dart
        ├── services\websocket_service.dart
        ├── screens\device_list_screen.dart
        ├── screens\remote_control_screen.dart
        └── widgets\terminal_sheet.dart
```

---

## 3. Cara Menjalankan & Menggunakan

### Langkah 1: Jalankan Relay Server (Host / VPS / Komputer)
Jalankan file batch:
```cmd
cd d:\bot_tele_jualan\remote_control\server_relay
run_server.bat
```
Server akan aktif di `http://0.0.0.0:8080/`.

### Langkah 2: Buka Web Admin Dashboard
Buka browser pada:
```
http://localhost:8080/
```
Dashboard akan memindai perangkat Android online secara otomatis.

### Langkah 3: Pasang & Jalankan Daemon di HP Android Target (Root)
1. Sambungkan HP Android dengan USB Debugging aktif:
```cmd
adb push d:\bot_tele_jualan\remote_control\agent_android_root\build\remote_agent.jar /data/local/tmp/
adb push d:\bot_tele_jualan\remote_control\agent_android_root\scripts\start_daemon.sh /data/local/tmp/
adb shell su -c "chmod +x /data/local/tmp/start_daemon.sh && /data/local/tmp/start_daemon.sh"
```
2. Atau pasang otomatis saat HP boot via Magisk / KernelSU:
```cmd
adb push d:\bot_tele_jualan\remote_control\agent_android_root\build\remote_agent.jar /data/local/tmp/
adb push d:\bot_tele_jualan\remote_control\agent_android_root\scripts\install_daemon.sh /data/local/tmp/
adb shell su -c "sh /data/local/tmp/install_daemon.sh"
```

### Langkah 4: Kontrol Melalui Aplikasi Android Mobile (Opsional)
Buka folder `D:\Android\remote_controller_app` melalui editor Flutter / VS Code, lalu jalankan:
```cmd
run_app.bat
```
Masukkan IP server relay pada kartu konfigurasi, pilih perangkat aktif dari daftar, lalu mulai remote touch dan terminal.
