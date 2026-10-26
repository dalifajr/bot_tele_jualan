#!/system/bin/sh
# ==============================================================================
# Menjalankan Remote Agent Daemon di Latar Belakang
# ==============================================================================

# Default URL menggunakan port 8099 loopback (atau wss://domain.com/ws/device jika lewat Nginx HTTPS)
SERVER_URL="${1:-wss://belajaridn.id/ws/device}"
DEVICE_ID="${2:-ANDROID_TARGET_01}"
DEVICE_TOKEN="${3:-SECRET_REMOTE_TOKEN_2026}"

echo "[INFO] Menjalankan daemon agent..."
export CLASSPATH=/data/local/tmp/remote_control/remote_agent.jar
nohup app_process /data/local/tmp/remote_control com.remote.agent.Main --url "$SERVER_URL" --id "$DEVICE_ID" --token "$DEVICE_TOKEN" > /data/local/tmp/remote_control/agent.log 2>&1 &

PID=$!
echo "[SUCCESS] Daemon berjalan di background (PID: $PID)"
echo "[INFO] Cek live log dengan: tail -f /data/local/tmp/remote_control/agent.log"
