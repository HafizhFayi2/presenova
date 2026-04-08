#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Setup end-to-end Presenova di VPS Linux fresh (Debian/Ubuntu + Apache + MariaDB).

Usage:
  sudo bash scripts/setup_vps_fresh_presenova.sh [options]

Options:
  --domain <domain>            Domain utama app (default: presenova.my.id)
  --email <email>              Email Let's Encrypt (default: adm@presenova.my.id)
  --aliases <a,b,c>            Alias domain app utama (default: www.<domain>)
  --ebook-domain <domain>      Domain ebook terpisah (default: ebook.<domain>)
  --ebook-dir <path>           DocumentRoot ebook (default: <app-dir>/Ebook)
  --app-dir <path>             Root project (default: parent folder script)
  --db-name <name>             Nama database (default: presenova)
  --db-user <user>             User database (default: presenova)
  --db-pass <password>         Password database (default: auto-generate)
  --db-host <host>             DB host untuk .env (default: 127.0.0.1)
  --db-port <port>             DB port untuk .env (default: 3306)
  --sql-file <path>            File SQL bootstrap (default: <app-dir>/presenova.sql)
  --skip-sql-import            Lewati import SQL awal
  --skip-https                 Lewati setup HTTPS/Let's Encrypt
  --skip-cron                  Lewati setup cron push notification
  --with-deepface              Sekalian setup DeepFace venv
  --with-mail                  Sekalian setup mail server (Postfix/Dovecot)
  --mail-admin-email <email>   Admin mailbox untuk --with-mail (default: adm@<domain>)
  --mail-host <host>           Host mail server untuk --with-mail (default: mail.<domain>)
  --mail-password <password>   Password mailbox untuk --with-mail (default: auto-generate)
  --help                       Tampilkan bantuan

Contoh:
  sudo bash scripts/setup_vps_fresh_presenova.sh \
    --domain presenova.my.id \
    --email adm@presenova.my.id \
    --app-dir /var/www/presenova \
    --ebook-domain ebook.presenova.my.id \
    --ebook-dir /var/www/presenova/Ebook \
    --db-name presenova \
    --db-user presenova
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

upsert_env() {
  local file="$1"
  local key="$2"
  local value="$3"

  if grep -qE "^${key}=" "${file}"; then
    sed -i "s|^${key}=.*|${key}=${value}|" "${file}"
  else
    printf '\n%s=%s\n' "${key}" "${value}" >> "${file}"
  fi
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

DOMAIN="presenova.my.id"
EMAIL="adm@presenova.my.id"
ALIASES_RAW=""
EBOOK_DOMAIN=""
EBOOK_DIR=""
APP_DIR=""
DB_NAME="presenova"
DB_USER="presenova"
DB_PASS=""
DB_HOST="127.0.0.1"
DB_PORT="3306"
SQL_FILE=""
SKIP_SQL_IMPORT=0
SKIP_HTTPS=0
SKIP_CRON=0
WITH_DEEPFACE=0
WITH_MAIL=0
MAIL_ADMIN_EMAIL=""
MAIL_HOST=""
MAIL_PASSWORD=""
AUTO_GENERATED_DB_PASSWORD=0

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
    --ebook-domain)
      EBOOK_DOMAIN="${2:-}"
      shift 2
      ;;
    --ebook-dir)
      EBOOK_DIR="${2:-}"
      shift 2
      ;;
    --app-dir)
      APP_DIR="${2:-}"
      shift 2
      ;;
    --db-name)
      DB_NAME="${2:-}"
      shift 2
      ;;
    --db-user)
      DB_USER="${2:-}"
      shift 2
      ;;
    --db-pass)
      DB_PASS="${2:-}"
      shift 2
      ;;
    --db-host)
      DB_HOST="${2:-}"
      shift 2
      ;;
    --db-port)
      DB_PORT="${2:-}"
      shift 2
      ;;
    --sql-file)
      SQL_FILE="${2:-}"
      shift 2
      ;;
    --skip-sql-import)
      SKIP_SQL_IMPORT=1
      shift
      ;;
    --skip-https)
      SKIP_HTTPS=1
      shift
      ;;
    --skip-cron)
      SKIP_CRON=1
      shift
      ;;
    --with-deepface)
      WITH_DEEPFACE=1
      shift
      ;;
    --with-mail)
      WITH_MAIL=1
      shift
      ;;
    --mail-admin-email)
      MAIL_ADMIN_EMAIL="${2:-}"
      shift 2
      ;;
    --mail-host)
      MAIL_HOST="${2:-}"
      shift 2
      ;;
    --mail-password)
      MAIL_PASSWORD="${2:-}"
      shift 2
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

[[ -n "${DOMAIN}" ]] || fail "--domain wajib diisi."
[[ -n "${EMAIL}" ]] || fail "--email wajib diisi."
[[ -n "${DB_NAME}" ]] || fail "--db-name wajib diisi."
[[ -n "${DB_USER}" ]] || fail "--db-user wajib diisi."
[[ -n "${DB_HOST}" ]] || fail "--db-host wajib diisi."
[[ -n "${DB_PORT}" ]] || fail "--db-port wajib diisi."

if [[ "${EUID}" -ne 0 ]]; then
  fail "Jalankan script dengan sudo/root."
fi

if [[ ! "${DB_NAME}" =~ ^[A-Za-z0-9_]+$ ]]; then
  fail "--db-name hanya boleh alfanumerik/underscore."
fi
if [[ "${DB_USER}" == *"'"* || "${DB_PASS}" == *"'"* ]]; then
  fail "--db-user/--db-pass tidak boleh mengandung tanda petik tunggal (')."
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
APP_DIR="${APP_DIR:-${DEFAULT_APP_DIR}}"
APP_DIR="$(cd "${APP_DIR}" && pwd)"
EBOOK_DOMAIN="${EBOOK_DOMAIN:-ebook.${DOMAIN}}"
EBOOK_DIR="${EBOOK_DIR:-${APP_DIR}/Ebook}"

[[ -f "${APP_DIR}/artisan" ]] || fail "File artisan tidak ditemukan di ${APP_DIR}."

if [[ -z "${ALIASES_RAW}" ]]; then
  ALIASES_RAW="www.${DOMAIN}"
fi

mkdir -p "${EBOOK_DIR}"
EBOOK_DIR="$(cd "${EBOOK_DIR}" && pwd)"

if [[ -z "${SQL_FILE}" ]]; then
  SQL_FILE="${APP_DIR}/presenova.sql"
fi

MAIL_ADMIN_EMAIL="${MAIL_ADMIN_EMAIL:-adm@${DOMAIN}}"
MAIL_HOST="${MAIL_HOST:-mail.${DOMAIN}}"

log "Install dependency dasar VPS..."
apt_install_packages \
  ca-certificates \
  curl \
  git \
  unzip \
  apache2 \
  mariadb-server \
  composer \
  php \
  php-cli \
  libapache2-mod-php \
  php-mysql \
  php-xml \
  php-mbstring \
  php-curl \
  php-zip \
  php-gd \
  php-intl \
  php-bcmath \
  php-opcache \
  certbot \
  python3-certbot-apache \
  nodejs \
  npm \
  cron \
  openssl

require_cmd php
require_cmd composer
require_cmd mysql
require_cmd a2enmod
require_cmd systemctl

if [[ -z "${DB_PASS}" ]]; then
  if command -v openssl >/dev/null 2>&1; then
    DB_PASS="$(openssl rand -hex 18)"
  else
    DB_PASS="$(php -r 'echo bin2hex(random_bytes(18));' 2>/dev/null || true)"
  fi
  [[ -n "${DB_PASS}" ]] || fail "Gagal generate password database otomatis."
  AUTO_GENERATED_DB_PASSWORD=1
fi

systemctl enable --now apache2 >/dev/null 2>&1 || true
systemctl enable --now mariadb >/dev/null 2>&1 || true
systemctl enable --now cron >/dev/null 2>&1 || true

if command -v ufw >/dev/null 2>&1; then
  log "Membuka firewall ufw untuk HTTP/HTTPS..."
  ufw allow 80/tcp >/dev/null || true
  ufw allow 443/tcp >/dev/null || true
fi

log "Aktifkan module Apache dasar..."
a2enmod rewrite headers ssl expires >/dev/null || true
systemctl reload apache2 >/dev/null 2>&1 || true

ENV_FILE="${APP_DIR}/.env"
if [[ ! -f "${ENV_FILE}" ]]; then
  [[ -f "${APP_DIR}/.env.example" ]] || fail ".env.example tidak ditemukan."
  cp "${APP_DIR}/.env.example" "${ENV_FILE}"
  log "Membuat .env dari .env.example"
fi

log "Konfigurasi database MariaDB..."
mysql -e "SELECT 1;" >/dev/null 2>&1 || fail "Tidak bisa akses mysql sebagai root (socket auth)."
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

log "Update .env inti (app + DB)..."
upsert_env "${ENV_FILE}" "APP_ENV" "production"
upsert_env "${ENV_FILE}" "APP_DEBUG" "false"
upsert_env "${ENV_FILE}" "APP_URL" "http://${DOMAIN}"
upsert_env "${ENV_FILE}" "SITE_URL" "http://${DOMAIN}"
upsert_env "${ENV_FILE}" "FORCE_HTTPS" "false"
upsert_env "${ENV_FILE}" "SESSION_SECURE_COOKIE" "false"
upsert_env "${ENV_FILE}" "MEDIA_SIGNED_URL_TTL_MINUTES" "60"
upsert_env "${ENV_FILE}" "DB_CONNECTION" "mysql"
upsert_env "${ENV_FILE}" "DB_HOST" "${DB_HOST}"
upsert_env "${ENV_FILE}" "DB_PORT" "${DB_PORT}"
upsert_env "${ENV_FILE}" "DB_DATABASE" "${DB_NAME}"
upsert_env "${ENV_FILE}" "DB_USERNAME" "${DB_USER}"
upsert_env "${ENV_FILE}" "DB_PASSWORD" "${DB_PASS}"

log "Install dependency composer..."
export COMPOSER_ALLOW_SUPERUSER=1
(cd "${APP_DIR}" && composer install --no-dev --optimize-autoloader --no-interaction)

[[ -f "${APP_DIR}/vendor/autoload.php" ]] || fail "vendor/autoload.php tidak ditemukan setelah composer install."

if grep -qE '^APP_KEY=$' "${ENV_FILE}" || ! grep -qE '^APP_KEY=base64:' "${ENV_FILE}"; then
  log "Generate APP_KEY..."
  (cd "${APP_DIR}" && php artisan key:generate --force --no-interaction)
fi

if [[ "${SKIP_SQL_IMPORT}" -eq 0 ]]; then
  if [[ -f "${SQL_FILE}" ]]; then
    TABLE_COUNT="$(mysql -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}'" || true)"
    TABLE_COUNT="${TABLE_COUNT:-0}"
    if [[ "${TABLE_COUNT}" == "0" ]]; then
      log "Import SQL bootstrap: ${SQL_FILE}"
      mysql "${DB_NAME}" < "${SQL_FILE}"
    else
      log "Skip import SQL karena database '${DB_NAME}' sudah berisi ${TABLE_COUNT} tabel."
    fi
  else
    log "Skip import SQL karena file tidak ditemukan: ${SQL_FILE}"
  fi
else
  log "Import SQL dilewati (--skip-sql-import)."
fi

log "Menjalankan migrate/cache Laravel..."
(cd "${APP_DIR}" && php artisan optimize:clear)
(cd "${APP_DIR}" && php artisan migrate --force --no-interaction)
(cd "${APP_DIR}" && php artisan storage:link >/dev/null 2>&1 || true)

CORE_TABLE_COUNT="$(mysql -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name IN ('user','student','teacher','site')" || true)"
CORE_TABLE_COUNT="${CORE_TABLE_COUNT:-0}"
if [[ "${CORE_TABLE_COUNT}" -lt 4 ]]; then
  fail "Tabel inti Presenova belum lengkap di database '${DB_NAME}'. Jalankan import SQL awal (mis. ${SQL_FILE}) lalu ulangi script."
fi

log "Set permission runtime Laravel..."
mkdir -p "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" "${APP_DIR}/public/uploads" "${EBOOK_DIR}"
chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" "${APP_DIR}/public/uploads" "${EBOOK_DIR}"
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" "${APP_DIR}/public/uploads" -type d -exec chmod 775 {} \;
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" "${APP_DIR}/public/uploads" -type f -exec chmod 664 {} \;
find "${EBOOK_DIR}" -type d -exec chmod 755 {} \;
find "${EBOOK_DIR}" -type f -exec chmod 644 {} \;

if [[ "${SKIP_HTTPS}" -eq 0 ]]; then
  log "Setup HTTPS Let's Encrypt..."
  bash "${APP_DIR}/scripts/setup_https_letsencrypt_linux.sh" \
    --domain "${DOMAIN}" \
    --email "${EMAIL}" \
    --app-dir "${APP_DIR}" \
    --aliases "${ALIASES_RAW}" \
    --ebook-domain "${EBOOK_DOMAIN}" \
    --ebook-dir "${EBOOK_DIR}"
else
  log "Setup HTTPS dilewati (--skip-https)."
fi

if [[ "${SKIP_CRON}" -eq 0 ]]; then
  log "Setup cron push notification..."
  bash "${APP_DIR}/scripts/install_push_cron_linux.sh" "${APP_DIR}"
else
  log "Setup cron dilewati (--skip-cron)."
fi

if [[ "${WITH_DEEPFACE}" -eq 1 ]]; then
  log "Setup DeepFace..."
  bash "${APP_DIR}/scripts/setup_deepface.sh" \
    --project-root "${APP_DIR}" \
    --install-system-deps \
    --write-env
fi

if [[ "${WITH_MAIL}" -eq 1 ]]; then
  log "Setup mail server..."
  MAIL_CMD=(
    bash "${APP_DIR}/scripts/setup_mail_server_linux.sh"
    --domain "${DOMAIN}"
    --admin-email "${MAIL_ADMIN_EMAIL}"
    --mail-host "${MAIL_HOST}"
    --app-dir "${APP_DIR}"
  )
  if [[ -n "${MAIL_PASSWORD}" ]]; then
    MAIL_CMD+=(--mail-password "${MAIL_PASSWORD}")
  fi
  "${MAIL_CMD[@]}"
fi

log "Finalisasi cache Laravel..."
(cd "${APP_DIR}" && php artisan optimize:clear)
(cd "${APP_DIR}" && php artisan config:cache)

CREDENTIAL_FILE="/root/${DOMAIN//./-}-presenova-vps-credentials.txt"
cat > "${CREDENTIAL_FILE}" <<EOF
Domain        : ${DOMAIN}
Ebook domain  : ${EBOOK_DOMAIN}
App directory : ${APP_DIR}
Ebook dir     : ${EBOOK_DIR}
DB host       : ${DB_HOST}
DB port       : ${DB_PORT}
DB name       : ${DB_NAME}
DB user       : ${DB_USER}
DB password   : ${DB_PASS}
EOF
chmod 600 "${CREDENTIAL_FILE}"

log "Selesai."
echo
echo "Credential tersimpan di: ${CREDENTIAL_FILE}"
if [[ "${AUTO_GENERATED_DB_PASSWORD}" -eq 1 ]]; then
  echo "Password DB dibuat otomatis."
fi
echo
echo "Verifikasi cepat:"
echo "  curl -I http://${DOMAIN}"
echo "  curl -I https://${DOMAIN}"
echo "  curl -I https://${EBOOK_DOMAIN}"
echo "  crontab -l | grep PRESENOVA_PUSH_CRON"
echo "  systemctl status apache2 mariadb cron --no-pager"
