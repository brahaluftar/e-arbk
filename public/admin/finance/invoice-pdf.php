<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$app['auth']->requireStaff();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id){http_response_code(404);exit('Not found.');}
$language=App\Billing\InvoiceTranslations::normalize($_GET['lang']??'sq');
try{$pdf=(new App\Billing\InvoiceDocumentService($app['pdo'],$app['config']))->render((int)$id,$language);}
catch(DomainException $e){http_response_code(404);header('Content-Type: text/plain; charset=UTF-8');exit($e->getMessage());}
send_download($pdf,'fatura-'.$id.'-'.$language.'.pdf','application/pdf');
