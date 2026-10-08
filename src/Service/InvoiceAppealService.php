<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\PortalRepository;
use DomainException;
use PDO;
use Throwable;

final class InvoiceAppealService
{
    private const TRANSITIONS=['SUBMITTED'=>['ACCEPTED','REJECTED'],'ACCEPTED'=>['COORDINATOR'],'COORDINATOR'=>['COMMISSION'],'COMMISSION'=>['UNDER_REVIEW'],'UNDER_REVIEW'=>['DECIDED'],'DECIDED'=>['ANSWERED']];
    public function __construct(private PDO $pdo,private AuditLogger $audit) {}

    public function submit(int $invoiceId,int $userId,string $subject,string $text): int
    {
        $subject=trim($subject);$text=trim($text);
        if($invoiceId<1||$subject===''||mb_strlen($subject)>200||$text===''||mb_strlen($text)>10000)throw new DomainException('Plotësoni titullin dhe tekstin e ankesës.');
        if(!(new PortalRepository($this->pdo))->userCanAccessInvoice($userId,$invoiceId))throw new DomainException('Nuk keni qasje në këtë faturë.');
        $this->pdo->beginTransaction();
        try{
            $insert=$this->pdo->prepare("INSERT dbo.invoice_appeals(invoice_id,submitted_by_user_id,subject,appeal_text) OUTPUT inserted.id VALUES(:invoice,:user,:subject,:text)");
            $insert->execute(['invoice'=>$invoiceId,'user'=>$userId,'subject'=>$subject,'text'=>$text]);$id=(int)$insert->fetchColumn();
            $this->pdo->prepare("INSERT dbo.invoice_appeal_events(appeal_id,to_status,comment,actor_user_id) VALUES(:id,'SUBMITTED',:comment,:user)")->execute(['id'=>$id,'comment'=>$text,'user'=>$userId]);
            $this->audit->record($userId,'INVOICE_APPEAL_SUBMITTED','INVOICE_APPEAL',(string)$id,null,['invoice_id'=>$invoiceId,'subject'=>$subject]);
            $this->pdo->commit();return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @param array<string,string> $data */
    public function transition(int $appealId,string $toStatus,int $actorId,array $data=[]): void
    {
        $toStatus=strtoupper(trim($toStatus));$comment=trim($data['comment']??'');
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT status_code FROM dbo.invoice_appeals WITH(UPDLOCK,HOLDLOCK) WHERE id=:id');$q->execute(['id'=>$appealId]);$from=$q->fetchColumn();
            if(!is_string($from)||!in_array($toStatus,self::TRANSITIONS[$from]??[],true))throw new DomainException('Kalimi i statusit nuk lejohet.');
            $set=['status_code=:status'];$params=['status'=>$toStatus,'id'=>$appealId];
            if($toStatus==='COORDINATOR'){$set[]='coordinator_user_id=:actor';$params['actor']=$actorId;}
            if($toStatus==='COMMISSION'){$reference=trim($data['commission_reference']??'');if($reference==='')throw new DomainException('Referenca e komisionit është e detyrueshme.');$set[]='commission_reference=:reference';$params['reference']=$reference;}
            if($toStatus==='UNDER_REVIEW'){$set[]='review_notes=:review';$params['review']=trim($data['review_notes']??$comment);}
            if($toStatus==='DECIDED'){$decision=strtoupper(trim($data['decision_code']??''));$text=trim($data['decision_text']??'');if(!in_array($decision,['APPROVED','PARTIALLY_APPROVED','REJECTED'],true)||$text==='')throw new DomainException('Vendimi dhe arsyetimi janë të detyrueshëm.');$set[]='decision_code=:decision';$set[]='decision_text=:decision_text';$set[]='decided_at=SYSUTCDATETIME()';$params['decision']=$decision;$params['decision_text']=$text;}
            if($toStatus==='ANSWERED')$set[]='answered_at=SYSUTCDATETIME()';
            $this->pdo->prepare('UPDATE dbo.invoice_appeals SET '.implode(',',$set).' WHERE id=:id')->execute($params);
            $this->pdo->prepare('INSERT dbo.invoice_appeal_events(appeal_id,from_status,to_status,comment,actor_user_id) VALUES(:id,:from,:to,:comment,:actor)')->execute(['id'=>$appealId,'from'=>$from,'to'=>$toStatus,'comment'=>$comment?:null,'actor'=>$actorId]);
            $this->audit->record($actorId,'INVOICE_APPEAL_STATUS_CHANGED','INVOICE_APPEAL',(string)$appealId,['status'=>$from],['status'=>$toStatus]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
