<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();
$links=$app['pdo']->query("SELECT TOP (500) l.id,u.full_name,u.email,a.Emri legal_name,a.NRBIZ registration_number,assigner.full_name assigned_by,l.assigned_at FROM dbo.business_user_links l JOIN dbo.app_users u ON u.id=l.user_id JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=l.business_id JOIN dbo.app_users assigner ON assigner.id=l.assigned_by_user_id WHERE l.ended_at IS NULL ORDER BY l.assigned_at DESC,l.id DESC")->fetchAll();
$requests=$app['pdo']->query("SELECT r.id,r.requested_at,u.full_name,u.email,a.Emri legal_name,a.NRBIZ registration_number FROM dbo.business_access_requests r JOIN dbo.app_users u ON u.id=r.user_id JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=r.business_id WHERE r.status_code='PENDING' ORDER BY r.requested_at,r.id")->fetchAll();
$invites=$app['pdo']->query("SELECT TOP (50) i.email,i.created_at,i.expires_at,i.opened_at,i.consumed_at,m.status_code message_status FROM dbo.business_registration_invites i OUTER APPLY(SELECT TOP(1) status_code FROM dbo.outbound_messages m WHERE m.related_entity_type='REGISTRATION_INVITE' AND m.related_entity_id=i.id ORDER BY m.id DESC) m ORDER BY i.created_at DESC")->fetchAll();
render('admin/business-access/index','Qasjet e bizneseve',compact('links','requests','invites'));
