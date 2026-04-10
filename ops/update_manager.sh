#!/usr/bin/env bash
set -euo pipefail

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
  if [[ -n "${configured}" ]]; then
    echo "${configured}"
    return
  fi
  git remote show origin | sed -n '/HEAD branch/s/.*: //p'
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
  git fetch origin

  local branch ts
  branch="$(default_branch)"
  ts="$(date +%Y%m%d_%H%M%S)"

  backup_before_update
  preserve_runtime_files "${ts}"
  stash_local_changes_for_update "${ts}"

  git checkout "${branch}"
  git pull --ff-only origin "${branch}"
  restore_runtime_files
  install_requirements
  restart_services

  echo "Update selesai pada branch ${branch}."
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
