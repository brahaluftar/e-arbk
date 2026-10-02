<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);
$runs=$app['pdo']->query("SELECT TOP(100) id,source_type,original_filename,status,total_rows,valid_rows,inserted_rows,updated_rows,skipped_rows,error_rows,error_message,created_at,started_at,completed_at FROM dbo.business_import_runs ORDER BY id DESC")->fetchAll();
render('admin/imports/index','Importi i bizneseve',compact('runs'));
