#!/system/bin/sh
# ==============================================================================
# Menjalankan Remote Agent Daemon di Latar Belakang
# ==============================================================================

SERVER_URL="${1:-ws://127.0.0.1:8080/ws/device}"
DEVICE_ID="${2:-ANDROID_TARGET_01}"

echo "[INFO] Menjalankan daemon agent..."
export CLASSPATH=/data/local/tmp/remote_control/remote_agent.jar
nohup app_process /data/local/tmp/remote_control com.remote.agent.Main --url "$SERVER_URL" --id "$DEVICE_ID" > /data/local/tmp/remote_control/agent.log 2>&1 &

PID=$!
echo "[SUCCESS] Daemon berjalan di background (PID: $PID)"
echo "[INFO] Cek live log dengan: tail -f /data/local/tmp/remote_control/agent.log"
