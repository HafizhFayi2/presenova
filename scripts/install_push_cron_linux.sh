#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   bash scripts/install_push_cron_linux.sh
#   bash scripts/install_push_cron_linux.sh /var/www/presenova
#
# Optional env overrides:
#   PHP_BIN=/usr/bin/php
#   CRON_SCHEDULE="* * * * *"

log() {
  printf '[install-push-cron] %s\n' "$*"
}

fail() {
  printf '[install-push-cron] ERROR: %s\n' "$*" >&2
  exit 1
}

can_escalate_root() {
  [[ "$(id -u)" -eq 0 ]] || command -v sudo >/dev/null 2>&1
}

run_as_root() {
  if [[ "$(id -u)" -eq 0 ]]; then
    "$@"
  else
    sudo "$@"
  fi
}

APT_UPDATED=0
apt_install_if_missing() {
  local cmd="$1"
  shift
  local packages=("$@")

  if command -v "${cmd}" >/dev/null 2>&1; then
    return 0
  fi

  if ! command -v apt-get >/dev/null 2>&1; then
    fail "Command '${cmd}' tidak ditemukan dan apt-get tidak tersedia."
  fi

  if ! can_escalate_root; then
    fail "Command '${cmd}' tidak ditemukan. Jalankan sebagai root/sudo untuk install paket: ${packages[*]}"
  fi

  export DEBIAN_FRONTEND=noninteractive
  if [[ "${APT_UPDATED}" -eq 0 ]]; then
    log "Menjalankan apt-get update..."
    run_as_root apt-get update -y
    APT_UPDATED=1
  fi

  log "Install paket untuk '${cmd}': ${packages[*]}"
  run_as_root apt-get install -y "${packages[@]}"

  command -v "${cmd}" >/dev/null 2>&1 || fail "Gagal menemukan command '${cmd}' setelah install paket."
}

APP_DIR_INPUT="${1:-}"
if [[ -n "${APP_DIR_INPUT}" ]]; then
  APP_DIR="$(cd "${APP_DIR_INPUT}" && pwd)"
else
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
fi

if [[ ! -f "${APP_DIR}/artisan" ]]; then
  fail "artisan tidak ditemukan di ${APP_DIR}"
fi

if [[ ! -f "${APP_DIR}/public/cron/send_notifications.php" ]]; then
  fail "File cron tidak ditemukan: ${APP_DIR}/public/cron/send_notifications.php"
fi

apt_install_if_missing crontab cron
apt_install_if_missing php php-cli
apt_install_if_missing nodejs nodejs

if can_escalate_root; then
  run_as_root systemctl enable --now cron >/dev/null 2>&1 || true
fi

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [[ -z "${PHP_BIN}" ]]; then
  fail "binary php tidak ditemukan. Set env PHP_BIN dulu."
fi

NODE_BIN="${PUSH_NODE_BIN:-$(command -v node || command -v nodejs || true)}"
if [[ -z "${NODE_BIN}" ]]; then
  fail "binary node tidak ditemukan."
fi

ENV_FILE="${APP_DIR}/.env"
if [[ -f "${ENV_FILE}" ]]; then
  if grep -qE '^PUSH_NODE_BIN=' "${ENV_FILE}"; then
    sed -i "s|^PUSH_NODE_BIN=.*|PUSH_NODE_BIN=${NODE_BIN}|" "${ENV_FILE}"
  else
    printf '\nPUSH_NODE_BIN=%s\n' "${NODE_BIN}" >> "${ENV_FILE}"
  fi
fi

WEBPUSH_DIR="${APP_DIR}/public/scripts/webpush"
if [[ -f "${WEBPUSH_DIR}/package.json" && ! -f "${WEBPUSH_DIR}/node_modules/web-push/package.json" ]]; then
  apt_install_if_missing npm npm
  log "Install dependency webpush (npm --omit=dev)..."
  (cd "${WEBPUSH_DIR}" && npm install --omit=dev)
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
