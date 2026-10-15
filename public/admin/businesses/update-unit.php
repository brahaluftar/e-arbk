<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN','OFFICIAL']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
$unitId=filter_var($_POST['unit_id']??null,FILTER_VALIDATE_INT);$businessId=filter_var($_POST['business_id']??null,FILTER_VALIDATE_INT);if(!$unitId||!$businessId){http_response_code(400);exit('Invalid request.');}
try{(new App\Service\BusinessUnitService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->updateContacts((int)$unitId,(int)$businessId,$_POST,(int)$user['id']);flash('success','Kontakti i njësisë u përditësua.');}catch(DomainException $e){flash('error',$e->getMessage());}catch(Throwable $e){error_log($e->__toString());flash('error','Njësia nuk mund të përditësohej.');}
redirect('/admin/businesses/view.php?id='.(int)$businessId);
