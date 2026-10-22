<?php
declare(strict_types=1);
// Storno of a payment entered by mistake (the row is kept, marked as reversed, with the reason).
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
$invoiceId=(int)($_POST['invoice_id']??0);
run_action(static function()use($app,$user){(new App\Service\FinancialLedgerService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->reversePayment((int)($_POST['payment_id']??0),(string)($_POST['reason']??''),(int)$user['id']);return 'Pagesa u stornua.';},'Pagesa nuk mund të stornohej.');
redirect(safe_return($_POST['return']??null,'/admin/finance/invoice.php?id='.$invoiceId));
