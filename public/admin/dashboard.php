<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
$user=$app['auth']->requireUser();
$repository=new App\Repository\BusinessRepository($app['pdo']);
$stats=$repository->dashboard();
$year=filter_var($_GET['year']??date('Y'),FILTER_VALIDATE_INT);
if($year===false||$year<2000||$year>2100)$year=(int)date('Y');
$financialStats=$user['role_code']==='ADMIN'?$repository->financialDashboard($year):null;
render('admin/dashboard','Paneli',compact('stats','financialStats'));
