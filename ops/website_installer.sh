#!/usr/bin/env bash
set -euo pipefail

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
PROJECT_DIR="$(cd "$(dirname "${SCRIPT_PATH}")/.." && pwd)"
WEB_DIR="${PROJECT_DIR}/web"
ENV_FILE="${PROJECT_DIR}/.env"
WEB_ENV_FILE="${WEB_DIR}/.env"
OPS_DIR="${PROJECT_DIR}/ops"

log_step() {
  echo "[website-installer] $*"
}

load_env() {
  if [[ -f "${ENV_FILE}" ]]; then
    set -a
    # shellcheck disable=SC1090
    source "${ENV_FILE}"
    set +a
  fi
}

usage() {
  cat <<'EOF'
Usage: website_installer.sh <command> [args]

Commands:
  install [domain]   Full install: PHP, Nginx, Composer, Laravel, SSL
  configure-domain   Configure/change domain + SSL certificate
  status             Show website service status
  restart            Restart PHP-FPM + Nginx
  logs               Show recent Nginx error + Laravel log
  uninstall          Remove Nginx config + disable services
EOF
}

check_root() {
  if [[ "$(id -u)" -ne 0 ]]; then
    echo "Script ini harus dijalankan sebagai root (sudo)." >&2
    exit 1
  fi
}

detect_php_version() {
  # Prefer 8.3, fallback to whatever is available
  for v in 8.3 8.4 8.2; do
    if command -v "php${v}" >/dev/null 2>&1; then
      echo "${v}"
      return
    fi
  done
  echo "8.3"
}

install_system_packages() {
  log_step "Menginstall system packages..."
  apt-get update -qq

  # Install add-apt-repository tool
  apt-get install -y -qq software-properties-common

  # Add Ondrej PHP PPA for PHP 8.3 support
  LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php
  apt-get update -qq

  local php_ver
  php_ver="$(detect_php_version)"

  apt-get install -y -qq \
    nginx \
    certbot \
    python3-certbot-nginx \
    "php${php_ver}-fpm" \
    "php${php_ver}-cli" \
    "php${php_ver}-curl" \
    "php${php_ver}-mbstring" \
    "php${php_ver}-xml" \
    "php${php_ver}-zip" \
    "php${php_ver}-sqlite3" \
    "php${php_ver}-mysql" \
    "php${php_ver}-gd" \
    "php${php_ver}-bcmath" \
    "php${php_ver}-intl" \
    mysql-server \
    python3-pymysql \
    python3-sqlalchemy \
    unzip \
    curl \
    jq

  log_step "PHP version: $(php -v | head -n1)"
}

install_composer() {
  if command -v composer >/dev/null 2>&1; then
    log_step "Composer sudah terinstall: $(composer --version 2>/dev/null | head -n1)"
    return
  fi

  log_step "Menginstall Composer..."
  local expected_sig
  expected_sig="$(curl -sS https://composer.github.io/installer.sig)"
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  local actual_sig
  actual_sig="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

  if [[ "${expected_sig}" != "${actual_sig}" ]]; then
    echo "ERROR: Composer installer signature mismatch." >&2
    rm -f composer-setup.php
    exit 1
  fi

  php composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
  rm -f composer-setup.php
  log_step "Composer terinstall: $(composer --version 2>/dev/null | head -n1)"
}

setup_mysql() {
  log_step "Setup MySQL Database..."
  
  systemctl start mysql
  systemctl enable mysql

  local db_name="bot_jualan"
  local db_user="jualan_user"
  local db_pass
  
  # Generate a random password if not provided
  load_env
  if [[ -n "${MYSQL_PASSWORD:-}" ]]; then
    db_pass="${MYSQL_PASSWORD}"
  else
    db_pass="$(openssl rand -hex 16)"
    _set_env_value "${ENV_FILE}" "MYSQL_PASSWORD" "${db_pass}"
  fi

  # Create DB and user
  mysql -e "CREATE DATABASE IF NOT EXISTS ${db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -e "CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';"
  mysql -e "ALTER USER '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';"
  mysql -e "GRANT ALL PRIVILEGES ON ${db_name}.* TO '${db_user}'@'localhost';"
  mysql -e "FLUSH PRIVILEGES;"

  log_step "MySQL setup selesai (User: ${db_user})."
  
  # Return the connection string info for variables
  echo "${db_user}:${db_pass}@localhost:3306/${db_name}" > /tmp/mysql_creds.txt
}

setup_laravel() {
  log_step "Setup Laravel project di ${WEB_DIR}..."

  if [[ ! -f "${WEB_DIR}/composer.json" ]]; then
    echo "ERROR: Folder web/ tidak ditemukan atau tidak berisi project Laravel." >&2
    echo "Pastikan folder web/ sudah ada di dalam project." >&2
    exit 1
  fi

  cd "${WEB_DIR}"

  # Install dependencies
  composer install --no-dev --optimize-autoloader --no-interaction

  # Create .env if not exists
  if [[ ! -f "${WEB_ENV_FILE}" ]]; then
    cp .env.example .env
  fi

  # Generate app key
  php artisan key:generate --force --no-interaction

  # Configure Laravel .env from main project .env
  load_env
  local bot_token="${BOT_TOKEN:-}"
  local shared_secret="${LISTENER_SHARED_SECRET:-change-me}"
  local website_domain="${WEBSITE_DOMAIN:-}"
  local bot_username="${WEBSITE_BOT_USERNAME:-}"

  # Auto fetch bot username if we have a token but no username
  if [[ -n "${bot_token}" && -z "${bot_username}" ]]; then
      log_step "Fetching bot username from Telegram API..."
      local me_resp
      me_resp=$(curl -s "https://api.telegram.org/bot${bot_token}/getMe")
      if echo "${me_resp}" | grep -q '"ok":true'; then
          bot_username=$(echo "${me_resp}" | jq -r '.result.username')
          log_step "Found bot username: @${bot_username}"
          _set_env_value "${ENV_FILE}" "WEBSITE_BOT_USERNAME" "${bot_username}"
      else
          log_step "Failed to fetch bot username. Please set it manually."
      fi
  fi

  # Setup MySQL Connection
  local mysql_creds
  mysql_creds=$(cat /tmp/mysql_creds.txt 2>/dev/null || echo "")
  local db_name="bot_jualan"
  local db_user="jualan_user"
  local db_pass=""
  
  if [[ -n "${mysql_creds}" ]]; then
      db_user=$(echo "${mysql_creds}" | cut -d':' -f1)
      db_pass=$(echo "${mysql_creds}" | cut -d':' -f2 | cut -d'@' -f1)
  fi

  sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" "${WEB_ENV_FILE}"
  _set_env_value "${WEB_ENV_FILE}" "DB_HOST" "127.0.0.1"
  _set_env_value "${WEB_ENV_FILE}" "DB_PORT" "3306"
  _set_env_value "${WEB_ENV_FILE}" "DB_DATABASE" "${db_name}"
  _set_env_value "${WEB_ENV_FILE}" "DB_USERNAME" "${db_user}"
  _set_env_value "${WEB_ENV_FILE}" "DB_PASSWORD" "${db_pass}"
  
  # Hapus konfigurasi SQLite jika ada
  sed -i "\|DB_DATABASE=../data/bot_jualan.db|d" "${WEB_ENV_FILE}"

  # Set custom env values
  _set_env_value "${WEB_ENV_FILE}" "TELEGRAM_BOT_TOKEN" "${bot_token}"
  _set_env_value "${WEB_ENV_FILE}" "TELEGRAM_BOT_USERNAME" "${bot_username}"
  _set_env_value "${WEB_ENV_FILE}" "TELEGRAM_SHARED_SECRET" "${shared_secret}"
  _set_env_value "${WEB_ENV_FILE}" "WEBSITE_DOMAIN" "${website_domain}"
  _set_env_value "${WEB_ENV_FILE}" "SESSION_LIFETIME" "43200"
  _set_env_value "${WEB_ENV_FILE}" "REMEMBER_ME_DAYS" "30"
  _set_env_value "${WEB_ENV_FILE}" "APP_URL" "https://${website_domain}"

  # Update Python Bot to use MySQL
  local python_mysql_url="mysql+pymysql://${mysql_creds}"
  _set_env_value "${ENV_FILE}" "DATABASE_URL" "${python_mysql_url}"

  # Run migration script SQLite -> MySQL (or just init MySQL schema via python if SQLite is empty)
  if [[ -f "${OPS_DIR}/migrate_sqlite_to_mysql.py" ]]; then
      log_step "Menjalankan migrasi data dari SQLite ke MySQL..."
      python3 "${OPS_DIR}/migrate_sqlite_to_mysql.py" --mysql-url="${python_mysql_url}"
  fi

  # Run laravel migrations (just in case there are specific laravel tables like sessions/jobs etc, but we rely on python for core tables)
  # But we must be careful not to overwrite the schema managed by Python. Laravel's default migrations include users table which might clash.
  # Since python's Base.metadata.create_all handles it, we don't need `php artisan migrate` unless we have Laravel-only tables.
  # Let's run it just for Laravel specific things, assuming they don't clash. Wait, `users` table might clash if Laravel's default migration runs.
  # Actually, `ops/migrate_sqlite_to_mysql.py` creates ALL python tables. If Laravel runs migrate, it will see `users` exists if we created it.
  # To avoid issues, we should just let python handle schema. We only need `telegram_login_tokens` which is in Python `models.py`.
  # So we SKIP `php artisan migrate` to avoid collisions with default Laravel migrations!
  
  # Set permissions
  local run_user
  run_user="$(stat -c '%U' "${PROJECT_DIR}")"
  chown -R "${run_user}:www-data" "${WEB_DIR}"
  chmod -R 775 "${WEB_DIR}/storage" "${WEB_DIR}/bootstrap/cache"

  log_step "Laravel setup selesai."
}

_set_env_value() {
  local file="$1" key="$2" value="$3"
  if grep -q "^${key}=" "${file}" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${value}|" "${file}"
  else
    echo "${key}=${value}" >> "${file}"
  fi
}

setup_nginx() {
  local domain="$1"

  log_step "Konfigurasi Nginx untuk domain: ${domain}"

  local php_ver
  php_ver="$(detect_php_version)"
  local fpm_sock="/run/php/php${php_ver}-fpm.sock"

  local nginx_conf="/etc/nginx/sites-available/jualan-web"

  cat > "${nginx_conf}" <<NGINX_EOF
server {
    listen 80;
    server_name ${domain};

    root ${WEB_DIR}/public;
    index index.php index.html;

    charset utf-8;

    # Laravel routes
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP-FPM
    location ~ \.php\$ {
        fastcgi_pass unix:${fpm_sock};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # API listener proxy (existing FastAPI)
    location /listener/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location /health {
        proxy_pass http://127.0.0.1:8080;
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
    location ~* \.(css|js|ico|png|jpg|jpeg|gif|svg|woff2?|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    access_log /var/log/nginx/jualan-web-access.log;
    error_log /var/log/nginx/jualan-web-error.log;
}
NGINX_EOF

  # Enable site
  ln -sf "${nginx_conf}" /etc/nginx/sites-enabled/jualan-web

  # Remove default if exists
  rm -f /etc/nginx/sites-enabled/default

  # Test config
  nginx -t

  # Reload
  systemctl reload nginx

  log_step "Nginx dikonfigurasi dan direload."
}

setup_ssl() {
  local domain="$1"

  log_step "Setup SSL certificate untuk ${domain}..."

  certbot --nginx \
    -d "${domain}" \
    --non-interactive \
    --agree-tos \
    --redirect \
    --register-unsafely-without-email \
    || {
      echo "WARNING: Certbot gagal. Website akan jalan di HTTP dulu." >&2
      echo "Jalankan ulang: sudo certbot --nginx -d ${domain}" >&2
    }

  log_step "SSL setup selesai."
}

update_main_env() {
  local domain="$1"
  load_env

  _set_env_value "${ENV_FILE}" "WEBSITE_ENABLED" "true"
  _set_env_value "${ENV_FILE}" "WEBSITE_DOMAIN" "${domain}"

  log_step "Main .env diperbarui: WEBSITE_ENABLED=true, WEBSITE_DOMAIN=${domain}"
}

do_install() {
  local domain="${1:-}"

  if [[ -z "${domain}" ]]; then
    read -rp "Masukkan domain website (contoh: shop.example.com): " domain
  fi

  if [[ -z "${domain}" ]]; then
    echo "Domain tidak boleh kosong." >&2
    exit 1
  fi

  check_root

  install_system_packages
  setup_mysql
  install_composer
  setup_laravel
  setup_nginx "${domain}"
  setup_ssl "${domain}"
  update_main_env "${domain}"

  echo ""
  echo "============================================"
  echo "  ✅ Website berhasil diinstall!"
  echo "  Domain: https://${domain}"
  echo "  Laravel: ${WEB_DIR}"
  echo "============================================"
  echo ""
  echo "Restart bot agar membaca WEBSITE_ENABLED=true:"
  echo "  sudo systemctl restart jualan-bot.service"
}

do_configure_domain() {
  local domain="${1:-}"

  if [[ -z "${domain}" ]]; then
    read -rp "Masukkan domain baru: " domain
  fi

  check_root
  setup_nginx "${domain}"
  setup_ssl "${domain}"
  update_main_env "${domain}"

  # Update Laravel .env
  _set_env_value "${WEB_ENV_FILE}" "APP_URL" "https://${domain}"
  _set_env_value "${WEB_ENV_FILE}" "WEBSITE_DOMAIN" "${domain}"

  php "${WEB_DIR}/artisan" config:clear 2>/dev/null || true

  echo "Domain diperbarui ke: https://${domain}"
}

do_status() {
  echo "=== Website Status ==="
  echo ""

  load_env
  echo "WEBSITE_ENABLED: ${WEBSITE_ENABLED:-false}"
  echo "WEBSITE_DOMAIN: ${WEBSITE_DOMAIN:-<not set>}"
  echo ""

  local php_ver
  php_ver="$(detect_php_version)"

  echo "--- PHP-FPM (php${php_ver}-fpm) ---"
  systemctl status "php${php_ver}-fpm" --no-pager -l 2>/dev/null | head -5 || echo "Not installed"
  echo ""

  echo "--- Nginx ---"
  systemctl status nginx --no-pager -l 2>/dev/null | head -5 || echo "Not installed"
  echo ""

  if [[ -f "${WEB_DIR}/artisan" ]]; then
    echo "--- Laravel ---"
    php "${WEB_DIR}/artisan" --version 2>/dev/null || echo "Laravel not available"
  else
    echo "Laravel: Not installed (web/ folder missing)"
  fi
}

do_restart() {
  check_root

  local php_ver
  php_ver="$(detect_php_version)"

  log_step "Restarting PHP-FPM dan Nginx..."
  systemctl restart "php${php_ver}-fpm"
  systemctl restart nginx
  log_step "Restart selesai."
}

do_logs() {
  echo "=== Nginx Error Log (last 30 lines) ==="
  tail -n 30 /var/log/nginx/jualan-web-error.log 2>/dev/null || echo "(no log)"
  echo ""
  echo "=== Laravel Log (last 30 lines) ==="
  tail -n 30 "${WEB_DIR}/storage/logs/laravel.log" 2>/dev/null || echo "(no log)"
}

do_uninstall() {
  check_root

  log_step "Menghapus konfigurasi website..."
  rm -f /etc/nginx/sites-enabled/jualan-web
  rm -f /etc/nginx/sites-available/jualan-web
  systemctl reload nginx 2>/dev/null || true

  _set_env_value "${ENV_FILE}" "WEBSITE_ENABLED" "false"

  log_step "Website config dihapus. File Laravel di web/ tidak dihapus."
}

cmd="${1:-}"
case "${cmd}" in
  install)
    do_install "${2:-}"
    ;;
  configure-domain)
    do_configure_domain "${2:-}"
    ;;
  status)
    do_status
    ;;
  restart)
    do_restart
    ;;
  logs)
    do_logs
    ;;
  uninstall)
    do_uninstall
    ;;
  *)
    usage
    exit 1
    ;;
esac
