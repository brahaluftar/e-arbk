#!/usr/bin/env bash
# Deploys e-ARBK directly from GitHub on the CloudClusters Ubuntu server.
#
#   sudo GITHUB_TOKEN=... bash deploy-from-github.sh deploy [--ref main]
#
# Commands: check | deploy | status | rollback [RELEASE] | apache | help   (details: help)
#
# Layout (same as deploy.sh):
#   /cloudclusters/arbk/releases/git-<UTC time>-<commit>   one directory per deployment
#   /cloudclusters/arbk/current -> releases/...            active release (atomic switch)
#   /cloudclusters/arbk/public  -> current/public          Apache DocumentRoot
#   /cloudclusters/arbk/shared/{imports,log,documents}     data kept between releases
#   /cloudclusters/arbk-env/.env                           production settings (never in Git)
set -Eeuo pipefail
umask 027

REPO_URL=${REPO_URL:-https://github.com/brahaluftar/e-arbk.git}
REF=${REF:-main}
APP_ROOT=${APP_ROOT:-/cloudclusters/arbk}
ENV_DIR=${ENV_DIR:-/cloudclusters/arbk-env}
ENV_FILE=${ENV_FILE:-$ENV_DIR/.env}
DEPLOY_ROOT=${DEPLOY_ROOT:-/cloudclusters/arbk-deployments}
PHP_BIN=${PHP_BIN:-php}
COMPOSER_BIN=${COMPOSER_BIN:-}
COMPOSER_FLAGS=${COMPOSER_FLAGS:-}
WEB_USER=${WEB_USER:-www-data}
WEB_GROUP=${WEB_GROUP:-www-data}
KEEP_RELEASES=${KEEP_RELEASES:-5}
HEALTH_URL=${HEALTH_URL:-}
TOKEN_FILE=${TOKEN_FILE:-$ENV_DIR/github-token}
read -r -a REQUIRED_EXTENSIONS <<<"${PHP_EXTENSIONS:-pdo pdo_sqlsrv mbstring openssl gd zip xmlreader xmlwriter simplexml zlib iconv}"

COMMAND=${1:-help}; shift || true
FORCE=0; SKIP_HEALTH=0; SKIP_CRON=0; ROLLBACK_TARGET=''
while [[ $# -gt 0 ]]; do
    case "$1" in
        --ref) REF=${2:?--ref needs a branch, tag or commit}; shift 2;;
        --repo) REPO_URL=${2:?--repo needs a URL}; shift 2;;
        --force) FORCE=1; shift;;
        --skip-health) SKIP_HEALTH=1; shift;;
        --no-cron) SKIP_CRON=1; shift;;
        -*) printf 'Unknown option: %s\n' "$1" >&2; exit 1;;
        *) ROLLBACK_TARGET=$1; shift;;
    esac
done

MIRROR="$DEPLOY_ROOT/e-arbk.git"
RELEASES="$APP_ROOT/releases"
SHARED="$APP_ROOT/shared"

log() { printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

usage() {
    cat <<'HELP'
Usage: bash deploy-from-github.sh COMMAND [options]

Commands
  check              Verify server prerequisites (PHP, extensions, git, composer, .env)
  build              Fetch and build a release only (no migration, no switch) - a dry run
  deploy             Fetch the ref from GitHub, build a release, back up (sqlserver mode),
                     migrate, run preflight, switch the release, restart PHP-FPM,
                     install cron, check /health.php (rolls back the code if unhealthy)
  status             Show the active release and the available releases
  rollback [NAME]    Switch back to the previous release (or NAME); migrations are NOT undone
  apache             Install/refresh the ARBK virtual host in the CloudClusters Apache file
  help               This text

Options
  --ref REF          Branch, tag or commit to deploy (default: main)
  --repo URL         Repository URL (default: https://github.com/brahaluftar/e-arbk.git)
  --force            Deploy even if the commit is already active
  --skip-health      Do not check /health.php after the switch
  --no-cron          Do not (re)install /etc/cron.d/e-arbk

Private repository access (first match wins)
  GITHUB_TOKEN env   Fine-grained token with read-only "Contents" access to the repository
  /cloudclusters/arbk-env/github-token   same token in a root-only file (chmod 600)
  SSH deploy key     use --repo git@github.com:brahaluftar/e-arbk.git

Environment overrides: APP_ROOT ENV_DIR ENV_FILE DEPLOY_ROOT PHP_BIN COMPOSER_BIN COMPOSER_FLAGS
                       WEB_USER WEB_GROUP KEEP_RELEASES HEALTH_URL REPO_URL REF PHP_EXTENSIONS
HELP
}

# ---------------------------------------------------------------- helpers

require_root() { [[ $(id -u) -eq 0 ]] || die 'Run as root (sudo), so file ownership and PHP-FPM can be managed.'; }

env_value() { # reads KEY from the production .env without sourcing it
    local key=$1
    [[ -r "$ENV_FILE" ]] || return 0
    sed -n "s/^[[:space:]]*$key[[:space:]]*=[[:space:]]*//p" "$ENV_FILE" | tail -n1 | sed -e 's/^["'\'']//' -e 's/["'\'']$//' -e 's/[[:space:]]*$//'
}

git_auth() { # runs git with read-only credentials, without writing the token to disk or the remote URL
    local token=${GITHUB_TOKEN:-}
    if [[ -z "$token" && -r "$TOKEN_FILE" ]]; then token=$(tr -d '[:space:]' < "$TOKEN_FILE"); fi
    if [[ -n "$token" && "$REPO_URL" == https://* ]]; then
        local helper
        helper=$(mktemp "$DEPLOY_ROOT/.askpass.XXXXXXXX")
        printf '#!/bin/sh\ncase "$1" in Username*) echo x-access-token;; *) printf "%%s\\n" "$ARBK_GIT_TOKEN";; esac\n' > "$helper"
        chmod 700 "$helper"
        local status=0
        ARBK_GIT_TOKEN=$token GIT_ASKPASS=$helper GIT_TERMINAL_PROMPT=0 git "$@" || status=$?
        rm -f "$helper"
        return "$status"
    fi
    GIT_TERMINAL_PROMPT=0 git "$@"
}

composer_cmd() {
    if [[ -n "$COMPOSER_BIN" ]]; then printf '%s' "$COMPOSER_BIN"; return; fi
    if command -v composer >/dev/null 2>&1; then command -v composer; return; fi
    printf '%s' "$DEPLOY_ROOT/composer.phar"
}

ensure_composer() {
    local composer
    composer=$(composer_cmd)
    if [[ -f "$composer" ]] && "$PHP_BIN" "$composer" --version >/dev/null 2>&1; then return; fi
    log 'Composer not found; installing it into the deployment directory (signature verified).'
    local expected actual installer="$DEPLOY_ROOT/composer-setup.php"
    expected=$(curl -fsSL https://composer.github.io/installer.sig)
    curl -fsSL https://getcomposer.org/installer -o "$installer"
    actual=$("$PHP_BIN" -r "echo hash_file('sha384', '$installer');")
    [[ -n "$expected" && "$expected" == "$actual" ]] || { rm -f "$installer"; die 'Composer installer signature mismatch.'; }
    "$PHP_BIN" "$installer" --quiet --install-dir="$DEPLOY_ROOT" --filename=composer.phar
    rm -f "$installer"
}

run_composer() {
    local composer
    composer=$(composer_cmd)
    COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_HOME="$DEPLOY_ROOT/composer-home" COMPOSER_CACHE_DIR="$DEPLOY_ROOT/composer-cache" "$PHP_BIN" "$composer" "$@"
}

# Runs a PHP CLI script that writes a completion marker; tolerates the known pdo_sqlsrv abort (exit 134)
# at PHP shutdown on CloudClusters once the marker proves the work finished.
verified_php() {
    local release=$1 script=$2 label=$3 marker status=0
    marker=$(mktemp "$DEPLOY_ROOT/.verification.XXXXXXXX")
    ARBK_ENV_FILE="$ENV_FILE" ARBK_COMPLETION_FILE="$marker" "$PHP_BIN" "$release/$script" || status=$?
    local value
    value=$(cat "$marker" 2>/dev/null || true)
    rm -f "$marker"
    [[ "$value" == complete ]] || die "$label did not complete (PHP exit $status)."
    [[ $status -eq 0 || $status -eq 134 ]] || die "$label finished its checks but PHP exited with status $status."
    [[ $status -eq 0 ]] || log "WARNING: $label completed; PHP aborted during pdo_sqlsrv shutdown (known CloudClusters issue)."
}

restart_php_fpm() {
    if command -v supervisorctl >/dev/null 2>&1 && supervisorctl status php-fpm >/dev/null 2>&1; then
        supervisorctl restart php-fpm >/dev/null && return
    fi
    if command -v systemctl >/dev/null 2>&1; then
        local service
        service=$(systemctl list-units --type=service --all --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}' | head -n1)
        if [[ -n "$service" ]]; then systemctl reload-or-restart "$service" && return; fi
    fi
    log 'WARNING: PHP-FPM restart not found (supervisor php-fpm or systemd php*-fpm); restart it manually to clear OPcache.'
}

switch_current() {
    local release=$1 next="$APP_ROOT/.current-$$"
    [[ ! -e "$APP_ROOT/current" || -L "$APP_ROOT/current" ]] || die "$APP_ROOT/current exists and is not a symlink."
    [[ ! -e "$APP_ROOT/public" || -L "$APP_ROOT/public" ]] || die "$APP_ROOT/public exists and is not a symlink."
    ln -sfn "$release" "$next"
    mv -Tf "$next" "$APP_ROOT/current"
    [[ -L "$APP_ROOT/public" ]] || ln -s "$APP_ROOT/current/public" "$APP_ROOT/public"
    restart_php_fpm
}

current_release() { [[ -L "$APP_ROOT/current" ]] && readlink -f "$APP_ROOT/current" || true; }

health_check() {
    local url=$HEALTH_URL host
    host=$(env_value APP_URL | sed -E 's#^https?://([^/:]+).*#\1#')
    if [[ -z "$url" ]]; then url='http://127.0.0.1/health.php'; fi
    # health.php does not redirect to HTTPS, so the local Apache answers directly for the ARBK virtual host.
    local attempt body
    for attempt in 1 2 3 4 5; do
        if body=$(curl -fsS --max-time 10 -H "Host: ${host:-localhost}" "$url" 2>/dev/null) && [[ "$body" == *'"status":"ok"'* ]]; then
            log "Health check passed: $body"
            return 0
        fi
        sleep 3
    done
    return 1
}

# ---------------------------------------------------------------- commands

cmd_check() {
    local problems=0
    command -v git >/dev/null 2>&1 || { echo 'MISSING: git (apt-get install -y git)'; problems=1; }
    command -v curl >/dev/null 2>&1 || { echo 'MISSING: curl'; problems=1; }
    command -v flock >/dev/null 2>&1 || { echo 'MISSING: flock (util-linux)'; problems=1; }
    command -v tar >/dev/null 2>&1 || { echo 'MISSING: tar'; problems=1; }
    if ! command -v "$PHP_BIN" >/dev/null 2>&1; then echo "MISSING: PHP CLI ($PHP_BIN)"; problems=1
    else
        "$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || { echo "PHP 8.1+ required, found $("$PHP_BIN" -r 'echo PHP_VERSION;')"; problems=1; }
        local modules extension
        modules=$("$PHP_BIN" -m | tr '[:upper:]' '[:lower:]')
        for extension in "${REQUIRED_EXTENSIONS[@]}"; do grep -qx "$extension" <<<"$modules" || { echo "MISSING PHP extension: $extension"; problems=1; }; done
        [[ "$("$PHP_BIN" -r 'echo ini_get("allow_url_fopen");')" == 1 ]] || { echo 'allow_url_fopen must be On (Microsoft Graph email)'; problems=1; }
    fi
    [[ -f "$ENV_FILE" ]] || { echo "MISSING: $ENV_FILE (copy .env.production.example and fill it in)"; problems=1; }
    id "$WEB_USER" >/dev/null 2>&1 || { echo "MISSING: user $WEB_USER (set WEB_USER)"; problems=1; }
    if [[ $problems -ne 0 ]]; then die 'Prerequisites are missing (see above).'; fi
    echo "OK: PHP $("$PHP_BIN" -r 'echo PHP_VERSION;'), extensions, git, environment file."
}

cmd_status() {
    local active
    active=$(current_release)
    echo "Active release: ${active:-none}"
    [[ -n "$active" && -f "$active/REVISION" ]] && sed 's/^/  /' "$active/REVISION"
    echo 'Releases (newest first):'
    ls -1dt "$RELEASES"/*/ 2>/dev/null | sed 's#/$##' | while read -r dir; do
        printf '  %s%s\n' "$(basename "$dir")" "$([[ "$dir" == "$active" ]] && echo '  <- active')"
    done
}

stage_release() {
    local commit=$1 name staging
    name="git-$(date -u +%Y%m%d%H%M%S)-${commit:0:10}"
    staging="$RELEASES/.staging-$name"
    RELEASE="$RELEASES/$name"
    rm -rf "$RELEASES"/.staging-*   # leftovers of an interrupted build (the deploy lock is held)
    mkdir -p "$staging"
    git --git-dir="$MIRROR" archive --format=tar "$commit" | tar -x -C "$staging"
    [[ ! -e "$staging/config/.env" && ! -e "$staging/.env" ]] || die 'The repository contains an .env file; refusing to deploy it.'
    printf 'commit=%s\nref=%s\nrepository=%s\ndeployed_at=%s\n' "$commit" "$REF" "${REPO_URL%%\?*}" "$(date -u +%FT%TZ)" > "$staging/REVISION"

    log 'Installing PHP dependencies (composer, no dev packages).'
    # shellcheck disable=SC2086 # COMPOSER_FLAGS holds extra options on purpose
    run_composer install --working-dir="$staging" --no-dev --prefer-dist --no-interaction --no-progress --classmap-authoritative $COMPOSER_FLAGS

    # Runtime data lives in shared/ and survives releases.
    mkdir -p "$SHARED/imports" "$SHARED/log" "$SHARED/documents"
    local dir
    for dir in imports log documents; do
        rm -rf "${staging:?}/var/$dir"
        ln -s "$SHARED/$dir" "$staging/var/$dir"
    done
    find "$staging" -type d -exec chmod 755 {} +
    find "$staging" -type f -exec chmod 644 {} +
    find "$staging/deploy" -name '*.sh' -exec chmod 755 {} +
    chown -R root:root "$staging"
    chmod 2770 "$SHARED/imports" "$SHARED/log" "$SHARED/documents"
    chown -R "$WEB_USER:$WEB_GROUP" "$SHARED"
    mv "$staging" "$RELEASE"
    log "Release staged: $RELEASE"
}

backup_database() {
    local mode
    mode=$(env_value BACKUP_MODE)
    if [[ "${mode:-managed}" == sqlserver ]]; then
        log 'BACKUP_MODE=sqlserver: creating and verifying a database backup before migrating.'
        ARBK_ENV_FILE="$ENV_FILE" "$PHP_BIN" "$RELEASE/bin/backup-database.php"
    else
        log 'BACKUP_MODE=managed: make sure a recent CloudClusters backup exists before migrations run.'
    fi
}

prune_releases() {
    local active keep=$KEEP_RELEASES count=0 dir
    active=$(current_release)
    while read -r dir; do
        dir=${dir%/}
        [[ "$(basename "$dir")" == git-* ]] || continue
        count=$((count + 1))
        [[ $count -le $keep || "$dir" == "$active" ]] && continue
        log "Removing old release $(basename "$dir")"
        rm -rf "$dir"
    done < <(ls -1dt "$RELEASES"/*/ 2>/dev/null)
}

prepare() {
    require_root
    cmd_check
    mkdir -p "$DEPLOY_ROOT" "$RELEASES"
    chmod 700 "$DEPLOY_ROOT"
    exec 9>"$DEPLOY_ROOT/.deploy.lock"
    flock -n 9 || die 'Another deployment is running.'
    # Settings readable by PHP-FPM and cron, nobody else.
    chown "root:$WEB_GROUP" "$ENV_DIR" "$ENV_FILE"
    chmod 750 "$ENV_DIR"; chmod 640 "$ENV_FILE"
    [[ ! -f "$TOKEN_FILE" ]] || { chown root:root "$TOKEN_FILE"; chmod 600 "$TOKEN_FILE"; }
}

fetch_commit() {
    log "Fetching $REF from ${REPO_URL%%\?*}"
    if [[ -d "$MIRROR" ]]; then
        git --git-dir="$MIRROR" remote set-url origin "$REPO_URL"
        git_auth --git-dir="$MIRROR" fetch --prune origin
    else
        git_auth clone --mirror "$REPO_URL" "$MIRROR"
    fi
    COMMIT=$(git --git-dir="$MIRROR" rev-parse --verify "$REF^{commit}" 2>/dev/null) || die "Ref not found: $REF"
    log "Commit $COMMIT: $(git --git-dir="$MIRROR" log -1 --format='%s (%an, %cs)' "$COMMIT")"
}

# Fetch and build a release without touching the database or the active release.
cmd_build() {
    prepare
    fetch_commit
    ensure_composer
    stage_release "$COMMIT"
    log "Built only. Activate later with: deploy --ref $COMMIT (the build is reused only by rollback; deploy builds again)."
}

cmd_deploy() {
    prepare
    fetch_commit
    local commit=$COMMIT active
    active=$(current_release)
    if [[ $FORCE -eq 0 && -n "$active" && -f "$active/REVISION" ]] && grep -qx "commit=$commit" "$active/REVISION"; then
        log 'This commit is already active. Use --force to redeploy it.'
        return 0
    fi

    ensure_composer
    stage_release "$commit"
    backup_database
    log 'Running database migrations.'
    verified_php "$RELEASE" bin/migrate.php 'Database migration'
    log 'Running production preflight.'
    verified_php "$RELEASE" bin/preflight-production.php 'Production preflight'

    local previous=$active
    log 'Switching to the new release.'
    switch_current "$RELEASE"

    if [[ $SKIP_CRON -eq 0 ]]; then
        if [[ -d /etc/cron.d ]]; then
            APP_ROOT="$APP_ROOT/current" SHARED_ROOT="$SHARED" PHP_BIN="$(command -v "$PHP_BIN")" WEB_USER="$WEB_USER" WEB_GROUP="$WEB_GROUP" bash "$RELEASE/deploy/install-cron.sh"
        else
            log 'WARNING: /etc/cron.d not found; schedule the workers manually (deploy/cron.example).'
        fi
    fi

    if [[ $SKIP_HEALTH -eq 0 ]] && ! health_check; then
        if [[ -n "$previous" ]]; then
            log "Health check failed; switching the code back to $(basename "$previous"). Database migrations stay applied."
            switch_current "$previous"
        fi
        die 'Health check failed. Inspect the Apache/PHP error logs and /health.php.'
    fi
    prune_releases
    log "Deployed $(basename "$RELEASE") (commit ${commit:0:10})."
}

cmd_rollback() {
    require_root
    exec 9>"$DEPLOY_ROOT/.deploy.lock"
    flock -n 9 || die 'A deployment is running.'
    local active target
    active=$(current_release)
    if [[ -n "$ROLLBACK_TARGET" ]]; then
        target="$RELEASES/$ROLLBACK_TARGET"
    else
        target=$(ls -1dt "$RELEASES"/*/ 2>/dev/null | sed 's#/$##' | grep -vx "$active" | grep -v '/\.staging-' | head -n1 || true)
    fi
    [[ -n "$target" && -d "$target" && -f "$target/bin/migrate.php" ]] || die 'No release to roll back to.'
    log "Switching from $(basename "${active:-none}") to $(basename "$target"). Database migrations are NOT reverted."
    switch_current "$target"
    log 'Rollback done.'
}

cmd_apache() {
    require_root
    local release conf=/cloudclusters/config/apache/default.conf replacement backup
    release=$(current_release)
    [[ -n "$release" ]] || die 'Deploy a release first.'
    [[ -f "$conf" && ! -L "$conf" ]] || die "Expected the CloudClusters Apache file: $conf"
    apache2ctl configtest || die 'The current Apache configuration is invalid; nothing changed.'
    replacement=$(mktemp "$DEPLOY_ROOT/.apache.XXXXXXXX")
    backup="$conf.arbk-backup-$$"
    awk -v replacement="$release/deploy/100-arbk.conf" -f "$release/deploy/manage-apache-vhosts.awk" "$conf" > "$replacement" || { rm -f "$replacement"; die 'Could not parse the virtual hosts.'; }
    if cmp -s "$conf" "$replacement"; then rm -f "$replacement"; log 'Apache already has the ARBK virtual host.'; return; fi
    cp -p "$conf" "$backup"
    chmod --reference="$conf" "$replacement"; chown --reference="$conf" "$replacement"
    mv -f "$replacement" "$conf"
    if ! apache2ctl configtest; then mv -f "$backup" "$conf"; die 'Apache syntax check failed; the previous file was restored.'; fi
    supervisorctl restart apache >/dev/null 2>&1 || systemctl reload apache2 || { mv -f "$backup" "$conf"; die 'Apache could not restart; the previous file was restored.'; }
    rm -f "$backup"
    log 'ARBK virtual host installed; other sites were preserved.'
}

case "$COMMAND" in
    check) cmd_check;;
    build) cmd_build;;
    deploy) cmd_deploy;;
    status) cmd_status;;
    rollback) cmd_rollback;;
    apache) cmd_apache;;
    help|--help|-h) usage;;
    *) usage; exit 1;;
esac
