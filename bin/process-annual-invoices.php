<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit("CLI only.\n");
$app=require dirname(__DIR__).'/bootstrap.php';$batch=max(1,min(1000,(int)($argv[1]??$app['config']->int('ANNUAL_INVOICE_BATCH_SIZE'))));$service=new App\Billing\AnnualInvoiceJobService($app['pdo'],new App\Service\AuditLogger($app['pdo']));$batches=0;
while($service->processNextBatch($batch)){$batches++;echo '['.date('c')."] Processed batch $batches (up to $batch businesses).\n";}
$artifacts=new App\Billing\JobArtifactService($app['pdo'],$app['config'],new App\Document\JobArtifactStore(dirname(__DIR__).'/var/documents'));$exports=0;while($artifacts->processNext()){$exports++;echo '['.date('c')."] Processed job export.\n";}
echo "Queue is empty; batches processed: $batches; exports processed: $exports.\n";
