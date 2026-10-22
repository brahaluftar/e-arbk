<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();require_post();
$businessId=(int)($_POST['business_id']??0);
run_action(static function()use($app,$businessId,$user){(new App\Permitting\PermitService($app['pdo'],$app['config'],new App\Service\AuditLogger($app['pdo']),new App\Document\DocumentStore(dirname(__DIR__,3).'/var/documents')))->issue($businessId,(string)($_POST['valid_from']??''),(string)($_POST['valid_until']??''),(int)$user['id']);return 'Leja e punës u gjenerua.';},'Leja nuk mund të gjenerohej.');
redirect('/admin/businesses/view.php?id='.$businessId.'#lejet');
