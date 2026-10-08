<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);$year=max(2000,min(2100,(int)($_GET['year']??date('Y'))));$page=max(1,(int)($_GET['page']??1));$perPage=(int)($_GET['per_page']??30);$query=mb_substr(trim((string)($_GET['q']??'')),0,120);$jobId=isset($_GET['job'])&&ctype_digit((string)$_GET['job'])?(int)$_GET['job']:null;$result=(new App\Repository\BusinessRepository($app['pdo']))->financeInvoicesPage($year,$page,$perPage,$query,$jobId);render('admin/finance/invoices','Faturat',compact('year','query','jobId','result'));
