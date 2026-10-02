<?php
declare(strict_types=1);
namespace App\Service;
use PDO;

final class AtkStatusService
{
    public function __construct(private PDO $pdo,private AuditLogger $audit) {}
    public function synchronize(): int
    {
        $sql=<<<'SQL'
SET NOCOUNT ON;
WITH matches AS (
 SELECT a.REGULATION_ID business_id,a.NRBIZ matched_value,COUNT(t.ID) match_count,MIN(t.ID) atk_record_id,
  COUNT(DISTINCT ISNULL(NULLIF(LTRIM(RTRIM(t.Statusi)),''),'?')) distinct_statuses,
  MIN(t.Statusi) atk_status,MIN(t.DataMbylljes) deactivated_at
 FROM dbo.ARBK_LIST a LEFT JOIN dbo.ATK_LIST t ON LTRIM(RTRIM(t.NRB))=LTRIM(RTRIM(a.NRBIZ)) GROUP BY a.REGULATION_ID,a.NRBIZ
), source_rows AS (
 SELECT business_id,CASE WHEN match_count=0 THEN 'ACTIVE' WHEN distinct_statuses=1 THEN 'DEACTIVATED' ELSE 'NEEDS_REVIEW' END status_code,
  CASE WHEN match_count=0 THEN NULL ELSE 'BUSINESS_NUMBER' END matched_by,matched_value,CASE WHEN distinct_statuses=1 THEN atk_record_id END atk_record_id,
  CASE WHEN distinct_statuses=1 THEN atk_status END atk_status,CASE WHEN distinct_statuses=1 THEN deactivated_at END deactivated_at,match_count FROM matches
)
MERGE dbo.business_atk_status WITH (HOLDLOCK) target USING source_rows source ON source.business_id=target.business_id
WHEN MATCHED THEN UPDATE SET status_code=source.status_code,matched_by=source.matched_by,matched_value=source.matched_value,atk_record_id=source.atk_record_id,atk_status=source.atk_status,deactivated_at=source.deactivated_at,match_count=source.match_count,synchronized_at=SYSUTCDATETIME()
WHEN NOT MATCHED THEN INSERT(business_id,status_code,matched_by,matched_value,atk_record_id,atk_status,deactivated_at,match_count) VALUES(source.business_id,source.status_code,source.matched_by,source.matched_value,source.atk_record_id,source.atk_status,source.deactivated_at,source.match_count);
SELECT COUNT(*) FROM dbo.business_atk_status;
SQL;
        $this->pdo->beginTransaction(); try{$count=(int)$this->pdo->query($sql)->fetchColumn();$this->audit->record(null,'ATK_STATUS_SYNCHRONIZATION','BATCH',gmdate('YmdHis'),null,['processed'=>$count],['matching'=>'ARBK_LIST.NRBIZ = ATK_LIST.NRB']);$this->pdo->commit();return $count;}catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
    }
}
