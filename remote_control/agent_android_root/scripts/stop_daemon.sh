#!/system/bin/sh
# ==============================================================================
# Menghentikan Remote Agent Daemon
# ==============================================================================

echo "[INFO] Mencari proses com.remote.agent.Main..."
PIDS=$(ps -ef 2>/dev/null | grep "com.remote.agent.Main" | grep -v "grep" | awk '{print $2}')
if [ -z "$PIDS" ]; then
    PIDS=$(pgrep -f "com.remote.agent.Main")
fi

if [ -n "$PIDS" ]; then
    echo "[INFO] Menghentikan PID: $PIDS"
    kill -9 $PIDS
    echo "[SUCCESS] Remote Agent Daemon berhasil dimatikan."
else
    echo "[INFO] Tidak ada daemon yang sedang berjalan."
fi
