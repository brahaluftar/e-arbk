# Working on ELI and ALTERNATOR

The same tracked development configuration works on both workstations:

| Setting | ELI | ALTERNATOR |
| --- | --- | --- |
| Application URL | `http://localhost/arbk` | `http://localhost/arbk` |
| SQL Server | `localhost\sql2025` (ELI) | `localhost\sql2025` (ALTERNATOR) |
| Database | `ARBK` | `ARBK` |
| Authentication | Windows account running PHP | Windows account running PHP |

`localhost` is the computer running PHP, even when the source folder is accessed
through file sharing. Each computer must have its SQL2025 instance/database and
the PHP/Apache Windows account must have access. Git and file sharing do not
synchronize SQL Server databases.

## Set up each computer

Keep a checkout at `C:\xampp\htdocs\arbk` on each machine, or expose the shared
source folder at the equivalent Apache URL. Run from the application directory:

```powershell
git pull --ff-only origin main
composer install
php bin/check-local.php
```

Both PHP CLI and Apache need `pdo_sqlsrv`, `mbstring`, `openssl`, `zip`,
`xmlreader`, `xmlwriter`, and `simplexml`. Enable the extensions in that machine's
PHP configuration and restart its Apache after PHP configuration changes.
Each computer maintains its own PHP configuration; it is not deployed through Git.

The tracked defaults already work without a `.env` file. If you use a local
`.env`, copy `.env.example` only when `.env` does not already exist. In any older
`.env`, change the hard-coded `ELI\sql2025` or `alternator\sql2025` value to:

```dotenv
DB_HOST=localhost\sql2025
```

This value can safely be used by both computers in a shared source directory.
An explicit process-level `DB_HOST` setting overrides the file/defaults; remove
an obsolete override if the local checker reports an unexpected server.

For genuinely different private settings, keep a file outside the shared folder
on each computer and set `ARBK_ENV_FILE` for that computer's PHP/Apache process.
For a CLI session, for example:

```powershell
$env:ARBK_ENV_FILE = 'C:\arbk-env\.env'
php bin/check-local.php
```

This PowerShell setting affects only that process and its children; Apache needs
its own environment setting and a restart. Do not put credentials in tracked
configuration or share one computer's production environment file as local `.env`.

## Database setup and updates

For an existing ARBK database, apply pending migrations with `php bin/migrate.php`.
For a fresh/empty database, use the schema/data transfer from
[the deployment bundle](deployment-bundle.md): the historical migrations alone
do not create the legacy master tables. Import refuses to overwrite a populated
database. Decide which computer holds the authoritative data before any transfer.

After code changes, commit and push on the machine where you edited, then pull
on the other machine. Run `composer install` when the dependency definitions
change. Do not run Git operations concurrently against the same shared checkout.

## Production remains separate

CloudClusters uses `/cloudcluster/arbk-env/.env` and application root
`/cloudclusters/arbk`. Its external file overrides these workstation defaults.
Deployment archives, real environment files, `vendor/`, uploaded XLSX files and
database snapshots remain outside Git; copy private deployment artifacts through
your file-sharing/SFTP channel when needed.
