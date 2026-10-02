# Business Tariff Administration

Framework-free PHP 8.1+ module for ARBK/ATK business status normalization and municipal NACE classification. It follows the adjacent `e-transporti` application's PSR-4, PDO SQL Server, physical-route, migration, CLI-job, security, and visual conventions.

## Setup

1. Copy `.env.example` to `.env` only when defaults are unsuitable. Do not commit it.
2. Run `composer install`.
3. Run `php bin/migrate.php`.
4. Create the first administrator with `php bin/create-admin.php admin@example.test "Full Name" "a-long-password"`.
5. Run `php bin/sync-atk.php`, `php bin/sync-tariff-mappings.php`, then `php bin/classify-nace.php` after confirming `nace_list` is populated.
6. Open `http://localhost/arbk/public/auth/login.php`.

The migration is additive and does not add foreign keys to legacy master tables. Batch jobs are transactional and idempotent. Tariff mapping fills only empty master fields; existing manual values are not overwritten.

## Roles

- `ADMIN`: all Phase 2 screens and classification changes.
- `OFFICIAL`: listing, details, and manual classification.
- `READ_ONLY`: listing and details only.

No initial password is stored in source. Use the CLI command to create accounts.
