# Business Tariff Administration

Framework-free PHP 8.1+ module for ARBK/ATK business status normalization and municipal NACE classification. It follows the adjacent `e-transporti` application's PSR-4, PDO SQL Server, physical-route, migration, CLI-job, security, and visual conventions.

## Setup

1. Copy `.env.example` to `.env` only when defaults are unsuitable. Do not commit it.
2. Run `composer install`.
3. Run `php bin/migrate.php`.
4. Create the first administrator with `php bin/create-admin.php admin@example.test "Full Name" "a-long-password"`.
5. Run `php bin/sync-atk.php`, `php bin/sync-tariff-mappings.php`, then `php bin/classify-nace.php` after confirming `nace_list` is populated.
6. Open `http://localhost/arbk/auth/login.php`.

For production, use `public/` as the website document root and follow [docs/production-deployment.md](docs/production-deployment.md).

Production operations include database-backed login throttling, `/health.php`, strict HTTPS/cookie configuration, `bin/preflight-production.php`, non-overlapping scheduled synchronization, and managed or SQL Server backup modes.

## Excel imports

Administrators can queue the full, women-owned, and closed-business XLSX exports from **Importet**. Only rows whose `Qyteti` value is exactly `Prishtinë` are imported. `bin/process-imports.php` processes the queue directly; the regular scheduler processes one queued workbook per run. The streaming reader keeps memory bounded, splits `NNNN-description` into `NACE_CODE_REG` and `NACEPERSHKRIMI`, and removes the leading sector letter/dash. Imports are deduplicated by business number and file SHA-256 and are fully audited.

Statuses such as `Pasiv-DD/MM/YYYY` are normalized into the `Pasiv` flag and `date_pasivizimit`, and also set `ATK_MBYLLUR=1`. Administrators and officials can manually edit the supported business fields from the business detail page; every update is validated and audited.

The migration is additive and does not add foreign keys to legacy master tables. Batch jobs are transactional and idempotent. Tariff mapping fills only empty master fields; existing manual values are not overwritten.

## Roles

- `ADMIN`: all Phase 2 screens and classification changes.
- `OFFICIAL`: listing, details, and manual classification.
- `READ_ONLY`: listing and details only.

No initial password is stored in source. Use the CLI command to create accounts.
