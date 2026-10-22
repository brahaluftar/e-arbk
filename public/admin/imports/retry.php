<?php
declare(strict_types=1);
// Puts a failed (or interrupted) import back in the queue without uploading the file again.
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
run_action(static function()use($app,$user){(new App\Import\BusinessImportService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->retry((int)($_POST['run_id']??0),(int)$user['id']);return 'Importi u kthye në radhë.';},'Importi nuk mund të riprovohej.');
redirect('/admin/imports/index.php');
