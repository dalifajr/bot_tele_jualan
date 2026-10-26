#!/system/bin/sh
# ==============================================================================
# Script Peluncur Agent Android Root (Target Device)
# Domain: belajaridn.id
# ==============================================================================

JAR_PATH="/data/local/tmp/remote_agent.jar"

if [ ! -f "$JAR_PATH" ]; then
    echo "[ERROR] File $JAR_PATH tidak ditemukan!"
    echo "Salin file remote_agent.jar ke /data/local/tmp/ terlebih dahulu."
    exit 1
fi

chmod 755 "$JAR_PATH"

echo "====================================================="
echo "   MEMULAI DAEMON ANDROID REMOTE CONTROL (ROOT)      "
echo "   Target Server: wss://belajaridn.id/ws/device      "
echo "====================================================="

export CLASSPATH="$JAR_PATH"
exec app_process /data/local/tmp com.remote.agent.Main "$@"
