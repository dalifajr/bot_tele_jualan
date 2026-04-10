#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RUN_USER="${SUDO_USER:-$USER}"
VENV_DIR="${PROJECT_DIR}/.venv"
PYTHON_BIN="${VENV_DIR}/bin/python"

if ! command -v apt >/dev/null 2>&1; then
  echo "setup.sh ini ditujukan untuk Ubuntu/Debian (apt)." >&2
  exit 1
fi

echo "[1/8] Install dependency apt"
sudo apt update
sudo apt install -y python3 python3-venv python3-pip git curl

echo "[2/8] Prepare virtual environment"
if [[ ! -d "${VENV_DIR}" ]]; then
  python3 -m venv "${VENV_DIR}"
fi

"${VENV_DIR}/bin/pip" install --upgrade pip
"${VENV_DIR}/bin/pip" install -r "${PROJECT_DIR}/requirements.txt"

echo "[3/8] Prepare env files"
if [[ ! -f "${PROJECT_DIR}/.env" ]]; then
  cp "${PROJECT_DIR}/.env.example" "${PROJECT_DIR}/.env"
  echo ".env dibuat dari template. Silakan isi BOT_TOKEN dan secret lainnya."
fi

if [[ ! -f "${PROJECT_DIR}/user_role.txt" ]]; then
  cat > "${PROJECT_DIR}/user_role.txt" <<'EOF'
# Format: admin:<telegram_user_id>
# Dikelola oleh panel jualan
EOF
fi

mkdir -p "${PROJECT_DIR}/data"

echo "[4/9] Install scripts permissions"
chmod +x "${PROJECT_DIR}/ops/jualan"
chmod +x "${PROJECT_DIR}/ops/update_manager.sh"
chmod +x "${PROJECT_DIR}/ops/backup_manager.sh"
chmod +x "${PROJECT_DIR}/setup.sh"

echo "[5/9] Render systemd units"
BOT_UNIT_TMP="${PROJECT_DIR}/ops/systemd/jualan-bot.service"
API_UNIT_TMP="${PROJECT_DIR}/ops/systemd/jualan-api.service"
BACKUP_UNIT_TMP="${PROJECT_DIR}/ops/systemd/jualan-backup.service"
BACKUP_TIMER_TMP="${PROJECT_DIR}/ops/systemd/jualan-backup.timer"
BOT_UNIT_OUT="/etc/systemd/system/jualan-bot.service"
API_UNIT_OUT="/etc/systemd/system/jualan-api.service"
BACKUP_UNIT_OUT="/etc/systemd/system/jualan-backup.service"
BACKUP_TIMER_OUT="/etc/systemd/system/jualan-backup.timer"

render_unit() {
  local src="$1"
  local dst="$2"
  sed \
    -e "s|__PROJECT_DIR__|${PROJECT_DIR}|g" \
    -e "s|__PYTHON_BIN__|${PYTHON_BIN}|g" \
    -e "s|__RUN_USER__|${RUN_USER}|g" \
    "${src}" | sudo tee "${dst}" >/dev/null
}

render_unit "${BOT_UNIT_TMP}" "${BOT_UNIT_OUT}"
render_unit "${API_UNIT_TMP}" "${API_UNIT_OUT}"
render_unit "${BACKUP_UNIT_TMP}" "${BACKUP_UNIT_OUT}"
render_unit "${BACKUP_TIMER_TMP}" "${BACKUP_TIMER_OUT}"

echo "[6/9] Reload and enable services"
sudo systemctl daemon-reload
sudo systemctl enable jualan-bot.service jualan-api.service
sudo systemctl enable jualan-backup.timer

echo "[7/9] Install alias command"
sudo ln -sf "${PROJECT_DIR}/ops/jualan" /usr/local/bin/jualan

echo "[8/9] Start services"
sudo systemctl restart jualan-bot.service jualan-api.service
sudo systemctl restart jualan-backup.timer

echo "[9/9] Initial backup snapshot"
bash "${PROJECT_DIR}/ops/backup_manager.sh" backup || true

echo "Setup selesai. Jalankan:"
echo "  jualan config"
echo "  jualan status"
