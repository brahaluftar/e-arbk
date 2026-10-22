<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$app['auth']->requireRole(['ADMIN']);
header('Cache-Control: private, no-store');
$year=filter_var($_GET['year']??date('Y'),FILTER_VALIDATE_INT);
if($year===false||$year<2000||$year>2100)$year=(int)date('Y');
$financialStats=(new App\Repository\BusinessRepository($app['pdo']))->financialDashboard($year,App\Billing\BillingPolicy::fromConfig($app['config']));
$obligations=(new App\Repository\InvoiceRepository($app['pdo']))->obligationsSummary(null);
render('admin/finance/index','Financat',compact('financialStats','obligations','year'));
