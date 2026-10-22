<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$user=$app['auth']->requireEditor();require_post();
$businessId=filter_var($_POST['business_id']??null,FILTER_VALIDATE_INT);if(!$businessId){http_response_code(400);exit('Invalid business ID.');}
$returnPath=business_list_path($_POST['return']??'');$returnQuery=(string)(parse_url($returnPath,PHP_URL_QUERY)??'');
run_action(static function()use($app,$businessId,$user){(new App\Service\BusinessEditService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->update((int)$businessId,$_POST,(int)$user['id']);return 'Të dhënat e biznesit u përditësuan dhe u regjistruan në auditim.';},'Ndryshimet nuk mund të ruheshin.');
redirect('/admin/businesses/view.php?id='.(int)$businessId.($returnQuery===''?'':'&return='.rawurlencode($returnQuery)));
