#!/usr/bin/env bash
set -Eeuo pipefail

on_error() {
  local exit_code="$?"
  echo "ERROR: update_manager gagal pada baris ${BASH_LINENO[0]}: ${BASH_COMMAND}" >&2
  exit "${exit_code}"
}

trap on_error ERR

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${PROJECT_DIR}/ops/backups"
LAST_COMMIT_FILE="${BACKUP_DIR}/.last_commit"
ENV_FILE="${PROJECT_DIR}/.env"
RUNTIME_PRESERVE_FILES=(".env" "user_role.txt")

export GIT_CONFIG_COUNT=1
export GIT_CONFIG_KEY_0="safe.directory"
export GIT_CONFIG_VALUE_0="${PROJECT_DIR}"

PRESERVE_DIR=""
LOCAL_PATCH_FILE=""
AUTOSTASH_NAME=""
STASH_REF=""
STASH_CREATED=0

mkdir -p "${BACKUP_DIR}"

log_step() {
  echo "[update] $*"
}

sanitize_ref_value() {
  local value="$1"
  printf '%s' "${value}" | tr -d '\r\n' | sed 's/^[[:space:]]*//; s/[[:space:]]*$//'
}

has_local_changes() {
  if ! git diff --quiet || ! git diff --cached --quiet; then
    return 0
  fi

  if [[ -n "$(git ls-files --others --exclude-standard)" ]]; then
    return 0
  fi

  return 1
}

preserve_runtime_files() {
  local ts="$1"
  local rel_path
  local src
  local dst

  PRESERVE_DIR="${BACKUP_DIR}/preserve_${ts}"
  mkdir -p "${PRESERVE_DIR}"

  for rel_path in "${RUNTIME_PRESERVE_FILES[@]}"; do
    src="${PROJECT_DIR}/${rel_path}"
    dst="${PRESERVE_DIR}/${rel_path}"
    if [[ -f "${src}" ]]; then
      mkdir -p "$(dirname "${dst}")"
      cp -f "${src}" "${dst}"
    fi
  done
}

restore_runtime_files() {
  local rel_path
  local src
  local dst

  if [[ -z "${PRESERVE_DIR}" || ! -d "${PRESERVE_DIR}" ]]; then
    return
  fi

  for rel_path in "${RUNTIME_PRESERVE_FILES[@]}"; do
    src="${PRESERVE_DIR}/${rel_path}"
    dst="${PROJECT_DIR}/${rel_path}"
    if [[ -f "${src}" ]]; then
      mkdir -p "$(dirname "${dst}")"
      cp -f "${src}" "${dst}"
    fi
  done
}

stash_local_changes_for_update() {
  local ts="$1"

  if ! has_local_changes; then
    return
  fi

  LOCAL_PATCH_FILE="${BACKUP_DIR}/local_changes_${ts}.patch"
  {
    git diff --binary
    git diff --binary --cached
  } > "${LOCAL_PATCH_FILE}"

  AUTOSTASH_NAME="jualan-auto-update-${ts}"
  if ! git stash push --include-untracked -m "${AUTOSTASH_NAME}" >/dev/null; then
    echo "Gagal menyimpan perubahan lokal sementara. Update dibatalkan agar aman." >&2
    exit 1
  fi

  local stash_list
  stash_list="$(git stash list || true)"
  STASH_REF="$(awk -v needle="${AUTOSTASH_NAME}" 'index($0, needle) { print $1; exit }' <<< "${stash_list}" || true)"
  if [[ -z "${STASH_REF}" ]]; then
    STASH_REF="$(git stash list -n 1 --format='%gd' 2>/dev/null || true)"
  fi
  STASH_CREATED=1

  echo "Local changes disimpan sementara sebelum update."
  echo "Patch backup: ${LOCAL_PATCH_FILE}"
  if [[ -n "${STASH_REF}" ]]; then
    echo "Stash ref : ${STASH_REF}"
  fi
}

read_env_var() {
  local key="$1"
  if [[ ! -f "${ENV_FILE}" ]]; then
    return
  fi
  local val
  val="$(grep -E "^${key}=" "${ENV_FILE}" | head -n1 | cut -d'=' -f2- || true)"
  val="${val#\"}"
  val="${val%\"}"
  val="${val#\'}"
  val="${val%\'}"
  echo "${val}"
}

default_branch() {
  local configured
  configured="$(read_env_var UPDATE_BRANCH || true)"
  configured="$(sanitize_ref_value "${configured}")"
  if [[ -n "${configured}" ]]; then
    echo "${configured}"
    return
  fi

  local discovered
  discovered="$(git remote show origin 2>/dev/null | sed -n '/HEAD branch/s/.*: //p' || true)"
  discovered="$(sanitize_ref_value "${discovered}")"
  if [[ -n "${discovered}" ]]; then
    echo "${discovered}"
    return
  fi

  discovered="$(git symbolic-ref --quiet --short HEAD 2>/dev/null || true)"
  discovered="$(sanitize_ref_value "${discovered}")"
  if [[ -n "${discovered}" ]]; then
    echo "${discovered}"
    return
  fi

  echo "main"
}

check_update() {
  cd "${PROJECT_DIR}"
  git fetch origin

  local branch local_commit remote_commit
  branch="$(default_branch)"
  local_commit="$(git rev-parse HEAD)"
  remote_commit="$(git rev-parse "origin/${branch}")"

  echo "Repo: ${PROJECT_DIR}"
  echo "Branch: ${branch}"
  echo "Local : ${local_commit}"
  echo "Remote: ${remote_commit}"

  if [[ "${local_commit}" == "${remote_commit}" ]]; then
    echo "Status: up-to-date"
  else
    echo "Status: update available"
  fi
}

backup_before_update() {
  cd "${PROJECT_DIR}"
  local ts archive free_kb
  ts="$(date +%Y%m%d_%H%M%S)"
  archive="${BACKUP_DIR}/update_${ts}.tar.gz"

  mkdir -p "${BACKUP_DIR}"

  # Hapus file tar yang rusak/berukuran 0 jika ada
  find "${BACKUP_DIR}" -maxdepth 1 -name "update_*.tar.gz" -size 0 -delete 2>/dev/null || true

  # Cek sisa ruang disk di partisi target backup (dalam KB)
  free_kb=$(df -k "${BACKUP_DIR}" 2>/dev/null | awk 'NR==2 {print $4}' || echo "1000000")

  git rev-parse HEAD > "${LAST_COMMIT_FILE}"
  echo "Previous commit: $(cat "${LAST_COMMIT_FILE}")"

  # Jika ruang disk menipis (< 400 MB / 409600 KB) atau SKIP_BACKUP aktif, buat backup ringan darurat
  if [[ "${SKIP_BACKUP:-0}" == "1" || "${free_kb}" -lt 409600 ]]; then
    log_step "PERINGATAN: Sisa disk menipis (${free_kb} KB) atau SKIP_BACKUP aktif. Membuat backup ringan (hanya berkas konfigurasi penting)..."
    local cfg_backup="${BACKUP_DIR}/update_cfg_${ts}.tar.gz"
    tar -czf "${cfg_backup}" \
      --exclude='.git' \
      --exclude='web/vendor' \
      --exclude='web/node_modules' \
      .env web/.env user_role.txt 2>/dev/null || true
    echo "Lightweight config backup created: ${cfg_backup}"
    return 0
  fi

  # Coba buat backup penuh dengan pengecualian direktori berat
  if tar \
    --exclude='.venv' \
    --exclude='.git' \
    --exclude='.gradle' \
    --exclude='.pub_cache' \
    --exclude='ops/backups' \
    --exclude='reference design' \
    --exclude='web/vendor' \
    --exclude='web/node_modules' \
    --exclude='web/storage/logs' \
    --exclude='web/storage/app/backups' \
    -czf "${archive}" \
    . 2>/dev/null; then
    echo "Backup created: ${archive}"
  else
    log_step "PERINGATAN: Gagal membuat full archive backup (ruang disk tidak cukup). Melanjutkan proses update tanpa full archive..."
    rm -f "${archive}" 2>/dev/null || true
  fi

  # Simpan maksimal 2 arsip backup update terbaru agar tidak membebani kapasitas disk VPS
  mapfile -t old_updates < <(ls -1t "${BACKUP_DIR}"/update_*.tar.gz 2>/dev/null | tail -n +3 || true)
  if (( ${#old_updates[@]} > 0 )); then
    rm -f "${old_updates[@]}" 2>/dev/null || true
  fi
}

install_requirements() {
  cd "${PROJECT_DIR}"
  if [[ -x "${PROJECT_DIR}/.venv/bin/pip" ]]; then
    "${PROJECT_DIR}/.venv/bin/pip" install -r requirements.txt
  fi
}

update_website() {
  cd "${PROJECT_DIR}"

  local web_dir="${PROJECT_DIR}/web"

  # --- Deteksi apakah website terinstall dan aktif ---
  # Cek 1: composer.json harus ada (struktur proyek Laravel)
  if [[ ! -f "${web_dir}/composer.json" ]]; then
    log_step "website: dilewati (composer.json tidak ditemukan)"
    return
  fi

  # Cek 2: website dianggap aktif jika SALAH SATU terpenuhi:
  #   a) WEBSITE_ENABLED=true di .env utama
  #   b) web/.env DAN web/artisan ada (bukti konkret instalasi website)
  #   c) Nginx config jualan-web aktif (bukti website sudah di-serve)
  local website_active=0

  local website_enabled
  website_enabled="$(read_env_var WEBSITE_ENABLED || true)"
  if [[ "${website_enabled}" == "true" ]]; then
    website_active=1
  fi

  if [[ "${website_active}" -eq 0 && -f "${web_dir}/.env" && -f "${web_dir}/artisan" ]]; then
    website_active=1
    log_step "website: terdeteksi aktif dari web/.env (WEBSITE_ENABLED belum diset)"
  fi

  local website_domain
  website_domain="$(read_env_var WEBSITE_DOMAIN || true)"
  if [[ "${website_active}" -eq 0 ]]; then
    if [[ -n "${website_domain}" && -f "/etc/nginx/sites-enabled/jualan-web-${website_domain}" ]]; then
      website_active=1
      log_step "website: terdeteksi aktif dari Nginx config jualan-web-${website_domain}"
    elif [[ -f "/etc/nginx/sites-enabled/jualan-web" ]]; then
      website_active=1
      log_step "website: terdeteksi aktif dari legacy Nginx config jualan-web"
    fi
  fi

  if [[ "${website_active}" -eq 0 ]]; then
    log_step "website: dilewati (tidak aktif)"
    return
  fi

  log_step "update website Laravel..."
  cd "${web_dir}"

  export COMPOSER_ALLOW_SUPERUSER=1
  composer install --no-dev --optimize-autoloader --no-interaction 2>&1 || true

  php artisan config:clear 2>/dev/null || true
  php artisan cache:clear 2>/dev/null || true
  php artisan view:clear 2>/dev/null || true
  php artisan route:clear 2>/dev/null || true
  php artisan migrate --force || echo "WARNING: Laravel migration failed."

  log_step "memperbarui izin file (permissions)..."
  local run_user
  run_user="$(stat -c '%U' "${PROJECT_DIR}")"
  sudo chown -R "${run_user}:www-data" "${web_dir}" 2>/dev/null || true
  sudo chmod -R g+rX "${web_dir}" 2>/dev/null || true
  sudo chmod -R 775 "${web_dir}/storage" "${web_dir}/bootstrap/cache" 2>/dev/null || true
  sudo chmod o+x /root 2>/dev/null || true
  sudo chmod o+x "${PROJECT_DIR}" 2>/dev/null || true
  sudo chmod -R o+rX "${web_dir}/public" 2>/dev/null || true

  # Patch Nginx: tambahkan client_max_body_size jika belum ada
  local website_domain
  website_domain="$(read_env_var WEBSITE_DOMAIN || true)"
  local nginx_conf="/etc/nginx/sites-available/jualan-web-${website_domain}"
  if [[ -z "${website_domain}" || ! -f "${nginx_conf}" ]]; then
    nginx_conf="/etc/nginx/sites-available/jualan-web"
  fi

  if [[ -f "${nginx_conf}" ]] && ! grep -q 'client_max_body_size' "${nginx_conf}"; then
    log_step "patch Nginx: menambahkan client_max_body_size 100M..."
    sudo sed -i '/charset utf-8;/a\    client_max_body_size 100M;' "${nginx_conf}" 2>/dev/null || true
  fi

  # Patch PHP: pastikan upload_max_filesize dan post_max_size cukup besar
  local php_ver=""
  if command -v php >/dev/null 2>&1; then
    php_ver=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
  fi

  if [[ -n "${php_ver}" ]]; then
    local fpm_ini="/etc/php/${php_ver}/fpm/php.ini"
    if [[ -f "${fpm_ini}" ]]; then
      local current_upload
      current_upload="$(grep -E '^upload_max_filesize\s*=' "${fpm_ini}" | head -1 | sed 's/.*=\s*//' | tr -d '[:space:]')"
      # Patch if still at 2M default or not set
      if [[ -z "${current_upload}" || "${current_upload}" == "2M" ]]; then
        log_step "patch PHP: upload_max_filesize dan post_max_size -> 100M..."
        sudo sed -i 's/^upload_max_filesize\s*=.*/upload_max_filesize = 100M/' "${fpm_ini}" 2>/dev/null || true
        sudo sed -i 's/^post_max_size\s*=.*/post_max_size = 100M/' "${fpm_ini}" 2>/dev/null || true
      fi
    fi
  fi

  log_step "restart PHP-FPM dan Nginx..."

  if [[ -n "${php_ver}" ]]; then
    sudo systemctl restart "php${php_ver}-fpm" 2>/dev/null || true
  fi
  sudo systemctl reload nginx 2>/dev/null || true

  # Otomatis pasang/perbarui VPS Ops Bridge sudoers agar fitur update & kontrol VPS di web aktif 1-klik
  if [[ -f "${PROJECT_DIR}/ops/install_web_sudoers.sh" && "$(id -u)" -eq 0 ]]; then
    log_step "memasang/memperbarui VPS Ops Bridge sudoers..."
    bash "${PROJECT_DIR}/ops/install_web_sudoers.sh" || true
  fi
}

restart_services() {
  sudo systemctl restart jualan-bot.service jualan-api.service
}

apply_update() {
  cd "${PROJECT_DIR}"
  log_step "fetch origin"
  git fetch origin

  local branch ts before_commit after_commit
  branch="$(default_branch)"
  ts="$(date +%Y%m%d_%H%M%S)"
  before_commit="$(git rev-parse HEAD)"

  if [[ -z "${branch}" ]]; then
    echo "ERROR: branch update tidak ditemukan. Set UPDATE_BRANCH di .env atau pastikan remote origin valid." >&2
    exit 1
  fi

  log_step "target branch: ${branch}"

  log_step "membuat backup sebelum update"
  backup_before_update
  log_step "menyimpan file runtime lokal (.env, user_role.txt)"
  preserve_runtime_files "${ts}"
  log_step "cek perubahan lokal untuk auto-stash"
  stash_local_changes_for_update "${ts}"

  log_step "checkout branch ${branch}"
  if ! git checkout "${branch}"; then
    log_step "branch lokal '${branch}' belum ada, membuat tracking dari origin/${branch}"
    git checkout -B "${branch}" "origin/${branch}"
  fi
  log_step "pull fast-forward dari origin/${branch}"
  git pull --ff-only origin "${branch}"
  after_commit="$(git rev-parse HEAD)"

  log_step "restore file runtime lokal"
  restore_runtime_files
  log_step "install requirements (jika venv tersedia)"
  install_requirements
  log_step "update website (jika aktif)"
  update_website
  log_step "restart service bot dan api"
  restart_services

  if [[ "${before_commit}" == "${after_commit}" ]]; then
    echo "Update selesai pada branch ${branch} (tidak ada commit baru)."
  else
    echo "Update selesai pada branch ${branch} (${before_commit:0:7} -> ${after_commit:0:7})."
  fi
  if [[ "${STASH_CREATED}" -eq 1 ]]; then
    echo "Catatan: perubahan lokal lama disimpan di stash agar tidak menimpa update terbaru."
    if [[ -n "${STASH_REF}" ]]; then
      echo "Lihat detail stash : git stash show -p ${STASH_REF}"
    else
      echo "Lihat daftar stash : git stash list"
    fi
  fi
}

rollback_update() {
  cd "${PROJECT_DIR}"
  if [[ ! -f "${LAST_COMMIT_FILE}" ]]; then
    echo "Tidak ada data commit rollback." >&2
    exit 1
  fi

  local prev
  prev="$(cat "${LAST_COMMIT_FILE}")"
  if [[ -z "${prev}" ]]; then
    echo "Commit rollback kosong." >&2
    exit 1
  fi

  git reset --hard "${prev}"
  install_requirements
  update_website
  restart_services

  echo "Rollback selesai ke commit ${prev}."
}

cmd="${1:-check}"
case "${cmd}" in
  check) check_update ;;
  update) apply_update ;;
  rollback) rollback_update ;;
  *)
    echo "Usage: $0 {check|update|rollback}" >&2
    exit 1
    ;;
esac
