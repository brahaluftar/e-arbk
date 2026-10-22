<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
run_action(static function()use($app,$user){$jobId=(new App\Billing\AnnualInvoiceJobService($app['pdo'],new App\Service\AuditLogger($app['pdo']),App\Billing\BillingPolicy::fromConfig($app['config'])))->enqueue((string)($_POST['from_date']??''),(int)$user['id'],$app['config']->int('INVOICE_DUE_DAYS'));return 'Gjenerimi u vendos në radhë si job #'.$jobId.'. Worker-i i përpunon faturat në sfond.';},'Job-i nuk mund të krijohej.');
redirect('/admin/finance/annual.php');
