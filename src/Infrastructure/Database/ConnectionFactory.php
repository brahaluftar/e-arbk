<?php
declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Support\Config;
use PDO;

final class ConnectionFactory
{
    public function __construct(private Config $config) {}

    public function create(): PDO
    {
        $server = str_replace([';', "\0", "\r", "\n"], '', $this->config->string('DB_HOST'));
        $database = str_replace([';', "\0", "\r", "\n"], '', $this->config->string('DB_NAME'));
        if ($server === '' || $database === '') throw new \RuntimeException('Database configuration is incomplete.');
        $dsn = sprintf('sqlsrv:Server=%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s', $server, $database,
            $this->config->bool('DB_ENCRYPT') ? 'true' : 'false',
            $this->config->bool('DB_TRUST_SERVER_CERTIFICATE') ? 'true' : 'false');
        $user = $this->config->bool('DB_TRUSTED_CONNECTION') ? null : $this->config->string('DB_USER');
        $password = $this->config->bool('DB_TRUSTED_CONNECTION') ? null : $this->config->string('DB_PASSWORD');
        return new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
}
