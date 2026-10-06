# ARBK deployment bundle

## Paths and first deployment

- Application root: `/cloudclusters/arbk`.
- Public document root: `/cloudclusters/arbk/public`.
- Protected environment file: `/cloudcluster/arbk-env/.env` (singular `cloudcluster`).
- Private extracted package: `/cloudclusters/arbk-deployments/VERSION`.
- Persistent workbooks/logs: `/cloudclusters/arbk/shared/imports` and `shared/log`.
- Application releases: `/cloudclusters/arbk/releases/VERSION`; `current` selects the active release and `public` points at `current/public`.

Upload the ZIP, its `.sha256` file and `deploy-arbk.sh` to `/cloudclusters/` using
SFTP/SSH. The ZIP contains business data, user password hashes and audit history;
keep it outside every public document root and do not publish it in GitHub.
Passwords are preserved as hashes, so existing application logins continue to work.

Before first deployment:

1. Create `/cloudcluster/arbk-env` and upload the completed production environment
   configuration as `.env`. A workstation file such as `arbk-prduction.env` must
   be renamed to `.env` on the server. It is deliberately not bundled.
2. Use a dedicated ARBK SQL database and SQL login with schema/data creation
   permissions. Set `DB_TRUSTED_CONNECTION=false`, the production SQL host,
   database, username/password, encryption settings and a unique `APP_KEY`.
   `APP_ENV=production`, `APP_URL=https://arbk.kryeqyteti.net`, and
   `FORCE_HTTPS=true` are required. Do not use the e-aplikimet database.
3. Enable PHP 8.1+ with `pdo_sqlsrv`, `mbstring`, `openssl`, `zip`, `xmlreader`,
   `xmlwriter`, `simplexml`, and `zlib` for both CLI and PHP-FPM. Production Composer
   autoload files are bundled, so Composer/network access is not needed on the server.
4. Run from the CloudClusters root terminal. The script defaults to FPM account/group
   `www-data`; set `WEB_USER` and `WEB_GROUP` if your pool uses different accounts.
   It gives that group read access to the protected environment file and ownership
   of ARBK's shared runtime directories only. No environment file is overwritten.

For an existing **empty** database:

```bash
bash /cloudclusters/deploy-arbk.sh /cloudclusters/e-arbk-VERSION.zip deploy
```

To create the configured database too, when the SQL login has permission:

```bash
bash /cloudclusters/deploy-arbk.sh /cloudclusters/e-arbk-VERSION.zip deploy --create-database
```

If the provider does not grant CREATE DATABASE, create the empty database in its
control panel and use the first command. The database name comes from the external
environment file; the package does not contain production credentials.

## Separate commands

```bash
bash /cloudclusters/deploy-arbk.sh /cloudclusters/e-arbk-VERSION.zip check
bash /cloudclusters/deploy-arbk.sh /cloudclusters/e-arbk-VERSION.zip install
bash /cloudclusters/deploy-arbk.sh /cloudclusters/e-arbk-VERSION.zip database-import --create-database
bash /cloudclusters/deploy-arbk.sh /cloudclusters/e-arbk-VERSION.zip database-verify
bash /cloudclusters/deploy-arbk.sh /cloudclusters/e-arbk-VERSION.zip migrate
bash /cloudclusters/deploy-arbk.sh /cloudclusters/e-arbk-VERSION.zip activate
```

`database-import` is the explicit schema/data transfer command. It checks every
transfer file's SHA-256, creates all 11 application tables, sequence, defaults,
indexes, primary/unique/foreign keys, check constraints and `v_business_master`.
Rows are imported in batches using typed OPENJSON and a single transaction.
IDs, GUIDs, Unicode, decimals, floats, timestamps and password hashes are retained;
rowversion values are generated anew. Row counts and canonical content hashes are
checked before commit. A failed import rolls back schema and data (a newly created
database itself may remain empty). Repeating the same snapshot skips import using
a database ledger; any other populated target is refused. Nothing truncates,
deletes, merges into, or drops an existing production database.

`database-verify` compares against the initial snapshot; it is expected to report
differences after legitimate user edits, logins, imports or scheduled jobs.
`activate` runs production preflight and then switches only ARBK's current release.

## Data selection

Full rows from `ARBK_LIST`, `ATK_LIST`, `NACE_LIST`, `app_users`,
`business_atk_status`, `business_nace_assignments`, `audit_log`,
`business_import_runs`, `business_import_staging`, and `schema_migrations` are
included. `login_rate_limits` is created empty so workstation lockouts do not move
to production. The three original completed-import XLSX files are included with
their checked hashes and portable paths. No import is automatically replayed.

The unused legacy tables `arbk`, `atk2`, `BizList1` and old stored procedures are
excluded. They are not referenced by the application and some old procedure
definitions refer to obsolete columns. This is an application migration, not a
full archival backup of every historical database object. SQL Server logins,
server permissions, jobs and linked servers are not transferred.

The schema/data snapshot reflects the installed migrations and the actual legacy
master table definitions. All 12 migration records are included, so `migrate`
does not attempt to add columns that are already present. Future migrations run
normally. SQL Server 2016+ and database compatibility level 130+ are required.

## Apache and the existing default site

After activating ARBK, enable its separate hostname with:

```bash
bash /cloudclusters/deploy-arbk.sh /cloudclusters/e-arbk-VERSION.zip apache
```

This command installs only `100-arbk.conf`, checks the PHP 8.1 FPM socket and Apache
syntax, compares the existing default-vhost listing, then performs a graceful
reload. It never repoints `/cloudclusters/default_site` or edits the existing
e-aplikimet virtual host. A different pre-existing `100-arbk.conf` is refused.

Add `arbk.kryeqyteti.net` to the same CloudClusters application, configure DNS and
its SSL certificate without changing the default domain. If TLS terminates at a
trusted proxy, confirm it preserves Host and sends `X-Forwarded-Proto=https`
before using `TRUST_PROXY_HEADERS=true`. If Apache terminates TLS, a separate
443 virtual host and certificate are also required; the supplied file is port 80.

Check `https://arbk.kryeqyteti.net/health.php`, login, users, listings and XLSX.
Also check the existing `https://e-aplikimet.kryeqyteti.net` site after reload.

Schedule (using the same account with environment read/shared-directory write access):

```cron
*/15 * * * * cd /cloudclusters/arbk/current && /usr/bin/php bin/run-scheduled.php >> /cloudclusters/arbk/shared/log/scheduled.log 2>&1
```

Do not start the scheduler until the initial snapshot verification is complete.
Configure backups in CloudClusters separately; deployment is not a backup policy.

## Subsequent code updates and recovery

For a later code-only release use `install`, `migrate`, then `activate`; do not
import a new local snapshot over an established production database. Shared files
and external settings survive releases. Old releases remain available. Switching
code to an older release does not reverse schema migrations; review compatibility
before doing so. Keep the previous release and a database backup before upgrades.

## Rebuilding on Windows

```powershell
php bin/database-transfer.php export dist/database-snapshot-NEW
powershell -ExecutionPolicy Bypass -File deploy/build-package.ps1 -Version VERSION -Snapshot dist/database-snapshot-NEW
```

The exporter briefly holds shared locks over the selected application tables to
produce a consistent snapshot without changing source data/schema. It refuses
queued/processing imports and missing/changed workbook files. Snapshot directories
and version identifiers must be new. The builder includes current workspace code,
including reviewed uncommitted changes, excludes real environment files and dev
dependencies, replaces workstation DB defaults in the packaged copy, and emits
POSIX-path ZIP entries, per-file checksums and an archive checksum.
