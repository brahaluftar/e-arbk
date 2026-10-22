<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';$user=$app['auth']->requireBusiness();
require_post();
try{(new App\Service\BusinessAccessService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->requestByRegistrationNumber($user['id'],(string)($_POST['registration_number']??''));flash('success','Kërkesa u dërgua për verifikim dhe aprovim.');}
catch(DomainException $e){flash('error',$e->getMessage());}catch(Throwable $e){error_log($e->__toString());flash('error','Kërkesa nuk mund të dërgohej.');}
redirect('/portal/index.php');
