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

  STASH_REF="$(git stash list | awk -v needle="${AUTOSTASH_NAME}" 'index($0, needle) { print $1; exit }')"
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
  grep -E "^${key}=" "${ENV_FILE}" | head -n1 | cut -d'=' -f2-
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
  local ts archive
  ts="$(date +%Y%m%d_%H%M%S)"
  archive="${BACKUP_DIR}/update_${ts}.tar.gz"

  tar \
    --exclude='.venv' \
    --exclude='ops/backups' \
    -czf "${archive}" \
    .

  git rev-parse HEAD > "${LAST_COMMIT_FILE}"
  echo "Backup created: ${archive}"
  echo "Previous commit: $(cat "${LAST_COMMIT_FILE}")"
}

install_requirements() {
  cd "${PROJECT_DIR}"
  if [[ -x "${PROJECT_DIR}/.venv/bin/pip" ]]; then
    "${PROJECT_DIR}/.venv/bin/pip" install -r requirements.txt
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
