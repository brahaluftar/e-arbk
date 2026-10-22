# Deploying from GitHub on CloudClusters (Ubuntu)

`deploy/deploy-from-github.sh` deploys e-ARBK straight from this repository on the CloudClusters Ubuntu server: no ZIP to build on Windows, no upload. It keeps the same layout as the ZIP deployment (`deploy.sh`), so both methods can be used on the same server.

| Path | Content |
| --- | --- |
| `/cloudclusters/arbk/releases/git-<UTC time>-<commit>` | One directory per deployment (code + `vendor/`) |
| `/cloudclusters/arbk/current` → release | Active release, switched atomically |
| `/cloudclusters/arbk/public` → `current/public` | Apache `DocumentRoot` |
| `/cloudclusters/arbk/shared/{imports,log,documents}` | Data kept between releases |
| `/cloudclusters/arbk-env/.env` | Production settings (never in Git) |
| `/cloudclusters/arbk-deployments/` | Git mirror, Composer, cache, lock (root only) |

## 1. One-time server preparation

Run as root in the CloudClusters terminal.

```bash
# Tools (git and curl are usually present)
apt-get update && apt-get install -y git curl util-linux

# Production settings (first installation only; never overwritten by deployments)
mkdir -p /cloudclusters/arbk-env
nano /cloudclusters/arbk-env/.env      # start from .env.production.example and replace every REPLACE_ value
```

PHP 8.1+ with `pdo_sqlsrv`, `mbstring`, `openssl`, `gd`, `zip`, `xmlreader`, `xmlwriter`, `simplexml`, `zlib`, `iconv` must be enabled for both CLI and PHP-FPM, with `allow_url_fopen=On`. Composer is installed automatically into `/cloudclusters/arbk-deployments` (with signature check) when it is missing.

### Repository access

If the repository is **private**, give the server read-only access in one of these ways:

- **Token (simplest):** on GitHub create a *fine-grained personal access token* limited to `brahaluftar/e-arbk` with **Contents: Read-only**, then store it root-only on the server:
  ```bash
  install -m 600 /dev/null /cloudclusters/arbk-env/github-token
  nano /cloudclusters/arbk-env/github-token     # paste the token, save
  ```
  The script hands it to git through a temporary askpass helper; it is never written into the Git configuration, the remote URL or the release.
- **SSH deploy key:** add a read-only deploy key to the repository and pass `--repo git@github.com:brahaluftar/e-arbk.git`.

For a public repository nothing is needed.

### Get the script

```bash
# Public repository
curl -fsSL https://raw.githubusercontent.com/brahaluftar/e-arbk/main/deploy/deploy-from-github.sh -o /cloudclusters/deploy-from-github.sh

# Private repository (token from the file above)
curl -fsSL -H "Authorization: Bearer $(cat /cloudclusters/arbk-env/github-token)" \
  https://raw.githubusercontent.com/brahaluftar/e-arbk/main/deploy/deploy-from-github.sh -o /cloudclusters/deploy-from-github.sh
```

After the first deployment the script is also available as `/cloudclusters/arbk/current/deploy/deploy-from-github.sh`.

## 2. Check, dry run, deploy

```bash
bash /cloudclusters/deploy-from-github.sh check                 # prerequisites only
bash /cloudclusters/deploy-from-github.sh build --ref main       # fetch + build a release, changes nothing live
bash /cloudclusters/deploy-from-github.sh deploy --ref main      # the real deployment
```

`deploy` does, in order:

1. Checks prerequisites and takes a lock (one deployment at a time).
2. Fetches the branch/tag/commit from GitHub into a local mirror. If that commit is already active it stops (`--force` redeploys).
3. Builds a new release: exports the commit (no `.git`, refuses any `.env`), runs `composer install --no-dev --classmap-authoritative`, links `var/imports`, `var/log`, `var/documents` to `shared/`, sets ownership (`root` code, `www-data` data, `.env` readable by `www-data` only).
4. Backs up the database when `BACKUP_MODE=sqlserver` (`bin/backup-database.php`); with `managed` it reminds you to check the CloudClusters backup.
5. Runs the migrations (`bin/migrate.php`) and the production preflight (`bin/preflight-production.php`). Any failure stops here; the live site is untouched.
6. Switches `current` to the new release and restarts PHP-FPM (`supervisorctl restart php-fpm`, or the systemd `php*-fpm` service).
7. Installs `/etc/cron.d/e-arbk` (imports, annual invoices, synchronization) unless `--no-cron`.
8. Calls `/health.php` on the local Apache (Host header from `APP_URL`). If it is not healthy, the code is switched back to the previous release and the script fails. **Migrations are not reverted.** Use `--skip-health` or `HEALTH_URL=...` if the local check cannot reach the site.
9. Keeps the last 5 GitHub releases (`KEEP_RELEASES`) and removes older ones.

To deploy the pull-request branch to a staging server before merging: `--ref feature/bugfixes-and-proposals`.

## 3. Day-to-day

```bash
bash /cloudclusters/arbk/current/deploy/deploy-from-github.sh status          # active release and commit
bash /cloudclusters/arbk/current/deploy/deploy-from-github.sh rollback        # back to the previous release
bash /cloudclusters/arbk/current/deploy/deploy-from-github.sh rollback git-20261022232447-51ea7e8cb5
bash /cloudclusters/arbk/current/deploy/deploy-from-github.sh apache          # (re)install the ARBK virtual host
```

Rollback only switches code. If the release you are leaving ran migrations that the older code cannot work with, restore the database backup as well.

## 4. Options and overrides

| Option / variable | Default | Use |
| --- | --- | --- |
| `--ref` / `REF` | `main` | Branch, tag or commit |
| `--repo` / `REPO_URL` | `https://github.com/brahaluftar/e-arbk.git` | Other fork or SSH URL |
| `--force` | off | Redeploy the active commit |
| `--skip-health`, `HEALTH_URL` | local `/health.php` | Health check control |
| `--no-cron` | off | Leave cron untouched |
| `GITHUB_TOKEN`, `TOKEN_FILE` | `/cloudclusters/arbk-env/github-token` | Private repository access |
| `WEB_USER`, `WEB_GROUP` | `www-data` | PHP-FPM account |
| `PHP_BIN`, `COMPOSER_BIN`, `COMPOSER_FLAGS` | `php`, auto | Tool paths and extra Composer options |
| `KEEP_RELEASES` | `5` | Releases kept |
| `APP_ROOT`, `ENV_DIR`, `DEPLOY_ROOT` | `/cloudclusters/...` | Other layout (testing) |

## 5. Troubleshooting

| Message | Meaning / action |
| --- | --- |
| `MISSING PHP extension: pdo_sqlsrv` | Enable the Microsoft SQL Server driver for PHP CLI (and FPM) in the CloudClusters panel or with `pecl install sqlsrv pdo_sqlsrv` |
| `Ref not found` | Branch/tag does not exist, or the token cannot read the repository |
| `Database migration did not complete` | See the SQL error above it; the site still runs the previous release |
| `PHP aborted during pdo_sqlsrv shutdown` (warning) | Known CloudClusters PHP issue; the completion marker proves the step finished |
| `Health check failed` | Code was switched back; check `/var/log/apache2/arbk-error.log`, the PHP-FPM log and `curl -H 'Host: arbk.kryeqyteti.net' http://127.0.0.1/health.php` |
| `Another deployment is running` | Wait, or remove a stale lock only if no deployment process exists (`ps aux | grep deploy-from-github`) |
