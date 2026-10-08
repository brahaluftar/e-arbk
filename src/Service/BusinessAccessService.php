<?php
declare(strict_types=1);

namespace App\Service;

use DomainException;
use PDO;
use Throwable;

final class BusinessAccessService
{
    public function __construct(private PDO $pdo,private AuditLogger $audit) {}

    public function link(int $businessUserId,int $businessId,int $actorId): void
    {
        $this->pdo->beginTransaction();
        try{
            $actor=$this->user($actorId,true);$target=$this->user($businessUserId,true);
            if(!in_array($actor['role_code'],['ADMIN','OFFICIAL'],true))throw new DomainException('Vetëm zyrtari komunal mund ta bëjë lidhjen.');
            if($target['role_code']!=='BUSINESS')throw new DomainException('Llogaria e zgjedhur nuk është llogari biznesi.');
            $business=$this->pdo->prepare('SELECT REGULATION_ID,Emri FROM dbo.ARBK_LIST WITH(UPDLOCK,HOLDLOCK) WHERE REGULATION_ID=:id');$business->execute(['id'=>$businessId]);$row=$business->fetch();
            if(!is_array($row))throw new DomainException('Biznesi nuk u gjet.');
            $exists=$this->pdo->prepare('SELECT 1 FROM dbo.business_user_links WHERE user_id=:user AND business_id=:business AND ended_at IS NULL');$exists->execute(['user'=>$businessUserId,'business'=>$businessId]);
            if($exists->fetchColumn()!==false)throw new DomainException('Kjo lidhje ekziston.');
            $this->pdo->prepare('INSERT dbo.business_user_links(user_id,business_id,assigned_by_user_id) VALUES(:user,:business,:actor)')->execute(['user'=>$businessUserId,'business'=>$businessId,'actor'=>$actorId]);
            $this->audit->record($actorId,'BUSINESS_USER_LINKED','BUSINESS',(string)$businessId,null,['user_id'=>$businessUserId,'business_name'=>$row['Emri']]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function unlink(int $linkId,int $actorId,string $reason): void
    {
        $reason=trim($reason);if($reason===''||mb_strlen($reason)>300)throw new DomainException('Shënoni arsyen e ndërprerjes së lidhjes.');
        $this->pdo->beginTransaction();
        try{
            $actor=$this->user($actorId,true);if(!in_array($actor['role_code'],['ADMIN','OFFICIAL'],true))throw new DomainException('Vetëm zyrtari komunal mund ta ndërpresë lidhjen.');
            $statement=$this->pdo->prepare('SELECT id,user_id,business_id FROM dbo.business_user_links WITH(UPDLOCK,HOLDLOCK) WHERE id=:id AND ended_at IS NULL');$statement->execute(['id'=>$linkId]);$link=$statement->fetch();if(!is_array($link))throw new DomainException('Lidhja aktive nuk u gjet.');
            $this->pdo->prepare('UPDATE dbo.business_user_links SET ended_at=SYSUTCDATETIME(),ended_by_user_id=:actor,end_reason=:reason WHERE id=:id')->execute(['actor'=>$actorId,'reason'=>$reason,'id'=>$linkId]);
            $this->audit->record($actorId,'BUSINESS_USER_UNLINKED','BUSINESS',(string)$link['business_id'],['user_id'=>$link['user_id']],null,['reason'=>$reason]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function requestByRegistrationNumber(int $businessUserId,string $registrationNumber): int
    {
        $registrationNumber=trim($registrationNumber);
        if($registrationNumber===''||mb_strlen($registrationNumber)>50)throw new DomainException('Shënoni numrin unik të biznesit (NRBIZ).');
        $this->pdo->beginTransaction();
        try{
            $target=$this->user($businessUserId,true);if($target['role_code']!=='BUSINESS')throw new DomainException('Llogaria nuk është llogari biznesi.');
            $business=$this->pdo->prepare('SELECT REGULATION_ID,Emri FROM dbo.ARBK_LIST WITH(UPDLOCK,HOLDLOCK) WHERE LTRIM(RTRIM(NRBIZ))=:nrbiz');$business->execute(['nrbiz'=>$registrationNumber]);$row=$business->fetch();
            if(!is_array($row))throw new DomainException('Nuk u gjet biznes me këtë NRBIZ.');
            $businessId=(int)$row['REGULATION_ID'];
            $active=$this->pdo->prepare('SELECT 1 FROM dbo.business_user_links WHERE user_id=:user AND business_id=:business AND ended_at IS NULL');$active->execute(['user'=>$businessUserId,'business'=>$businessId]);
            if($active->fetchColumn()!==false)throw new DomainException('Ky biznes është tashmë i lidhur me llogarinë tuaj.');
            $pending=$this->pdo->prepare("SELECT 1 FROM dbo.business_access_requests WHERE user_id=:user AND business_id=:business AND status_code='PENDING'");$pending->execute(['user'=>$businessUserId,'business'=>$businessId]);
            if($pending->fetchColumn()!==false)throw new DomainException('Kërkesa për këtë biznes është duke pritur shqyrtimin.');
            $insert=$this->pdo->prepare("INSERT dbo.business_access_requests(user_id,business_id,status_code) OUTPUT inserted.id VALUES(:user,:business,'PENDING')");$insert->execute(['user'=>$businessUserId,'business'=>$businessId]);$requestId=(int)$insert->fetchColumn();
            $this->audit->record($businessUserId,'BUSINESS_ACCESS_REQUESTED','BUSINESS_ACCESS_REQUEST',(string)$requestId,null,['business_id'=>$businessId,'business_name'=>$row['Emri'],'nrbiz'=>$registrationNumber]);
            $this->pdo->commit();return $requestId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function reviewRequest(int $requestId,int $actorId,string $decision,string $note=''): void
    {
        $decision=strtoupper(trim($decision));$note=trim($note);
        if(!in_array($decision,['APPROVED','REJECTED'],true))throw new DomainException('Vendimi nuk është valid.');
        if(mb_strlen($note)>300)throw new DomainException('Shënimi mund të ketë më së shumti 300 karaktere.');
        $this->pdo->beginTransaction();
        try{
            $actor=$this->user($actorId,true);if(!in_array($actor['role_code'],['ADMIN','OFFICIAL'],true))throw new DomainException('Vetëm zyrtari komunal mund ta shqyrtojë kërkesën.');
            $statement=$this->pdo->prepare("SELECT id,user_id,business_id FROM dbo.business_access_requests WITH(UPDLOCK,HOLDLOCK) WHERE id=:id AND status_code='PENDING'");$statement->execute(['id'=>$requestId]);$request=$statement->fetch();
            if(!is_array($request))throw new DomainException('Kërkesa në pritje nuk u gjet.');
            if($decision==='APPROVED'){
                $active=$this->pdo->prepare('SELECT 1 FROM dbo.business_user_links WHERE user_id=:user AND business_id=:business AND ended_at IS NULL');$active->execute(['user'=>$request['user_id'],'business'=>$request['business_id']]);
                if($active->fetchColumn()===false)$this->pdo->prepare('INSERT dbo.business_user_links(user_id,business_id,assigned_by_user_id) VALUES(:user,:business,:actor)')->execute(['user'=>$request['user_id'],'business'=>$request['business_id'],'actor'=>$actorId]);
            }
            $this->pdo->prepare('UPDATE dbo.business_access_requests SET status_code=:status,reviewed_at=SYSUTCDATETIME(),reviewed_by_user_id=:actor,review_note=:note WHERE id=:id')->execute(['status'=>$decision,'actor'=>$actorId,'note'=>$note===''?null:$note,'id'=>$requestId]);
            $this->audit->record($actorId,'BUSINESS_ACCESS_'.$decision,'BUSINESS_ACCESS_REQUEST',(string)$requestId,['status'=>'PENDING'],['status'=>$decision,'user_id'=>(int)$request['user_id'],'business_id'=>(int)$request['business_id'],'note'=>$note]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @return array<string,mixed> */
    private function user(int $id,bool $lock): array
    {
        $statement=$this->pdo->prepare('SELECT id,role_code,is_active FROM dbo.app_users'.($lock?' WITH(UPDLOCK,HOLDLOCK)':'').' WHERE id=:id');$statement->execute(['id'=>$id]);$row=$statement->fetch();
        if(!is_array($row)||(int)$row['is_active']!==1)throw new DomainException('Përdoruesi aktiv nuk u gjet.');return $row;
    }
}
