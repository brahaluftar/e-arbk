<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();require_post();
$businessId=(int)($_POST['business_id']??0);
run_action(static function()use($app,$user){(new App\Permitting\PermitService($app['pdo'],$app['config'],new App\Service\AuditLogger($app['pdo']),new App\Document\DocumentStore(dirname(__DIR__,3).'/var/documents')))->revoke((int)($_POST['permit_id']??0),(string)($_POST['reason']??''),(int)$user['id']);return 'Leja u revokua.';},'Leja nuk mund të revokohej.');
redirect('/admin/businesses/view.php?id='.$businessId.'#lejet');
