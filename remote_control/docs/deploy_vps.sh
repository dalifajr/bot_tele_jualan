#!/bin/bash
# ====================================================================
# Script Deployment Otomatis Remote Control Relay Server pada VPS Ubuntu/Debian
# ====================================================================

set -e

echo "=== [1/4] Menginstal Dependensi Python & Layanan Relay ==="
sudo apt-get update -y
sudo apt-get install -y python3 python3-pip python3-venv nginx

# Path aplikasi di VPS
TARGET_DIR="/var/www/remote_control"
sudo mkdir -p "$TARGET_DIR"

echo "=== [2/4] Menyiapkan Virtual Environment Python ==="
python3 -m venv "$TARGET_DIR/venv"
"$TARGET_DIR/venv/bin/pip" install --upgrade pip
"$TARGET_DIR/venv/bin/pip" install fastapi uvicorn websockets

echo "=== [3/4] Memasang Systemd Service Daemon ==="
sudo cp remote_control.service /etc/systemd/system/remote_control.service
sudo systemctl daemon-reload
sudo systemctl enable remote_control
sudo systemctl restart remote_control

echo "=== [4/4] Memverifikasi Status Layanan ==="
sudo systemctl status remote_control --no-pager

echo ""
echo "============================================================"
echo " Relay Gateway Server Berhasil Berjalan di port 8080 VPS!   "
echo " Tambahkan konfigurasi nginx_remote_control.conf ke Nginx. "
echo "============================================================"
