<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);
$runs=$app['pdo']->query("SELECT TOP(100) r.id,r.source_type,r.original_filename,r.status,r.total_rows,r.valid_rows,r.inserted_rows,r.updated_rows,r.skipped_rows,r.error_rows,r.error_message,r.created_at,r.started_at,r.completed_at,u.full_name uploaded_by FROM dbo.business_import_runs r LEFT JOIN dbo.app_users u ON u.id=r.uploaded_by_user_id ORDER BY r.id DESC")->fetchAll();
render('admin/imports/index','Importet Excel',compact('runs'));
