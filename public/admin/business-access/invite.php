<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireEditor();require_post();
run_action(static function()use($app,$user){
    $invite=(new App\Security\BusinessRegistrationService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->invite((string)($_POST['email']??''),(int)$user['id']);
    (new App\Service\TrackedMessageService($app['pdo'],$app['config'],new App\Service\GraphMailer($app['config'])))->send('REGISTRATION_INVITE',$invite['email'],'Ftesë për regjistrim','Jeni ftuar të regjistroheni në sistemin e tarifave të bizneseve të Komunës së Prishtinës.','REGISTER','/auth/register-business.php?token='.rawurlencode($invite['token']),'Regjistrohu',(int)$user['id'],'REGISTRATION_INVITE',$invite['id'],App\Security\BusinessRegistrationService::INVITE_DAYS);
    return 'Ftesa u dërgua me email te '.$invite['email'].'.';
},'Ftesa nuk mund të dërgohej.');
redirect('/admin/business-access/index.php');
