<?php
declare(strict_types=1);
namespace App\Service;
use PDO;

/**
 * Every 15 minutes (bin/run-scheduled.php): status of every business from the ATK list (closed businesses, matched by NRB)
 * and from ARBK itself. A business closed or passive in ARBK (ATK_MBYLLUR, Pasiv, DATA_SHUARJES, Statusi 'Shuar') is always
 * DEACTIVATED, also when ATK does not list it; only businesses that neither list marks as closed are ACTIVE.
 */
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
  MIN(t.Statusi) atk_status,MIN(t.DataMbylljes) deactivated_at,
  MAX(CASE WHEN a.ATK_MBYLLUR=1 OR a.Pasiv=1 OR a.DATA_SHUARJES IS NOT NULL OR LTRIM(RTRIM(ISNULL(a.Statusi,N'')))=N'Shuar' THEN 1 ELSE 0 END) arbk_closed,
  MAX(COALESCE(CONVERT(nvarchar(40),a.DATA_SHUARJES,23),CONVERT(nvarchar(40),a.date_pasivizimit,23))) arbk_closed_at
 FROM dbo.ARBK_LIST a LEFT JOIN dbo.ATK_LIST t ON LTRIM(RTRIM(t.NRB))=LTRIM(RTRIM(a.NRBIZ)) GROUP BY a.REGULATION_ID,a.NRBIZ
), source_rows AS (
 SELECT business_id,
  CASE WHEN arbk_closed=1 THEN 'DEACTIVATED' WHEN match_count=0 THEN 'ACTIVE' WHEN distinct_statuses=1 THEN 'DEACTIVATED' ELSE 'NEEDS_REVIEW' END status_code,
  CASE WHEN match_count>0 THEN 'BUSINESS_NUMBER' WHEN arbk_closed=1 THEN 'ARBK' END matched_by,matched_value,
  CASE WHEN distinct_statuses=1 THEN atk_record_id END atk_record_id,CASE WHEN distinct_statuses=1 THEN atk_status END atk_status,
  CASE WHEN match_count>0 AND distinct_statuses=1 THEN deactivated_at WHEN arbk_closed=1 THEN arbk_closed_at END deactivated_at,match_count FROM matches
)
MERGE dbo.business_atk_status WITH (HOLDLOCK) target USING source_rows source ON source.business_id=target.business_id
WHEN MATCHED THEN UPDATE SET status_code=source.status_code,matched_by=source.matched_by,matched_value=source.matched_value,atk_record_id=source.atk_record_id,atk_status=source.atk_status,deactivated_at=source.deactivated_at,match_count=source.match_count,synchronized_at=SYSUTCDATETIME()
WHEN NOT MATCHED THEN INSERT(business_id,status_code,matched_by,matched_value,atk_record_id,atk_status,deactivated_at,match_count) VALUES(source.business_id,source.status_code,source.matched_by,source.matched_value,source.atk_record_id,source.atk_status,source.deactivated_at,source.match_count);
SELECT COUNT(*) FROM dbo.business_atk_status;
SQL;
        $this->pdo->beginTransaction(); try{$count=(int)$this->pdo->query($sql)->fetchColumn();$this->audit->record(null,'ATK_STATUS_SYNCHRONIZATION','BATCH',gmdate('YmdHis'),null,['processed'=>$count],['matching'=>'ARBK_LIST.NRBIZ = ATK_LIST.NRB']);$this->pdo->commit();return $count;}catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
    }
}
