<?php
declare(strict_types=1);
// Bulk invitations, in rounds of BATCH addresses per click so the request cannot time out.
// An address is invited again when its open invitation was never delivered (email failed).
const BATCH=200;
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();require_post();
set_time_limit(0);
$candidates="SELECT DISTINCT LTRIM(RTRIM(a.EMAIL)) email FROM dbo.ARBK_LIST a WHERE a.EMAIL IS NOT NULL AND LTRIM(RTRIM(a.EMAIL))<>''
    AND NOT EXISTS(SELECT 1 FROM dbo.app_users u WHERE u.normalized_email=UPPER(LTRIM(RTRIM(a.EMAIL))))
    AND NOT EXISTS(SELECT 1 FROM dbo.business_registration_invites i JOIN dbo.outbound_messages m ON m.related_entity_type='REGISTRATION_INVITE' AND m.related_entity_id=i.id AND m.status_code='SENT'
        WHERE i.normalized_email=UPPER(LTRIM(RTRIM(a.EMAIL))) AND i.consumed_at IS NULL AND i.expires_at>SYSUTCDATETIME())";
$rows=$app['pdo']->query('SELECT TOP ('.BATCH.') email FROM ('.$candidates.') c ORDER BY email')->fetchAll();
$registration=new App\Security\BusinessRegistrationService($app['pdo'],new App\Service\AuditLogger($app['pdo']));$messages=new App\Service\TrackedMessageService($app['pdo'],$app['config'],new App\Service\GraphMailer($app['config']));
$sent=0;$failed=0;
foreach($rows as $row){
    if(filter_var((string)$row['email'],FILTER_VALIDATE_EMAIL)===false){$failed++;continue;}
    try{$invite=$registration->invite((string)$row['email'],(int)$user['id']);$messages->send('REGISTRATION_INVITE',$invite['email'],'Ftesë për regjistrim','Jeni ftuar të regjistroheni në sistemin e tarifave të bizneseve të Komunës së Prishtinës.','REGISTER','/auth/register-business.php?token='.rawurlencode($invite['token']),'Regjistrohu',(int)$user['id'],'REGISTRATION_INVITE',$invite['id'],App\Security\BusinessRegistrationService::INVITE_DAYS);$sent++;}
    catch(Throwable $e){error_log($e->__toString());$failed++;}
}
$remaining=(int)$app['pdo']->query('SELECT COUNT(*) FROM ('.$candidates.') c')->fetchColumn();
flash($failed?'info':'success',"Ftesa të dërguara: $sent; dështime: $failed; ende pa ftesë: $remaining.".($remaining>0?' Klikoni sërish për grupin tjetër.':''));
redirect('/admin/business-access/index.php');
