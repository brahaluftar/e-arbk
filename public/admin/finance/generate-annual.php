<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
try{$jobId=(new App\Billing\AnnualInvoiceJobService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->enqueue((string)($_POST['from_date']??''),(int)$user['id'],$app['config']->int('INVOICE_DUE_DAYS'));flash('success','Gjenerimi u vendos në radhë si job #'.$jobId.'. Worker-i CLI do ta përpunojë me batch-e.');}
catch(DomainException $e){flash('error',$e->getMessage());}catch(Throwable $e){$reference=gmdate('YmdHis').'-'.bin2hex(random_bytes(3));error_log('Annual invoice enqueue '.$reference."\n".$e->__toString());flash('error','Job-i nuk mund të krijohej. Referenca: '.$reference);}
redirect('/admin/finance/index.php');
