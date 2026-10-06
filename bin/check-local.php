<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
try {
    if (!is_file($root.'/vendor/autoload.php')) throw new RuntimeException('Run composer install first.');
    require $root.'/vendor/autoload.php';
    $config = App\Support\Config::load($root,require $root.'/config/defaults.php');
    if ($config->string('APP_ENV') !== 'development') throw new RuntimeException('Local checker expects APP_ENV=development. Check ARBK_ENV_FILE and .env.');
    foreach (['pdo_sqlsrv','mbstring','openssl','zip','xmlreader','xmlwriter','simplexml'] as $extension) {
        if (!extension_loaded($extension)) throw new RuntimeException('Missing PHP extension: '.$extension);
    }
    if (PHP_VERSION_ID < 80100) throw new RuntimeException('PHP 8.1 or newer is required.');
    echo 'PHP: '.PHP_VERSION.PHP_EOL;
    echo 'Configured SQL host: '.$config->string('DB_HOST').PHP_EOL;
    $pdo = (new App\Infrastructure\Database\ConnectionFactory($config))->create();
    $identity = $pdo->query("SELECT CONVERT(nvarchar(128),SERVERPROPERTY('ServerName')) server_name,DB_NAME() database_name")->fetch();
    echo 'Connected: '.$identity['server_name'].' / '.$identity['database_name'].PHP_EOL;
    foreach (App\Deployment\DatabaseTransfer::TABLES as $table) {
        $s = $pdo->prepare("SELECT OBJECT_ID(:name,'U')");
        $s->execute(['name'=>'dbo.'.$table]);
        if (!$s->fetchColumn()) throw new RuntimeException('Missing table: dbo.'.$table.'. See docs/local-development.md for database setup.');
    }
    $applied = $pdo->query('SELECT migration FROM dbo.schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    foreach (glob($root.'/database/migrations/*.sql') ?: [] as $file) {
        if (!in_array(basename($file),$applied,true)) throw new RuntimeException('Pending migrations. Run php bin/migrate.php.');
    }
    echo "Local database and PHP checks passed. No database changes made.\n";
} catch (Throwable $e) {
    // PDO connection messages can include connection details; show only the code.
    $message = $e instanceof PDOException ? 'Database access failed ('.$e->getCode().'). Check SQL instance, database and Windows account permissions.' : $e->getMessage();
    fwrite(STDERR,$message.PHP_EOL);
    exit(1);
}
