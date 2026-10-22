<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();require_post();
$businessId=filter_var($_POST['business_id']??null,FILTER_VALIDATE_INT);if(!$businessId){http_response_code(400);exit('Invalid business ID.');}
run_action(static function()use($app,$businessId,$user){(new App\Service\BusinessUnitService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->create((int)$businessId,$_POST,(int)$user['id']);return 'Njësia u regjistrua.';},'Njësia nuk mund të regjistrohej.');
redirect('/admin/businesses/view.php?id='.(int)$businessId.'#njesite');
