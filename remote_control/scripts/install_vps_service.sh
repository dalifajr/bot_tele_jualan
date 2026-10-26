#!/bin/bash
# ==============================================================================
# Installer Otomatis Service Remote Control Relay Gateway di VPS
# Mendukung: Ubuntu / Debian / Coexistence dengan Xray & Nginx
# ==============================================================================

set -e

# Warna Terminal
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}================================================================${NC}"
echo -e "${BLUE}   INSTALLER SERVICE REMOTE CONTROL ANDROID ROOT (VPS GATEWAY)  ${NC}"
echo -e "${BLUE}================================================================${NC}"

# 1. Konfigurasi Port & Kredensial
RELAY_PORT="${RELAY_PORT:-8099}"
DEVICE_AUTH_TOKEN="${DEVICE_AUTH_TOKEN:-SECRET_REMOTE_TOKEN_2026}"
ADMIN_SECRET_KEY="${ADMIN_SECRET_KEY:-ADMIN_SECRET_PASS_2026}"

echo -e "${YELLOW}[1/6] Memeriksa hak akses root...${NC}"
if [ "$(id -u)" -ne 0 ]; then
    echo -e "${RED}[ERROR] Skrip ini harus dijalankan dengan hak akses root (sudo).${NC}"
    exit 1
fi

echo -e "${YELLOW}[2/6] Memeriksa & menginstal paket dependensi sistem...${NC}"
apt-get update -y >/dev/null 2>&1 || true
apt-get install -y python3 python3-pip python3-venv nginx curl net-tools >/dev/null 2>&1 || true

# 2. Direktori Instalasi
TARGET_DIR="/var/www/remote_control"
SCRIPT_SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../server_relay" && pwd)"

echo -e "${YELLOW}[3/6] Menyiapkan direktori aplikasi di ${TARGET_DIR}...${NC}"
mkdir -p "$TARGET_DIR"

if [ -d "$SCRIPT_SOURCE_DIR" ]; then
    cp -r "$SCRIPT_SOURCE_DIR"/* "$TARGET_DIR/"
else
    echo -e "${RED}[PERINGATAN] Direktori sumber server_relay tidak ditemukan di $SCRIPT_SOURCE_DIR.${NC}"
fi

# 3. Virtual Environment Python
echo -e "${YELLOW}[4/6] Menyiapkan Python Virtual Environment & Library...${NC}"
if [ ! -d "$TARGET_DIR/venv" ]; then
    python3 -m venv "$TARGET_DIR/venv"
fi
"$TARGET_DIR/venv/bin/pip" install --upgrade pip >/dev/null 2>&1 || true
"$TARGET_DIR/venv/bin/pip" install fastapi uvicorn websockets >/dev/null 2>&1 || true

# Sinkronkan config.py dengan kredensial aktif
cat > "$TARGET_DIR/config.py" <<EOF
"""
Konfigurasi Relay & Gateway Server Remote Control
Otomatis digenerate oleh Installer VPS
"""
import os

class ServerConfig:
    HOST = os.getenv("RELAY_HOST", "127.0.0.1")
    PORT = int(os.getenv("RELAY_PORT", ${RELAY_PORT}))
    
    DEVICE_AUTH_TOKEN = os.getenv("DEVICE_AUTH_TOKEN", "${DEVICE_AUTH_TOKEN}")
    ADMIN_SECRET_KEY = os.getenv("ADMIN_SECRET_KEY", "${ADMIN_SECRET_KEY}")
    
    HEARTBEAT_INTERVAL = 15
    CLIENT_TIMEOUT = 30
EOF

# 4. Memasang Systemd Daemon Service
echo -e "${YELLOW}[5/6] Memasang Systemd Service (remote_control.service)...${NC}"
cat > /etc/systemd/system/remote_control.service <<EOF
[Unit]
Description=Android Remote Control Relay Gateway Daemon
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=${TARGET_DIR}
Environment="RELAY_HOST=127.0.0.1"
Environment="RELAY_PORT=${RELAY_PORT}"
Environment="DEVICE_AUTH_TOKEN=${DEVICE_AUTH_TOKEN}"
Environment="ADMIN_SECRET_KEY=${ADMIN_SECRET_KEY}"
ExecStart=${TARGET_DIR}/venv/bin/python3 -m uvicorn server:app --host 127.0.0.1 --port ${RELAY_PORT}
Restart=always
RestartSec=3
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now remote_control
systemctl restart remote_control

# 5. Integrasi Otomatis ke Nginx Aktif
echo -e "${YELLOW}[6/6] Memeriksa konfigurasi Nginx untuk WebSocket Reverse Proxy (/ws/)...${NC}"

NGINX_UPDATED=0
# Cari file konfigurasi Nginx yang melayani domain website jualan
for conf in /etc/nginx/sites-available/jualan-web-* /etc/nginx/conf.d/*.conf /etc/nginx/sites-available/default; do
    if [ -f "$conf" ]; then
        if grep -q "location /ws/" "$conf"; then
            echo -e "${GREEN}[OK] Blok WebSocket (/ws/) sudah ada pada ${conf}.${NC}"
            NGINX_UPDATED=1
        else
            echo -e "${YELLOW}[UPDATE] Menyuntikkan blok reverse proxy /ws/ ke dalam ${conf}...${NC}"
            # Sisipkan blok sebelum penutup server } terakhir
            sed -i '/access_log/i \
    # Remote Control WebSocket Relay Gateway (Port Terisolasi '${RELAY_PORT}')\
    location /ws/ {\
        proxy_pass http://127.0.0.1:'${RELAY_PORT}';\
        proxy_http_version 1.1;\
        proxy_set_header Upgrade $http_upgrade;\
        proxy_set_header Connection "upgrade";\
        proxy_set_header Host $host;\
        proxy_set_header X-Real-IP $remote_addr;\
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;\
        proxy_set_header X-Forwarded-Proto $scheme;\
        proxy_read_timeout 86400s;\
        proxy_send_timeout 86400s;\
        proxy_buffering off;\
    }\
    location /api/devices {\
        proxy_pass http://127.0.0.1:'${RELAY_PORT}'/api/devices;\
        proxy_set_header Host $host;\
        proxy_set_header X-Real-IP $remote_addr;\
    }\
' "$conf"
            NGINX_UPDATED=1
        fi
    fi
done

if [ "$NGINX_UPDATED" -eq 1 ]; then
    if nginx -t >/dev/null 2>&1; then
        systemctl reload nginx
        echo -e "${GREEN}[SUCCESS] Nginx berhasil di-reload dengan rute WebSocket aktif!${NC}"
    else
        echo -e "${RED}[WARNING] Validasi Nginx gagal. Silakan periksa 'nginx -t'.${NC}"
    fi
fi

# 6. Verifikasi Status Akhir
echo ""
echo -e "${GREEN}================================================================${NC}"
echo -e "${GREEN}   INSTALASI SERVICE REMOTE CONTROL SELESAI & AKTIF!            ${NC}"
echo -e "${GREEN}================================================================${NC}"
echo -e "Status Daemon : $(systemctl is-active remote_control)"
echo -e "Port Internal : 127.0.0.1:${RELAY_PORT} (Aman dari tabrakan port 8080)"
echo -e "Auth Token    : ${DEVICE_AUTH_TOKEN}"
echo -e "Admin Key     : ${ADMIN_SECRET_KEY}"
echo -e "WebSocket URL : wss://namadomain.com/ws/device"
echo -e "${GREEN}================================================================${NC}"
