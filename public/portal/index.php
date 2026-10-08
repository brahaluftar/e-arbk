<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
$user=$app['auth']->requireBusiness();
$repository=new App\Repository\PortalRepository($app['pdo']);
$businesses=$repository->businesses($user['id']);
render('portal/index','Bizneset e mia',compact('businesses'));
