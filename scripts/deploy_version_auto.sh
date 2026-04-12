#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Auto versioning Presenova saat proses deploy.

Behavior:
- Jika versi belum ada di .env, inisialisasi ke START_VERSION (default: 1.0.0)
- Jika versi sudah ada, bump otomatis (default: patch)
- Idempotent per commit Git: commit yang sama tidak akan bump ulang (kecuali --force-bump)
- Sinkronkan APP_VERSION, SYSTEM_CURRENT_VERSION, SYSTEM_LATEST_VERSION

Usage:
  bash scripts/deploy_version_auto.sh [options]

Options:
  --app-dir <path>         Root project (default: parent folder script)
  --env-file <path>        File .env target (default: <app-dir>/.env)
  --state-file <path>      File state deploy (default: <app-dir>/storage/app/deploy-version-state.env)
  --start-version <x.y.z>  Versi awal jika belum ada (default: 1.0.0)
  --bump <major|minor|patch> Jenis bump deploy (default: patch)
  --force-bump             Paksa bump walau commit sama
  --help                   Tampilkan bantuan
USAGE
}

log() {
  printf '[deploy-version] %s\n' "$*"
}

fail() {
  printf '[deploy-version] ERROR: %s\n' "$*" >&2
  exit 1
}

sanitize_value() {
  local value="${1:-}"
  value="${value//$'\r'/}"
  value="${value//$'\n'/}"
  value="${value#v}"
  value="${value#V}"
  value="$(echo "${value}" | xargs)"
  if [[ "${value}" == \"*\" && "${value}" == *\" ]]; then
    value="${value:1:${#value}-2}"
  fi
  if [[ "${value}" == \'*\' && "${value}" == *\' ]]; then
    value="${value:1:${#value}-2}"
  fi
  echo "${value}"
}

is_semver() {
  local version="${1:-}"
  [[ "${version}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

read_kv_value() {
  local file="$1"
  local key="$2"
  if [[ ! -f "${file}" ]]; then
    echo ""
    return 0
  fi
  local line
  line="$(grep -E "^${key}=" "${file}" | tail -n1 || true)"
  if [[ -z "${line}" ]]; then
    echo ""
    return 0
  fi
  sanitize_value "${line#*=}"
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

bump_version() {
  local version="$1"
  local bump_type="$2"
  local major minor patch
  IFS='.' read -r major minor patch <<< "${version}"

  case "${bump_type}" in
    major)
      major=$((major + 1))
      minor=0
      patch=0
      ;;
    minor)
      minor=$((minor + 1))
      patch=0
      ;;
    patch)
      patch=$((patch + 1))
      ;;
    *)
      fail "Jenis bump tidak valid: ${bump_type}"
      ;;
  esac

  echo "${major}.${minor}.${patch}"
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE=""
STATE_FILE=""
START_VERSION="1.0.0"
BUMP_TYPE="patch"
FORCE_BUMP=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --app-dir)
      APP_DIR="${2:-}"
      shift 2
      ;;
    --env-file)
      ENV_FILE="${2:-}"
      shift 2
      ;;
    --state-file)
      STATE_FILE="${2:-}"
      shift 2
      ;;
    --start-version)
      START_VERSION="${2:-}"
      shift 2
      ;;
    --bump)
      BUMP_TYPE="${2:-}"
      shift 2
      ;;
    --force-bump)
      FORCE_BUMP=1
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

APP_DIR="$(cd "${APP_DIR}" && pwd)"
ENV_FILE="${ENV_FILE:-${APP_DIR}/.env}"
STATE_FILE="${STATE_FILE:-${APP_DIR}/storage/app/deploy-version-state.env}"

[[ -f "${ENV_FILE}" ]] || fail "File .env tidak ditemukan: ${ENV_FILE}"
is_semver "${START_VERSION}" || fail "--start-version harus format x.y.z"
[[ "${BUMP_TYPE}" =~ ^(major|minor|patch)$ ]] || fail "--bump harus major|minor|patch"

env_latest="$(read_kv_value "${ENV_FILE}" "SYSTEM_LATEST_VERSION")"
env_current="$(read_kv_value "${ENV_FILE}" "SYSTEM_CURRENT_VERSION")"
env_app="$(read_kv_value "${ENV_FILE}" "APP_VERSION")"

for candidate in "${env_latest}" "${env_current}" "${env_app}"; do
  if [[ -n "${candidate}" ]] && ! is_semver "${candidate}"; then
    fail "Versi di .env harus format x.y.z. Nilai invalid: ${candidate}"
  fi
done

base_version=""
if [[ -n "${env_latest}" ]]; then
  base_version="${env_latest}"
elif [[ -n "${env_current}" ]]; then
  base_version="${env_current}"
elif [[ -n "${env_app}" ]]; then
  base_version="${env_app}"
fi

current_commit=""
if command -v git >/dev/null 2>&1 && git -C "${APP_DIR}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  current_commit="$(git -C "${APP_DIR}" rev-parse --short=12 HEAD 2>/dev/null || true)"
fi

last_commit="$(read_kv_value "${STATE_FILE}" "LAST_COMMIT")"
last_version="$(read_kv_value "${STATE_FILE}" "LAST_VERSION")"

next_version=""
action="init"
if [[ -z "${base_version}" ]]; then
  next_version="${START_VERSION}"
  action="init_start_version"
elif [[ "${FORCE_BUMP}" -eq 0 && -n "${current_commit}" && -n "${last_commit}" && "${current_commit}" == "${last_commit}" ]]; then
  next_version="${base_version}"
  action="same_commit_no_bump"
else
  next_version="$(bump_version "${base_version}" "${BUMP_TYPE}")"
  action="bump_${BUMP_TYPE}"
fi

is_semver "${next_version}" || fail "Hasil versi tidak valid: ${next_version}"

upsert_env "${ENV_FILE}" "APP_VERSION" "${next_version}"
upsert_env "${ENV_FILE}" "SYSTEM_CURRENT_VERSION" "${next_version}"
upsert_env "${ENV_FILE}" "SYSTEM_LATEST_VERSION" "${next_version}"
upsert_env "${ENV_FILE}" "SYSTEM_UPDATE_STATUS" "uptodate"
upsert_env "${ENV_FILE}" "SYSTEM_UPDATE_AVAILABLE" "false"

mkdir -p "$(dirname "${STATE_FILE}")"
cat > "${STATE_FILE}" <<EOF
LAST_DEPLOY_AT=$(date '+%Y-%m-%d %H:%M:%S')
LAST_COMMIT=${current_commit}
LAST_VERSION=${next_version}
LAST_ACTION=${action}
PREVIOUS_VERSION=${base_version}
PREVIOUS_DEPLOYED_VERSION=${last_version}
EOF

log "Versi deploy: ${next_version}"
log "Aksi: ${action}"
if [[ -n "${current_commit}" ]]; then
  log "Commit: ${current_commit}"
fi
log "State tersimpan: ${STATE_FILE}"
