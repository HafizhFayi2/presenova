#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
  cat <<'EOF'
Setup DeepFace environment for Presenova.

Usage:
  bash scripts/setup_deepface.sh [options]

Options:
  --project-root <path>      Project root path (default: auto from script location)
  --python <binary>          Python binary to create venv (default: python3)
  --venv <relative-path>     Venv path relative to project root (default: public/face/.venv)
  --requirements <path>      Requirements path relative to project root (default: public/face/faces_conf/requirements.txt)
  --write-env                Update .env PYTHON_BIN to the venv python path
  --install-system-deps      Install Debian/Ubuntu system deps for DeepFace/OpenCV
  -h, --help                 Show help

Examples:
  bash scripts/setup_deepface.sh --write-env
  bash /var/www/presenova/scripts/setup_deepface.sh --project-root /var/www/presenova --write-env
EOF
}

log() {
  printf '[setup-deepface] %s\n' "$*"
}

die() {
  printf '[setup-deepface] ERROR: %s\n' "$*" >&2
  exit 1
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "Command not found: $1"
}

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
PROJECT_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd -P)"
PYTHON_BIN="python3"
VENV_REL="public/face/.venv"
REQ_REL="public/face/faces_conf/requirements.txt"
WRITE_ENV=0
INSTALL_SYSTEM_DEPS=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --project-root)
      [[ $# -ge 2 ]] || die "--project-root membutuhkan nilai"
      PROJECT_ROOT="$2"
      shift 2
      ;;
    --python)
      [[ $# -ge 2 ]] || die "--python membutuhkan nilai"
      PYTHON_BIN="$2"
      shift 2
      ;;
    --venv)
      [[ $# -ge 2 ]] || die "--venv membutuhkan nilai"
      VENV_REL="$2"
      shift 2
      ;;
    --requirements)
      [[ $# -ge 2 ]] || die "--requirements membutuhkan nilai"
      REQ_REL="$2"
      shift 2
      ;;
    --write-env)
      WRITE_ENV=1
      shift
      ;;
    --install-system-deps)
      INSTALL_SYSTEM_DEPS=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      die "Argumen tidak dikenal: $1"
      ;;
  esac
done

PROJECT_ROOT="$(cd -- "${PROJECT_ROOT}" && pwd -P)"
VENV_PATH="${PROJECT_ROOT}/${VENV_REL}"
REQ_PATH="${PROJECT_ROOT}/${REQ_REL}"
ENV_FILE="${PROJECT_ROOT}/.env"
ENV_EXAMPLE_FILE="${PROJECT_ROOT}/.env.example"

[[ -f "${REQ_PATH}" ]] || die "requirements.txt tidak ditemukan: ${REQ_PATH}"

if [[ "${INSTALL_SYSTEM_DEPS}" -eq 1 ]]; then
  need_cmd apt-get
  if [[ "$(id -u)" -eq 0 ]]; then
    APT_RUN=()
  elif command -v sudo >/dev/null 2>&1; then
    APT_RUN=(sudo)
  else
    die "Perlu root/sudo untuk --install-system-deps"
  fi

  log "Install system dependencies (Debian/Ubuntu)..."
  "${APT_RUN[@]}" apt-get update
  "${APT_RUN[@]}" apt-get install -y \
    python3 \
    python3-venv \
    python3-pip \
    libglib2.0-0 \
    libgl1 \
    libsm6 \
    libxext6 \
    libxrender1 \
    ffmpeg
fi

need_cmd "${PYTHON_BIN}"

log "Project root: ${PROJECT_ROOT}"
log "Create/update venv: ${VENV_PATH}"
"${PYTHON_BIN}" -m venv "${VENV_PATH}"

if [[ -x "${VENV_PATH}/bin/python" ]]; then
  VENV_PYTHON="${VENV_PATH}/bin/python"
elif [[ -x "${VENV_PATH}/Scripts/python.exe" ]]; then
  VENV_PYTHON="${VENV_PATH}/Scripts/python.exe"
else
  die "Python di venv tidak ditemukan"
fi

log "Upgrade pip/setuptools/wheel..."
"${VENV_PYTHON}" -m pip install --upgrade pip setuptools wheel

log "Install requirements..."
"${VENV_PYTHON}" -m pip install -r "${REQ_PATH}"

if [[ "${WRITE_ENV}" -eq 1 ]]; then
  if [[ ! -f "${ENV_FILE}" ]]; then
    [[ -f "${ENV_EXAMPLE_FILE}" ]] || die ".env.example tidak ditemukan"
    cp "${ENV_EXAMPLE_FILE}" "${ENV_FILE}"
    log "Membuat .env dari .env.example"
  fi

  if grep -q '^PYTHON_BIN=' "${ENV_FILE}"; then
    sed -i "s#^PYTHON_BIN=.*#PYTHON_BIN=${VENV_PYTHON}#g" "${ENV_FILE}"
  else
    printf '\nPYTHON_BIN=%s\n' "${VENV_PYTHON}" >> "${ENV_FILE}"
  fi
  log "Update .env: PYTHON_BIN=${VENV_PYTHON}"
fi

log "Verifikasi import DeepFace..."
"${VENV_PYTHON}" - <<'PY'
from deepface import DeepFace  # noqa: F401
print("DeepFace import OK")
PY

cat <<EOF

Selesai.

Python venv:
  ${VENV_PYTHON}

Jalankan app dengan env ini:
  PYTHON_BIN=${VENV_PYTHON}

Contoh run dari project root:
  bash scripts/setup_deepface.sh --write-env

Contoh run dari folder mana pun:
  bash ${PROJECT_ROOT}/scripts/setup_deepface.sh --write-env
EOF
