<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
$unitId=filter_var($_POST['unit_id']??null,FILTER_VALIDATE_INT);$businessId=filter_var($_POST['business_id']??null,FILTER_VALIDATE_INT);$year=filter_var($_POST['fiscal_year']??null,FILTER_VALIDATE_INT);
if(!$unitId||!$businessId||!$year){http_response_code(400);exit('Invalid request.');}
run_action(static function()use($app,$unitId,$year,$user){(new App\Service\BusinessUnitService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->generateInvoice((int)$unitId,(int)$year,(int)$user['id'],$app['config']->int('INVOICE_DUE_DAYS'));return 'Fatura e njësisë për vitin '.(int)$year.' u gjenerua.';},'Fatura nuk mund të gjenerohej.');
redirect('/admin/businesses/view.php?id='.(int)$businessId.'#faturat');
