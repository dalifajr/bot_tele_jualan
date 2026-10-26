#!/bin/bash
# ==============================================================================
# Setup Relay Server & Nginx Reverse Proxy di VPS belajaridn.id
# ==============================================================================

set -e

echo "[1/4] Menginstal dependensi Python & Nginx..."
sudo apt update -y
sudo apt install -y python3 python3-pip python3-venv nginx

TARGET_DIR="/var/www/remote_control"
sudo mkdir -p "$TARGET_DIR"

echo "[2/4] Menyiapkan environment & package Python..."
python3 -m venv "$TARGET_DIR/venv"
"$TARGET_DIR/venv/bin/pip" install --upgrade pip
"$TARGET_DIR/venv/bin/pip" install fastapi uvicorn websockets

# Salin script server_relay ke TARGET_DIR jika belum ada
if [ -d "./server_relay" ]; then
    sudo cp -r ./server_relay/* "$TARGET_DIR/"
fi

echo "[3/4] Mengonfigurasi systemd service (remote_control.service)..."
cat << 'EOF' | sudo tee /etc/systemd/system/remote_control.service
[Unit]
Description=Android Remote Control Relay Gateway Daemon
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/var/www/remote_control
ExecStart=/var/www/remote_control/venv/bin/python3 -m uvicorn server:app --host 127.0.0.1 --port 8099
Restart=always
RestartSec=3
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now remote_control
sudo systemctl restart remote_control

echo "[4/4] Memeriksa status service relay..."
sudo systemctl status remote_control --no-pager

echo ""
echo "=============================================================================="
echo " Relay Gateway BERHASIL AKTIF di 127.0.0.1:8099 VPS!                           "
echo " Terisolasi di loopback internal, bebas dari tabrakan port 8080 Xray/Bot!     "
echo " Pastikan blok Nginx untuk /ws/ dan /api/devices sudah ada di belajaridn.id   "
echo "=============================================================================="
