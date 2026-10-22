<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();require_post();
$service=new App\Service\BusinessAccessService($app['pdo'],new App\Service\AuditLogger($app['pdo']));
run_action(static function()use($app,$service,$user){
    if(($_POST['action']??'')==='unlink'){$service->unlink((int)($_POST['link_id']??0),(int)$user['id'],(string)($_POST['reason']??''));return 'Lidhja u ndërpre.';}
    // Link by the portal user's email and the business number instead of long drop-down lists.
    $q=$app['pdo']->prepare("SELECT id FROM dbo.app_users WHERE normalized_email=:email AND role_code='BUSINESS'");$q->execute(['email'=>mb_strtoupper(trim((string)($_POST['email']??'')),'UTF-8')]);$userId=$q->fetchColumn();$q->closeCursor();
    if($userId===false)throw new DomainException('Nuk u gjet llogari biznesi me këtë email.');
    $q=$app['pdo']->prepare('SELECT REGULATION_ID FROM dbo.ARBK_LIST WHERE LTRIM(RTRIM(NRBIZ))=:nrbiz');$q->execute(['nrbiz'=>trim((string)($_POST['registration_number']??''))]);$businessId=$q->fetchColumn();$q->closeCursor();
    if($businessId===false)throw new DomainException('Nuk u gjet biznes me këtë NRB.');
    $service->link((int)$userId,(int)$businessId,(int)$user['id']);return 'Përdoruesi u lidh me biznesin.';
},'Veprimi nuk mund të përfundohej.');
redirect('/admin/business-access/index.php');
