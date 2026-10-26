/**
 * CYBERCONTROL :: Android Root Remote Gateway Client Engine
 * Multiplexing Low-Latency H.264 Video Decoding via JMuxer,
 * Real-Time 0ms Touch Injection & Interactive Root Terminal.
 */

class RemoteControlApp {
    constructor() {
        this.selectedDeviceId = null;
        this.deviceInfo = null;
        this.ws = null;
        this.jmuxer = null;
        
        // State Telemetri
        this.frameCount = 0;
        this.lastFpsUpdate = performance.now();
        this.fps = 0;
        this.fpsTimer = null;

        // Touch State
        this.isMouseDown = false;
        this.touchCanvas = document.getElementById('touchOverlay');
        this.videoPlayer = document.getElementById('player');
        this.touchCtx = this.touchCanvas.getContext('2d');

        // History Shell
        this.shellHistory = [];
        this.historyIndex = -1;

        this.initDOMElements();
        this.bindEvents();
        this.startDevicePolling();
    }

    initDOMElements() {
        this.dom = {
            deviceList: document.getElementById('deviceListContainer'),
            deviceCount: document.getElementById('deviceCount'),
            statusDot: document.getElementById('statusIndicator'),
            statusText: document.getElementById('statusText'),
            authKey: document.getElementById('authKeyInput'),
            btnRefresh: document.getElementById('btnRefreshDevices'),
            
            // Screen Viewport
            viewport: document.getElementById('screenViewport'),
            placeholder: document.getElementById('screenPlaceholder'),
            placeholderTitle: document.getElementById('placeholderTitle'),
            placeholderSubtitle: document.getElementById('placeholderSubtitle'),
            btnConnectCurrent: document.getElementById('btnConnectCurrent'),

            // Telemetri
            valStreamStatus: document.getElementById('valStreamStatus'),
            valResolution: document.getElementById('valResolution'),
            valFps: document.getElementById('valFps'),
            valLatency: document.getElementById('valLatency'),

            // Terminal
            terminalLog: document.getElementById('terminalLog'),
            terminalForm: document.getElementById('terminalForm'),
            terminalInput: document.getElementById('terminalInput'),
            btnClearTerminal: document.getElementById('btnClearTerminal'),

            // Info Tab
            infoDeviceId: document.getElementById('infoDeviceId'),
            infoDeviceName: document.getElementById('infoDeviceName'),
            infoResolution: document.getElementById('infoResolution'),
            infoUptime: document.getElementById('infoUptime'),
            
            // Shortcuts
            textToInject: document.getElementById('textToInject'),
            btnSendText: document.getElementById('btnSendText'),
            urlToOpen: document.getElementById('urlToOpen'),
            btnOpenUrl: document.getElementById('btnOpenUrl'),
            btnRebootNormal: document.getElementById('btnRebootNormal'),
            btnRebootRecovery: document.getElementById('btnRebootRecovery'),
            btnRebootBootloader: document.getElementById('btnRebootBootloader'),

            // Hardware Nav Keys
            btnKeyBack: document.getElementById('btnKeyBack'),
            btnKeyHome: document.getElementById('btnKeyHome'),
            btnKeyRecents: document.getElementById('btnKeyRecents'),
            btnPower: document.getElementById('btnPower'),
            btnVolDown: document.getElementById('btnVolDown'),
            btnVolUp: document.getElementById('btnVolUp'),
            btnNotification: document.getElementById('btnNotification'),
            btnWakeScreen: document.getElementById('btnWakeScreen')
        };
    }

    bindEvents() {
        // Tab Switcher
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                const targetId = btn.getAttribute('data-tab');
                const target = document.getElementById(targetId);
                if (target) target.classList.add('active');
            });
        });

        // Refresh Perangkat
        this.dom.btnRefresh.addEventListener('click', () => this.fetchDevices());
        this.dom.btnConnectCurrent.addEventListener('click', () => {
            if (this.selectedDeviceId) this.connectToDevice(this.selectedDeviceId);
        });

        // Touch Interaction pada Canvas
        this.touchCanvas.addEventListener('mousedown', (e) => this.handlePointerDown(e));
        window.addEventListener('mousemove', (e) => this.handlePointerMove(e));
        window.addEventListener('mouseup', (e) => this.handlePointerUp(e));

        // Touch Mobile Touchscreen (untuk responsive controller di HP)
        this.touchCanvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            if (e.touches.length > 0) this.handlePointerDown(e.touches[0]);
        }, { passive: false });
        this.touchCanvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            if (e.touches.length > 0) this.handlePointerMove(e.touches[0]);
        }, { passive: false });
        this.touchCanvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            this.handlePointerUp(e);
        }, { passive: false });

        // Virtual Navigation Keys
        this.dom.btnKeyBack.addEventListener('click', () => this.sendKey(4));       // KEYCODE_BACK
        this.dom.btnKeyHome.addEventListener('click', () => this.sendKey(3));       // KEYCODE_HOME
        this.dom.btnKeyRecents.addEventListener('click', () => this.sendKey(187)); // KEYCODE_APP_SWITCH
        this.dom.btnPower.addEventListener('click', () => this.sendKey(26));       // KEYCODE_POWER
        this.dom.btnVolDown.addEventListener('click', () => this.sendKey(25));     // KEYCODE_VOLUME_DOWN
        this.dom.btnVolUp.addEventListener('click', () => this.sendKey(24));       // KEYCODE_VOLUME_UP
        this.dom.btnWakeScreen.addEventListener('click', () => this.sendKey(224)); // KEYCODE_WAKEUP

        // Swipe Pull Down Notification
        this.dom.btnNotification.addEventListener('click', () => {
            const w = this.deviceInfo ? this.deviceInfo.screen_width : 720;
            const h = this.deviceInfo ? this.deviceInfo.screen_height : 1280;
            this.sendSwipe(w / 2, 10, w / 2, h * 0.65, 300);
        });

        // Terminal Shell Input
        this.dom.terminalForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const cmd = this.dom.terminalInput.value.trim();
            if (!cmd) return;
            this.sendShellCommand(cmd);
            this.shellHistory.push(cmd);
            this.historyIndex = this.shellHistory.length;
            this.dom.terminalInput.value = '';
        });

        // Terminal History Navigation
        this.dom.terminalInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowUp') {
                if (this.historyIndex > 0) {
                    this.historyIndex--;
                    this.dom.terminalInput.value = this.shellHistory[this.historyIndex];
                }
            } else if (e.key === 'ArrowDown') {
                if (this.historyIndex < this.shellHistory.length - 1) {
                    this.historyIndex++;
                    this.dom.terminalInput.value = this.shellHistory[this.historyIndex];
                } else {
                    this.historyIndex = this.shellHistory.length;
                    this.dom.terminalInput.value = '';
                }
            }
        });

        this.dom.btnClearTerminal.addEventListener('click', () => {
            this.dom.terminalLog.innerHTML = '<div class="term-line sys-msg">Terminal dibersihkan.</div>';
        });

        // Preset Command Buttons
        document.querySelectorAll('.preset-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const cmd = btn.getAttribute('data-cmd');
                if (cmd) this.sendShellCommand(cmd);
            });
        });

        // Shortcut Actions
        this.dom.btnSendText.addEventListener('click', () => {
            const text = this.dom.textToInject.value;
            if (!text) return;
            // Sanitasi karakter spasi untuk shell input text
            const sanitized = text.replace(/ /g, '%s');
            this.sendShellCommand(`input text "${sanitized}"`);
            this.dom.textToInject.value = '';
        });

        this.dom.btnOpenUrl.addEventListener('click', () => {
            let url = this.dom.urlToOpen.value.trim();
            if (!url) return;
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                url = 'https://' + url;
            }
            this.sendShellCommand(`am start -a android.intent.action.VIEW -d "${url}"`);
            this.dom.urlToOpen.value = '';
        });

        this.dom.btnRebootNormal.addEventListener('click', () => {
            if (confirm('Reboot perangkat Android sekarang?')) this.sendShellCommand('reboot');
        });
        this.dom.btnRebootRecovery.addEventListener('click', () => {
            if (confirm('Reboot ke Recovery mode?')) this.sendShellCommand('reboot recovery');
        });
        this.dom.btnRebootBootloader.addEventListener('click', () => {
            if (confirm('Reboot ke Bootloader / Fastboot?')) this.sendShellCommand('reboot bootloader');
        });

        // Window Resize: Sesuaikan ukuran canvas overlay dengan video player
        window.addEventListener('resize', () => this.resizeCanvas());
    }

    startDevicePolling() {
        this.fetchDevices();
        setInterval(() => this.fetchDevices(), 4000);
    }

    async fetchDevices() {
        try {
            const res = await fetch('/api/devices');
            if (!res.ok) return;
            const data = await res.json();
            this.renderDeviceList(data.devices || []);
        } catch (e) {
            console.warn('[App] Gagal memuat daftar perangkat:', e);
        }
    }

    renderDeviceList(devices) {
        this.dom.deviceCount.textContent = devices.length;
        if (devices.length === 0) {
            this.dom.deviceList.innerHTML = `
                <div class="empty-state">
                    <p style="color:#64748b; font-size:12px;">Tidak ada perangkat Android yang online.<br>Jalankan daemon root di HP target.</p>
                </div>
            `;
            return;
        }

        this.dom.deviceList.innerHTML = '';
        devices.forEach(dev => {
            const item = document.createElement('div');
            item.className = 'device-item' + (this.selectedDeviceId === dev.device_id ? ' active' : '');
            item.innerHTML = `
                <div class="device-item-header">
                    <span class="device-name">${this.escapeHtml(dev.device_name)}</span>
                    <span class="device-badge-active">Online</span>
                </div>
                <div class="device-id">ID: ${this.escapeHtml(dev.device_id)}</div>
                <div class="device-res">${dev.screen_width}x${dev.screen_height} | ${dev.controllers_count} Pengendali</div>
            `;
            item.addEventListener('click', () => {
                this.selectDevice(dev);
            });
            this.dom.deviceList.appendChild(item);
        });

        // Jika belum ada perangkat terpilih dan ada perangkat online, pilih yang pertama
        if (!this.selectedDeviceId && devices.length > 0) {
            this.selectDevice(devices[0]);
        }
    }

    selectDevice(device) {
        this.selectedDeviceId = device.device_id;
        this.deviceInfo = device;

        // Update list highlight
        document.querySelectorAll('.device-item').forEach(el => el.classList.remove('active'));
        
        // Update Info Tab
        this.dom.infoDeviceId.textContent = device.device_id;
        this.dom.infoDeviceName.textContent = device.device_name;
        this.dom.infoResolution.textContent = `${device.screen_width} x ${device.screen_height} px`;
        this.dom.infoUptime.textContent = `${device.uptime_seconds} detik`;

        // Update Placeholder Button
        this.dom.placeholderTitle.textContent = device.device_name;
        this.dom.placeholderSubtitle.textContent = `Resolusi: ${device.screen_width}x${device.screen_height} px (ID: ${device.device_id})`;
        this.dom.btnConnectCurrent.style.display = 'inline-block';

        // Langsung hubungkan jika belum terhubung atau ganti perangkat
        this.connectToDevice(device.device_id);
    }

    connectToDevice(deviceId) {
        if (this.ws) {
            try { this.ws.close(); } catch (e) {}
            this.ws = null;
        }

        if (this.jmuxer) {
            try { this.jmuxer.destroy(); } catch (e) {}
            this.jmuxer = null;
        }

        const token = encodeURIComponent(this.dom.authKey.value.trim());
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws/controller?device_id=${deviceId}&token=${token}`;

        this.appendTerminalLog(`[Koneksi] Menghubungi Relay Server untuk ${deviceId}...`, 'sys-msg');
        this.dom.statusIndicator.className = 'status-dot offline';
        this.dom.statusText.textContent = 'Menghubungkan...';

        this.initJMuxer();

        this.ws = new WebSocket(wsUrl);
        this.ws.binaryType = 'arraybuffer';

        this.ws.onopen = () => {
            this.dom.statusIndicator.className = 'status-dot online';
            this.dom.statusText.textContent = `Terkoneksi (${deviceId})`;
            this.dom.placeholder.style.display = 'none';
            this.dom.valStreamStatus.textContent = 'Streaming H.264';
            this.dom.valStreamStatus.style.color = '#10b981';
            this.appendTerminalLog(`[Berhasil] Terkoneksi ke sesi remote ${deviceId}! Kontrol 0ms aktif.`, 'sys-msg');
            this.startFpsCounter();
            this.resizeCanvas();
        };

        this.ws.onmessage = (event) => {
            if (event.data instanceof ArrayBuffer) {
                // Binary Frame: Raw H.264 NAL Units
                this.frameCount++;
                if (this.jmuxer) {
                    this.jmuxer.feed({
                        video: new Uint8Array(event.data)
                    });
                }
            } else {
                // Text Message (JSON Telemetri / Shell Output)
                try {
                    const msg = JSON.parse(event.data);
                    this.handleServerMessage(msg);
                } catch (e) {
                    console.log('[App] Pesan non-JSON:', event.data);
                }
            }
        };

        this.ws.onerror = (err) => {
            console.error('[WS Error]', err);
            this.appendTerminalLog('[Error] Gangguan koneksi WebSocket.', 'err');
        };

        this.ws.onclose = (e) => {
            this.dom.statusIndicator.className = 'status-dot offline';
            this.dom.statusText.textContent = 'Terputus';
            this.dom.placeholder.style.display = 'flex';
            this.dom.valStreamStatus.textContent = 'Terhenti';
            this.dom.valStreamStatus.style.color = '#f43f5e';
            this.appendTerminalLog(`[Koneksi Ditutup] Kode: ${e.code}`, 'sys-msg');
            this.stopFpsCounter();
        };
    }

    initJMuxer() {
        try {
            if (typeof JMuxer === 'undefined') {
                console.error('[JMuxer] Library tidak ditemukan!');
                return;
            }
            this.jmuxer = new JMuxer({
                node: 'player',
                mode: 'video',
                flushingTime: 0,
                clearBuffer: true,
                fps: 30,
                debug: false,
                onError: (data) => {
                    console.warn('[JMuxer Warning]', data);
                }
            });
        } catch (e) {
            console.error('[JMuxer Init Error]', e);
        }
    }

    handleServerMessage(msg) {
        if (msg.type === 'device_info') {
            this.deviceInfo = msg.device;
            this.dom.valResolution.textContent = `${msg.device.screen_width}x${msg.device.screen_height}`;
            this.resizeCanvas();
        } else if (msg.type === 'shell_output') {
            this.appendTerminalLog(msg.line, msg.is_error ? 'err' : '');
        } else if (msg.type === 'device_disconnected') {
            this.appendTerminalLog(`[Peringatan] ${msg.message}`, 'err');
            this.dom.placeholder.style.display = 'flex';
            this.dom.placeholderTitle.textContent = 'Perangkat Terputus';
            this.dom.placeholderSubtitle.textContent = msg.message;
        } else if (msg.type === 'error') {
            this.appendTerminalLog(`[Server Error] ${msg.message}`, 'err');
            alert(msg.message);
        }
    }

    // ==========================================
    // TOUCH & MOUSE MAPPING ENGINE
    // ==========================================

    handlePointerDown(e) {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) return;
        this.isMouseDown = true;
        const coords = this.getNormalizedCoordinates(e);
        if (!coords) return;

        this.showTouchRipple(e.clientX, e.clientY);
        this.sendTouch('down', coords.x, coords.y);
    }

    handlePointerMove(e) {
        if (!this.isMouseDown || !this.ws || this.ws.readyState !== WebSocket.OPEN) return;
        const coords = this.getNormalizedCoordinates(e);
        if (!coords) return;

        this.sendTouch('move', coords.x, coords.y);
    }

    handlePointerUp(e) {
        if (!this.isMouseDown) return;
        this.isMouseDown = false;
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) return;

        const coords = this.getNormalizedCoordinates(e);
        if (coords) {
            this.sendTouch('up', coords.x, coords.y);
        }
    }

    getNormalizedCoordinates(e) {
        const rect = this.touchCanvas.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return null;

        const relX = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        const relY = Math.max(0, Math.min(1, (e.clientY - rect.top) / rect.height));

        const targetW = this.deviceInfo ? this.deviceInfo.screen_width : 720;
        const targetH = this.deviceInfo ? this.deviceInfo.screen_height : 1280;

        return {
            x: Math.round(relX * targetW),
            y: Math.round(relY * targetH)
        };
    }

    showTouchRipple(clientX, clientY) {
        const rect = this.dom.viewport.getBoundingClientRect();
        const ripple = document.createElement('div');
        ripple.className = 'touch-ripple';
        ripple.style.left = (clientX - rect.left) + 'px';
        ripple.style.top = (clientY - rect.top) + 'px';
        this.dom.viewport.appendChild(ripple);
        setTimeout(() => ripple.remove(), 400);
    }

    resizeCanvas() {
        const rect = this.dom.viewport.getBoundingClientRect();
        this.touchCanvas.width = rect.width;
        this.touchCanvas.height = rect.height;
    }

    // ==========================================
    // CONTROL INJECTION DISPATCHERS
    // ==========================================

    sendTouch(action, x, y) {
        this.sendJson({
            type: 'touch',
            action: action,
            x: x,
            y: y
        });
    }

    sendKey(keyCode) {
        this.sendJson({
            type: 'key',
            code: keyCode
        });
        this.appendTerminalLog(`[Key Injected] KeyCode: ${keyCode}`, 'sys-msg');
    }

    sendSwipe(startX, startY, endX, endY, duration = 250) {
        this.sendJson({
            type: 'swipe',
            startX: Math.round(startX),
            startY: Math.round(startY),
            endX: Math.round(endX),
            endY: Math.round(endY),
            duration: duration
        });
        this.appendTerminalLog(`[Swipe Injected] (${startX},${startY}) -> (${endX},${endY})`, 'sys-msg');
    }

    sendShellCommand(cmd) {
        this.appendTerminalLog(`root@android:# ${cmd}`, 'cmd');
        this.sendJson({
            type: 'shell',
            command: cmd
        });
    }

    sendJson(obj) {
        if (!this.ws || this.ws.readyState !== WebSocket.OPEN) return;
        this.ws.send(JSON.stringify(obj));
    }

    // ==========================================
    // UTILITIES
    // ==========================================

    appendTerminalLog(text, className = '') {
        const line = document.createElement('div');
        line.className = 'term-line ' + className;
        line.textContent = text;
        this.dom.terminalLog.appendChild(line);
        this.dom.terminalLog.scrollTop = this.dom.terminalLog.scrollHeight;
    }

    startFpsCounter() {
        this.frameCount = 0;
        this.lastFpsUpdate = performance.now();
        this.fpsTimer = setInterval(() => {
            const now = performance.now();
            const elapsed = (now - this.lastFpsUpdate) / 1000;
            if (elapsed >= 1.0) {
                this.fps = Math.round(this.frameCount / elapsed);
                this.dom.valFps.textContent = `${this.fps} fps`;
                this.frameCount = 0;
                this.lastFpsUpdate = now;
            }
        }, 1000);
    }

    stopFpsCounter() {
        if (this.fpsTimer) {
            clearInterval(this.fpsTimer);
            this.fpsTimer = null;
        }
        this.dom.valFps.textContent = '0 fps';
    }

    escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
}

// Inisialisasi saat DOM siap
window.addEventListener('DOMContentLoaded', () => {
    window.remoteApp = new RemoteControlApp();
});
