<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$app['auth']->requireRole(['ADMIN']);
$year=max(2000,min(2100,(int)($_GET['year']??date('Y'))));$part=max(1,(int)($_GET['part']??1));$language=App\Billing\InvoiceTranslations::normalize($_GET['lang']??'sq');
set_time_limit(0);session_write_close();
try{$pdf=(new App\Billing\InvoiceDocumentService($app['pdo'],$app['config']))->bulk($year,$part,$language);}
catch(DomainException $e){http_response_code(404);header('Content-Type: text/plain; charset=UTF-8');exit($e->getMessage());}
send_download($pdf,'faturat-'.$year.'-pjesa-'.$part.'-'.$language.'.pdf','application/pdf');
