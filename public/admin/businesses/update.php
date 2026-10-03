<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$user=$app['auth']->requireRole(['ADMIN','OFFICIAL']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
$businessId=filter_var($_POST['business_id']??null,FILTER_VALIDATE_INT);if(!$businessId){http_response_code(400);exit('Invalid business ID.');}
try{(new App\Service\BusinessEditService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->update((int)$businessId,$_POST,(int)$user['id']);flash('success','Të dhënat e biznesit u përditësuan dhe u regjistruan në auditim.');}
catch(DomainException $e){flash('error',$e->getMessage());}
catch(Throwable $e){error_log($e->__toString());flash('error','Ndryshimet nuk mund të ruheshin.');}
redirect('/admin/businesses/view.php?id='.(int)$businessId);
