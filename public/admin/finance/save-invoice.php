<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
$year=filter_var($_POST['fiscal_year']??date('Y'),FILTER_VALIDATE_INT);if($year===false||$year<2000||$year>2100)$year=(int)date('Y');
$invoiceId=null;
try{$invoiceId=(new App\Service\FinancialLedgerService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->createInvoice($_POST,(int)$user['id']);flash('success','Fatura u regjistrua me UNIREF.');}
catch(DomainException $e){flash('error',$e->getMessage());}
catch(PDOException $e){if(in_array((int)($e->errorInfo[1]??0),[2601,2627],true))flash('error','Ky biznes ka faturë për vitin ose numri i faturës është përdorur.');else{error_log($e->__toString());flash('error','Fatura nuk mund të ruhej.');}}
catch(Throwable $e){error_log($e->__toString());flash('error','Fatura nuk mund të ruhej.');}
redirect($invoiceId!==null?'/admin/finance/invoice.php?id='.$invoiceId:'/admin/finance/invoices.php?'.http_build_query(['year'=>$year]));
