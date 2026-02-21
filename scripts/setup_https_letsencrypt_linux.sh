#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  sudo bash scripts/setup_https_letsencrypt_linux.sh --domain presenova.my.id --email admin@presenova.my.id [options]

Options:
  --domain <domain>         Domain utama (wajib), contoh: presenova.my.id
  --email <email>           Email Let's Encrypt (wajib)
  --aliases <a,b,c>         Domain alias dipisah koma, contoh: www.presenova.my.id
  --app-dir <path>          Root project (default: parent folder script ini)
  --site-name <name>        Nama file vhost Apache (default: presenova-<domain>)
  --skip-env-update         Jangan update file .env
  --help                    Tampilkan bantuan

Catatan:
  - Script ini ditujukan untuk Debian/Ubuntu + Apache2.
  - Domain wajib sudah mengarah ke IP VPS sebelum script dijalankan.
USAGE
}

log() {
  printf '[%s] %s\n' "$(date '+%F %T')" "$*"
}

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

require_cmd() {
  local cmd="$1"
  command -v "${cmd}" >/dev/null 2>&1 || fail "Perintah '${cmd}' tidak ditemukan."
}

render_vhost_template() {
  local content
  content="$(cat "${TEMPLATE_FILE}")"
  content="${content//__DOMAIN__/${DOMAIN}}"
  content="${content//__APP_DIR__/${APP_DIR}}"
  content="${content//__SERVER_ALIAS_DIRECTIVE__/${SERVER_ALIAS_TEMPLATE_LINE}}"
  printf '%s\n' "${content}"
}

write_bootstrap_http_conf() {
  local rendered
  rendered="$(render_vhost_template)"
  printf '%s\n' "${rendered}" | awk '
    /^[[:space:]]*<IfModule[[:space:]]+mod_ssl\.c>/ { exit }
    { print }
  ' > "${SITE_CONF}"
}

write_full_https_conf() {
  render_vhost_template > "${SITE_CONF}"
}

DOMAIN=""
EMAIL=""
ALIASES_RAW=""
APP_DIR=""
SITE_NAME=""
SKIP_ENV_UPDATE=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain)
      DOMAIN="${2:-}"
      shift 2
      ;;
    --email)
      EMAIL="${2:-}"
      shift 2
      ;;
    --aliases)
      ALIASES_RAW="${2:-}"
      shift 2
      ;;
    --app-dir)
      APP_DIR="${2:-}"
      shift 2
      ;;
    --site-name)
      SITE_NAME="${2:-}"
      shift 2
      ;;
    --skip-env-update)
      SKIP_ENV_UPDATE=1
      shift
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      fail "Argumen tidak dikenal: $1"
      ;;
  esac
done

[[ -n "${DOMAIN}" ]] || fail "--domain wajib diisi. Gunakan --help untuk panduan."
[[ -n "${EMAIL}" ]] || fail "--email wajib diisi. Gunakan --help untuk panduan."

if [[ "${EUID}" -ne 0 ]]; then
  fail "Jalankan script dengan sudo/root."
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
APP_DIR="${APP_DIR:-${DEFAULT_APP_DIR}}"
APP_DIR="$(cd "${APP_DIR}" && pwd)"

[[ -f "${APP_DIR}/artisan" ]] || fail "File artisan tidak ditemukan di ${APP_DIR}."
[[ -d /etc/apache2 ]] || fail "Apache2 tidak terdeteksi (folder /etc/apache2 tidak ditemukan)."

SITE_NAME="${SITE_NAME:-presenova-${DOMAIN//./-}}"
SITE_CONF="/etc/apache2/sites-available/${SITE_NAME}.conf"
TEMPLATE_FILE="${APP_DIR}/scripts/apache-vhost-linux-https-letsencrypt.conf"
[[ -f "${TEMPLATE_FILE}" ]] || fail "Template vhost tidak ditemukan: ${TEMPLATE_FILE}"

ALIASES=()
if [[ -n "${ALIASES_RAW}" ]]; then
  IFS=',' read -r -a raw_items <<< "${ALIASES_RAW}"
  for item in "${raw_items[@]}"; do
    cleaned="$(printf '%s' "${item}" | xargs)"
    [[ -n "${cleaned}" ]] && ALIASES+=("${cleaned}")
  done
fi

SERVER_ALIAS_TEMPLATE_LINE="    # ServerAlias www.${DOMAIN}"
if [[ ${#ALIASES[@]} -gt 0 ]]; then
  SERVER_ALIAS_TEMPLATE_LINE="    ServerAlias ${ALIASES[*]}"
fi

DOMAINS=("${DOMAIN}")
if [[ ${#ALIASES[@]} -gt 0 ]]; then
  DOMAINS+=("${ALIASES[@]}")
fi

log "Memastikan dependency tersedia..."
if command -v apt-get >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y certbot python3-certbot-apache
else
  fail "apt-get tidak ditemukan. Install manual 'certbot' dan 'python3-certbot-apache' terlebih dahulu."
fi

require_cmd certbot
require_cmd a2enmod
require_cmd a2ensite
require_cmd a2query

APACHECTL_BIN="$(command -v apache2ctl || command -v apachectl || true)"
[[ -n "${APACHECTL_BIN}" ]] || fail "apache2ctl/apachectl tidak ditemukan."

log "Mengaktifkan module Apache yang dibutuhkan..."
a2enmod rewrite ssl headers >/dev/null

log "Mempersiapkan webroot ACME challenge..."
mkdir -p "${APP_DIR}/public/.well-known/acme-challenge"

log "Menulis konfigurasi bootstrap HTTP (untuk challenge awal)..."
write_bootstrap_http_conf

log "Mengaktifkan site Apache: ${SITE_NAME}.conf"
a2ensite "${SITE_NAME}.conf" >/dev/null

if [[ -f /etc/apache2/sites-enabled/000-default.conf ]]; then
  a2dissite 000-default >/dev/null || true
fi

log "Validasi konfigurasi Apache (bootstrap)..."
"${APACHECTL_BIN}" -t
systemctl reload apache2

log "Meminta sertifikat Let's Encrypt..."
CERTBOT_CMD=(certonly --webroot -w "${APP_DIR}/public" --non-interactive --agree-tos --email "${EMAIL}" --keep-until-expiring --rsa-key-size 4096)
for d in "${DOMAINS[@]}"; do
  CERTBOT_CMD+=(-d "${d}")
done
certbot "${CERTBOT_CMD[@]}"

log "Menulis konfigurasi final HTTPS..."
write_full_https_conf

log "Reload Apache dengan sertifikat aktif..."
"${APACHECTL_BIN}" -t
systemctl reload apache2

if systemctl list-unit-files | grep -q '^certbot\.timer'; then
  systemctl enable --now certbot.timer >/dev/null || true
fi

log "Validasi auto-renew (dry-run)..."
certbot renew --dry-run || true

if [[ "${SKIP_ENV_UPDATE}" -eq 0 ]]; then
  ENV_FILE="${APP_DIR}/.env"
  if [[ -f "${ENV_FILE}" ]]; then
    log "Update .env agar konsisten HTTPS..."

    upsert_env() {
      local key="$1"
      local value="$2"
      if grep -qE "^${key}=" "${ENV_FILE}"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "${ENV_FILE}"
      else
        printf '\n%s=%s\n' "${key}" "${value}" >> "${ENV_FILE}"
      fi
    }

    upsert_env "APP_URL" "https://${DOMAIN}"
    upsert_env "SITE_URL" "https://${DOMAIN}"
    upsert_env "SESSION_SECURE_COOKIE" "true"
    upsert_env "FORCE_HTTPS" "true"
  else
    log "Lewati update .env karena file tidak ditemukan."
  fi
fi

log "Selesai."
echo
echo "Verifikasi cepat:"
echo "  curl -I http://${DOMAIN}"
echo "  curl -I https://${DOMAIN}"
echo "  openssl s_client -connect ${DOMAIN}:443 -servername ${DOMAIN} </dev/null 2>/dev/null | openssl x509 -noout -issuer -subject -dates"
echo
echo "Jika APP_URL/.env baru diubah, jalankan:"
echo "  cd ${APP_DIR}"
echo "  php artisan optimize:clear"
echo "  php artisan config:cache"
