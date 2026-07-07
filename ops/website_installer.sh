#!/usr/bin/env bash
set -euo pipefail

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
PROJECT_DIR="$(cd "$(dirname "${SCRIPT_PATH}")/.." && pwd)"
WEB_DIR="${PROJECT_DIR}/web"
ENV_FILE="${PROJECT_DIR}/.env"
WEB_ENV_FILE="${WEB_DIR}/.env"
OPS_DIR="${PROJECT_DIR}/ops"

PROGRESS_ENABLED=0
TOTAL_STEPS=18
CURRENT_STEP=0
LAST_PROGRESS_TEXT="Menyiapkan instalasi..."

restore_terminal() {
  if [[ "${PROGRESS_ENABLED}" == "1" ]]; then
    printf "\033[r"      # reset scrolling region
    local lines
    lines=$(tput lines 2>/dev/null || echo 24)
    printf "\033[%d;1H\033[K" "$lines" # move to bottom and clear line
    printf "\033[?25h"   # show cursor
    PROGRESS_ENABLED=0   # disable so it doesn't run twice
  fi
}

handle_winch() {
  if [[ "${PROGRESS_ENABLED}" == "1" ]]; then
    local lines
    lines=$(tput lines 2>/dev/null || echo 24)
    local scroll_lines=$((lines - 2))
    if [[ $scroll_lines -lt 5 ]]; then scroll_lines=5; fi
    printf "\033[1;%dr" "$scroll_lines"
    update_progress "${LAST_PROGRESS_TEXT}"
  fi
}

init_progress() {
  PROGRESS_ENABLED=1
  CURRENT_STEP=0
  trap 'restore_terminal; exit 1' INT TERM
  trap 'restore_terminal' EXIT
  trap 'handle_winch' WINCH

  printf "\033[?25l" # hide cursor
  handle_winch
  printf "\033[2J\033[1;1H" # clear screen and move to top
}

update_progress() {
  local text="$1"
  LAST_PROGRESS_TEXT="${text}"
  if [[ "${PROGRESS_ENABLED}" != "1" ]]; then return; fi
  
  local lines
  lines=$(tput lines 2>/dev/null || echo 24)
  local cols
  cols=$(tput cols 2>/dev/null || echo 80)
  
  local prog_line=$((lines - 1))
  
  local percent=$(( CURRENT_STEP * 100 / TOTAL_STEPS ))
  if [[ $percent -gt 100 ]]; then percent=100; fi
  
  local prefix="Progress: [${percent}%] "
  local bar_len=$((cols - ${#prefix} - ${#text} - 4))
  if [[ $bar_len -lt 10 ]]; then bar_len=10; fi
  
  local filled_len=$(( percent * bar_len / 100 ))
  local empty_len=$(( bar_len - filled_len ))
  
  local bar=""
  for ((i=0; i<filled_len; i++)); do bar="${bar}#"; done
  for ((i=0; i<empty_len; i++)); do bar="${bar}-"; done
  
  printf "\0337" # save cursor
  printf "\033[%d;1H\033[K" "$prog_line" # move to progress line and clear
  printf "\033[44;37m %s[%s] %s \033[0m" "${prefix}" "${bar}" "${text}"
  printf "\0338" # restore cursor
}

log_step() {
  if [[ "${PROGRESS_ENABLED}" == "1" ]]; then
    CURRENT_STEP=$((CURRENT_STEP + 1))
    echo -e "\n\033[1;32m[+] $*\033[0m"
    update_progress "$*"
  else
    echo "[website-installer] $*"
  fi
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
  # If PHP is already installed, get its exact version
  if command -v php >/dev/null 2>&1; then
    php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'
    return
  fi

  # Try to find which specific PHP version is available in apt-cache
  for v in 8.4 8.3 8.2 8.1; do
    if apt-cache show "php${v}-fpm" >/dev/null 2>&1; then
      echo "${v}"
      return
    fi
  done
  
  # Fallback to empty string for unversioned packages
  echo ""
}

install_system_packages() {
  log_step "Menginstall system packages..."
  apt-get update -qq || true

  # Install add-apt-repository tool
  apt-get install -y -qq software-properties-common

  # Add Ondrej PHP PPA for PHP 8.3 support, ignoring errors if OS is unsupported (like 'questing')
  LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php || true
  apt-get update -qq || true

  local php_ver
  php_ver="$(detect_php_version)"

  if [[ -z "${php_ver}" ]]; then
    log_step "Tidak menemukan paket PHP versi spesifik, menggunakan paket bawaan OS..."
    apt-get install -y -qq \
      nginx certbot python3-certbot-nginx \
      php-fpm php-cli php-curl php-mbstring php-xml php-zip php-sqlite3 php-mysql php-gd php-bcmath php-intl \
      mysql-server python3-pymysql python3-sqlalchemy unzip curl jq
  else
    log_step "Menggunakan paket PHP versi ${php_ver}..."
    apt-get install -y -qq \
      nginx certbot python3-certbot-nginx \
      "php${php_ver}-fpm" "php${php_ver}-cli" "php${php_ver}-curl" "php${php_ver}-mbstring" \
      "php${php_ver}-xml" "php${php_ver}-zip" "php${php_ver}-sqlite3" "php${php_ver}-mysql" \
      "php${php_ver}-gd" "php${php_ver}-bcmath" "php${php_ver}-intl" \
      mysql-server python3-pymysql python3-sqlalchemy unzip curl jq
  fi

  log_step "PHP version: $(php -v | head -n1)"
}

setup_swap() {
  if [[ $(swapon --show | wc -l) -eq 0 ]]; then
    log_step "Membuat swap file 1GB untuk mencegah kehabisan memori (OOM hang)..."
    fallocate -l 1G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=1024 status=none
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null 2>&1
    swapon /swapfile
    if ! grep -q "/swapfile" /etc/fstab; then
      echo '/swapfile none swap sw 0 0' >> /etc/fstab
    fi
  fi
}

install_composer() {
  if command -v composer >/dev/null 2>&1; then
    log_step "Composer sudah terinstall: $(composer --version 2>/dev/null | head -n1)"
    return
  fi

  log_step "Menginstall Composer..."
  local expected_sig
  expected_sig="$(curl -4 -sS --connect-timeout 15 https://composer.github.io/installer.sig)" || {
    echo "ERROR: Gagal mengunduh signature composer. Periksa koneksi internet." >&2
    exit 1
  }
  
  curl -4 -sS --connect-timeout 30 https://getcomposer.org/installer -o composer-setup.php || {
    echo "ERROR: Gagal mengunduh installer composer." >&2
    exit 1
  }
  
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

  # Create .env BEFORE composer install (composer runs artisan package:discover which needs valid APP_URL)
  if [[ ! -f "${WEB_ENV_FILE}" ]]; then
    cp .env.example .env
  fi

  # Load main project .env untuk mendapatkan domain
  load_env
  local bot_token="${BOT_TOKEN:-}"
  local shared_secret="${LISTENER_SHARED_SECRET:-change-me}"
  local website_domain="${WEBSITE_DOMAIN:-}"
  local bot_username="${WEBSITE_BOT_USERNAME:-}"

  # Set APP_URL dulu agar composer post-install scripts tidak crash
  _set_env_value "${WEB_ENV_FILE}" "APP_URL" "https://${website_domain}"
  _set_env_value "${WEB_ENV_FILE}" "APP_NAME" "Dzulfikrialifajri Store"

  # Install dependencies (allow running as root on VPS)
  export COMPOSER_ALLOW_SUPERUSER=1
  composer install --no-dev --optimize-autoloader --no-interaction

  # Generate app key
  php artisan key:generate --force --no-interaction

  # Auto fetch bot username if we have a token but no username
  if [[ -n "${bot_token}" && -z "${bot_username}" ]]; then
      log_step "Fetching bot username from Telegram API..."
      local me_resp
      me_resp=$(curl -4 -s --connect-timeout 10 "https://api.telegram.org/bot${bot_token}/getMe")
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

  # Update Python Bot to use MySQL
  local python_mysql_url="mysql+pymysql://${mysql_creds}"
  _set_env_value "${ENV_FILE}" "DATABASE_URL" "${python_mysql_url}"

  # Run migration script SQLite -> MySQL (or just init MySQL schema via python if SQLite is empty)
  if [[ -f "${OPS_DIR}/migrate_sqlite_to_mysql.py" ]]; then
      log_step "Menjalankan migrasi data dari SQLite ke MySQL..."
      local python_bin="python3"
      if [[ -f "${PROJECT_DIR}/.venv/bin/python" ]]; then
          python_bin="${PROJECT_DIR}/.venv/bin/python"
      fi
      "${python_bin}" "${OPS_DIR}/migrate_sqlite_to_mysql.py" --mysql-url="${python_mysql_url}"
  fi

  # Jalankan migrasi Laravel untuk membuat tabel bawaan (seperti sessions, jobs, cache)
  # File migrasi Laravel 0001_01_01_000000_create_users_table.php sudah dimodifikasi
  # agar aman dan tidak menabrak tabel 'users' yang dibuat oleh Python.
  log_step "Menjalankan migrasi database Laravel..."
  php artisan migrate --force

  # Pastikan PHP-FPM mengizinkan upload file besar (100M)
  local fpm_php_ver
  fpm_php_ver="$(detect_php_version)"
  if [[ -n "${fpm_php_ver}" ]]; then
    local fpm_ini="/etc/php/${fpm_php_ver}/fpm/php.ini"
    if [[ -f "${fpm_ini}" ]]; then
      log_step "Mengatur PHP upload_max_filesize dan post_max_size -> 100M..."
      sed -i 's/^upload_max_filesize\s*=.*/upload_max_filesize = 100M/' "${fpm_ini}" 2>/dev/null || true
      sed -i 's/^post_max_size\s*=.*/post_max_size = 100M/' "${fpm_ini}" 2>/dev/null || true
      sed -i 's/^memory_limit\s*=.*/memory_limit = 256M/' "${fpm_ini}" 2>/dev/null || true
    fi
  fi

  # Set permissions
  local run_user
  run_user="$(stat -c '%U' "${PROJECT_DIR}")"
  chown -R "${run_user}:www-data" "${WEB_DIR}"
  
  # Pastikan seluruh folder web bisa dibaca oleh group www-data
  chmod -R g+rX "${WEB_DIR}"
  
  # Beri akses write untuk folder storage dan bootstrap/cache
  chmod -R 775 "${WEB_DIR}/storage" "${WEB_DIR}/bootstrap/cache"

  # Agar Nginx (www-data) bisa mengakses folder di dalam /root, kita harus 
  # memberikan izin eksekusi (traversal) ke parent directories.
  chmod o+x /root 2>/dev/null || true
  chmod o+x "${PROJECT_DIR}" 2>/dev/null || true
  
  # Pastikan file static bisa dibaca oleh Nginx
  chmod -R o+rX "${WEB_DIR}/public" 2>/dev/null || true

  log_step "Laravel setup selesai."
}

_set_env_value() {
  local file="$1" key="$2" value="$3"
  # Trim outer quotes if already present to avoid double quoting
  local clean_val
  clean_val=$(echo "${value}" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
  if grep -q "^${key}=" "${file}" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=\"${clean_val}\"|" "${file}"
  else
    echo "${key}=\"${clean_val}\"" >> "${file}"
  fi
}

setup_nginx() {
  local domain="$1"

  log_step "Konfigurasi Nginx untuk domain: ${domain}"

  local php_ver
  php_ver="$(detect_php_version)"
  local fpm_sock="/run/php/php${php_ver}-fpm.sock"

  # Path Nginx default
  local nginx_conf="/etc/nginx/sites-available/jualan-web-${domain}"
  local nginx_enabled="/etc/nginx/sites-enabled/jualan-web-${domain}"

  # Deteksi jika Nginx hanya menggunakan conf.d (seperti webpanel/VPN script)
  if [[ ! -d "/etc/nginx/sites-enabled" ]] || ! grep -q "sites-enabled" /etc/nginx/nginx.conf 2>/dev/null; then
    nginx_conf="/etc/nginx/conf.d/jualan-web.conf"
    nginx_enabled=""
  fi

  # Deteksi jika HAProxy/Xray berjalan di port 80/443
  local is_tunnel=0
  if ss -tulpn 2>/dev/null | grep -E ":80|:443" | grep -q "haproxy\|xray"; then
    is_tunnel=1
  fi

  # Load API_PORT dari env
  load_env
  local api_port="${API_PORT:-8080}"
  # Jika API berjalan di port python custom (seperti 8086), gunakan port itu
  local active_api_port
  active_api_port=$(ss -tulpn 2>/dev/null | grep "python" | grep -oE ":[0-9]+" | head -1 | tr -d ":" || echo "")
  if [[ -n "${active_api_port}" ]]; then
    api_port="${active_api_port}"
  fi

  if [[ "${is_tunnel}" -eq 1 ]]; then
    log_step "Mendeteksi HAProxy/Xray aktif di port 80/443. Mengonfigurasi Nginx untuk proxy_protocol (Port 1010 & 1013)..."
    cat > "${nginx_conf}" <<NGINX_EOF
server {
    # Port 1010 (untuk HTTP/1.1)
    listen 1010 proxy_protocol;
    listen [::]:1010 proxy_protocol;

    # Port 1013 (untuk HTTP/2)
    listen 1013 http2 proxy_protocol;
    listen [::]:1013 http2 proxy_protocol;

    server_name ${domain};

    # Wajib ada agar Nginx bisa mengurai header PROXY protocol dari HAProxy
    set_real_ip_from 127.0.0.1;
    real_ip_header proxy_protocol;

    root ${WEB_DIR}/public;
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
        fastcgi_pass unix:${fpm_sock};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
        fastcgi_param HTTPS on;
        fastcgi_param HTTP_X_FORWARDED_PROTO https;
    }

    # API listener proxy (FastAPI pada port ${api_port})
    location /listener/ {
        proxy_pass http://127.0.0.1:${api_port};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }

    location /health {
        proxy_pass http://127.0.0.1:${api_port};
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

    access_log /var/log/nginx/jualan-web-${domain}-access.log;
    error_log /var/log/nginx/jualan-web-${domain}-error.log;
}
NGINX_EOF
  else
    # Standar port 80/IPv6
    cat > "${nginx_conf}" <<NGINX_EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${domain};

    root ${WEB_DIR}/public;
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
        fastcgi_pass unix:${fpm_sock};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }

    # API listener proxy (FastAPI pada port ${api_port})
    location /listener/ {
        proxy_pass http://127.0.0.1:${api_port};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location /health {
        proxy_pass http://127.0.0.1:${api_port};
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

    access_log /var/log/nginx/jualan-web-${domain}-access.log;
    error_log /var/log/nginx/jualan-web-${domain}-error.log;
}
NGINX_EOF
  fi

  # Enable site (jika sites-enabled digunakan)
  if [[ -n "${nginx_enabled}" ]]; then
    ln -sf "${nginx_conf}" "${nginx_enabled}"
    # Remove default if exists
    rm -f /etc/nginx/sites-enabled/default
  fi

  # Test config
  nginx -t

  # Restart PHP-FPM dan reload Nginx
  systemctl restart "php${php_ver}-fpm" 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
  systemctl reload nginx

  log_step "Nginx + PHP-FPM dikonfigurasi dan direstart."
}

setup_ssl() {
  local domain="$1"

  # Deteksi jika HAProxy/Xray berjalan di port 80/443
  local is_tunnel=0
  if ss -tulpn 2>/dev/null | grep -E ":80|:443" | grep -q "haproxy\|xray"; then
    is_tunnel=1
  fi

  if [[ "${is_tunnel}" -eq 1 ]]; then
    log_step "Mendeteksi sistem tunnel. Registrasi SSL otomatis dilewati."
    echo "========================================================="
    echo "Silakan jalankan perintah berikut secara manual di VPS:"
    echo "  1. sudo systemctl stop haproxy nginx"
    echo "  2. sudo certbot certonly --standalone -d ${domain}"
    echo "  3. sudo sh -c \"cat /etc/letsencrypt/live/${domain}/fullchain.pem /etc/letsencrypt/live/${domain}/privkey.pem > /etc/haproxy/${domain}.pem\""
    echo "  4. Tambahkan 'crt /etc/haproxy/${domain}.pem' ke baris bind :443 di /etc/haproxy/haproxy.cfg"
    echo "  5. sudo systemctl start haproxy nginx"
    echo "========================================================="
  else
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
  fi

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
  
  init_progress

  setup_swap
  install_system_packages
  setup_mysql
  install_composer
  update_main_env "${domain}"
  setup_laravel
  setup_nginx "${domain}"
  setup_ssl "${domain}"

  restore_terminal

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
  load_env
  local domain="${WEBSITE_DOMAIN:-}"
  local nginx_log="/var/log/nginx/jualan-web-${domain}-error.log"
  if [[ -z "${domain}" || ! -f "${nginx_log}" ]]; then
    nginx_log="/var/log/nginx/jualan-web-error.log"
  fi

  echo "=== Nginx Error Log (last 30 lines) ==="
  tail -n 30 "${nginx_log}" 2>/dev/null || echo "(no log)"
  echo ""
  echo "=== Laravel Log (last 30 lines) ==="
  tail -n 30 "${WEB_DIR}/storage/logs/laravel.log" 2>/dev/null || echo "(no log)"
}

do_uninstall() {
  check_root

  local confirm
  read -rp "⚠️  Hapus semua konfigurasi website? Database MySQL tetap dipertahankan. Ketik YES: " confirm
  if [[ "${confirm}" != "YES" ]]; then
    echo "Batal."
    exit 0
  fi

  echo ""
  log_step "Menghapus konfigurasi Nginx..."
  load_env
  local domain="${WEBSITE_DOMAIN:-}"
  if [[ -n "${domain}" ]]; then
    rm -f "/etc/nginx/sites-enabled/jualan-web-${domain}"
    rm -f "/etc/nginx/sites-available/jualan-web-${domain}"
  fi
  # Fallback/cleanup legacy config
  rm -f /etc/nginx/sites-enabled/jualan-web
  rm -f /etc/nginx/sites-available/jualan-web
  systemctl reload nginx 2>/dev/null || true

  # Hapus SSL certificates untuk domain website
  load_env
  local domain="${WEBSITE_DOMAIN:-}"
  if [[ -n "${domain}" ]]; then
    log_step "Menghapus sertifikat SSL untuk ${domain}..."
    certbot delete --cert-name "${domain}" --non-interactive 2>/dev/null || true
  fi

  # Hapus Ondrej PPA yang bermasalah (hanya jika ada)
  local ppa_file="/etc/apt/sources.list.d/ondrej-ubuntu-php-questing.sources"
  if [[ -f "${ppa_file}" ]]; then
    log_step "Menghapus PPA ondrej/php yang tidak kompatibel..."
    rm -f "${ppa_file}"
    apt-get update -qq 2>/dev/null || true
  fi

  # Bersihkan cache dan compiled files Laravel
  if [[ -f "${WEB_DIR}/artisan" ]]; then
    log_step "Membersihkan cache Laravel..."
    php "${WEB_DIR}/artisan" config:clear 2>/dev/null || true
    php "${WEB_DIR}/artisan" cache:clear 2>/dev/null || true
    php "${WEB_DIR}/artisan" view:clear 2>/dev/null || true
    php "${WEB_DIR}/artisan" route:clear 2>/dev/null || true
  fi

  # Hapus .env website
  log_step "Menghapus konfigurasi environment website (.env)..."
  rm -f "${WEB_ENV_FILE}"

  # Update main .env
  _set_env_value "${ENV_FILE}" "WEBSITE_ENABLED" "false"
  _set_env_value "${ENV_FILE}" "WEBSITE_DOMAIN" ""

  echo ""
  echo "============================================"
  echo "  ✅ Website berhasil di-uninstall!"
  echo ""
  echo "  Yang dihapus:"
  echo "    ✓ Konfigurasi Nginx"
  echo "    ✓ Sertifikat SSL (${domain:-n/a})"
  echo "    ✓ PPA ondrej/php (jika ada)"
  echo "    ✓ Cache & compiled Laravel"
  echo "    ✓ File .env website"
  echo ""
  echo "  Yang DIPERTAHANKAN:"
  echo "    ✓ Database MySQL (bot_jualan)"
  echo "    ✓ Source code Laravel di web/"
  echo "    ✓ PHP, Nginx, MySQL packages"
  echo "============================================"
  echo ""

  # Restart bot if service exists so it picks up WEBSITE_ENABLED=false
  if systemctl list-units --full -all | grep -Fq "jualan-bot.service"; then
      log_step "Me-restart jualan-bot.service agar bot menyembunyikan akses website..."
      systemctl restart jualan-bot.service || true
  fi
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
