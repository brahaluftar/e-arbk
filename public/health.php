<?php
declare(strict_types=1);

use App\Infrastructure\Database\ConnectionFactory;
use App\Support\Config;
use App\Support\ProductionValidator;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$root=dirname(__DIR__);$started=microtime(true);
try {
    require $root.'/vendor/autoload.php';
    $config=Config::load($root,require $root.'/config/defaults.php');
    if(ProductionValidator::errors($config)!==[]) throw new RuntimeException('Invalid production configuration.');
    $pdo=(new ConnectionFactory($config))->create();
    $pdo->query('SELECT 1')->fetchColumn();
    $migration=$pdo->query('SELECT MAX(applied_at) FROM dbo.schema_migrations')->fetchColumn();
    http_response_code(200);
    echo json_encode(['status'=>'ok','database'=>'ok','last_migration_at'=>$migration?:null,'duration_ms'=>(int)round((microtime(true)-$started)*1000)],JSON_THROW_ON_ERROR);
} catch(Throwable) {
    http_response_code(503);
    echo json_encode(['status'=>'unavailable','database'=>'unavailable','duration_ms'=>(int)round((microtime(true)-$started)*1000)],JSON_THROW_ON_ERROR);
}
