#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

BUNDLE=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
APP_ROOT=/cloudclusters/arbk
ENV_DIR=/cloudclusters/arbk-env
ENV_FILE="$ENV_DIR/.env"
PHP_BIN=${PHP_BIN:-php}
WEB_USER=${WEB_USER:-www-data}
WEB_GROUP=${WEB_GROUP:-www-data}
COMMAND=${1:-help}
CREATE_DATABASE=${2:-}

die() { printf '%s\n' "$*" >&2; exit 1; }
usage() {
    cat <<'HELP'
Usage: bash deploy.sh COMMAND [--create-database]
  check             Validate package checksums, external environment and runtime
  deploy            Install release, import initial database, migrate, validate, activate
  install           Stage application and original XLSX files without activating
  database-import   Create tables/schema and import data into an EMPTY database
  database-verify   Compare database rows/content against the packaged snapshot
  migrate           Run pending application migrations in the staged release
  activate          Validate and switch the application to the staged release
    apache            Manage ARBK in the CloudClusters master vhost file
  help              Print this help

--create-database is optional with deploy/database-import. It needs CREATE DATABASE
permission. Otherwise provision an empty database in CloudClusters first.

Application: /cloudclusters/arbk
Environment: /cloudclusters/arbk-env/.env
PHP_BIN, WEB_USER and WEB_GROUP may be set in the shell. Default FPM socket in
100-arbk.conf is /var/run/php/php8.1-fpm.sock. The master Apache file is
/cloudclusters/config/apache/default.conf. PHP-FPM is restarted after activation.
HELP
}
[[ "$COMMAND" == help || "$COMMAND" == --help ]] && { usage; exit 0; }
case "$COMMAND" in check|deploy|install|database-import|database-verify|migrate|activate|apache) ;; *) usage; exit 1;; esac
[[ -z "$CREATE_DATABASE" || "$CREATE_DATABASE" == --create-database ]] || die 'Unknown option.'
if [[ -n "$CREATE_DATABASE" && "$COMMAND" != deploy && "$COMMAND" != database-import ]]; then die '--create-database is valid only with deploy/database-import.'; fi
[[ -f "$BUNDLE/RELEASE" && -f "$BUNDLE/SHA256SUMS" ]] || die 'Run this script from the extracted deployment bundle.'
VERSION=$(cat "$BUNDLE/RELEASE")
[[ "$VERSION" =~ ^(code-)?[0-9][A-Za-z0-9._-]*$ && "$VERSION" != *..* ]] || die 'Invalid release identifier.'
PACKAGE_TYPE=full
if [[ -f "$BUNDLE/PACKAGE-TYPE" ]]; then
    [[ "$(cat "$BUNDLE/PACKAGE-TYPE")" == code-only && ! -d "$BUNDLE/database" ]] || die 'Invalid code-only package contents.'
    PACKAGE_TYPE=code-only
fi
if [[ "$PACKAGE_TYPE" == full && "$VERSION" == code-* ]]; then die 'Full package has an invalid release identifier.'; fi
if [[ "$PACKAGE_TYPE" == code-only && "$VERSION" != code-* ]]; then die 'Code-only package has an invalid release identifier.'; fi
RELEASE="$APP_ROOT/releases/$VERSION"
export ARBK_ENV_FILE="$ENV_FILE"

check_package() {
    (cd "$BUNDLE" && sha256sum --check --quiet SHA256SUMS)
    if [[ "$PACKAGE_TYPE" == full ]]; then
        "$PHP_BIN" "$BUNDLE/app/bin/database-transfer.php" check "$BUNDLE/database"
    fi
}
check_environment() {
    [[ -f "$ENV_FILE" && -r "$ENV_FILE" ]] || die "Upload production settings to $ENV_FILE before deployment."
    "$PHP_BIN" "$BUNDLE/app/bin/deployment-check.php"
}
if [[ "$COMMAND" == check ]]; then check_package; check_environment; exit; fi
if [[ "$PACKAGE_TYPE" == code-only && ( "$COMMAND" == database-import || "$COMMAND" == database-verify || -n "$CREATE_DATABASE" ) ]]; then
    die 'Code-only releases cannot import or verify database snapshots.'
fi

mkdir -p "$APP_ROOT"
[[ "$(readlink -f "$APP_ROOT")" == "$APP_ROOT" ]] || die 'Application root must be its own directory, not a shortcut to another site.'
exec 9>"$APP_ROOT/.deploy.lock"
flock -n 9 || die 'Another ARBK deployment is running.'
check_package

stage() {
    check_environment
    id "$WEB_USER" >/dev/null
    getent group "$WEB_GROUP" >/dev/null
    # Existing releases and runtime directories must stay within this application.
    for path in "$APP_ROOT/releases" "$APP_ROOT/shared"; do
        [[ ! -L "$path" ]] || die "Unexpected symlink: $path"
    done
    for path in "$APP_ROOT/current" "$APP_ROOT/public"; do
        [[ ! -e "$path" || -L "$path" ]] || die "Refusing to replace existing directory/file: $path"
    done
    mkdir -p "$APP_ROOT/releases" "$APP_ROOT/shared/imports" "$APP_ROOT/shared/log" "$APP_ROOT/shared/documents"
    [[ ! -L "$RELEASE" ]] || die 'Release directory must not be a symlink.'
    for path in "$APP_ROOT/shared/imports" "$APP_ROOT/shared/log" "$APP_ROOT/shared/documents"; do
        [[ "$(readlink -f "$path")" == "$path" ]] || die "Unexpected runtime directory target: $path"
    done
    if [[ -d "$RELEASE" ]]; then
        [[ -f "$RELEASE/.bundle-sha256" ]] && cmp -s "$BUNDLE/SHA256SUMS" "$RELEASE/.bundle-sha256" || die 'Release identifier already exists with different or incomplete contents.'
    else
        [[ ! -e "$RELEASE" && ! -L "$RELEASE" ]] || die 'Unexpected release path.'
        local staging="$APP_ROOT/releases/.staging-$VERSION-$$"
        mkdir "$staging"
        cp -a "$BUNDLE/app/." "$staging/"
        if [[ ! -f "$staging/public/logo_prishtina.png" && -f "$APP_ROOT/current/public/logo_prishtina.png" ]]; then
            cp "$APP_ROOT/current/public/logo_prishtina.png" "$staging/public/logo_prishtina.png"
        fi
        # These directories in the app package contain no business data.
        [[ ! -L "$staging/var/imports" && ! -L "$staging/var/log" ]] || die 'Invalid runtime directories in package.'
        rm -f "$staging/var/imports/.gitkeep"
        rmdir "$staging/var/imports"
        rm -f "$staging/var/documents/.gitkeep"
        rmdir "$staging/var/documents"
        if [[ -d "$staging/var/log" ]]; then rmdir "$staging/var/log"; fi
        ln -s "$APP_ROOT/shared/imports" "$staging/var/imports"
        ln -s "$APP_ROOT/shared/log" "$staging/var/log"
        ln -s "$APP_ROOT/shared/documents" "$staging/var/documents"
        cp "$BUNDLE/SHA256SUMS" "$staging/.bundle-sha256"
        find "$staging" -type d -exec chmod 755 {} +
        find "$staging" -type f -exec chmod 644 {} +
        mv "$staging" "$RELEASE"
    fi
    if [[ "$PACKAGE_TYPE" == full ]]; then
        for source in "$BUNDLE"/database/files/*.xlsx; do
            [[ -f "$source" ]] || continue
            local target="$APP_ROOT/shared/imports/$(basename "$source")"
            [[ ! -L "$target" ]] || die 'Refusing an import workbook symlink.'
            if [[ -e "$target" ]]; then cmp -s "$source" "$target" || die 'Existing workbook differs from packaged file.'; else cp "$source" "$target"; fi
        done
    fi
    chmod 755 "$APP_ROOT" "$APP_ROOT/releases" "$APP_ROOT/shared"
    # setgid keeps CLI/cron files in the PHP-FPM group even when the worker runs as root.
    chmod 2770 "$APP_ROOT/shared/imports" "$APP_ROOT/shared/log" "$APP_ROOT/shared/documents"
    find "$APP_ROOT/shared/imports" -maxdepth 1 -type f -exec chmod 660 {} +
    if [[ $(id -u) -eq 0 ]]; then
        chown -R "$WEB_USER:$WEB_GROUP" "$APP_ROOT/shared"
        find "$APP_ROOT/shared/documents" -type d -exec chmod 2770 {} +
        find "$APP_ROOT/shared/documents" -type f -exec chmod 660 {} +
        chown "root:$WEB_GROUP" "$ENV_DIR" "$ENV_FILE"
        chmod 750 "$ENV_DIR"
        chmod 640 "$ENV_FILE"
        runuser -u "$WEB_USER" -- test -r "$ENV_FILE" || die 'PHP-FPM user cannot read the environment file.'
    else
        [[ $(id -un) == "$WEB_USER" ]] || die 'Run as root or the PHP-FPM user to establish file permissions.'
    fi
    printf 'Staged release: %s\n' "$RELEASE"
}
require_release() { [[ -f "$RELEASE/bin/migrate.php" ]] || die 'Run install first.'; check_environment; }
import_database() {
    require_release
    local options=()
    [[ -n "$CREATE_DATABASE" ]] && options+=(--create-database)
    "$PHP_BIN" "$RELEASE/bin/database-transfer.php" import "$BUNDLE/database" "${options[@]}"
}
verified_php() {
    local script=$1 label=$2 marker status marker_value
    marker=$(mktemp "$APP_ROOT/.verification-$VERSION.XXXXXXXX")
    set +e
    ARBK_COMPLETION_FILE="$marker" "$PHP_BIN" "$script"
    status=$?
    set -e
    marker_value=$(cat "$marker")
    rm -f "$marker"
    [[ "$marker_value" == complete ]] || die "$label did not reach its verified completion point (PHP exit $status)."
    if [[ "$status" -eq 0 ]]; then return; fi
    if [[ "$status" -eq 134 ]]; then
        printf 'WARNING: %s completed, but CloudClusters PHP aborted during pdo_sqlsrv shutdown; continuing after marker verification.\n' "$label" >&2
        return
    fi
    die "$label completed its checks but PHP exited unexpectedly with status $status."
}
migrate() { require_release; verified_php "$RELEASE/bin/migrate.php" 'Database migration'; }
activate() {
    require_release
    verified_php "$RELEASE/bin/preflight-production.php" 'Production preflight'
    [[ ! -e "$APP_ROOT/current" || -L "$APP_ROOT/current" ]] || die 'current is not a symlink.'
    [[ ! -e "$APP_ROOT/public" || -L "$APP_ROOT/public" ]] || die 'public is not a symlink.'
    if [[ -L "$APP_ROOT/public" ]]; then
        [[ "$(readlink "$APP_ROOT/public")" == "$APP_ROOT/current/public" ]] || die 'Existing public symlink has an unexpected target.'
    fi
    local next="$APP_ROOT/.current-$VERSION-$$"
    ln -s "$RELEASE" "$next"
    mv -Tf "$next" "$APP_ROOT/current"
    if [[ ! -L "$APP_ROOT/public" ]]; then ln -s "$APP_ROOT/current/public" "$APP_ROOT/public"; fi
    if command -v supervisorctl >/dev/null 2>&1 && supervisorctl status php-fpm >/dev/null 2>&1; then
        supervisorctl restart php-fpm >/dev/null || die 'Release activated, but PHP-FPM could not be restarted to clear OPcache.'
    else
        die 'Release activated, but the CloudClusters php-fpm supervisor service was not found; restart PHP-FPM manually.'
    fi
    printf 'Activated %s. DocumentRoot: %s/public\n' "$VERSION" "$APP_ROOT"
}
apache() {
    [[ $(id -u) -eq 0 ]] || die 'Apache configuration requires root.'
    [[ -d "$APP_ROOT/public" ]] || die 'Deploy and activate the app first.'
    [[ -S /var/run/php/php8.1-fpm.sock ]] || die 'Expected PHP 8.1 FPM socket is absent; select the correct socket before enabling this site.'
    local conf=/cloudclusters/config/apache/default.conf
    local replacement backup changed=0
    [[ -f "$conf" && ! -L "$conf" ]] || die "Expected persistent CloudClusters Apache file: $conf"
    grep -Fq 'ServerName arbk.kryeqyteti.net' "$BUNDLE/100-arbk.conf" || die 'Packaged ARBK vhost has no expected ServerName.'
    grep -Fq 'DocumentRoot /cloudclusters/arbk/public' "$BUNDLE/100-arbk.conf" || die 'Packaged ARBK vhost has an unexpected DocumentRoot.'
    apache2ctl configtest || die 'Existing Apache configuration is invalid; no changes made.'
    replacement=$(mktemp "$APP_ROOT/.apache-replacement.XXXXXXXX")
    backup="$conf.arbk-backup-$$"
    awk -v replacement="$BUNDLE/100-arbk.conf" -f "$BUNDLE/manage-apache-vhosts.awk" "$conf" > "$replacement" || { rm -f "$replacement"; die 'Could not safely parse existing virtual hosts.'; }
    if ! cmp -s "$conf" "$replacement"; then
        cp -p "$conf" "$backup"
        chmod --reference="$conf" "$replacement"
        chown --reference="$conf" "$replacement"
        mv -f "$replacement" "$conf"
        changed=1
        if ! apache2ctl configtest; then
            mv -f "$backup" "$conf"
            die 'Apache syntax check failed; master vhost file was restored.'
        fi
        printf '%s\n' 'CloudClusters master vhost file updated; non-ARBK content was preserved and duplicate ARBK blocks removed.'
    else
        rm -f "$replacement"
        printf '%s\n' 'CloudClusters master vhost file already has one canonical ARBK block.'
    fi
    if ! supervisorctl restart apache; then
        if [[ "$changed" -eq 1 ]]; then mv -f "$backup" "$conf"; apache2ctl configtest >/dev/null 2>&1 && supervisorctl restart apache >/dev/null 2>&1 || true; fi
        die 'Apache could not restart; prior master configuration was restored when changed.'
    fi
    [[ "$changed" -eq 0 ]] || rm -f "$backup"
    printf '%s\n' 'Apache restarted. Existing virtual hosts and default_site were preserved.'
}

case "$COMMAND" in
    install) stage;;
    database-import) import_database;;
    database-verify) require_release; "$PHP_BIN" "$RELEASE/bin/database-transfer.php" verify "$BUNDLE/database";;
    migrate) migrate;;
    activate) activate;;
    apache) apache;;
    deploy)
        stage
        if [[ "$PACKAGE_TYPE" == full ]]; then import_database; fi
        migrate
        activate
        ;;
esac
