<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
$user=$app['auth']->requireUser();
$repository=new App\Repository\BusinessRepository($app['pdo']);
$stats=$repository->dashboard();
$financialStats=$user['role_code']==='ADMIN'?$repository->financialDashboard():null;
render('admin/dashboard','Paneli',compact('stats','financialStats'));
