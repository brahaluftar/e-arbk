<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN','OFFICIAL']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
try{$invite=(new App\Security\BusinessRegistrationService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->invite((string)($_POST['email']??''),$user['id']);$target='/auth/register-business.php?token='.rawurlencode($invite['token']);(new App\Service\TrackedMessageService($app['pdo'],$app['config'],new App\Service\GraphMailer($app['config'])))->send('REGISTRATION_INVITE',$invite['email'],'Ftesë për regjistrim','Jeni ftuar të regjistroheni në sistemin e tarifave të bizneseve.','REGISTER',$target,'Regjistrohu',(int)$user['id']);flash('success','Ftesa u krijua dhe u dërgua me email.');}
catch(DomainException $e){flash('error',$e->getMessage());}catch(Throwable $e){error_log($e->__toString());flash('error','Ftesa nuk mund të krijohej.');}
redirect('/admin/business-access/index.php');
