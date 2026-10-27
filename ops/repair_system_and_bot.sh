#!/usr/bin/env bash
# ==============================================================================
# Script Diagnosa & Pemulihan Service Bot Telegram & Payment Listener di VPS
# Dzulfikrialifajri Store - Clean Code & Operational Reliability
# ==============================================================================

set -e

# Warna Terminal
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${BLUE}================================================================${NC}"
echo -e "${BLUE}     PEMULIHAN BOT TELEGRAM & PAYMENT LISTENER (VPS BELAJARIDN) ${NC}"
echo -e "${BLUE}================================================================${NC}"

# 1. Validasi Root
if [[ "$(id -u)" -ne 0 ]]; then
    echo -e "${RED}[ERROR] Script ini harus dijalankan dengan hak akses root (sudo).${NC}"
    exit 1
fi

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
PROJECT_DIR="$(cd "$(dirname "${SCRIPT_PATH}")/.." && pwd)"
WEB_DIR="${PROJECT_DIR}/web"
ENV_FILE="${PROJECT_DIR}/.env"
WEB_ENV_FILE="${WEB_DIR}/.env"

echo -e "${CYAN}[INFO] Direktori Project: ${PROJECT_DIR}${NC}"

# 2. Diagnosa Awal Log Error Bot Telegram
echo -e "\n${YELLOW}[1/6] Memeriksa status & log kesalahan service bot Telegram...${NC}"
if systemctl is-active --quiet jualan-bot.service; then
    echo -e "${GREEN}[OK] Service jualan-bot.service saat ini sedang berjalan (Active).${NC}"
else
    echo -e "${RED}[PERINGATAN] Service jualan-bot.service dalam kondisi nonaktif/crash!${NC}"
    echo -e "${YELLOW}--- Cuplikan Log Terakhir dari journalctl ---${NC}"
    journalctl -u jualan-bot.service -n 25 --no-pager || true
    echo -e "${YELLOW}-------------------------------------------${NC}"
fi

# 3. Validasi & Sinkronisasi Environment File
echo -e "\n${YELLOW}[2/6] Memeriksa kelengkapan file konfigurasi (.env)...${NC}"
if [[ ! -f "${ENV_FILE}" ]]; then
    echo -e "${RED}[ERROR] File ${ENV_FILE} tidak ditemukan! Membuat dari example...${NC}"
    if [[ -f "${PROJECT_DIR}/.env.example" ]]; then
        cp "${PROJECT_DIR}/.env.example" "${ENV_FILE}"
    fi
fi

# Pastikan BOT_TOKEN tidak kosong
BOT_TOKEN_VAL=$(grep -E "^BOT_TOKEN=" "${ENV_FILE}" | cut -d'=' -f2- | tr -d '"' | tr -d "'" | tr -d ' ' || true)
if [[ -z "${BOT_TOKEN_VAL}" && -f "${WEB_ENV_FILE}" ]]; then
    # Coba ambil dari TELEGRAM_BOT_TOKEN di web/.env
    TELE_TOKEN_VAL=$(grep -E "^TELEGRAM_BOT_TOKEN=" "${WEB_ENV_FILE}" | cut -d'=' -f2- | tr -d '"' | tr -d "'" | tr -d ' ' || true)
    if [[ -n "${TELE_TOKEN_VAL}" ]]; then
        echo -e "${GREEN}[FIX] Menyalin BOT_TOKEN dari web/.env ke root .env...${NC}"
        sed -i "s|^BOT_TOKEN=.*|BOT_TOKEN=\"${TELE_TOKEN_VAL}\"|" "${ENV_FILE}"
    fi
fi

# 4. Perbaikan Permission & Hak Akses SQLite serta Direktori Project
echo -e "\n${YELLOW}[3/6] Memperbaiki hak akses (ownership & permission) direktori & database...${NC}"
RUN_USER="$(stat -c '%U' "${PROJECT_DIR}" 2>/dev/null || echo "root")"
if [[ "${RUN_USER}" == "root" && -n "${SUDO_USER:-}" ]]; then
    RUN_USER="${SUDO_USER}"
fi

# Pastikan folder data ada
mkdir -p "${PROJECT_DIR}/data"
mkdir -p "${WEB_DIR}/storage" "${WEB_DIR}/bootstrap/cache"

# Permission untuk SQLite agar bisa ditulis oleh bot (user) dan Laravel (www-data)
chown -R "${RUN_USER}:www-data" "${PROJECT_DIR}/data" 2>/dev/null || chown -R www-data:www-data "${PROJECT_DIR}/data" 2>/dev/null || true
chmod 775 "${PROJECT_DIR}/data"
chmod -R 664 "${PROJECT_DIR}/data"/* 2>/dev/null || true

# Permission untuk folder Laravel storage dan cache
chown -R "${RUN_USER}:www-data" "${WEB_DIR}/storage" "${WEB_DIR}/bootstrap/cache" 2>/dev/null || true
chmod -R 775 "${WEB_DIR}/storage" "${WEB_DIR}/bootstrap/cache"

# Izinkan Nginx membaca parent folder
chmod o+x /root 2>/dev/null || true
chmod o+x "${PROJECT_DIR}" 2>/dev/null || true

echo -e "${GREEN}[OK] Hak akses database dan storage berhasil diperbaiki.${NC}"

# 5. Membersihkan Cache Laravel agar Secret Key Baru Aktif
echo -e "\n${YELLOW}[4/6] Membersihkan konfigurasi & cache Laravel...${NC}"
if [[ -f "${WEB_DIR}/artisan" ]]; then
    cd "${WEB_DIR}"
    php artisan config:clear || true
    php artisan cache:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
    cd "${PROJECT_DIR}"
    echo -e "${GREEN}[OK] Cache Laravel berhasil dibersihkan.${NC}"
fi

# 6. Pastikan Rute Nginx Tidak Membajak /listener/
echo -e "\n${YELLOW}[5/6] Memeriksa konfigurasi Nginx untuk endpoint listener...${NC}"
python3 -c "
import glob, re
targets = glob.glob('/etc/nginx/sites-available/*') + glob.glob('/etc/nginx/sites-enabled/*') + glob.glob('/etc/nginx/conf.d/*')
for p in set(targets):
    try:
        with open(p, 'r') as f: c = f.read()
        if 'location /listener/' in c or 'location /listener ' in c:
            c_new = re.sub(r'#?[^\n]*\n?\s*location\s+/listener/?\s*\{[^}]*\}', '', c)
            with open(p, 'w') as f: f.write(c_new)
            print(f'[CLEANUP] Menghapus proxy /listener/ lama dari {p}')
    except Exception: pass
" 2>/dev/null || true

if nginx -t >/dev/null 2>&1; then
    systemctl reload nginx
    echo -e "${GREEN}[OK] Nginx berhasil di-reload.${NC}"
else
    echo -e "${RED}[WARNING] Konfigurasi Nginx memiliki sintaks bermasalah. Jalankan 'nginx -t'.${NC}"
fi

# 7. Me-restart Service Bot Telegram & Service Terkait
echo -e "\n${YELLOW}[6/6] Me-restart unit systemd (jualan-bot, remote_control, etc.)...${NC}"
systemctl daemon-reload
systemctl restart jualan-bot.service || true

# Periksa status akhir bot
echo ""
echo -e "${BLUE}================================================================${NC}"
echo -e "${BLUE}                     STATUS AKHIR SERVICE                       ${NC}"
echo -e "${BLUE}================================================================${NC}"

if systemctl is-active --quiet jualan-bot.service; then
    echo -e "Service Bot Telegram : ${GREEN}AKTIF (RUNNING)${NC}"
else
    echo -e "Service Bot Telegram : ${RED}GAGAL (FAILED)${NC}"
    echo -e "${YELLOW}Detail Log Error jualan-bot.service:${NC}"
    journalctl -u jualan-bot.service -n 15 --no-pager
fi

if systemctl is-active --quiet remote_control.service; then
    echo -e "Remote Control Relay : ${GREEN}AKTIF (RUNNING)${NC}"
else
    echo -e "Remote Control Relay : ${YELLOW}NONAKTIF / TIDAK TERPASANG${NC}"
fi

if systemctl is-active --quiet nginx; then
    echo -e "Nginx Web Server     : ${GREEN}AKTIF (RUNNING)${NC}"
else
    echo -e "Nginx Web Server     : ${RED}NONAKTIF${NC}"
fi

echo -e "${BLUE}================================================================${NC}"
echo -e "${GREEN}Langkah selanjutnya untuk Helm:${NC}"
echo -e "1. Buka Admin Panel Website -> Konfigurasi Sistem -> Tab 'Payment & QRIS'"
echo -e "2. Masukkan Secret Key Listener yang diinginkan dan klik 'Simpan Kunci Listener'"
echo -e "3. Masukkan Endpoint dan Secret Key yang sama di aplikasi Android Listener"
echo -e "4. Klik tombol 'Test Koneksi' di HP Android"
echo -e "${BLUE}================================================================${NC}"
