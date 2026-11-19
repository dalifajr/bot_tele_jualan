#!/usr/bin/env bash
# ==============================================================================
# Least-Privilege VPS Operations Bridge for Admin Web Panel
# Only accepts strictly whitelisted subcommands — zero arbitrary shell execution.
# ==============================================================================
set -euo pipefail

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
PROJECT_DIR="$(cd "$(dirname "${SCRIPT_PATH}")/.." && pwd)"
WEB_DIR="${PROJECT_DIR}/web"
TASK_LOG="${WEB_DIR}/storage/logs/vps_ops_task.log"
ENV_FILE="${PROJECT_DIR}/.env"

detect_php_fpm_unit() {
  local cand
  for cand in php8.4-fpm php8.3-fpm php8.2-fpm php8.1-fpm php-fpm; do
    if systemctl list-unit-files "${cand}.service" --no-legend 2>/dev/null | grep -q "${cand}"; then
      echo "${cand}.service"
      return
    fi
  done
  echo "php8.4-fpm.service"
}

detect_mysql_unit() {
  local cand
  for cand in mysql mariadb mysqld; do
    if systemctl list-unit-files "${cand}.service" --no-legend 2>/dev/null | grep -q "${cand}"; then
      echo "${cand}.service"
      return
    fi
  done
  echo "mysql.service"
}

resolve_unit_name() {
  local key="$1"
  case "${key}" in
    jualan-bot) echo "jualan-bot.service" ;;
    jualan-api) echo "jualan-api.service" ;;
    nginx) echo "nginx.service" ;;
    php-fpm) detect_php_fpm_unit ;;
    mysql) detect_mysql_unit ;;
    remote-control) echo "remote_control.service" ;;
    jualan-backup-timer) echo "jualan-backup.timer" ;;
    *)
      echo "INVALID_UNIT" >&2
      exit 2
      ;;
  esac
}

cmd_ping() {
  echo "BRIDGE_OK"
}

cmd_service_status() {
  local keys=("jualan-bot" "jualan-api" "nginx" "php-fpm" "mysql" "remote-control" "jualan-backup-timer")
  local key unit state substate enabled
  for key in "${keys[@]}"; do
    unit="$(resolve_unit_name "${key}")"
    state="$(systemctl is-active "${unit}" 2>/dev/null || true)"
    [[ -z "${state}" ]] && state="unknown"
    substate="$(systemctl show -p SubState --value "${unit}" 2>/dev/null || true)"
    enabled="$(systemctl is-enabled "${unit}" 2>/dev/null || true)"
    [[ -z "${enabled}" ]] && enabled="not-found"
    printf '%s|%s|%s|%s|%s\n' "${key}" "${unit}" "${state}" "${substate}" "${enabled}"
  done
}

cmd_service_action() {
  local key="${1:-}"
  local action="${2:-}"
  if [[ -z "${key}" || -z "${action}" ]]; then
    echo "Usage: service <unit_key> <start|stop|restart>" >&2
    exit 2
  fi

  case "${action}" in
    start|stop|restart) ;;
    *)
      echo "Aksi service tidak diizinkan: ${action}" >&2
      exit 2
      ;;
  esac

  local unit
  unit="$(resolve_unit_name "${key}")"

  # Avoid killing the active HTTP response if restarting PHP-FPM or Nginx
  if [[ ("${key}" == "php-fpm" || "${key}" == "nginx") && ("${action}" == "restart" || "${action}" == "stop") ]]; then
    nohup bash -c "sleep 1 && systemctl ${action} '${unit}'" >/dev/null 2>&1 &
    echo "SCHEDULED:${action}:${unit}"
    return 0
  fi

  systemctl "${action}" "${unit}"
  local after_state
  after_state="$(systemctl is-active "${unit}" 2>/dev/null || true)"
  echo "DONE:${action}:${unit}:${after_state}"
}

cmd_website_restart() {
  local fpm_unit
  fpm_unit="$(detect_php_fpm_unit)"
  mkdir -p "$(dirname "${TASK_LOG}")"
  {
    echo "[TASK_STATUS:RUNNING]"
    echo "[TASK_NAME:website-restart]"
    echo "[TASK_STARTED:$(date '+%Y-%m-%d %H:%M:%S')]"
    echo "Menjadwalkan restart ${fpm_unit} dan reload nginx..."
  } > "${TASK_LOG}"
  chown www-data:www-data "${TASK_LOG}" 2>/dev/null || chmod 664 "${TASK_LOG}" 2>/dev/null || true

  nohup bash -c "
    sleep 1
    {
      systemctl restart '${fpm_unit}' 2>&1
      nginx -t 2>&1 && systemctl reload nginx 2>&1
      echo '[TASK_FINISHED:'\$(date '+%Y-%m-%d %H:%M:%S')']'
      echo '[TASK_STATUS:DONE]'
    } >> '${TASK_LOG}' 2>&1 || {
      echo '[TASK_FINISHED:'\$(date '+%Y-%m-%d %H:%M:%S')']' >> '${TASK_LOG}'
      echo '[TASK_STATUS:FAILED]' >> '${TASK_LOG}'
    }
  " >/dev/null 2>&1 &

  echo "STARTED:website-restart"
}

cmd_logs() {
  local source="${1:-bot}"
  local lines="${2:-100}"
  if ! [[ "${lines}" =~ ^[0-9]+$ ]]; then
    lines=100
  fi
  if (( lines < 10 )); then lines=10; fi
  if (( lines > 500 )); then lines=500; fi

  case "${source}" in
    bot)
      journalctl -u jualan-bot.service -n "${lines}" --no-pager 2>&1 || echo "(Tidak ada log jualan-bot.service)"
      ;;
    api)
      journalctl -u jualan-api.service -n "${lines}" --no-pager 2>&1 || echo "(Tidak ada log jualan-api.service)"
      ;;
    remote-control)
      journalctl -u remote_control.service -n "${lines}" --no-pager 2>&1 || echo "(Tidak ada log remote_control.service)"
      ;;
    nginx)
      local domain=""
      if [[ -f "${ENV_FILE}" ]]; then
        domain="$(grep -E '^WEBSITE_DOMAIN=' "${ENV_FILE}" | head -n1 | cut -d'=' -f2- | tr -d ' "\r' || true)"
        domain="${domain#http://}"
        domain="${domain#https://}"
        domain="${domain%%/*}"
      fi
      local log_file="/var/log/nginx/jualan-web-${domain}-error.log"
      if [[ -z "${domain}" || ! -f "${log_file}" ]]; then
        log_file="/var/log/nginx/error.log"
      fi
      if [[ -f "${log_file}" ]]; then
        tail -n "${lines}" "${log_file}" 2>&1
      else
        echo "(File Nginx error log tidak ditemukan)"
      fi
      ;;
    *)
      echo "Sumber log tidak valid: ${source}" >&2
      exit 2
      ;;
  esac
}

cmd_check_update() {
  cd "${PROJECT_DIR}"
  bash "${PROJECT_DIR}/ops/update_manager.sh" check 2>&1
  local branch
  branch="$(git symbolic-ref --quiet --short HEAD 2>/dev/null || echo main)"
  echo "---COMMITS_BEHIND---"
  git log --oneline -n 15 "HEAD..origin/${branch}" 2>/dev/null || true
}

run_detached_script() {
  local task_name="$1"
  shift
  mkdir -p "$(dirname "${TASK_LOG}")"
  {
    echo "[TASK_STATUS:RUNNING]"
    echo "[TASK_NAME:${task_name}]"
    echo "[TASK_STARTED:$(date '+%Y-%m-%d %H:%M:%S')]"
    echo "Memulai tugas ${task_name}..."
  } > "${TASK_LOG}"
  chown www-data:www-data "${TASK_LOG}" 2>/dev/null || chmod 664 "${TASK_LOG}" 2>/dev/null || true

  nohup bash -c '
    sleep 1
    task_log="$1"
    shift
    if "$@" >> "${task_log}" 2>&1; then
      echo "[TASK_FINISHED:$(date "+%Y-%m-%d %H:%M:%S")]" >> "${task_log}"
      echo "[TASK_STATUS:DONE]" >> "${task_log}"
    else
      echo "[TASK_FINISHED:$(date "+%Y-%m-%d %H:%M:%S")]" >> "${task_log}"
      echo "[TASK_STATUS:FAILED]" >> "${task_log}"
    fi
    chmod 664 "${task_log}" 2>/dev/null || true
  ' _ "${TASK_LOG}" "$@" >/dev/null 2>&1 &

  echo "STARTED:${task_name}"
}

cmd_prune_backups() {
  local keep="${1:-10}"
  if ! [[ "${keep}" =~ ^[0-9]+$ ]]; then
    echo "Nilai keep tidak valid." >&2
    exit 2
  fi
  if (( keep < 1 )); then keep=1; fi
  if (( keep > 100 )); then keep=100; fi
  bash "${PROJECT_DIR}/ops/backup_manager.sh" prune "${keep}" 2>&1
}

action="${1:-}"
case "${action}" in
  ping) cmd_ping ;;
  service-status) cmd_service_status ;;
  service) cmd_service_action "${2:-}" "${3:-}" ;;
  website-restart) cmd_website_restart ;;
  logs) cmd_logs "${2:-bot}" "${3:-100}" ;;
  check-update) cmd_check_update ;;
  update) run_detached_script "update" bash "${PROJECT_DIR}/ops/update_manager.sh" update ;;
  rollback) run_detached_script "rollback" bash "${PROJECT_DIR}/ops/update_manager.sh" rollback ;;
  repair) run_detached_script "repair" bash "${PROJECT_DIR}/ops/repair_system_and_bot.sh" ;;
  prune-backups) cmd_prune_backups "${2:-10}" ;;
  *)
    echo "Perintah tidak dikenali." >&2
    exit 2
    ;;
esac
