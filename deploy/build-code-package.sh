#!/usr/bin/env bash
# Builds the code-only deployment package on Linux; same format as build-code-package.ps1.
#
#   bash deploy/build-code-package.sh VERSION        e.g. 2026.10.22
#
# Output in dist/: e-arbk-code-VERSION.zip, its .sha256, and the server launchers
# (deploy-arbk.sh, deploy-simple.sh, install-cron.sh, deploy-from-github.sh).
# Install on the server with:  bash /cloudclusters/deploy-simple.sh VERSION
set -Eeuo pipefail

VERSION=${1:-}
[[ "$VERSION" =~ ^[0-9][A-Za-z0-9._-]+$ && "$VERSION" != *..* ]] || { echo 'Usage: bash deploy/build-code-package.sh VERSION (e.g. 2026.10.22)' >&2; exit 1; }
ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)
NAME="e-arbk-code-$VERSION"
DIST="$ROOT/dist"
BUNDLE="$DIST/$NAME"
ARCHIVE="$DIST/$NAME.zip"
APP="$BUNDLE/app"
PHP_BIN=${PHP_BIN:-php}
COMPOSER_BIN=${COMPOSER_BIN:-$(command -v composer || true)}
# The build machine does not need the production-only extensions; preflight checks them on the server.
COMPOSER_FLAGS=${COMPOSER_FLAGS:---ignore-platform-req=ext-gd --ignore-platform-req=ext-pdo_sqlsrv}

[[ -n "$COMPOSER_BIN" ]] || { echo 'Composer is required (set COMPOSER_BIN).' >&2; exit 1; }
for tool in python3 sha256sum; do command -v "$tool" >/dev/null || { echo "Missing tool: $tool" >&2; exit 1; }; done
[[ ! -e "$BUNDLE" && ! -e "$ARCHIVE" ]] || { echo 'Use a new release identifier (dist/ already has it).' >&2; exit 1; }
if [[ -n "$(git -C "$ROOT" status --porcelain 2>/dev/null)" ]]; then echo 'WARNING: the working tree has uncommitted changes; they are packaged too.' >&2; fi

mkdir -p "$APP/database" "$APP/var/imports" "$APP/var/documents" "$APP/docs"
for directory in bin config public resources src templates; do cp -a "$ROOT/$directory" "$APP/"; done
# Environment files are machine-specific secrets and must never enter a release.
find "$APP" -name '.env' -type f -delete
cp -a "$ROOT/database/migrations" "$APP/database/"
for file in bootstrap.php index.php composer.json composer.lock README.md .htaccess .env.production.example; do cp -a "$ROOT/$file" "$APP/"; done
: > "$APP/var/imports/.gitkeep"
: > "$APP/var/documents/.gitkeep"
for doc in production-deployment.md deployment-bundle.md deploy-from-github.md release-notes-2026-10.md LLD-e-arbk.md; do
    [[ -f "$ROOT/docs/$doc" ]] && cp "$ROOT/docs/$doc" "$APP/docs/"
done
cp "$ROOT/docs/deployment-bundle.md" "$BUNDLE/DEPLOYMENT.md"
printf 'code-only\n' > "$BUNDLE/PACKAGE-TYPE"

# Do not package workstation connection defaults.
python3 - "$APP/config/defaults.php" <<'PY'
import re, sys
path = sys.argv[1]
text = open(path, encoding='utf-8').read()
overrides = {'APP_ENV': 'production', 'APP_URL': 'https://arbk.kryeqyteti.net', 'DB_HOST': '', 'DB_USER': '', 'DB_PASSWORD': '',
             'DB_TRUSTED_CONNECTION': 'false', 'DB_ENCRYPT': 'true', 'DB_TRUST_SERVER_CERTIFICATE': 'false', 'FORCE_HTTPS': 'true', 'APP_KEY': ''}
for key, value in overrides.items():
    text, count = re.subn(r"(?m)^(\s*'" + re.escape(key) + r"'\s*=>\s*)'[^']*'", lambda m: m.group(1) + "'" + value + "'", text)
    if count != 1: sys.exit(f'defaults.php: {key} not found exactly once')
open(path, 'w', encoding='utf-8').write(text)
PY

# shellcheck disable=SC2086 # COMPOSER_FLAGS holds several options
COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" "$COMPOSER_BIN" install --working-dir="$APP" --no-dev --prefer-dist --no-interaction --no-progress --classmap-authoritative $COMPOSER_FLAGS
# Composer falls back to git clones when downloads are unavailable; repository metadata never ships.
find "$APP/vendor" -type d \( -name .git -o -name .github \) -prune -exec rm -rf {} +

for file in deploy.sh deploy-simple.sh install-cron.sh cron.example 100-arbk.conf manage-apache-vhosts.awk; do
    sed 's/\r$//' "$ROOT/deploy/$file" > "$BUNDLE/$file"
done
chmod 755 "$BUNDLE"/*.sh
printf 'code-%s\n' "$VERSION" > "$BUNDLE/RELEASE"
find "$APP" -type d -exec chmod 755 {} +
find "$APP" -type f -exec chmod 644 {} +

# Checksums of every file except SHA256SUMS itself (verified by deploy.sh with sha256sum --check).
(cd "$BUNDLE" && find . -type f ! -name SHA256SUMS -printf '%P\n' | LC_ALL=C sort | while IFS= read -r file; do sha256sum "$file"; done) > "$DIST/.$NAME.sha256sums"
mv "$DIST/.$NAME.sha256sums" "$BUNDLE/SHA256SUMS"
(cd "$BUNDLE" && sha256sum --check --quiet SHA256SUMS)

# ZIP with POSIX paths, regular files only (deploy-arbk.sh rejects symlinks and unsafe paths).
python3 - "$BUNDLE" "$ARCHIVE" <<'PY'
import os, re, sys, zipfile
bundle, archive = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as zf:
    for base, dirs, files in os.walk(bundle):
        dirs.sort()
        for name in sorted(files):
            path = os.path.join(base, name)
            if os.path.islink(path): sys.exit(f'Symlink not allowed: {path}')
            rel = os.path.relpath(path, bundle).replace(os.sep, '/')
            if rel.startswith('/') or '..' in rel.split('/') or '\\' in rel or re.search(r'(^|/)\.env$', rel): sys.exit(f'Unsafe entry: {rel}')
            zf.write(path, rel)
with zipfile.ZipFile(archive) as zf:
    print(f'Archive verified: {len(zf.namelist())} entries with POSIX paths.')
PY

(cd "$DIST" && sha256sum "$NAME.zip" > "$NAME.zip.sha256")
for launcher in deploy-arbk.sh deploy-simple.sh install-cron.sh deploy-from-github.sh; do
    sed 's/\r$//' "$ROOT/deploy/$launcher" > "$DIST/$launcher"
    chmod 755 "$DIST/$launcher"
done
echo "Package: $ARCHIVE"
echo "SHA256:  $(cut -d' ' -f1 "$ARCHIVE.sha256")"
