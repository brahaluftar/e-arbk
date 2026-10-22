<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
run_action(static function()use($app){$count=(new App\Billing\AnnualInvoiceJobService($app['pdo'],new App\Service\AuditLogger($app['pdo']),App\Billing\BillingPolicy::fromConfig($app['config'])))->retryFailed((int)($_POST['job_id']??0));return "$count rreshta të dështuar u kthyen në radhë.";},'Riprova nuk mund të nisej.');
redirect('/admin/finance/annual.php');
