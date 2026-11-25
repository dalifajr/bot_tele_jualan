#!/usr/bin/env bash
# ==============================================================================
# Installer Cron Job Laravel Scheduler untuk Dzulfikrialifajri Store
# Menjalankan otomatis pelepasan dana seller, pembatalan order expired,
# sinkronisasi traffic VPN, dan notifikasi VPN expiring.
# ==============================================================================
set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "[ERROR] Harap jalankan script ini sebagai root: sudo bash ops/install_cron.sh" >&2
  exit 1
fi

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
PROJECT_DIR="$(cd "$(dirname "${SCRIPT_PATH}")/.." && pwd)"
WEB_DIR="${PROJECT_DIR}/web"
CRON_FILE="/etc/cron.d/jualan-web"

if [[ ! -f "${WEB_DIR}/artisan" ]]; then
  echo "[ERROR] File artisan tidak ditemukan di: ${WEB_DIR}/artisan" >&2
  exit 1
fi

echo "[INFO] Mengonfigurasi cron job Laravel scheduler di ${CRON_FILE}..."

cat > "${CRON_FILE}" <<EOF
# Cron job Laravel Scheduler untuk website jualan
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
* * * * * root cd ${WEB_DIR} && php artisan schedule:run >> /dev/null 2>&1
EOF

chmod 644 "${CRON_FILE}"

# Reload cron daemon
if systemctl is-active --quiet cron 2>/dev/null; then
  systemctl reload cron 2>/dev/null || systemctl restart cron 2>/dev/null || true
  echo "[OK] Cron daemon aktif dan dikonfigurasi ulang."
elif systemctl is-active --quiet crond 2>/dev/null; then
  systemctl reload crond 2>/dev/null || systemctl restart crond 2>/dev/null || true
  echo "[OK] Crond daemon aktif dan dikonfigurasi ulang."
fi

echo "[SUCCESS] Cron job Laravel scheduler berhasil dipasang!"
echo "Scheduler akan menjalankan 'php artisan schedule:run' setiap 1 menit:"
echo "  - funds:release-held (Pelepasan dana tertahan garansi seller)"
echo "  - orders:release-expired (Pembatalan otomatis pesanan kadaluwarsa)"
echo "  - broadcast:calculate-reads (Analitik broadcast)"
echo "  - vpn:sync-traffic & vpn:notify-expiring"
