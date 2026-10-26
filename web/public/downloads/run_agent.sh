#!/system/bin/sh
# ==============================================================================
# Script Peluncur Agent Android Root (Target Device)
# Domain: belajaridn.id
# ==============================================================================

TARGET_DIR="/data/local/tmp"
JAR_PATH="$TARGET_DIR/remote_agent.jar"
SCRIPT_DIR="$(cd "$(dirname "$0")" 2>/dev/null && pwd)"

# Otomatis sinkronkan file jar dari folder tempat script berada jika lebih baru
if [ -n "$SCRIPT_DIR" ] && [ "$SCRIPT_DIR" != "$TARGET_DIR" ] && [ -f "$SCRIPT_DIR/remote_agent.jar" ]; then
    echo "[Peluncur] Menyalin remote_agent.jar dari $SCRIPT_DIR ke $TARGET_DIR..."
    cp -f "$SCRIPT_DIR/remote_agent.jar" "$JAR_PATH" 2>/dev/null
fi

if [ ! -f "$JAR_PATH" ]; then
    echo "[ERROR] File $JAR_PATH tidak ditemukan!"
    echo "Unduh atau salin remote_agent.jar ke $TARGET_DIR terlebih dahulu."
    exit 1
fi

chmod 755 "$JAR_PATH"

echo "====================================================="
echo "   MEMULAI DAEMON ANDROID REMOTE CONTROL (ROOT)      "
echo "   Target Server: wss://belajaridn.id/ws/device      "
echo "====================================================="

# 1. Matikan instance daemon atau screenrecord lama yang menggantung (Mencegah Bentrok/LMKD)
pkill -9 -f com.remote.agent.Main 2>/dev/null || true
killall -9 screenrecord 2>/dev/null || true
sleep 1

# 2. Set SELinux ke Permissive untuk mencegah SIGKILL (Error 137) pada Android 14/15/17
setenforce 0 2>/dev/null || true
SE_STATUS=$(getenforce 2>/dev/null || echo "Unknown")
echo "[Peluncur] Status SELinux: $SE_STATUS"

# Info ukuran binary untuk memastikan versi terbaru yang dieksekusi
JAR_SIZE=$(ls -l "$JAR_PATH" 2>/dev/null | awk '{print $5}')
echo "[Peluncur] Memuat binary ($JAR_SIZE bytes): $JAR_PATH"

# 3. Jalankan melalui app_process
export CLASSPATH="$JAR_PATH"
exec app_process / com.remote.agent.Main "$@"

