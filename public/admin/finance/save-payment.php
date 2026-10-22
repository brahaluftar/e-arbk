<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
$invoiceId=(int)($_POST['invoice_id']??0);
run_action(static function()use($app,$user){(new App\Service\FinancialLedgerService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->recordPayment($_POST,(int)$user['id']);return 'Pagesa u regjistrua.';},'Pagesa nuk mund të ruhej.');
redirect(safe_return($_POST['return']??null,'/admin/finance/invoice.php?id='.$invoiceId));
