<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();require_post();
$unitId=filter_var($_POST['unit_id']??null,FILTER_VALIDATE_INT);$businessId=filter_var($_POST['business_id']??null,FILTER_VALIDATE_INT);if(!$unitId||!$businessId){http_response_code(400);exit('Invalid request.');}
run_action(static function()use($app,$unitId,$businessId,$user){(new App\Service\BusinessUnitService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->update((int)$unitId,(int)$businessId,$_POST,(int)$user['id']);return 'Njësia u përditësua.';},'Njësia nuk mund të përditësohej.');
redirect('/admin/businesses/view.php?id='.(int)$businessId.'#njesite');
