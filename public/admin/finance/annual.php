<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$app['auth']->requireRole(['ADMIN']);
header('Cache-Control: private, no-store');
$jobs=(new App\Repository\InvoiceRepository($app['pdo']))->annualJobs(30);
$sources=App\Billing\BillingPolicy::fromConfig($app['config'])->sources();
render('admin/finance/annual','Faturimi vjetor',compact('jobs','sources'));
