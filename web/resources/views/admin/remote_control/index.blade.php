@extends('layouts.app')

@section('title', __('Remote Control HP (Root)'))
@section('page_subtitle', __('Remote Control'))

@push('styles')
<style>
    .remote-wrapper {
        background: #0a0d14;
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        overflow: hidden;
        color: #f8fafc;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    }
    .remote-header-bar {
        background: #111622;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding: 12px 20px;
    }
    .phone-stage-container {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 24px;
        background: radial-gradient(circle at center, rgba(6, 182, 212, 0.04) 0%, transparent 70%);
        min-height: 640px;
    }
    .phone-box {
        width: 340px;
        height: 680px;
        background: #000;
        border: 4px solid #1e293b;
        border-radius: 36px;
        box-shadow: 0 0 40px rgba(0,0,0,0.8), 0 0 25px rgba(6, 182, 212, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
    }
    .phone-notch-bar {
        height: 22px;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .phone-notch-bar .lens {
        width: 8px; height: 8px; background: #0f172a; border-radius: 50%; border: 1px solid rgba(255,255,255,0.2);
    }
    .phone-notch-bar .speaker {
        width: 36px; height: 3px; background: #1e293b; border-radius: 2px;
    }
    .screen-area {
        flex: 1;
        position: relative;
        background: #000;
        overflow: hidden;
        cursor: crosshair;
    }
    .screen-area video {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
    }
    .screen-area canvas {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        z-index: 10;
    }
    .virtual-bottom-nav {
        height: 48px;
        background: #000;
        border-top: 1px solid #1e293b;
        display: flex;
        align-items: center;
        justify-content: space-around;
        padding: 0 16px;
    }
    .nav-icon-btn {
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 16px;
        transition: all 0.2s;
        cursor: pointer;
        padding: 6px 12px;
        border-radius: 8px;
    }
    .nav-icon-btn:hover {
        color: #38bdf8;
        background: rgba(56, 189, 248, 0.1);
    }
    .device-card-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 16px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .device-card-item:hover, .device-card-item.active {
        background: #eff6ff;
        border-color: #3b82f6;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);
    }
    .web-terminal-box {
        background: #0f172a;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #334155;
        height: 250px;
        display: flex;
        flex-direction: column;
    }
    .term-body {
        flex: 1;
        padding: 12px;
        font-family: 'Fira Code', 'Courier New', monospace;
        font-size: 11px;
        line-height: 1.5;
        overflow-y: auto;
        color: #e2e8f0;
    }
    .device-badge-dot {
        width: 8px; height: 8px; border-radius: 50%; display: inline-block;
    }
    .touch-dot {
        position: absolute;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(6, 182, 212, 0.4);
        border: 2px solid #06b6d4;
        pointer-events: none;
        transform: translate(-50%, -50%) scale(0);
        animation: touchRipple 0.35s ease-out forwards;
    }
    @keyframes touchRipple {
        0% { transform: translate(-50%, -50%) scale(0.2); opacity: 1; }
        100% { transform: translate(-50%, -50%) scale(1.6); opacity: 0; }
    }
</style>
{{-- Script JMuxer Video Demuxer --}}
<script src="{{ asset('static_remote/jmuxer.min.js') }}" onerror="loadJmuxerCdn()"></script>
<script>
    function loadJmuxerCdn() {
        var s = document.createElement('script');
        s.src = "https://cdn.jsdelivr.net/npm/jmuxer@2.0.5/dist/jmuxer.min.js";
        document.head.appendChild(s);
    }
</script>
@endpush

@section('content')
<div class="container-fluid py-4">
    <!-- Header Banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold m-0 d-flex align-items-center gap-2">
                <i class="fas fa-mobile-screen-button text-primary"></i> 
                {{ __('Remote Control Perangkat Android (Root)') }}
                <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 0.65rem;">ROOT LEVEL</span>
            </h4>
            <p class="text-muted small m-0">{{ __('Kontrol penuh HP Android root via Relay Gateway VPS dengan latensi ultra-rendah.') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#settingsModal">
                <i class="fas fa-key me-1"></i> {{ __('Password & Auth') }}
            </button>
            <button class="btn btn-sm btn-outline-info rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#guideModal">
                <i class="fas fa-terminal me-1"></i> {{ __('Panduan HP Target') }}
            </button>
            <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#installerModal">
                <i class="fas fa-server me-1"></i> {{ __('Status & Installer VPS') }}
            </button>
            <button id="btnRefreshDeviceList" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="fas fa-rotate me-1"></i> {{ __('Refresh') }}
            </button>
        </div>
    </div>

    <!-- Alert Status Layanan VPS -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-body p-3 bg-light border-start border-4 {{ ($serviceStatus['is_running'] ?? false) ? 'border-success' : 'border-warning' }}">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center p-2 {{ ($serviceStatus['is_running'] ?? false) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}" style="width: 44px; height: 44px;">
                        <i class="fas {{ ($serviceStatus['is_running'] ?? false) ? 'fa-circle-check fs-5' : 'fa-triangle-exclamation fs-5' }}"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold">{{ __('Status Service Relay VPS:') }}</span>
                            @if($serviceStatus['is_running'] ?? false)
                                <span class="badge bg-success rounded-pill px-2.5 py-1"><i class="fas fa-signal me-1"></i> ONLINE (127.0.0.1:{{ $relayPort }})</span>
                            @elseif($serviceStatus['is_installed'] ?? false)
                                <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1"><i class="fas fa-pause me-1"></i> STOPPED / INACTIVE</span>
                            @else
                                <span class="badge bg-danger rounded-pill px-2.5 py-1"><i class="fas fa-circle-xmark me-1"></i> BELUM TERPASANG DI VPS</span>
                            @endif

                            @if($serviceStatus['is_nginx_configured'] ?? false)
                                <span class="badge bg-info-subtle text-info rounded-pill px-2 py-1"><i class="fas fa-network-wired me-1"></i> Nginx WSS OK</span>
                            @endif
                        </div>
                        <p class="text-muted small m-0 mt-1">
                            {{ $serviceStatus['detail'] ?? 'Memeriksa status daemon di port ' . $relayPort }}
                            @if(!($serviceStatus['is_running'] ?? false))
                                <span class="text-danger fw-semibold"> — HP Android akan menerima 404 Handshake sampai service & reverse proxy Nginx diaktifkan.</span>
                            @endif
                        </p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if(!($serviceStatus['is_running'] ?? false))
                        <button class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm fw-semibold" onclick="triggerVpsInstaller()">
                            <i class="fas fa-play me-1"></i> {{ __('Pasang / Aktifkan di VPS') }}
                        </button>
                    @else
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="triggerVpsRestart()">
                            <i class="fas fa-arrows-rotate me-1"></i> {{ __('Restart Daemon') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="row g-4">
        <!-- Center Phone Controller -->
        <div class="col-lg-6 col-xl-5">
            <div class="remote-wrapper">
                <div class="remote-header-bar d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <span id="activeDot" class="device-badge-dot bg-danger"></span>
                        <span id="activeDeviceTitle" class="fw-bold small text-truncate" style="max-width: 220px;">Menunggu Perangkat...</span>
                    </div>
                    <span id="fpsBadge" class="badge bg-dark border border-secondary text-info font-monospace small">0 FPS</span>
                </div>

                <div class="phone-stage-container">
                    <div class="phone-box">
                        <div class="phone-notch-bar">
                            <span class="lens"></span>
                            <span class="speaker"></span>
                        </div>
                        <div class="screen-area" id="screenContainer">
                            <video id="remoteVideoPlayer" autoplay muted playsinline></video>
                            <canvas id="remoteTouchCanvas"></canvas>
                            <div id="screenOverlayPlaceholder" class="d-flex flex-column align-items-center justify-content-center h-100 text-center p-4">
                                <i class="fas fa-satellite-dish fs-1 text-secondary mb-3"></i>
                                <h6 class="fw-bold text-white">{{ __('Belum Terhubung') }}</h6>
                                <p class="text-muted small mb-0">{{ __('Pilih perangkat Android aktif di panel sebelah kanan untuk memulai sesi.') }}</p>
                            </div>
                        </div>
                        <div class="virtual-bottom-nav">
                            <button id="btnNavBack" class="nav-icon-btn" title="Back"><i class="fas fa-chevron-left"></i></button>
                            <button id="btnNavHome" class="nav-icon-btn" title="Home"><i class="far fa-circle"></i></button>
                            <button id="btnNavRecents" class="nav-icon-btn" title="Recents"><i class="far fa-square"></i></button>
                            <button id="btnNavPower" class="nav-icon-btn text-danger" title="Power"><i class="fas fa-power-off"></i></button>
                            <button id="btnNavVolDown" class="nav-icon-btn" title="Vol -"><i class="fas fa-volume-low"></i></button>
                            <button id="btnNavVolUp" class="nav-icon-btn" title="Vol +"><i class="fas fa-volume-high"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Device List & Terminal & Actions -->
        <div class="col-lg-6 col-xl-7">
            <!-- Active Devices Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="fas fa-list-check text-primary"></i> {{ __('Daftar Perangkat Terhubung') }}
                    </h6>
                    <div id="deviceCardsContainer" class="d-flex flex-column gap-2">
                        @forelse($devices as $dev)
                            <div class="device-card-item d-flex justify-content-between align-items-center" onclick="connectToDevice({{ json_encode($dev) }})">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-success-subtle text-success p-2">
                                        <i class="fas fa-mobile-screen fs-5"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold m-0 text-dark">{{ $dev['device_name'] ?? 'Android Device' }}</h6>
                                        <span class="text-muted small font-monospace">ID: {{ $dev['device_id'] }} | IP: {{ $dev['ip'] ?? '-' }}</span>
                                    </div>
                                </div>
                                <span class="badge bg-success rounded-pill px-3 py-1.5"><i class="fas fa-link me-1"></i> Hubungkan</span>
                            </div>
                        @empty
                            <div id="noDevicesAlert" class="text-center py-4 text-muted">
                                <i class="fas fa-mobile-screen-button fs-2 text-secondary mb-2 opacity-50"></i>
                                <p class="small mb-1">{{ __('Belum ada HP Android yang terhubung ke Relay Gateway.') }}</p>
                                <span class="badge bg-light text-secondary border font-monospace">wss://{{ request()->getHost() }}/ws/device</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Terminal Root & Fast Controls -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="fas fa-terminal text-primary"></i> {{ __('Root Interactive Terminal & Fast Actions') }}
                    </h6>

                    <!-- Fast Actions Row -->
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="sendShell('input keyevent 26')"><i class="fas fa-moon me-1"></i> Screen Toggle</button>
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="sendShell('input swipe 300 1000 300 200 150')"><i class="fas fa-arrow-up me-1"></i> Swipe Up</button>
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="sendShell('input swipe 300 300 300 1100 150')"><i class="fas fa-arrow-down me-1"></i> Swipe Down</button>
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="sendShell('settings get system screen_brightness')"><i class="fas fa-sun me-1"></i> Cek Brightness</button>
                        <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="sendShell('reboot')"><i class="fas fa-rotate me-1"></i> Reboot HP</button>
                    </div>

                    <!-- Terminal Output Box -->
                    <div class="web-terminal-box mb-2">
                        <div class="term-body" id="webTermLogs">
                            <span class="text-success">[Terminal Root Terhubung] Siap mengeksekusi perintah Android Shell UID 0.</span><br>
                        </div>
                    </div>

                    <!-- Shell Input -->
                    <div class="input-group">
                        <span class="input-group-text bg-dark text-success font-monospace border-secondary">#</span>
                        <input type="text" id="shellInput" class="form-control font-monospace text-dark" placeholder="Masukkan perintah shell (misal: pm list packages, dumpsys battery)...">
                        <button id="btnSendShell" class="btn btn-primary px-3"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL 1: PENGATURAN PASSWORD & AUTH TOKEN --}}
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="settingsModalLabel">
                    <i class="fas fa-key text-primary me-2"></i>{{ __('Konfigurasi Autentikasi Remote Control') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.remote-control.settings.update') }}" method="POST">
                @csrf
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">{{ __('Device Auth Token (Token HP Target)') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-shield-alt text-muted"></i></span>
                            <input type="text" name="device_auth_token" class="form-control font-monospace" value="{{ $deviceAuthToken }}" required>
                        </div>
                        <small class="text-muted">{{ __('Token yang wajib dikirim oleh daemon Android Root saat melakukan handshake WebSocket.') }}</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">{{ __('Admin Secret Key (Kunci Web Controller)') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                            <input type="text" name="admin_secret_key" class="form-control font-monospace" value="{{ $adminSecretKey }}" required>
                        </div>
                        <small class="text-muted">{{ __('Kunci rahasia untuk otorisasi controller web admin jualan dan user controller app.') }}</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-dark">{{ __('Port Internal Relay Gateway (Loopback 127.0.0.1)') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-network-wired text-muted"></i></span>
                            <input type="number" name="relay_port" class="form-control font-monospace" value="{{ $relayPort }}" min="1024" max="65535" required>
                        </div>
                        <small class="text-success"><i class="fas fa-check-circle me-1"></i>{{ __('Gunakan port 8099 agar terisolasi dari port 8080 Xray/HAProxy dan Bot Jualan.') }}</small>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">{{ __('Batal') }}</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">{{ __('Simpan Pengaturan') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL 2: PANDUAN KONEKSI HP TARGET --}}
<div class="modal fade" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="guideModalLabel">
                    <i class="fas fa-terminal text-info me-2"></i>{{ __('Panduan Menjalankan Agen di HP Android Root') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            </div>
            <div class="modal-body px-4 py-3">
                <p class="small text-muted mb-3">{{ __('Unduh binary terbaru atau jalankan perintah otomatis berikut di terminal root (MT Manager / Termux / adb shell):') }}</p>

                <!-- Tombol Download Cepat -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="/downloads/remote_agent.jar" class="btn btn-sm btn-outline-primary rounded-pill px-3" download>
                        <i class="fas fa-file-arrow-down me-1"></i> remote_agent.jar
                    </a>
                    <a href="/downloads/run_agent.sh" class="btn btn-sm btn-outline-info rounded-pill px-3" download>
                        <i class="fas fa-terminal me-1"></i> run_agent.sh
                    </a>
                    <a href="/downloads/remote_controller.apk" class="btn btn-sm btn-outline-success rounded-pill px-3" download>
                        <i class="fas fa-mobile-screen-button me-1"></i> Controller APK Android
                    </a>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">{{ __('1. Perintah Update & Jalankan Otomatis di HP Target (Direkomendasikan):') }}</label>
                    <div class="position-relative">
                        <pre class="bg-dark text-success p-3 rounded-3 small font-monospace m-0" id="cmdCurlAuto">su -c "curl -k -o /data/local/tmp/remote_agent.jar https://{{ request()->getHost() }}/downloads/remote_agent.jar && curl -k -o /data/local/tmp/run_agent.sh https://{{ request()->getHost() }}/downloads/run_agent.sh && chmod 755 /data/local/tmp/remote_agent.jar /data/local/tmp/run_agent.sh && sh /data/local/tmp/run_agent.sh"</pre>
                        <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-2" onclick="copyText('cmdCurlAuto')">
                            <i class="fas fa-copy"></i> Salin
                        </button>
                    </div>
                    <small class="text-muted d-block mt-1">Perintah di atas otomatis mengunduh binary terbaru, mengatur SELinux ke Permissive (mencegah error 137 Killed), dan menyalakan daemon.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">{{ __('2. Eksekusi Manual (Jika file sudah ada di /data/local/tmp/):') }}</label>
                    <div class="position-relative">
                        <pre class="bg-dark text-info p-3 rounded-3 small font-monospace m-0" id="cmdAppProcess">su
setenforce 0
pkill -9 -f com.remote.agent.Main 2>/dev/null
export CLASSPATH=/data/local/tmp/remote_agent.jar
exec app_process / com.remote.agent.Main --url wss://{{ request()->getHost() }}/ws/device --id ANDROID_01 --token {{ $deviceAuthToken }}</pre>
                        <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-2" onclick="copyText('cmdAppProcess')">
                            <i class="fas fa-copy"></i> Salin
                        </button>
                    </div>
                </div>

                <div class="alert alert-info rounded-3 small mb-0">
                    <i class="fas fa-info-circle me-1"></i> <strong>Catatan Android 14+ (Pixel 7):</strong> Pastikan menjalankan <code>setenforce 0</code> sebelum menjalankan app_process agar SELinux tidak mematikan koneksi dengan status <code>Killed (error 137)</code>.
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL 3: STATUS & INSTALLER SERVICE VPS --}}
<div class="modal fade" id="installerModal" tabindex="-1" aria-labelledby="installerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="installerModalLabel">
                    <i class="fas fa-server text-primary me-2"></i>{{ __('Status & Installer Service Remote Control VPS') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small d-block">{{ __('Status Daemon Relay') }}</span>
                            <span id="modalDaemonStatus" class="fw-bold {{ ($serviceStatus['is_running'] ?? false) ? 'text-success' : 'text-danger' }}">
                                <i class="fas {{ ($serviceStatus['is_running'] ?? false) ? 'fa-circle-check' : 'fa-circle-xmark' }} me-1"></i>
                                {{ ($serviceStatus['is_running'] ?? false) ? 'Aktif Berjalan' : 'Tidak Aktif' }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <span class="text-muted small d-block">{{ __('Reverse Proxy Nginx (/ws/)') }}</span>
                            <span id="modalNginxStatus" class="fw-bold {{ ($serviceStatus['is_nginx_configured'] ?? false) ? 'text-success' : 'text-warning' }}">
                                <i class="fas {{ ($serviceStatus['is_nginx_configured'] ?? false) ? 'fa-check' : 'fa-triangle-exclamation' }} me-1"></i>
                                {{ ($serviceStatus['is_nginx_configured'] ?? false) ? 'Terkonfigurasi' : 'Perlu Diterapkan' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">{{ __('Perintah Terminal SSH (Jika Dijalankan Manual):') }}</label>
                    <div class="position-relative">
                        <pre class="bg-dark text-warning p-3 rounded-3 small font-monospace m-0" id="cmdSshInstall">{{ $serviceStatus['ssh_command'] ?? 'sudo bash /var/www/bot_tele_jualan/remote_control/scripts/install_vps_service.sh' }}</pre>
                        <button class="btn btn-sm btn-outline-light position-absolute top-0 end-0 m-2" onclick="copyText('cmdSshInstall')">
                            <i class="fas fa-copy"></i> Salin
                        </button>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label fw-bold small text-dark">{{ __('Log Eksekusi Installer:') }}</label>
                    <div class="bg-dark text-light p-3 rounded-3 font-monospace small" style="height: 180px; overflow-y: auto;" id="installerLogBox">
                        <span class="text-muted">// Menunggu instruksi pemasangan atau restart...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" onclick="refreshVpsStatus()">
                    <i class="fas fa-rotate me-1"></i> {{ __('Periksa Ulang') }}
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-warning rounded-pill px-3" onclick="triggerVpsRestart()">
                        <i class="fas fa-arrows-rotate me-1"></i> {{ __('Restart Service') }}
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnRunInstaller" onclick="triggerVpsInstaller()">
                        <i class="fas fa-wrench me-1"></i> {{ __('Pasang / Terapkan Service di VPS') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const adminSecretKey = '{{ $adminSecretKey }}';
        let jmuxer = null;
        let ws = null;
        let activeDeviceId = null;
        let activeDeviceInfo = null;

        // Video & Canvas elements
        const videoEl = document.getElementById('remoteVideoPlayer');
        const touchCanvas = document.getElementById('remoteTouchCanvas');
        const screenContainer = document.getElementById('screenContainer');

        function fetchDevices() {
            fetch('{{ route("admin.remote-control.devices") }}')
                .then(res => res.json())
                .then(data => {
                    renderDeviceList(data.devices || []);
                })
                .catch(() => {});
        }

        function renderDeviceList(devices) {
            const container = document.getElementById('deviceCardsContainer');
            if (!container) return;

            if (devices.length === 0) {
                container.innerHTML = `
                    <div id="noDevicesAlert" class="text-center py-4 text-muted">
                        <i class="fas fa-mobile-screen-button fs-2 text-secondary mb-2 opacity-50"></i>
                        <p class="small mb-1">{{ __('Belum ada HP Android yang terhubung ke Relay Gateway.') }}</p>
                        <span class="badge bg-light text-secondary border font-monospace">wss://{{ request()->getHost() }}/ws/device</span>
                    </div>
                `;
                return;
            }

            let html = '';
            devices.forEach(dev => {
                const isActive = activeDeviceId === dev.device_id ? 'active' : '';
                html += `
                    <div class="device-card-item d-flex justify-content-between align-items-center ${isActive}" onclick='connectToDevice(${JSON.stringify(dev)})'>
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-circle bg-success-subtle text-success p-2">
                                <i class="fas fa-mobile-screen fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold m-0 text-dark">${dev.device_name || 'Android Device'}</h6>
                                <span class="text-muted small font-monospace">ID: ${dev.device_id} | IP: ${dev.ip || '-'}</span>
                            </div>
                        </div>
                        <span class="badge bg-success rounded-pill px-3 py-1.5"><i class="fas fa-link me-1"></i> Hubungkan</span>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        window.connectToDevice = function(device) {
            activeDeviceId = device.device_id;
            activeDeviceInfo = device;

            document.getElementById('activeDeviceTitle').textContent = device.device_name;
            document.getElementById('activeDot').className = 'device-badge-dot bg-warning';

            if (ws) {
                try { ws.close(); } catch(e) {}
            }
            if (jmuxer) {
                try { jmuxer.destroy(); } catch(e) {}
            }

            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsHost = window.location.protocol === 'https:' 
                ? window.location.host 
                : (window.location.port ? window.location.host : window.location.hostname + ':{{ $relayPort }}');
            const wsUrl = `${protocol}//${wsHost}/ws/controller?device_id=${device.device_id}&token=${adminSecretKey}`;

            let framesCount = 0;
            let lastFpsUpdate = performance.now();
            let pingInterval = null;

            try {
                jmuxer = new JMuxer({
                    node: 'remoteVideoPlayer',
                    mode: 'video',
                    flushingTime: 30,
                    clearBuffer: true,
                    fps: 30,
                    debug: false
                });
            } catch(e) {}

            ws = new WebSocket(wsUrl);
            ws.binaryType = 'arraybuffer';

            ws.onopen = function() {
                document.getElementById('activeDot').className = 'device-badge-dot bg-success';
                document.getElementById('screenOverlayPlaceholder').style.display = 'none';
                appendLog('[Berhasil] Terhubung ke sesi Android ' + device.device_name, 'text-success');

                // Minta device memulai transmisi video
                send({ type: 'start_stream' });

                // Heartbeat keep-alive setiap 5 detik
                if (pingInterval) clearInterval(pingInterval);
                pingInterval = setInterval(() => {
                    send({ type: 'ping' });
                }, 5000);
            };

            ws.onmessage = function(event) {
                if (event.data instanceof ArrayBuffer) {
                    framesCount++;
                    const now = performance.now();
                    if (now - lastFpsUpdate >= 1000) {
                        const currentFps = Math.round((framesCount * 1000) / (now - lastFpsUpdate));
                        const fpsBadgeEl = document.getElementById('fpsBadge');
                        if (fpsBadgeEl) fpsBadgeEl.textContent = currentFps + ' FPS';
                        framesCount = 0;
                        lastFpsUpdate = now;
                    }

                    if (videoEl && videoEl.paused) {
                        videoEl.play().catch(() => {});
                    }

                    if (jmuxer) {
                        jmuxer.feed({ video: new Uint8Array(event.data) });
                    }
                } else if (typeof event.data === 'string') {
                    try {
                        const msg = JSON.parse(event.data);
                        if (msg.type === 'shell_output' || msg.type === 'event') {
                            appendLog(msg.output || msg.data || event.data);
                        }
                    } catch(e) {
                        appendLog(event.data);
                    }
                }
            };

            ws.onclose = function() {
                if (pingInterval) clearInterval(pingInterval);
                document.getElementById('activeDot').className = 'device-badge-dot bg-danger';
                document.getElementById('screenOverlayPlaceholder').style.display = 'flex';
                document.getElementById('fpsBadge').textContent = '0 FPS';
                appendLog('[Peringatan] Koneksi terputus dari perangkat.', 'text-danger');
            };
        };

        function send(payload) {
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify(payload));
            }
        }

        window.sendShell = function(cmd) {
            if (!activeDeviceId) {
                alert('Pilih perangkat Android aktif terlebih dahulu.');
                return;
            }
            appendLog('# ' + cmd, 'text-warning');
            send({ type: 'shell', command: cmd });
        };

        // Navigation Buttons
        document.getElementById('btnNavBack').onclick = () => send({ type: 'key', action: 'click', code: 4 });
        document.getElementById('btnNavHome').onclick = () => send({ type: 'key', action: 'click', code: 3 });
        document.getElementById('btnNavRecents').onclick = () => send({ type: 'key', action: 'click', code: 187 });
        document.getElementById('btnNavPower').onclick = () => send({ type: 'key', action: 'click', code: 26 });
        document.getElementById('btnNavVolDown').onclick = () => send({ type: 'key', action: 'click', code: 25 });
        document.getElementById('btnNavVolUp').onclick = () => send({ type: 'key', action: 'click', code: 24 });

        // Shell Input Box
        document.getElementById('btnSendShell').onclick = function() {
            const inp = document.getElementById('shellInput');
            if (inp.value.trim()) {
                sendShell(inp.value.trim());
                inp.value = '';
            }
        };

        document.getElementById('shellInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('btnSendShell').click();
            }
        });

        // Touch Input Handler
        let isTouching = false;
        function getCoords(e) {
            const rect = touchCanvas.getBoundingClientRect();
            const clientX = e.clientX || (e.touches && e.touches[0].clientX);
            const clientY = e.clientY || (e.touches && e.touches[0].clientY);
            const x = (clientX - rect.left) / rect.width;
            const y = (clientY - rect.top) / rect.height;
            return { x: Math.max(0, Math.min(1, x)), y: Math.max(0, Math.min(1, y)) };
        }

        touchCanvas.addEventListener('mousedown', function(e) {
            if (!activeDeviceId) return;
            isTouching = true;
            const c = getCoords(e);
            send({ type: 'touch', action: 'down', x: c.x, y: c.y });
        });

        touchCanvas.addEventListener('mousemove', function(e) {
            if (!isTouching || !activeDeviceId) return;
            const c = getCoords(e);
            send({ type: 'touch', action: 'move', x: c.x, y: c.y });
        });

        window.addEventListener('mouseup', function() {
            if (isTouching && activeDeviceId) {
                isTouching = false;
                send({ type: 'touch', action: 'up' });
            }
        });

        document.getElementById('btnRefreshDeviceList').onclick = fetchDevices;

        function appendLog(txt, cls = '') {
            const box = document.getElementById('webTermLogs');
            const l = document.createElement('div');
            l.className = cls;
            l.textContent = txt;
            box.appendChild(l);
            box.scrollTop = box.scrollHeight;
        }

        fetchDevices();
        setInterval(fetchDevices, 5000);
    });

    // Helper Salin Teks
    function copyText(id) {
        const text = document.getElementById(id).innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('Perintah berhasil disalin ke clipboard!');
        });
    }

    // Eksekusi Installer VPS via AJAX
    function triggerVpsInstaller() {
        const logBox = document.getElementById('installerLogBox');
        const btn = document.getElementById('btnRunInstaller');
        logBox.innerHTML = '<span class="text-warning">[Memulai] Menjalankan skrip installer service VPS & reload Nginx... Mohon tunggu.</span><br>';
        btn.disabled = true;

        fetch('{{ route("admin.remote-control.install-service") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            logBox.innerHTML += `<pre class="text-light m-0 mt-2">${data.output || 'Proses selesai.'}</pre>`;
            refreshVpsStatus();
        })
        .catch(err => {
            btn.disabled = false;
            logBox.innerHTML += `<span class="text-danger">[Error] ${err.message}</span>`;
        });
    }

    // Eksekusi Restart Service VPS via AJAX
    function triggerVpsRestart() {
        const logBox = document.getElementById('installerLogBox');
        logBox.innerHTML = '<span class="text-info">[Info] Mengirim sinyal restart remote_control.service...</span><br>';

        fetch('{{ route("admin.remote-control.restart-service") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            logBox.innerHTML += `<span class="text-success">[Sukses] ${data.message}</span><br>`;
            refreshVpsStatus();
        })
        .catch(err => {
            logBox.innerHTML += `<span class="text-danger">[Error] ${err.message}</span>`;
        });
    }

    // Periksa Ulang Status Layanan VPS
    function refreshVpsStatus() {
        fetch('{{ route("admin.remote-control.service-status") }}')
            .then(res => res.json())
            .then(data => {
                if (data.status) {
                    const st = data.status;
                    const daemonEl = document.getElementById('modalDaemonStatus');
                    const nginxEl = document.getElementById('modalNginxStatus');
                    if (daemonEl) {
                        daemonEl.className = 'fw-bold ' + (st.is_running ? 'text-success' : 'text-danger');
                        daemonEl.innerHTML = `<i class="fas ${st.is_running ? 'fa-circle-check' : 'fa-circle-xmark'} me-1"></i> ${st.is_running ? 'Aktif Berjalan' : 'Tidak Aktif'}`;
                    }
                    if (nginxEl) {
                        nginxEl.className = 'fw-bold ' + (st.is_nginx_configured ? 'text-success' : 'text-warning');
                        nginxEl.innerHTML = `<i class="fas ${st.is_nginx_configured ? 'fa-check' : 'fa-triangle-exclamation'} me-1"></i> ${st.is_nginx_configured ? 'Terkonfigurasi' : 'Perlu Diterapkan'}`;
                    }
                }
            })
            .catch(() => {});
    }
</script>
@endpush
@endsection
