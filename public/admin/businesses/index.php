<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$user=$app['auth']->requireUser();
$filters=['q'=>trim((string)($_GET['q']??'')),'nace'=>trim((string)($_GET['nace']??'')),'classification'=>(string)($_GET['classification']??''),'atk_status'=>(string)($_GET['atk_status']??'')];
$result=(new App\Repository\BusinessRepository($app['pdo']))->search($filters,max(1,(int)($_GET['page']??1)),$app['config']->int('PAGE_SIZE'));
$heading='Regjistri i bizneseve';$description='Pamje e normalizuar e ARBK me statusin ATK dhe klasifikimin komunal.';
render('admin/businesses/index',$heading,compact('result','filters','heading','description'));
