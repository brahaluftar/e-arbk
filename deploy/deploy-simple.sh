#!/usr/bin/env bash
set -euo pipefail

VERSION="${1:?Usage: bash deploy-simple.sh VERSION}"
ROOT="${ROOT:-/cloudclusters}"
ZIP="${ROOT}/e-arbk-code-${VERSION}.zip"

cd "${ROOT}"
sha256sum -c "${ZIP}.sha256"
chmod +x "${ROOT}/deploy-arbk.sh"
bash "${ROOT}/deploy-arbk.sh" "${ZIP}" deploy

if [[ -f "${ROOT}/install-cron.sh" ]]; then
  if [[ "${EUID}" -eq 0 ]]; then
    bash "${ROOT}/install-cron.sh"
  elif command -v sudo >/dev/null 2>&1; then
    sudo bash "${ROOT}/install-cron.sh"
  else
    echo "Deploy completed, but cron requires root: bash ${ROOT}/install-cron.sh" >&2
  fi
fi

echo "Deployment completed: ${VERSION}"
