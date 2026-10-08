<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);
$messages=$app['pdo']->query("SELECT m.*,l.action_code,l.first_opened_at,l.open_count,l.completed_at FROM dbo.outbound_messages m LEFT JOIN dbo.message_action_links l ON l.message_id=m.id ORDER BY m.queued_at DESC,m.id DESC OFFSET 0 ROWS FETCH NEXT 500 ROWS ONLY")->fetchAll();
$summary=$app['pdo']->query("SELECT COUNT_BIG(*) total,SUM(CASE WHEN status_code='SENT' THEN 1 ELSE 0 END) sent,SUM(CASE WHEN status_code='FAILED' THEN 1 ELSE 0 END) failed FROM dbo.outbound_messages")->fetch();
render('admin/messages/index','Raportet e emailit',compact('messages','summary'));
