<?php
declare(strict_types=1);
// Invoice PDF for staff and for business users linked to the invoice's business.
$app=require dirname(__DIR__,2).'/bootstrap.php';$user=$app['auth']->requireUser();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$municipal=App\Security\Auth::isStaff($user);
$businessAccess=$user['role_code']==='BUSINESS'&&$id&&(new App\Repository\PortalRepository($app['pdo']))->userCanAccessInvoice($user['id'],(int)$id);
if(!$id||(!$municipal&&!$businessAccess)){http_response_code(403);exit('Access denied.');}
$language=App\Billing\InvoiceTranslations::normalize($_GET['lang']??'sq');
try{$pdf=(new App\Billing\InvoiceDocumentService($app['pdo'],$app['config']))->render((int)$id,$language);}
catch(DomainException $e){http_response_code(404);header('Content-Type: text/plain; charset=UTF-8');exit($e->getMessage());}
send_download($pdf,'fatura-'.$id.'-'.$language.'.pdf','application/pdf');
