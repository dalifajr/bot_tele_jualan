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

echo "[4/8] Install scripts permissions"
chmod +x "${PROJECT_DIR}/ops/jualan"
chmod +x "${PROJECT_DIR}/ops/update_manager.sh"
chmod +x "${PROJECT_DIR}/setup.sh"

echo "[5/8] Render systemd units"
BOT_UNIT_TMP="${PROJECT_DIR}/ops/systemd/jualan-bot.service"
API_UNIT_TMP="${PROJECT_DIR}/ops/systemd/jualan-api.service"
BOT_UNIT_OUT="/etc/systemd/system/jualan-bot.service"
API_UNIT_OUT="/etc/systemd/system/jualan-api.service"

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

echo "[6/8] Reload and enable services"
sudo systemctl daemon-reload
sudo systemctl enable jualan-bot.service jualan-api.service

echo "[7/8] Install alias command"
sudo ln -sf "${PROJECT_DIR}/ops/jualan" /usr/local/bin/jualan

echo "[8/8] Start services"
sudo systemctl restart jualan-bot.service jualan-api.service

echo "Setup selesai. Jalankan:"
echo "  jualan config"
echo "  jualan status"
