#!/system/bin/sh
# ==============================================================================
# Script Instalasi Daemon Android Remote Control (Magisk / KernelSU / Root)
# ==============================================================================

if [ "$(id -u)" -ne 0 ]; then
    echo "[ERROR] Script ini harus dijalankan sebagai ROOT (ketik 'su' terlebih dahulu)!"
    exit 1
fi

DEST_DIR="/data/local/tmp/remote_control"
mkdir -p "$DEST_DIR"
chmod 777 "$DEST_DIR"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Salin binary jar/dex jika ada di folder script
if [ -f "$SCRIPT_DIR/remote_agent.jar" ]; then
    cp "$SCRIPT_DIR/remote_agent.jar" "$DEST_DIR/remote_agent.jar"
elif [ -f "/sdcard/Download/remote_agent.jar" ]; then
    cp "/sdcard/Download/remote_agent.jar" "$DEST_DIR/remote_agent.jar"
fi

chmod 755 "$DEST_DIR/remote_agent.jar" 2>/dev/null

# Buat script launcher di /data/local/tmp
cat > "$DEST_DIR/run.sh" << 'EOF'
#!/system/bin/sh
export CLASSPATH=/data/local/tmp/remote_control/remote_agent.jar
exec app_process /data/local/tmp/remote_control com.remote.agent.Main "$@"
EOF
chmod 755 "$DEST_DIR/run.sh"

# Pasang Boot Persistence (Magisk / KernelSU / APatch)
MAGISK_SERVICE_DIR="/data/adb/service.d"
if [ -d "$MAGISK_SERVICE_DIR" ]; then
    echo "[INFO] Terdeteksi Magisk/KernelSU. Memasang persistent boot script..."
    cat > "$MAGISK_SERVICE_DIR/remote_agent_boot.sh" << 'EOF'
#!/system/bin/sh
sleep 15
export CLASSPATH=/data/local/tmp/remote_control/remote_agent.jar
nohup app_process /data/local/tmp/remote_control com.remote.agent.Main > /data/local/tmp/remote_control/agent.log 2>&1 &
EOF
    chmod 755 "$MAGISK_SERVICE_DIR/remote_agent_boot.sh"
    echo "[SUCCESS] Auto-start on boot berhasil dipasang di $MAGISK_SERVICE_DIR!"
fi

echo "[SUCCESS] Instalasi selesai!"
echo "Untuk menjalankan sekarang, jalankan: sh /data/local/tmp/remote_control/run.sh"
