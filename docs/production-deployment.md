# Production deployment — CloudClusters PHP

For a first deployment that includes creation of the legacy master tables and
transfer of the current application data, use [the deployment bundle](deployment-bundle.md).
Running the historical migrations alone against an empty database is insufficient.

1. Configure the website document root to the repository's `public/` directory.
2. Select PHP 8.1+ and enable `pdo_sqlsrv`, `mbstring`, and `openssl`.
   For the supplied ARBK workbooks, also enable `zip`, `xmlreader`, and `simplexml`; configure `upload_max_filesize=150M` and `post_max_size=160M` or higher.
3. Install dependencies with `composer install --no-dev --classmap-authoritative`.
4. Create `/cloudclusters/arbk-env/` and copy `.env.production.example` to `/cloudclusters/arbk-env/.env` only on first setup. Keep this directory separate from application releases. Replace every placeholder, set `APP_URL=https://arbk.kryeqyteti.net`, use the CloudClusters SQL host/user/password, and generate `APP_KEY` with `php -r "echo bin2hex(random_bytes(32)),PHP_EOL;"`.
5. Restrict access to `/cloudclusters/arbk-env/.env` while allowing both the PHP-FPM worker and the CLI/cron account to read it. Use file mode `600` when both use the owning account, or `640` with a dedicated shared group otherwise. The containing directory must allow those accounts to traverse it. Never expose this directory through a document root or Alias.
6. Run `php bin/migrate.php`, then `php bin/preflight-production.php`.
7. Configure the free TLS certificate, keep `FORCE_HTTPS=true`, and confirm the proxy sends `X-Forwarded-Proto=https` before enabling `TRUST_PROXY_HEADERS=true`.
8. Configure jobs from `deploy/cron.example`. Do not enable the SQL backup cron when `BACKUP_MODE=managed`.
   `bin/run-scheduled.php` claims at most one queued Excel import per run before executing synchronization/classification.
9. In managed backup mode, enable the CloudClusters backup schedule and perform a documented restore test before launch. In SQL Server mode, provide a server-side `DB_BACKUP_PATH` and grant the SQL Server service account access; the script runs `BACKUP ... CHECKSUM` and `RESTORE VERIFYONLY`.
10. Verify `GET /health.php` returns HTTP 200 without exposing credentials, then smoke-test login, listing, classification, logout, and rate limiting.

Deploy releases from Git; do not upload `vendor/`, local `.env`, database backups, or logs from a workstation.

## External environment configuration

The environment directory is exactly `/cloudclusters/arbk-env`.
The application can remain at `/cloudclusters/arbk`,
with document root `/cloudclusters/arbk/public`. Do not change the existing
default site's virtual host or `/cloudclusters/default_site` shortcut.

All entry points use the same configuration loader, including web requests,
health checks, migration/preflight commands and scheduled jobs:

1. An explicit `ARBK_ENV_FILE` process environment variable selects a configuration
   file when set; a missing/unreadable selected file causes an error.
2. Otherwise, when `/cloudclusters/arbk-env` exists, load its `.env` file. A missing
   or unreadable file causes an error rather than falling back to local settings.
3. If the external directory does not exist, retain the project-root `.env`
   fallback for local development.

Only one file is loaded. Defaults are overridden by that file, then by process
environment variables. `ARBK_ENV_FILE` must be supplied to the process, not placed
inside a `.env` file. The standard directory requires no Apache/FPM environment
directive and is discovered by both web and CLI automatically.

Do not overwrite the external `.env` during upgrades. The example file contains
placeholders, not usable production credentials.
