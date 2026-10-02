<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
$user=$app['auth']->requireUser();
$stats=(new App\Repository\BusinessRepository($app['pdo']))->dashboard();
render('admin/dashboard','Paneli',compact('stats'));
