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

    /** @return array<string,mixed> */
    private function user(int $id,bool $lock): array
    {
        $statement=$this->pdo->prepare('SELECT id,role_code,is_active FROM dbo.app_users'.($lock?' WITH(UPDLOCK,HOLDLOCK)':'').' WHERE id=:id');$statement->execute(['id'=>$id]);$row=$statement->fetch();
        if(!is_array($row)||(int)$row['is_active']!==1)throw new DomainException('Përdoruesi aktiv nuk u gjet.');return $row;
    }
}
