<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN','OFFICIAL']);
$service=new App\Service\InvoiceAppealService($app['pdo'],new App\Service\AuditLogger($app['pdo']));
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
    try{$service->transition((int)($_POST['appeal_id']??0),(string)($_POST['to_status']??''),(int)$user['id'],array_map(static fn($v)=>is_string($v)?$v:'',$_POST));flash('success','Statusi i ankesës u përditësua.');}
    catch(DomainException $e){flash('error',$e->getMessage());}catch(Throwable $e){error_log($e->__toString());flash('error','Ankesa nuk mund të përditësohej.');}
    redirect('/admin/appeals/index.php');
}
$appeals=$app['pdo']->query("SELECT ap.*,i.invoice_number,i.uniref,i.business_name_snapshot,u.full_name submitter_name FROM dbo.invoice_appeals ap JOIN dbo.business_invoices i ON i.id=ap.invoice_id JOIN dbo.app_users u ON u.id=ap.submitted_by_user_id ORDER BY CASE ap.status_code WHEN 'SUBMITTED' THEN 0 WHEN 'ANSWERED' THEN 2 ELSE 1 END,ap.submitted_at")->fetchAll();
render('admin/appeals/index','Ankesat e faturave',compact('appeals'));
