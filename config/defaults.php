<?php
declare(strict_types=1);

return [
    'APP_ENV' => 'development',
    'APP_DEBUG' => 'false',
    'APP_URL' => 'http://localhost/arbk',
    'APP_TIMEZONE' => 'Europe/Warsaw',
    'APP_SESSION_NAME' => 'arbk_admin',
    'APP_KEY' => '',
    'FORCE_HTTPS' => 'false',
    'TRUST_PROXY_HEADERS' => 'false',
    // Both workstations use their own local SQL2025 instance.
    'DB_HOST' => 'localhost\\sql2025',
    'DB_NAME' => 'ARBK',
    'DB_USER' => '',
    'DB_PASSWORD' => '',
    'DB_TRUSTED_CONNECTION' => 'true',
    'DB_ENCRYPT' => 'false',
    'DB_TRUST_SERVER_CERTIFICATE' => 'true',
    'PAGE_SIZE' => '25',
    'CSRF_TTL_SECONDS' => '7200',
    'LOGIN_RATE_WINDOW_SECONDS' => '900',
    'LOGIN_RATE_MAX_ATTEMPTS' => '5',
    'LOGIN_RATE_BLOCK_SECONDS' => '900',
    'BACKUP_MODE' => 'managed',
    'DB_BACKUP_PATH' => '',
    'IMPORT_MAX_BYTES' => '157286400',
];
