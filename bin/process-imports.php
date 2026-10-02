<?php
declare(strict_types=1);
$app=require dirname(__DIR__).'/bootstrap.php';if(PHP_SAPI!=='cli')exit("CLI only.\n");
$service=new App\Import\BusinessImportService($app['pdo'],new App\Service\AuditLogger($app['pdo']));$processed=0;
while($service->processNext()){$processed++;echo "Processed import run.\n";}
echo "Completed; runs processed: $processed.\n";
