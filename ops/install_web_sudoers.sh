#!/usr/bin/env bash
# ==============================================================================
# Installer Izin Sudoers Terbatas (Least-Privilege) untuk Pusat Kesehatan Web
# Hanya mengizinkan user www-data menjalankan ops/web_ops_bridge.sh
# ==============================================================================
set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "[ERROR] Jalankan sebagai root: sudo bash ops/install_web_sudoers.sh" >&2
  exit 1
fi

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
PROJECT_DIR="$(cd "$(dirname "${SCRIPT_PATH}")/.." && pwd)"
BRIDGE_SCRIPT="${PROJECT_DIR}/ops/web_ops_bridge.sh"
SUDOERS_FILE="/etc/sudoers.d/jualan-web-ops"

if [[ ! -f "${BRIDGE_SCRIPT}" ]]; then
  echo "[ERROR] File bridge tidak ditemukan: ${BRIDGE_SCRIPT}" >&2
  exit 1
fi

# Amankan kepemilikan & permission bridge agar hanya root yang bisa memodifikasi
chown root:root "${BRIDGE_SCRIPT}"
chmod 755 "${BRIDGE_SCRIPT}"
chmod +x "${PROJECT_DIR}/ops/update_manager.sh" "${PROJECT_DIR}/ops/backup_manager.sh" "${PROJECT_DIR}/ops/repair_system_and_bot.sh" 2>/dev/null || true

# Pastikan file .env root & web/.env dapat dibaca & diubah oleh group www-data (untuk toggle bot commands & Turnstile)
for env_target in "${PROJECT_DIR}/.env" "${PROJECT_DIR}/web/.env"; do
  if [[ -f "${env_target}" ]]; then
    chgrp www-data "${env_target}" 2>/dev/null || true
    chmod 660 "${env_target}" 2>/dev/null || true
  fi
done

TMP_SUDOERS="$(mktemp)"
cat > "${TMP_SUDOERS}" <<EOF
# Managed by bot_tele_jualan/ops/install_web_sudoers.sh
# Least-privilege: www-data may only execute the whitelisted ops bridge script
www-data ALL=(root) NOPASSWD: ${BRIDGE_SCRIPT}
EOF

if visudo -cf "${TMP_SUDOERS}" >/dev/null 2>&1; then
  install -m 0440 -o root -g root "${TMP_SUDOERS}" "${SUDOERS_FILE}"
  rm -f "${TMP_SUDOERS}"
  echo "[OK] Sudoers terbatas berhasil dipasang di ${SUDOERS_FILE}."
else
  rm -f "${TMP_SUDOERS}"
  echo "[ERROR] Validasi visudo gagal. File sudoers tidak diterapkan." >&2
  exit 1
fi

# Uji langsung sebagai www-data
if sudo -u www-data sudo -n "${BRIDGE_SCRIPT}" ping 2>/dev/null | grep -q "BRIDGE_OK"; then
  echo "[OK] Verifikasi sukses: www-data dapat menjalankan VPS Ops Bridge."
else
  echo "[WARNING] Sudoers terpasang, tetapi uji ping www-data belum mengembalikan BRIDGE_OK. Periksa izin direktori parent (chmod o+x ${PROJECT_DIR})."
fi
