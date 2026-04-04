#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Setup Let's Encrypt SSL (webroot mode) untuk VPS Linux/aaPanel.

Usage:
  sudo bash scripts/setup_ssl_webroot_linux.sh \
    --domains presenova.my.id,ebook.presenova.my.id \
    --email adm@presenova.my.id \
    --webroot /www/wwwroot/presenova/public [options]

Options:
  --domains <d1,d2,...>     Daftar domain dipisah koma (wajib).
  --email <email>           Email Let's Encrypt (wajib).
  --webroot <path>          Webroot yang melayani challenge ACME (wajib).
  --primary-domain <domain> Domain utama output sertifikat (default: domain pertama).
  --reload-cmd <command>    Command reload web server/panel setelah issue cert.
  --skip-dry-run            Lewati `certbot renew --dry-run`.
  --staging                 Gunakan Let's Encrypt staging (untuk testing).
  --help                    Tampilkan bantuan.

Contoh aaPanel:
  sudo bash scripts/setup_ssl_webroot_linux.sh \
    --domains presenova.my.id,ebook.presenova.my.id \
    --email adm@presenova.my.id \
    --webroot /www/wwwroot/presenova/public \
    --reload-cmd "bt reload"
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

APT_UPDATED=0
apt_install_packages() {
  local packages=("$@")
  [[ ${#packages[@]} -gt 0 ]] || return 0

  require_cmd apt-get
  export DEBIAN_FRONTEND=noninteractive

  if [[ "${APT_UPDATED}" -eq 0 ]]; then
    log "Menjalankan apt-get update..."
    apt-get update -y
    APT_UPDATED=1
  fi

  log "Install paket: ${packages[*]}"
  apt-get install -y "${packages[@]}"
}

DOMAINS_RAW=""
EMAIL=""
WEBROOT=""
PRIMARY_DOMAIN=""
RELOAD_CMD=""
SKIP_DRY_RUN=0
STAGING=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domains)
      DOMAINS_RAW="${2:-}"
      shift 2
      ;;
    --email)
      EMAIL="${2:-}"
      shift 2
      ;;
    --webroot)
      WEBROOT="${2:-}"
      shift 2
      ;;
    --primary-domain)
      PRIMARY_DOMAIN="${2:-}"
      shift 2
      ;;
    --reload-cmd)
      RELOAD_CMD="${2:-}"
      shift 2
      ;;
    --skip-dry-run)
      SKIP_DRY_RUN=1
      shift
      ;;
    --staging)
      STAGING=1
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

[[ -n "${DOMAINS_RAW}" ]] || fail "--domains wajib diisi."
[[ -n "${EMAIL}" ]] || fail "--email wajib diisi."
[[ -n "${WEBROOT}" ]] || fail "--webroot wajib diisi."

if [[ "${EUID}" -ne 0 ]]; then
  fail "Jalankan script dengan sudo/root."
fi

mkdir -p "${WEBROOT}"
WEBROOT="$(cd "${WEBROOT}" && pwd)"
mkdir -p "${WEBROOT}/.well-known/acme-challenge"

log "Memastikan dependency Debian tersedia..."
apt_install_packages ca-certificates curl certbot

require_cmd certbot
require_cmd systemctl

DOMAINS=()
IFS=',' read -r -a raw_items <<< "${DOMAINS_RAW}"
for item in "${raw_items[@]}"; do
  cleaned="$(printf '%s' "${item}" | xargs)"
  [[ -n "${cleaned}" ]] && DOMAINS+=("${cleaned}")
done
[[ ${#DOMAINS[@]} -gt 0 ]] || fail "Tidak ada domain valid pada --domains."

if [[ -z "${PRIMARY_DOMAIN}" ]]; then
  PRIMARY_DOMAIN="${DOMAINS[0]}"
fi

domain_found=0
for d in "${DOMAINS[@]}"; do
  if [[ "${d}" == "${PRIMARY_DOMAIN}" ]]; then
    domain_found=1
    break
  fi
done
[[ "${domain_found}" -eq 1 ]] || fail "--primary-domain harus ada di daftar --domains."

log "Request sertifikat Let's Encrypt (webroot)..."
CERTBOT_ARGS=(
  certonly
  --webroot
  -w "${WEBROOT}"
  --non-interactive
  --agree-tos
  --email "${EMAIL}"
  --keep-until-expiring
  --rsa-key-size 4096
)
if [[ "${STAGING}" -eq 1 ]]; then
  CERTBOT_ARGS+=(--staging)
fi
for d in "${DOMAINS[@]}"; do
  CERTBOT_ARGS+=(-d "${d}")
done
certbot "${CERTBOT_ARGS[@]}"

if systemctl list-unit-files | grep -q '^certbot\.timer'; then
  systemctl enable --now certbot.timer >/dev/null || true
fi

if [[ -n "${RELOAD_CMD}" ]]; then
  log "Menjalankan reload command: ${RELOAD_CMD}"
  bash -lc "${RELOAD_CMD}"
fi

if [[ "${SKIP_DRY_RUN}" -eq 0 ]]; then
  log "Validasi auto-renew (dry-run)..."
  certbot renew --dry-run || true
fi

CERT_DIR="/etc/letsencrypt/live/${PRIMARY_DOMAIN}"
FULLCHAIN="${CERT_DIR}/fullchain.pem"
PRIVKEY="${CERT_DIR}/privkey.pem"
[[ -f "${FULLCHAIN}" ]] || fail "File sertifikat tidak ditemukan: ${FULLCHAIN}"
[[ -f "${PRIVKEY}" ]] || fail "Private key tidak ditemukan: ${PRIVKEY}"

log "Selesai."
echo
echo "Domain SSL:"
for d in "${DOMAINS[@]}"; do
  echo "  - ${d}"
done
echo
echo "Path sertifikat:"
echo "  fullchain: ${FULLCHAIN}"
echo "  privkey  : ${PRIVKEY}"
echo
echo "Verifikasi cepat:"
echo "  openssl s_client -connect ${PRIMARY_DOMAIN}:443 -servername ${PRIMARY_DOMAIN} </dev/null 2>/dev/null | openssl x509 -noout -issuer -subject -dates"
