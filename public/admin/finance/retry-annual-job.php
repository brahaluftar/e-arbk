<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
try{$jobId=(int)($_POST['job_id']??0);$count=(new App\Billing\AnnualInvoiceJobService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->retryFailed($jobId);flash('success',"$count rreshta të dështuar u kthyen në radhë për riprovim.");}catch(DomainException $e){flash('error',$e->getMessage());}catch(Throwable $e){error_log($e->__toString());flash('error','Riprova nuk mund të nisej.');}redirect('/admin/finance/index.php');
