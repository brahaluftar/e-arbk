<?php
declare(strict_types=1);
// Edit, cancel or restore an invoice. A comment is required and is stored in the invoice history.
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
$invoiceId=filter_var($_POST['invoice_id']??null,FILTER_VALIDATE_INT);if(!$invoiceId){http_response_code(400);exit('Invalid invoice ID.');}
$action=(string)($_POST['action']??'edit');
run_action(static function()use($app,$invoiceId,$action,$user){
    $ledger=new App\Service\FinancialLedgerService($app['pdo'],new App\Service\AuditLogger($app['pdo']));
    if($action==='cancel'){$ledger->setCancelled((int)$invoiceId,true,$_POST['comment']??null,(int)$user['id']);return 'Fatura u anulua.';}
    if($action==='restore'){$ledger->setCancelled((int)$invoiceId,false,$_POST['comment']??null,(int)$user['id']);return 'Fatura u rikthye.';}
    $ledger->updateInvoice((int)$invoiceId,$_POST,(int)$user['id']);return 'Fatura u përditësua dhe ndryshimi u ruajt në historik.';
},'Fatura nuk mund të përditësohej.');
redirect('/admin/finance/invoice.php?id='.(int)$invoiceId);
