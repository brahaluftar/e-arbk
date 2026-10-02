<?php
declare(strict_types=1);
namespace App\Service;
use PDO;

final class ClassificationService
{
    public function __construct(private PDO $pdo,private AuditLogger $audit) {}

    public function assignManual(int $businessId,string $mappingKey,int $userId): void
    {
        if(preg_match('/\A[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}\z/i',$mappingKey)!==1) throw new \DomainException('Invalid category key.');
        $candidate=$this->pdo->prepare("SELECT a.NACE_CODE_REG original_code,n.REGULATIONID nace_list_id,n.NACErowGUID,n.Sektori,n.Veprimtaria,n.Tarifa tariff,n.NACE_CODE FROM dbo.ARBK_LIST a JOIN dbo.NACE_LIST n ON n.NACErowGUID=CONVERT(uniqueidentifier,:mapping) AND LTRIM(RTRIM(n.NACE_CODE))=LTRIM(RTRIM(a.NACE_CODE_REG)) WHERE a.REGULATION_ID=:business");
        $candidate->execute(['mapping'=>$mappingKey,'business'=>$businessId]); $row=$candidate->fetch();
        if(!is_array($row)) throw new \DomainException('The selected category is not valid for this business NACE code.');
        $category=trim((string)$row['Sektori']).' — '.trim((string)$row['Veprimtaria']);
        $this->pdo->beginTransaction();
        try {
            $current=$this->pdo->prepare('SELECT id,nace_list_id,nace_category,assignment_method FROM dbo.business_nace_assignments WITH (UPDLOCK,HOLDLOCK) WHERE business_id=:id AND ended_at IS NULL'); $current->execute(['id'=>$businessId]); $previous=$current->fetch()?:null;
            $this->pdo->prepare("UPDATE dbo.business_nace_assignments SET ended_at=SYSUTCDATETIME(),ended_by_user_id=:user,end_reason=N'Replaced by manual classification' WHERE business_id=:id AND ended_at IS NULL")->execute(['user'=>$userId,'id'=>$businessId]);
            $insert=$this->pdo->prepare("INSERT dbo.business_nace_assignments(business_id,original_nace_code,nace_list_id,mapping_key,source_nace_row_guid,source_sector,source_activity,nace_category,tariff_snapshot,assignment_method,mapping_rule,assigned_by_user_id) VALUES(:business,:code,:source_id,:mapping,CONVERT(uniqueidentifier,:source_guid),:sector,:activity,:category,:tariff,'MANUAL',N'Validated by NACE row GUID and exact registered NACE code',:user)");
            $insert->execute(['business'=>$businessId,'code'=>trim((string)$row['original_code']),'source_id'=>$row['nace_list_id'],'mapping'=>$mappingKey,'source_guid'=>$mappingKey,'sector'=>trim((string)$row['Sektori']),'activity'=>trim((string)$row['Veprimtaria']),'category'=>$category,'tariff'=>$row['tariff'],'user'=>$userId]);
            $this->pdo->prepare('UPDATE dbo.ARBK_LIST SET NACE_CODE_TARIFF=:code,nace_veprimtaria_tariff=:activity,NaceRowGuid=CONVERT(uniqueidentifier,:guid) WHERE REGULATION_ID=:business')->execute(['code'=>trim((string)$row['NACE_CODE']),'activity'=>trim((string)$row['Veprimtaria']),'guid'=>$mappingKey,'business'=>$businessId]);
            $this->audit->record($userId,'NACE_MANUAL_ASSIGNMENT','BUSINESS',(string)$businessId,$previous,['mapping_key'=>$mappingKey,'category'=>$category,'tariff'=>$row['tariff']]);
            $this->pdo->commit();
        } catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
    }

    public function autoAssign(): int
    {
        $sql=<<<'SQL'
SET NOCOUNT ON;
DECLARE @assigned TABLE(business_id int,nace_list_id float,nace_category nvarchar(510),tariff money,nace_guid uniqueidentifier);
INSERT dbo.business_nace_assignments(business_id,original_nace_code,nace_list_id,mapping_key,source_nace_row_guid,source_sector,source_activity,nace_category,tariff_snapshot,assignment_method,mapping_rule)
OUTPUT inserted.business_id,inserted.nace_list_id,inserted.nace_category,inserted.tariff_snapshot,inserted.source_nace_row_guid INTO @assigned
SELECT a.REGULATION_ID,LTRIM(RTRIM(a.NACE_CODE_REG)),MIN(n.REGULATIONID),MIN(CONVERT(varchar(36),n.NACErowGUID)),CONVERT(uniqueidentifier,MIN(CONVERT(char(36),n.NACErowGUID))),MIN(LTRIM(RTRIM(n.Sektori))),MIN(LTRIM(RTRIM(n.Veprimtaria))),MIN(LTRIM(RTRIM(n.Sektori))+N' — '+LTRIM(RTRIM(n.Veprimtaria))),MIN(n.Tarifa),'AUTO',N'One exact registered NACE code and activity match'
FROM dbo.ARBK_LIST a JOIN dbo.NACE_LIST n ON LTRIM(RTRIM(n.NACE_CODE))=LTRIM(RTRIM(a.NACE_CODE_REG)) AND LTRIM(RTRIM(n.Veprimtaria))=LTRIM(RTRIM(a.NACEPERSHKRIMI))
WHERE NOT EXISTS(SELECT 1 FROM dbo.business_nace_assignments x WHERE x.business_id=a.REGULATION_ID AND x.ended_at IS NULL)
GROUP BY a.REGULATION_ID,a.NACE_CODE_REG HAVING COUNT(*)=1;
UPDATE a SET NACE_CODE_TARIFF=n.NACE_CODE,nace_veprimtaria_tariff=n.Veprimtaria,NaceRowGuid=n.NACErowGUID
FROM dbo.ARBK_LIST a JOIN @assigned x ON x.business_id=a.REGULATION_ID JOIN dbo.NACE_LIST n ON n.NACErowGUID=x.nace_guid;
INSERT dbo.audit_log(actor_type,action_code,entity_type,entity_id,new_value_json)
SELECT 'SYSTEM','NACE_AUTO_ASSIGNMENT','BUSINESS',CONVERT(nvarchar(100),business_id),(SELECT nace_list_id,nace_category,tariff FOR JSON PATH,WITHOUT_ARRAY_WRAPPER) FROM @assigned;
SELECT COUNT(*) assigned FROM @assigned;
SQL;
        $this->pdo->beginTransaction(); try{$count=(int)$this->pdo->query($sql)->fetchColumn();$this->pdo->commit();return $count;}catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
    }
}
