<?php
declare(strict_types=1);
namespace App\Service;
use App\Support\Config;
use DomainException;
use PDO;
use Throwable;
final class TrackedMessageService
{
    public function __construct(private PDO $pdo,private Config $config,private GraphMailer $mailer){}
    public function send(string $type,string $email,string $subject,string $intro,string $action,string $targetPath,string $button,int $actorId,?string $entityType=null,?int $entityId=null): int
    {
        if(filter_var($email,FILTER_VALIDATE_EMAIL)===false||!str_starts_with($targetPath,'/'))throw new DomainException('Emaili ose destinacioni nuk është valid.');
        $selector=bin2hex(random_bytes(8));$secret=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$id=null;
        $this->pdo->beginTransaction();
        try{$q=$this->pdo->prepare('INSERT dbo.outbound_messages(message_type,recipient_email,subject,related_entity_type,related_entity_id,created_by_user_id) OUTPUT inserted.id VALUES(:type,:email,:subject,:entity_type,:entity_id,:actor)');$q->execute(['type'=>$type,'email'=>$email,'subject'=>$subject,'entity_type'=>$entityType,'entity_id'=>$entityId,'actor'=>$actorId]);$id=(int)$q->fetchColumn();$this->pdo->prepare('INSERT dbo.message_action_links(message_id,action_code,selector,secret_hash,target_path,expires_at) VALUES(:message,:action,:selector,CONVERT(binary(32),:hash,2),:target,DATEADD(day,30,SYSUTCDATETIME()))')->execute(['message'=>$id,'action'=>$action,'selector'=>$selector,'hash'=>hash('sha256',$secret),'target'=>$targetPath]);$this->pdo->prepare("INSERT dbo.message_events(message_id,event_code) VALUES(:id,'QUEUED')")->execute(['id'=>$id]);$this->pdo->commit();
            $url=rtrim($this->config->string('APP_URL'),'/').'/message-action.php?s='.$selector.'&t='.rawurlencode($secret);$this->mailer->sendHtml($email,$subject,'<p>'.htmlspecialchars($intro,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($button,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</a></p>');$this->pdo->prepare("UPDATE dbo.outbound_messages SET status_code='SENT',sent_at=SYSUTCDATETIME() WHERE id=:id")->execute(['id'=>$id]);$this->pdo->prepare("INSERT dbo.message_events(message_id,event_code) VALUES(:id,'SENT')")->execute(['id'=>$id]);return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($id!==null){$this->pdo->prepare("UPDATE dbo.outbound_messages SET status_code='FAILED',failed_at=SYSUTCDATETIME(),failure_message=:error WHERE id=:id")->execute(['id'=>$id,'error'=>mb_substr($e->getMessage(),0,1000)]);$this->pdo->prepare("INSERT dbo.message_events(message_id,event_code) VALUES(:id,'FAILED')")->execute(['id'=>$id]);}throw $e;}
    }
}
