<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN','OFFICIAL']);
$query=trim((string)($_GET['q']??''));
$users=$app['pdo']->query("SELECT id,email,full_name FROM dbo.app_users WHERE role_code='BUSINESS' AND is_active=1 ORDER BY full_name,email")->fetchAll();
$businessStatement=$app['pdo']->prepare("SELECT TOP (100) REGULATION_ID id,Emri legal_name,NRBIZ registration_number FROM dbo.ARBK_LIST WHERE :query='' OR Emri LIKE :name OR NRBIZ LIKE :number ORDER BY Emri,REGULATION_ID");$businessStatement->execute(['query'=>$query,'name'=>'%'.$query.'%','number'=>'%'.$query.'%']);$businesses=$businessStatement->fetchAll();
$links=$app['pdo']->query("SELECT l.id,u.full_name,u.email,a.Emri legal_name,a.NRBIZ registration_number,assigner.full_name assigned_by,l.assigned_at FROM dbo.business_user_links l JOIN dbo.app_users u ON u.id=l.user_id JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=l.business_id JOIN dbo.app_users assigner ON assigner.id=l.assigned_by_user_id WHERE l.ended_at IS NULL ORDER BY l.assigned_at DESC,l.id DESC")->fetchAll();
render('admin/business-access/index','Qasjet e bizneseve',compact('users','businesses','links','query'));
