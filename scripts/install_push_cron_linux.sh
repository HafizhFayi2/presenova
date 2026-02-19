#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   bash scripts/install_push_cron_linux.sh
#   bash scripts/install_push_cron_linux.sh /var/www/presenova
#
# Optional env overrides:
#   PHP_BIN=/usr/bin/php
#   CRON_SCHEDULE="* * * * *"

APP_DIR_INPUT="${1:-}"
if [[ -n "${APP_DIR_INPUT}" ]]; then
  APP_DIR="$(cd "${APP_DIR_INPUT}" && pwd)"
else
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
fi

if [[ ! -f "${APP_DIR}/artisan" ]]; then
  echo "ERROR: artisan tidak ditemukan di ${APP_DIR}"
  exit 1
fi

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [[ -z "${PHP_BIN}" ]]; then
  echo "ERROR: binary php tidak ditemukan. Set env PHP_BIN dulu."
  exit 1
fi

FLOCK_BIN="$(command -v flock || true)"
CRON_SCHEDULE="${CRON_SCHEDULE:-* * * * *}"
LOG_FILE="${APP_DIR}/storage/logs/push-cron.log"
LOCK_FILE="/tmp/presenova_push_notifications.lock"
CRON_TAG="PRESENOVA_PUSH_CRON"

mkdir -p "${APP_DIR}/storage/logs"
touch "${LOG_FILE}"

if [[ -n "${FLOCK_BIN}" ]]; then
  CRON_COMMAND="cd \"${APP_DIR}\" && \"${FLOCK_BIN}\" -n \"${LOCK_FILE}\" \"${PHP_BIN}\" \"${APP_DIR}/public/cron/send_notifications.php\" >> \"${LOG_FILE}\" 2>&1 # ${CRON_TAG}"
else
  CRON_COMMAND="cd \"${APP_DIR}\" && \"${PHP_BIN}\" \"${APP_DIR}/public/cron/send_notifications.php\" >> \"${LOG_FILE}\" 2>&1 # ${CRON_TAG}"
fi

CURRENT_CRONTAB="$(crontab -l 2>/dev/null || true)"
CLEANED_CRONTAB="$(printf '%s\n' "${CURRENT_CRONTAB}" | sed "/${CRON_TAG}/d" | sed '/^[[:space:]]*$/N;/^\n$/D')"

{
  printf '%s\n' "${CLEANED_CRONTAB}"
  printf '%s %s\n' "${CRON_SCHEDULE}" "${CRON_COMMAND}"
} | sed '/^[[:space:]]*$/d' | crontab -

echo "OK: Cron push notification terpasang."
echo "APP_DIR    : ${APP_DIR}"
echo "PHP_BIN    : ${PHP_BIN}"
echo "SCHEDULE   : ${CRON_SCHEDULE}"
echo "LOG_FILE   : ${LOG_FILE}"
echo "CRON_TAG   : ${CRON_TAG}"
echo
echo "Cek hasil:"
echo "  crontab -l | grep ${CRON_TAG}"
echo "  tail -f \"${LOG_FILE}\""
