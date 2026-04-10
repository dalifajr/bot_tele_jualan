#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${PROJECT_DIR}/ops/backups"
LAST_COMMIT_FILE="${BACKUP_DIR}/.last_commit"
ENV_FILE="${PROJECT_DIR}/.env"

mkdir -p "${BACKUP_DIR}"

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

  local branch
  branch="$(default_branch)"

  backup_before_update

  git checkout "${branch}"
  git pull --ff-only origin "${branch}"
  install_requirements
  restart_services

  echo "Update selesai pada branch ${branch}."
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
