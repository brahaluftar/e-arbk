#!/usr/bin/env bash
set -euo pipefail

CRON_FILE="/etc/cron.d/e-arbk"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
APP_ROOT="${APP_ROOT:-/cloudclusters/arbk/current}"
SHARED_ROOT="${SHARED_ROOT:-/cloudclusters/arbk/shared}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

install -d -o "${WEB_USER}" -g "${WEB_GROUP}" -m 0770 "${SHARED_ROOT}/log"
cat > "${CRON_FILE}" <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * ${WEB_USER} cd ${APP_ROOT} && /usr/bin/flock -n ${SHARED_ROOT}/imports.lock ${PHP_BIN} bin/process-imports.php >> ${SHARED_ROOT}/log/imports.log 2>&1
* * * * * ${WEB_USER} cd ${APP_ROOT} && /usr/bin/flock -n ${SHARED_ROOT}/annual-invoices.lock ${PHP_BIN} bin/process-annual-invoices.php 200 >> ${SHARED_ROOT}/log/annual-invoices.log 2>&1
*/15 * * * * ${WEB_USER} cd ${APP_ROOT} && /usr/bin/flock -n ${SHARED_ROOT}/scheduled.lock ${PHP_BIN} bin/run-scheduled.php >> ${SHARED_ROOT}/log/scheduled.log 2>&1
EOF
chmod 0644 "${CRON_FILE}"
echo "Cron installed: ${CRON_FILE}"
