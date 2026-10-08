<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN','OFFICIAL']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
$service=new App\Service\BusinessAccessService($app['pdo'],new App\Service\AuditLogger($app['pdo']));
try{if(($_POST['action']??'')==='unlink'){$service->unlink((int)($_POST['link_id']??0),$user['id'],(string)($_POST['reason']??''));flash('success','Lidhja u ndërpre.');}else{$service->link((int)($_POST['user_id']??0),(int)($_POST['business_id']??0),$user['id']);flash('success','Përdoruesi u lidh me biznesin.');}}
catch(DomainException $e){flash('error',$e->getMessage());}catch(Throwable $e){error_log($e->__toString());flash('error','Veprimi nuk mund të përfundohej.');}
redirect('/admin/business-access/index.php');
