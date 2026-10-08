#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)
TEMP=$(mktemp -d)
trap 'rm -rf "$TEMP"' EXIT
CANONICAL="$ROOT/deploy/100-arbk.conf"
TRANSFORM="$ROOT/deploy/manage-apache-vhosts.awk"

cat > "$TEMP/default.conf" <<'CONF'
# Keep global settings
<VirtualHost *:80>
    ServerName first.example.test
    DocumentRoot /first
</VirtualHost>
<VirtualHost *:80>
    ServerName arbk.kryeqyteti.net
    DocumentRoot /old-arbk
</VirtualHost>
<VirtualHost *:80>
    ServerName arbk.kryeqyteti.net
    DocumentRoot /old-arbk-duplicate
</VirtualHost>
<VirtualHost *:80>
    ServerName last.example.test
    DocumentRoot /last
</VirtualHost>
CONF

awk -v replacement="$CANONICAL" -f "$TRANSFORM" "$TEMP/default.conf" > "$TEMP/result.conf"
[[ $(grep -icE '^[[:space:]]*ServerName[[:space:]]+arbk\.kryeqyteti\.net([[:space:]]|$)' "$TEMP/result.conf") -eq 1 ]]
grep -Fq '# Keep global settings' "$TEMP/result.conf"
grep -Fq 'ServerName first.example.test' "$TEMP/result.conf"
grep -Fq 'ServerName last.example.test' "$TEMP/result.conf"
! grep -Fq '/old-arbk' "$TEMP/result.conf"

awk -v replacement="$CANONICAL" -f "$TRANSFORM" "$TEMP/result.conf" > "$TEMP/idempotent.conf"
cmp -s "$TEMP/result.conf" "$TEMP/idempotent.conf"

cat > "$TEMP/no-arbk.conf" <<'CONF'
<VirtualHost *:80>
    ServerName only.example.test
    DocumentRoot /only
</VirtualHost>
CONF
awk -v replacement="$CANONICAL" -f "$TRANSFORM" "$TEMP/no-arbk.conf" > "$TEMP/with-arbk.conf"
[[ $(grep -icE '^[[:space:]]*ServerName[[:space:]]+arbk\.kryeqyteti\.net([[:space:]]|$)' "$TEMP/with-arbk.conf") -eq 1 ]]
grep -Fq 'ServerName only.example.test' "$TEMP/with-arbk.conf"

cat > "$TEMP/alias-conflict.conf" <<'CONF'
<VirtualHost *:80>
    ServerName unrelated.example.test
    ServerAlias arbk.kryeqyteti.net
    DocumentRoot /unrelated
</VirtualHost>
CONF
if awk -v replacement="$CANONICAL" -f "$TRANSFORM" "$TEMP/alias-conflict.conf" > "$TEMP/alias-result.conf"; then
    echo 'Expected an unrelated ServerAlias conflict to be rejected.' >&2
    exit 1
fi

printf '%s\n' 'Vhost transform tests passed: preserve other hosts, canonicalize/dedupe ARBK, and remain idempotent.'
