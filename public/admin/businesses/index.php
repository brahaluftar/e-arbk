<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$user=$app['auth']->requireStaff();
$filters=business_filters($_GET);
$result=(new App\Repository\BusinessRepository($app['pdo']))->search($filters,max(1,(int)($_GET['page']??1)),$app['config']->int('PAGE_SIZE'));
render('admin/businesses/index','Bizneset',compact('result','filters'));
