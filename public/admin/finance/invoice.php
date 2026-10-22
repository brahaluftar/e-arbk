<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);
header('Cache-Control: private, no-store');
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id){http_response_code(400);exit('Invalid invoice ID.');}
$repository=new App\Repository\InvoiceRepository($app['pdo']);$invoice=$repository->find((int)$id);
if($invoice===null){http_response_code(404);exit('Invoice not found.');}
$payments=$repository->payments((int)$id);
$history=(new App\Billing\InvoiceHistory($app['pdo']))->forInvoice((int)$id);
render('admin/finance/invoice','Fatura '.$invoice['invoice_number'],compact('invoice','payments','history'));
