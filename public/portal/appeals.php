<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
$user=$app['auth']->requireBusiness();
$repository=new App\Repository\PortalRepository($app['pdo']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
    try{(new App\Service\InvoiceAppealService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->submit((int)($_POST['invoice_id']??0),$user['id'],(string)($_POST['subject']??''),(string)($_POST['appeal_text']??''));flash('success','Ankesa u dërgua te koordinatori.');redirect('/portal/appeals.php');}
    catch(DomainException $e){flash('error',$e->getMessage());}
}
$appeals=$repository->appeals($user['id']);$invoices=$repository->invoices($user['id']);
render('portal/appeals','Ankesat',compact('appeals','invoices'));
