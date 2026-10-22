<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_post();
    run_action(static function()use($app,$user){(new App\Service\InvoiceAppealService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->transition((int)($_POST['appeal_id']??0),(string)($_POST['to_status']??''),(int)$user['id'],array_map(static fn($v)=>is_string($v)?$v:'',$_POST));return 'Statusi i ankesës u përditësua.';},'Ankesa nuk mund të përditësohej.');
    redirect('/admin/appeals/index.php');
}
$appeals=$app['pdo']->query("SELECT ap.*,i.invoice_number,i.uniref,i.business_name_snapshot,u.full_name submitter_name FROM dbo.invoice_appeals ap JOIN dbo.business_invoices i ON i.id=ap.invoice_id JOIN dbo.app_users u ON u.id=ap.submitted_by_user_id ORDER BY CASE ap.status_code WHEN 'SUBMITTED' THEN 0 WHEN 'ANSWERED' THEN 2 WHEN 'REJECTED' THEN 2 ELSE 1 END,ap.submitted_at")->fetchAll();
render('admin/appeals/index','Ankesat e faturave',compact('appeals'));
