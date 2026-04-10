#!/usr/bin/env bash
set -euo pipefail

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
PROJECT_DIR="$(cd "$(dirname "${SCRIPT_PATH}")/.." && pwd)"
ENV_FILE="${PROJECT_DIR}/.env"
BACKUP_DIR="${PROJECT_DIR}/ops/backups"

usage() {
  cat <<'EOF'
Usage: backup_manager.sh <command> [args]

Commands:
  backup                 Create a new database backup snapshot
  list                   List available backups (newest first)
  restore [file]         Restore database from backup file (default: latest)
  prune [keep_count]     Keep only latest N backups (default from BACKUP_KEEP_COUNT or 14)
EOF
}

load_env() {
  if [[ -f "${ENV_FILE}" ]]; then
    set -a
    # shellcheck disable=SC1090
    source "${ENV_FILE}"
    set +a
  fi
}

resolve_db_path() {
  local url="${DATABASE_URL:-sqlite:///./data/bot_jualan.db}"
  if [[ "${url}" != sqlite:///* ]]; then
    echo "Database URL non-sqlite belum didukung oleh backup_manager: ${url}" >&2
    return 1
  fi

  local raw_path="${url#sqlite:///}"
  if [[ "${raw_path}" == ./* ]]; then
    echo "${PROJECT_DIR}/${raw_path#./}"
  else
    echo "${raw_path}"
  fi
}

backup_db() {
  load_env
  mkdir -p "${BACKUP_DIR}"

  local db_path
  db_path="$(resolve_db_path)"
  mkdir -p "$(dirname "${db_path}")"

  if [[ ! -f "${db_path}" ]]; then
    echo "Database belum ada, membuat file kosong: ${db_path}"
    : > "${db_path}"
  fi

  local ts
  ts="$(date +%Y%m%d_%H%M%S)"
  local out_file="${BACKUP_DIR}/botdb_${ts}.sqlite3"

  if command -v sqlite3 >/dev/null 2>&1; then
    sqlite3 "${db_path}" ".backup '${out_file}'"
  else
    cp "${db_path}" "${out_file}"
  fi

  echo "Backup berhasil: ${out_file}"
}

list_backups() {
  mkdir -p "${BACKUP_DIR}"
  ls -1t "${BACKUP_DIR}"/*.sqlite3 2>/dev/null || true
}

restore_db() {
  load_env
  local target="${1:-}"
  mkdir -p "${BACKUP_DIR}"

  if [[ -z "${target}" ]]; then
    target="$(ls -1t "${BACKUP_DIR}"/*.sqlite3 2>/dev/null | head -n1 || true)"
  elif [[ "${target}" != /* ]]; then
    target="${BACKUP_DIR}/${target}"
  fi

  if [[ -z "${target}" || ! -f "${target}" ]]; then
    echo "File backup tidak ditemukan." >&2
    return 1
  fi

  local db_path
  db_path="$(resolve_db_path)"
  mkdir -p "$(dirname "${db_path}")"

  if [[ -f "${db_path}" ]]; then
    local pre_restore_file="${BACKUP_DIR}/pre_restore_$(date +%Y%m%d_%H%M%S).sqlite3"
    cp "${db_path}" "${pre_restore_file}"
    echo "Snapshot sebelum restore: ${pre_restore_file}"
  fi

  cp "${target}" "${db_path}"
  echo "Restore berhasil dari: ${target}"
}

prune_backups() {
  local keep_count="${1:-${BACKUP_KEEP_COUNT:-14}}"
  if ! [[ "${keep_count}" =~ ^[0-9]+$ ]]; then
    echo "keep_count harus angka." >&2
    return 1
  fi

  mkdir -p "${BACKUP_DIR}"
  mapfile -t files < <(ls -1t "${BACKUP_DIR}"/*.sqlite3 2>/dev/null || true)

  if (( ${#files[@]} <= keep_count )); then
    echo "Tidak ada backup yang perlu dihapus."
    return 0
  fi

  local to_delete=("${files[@]:keep_count}")
  local file
  for file in "${to_delete[@]}"; do
    rm -f "${file}"
    echo "Pruned: ${file}"
  done
}

cmd="${1:-}"
case "${cmd}" in
  backup)
    backup_db
    ;;
  list)
    list_backups
    ;;
  restore)
    restore_db "${2:-}"
    ;;
  prune)
    prune_backups "${2:-}"
    ;;
  *)
    usage
    exit 1
    ;;
esac
