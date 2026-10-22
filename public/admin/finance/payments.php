<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);
header('Cache-Control: private, no-store');
$repository=new App\Repository\InvoiceRepository($app['pdo']);
$query=mb_substr(trim((string)($_GET['q']??'')),0,120);
$payable=$repository->payable($query);
$imports=$repository->paymentImports(20);
$recent=$repository->recentPayments(30);
render('admin/finance/payments','Pagesat',compact('query','payable','imports','recent'));
