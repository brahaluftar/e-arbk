# Production deployment — CloudClusters PHP

1. Configure the website document root to the repository's `public/` directory.
2. Select PHP 8.1+ and enable `pdo_sqlsrv`, `mbstring`, and `openssl`.
   For the supplied ARBK workbooks, also enable `zip`, `xmlreader`, and `simplexml`; configure `upload_max_filesize=150M` and `post_max_size=160M` or higher.
3. Install dependencies with `composer install --no-dev --classmap-authoritative`.
4. Copy `.env.production.example` to `.env`, replace every placeholder, use the CloudClusters SQL host/user/password, and generate `APP_KEY` with `php -r "echo bin2hex(random_bytes(32)),PHP_EOL;"`.
5. Keep `.env` outside backups exposed to the web and set file mode `600` where supported.
6. Run `php bin/migrate.php`, then `php bin/preflight-production.php`.
7. Configure the free TLS certificate, keep `FORCE_HTTPS=true`, and confirm the proxy sends `X-Forwarded-Proto=https` before enabling `TRUST_PROXY_HEADERS=true`.
8. Configure jobs from `deploy/cron.example`. Do not enable the SQL backup cron when `BACKUP_MODE=managed`.
   `bin/run-scheduled.php` claims at most one queued Excel import per run before executing synchronization/classification.
9. In managed backup mode, enable the CloudClusters backup schedule and perform a documented restore test before launch. In SQL Server mode, provide a server-side `DB_BACKUP_PATH` and grant the SQL Server service account access; the script runs `BACKUP ... CHECKSUM` and `RESTORE VERIFYONLY`.
10. Verify `GET /health.php` returns HTTP 200 without exposing credentials, then smoke-test login, listing, classification, logout, and rate limiting.

Deploy releases from Git; do not upload `vendor/`, local `.env`, database backups, or logs from a workstation.
