<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();require_post();
run_action(static function()use($app,$user){(new App\Service\BusinessAccessService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->reviewRequest((int)($_POST['request_id']??0),(int)$user['id'],(string)($_POST['decision']??''),(string)($_POST['note']??''));return 'Kërkesa u shqyrtua.';},'Kërkesa nuk mund të shqyrtohej.');
redirect('/admin/business-access/index.php');
