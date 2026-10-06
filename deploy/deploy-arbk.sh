#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

ARCHIVE=${1:-}
if [[ -z "$ARCHIVE" || "$ARCHIVE" == --help ]]; then
    printf '%s\n' 'Usage: bash deploy-arbk.sh /path/e-arbk-VERSION.zip [deploy|check|install|database-import|database-verify|migrate|activate|apache] [--create-database]'
    exit 0
fi
shift
[[ -f "$ARCHIVE" && -f "$ARCHIVE.sha256" ]] || { echo 'Upload both the ZIP and its .sha256 file.' >&2; exit 1; }
ARCHIVE=$(readlink -f "$ARCHIVE")
NAME=$(basename "$ARCHIVE")
[[ "$NAME" =~ ^e-arbk-(code-)?([0-9][A-Za-z0-9._-]*)\.zip$ ]] || { echo 'Unexpected package name.' >&2; exit 1; }
VERSION="${BASH_REMATCH[1]}${BASH_REMATCH[2]}"
[[ "$VERSION" != *..* ]] || exit 1
EXPECTED=$(awk 'NR==1 {print $1}' "$ARCHIVE.sha256")
ACTUAL=$(sha256sum "$ARCHIVE" | awk '{print $1}')
[[ "$EXPECTED" =~ ^[a-fA-F0-9]{64}$ && "${EXPECTED,,}" == "$ACTUAL" ]] || { echo 'ZIP checksum mismatch.' >&2; exit 1; }
DEPLOY_ROOT=/cloudclusters/arbk-deployments
mkdir -p "$DEPLOY_ROOT"
[[ "$(readlink -f "$DEPLOY_ROOT")" == "$DEPLOY_ROOT" ]] || { echo 'Unexpected deployment directory symlink.' >&2; exit 1; }
DESTINATION="$DEPLOY_ROOT/$VERSION"
exec 8>"$DEPLOY_ROOT/.extract.lock"
flock -n 8 || { echo 'Another package extraction is running.' >&2; exit 1; }
if [[ -e "$DESTINATION" || -L "$DESTINATION" ]]; then
    [[ ! -L "$DESTINATION" && -f "$DESTINATION/.archive-sha256" && "$(cat "$DESTINATION/.archive-sha256")" == "$ACTUAL" ]] || { echo 'Deployment directory already contains a different or incomplete package.' >&2; exit 1; }
else
    STAGING=$(mktemp -d "$DEPLOY_ROOT/.extract-$VERSION.XXXXXXXX")
    "${PHP_BIN:-php}" -r '
        $zip=new ZipArchive();
        if($zip->open($argv[1])!==true)throw new RuntimeException("Cannot open deployment ZIP.");
        for($i=0;$i<$zip->numFiles;$i++){
            $name=$zip->getNameIndex($i);
            if(str_contains($name,"\\") || preg_match("~(^/|^[A-Za-z]:|(^|/)\\.\\.(/|$))~",$name))throw new RuntimeException("Unsafe ZIP entry.");
            $zip->getExternalAttributesIndex($i,$opsys,$attributes);
            if((($attributes >> 16) & 0170000)===0120000)throw new RuntimeException("Symlinks are not allowed in the package.");
        }
        if(!$zip->extractTo($argv[2]))throw new RuntimeException("Package extraction failed.");
        $zip->close();
    ' "$ARCHIVE" "$STAGING"
    [[ "$(cat "$STAGING/RELEASE")" == "$VERSION" ]] || { echo 'Release metadata mismatch.' >&2; exit 1; }
    printf '%s\n' "$ACTUAL" > "$STAGING/.archive-sha256"
    mv "$STAGING" "$DESTINATION"
fi
flock -u 8
[[ $# -gt 0 ]] || set -- deploy
exec bash "$DESTINATION/deploy.sh" "$@"
