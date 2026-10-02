<?php
declare(strict_types=1);
$app=require dirname(__DIR__).'/bootstrap.php';
$service=new App\Service\AtkStatusService($app['pdo'],new App\Service\AuditLogger($app['pdo']));
echo 'Synchronized '.$service->synchronize()." businesses.\n";
