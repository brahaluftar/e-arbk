<?php
declare(strict_types=1);
namespace App\Service;
use PDO;

final class AuditLogger
{
    public function __construct(private PDO $pdo) {}
    public function record(?int $userId,string $action,string $entityType,string $entityId,mixed $previous,mixed $new,array $metadata=[]): void
    {
        $sql='INSERT dbo.audit_log(actor_type,actor_user_id,action_code,entity_type,entity_id,previous_value_json,new_value_json,metadata_json) VALUES(:actor_type,:user,:action,:entity_type,:entity_id,:previous,:new,:metadata)';
        $this->pdo->prepare($sql)->execute(['actor_type'=>$userId===null?'SYSTEM':'USER','user'=>$userId,'action'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId,'previous'=>$previous===null?null:json_encode($previous,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'new'=>$new===null?null:json_encode($new,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'metadata'=>$metadata===[]?null:json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }
}
