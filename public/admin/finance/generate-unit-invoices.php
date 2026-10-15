<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
$year=filter_var($_POST['fiscal_year']??null,FILTER_VALIDATE_INT);if(!$year){http_response_code(400);exit('Invalid year.');}
try{$result=(new App\Service\BusinessUnitService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->generateAutomatic((int)$year,(int)$user['id'],$app['config']->int('INVOICE_DUE_DAYS'));flash('success',$result['created'].' fatura të njësive u gjeneruan; '.$result['skipped'].' u kaluan.');}
catch(Throwable $e){error_log($e->__toString());flash('error','Faturat e njësive nuk mund të gjeneroheshin.');}
redirect('/admin/finance/index.php?year='.(int)$year);
