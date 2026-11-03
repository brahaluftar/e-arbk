<?php
declare(strict_types=1);
// Rebuilds the revenue from one payment report only: every payment is deleted, then the report's rows are applied again.
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
$batchId=(int)($_POST['batch_id']??0);
run_action(static function()use($app,$user,$batchId){
    if(mb_strtoupper(trim((string)($_POST['confirm']??'')))!=='FSHI')throw new DomainException('Shkruani FSHI për të konfirmuar: të gjitha pagesat aktuale fshihen para rindërtimit.');
    $result=(new App\Billing\PaymentImportService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->restoreFrom($batchId,(int)$user['id'],(string)($_POST['comment']??''));
    return 'Të hyrat u rindërtuan vetëm nga ky raport: '.number_format($result['applied'],0,',','.').' pagesa u aplikuan ('.money($result['amount']).'), '.number_format($result['review']+$result['duplicate'],0,',','.').' rreshta për shqyrtim. Më parë u fshinë '.number_format($result['cleared']['payments'],0,',','.').' pagesa ('.money($result['cleared']['amount']).').';
},'Të hyrat nuk mund të rindërtoheshin.');
redirect($batchId>0?'/admin/finance/payment-import.php?id='.$batchId.'&all=1':'/admin/finance/payments.php');
