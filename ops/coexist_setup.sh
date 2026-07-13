#!/usr/bin/env bash
set -euo pipefail

# Visual helper functions
log_step() {
    echo -e "\n\e[1;32m[+] $*\e[0m"
}

log_error() {
    echo -e "\n\e[1;31m[!] ERROR: $*\e[0m" >&2
}

# PHP version detection
detect_php_version() {
  if command -v php >/dev/null 2>&1; then
    php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'
    return
  fi
  echo "8.2" # default fallback
}

# 1. Check Root Privilege
if [[ "$(id -u)" -ne 0 ]]; then
    log_error "Skrip ini harus dijalankan sebagai root (gunakan sudo)."
    exit 1
fi

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
PROJECT_DIR="$(cd "$(dirname "${SCRIPT_PATH}")/.." && pwd)"
WEB_DIR="${PROJECT_DIR}/web"
ENV_FILE="${PROJECT_DIR}/.env"
WEB_ENV_FILE="${WEB_DIR}/.env"
RUN_USER="${SUDO_USER:-$USER}"

# 2. Gather Inputs
echo "========================================================="
echo "   COEXISTENCE INSTALLER - BOT TELE & LARAVEL STORE      "
echo "========================================================="
echo "Gunakan skrip ini untuk menginstall ke server yang sudah"
echo "memiliki website / bot lain agar berjalan berdampingan."
echo "========================================================="

# Domain Name
read -rp "Masukkan Domain Baru (contoh: ini.belajaridn.id): " WEBSITE_DOMAIN
if [[ -z "${WEBSITE_DOMAIN}" ]]; then
    log_error "Domain tidak boleh kosong!"
    exit 1
fi

# Bot Token
read -rp "Masukkan Telegram Bot Token baru Anda: " BOT_TOKEN
if [[ -z "${BOT_TOKEN}" ]]; then
    log_error "Bot Token tidak boleh kosong!"
    exit 1
fi

# API Port (to avoid 8080 collision)
read -rp "Masukkan Port API Uvicorn baru [Default: 8085]: " API_PORT
API_PORT="${API_PORT:-8085}"

# 4. Prepare Environment Files (.env)
log_step "Mengonfigurasi file lingkungan (.env)..."

# Helper function to write to .env
_set_env_value() {
  local file="$1" key="$2" value="$3"
  local clean_val
  clean_val=$(echo "${value}" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
  if grep -q "^${key}=" "${file}" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=\"${clean_val}\"|" "${file}"
  else
    echo "${key}=\"${clean_val}\"" >> "${file}"
  fi
}

# Root .env
if [[ ! -f "${ENV_FILE}" ]]; then
    cp "${ENV_FILE}.example" "${ENV_FILE}"
fi
_set_env_value "${ENV_FILE}" "BOT_TOKEN" "${BOT_TOKEN}"
_set_env_value "${ENV_FILE}" "API_PORT" "${API_PORT}"
_set_env_value "${ENV_FILE}" "DATABASE_URL" "sqlite:///./data/bot_jualan.db"
_set_env_value "${ENV_FILE}" "WEBSITE_ENABLED" "true"
_set_env_value "${ENV_FILE}" "WEBSITE_DOMAIN" "${WEBSITE_DOMAIN}"

# Fetch Bot Username
log_step "Mengambil username bot dari Telegram API..."
BOT_USERNAME=""
if me_resp=$(curl -s --connect-timeout 10 "https://api.telegram.org/bot${BOT_TOKEN}/getMe"); then
    if echo "${me_resp}" | grep -q '"ok":true'; then
        BOT_USERNAME=$(echo "${me_resp}" | jq -r '.result.username')
        log_step "Bot Username terdeteksi: @${BOT_USERNAME}"
        _set_env_value "${ENV_FILE}" "WEBSITE_BOT_USERNAME" "${BOT_USERNAME}"
    fi
fi

# Web (Laravel) .env
if [[ ! -f "${WEB_ENV_FILE}" ]]; then
    cp "${WEB_ENV_FILE}.example" "${WEB_ENV_FILE}"
fi
_set_env_value "${WEB_ENV_FILE}" "APP_URL" "https://${WEBSITE_DOMAIN}"
_set_env_value "${WEB_ENV_FILE}" "APP_NAME" "Dzulfikrialifajri Store"
_set_env_value "${WEB_ENV_FILE}" "DB_CONNECTION" "sqlite"
_set_env_value "${WEB_ENV_FILE}" "DB_DATABASE" "${WEB_DIR}/database/database.sqlite"
_set_env_value "${WEB_ENV_FILE}" "TELEGRAM_BOT_TOKEN" "${BOT_TOKEN}"
_set_env_value "${WEB_ENV_FILE}" "TELEGRAM_BOT_USERNAME" "${BOT_USERNAME}"
_set_env_value "${WEB_ENV_FILE}" "WEBSITE_DOMAIN" "${WEBSITE_DOMAIN}"
_set_env_value "${WEB_ENV_FILE}" "SESSION_DRIVER" "database"
_set_env_value "${WEB_ENV_FILE}" "CACHE_STORE" "database"


# 5. Build Python Virtual Environment
log_step "Mempersiapkan Python Virtual Environment..."
VENV_DIR="${PROJECT_DIR}/.venv"
if [[ ! -d "${VENV_DIR}" ]]; then
    python3 -m venv "${VENV_DIR}"
fi
"${VENV_DIR}/bin/pip" install --upgrade pip
"${VENV_DIR}/bin/pip" install -r "${PROJECT_DIR}/requirements.txt"

# Create user_role.txt if missing
if [[ ! -f "${PROJECT_DIR}/user_role.txt" ]]; then
    cat > "${PROJECT_DIR}/user_role.txt" <<'EOF'
# Format: admin:<telegram_user_id>
EOF
fi
mkdir -p "${PROJECT_DIR}/data"

# 5.5 Check and Install Missing PHP Extensions
log_step "Memeriksa ekstensi PHP yang diperlukan..."
PHP_VER="$(detect_php_version)"

if [[ -n "${PHP_VER}" ]]; then
    PKG_PREFIX="php${PHP_VER}-"
else
    PKG_PREFIX="php-"
fi

MISSING_EXTS=()
for ext in gd curl mbstring xml zip sqlite3 bcmath intl; do
    if ! php -m | grep -qi "^${ext}$"; then
        MISSING_EXTS+=("${PKG_PREFIX}${ext}")
    fi
done

if [ ${#MISSING_EXTS[@]} -gt 0 ]; then
    log_step "Menginstal ekstensi PHP yang hilang: ${MISSING_EXTS[*]}..."
    if command -v apt-get >/dev/null 2>&1; then
        apt-get update -qq || true
        apt-get install -y "${MISSING_EXTS[@]}"
        # Restart php-fpm to load the new extensions
        systemctl restart "php${PHP_VER}-fpm" 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
    else
        log_error "Sistem Anda tidak memiliki package manager 'apt-get'. Silakan instal ekstensi berikut secara manual: ${MISSING_EXTS[*]}"
    fi
fi

# 6. Configure Laravel Application
log_step "Mengonfigurasi Laravel Application..."
mkdir -p "${WEB_DIR}/database"
touch "${WEB_DIR}/database/database.sqlite"

# Pastikan folder dan file database dapat ditulis oleh user saat ini dan php-fpm (www-data)
chown -R "${RUN_USER}:www-data" "${WEB_DIR}/database"
chmod -R 775 "${WEB_DIR}/database"

cd "${WEB_DIR}"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force --no-interaction
php artisan config:clear
php artisan migrate --force

# 7. Set Permissions
log_step "Mengatur hak akses file (permissions)..."
RUN_USER="${SUDO_USER:-$USER}"
chown -R "${RUN_USER}:www-data" "${WEB_DIR}"
chmod -R g+rX "${WEB_DIR}"
chmod -R 775 "${WEB_DIR}/storage" "${WEB_DIR}/bootstrap/cache" "${WEB_DIR}/database"
chmod o+x "${PROJECT_DIR}" 2>/dev/null || true
chmod -R o+rX "${WEB_DIR}/public" 2>/dev/null || true

# 8. Setup Nginx
log_step "Mengonfigurasi Nginx untuk domain ${WEBSITE_DOMAIN}..."

PHP_VER="$(detect_php_version)"
FPM_SOCK="php${PHP_VER}-fpm.sock"
# Path Nginx default
NGINX_CONF="/etc/nginx/sites-available/jualan-web-${WEBSITE_DOMAIN}"
NGINX_ENABLED="/etc/nginx/sites-enabled/jualan-web-${WEBSITE_DOMAIN}"

# Deteksi jika Nginx hanya menggunakan conf.d (seperti webpanel/VPN script)
if [[ ! -d "/etc/nginx/sites-enabled" ]] || ! grep -q "sites-enabled" /etc/nginx/nginx.conf 2>/dev/null; then
    NGINX_CONF="/etc/nginx/conf.d/jualan-web.conf"
    NGINX_ENABLED=""
fi

# Deteksi jika HAProxy/Xray berjalan di port 80/443
IS_TUNNEL=0
if ss -tulpn 2>/dev/null | grep -E ":80|:443" | grep -q "haproxy\|xray"; then
    IS_TUNNEL=1
fi

if [[ "${IS_TUNNEL}" -eq 1 ]]; then
    log_step "Mendeteksi HAProxy/Xray aktif di port 80/443. Mengonfigurasi Nginx untuk proxy_protocol (Port 1010 & 1013)..."
    
    # Render konfigurasi Nginx khusus tunnel
    cat > "${NGINX_CONF}" <<EOF
server {
    # Port 1010 (untuk HTTP/1.1)
    listen 1010 proxy_protocol;
    listen [::]:1010 proxy_protocol;

    # Port 1013 (untuk HTTP/2)
    listen 1013 http2 proxy_protocol;
    listen [::]:1013 http2 proxy_protocol;

    server_name ${WEBSITE_DOMAIN};

    # Wajib ada agar Nginx bisa mengurai header PROXY protocol dari HAProxy
    set_real_ip_from 127.0.0.1;
    real_ip_header proxy_protocol;

    # Redirect HTTP (Port 80/8080/8880/2082) ke HTTPS secara otomatis
    # if (\$proxy_protocol_server_port = 80) {
    #     return 301 https://\$host\$request_uri;
    # }
    # if (\$proxy_protocol_server_port = 8080) {
    #     return 301 https://\$host\$request_uri;
    # }
    # if (\$proxy_protocol_server_port = 8880) {
    #     return 301 https://\$host\$request_uri;
    # }
    # if (\$proxy_protocol_server_port = 2082) {
    #     return 301 https://\$host\$request_uri;
    # }

    # Deteksi skema HTTPS asal
    set \$my_https "off";
    set \$my_proto "http";
    if (\$proxy_protocol_server_port = 443) {
        set \$my_https "on";
        set \$my_proto "https";
    }
    if (\$proxy_protocol_server_port = 8443) {
        set \$my_https "on";
        set \$my_proto "https";
    }
    if (\$proxy_protocol_server_port = 2096) {
        set \$my_https "on";
        set \$my_proto "https";
    }
    if (\$proxy_protocol_server_port = 2087) {
        set \$my_https "on";
        set \$my_proto "https";
    }

    # Deteksi status HTTPS dari Cloudflare
    if (\$http_x_forwarded_proto = "https") {
        set \$my_https "on";
        set \$my_proto "https";
    }

    root ${PROJECT_DIR}/web/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 100M;

    # Laravel routes
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP-FPM
    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/${FPM_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
        fastcgi_param HTTPS \$my_https;
        fastcgi_param HTTP_X_FORWARDED_PROTO \$my_proto;
    }

    # API listener proxy (FastAPI pada port ${API_PORT})
    location /listener/ {
        proxy_pass http://127.0.0.1:${API_PORT};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$my_proto;
    }

    location /health {
        proxy_pass http://127.0.0.1:${API_PORT};
        proxy_set_header Host \$host;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Deny hidden files
    location ~ /\.(?!well-known) {
        deny all;
    }

    # Static assets caching
    location ~* \.(css|js|ico|png|jpg|jpeg|gif|svg|woff2?|ttf|eot)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    access_log /var/log/nginx/jualan-web-${WEBSITE_DOMAIN}-access.log;
    error_log /var/log/nginx/jualan-web-${WEBSITE_DOMAIN}-error.log;
}
EOF
else
    # Render Nginx configuration dari template standar
    sed \
      -e "s|__DOMAIN__|${WEBSITE_DOMAIN}|g" \
      -e "s|__PROJECT_DIR__|${PROJECT_DIR}|g" \
      -e "s|__PHP_FPM_SOCK__|${FPM_SOCK}|g" \
      -e "s|__API_PORT__|${API_PORT}|g" \
      "${PROJECT_DIR}/ops/nginx/jualan-web.conf.template" > "${NGINX_CONF}"
fi

# Enable site in Nginx (jika folder sites-enabled digunakan)
if [[ -n "${NGINX_ENABLED}" ]]; then
    ln -sf "${NGINX_CONF}" "${NGINX_ENABLED}"
fi

nginx -t
systemctl reload nginx

# 9. Register SSL (Certbot)
if [[ "${IS_TUNNEL}" -eq 1 ]]; then
    log_step "Mendeteksi sistem tunnel. Registrasi SSL otomatis dilewati."
    echo "========================================================="
    echo "Silakan jalankan perintah berikut secara manual di VPS:"
    echo "  1. sudo systemctl stop haproxy nginx"
    echo "  2. sudo certbot certonly --standalone -d ${WEBSITE_DOMAIN}"
    echo "  3. sudo sh -c \"cat /etc/letsencrypt/live/${WEBSITE_DOMAIN}/fullchain.pem /etc/letsencrypt/live/${WEBSITE_DOMAIN}/privkey.pem > /etc/haproxy/${WEBSITE_DOMAIN}.pem\""
    echo "  4. Tambahkan 'crt /etc/haproxy/${WEBSITE_DOMAIN}.pem' ke baris bind :443 di /etc/haproxy/haproxy.cfg"
    echo "  5. sudo systemctl start haproxy nginx"
    echo "========================================================="
else
    log_step "Mendaftarkan SSL (Certbot) untuk ${WEBSITE_DOMAIN}..."
    certbot --nginx \
      -d "${WEBSITE_DOMAIN}" \
      --non-interactive \
      --agree-tos \
      --redirect \
      --register-unsafely-without-email \
      || echo "Peringatan: Certbot gagal mendaftarkan SSL. Website berjalan dengan HTTP."
fi

# 10. Install Systemd Services
log_step "Mendaftarkan systemd services untuk bot & API..."
PYTHON_BIN="${VENV_DIR}/bin/python"

render_unit() {
  local src="$1"
  local dst="$2"
  sed \
    -e "s|__PROJECT_DIR__|${PROJECT_DIR}|g" \
    -e "s|__PYTHON_BIN__|${PYTHON_BIN}|g" \
    -e "s|__RUN_USER__|${RUN_USER}|g" \
    "${src}" > "${dst}"
}

render_unit "${PROJECT_DIR}/ops/systemd/jualan-bot.service" "/etc/systemd/system/jualan-bot.service"
render_unit "${PROJECT_DIR}/ops/systemd/jualan-api.service" "/etc/systemd/system/jualan-api.service"
render_unit "${PROJECT_DIR}/ops/systemd/jualan-backup.service" "/etc/systemd/system/jualan-backup.service"
render_unit "${PROJECT_DIR}/ops/systemd/jualan-backup.timer" "/etc/systemd/system/jualan-backup.timer"

systemctl daemon-reload
systemctl enable jualan-bot.service jualan-api.service jualan-backup.timer
systemctl restart jualan-bot.service jualan-api.service jualan-backup.timer

# Create alias link
ln -sf "${PROJECT_DIR}/ops/jualan" /usr/local/bin/jualan

log_step "INSTALASI BERHASIL!"
echo "========================================================="
echo "Website Jualan: https://${WEBSITE_DOMAIN}"
echo "Uvicorn API Port: ${API_PORT}"
echo "Database: SQLite (database/database.sqlite)"
echo "========================================================="
echo "Silakan gunakan perintah 'jualan' untuk mengelola:"
echo "  jualan status"
echo "  jualan config"
