<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';
$user=$app['auth']->requireRole(['ADMIN','OFFICIAL']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
$businessId=filter_var($_POST['business_id']??null,FILTER_VALIDATE_INT);$mapping=strtoupper(trim((string)($_POST['mapping_key']??'')));
if(!$businessId||preg_match('/\A[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}\z/',$mapping)!==1){flash('error','Zgjedhja nuk është e vlefshme.');redirect('/public/admin/businesses/classification.php');}
try{(new App\Service\ClassificationService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->assignManual((int)$businessId,$mapping,$user['id']);flash('success','Klasifikimi u ruajt dhe u regjistrua në auditim.');}
catch(DomainException $e){flash('error',$e->getMessage());redirect('/public/admin/businesses/view.php?id='.(int)$businessId);}
if(($_POST['action']??'')==='next'){
    $statement=$app['pdo']->prepare("SELECT TOP (1) a.REGULATION_ID FROM dbo.ARBK_LIST a WHERE a.REGULATION_ID>:id AND NOT EXISTS(SELECT 1 FROM dbo.business_nace_assignments x WHERE x.business_id=a.REGULATION_ID AND x.ended_at IS NULL) ORDER BY a.REGULATION_ID");$statement->execute(['id'=>$businessId]);$next=$statement->fetchColumn();
    if($next!==false) redirect('/public/admin/businesses/view.php?id='.(int)$next);
}
redirect('/public/admin/businesses/view.php?id='.(int)$businessId);
