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
        width: 100%; height: 100%; object-fit: contain; pointer-events: none;
    }
    .screen-area canvas {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10;
    }
    .virtual-bottom-nav {
        height: 44px;
        background: #000;
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 0 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }
    .nav-icon-btn {
        background: transparent;
        border: none;
        color: #64748b;
        font-size: 1.1rem;
        padding: 6px 14px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .nav-icon-btn:hover {
        color: #06b6d4;
        background: rgba(255, 255, 255, 0.05);
    }
    .terminal-window {
        background: #05070a;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        display: flex;
        flex-direction: column;
        height: 380px;
        overflow: hidden;
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
            <p class="text-muted small m-0">{{ __('Kontrol penuh HP Android root via Relay Gateway VPS dengan latensi 0ms.') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button id="btnRefreshDeviceList" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="fas fa-rotate me-1"></i> {{ __('Refresh Perangkat') }}
            </button>
            <span id="gatewayStatusBadge" class="badge bg-secondary rounded-pill px-3 py-2">
                <i class="fas fa-circle-nodes me-1"></i> Gateway: Disconnected
            </span>
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
                        <div class="text-center py-3 text-muted small">
                            <i class="fas fa-spinner fa-spin me-1"></i> Memeriksa perangkat Android...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terminal Root Console -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-dark text-white">
                <div class="card-header bg-transparent border-secondary border-opacity-25 d-flex justify-content-between align-items-center p-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-terminal text-info"></i>
                        <span class="fw-bold small font-monospace">Root Interactive Shell (UID: 0)</span>
                    </div>
                    <button id="btnClearWebTerm" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;">Clear</button>
                </div>
                <div class="card-body p-0">
                    <div class="terminal-window">
                        <div id="webTermLogs" class="term-body">
                            <div class="text-secondary">[Android Root Terminal Siap]</div>
                        </div>
                        <form id="webTermForm" class="d-flex p-2 bg-black border-top border-secondary border-opacity-25">
                            <span class="text-danger fw-bold font-monospace me-2">#</span>
                            <input type="text" id="webTermInput" class="form-control form-control-sm bg-transparent border-0 text-white font-monospace" placeholder="Ketik perintah shell (misal: ps -A, df -h, getprop)..." autocomplete="off">
                            <button type="submit" class="btn btn-sm btn-info px-3 fw-bold">Kirim</button>
                        </form>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-secondary border-opacity-25 p-2 d-flex flex-wrap gap-1">
                    <button class="btn btn-xs btn-outline-info rounded-pill py-1 px-2 term-shortcut" data-cmd="dumpsys battery | grep level">Cek Baterai</button>
                    <button class="btn btn-xs btn-outline-info rounded-pill py-1 px-2 term-shortcut" data-cmd="df -h /data">Sisa Storage</button>
                    <button class="btn btn-xs btn-outline-info rounded-pill py-1 px-2 term-shortcut" data-cmd="getprop ro.product.model">Model HP</button>
                    <button class="btn btn-xs btn-outline-info rounded-pill py-1 px-2 term-shortcut" data-cmd="top -n 1 -m 5">Top Proses</button>
                    <button class="btn btn-xs btn-outline-danger rounded-pill py-1 px-2 term-shortcut" data-cmd="reboot">Reboot HP</button>
                </div>
            </div>

            <!-- Quick Hardware Actions -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-3"><i class="fas fa-bolt text-warning me-1"></i> {{ __('Aksi Cepat Perangkat') }}</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <input type="text" id="webTextInput" class="form-control" placeholder="Teks untuk diketik di HP...">
                                <button id="btnWebSendText" class="btn btn-primary"><i class="fas fa-keyboard me-1"></i> Kirim</button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <input type="text" id="webUrlInput" class="form-control" placeholder="URL web (https://...)...">
                                <button id="btnWebOpenUrl" class="btn btn-success"><i class="fas fa-globe me-1"></i> Buka</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const relayWsBase = "{{ $relayWsUrl }}";
        const adminSecretKey = "{{ $adminKey }}";
        
        let activeDeviceId = null;
        let activeDeviceInfo = null;
        let ws = null;
        let jmuxer = null;
        let isMouseDown = false;

        const canvas = document.getElementById('remoteTouchCanvas');
        const video = document.getElementById('remoteVideoPlayer');
        const container = document.getElementById('screenContainer');

        // Polling Perangkat
        function fetchDevices() {
            fetch("{{ route('admin.remote-control.devices') }}")
                .then(r => r.json())
                .then(data => renderDevices(data.devices || []))
                .catch(() => {});
        }

        function renderDevices(devices) {
            const container = document.getElementById('deviceCardsContainer');
            if (devices.length === 0) {
                container.innerHTML = '<div class="alert alert-light border text-center small text-muted py-2 m-0">Tidak ada perangkat Android yang online saat ini.</div>';
                return;
            }

            container.innerHTML = devices.map(d => `
                <div class="p-3 border rounded-3 d-flex justify-content-between align-items-center bg-body ${activeDeviceId === d.device_id ? 'border-primary shadow-sm' : ''}">
                    <div>
                        <div class="fw-bold">${d.device_name}</div>
                        <small class="text-muted font-monospace">ID: ${d.device_id} • ${d.screen_width}x${d.screen_height}</small>
                    </div>
                    <button class="btn btn-sm ${activeDeviceId === d.device_id ? 'btn-success' : 'btn-primary'} rounded-pill px-3 btn-select-device" data-id="${d.device_id}">
                        ${activeDeviceId === d.device_id ? '<i class="fas fa-check me-1"></i> Terhubung' : 'Pilih & Kontrol'}
                    </button>
                </div>
            `).join('');

            document.querySelectorAll('.btn-select-device').forEach(b => {
                b.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const dev = devices.find(x => x.device_id === id);
                    if (dev) connectToDevice(dev);
                });
            });

            if (!activeDeviceId && devices.length > 0) {
                connectToDevice(devices[0]);
            }
        }

        function connectToDevice(device) {
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
            // Pada produksi HTTPS (misal belajaridn.id), WebSocket dialihkan lewat Nginx port 443 (/ws/...)
            // Pada mode development lokal HTTP, gunakan port 8080 jika tidak ada reverse proxy
            const wsHost = window.location.protocol === 'https:' 
                ? window.location.host 
                : (window.location.port ? window.location.host : window.location.hostname + ':8080');
            const wsUrl = `${protocol}//${wsHost}/ws/controller?device_id=${device.device_id}&token=${adminSecretKey}`;

            try {
                jmuxer = new JMuxer({
                    node: 'remoteVideoPlayer',
                    mode: 'video',
                    flushingTime: 0,
                    clearBuffer: true,
                    fps: 30,
                    debug: false
                });
            } catch(e) {}

            ws = new WebSocket(wsUrl);
            ws.binaryType = 'arraybuffer';

            ws.onopen = function() {
                document.getElementById('activeDot').className = 'device-badge-dot bg-success';
                document.getElementById('gatewayStatusBadge').className = 'badge bg-success rounded-pill px-3 py-2';
                document.getElementById('gatewayStatusBadge').innerHTML = '<i class="fas fa-circle-check me-1"></i> Gateway: Connected';
                document.getElementById('screenOverlayPlaceholder').style.display = 'none';
                appendLog('[Berhasil] Terhubung ke sesi Android ' + device.device_name, 'text-success');
                resizeCanvas();
            };

            ws.onmessage = function(e) {
                if (e.data instanceof ArrayBuffer) {
                    if (jmuxer) jmuxer.feed({ video: new Uint8Array(e.data) });
                } else {
                    try {
                        const msg = JSON.parse(e.data);
                        if (msg.type === 'shell_output') {
                            appendLog(msg.line, msg.is_error ? 'text-danger' : '');
                        }
                    } catch(err) {}
                }
            };

            ws.onclose = function() {
                document.getElementById('activeDot').className = 'device-badge-dot bg-danger';
                document.getElementById('screenOverlayPlaceholder').style.display = 'flex';
                appendLog('[Info] Koneksi WebSocket terputus.', 'text-muted');
            };
        }

        function resizeCanvas() {
            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight;
        }
        window.addEventListener('resize', resizeCanvas);

        // Touch Ingestion
        canvas.addEventListener('mousedown', function(e) {
            if (!ws || ws.readyState !== WebSocket.OPEN) return;
            isMouseDown = true;
            const c = getCoords(e);
            if (c) {
                showRipple(e.clientX, e.clientY);
                send({ type: 'touch', action: 'down', x: c.x, y: c.y });
            }
        });
        window.addEventListener('mousemove', function(e) {
            if (!isMouseDown || !ws || ws.readyState !== WebSocket.OPEN) return;
            const c = getCoords(e);
            if (c) send({ type: 'touch', action: 'move', x: c.x, y: c.y });
        });
        window.addEventListener('mouseup', function(e) {
            if (!isMouseDown) return;
            isMouseDown = false;
            if (!ws || ws.readyState !== WebSocket.OPEN) return;
            const c = getCoords(e);
            if (c) send({ type: 'touch', action: 'up', x: c.x, y: c.y });
        });

        function getCoords(e) {
            const rect = canvas.getBoundingClientRect();
            if (rect.width <= 0 || rect.height <= 0) return null;
            const rx = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            const ry = Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height));
            const tw = activeDeviceInfo ? activeDeviceInfo.screen_width : 720;
            const th = activeDeviceInfo ? activeDeviceInfo.screen_height : 1280;
            return { x: Math.round(rx * tw), y: Math.round(ry * th) };
        }

        function showRipple(cx, cy) {
            const rect = container.getBoundingClientRect();
            const d = document.createElement('div');
            d.className = 'touch-dot';
            d.style.left = (cx - rect.left) + 'px';
            d.style.top = (cy - rect.top) + 'px';
            container.appendChild(d);
            setTimeout(() => d.remove(), 350);
        }

        function send(obj) {
            if (ws && ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify(obj));
        }

        // Nav Keys
        document.getElementById('btnNavBack').onclick = () => send({ type: 'key', code: 4 });
        document.getElementById('btnNavHome').onclick = () => send({ type: 'key', code: 3 });
        document.getElementById('btnNavRecents').onclick = () => send({ type: 'key', code: 187 });
        document.getElementById('btnNavPower').onclick = () => send({ type: 'key', code: 26 });
        document.getElementById('btnNavVolDown').onclick = () => send({ type: 'key', code: 25 });
        document.getElementById('btnNavVolUp').onclick = () => send({ type: 'key', code: 24 });

        // Terminal Shell
        document.getElementById('webTermForm').onsubmit = function(e) {
            e.preventDefault();
            const inp = document.getElementById('webTermInput');
            const cmd = inp.value.trim();
            if (!cmd) return;
            appendLog('# ' + cmd, 'text-info fw-bold');
            send({ type: 'shell', command: cmd });
            inp.value = '';
        };

        document.querySelectorAll('.term-shortcut').forEach(b => {
            b.onclick = function() {
                const cmd = this.getAttribute('data-cmd');
                if (cmd) {
                    appendLog('# ' + cmd, 'text-info fw-bold');
                    send({ type: 'shell', command: cmd });
                }
            };
        });

        document.getElementById('btnClearWebTerm').onclick = () => {
            document.getElementById('webTermLogs').innerHTML = '<div class="text-secondary">[Terminal dibersihkan]</div>';
        };

        // Quick Input
        document.getElementById('btnWebSendText').onclick = () => {
            const inp = document.getElementById('webTextInput');
            const t = inp.value;
            if (t) {
                const s = t.replace(/ /g, '%s');
                send({ type: 'shell', command: `input text "${s}"` });
                inp.value = '';
            }
        };

        document.getElementById('btnWebOpenUrl').onclick = () => {
            const inp = document.getElementById('webUrlInput');
            let u = inp.value.trim();
            if (u) {
                if (!u.startsWith('http://') && !u.startsWith('https://')) u = 'https://' + u;
                send({ type: 'shell', command: `am start -a android.intent.action.VIEW -d "${u}"` });
                inp.value = '';
            }
        };

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
</script>
@endpush
