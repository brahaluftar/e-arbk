<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);
header('Cache-Control: private, no-store');
$year=max(2000,min(2100,(int)($_GET['year']??date('Y'))));$page=max(1,(int)($_GET['page']??1));$perPage=(int)($_GET['per_page']??30);
$query=mb_substr(trim((string)($_GET['q']??'')),0,120);$status=(string)($_GET['status']??'');if(!in_array($status,App\Repository\InvoiceRepository::STATUS_FILTERS,true))$status='';
$jobId=isset($_GET['job'])&&ctype_digit((string)$_GET['job'])?(int)$_GET['job']:null;
$result=(new App\Repository\InvoiceRepository($app['pdo']))->page($year,$page,$perPage,$query,$jobId,$status);
$businessQuery=mb_substr(trim((string)($_GET['business_q']??'')),0,120);
$businesses=$businessQuery===''?[]:(new App\Repository\BusinessRepository($app['pdo']))->financeBusinesses($year,$businessQuery);
$bulkParts=(new App\Billing\InvoiceDocumentService($app['pdo'],$app['config']))->bulkParts($year);
render('admin/finance/invoices','Faturat',compact('year','query','status','jobId','result','businessQuery','businesses','bulkParts'));
