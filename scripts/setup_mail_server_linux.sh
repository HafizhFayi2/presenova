#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  sudo bash scripts/setup_mail_server_linux.sh [options]

Options:
  --domain <domain>            Domain email (default: presenova.my.id)
  --admin-email <email>        Akun admin email (default: admin@presenova.my.id)
  --mail-password <password>   Password mailbox admin (default: auto-generate)
  --mail-host <host>           Host mail server (default: mail.<domain>)
  --letsencrypt-email <email>  Email untuk registrasi Let's Encrypt (default: admin-email)
  --app-dir <path>             Path project Laravel untuk update .env (default: parent folder script)
  --skip-cert                  Lewati Let's Encrypt dan pakai self-signed certificate
  --skip-env-update            Jangan update file .env project
  --help                       Tampilkan bantuan

Contoh:
  sudo bash scripts/setup_mail_server_linux.sh
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

ensure_postfix_master_service() {
  local service_name="$1"
  local service_block="$2"

  if ! grep -qE "^${service_name}[[:space:]]+inet" /etc/postfix/master.cf; then
    printf '\n%s\n' "${service_block}" >> /etc/postfix/master.cf
  fi
}

DOMAIN="presenova.my.id"
ADMIN_EMAIL="admin@presenova.my.id"
MAIL_PASSWORD=""
MAIL_HOST=""
LETSENCRYPT_EMAIL=""
APP_DIR=""
SKIP_CERT=0
SKIP_ENV_UPDATE=0
AUTO_GENERATED_PASSWORD=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain)
      DOMAIN="${2:-}"
      shift 2
      ;;
    --admin-email)
      ADMIN_EMAIL="${2:-}"
      shift 2
      ;;
    --mail-password)
      MAIL_PASSWORD="${2:-}"
      shift 2
      ;;
    --mail-host)
      MAIL_HOST="${2:-}"
      shift 2
      ;;
    --letsencrypt-email)
      LETSENCRYPT_EMAIL="${2:-}"
      shift 2
      ;;
    --app-dir)
      APP_DIR="${2:-}"
      shift 2
      ;;
    --skip-cert)
      SKIP_CERT=1
      shift
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

[[ -n "${DOMAIN}" ]] || fail "--domain wajib diisi."
[[ -n "${ADMIN_EMAIL}" ]] || fail "--admin-email wajib diisi."

if [[ "${EUID}" -ne 0 ]]; then
  fail "Jalankan script dengan sudo/root."
fi

if [[ "${DOMAIN}" != "presenova.my.id" ]]; then
  log "Info: domain diganti ke '${DOMAIN}'."
fi

MAIL_HOST="${MAIL_HOST:-mail.${DOMAIN}}"
LETSENCRYPT_EMAIL="${LETSENCRYPT_EMAIL:-${ADMIN_EMAIL}}"

MAILBOX_LOCALPART="${ADMIN_EMAIL%@*}"
MAILBOX_DOMAIN="${ADMIN_EMAIL#*@}"
[[ -n "${MAILBOX_LOCALPART}" && "${MAILBOX_DOMAIN}" != "${ADMIN_EMAIL}" ]] || fail "Format --admin-email tidak valid."
[[ "${MAILBOX_DOMAIN}" == "${DOMAIN}" ]] || fail "Domain email admin harus sama dengan --domain."

if [[ -z "${MAIL_PASSWORD}" ]]; then
  require_cmd openssl
  MAIL_PASSWORD="$(openssl rand -hex 16)"
  AUTO_GENERATED_PASSWORD=1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
APP_DIR="${APP_DIR:-${DEFAULT_APP_DIR}}"
if [[ -d "${APP_DIR}" ]]; then
  APP_DIR="$(cd "${APP_DIR}" && pwd)"
fi

require_cmd apt-get
require_cmd systemctl
require_cmd sed
require_cmd awk
require_cmd debconf-set-selections

log "Install dependency mail server..."
export DEBIAN_FRONTEND=noninteractive
echo "postfix postfix/mailname string ${DOMAIN}" | debconf-set-selections
echo "postfix postfix/main_mailer_type select Internet Site" | debconf-set-selections
apt-get update -y
apt-get install -y postfix postfix-pcre libsasl2-modules dovecot-core dovecot-imapd dovecot-pop3d dovecot-lmtpd opendkim opendkim-tools certbot ca-certificates curl

require_cmd postconf
require_cmd postmap
require_cmd doveadm
require_cmd opendkim-genkey
require_cmd certbot

if ! getent group vmail >/dev/null 2>&1; then
  groupadd --system vmail
fi
if ! id -u vmail >/dev/null 2>&1; then
  useradd --system --gid vmail --home /var/mail/vhosts --shell /usr/sbin/nologin vmail
fi

VMAIL_UID="$(id -u vmail)"
VMAIL_GID="$(id -g vmail)"
MAIL_ROOT="/var/mail/vhosts"
MAILBOX_DIR="${MAIL_ROOT}/${DOMAIN}/${MAILBOX_LOCALPART}"

mkdir -p "${MAILBOX_DIR}"/{cur,new,tmp}
chown -R vmail:vmail "${MAIL_ROOT}"
chmod -R 770 "${MAIL_ROOT}"

HASHED_PASSWORD="$(doveadm pw -s SHA512-CRYPT -p "${MAIL_PASSWORD}")"
cat > /etc/dovecot/passwd <<EOF
${ADMIN_EMAIL}:${HASHED_PASSWORD}:${VMAIL_UID}:${VMAIL_GID}::${MAILBOX_DIR}::
EOF
chmod 640 /etc/dovecot/passwd
if getent group dovecot >/dev/null 2>&1; then
  chown root:dovecot /etc/dovecot/passwd
fi

cat > /etc/postfix/virtual_mailbox_domains <<EOF
${DOMAIN} OK
EOF

cat > /etc/postfix/virtual_mailbox_maps <<EOF
${ADMIN_EMAIL} ${DOMAIN}/${MAILBOX_LOCALPART}/
EOF

cat > /etc/postfix/virtual_alias_maps <<EOF
postmaster@${DOMAIN} ${ADMIN_EMAIL}
abuse@${DOMAIN} ${ADMIN_EMAIL}
EOF

postmap /etc/postfix/virtual_mailbox_domains
postmap /etc/postfix/virtual_mailbox_maps
postmap /etc/postfix/virtual_alias_maps

STOPPED_WEB_SERVICES=()
restore_web_services() {
  local svc
  for svc in "${STOPPED_WEB_SERVICES[@]}"; do
    systemctl start "${svc}" || true
  done
  STOPPED_WEB_SERVICES=()
}

TLS_CERT_FILE=""
TLS_KEY_FILE=""

if [[ "${SKIP_CERT}" -eq 1 ]]; then
  log "Skip Let's Encrypt, membuat self-signed certificate..."
  SELF_SIGNED_DIR="/etc/ssl/presenova-mail"
  mkdir -p "${SELF_SIGNED_DIR}"
  TLS_CERT_FILE="${SELF_SIGNED_DIR}/${MAIL_HOST}.crt"
  TLS_KEY_FILE="${SELF_SIGNED_DIR}/${MAIL_HOST}.key"
  openssl req -x509 -nodes -newkey rsa:4096 -days 365 \
    -subj "/CN=${MAIL_HOST}" \
    -keyout "${TLS_KEY_FILE}" \
    -out "${TLS_CERT_FILE}"
else
  log "Minta TLS certificate Let's Encrypt untuk ${MAIL_HOST}..."
  for svc in apache2 nginx; do
    if systemctl list-unit-files | grep -q "^${svc}\.service" && systemctl is-active --quiet "${svc}"; then
      log "Stop sementara service '${svc}' agar certbot standalone bisa bind port 80."
      systemctl stop "${svc}"
      STOPPED_WEB_SERVICES+=("${svc}")
    fi
  done

  trap restore_web_services EXIT
  certbot certonly --standalone --non-interactive --agree-tos --email "${LETSENCRYPT_EMAIL}" --keep-until-expiring -d "${MAIL_HOST}"
  restore_web_services
  trap - EXIT

  TLS_CERT_FILE="/etc/letsencrypt/live/${MAIL_HOST}/fullchain.pem"
  TLS_KEY_FILE="/etc/letsencrypt/live/${MAIL_HOST}/privkey.pem"
fi

[[ -f "${TLS_CERT_FILE}" ]] || fail "Certificate file tidak ditemukan: ${TLS_CERT_FILE}"
[[ -f "${TLS_KEY_FILE}" ]] || fail "Private key file tidak ditemukan: ${TLS_KEY_FILE}"

log "Konfigurasi OpenDKIM..."
DKIM_DIR="/etc/opendkim/keys/${DOMAIN}"
mkdir -p "${DKIM_DIR}"
if [[ ! -f "${DKIM_DIR}/mail.private" || ! -f "${DKIM_DIR}/mail.txt" ]]; then
  opendkim-genkey -b 2048 -d "${DOMAIN}" -D "${DKIM_DIR}" -s mail -v
fi

cat > /etc/opendkim/key.table <<EOF
mail._domainkey.${DOMAIN} ${DOMAIN}:mail:${DKIM_DIR}/mail.private
EOF

cat > /etc/opendkim/signing.table <<EOF
*@${DOMAIN} mail._domainkey.${DOMAIN}
EOF

cat > /etc/opendkim/trusted.hosts <<EOF
127.0.0.1
::1
localhost
${DOMAIN}
${MAIL_HOST}
EOF

cat > /etc/opendkim.conf <<EOF
Syslog                  yes
UMask                   002
Canonicalization        relaxed/simple
Mode                    sv
SubDomains              no
OversignHeaders         From
Socket                  local:/run/opendkim/opendkim.sock
UserID                  opendkim
PidFile                 /run/opendkim/opendkim.pid
KeyTable                /etc/opendkim/key.table
SigningTable            refile:/etc/opendkim/signing.table
ExternalIgnoreList      /etc/opendkim/trusted.hosts
InternalHosts           /etc/opendkim/trusted.hosts
EOF

if [[ -f /etc/default/opendkim ]]; then
  if grep -q '^SOCKET=' /etc/default/opendkim; then
    sed -i 's|^SOCKET=.*|SOCKET="local:/run/opendkim/opendkim.sock"|' /etc/default/opendkim
  else
    printf '\nSOCKET="local:/run/opendkim/opendkim.sock"\n' >> /etc/default/opendkim
  fi
fi

chown root:root /etc/opendkim.conf /etc/opendkim/key.table /etc/opendkim/signing.table /etc/opendkim/trusted.hosts
chmod 700 /etc/opendkim/keys
chown -R opendkim:opendkim /etc/opendkim/keys
chmod 700 "${DKIM_DIR}"
chmod 600 "${DKIM_DIR}/mail.private"
chmod 644 "${DKIM_DIR}/mail.txt"
usermod -aG opendkim postfix || true

log "Konfigurasi Postfix..."
postconf -e "myhostname = ${MAIL_HOST}"
postconf -e "mydomain = ${DOMAIN}"
postconf -e "myorigin = \$mydomain"
postconf -e "mydestination = localhost.\$mydomain, localhost"
postconf -e "relayhost ="
postconf -e "inet_interfaces = all"
postconf -e "inet_protocols = ipv4"
postconf -e "virtual_mailbox_domains = hash:/etc/postfix/virtual_mailbox_domains"
postconf -e "virtual_mailbox_maps = hash:/etc/postfix/virtual_mailbox_maps"
postconf -e "virtual_alias_maps = hash:/etc/postfix/virtual_alias_maps"
postconf -e "virtual_minimum_uid = ${VMAIL_UID}"
postconf -e "virtual_uid_maps = static:${VMAIL_UID}"
postconf -e "virtual_gid_maps = static:${VMAIL_GID}"
postconf -e "virtual_transport = lmtp:unix:private/dovecot-lmtp"
postconf -e "smtpd_sasl_type = dovecot"
postconf -e "smtpd_sasl_path = private/auth"
postconf -e "smtpd_sasl_auth_enable = yes"
postconf -e "smtpd_sasl_security_options = noanonymous"
postconf -e "broken_sasl_auth_clients = yes"
postconf -e "smtpd_tls_cert_file = ${TLS_CERT_FILE}"
postconf -e "smtpd_tls_key_file = ${TLS_KEY_FILE}"
postconf -e "smtpd_use_tls = yes"
postconf -e "smtpd_tls_security_level = may"
postconf -e "smtpd_tls_auth_only = yes"
postconf -e "smtp_tls_security_level = may"
postconf -e "smtpd_recipient_restrictions = permit_sasl_authenticated,permit_mynetworks,reject_unauth_destination"
postconf -e "milter_default_action = accept"
postconf -e "milter_protocol = 6"
postconf -e "smtpd_milters = local:/run/opendkim/opendkim.sock"
postconf -e "non_smtpd_milters = local:/run/opendkim/opendkim.sock"
postconf -e "mailbox_size_limit = 0"
postconf -e "message_size_limit = 52428800"

SUBMISSION_BLOCK="$(cat <<'EOF'
submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_tls_auth_only=yes
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
EOF
)"

SMTPS_BLOCK="$(cat <<'EOF'
smtps     inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/smtps
  -o smtpd_tls_wrappermode=yes
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
EOF
)"

ensure_postfix_master_service "submission" "${SUBMISSION_BLOCK}"
ensure_postfix_master_service "smtps" "${SMTPS_BLOCK}"

log "Konfigurasi Dovecot..."
if [[ -f /etc/dovecot/conf.d/10-auth.conf ]]; then
  sed -i 's/^!include auth-system\.conf\.ext/#!include auth-system.conf.ext/' /etc/dovecot/conf.d/10-auth.conf

  if grep -q '^#!include auth-passwdfile\.conf\.ext' /etc/dovecot/conf.d/10-auth.conf; then
    sed -i 's/^#!include auth-passwdfile\.conf\.ext/!include auth-passwdfile.conf.ext/' /etc/dovecot/conf.d/10-auth.conf
  elif ! grep -q '^!include auth-passwdfile\.conf\.ext' /etc/dovecot/conf.d/10-auth.conf; then
    printf '\n!include auth-passwdfile.conf.ext\n' >> /etc/dovecot/conf.d/10-auth.conf
  fi
fi

cat > /etc/dovecot/conf.d/auth-passwdfile.conf.ext <<EOF
passdb {
  driver = passwd-file
  args = scheme=SHA512-CRYPT username_format=%u /etc/dovecot/passwd
}

userdb {
  driver = static
  args = uid=${VMAIL_UID} gid=${VMAIL_GID} home=/var/mail/vhosts/%d/%n
}
EOF

cat > /etc/dovecot/conf.d/99-presenova-mail.conf <<EOF
protocols = imap pop3 lmtp
listen = *
mail_location = maildir:/var/mail/vhosts/%d/%n
first_valid_uid = ${VMAIL_UID}
last_valid_uid = ${VMAIL_UID}
disable_plaintext_auth = yes
auth_mechanisms = plain login
ssl = required
ssl_cert = <${TLS_CERT_FILE}
ssl_key = <${TLS_KEY_FILE}

service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0660
    user = postfix
    group = postfix
  }
}

service lmtp {
  unix_listener /var/spool/postfix/private/dovecot-lmtp {
    mode = 0600
    user = postfix
    group = postfix
  }
}
EOF

if [[ -d /etc/letsencrypt/renewal-hooks/deploy && "${SKIP_CERT}" -eq 0 ]]; then
  cat > /etc/letsencrypt/renewal-hooks/deploy/reload-mail-services.sh <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
systemctl reload dovecot
systemctl reload postfix
EOF
  chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-mail-services.sh
fi

log "Buka port firewall jika ufw tersedia..."
if command -v ufw >/dev/null 2>&1; then
  ufw allow 25/tcp >/dev/null || true
  ufw allow 465/tcp >/dev/null || true
  ufw allow 587/tcp >/dev/null || true
  ufw allow 143/tcp >/dev/null || true
  ufw allow 993/tcp >/dev/null || true
  ufw allow 110/tcp >/dev/null || true
  ufw allow 995/tcp >/dev/null || true
fi

log "Restart dan enable service..."
systemctl enable --now opendkim
systemctl enable --now dovecot
systemctl enable --now postfix
systemctl restart opendkim
systemctl restart dovecot
systemctl restart postfix

postfix check
doveconf -n >/dev/null
systemctl is-active --quiet opendkim || fail "Service opendkim tidak aktif."
systemctl is-active --quiet dovecot || fail "Service dovecot tidak aktif."
systemctl is-active --quiet postfix || fail "Service postfix tidak aktif."

if [[ "${SKIP_ENV_UPDATE}" -eq 0 ]]; then
  ENV_FILE="${APP_DIR}/.env"
  if [[ -f "${ENV_FILE}" ]]; then
    log "Update konfigurasi mail di ${ENV_FILE}..."
    upsert_env "${ENV_FILE}" "MAIL_MAILER" "smtp"
    upsert_env "${ENV_FILE}" "MAIL_HOST" "127.0.0.1"
    upsert_env "${ENV_FILE}" "MAIL_PORT" "587"
    upsert_env "${ENV_FILE}" "MAIL_USERNAME" "${ADMIN_EMAIL}"
    upsert_env "${ENV_FILE}" "MAIL_PASSWORD" "${MAIL_PASSWORD}"
    upsert_env "${ENV_FILE}" "MAIL_ENCRYPTION" "tls"
    upsert_env "${ENV_FILE}" "MAIL_FROM_ADDRESS" "${ADMIN_EMAIL}"
    if [[ -f "${APP_DIR}/artisan" ]]; then
      (cd "${APP_DIR}" && php artisan optimize:clear >/dev/null 2>&1 || true)
    fi
  else
    log "Lewati update .env karena file tidak ditemukan di ${ENV_FILE}."
  fi
fi

CREDENTIAL_FILE="/root/${DOMAIN}-mail-credentials.txt"
cat > "${CREDENTIAL_FILE}" <<EOF
Domain        : ${DOMAIN}
Mail host     : ${MAIL_HOST}
Admin email   : ${ADMIN_EMAIL}
Password      : ${MAIL_PASSWORD}

SMTP STARTTLS : ${MAIL_HOST}:587
SMTP SSL/TLS  : ${MAIL_HOST}:465
IMAP SSL/TLS  : ${MAIL_HOST}:993
POP3 SSL/TLS  : ${MAIL_HOST}:995
EOF
chmod 600 "${CREDENTIAL_FILE}"

DKIM_DNS_VALUE="$(grep -oE '"[^"]+"' "${DKIM_DIR}/mail.txt" | tr -d '"' | tr -d '[:space:]' || true)"
PUBLIC_IP="$(curl -fsS https://api.ipify.org || true)"
PUBLIC_IP="${PUBLIC_IP:-<IP-VPS-ANDA>}"

log "Selesai."
echo
echo "Credential tersimpan di: ${CREDENTIAL_FILE}"
if [[ "${AUTO_GENERATED_PASSWORD}" -eq 1 ]]; then
  echo "Password mailbox dibuat otomatis."
fi
echo
echo "DNS yang wajib diset di provider domain (${DOMAIN}):"
echo "1) A Record"
echo "   Host: mail"
echo "   Value: ${PUBLIC_IP}"
echo
echo "2) MX Record"
echo "   Host: @"
echo "   Value: ${MAIL_HOST}"
echo "   Priority: 10"
echo
echo "3) TXT SPF"
echo "   Host: @"
echo "   Value: v=spf1 mx a:${MAIL_HOST} ~all"
echo
echo "4) TXT DKIM"
echo "   Host: mail._domainkey"
echo "   Value: ${DKIM_DNS_VALUE}"
echo
echo "5) TXT DMARC"
echo "   Host: _dmarc"
echo "   Value: v=DMARC1; p=quarantine; rua=mailto:${ADMIN_EMAIL}; fo=1"
echo
echo "Tes cepat setelah DNS propagate:"
echo "  dig +short MX ${DOMAIN}"
echo "  openssl s_client -connect ${MAIL_HOST}:587 -starttls smtp -servername ${MAIL_HOST} </dev/null"
echo "  openssl s_client -connect ${MAIL_HOST}:993 -servername ${MAIL_HOST} </dev/null"
echo
echo "Saran kirim test email:"
echo "  echo 'SMTP test from ${DOMAIN}' | mail -s 'SMTP Test' ${ADMIN_EMAIL}"
