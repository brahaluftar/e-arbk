<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$user=$app['auth']->requireUser();
$filters=['q'=>trim((string)($_GET['q']??'')),'nace'=>trim((string)($_GET['nace']??'')),'classification'=>(string)($_GET['classification']??'UNCLASSIFIED'),'atk_status'=>(string)($_GET['atk_status']??''),'pronare_grua'=>(string)($_GET['pronare_grua']??''),'pronar_veteran'=>(string)($_GET['pronar_veteran']??'')];
$result=(new App\Repository\BusinessRepository($app['pdo']))->search($filters,max(1,(int)($_GET['page']??1)),$app['config']->int('PAGE_SIZE'));
$heading='Klasifikimi NACE';$description='Zgjidhni vetëm ndër kategoritë që nace_list lejon për kodin NACE të biznesit.';
render('admin/businesses/index',$heading,compact('result','filters','heading','description'));
