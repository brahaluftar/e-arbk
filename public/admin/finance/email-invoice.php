<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
$id=(int)($_POST['invoice_id']??0);$language=App\Billing\InvoiceTranslations::normalize($_POST['lang']??'sq');
run_action(static function()use($app,$id,$language,$user){
    $q=$app['pdo']->prepare('SELECT i.id,i.invoice_number,i.uniref,i.business_id,a.EMAIL,(SELECT TOP(1) u.email FROM dbo.business_user_links l JOIN dbo.app_users u ON u.id=l.user_id WHERE l.business_id=i.business_id AND l.ended_at IS NULL AND u.is_active=1 ORDER BY l.assigned_at) user_email FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id WHERE i.id=:id');$q->execute(['id'=>$id]);$invoice=$q->fetch();$q->closeCursor();
    if(!$invoice)throw new DomainException('Fatura nuk u gjet.');
    if(empty($invoice['uniref']))throw new DomainException('Fatura nuk ka UNIREF; PDF-ja nuk mund të dërgohet.');
    $email=trim((string)($_POST['email']??''))?:((string)($invoice['user_email']??'')?:trim((string)($invoice['EMAIL']??'')));
    if(filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new DomainException('Biznesi nuk ka email valid. Shënoni emailin në formular.');
    (new App\Service\TrackedMessageService($app['pdo'],$app['config'],new App\Service\GraphMailer($app['config'])))->send('INVOICE',$email,'Fatura '.$invoice['invoice_number'],'Fatura juaj është gati për shkarkim.','DOWNLOAD_INVOICE','/portal/invoice-pdf.php?id='.$id.'&lang='.$language,'Shkarko faturën',(int)$user['id'],'INVOICE',$id);
    $app['pdo']->prepare('UPDATE dbo.business_invoices SET emailed_at=SYSUTCDATETIME() WHERE id=:id')->execute(['id'=>$id]);
    (new App\Billing\InvoiceHistory($app['pdo']))->record($id,'EMAILED',['email'=>$email,'language'=>$language],null,(int)$user['id']);
    return 'Fatura u dërgua me email te '.$email.'.';
},'Fatura nuk mund të dërgohej.');
redirect(safe_return($_POST['return']??null,'/admin/finance/invoice.php?id='.$id));
