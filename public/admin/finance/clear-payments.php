<?php
declare(strict_types=1);
// Deletes every payment (manual and imported); the revenue can then be rebuilt from one payment report.
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
run_action(static function()use($app,$user){
    if(mb_strtoupper(trim((string)($_POST['confirm']??'')))!=='FSHI')throw new DomainException('Shkruani FSHI për të konfirmuar fshirjen e të gjitha pagesave.');
    $result=(new App\Billing\PaymentImportService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->clearAll((int)$user['id'],(string)($_POST['comment']??''));
    return 'U fshinë '.number_format($result['payments'],0,',','.').' pagesa ('.money($result['amount']).') nga '.number_format($result['invoices'],0,',','.').' fatura. Faturat janë përsëri të papaguara.';
},'Të hyrat nuk mund të fshiheshin.');
redirect('/admin/finance/payments.php');
