<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN','OFFICIAL']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
try{(new App\Service\BusinessAccessService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->reviewRequest((int)($_POST['request_id']??0),$user['id'],(string)($_POST['decision']??''),(string)($_POST['note']??''));flash('success','Kërkesa u shqyrtua.');}
catch(DomainException $e){flash('error',$e->getMessage());}catch(Throwable $e){error_log($e->__toString());flash('error','Kërkesa nuk mund të shqyrtohej.');}
redirect('/admin/business-access/index.php');
