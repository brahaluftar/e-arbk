<?php
declare(strict_types=1);
$app=require dirname(__DIR__).'/bootstrap.php';
$service=new App\Service\ClassificationService($app['pdo'],new App\Service\AuditLogger($app['pdo']));
echo 'Assigned '.$service->autoAssign()." businesses.\n";
