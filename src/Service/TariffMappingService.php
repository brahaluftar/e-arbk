<?php
declare(strict_types=1);
namespace App\Service;
use PDO;

final class TariffMappingService
{
    public function __construct(private PDO $pdo,private AuditLogger $audit) {}

    /** @return array{codes:int,activities:int,guids:int} */
    public function synchronize(): array
    {
        $sql=<<<'SQL'
SET NOCOUNT ON;
DECLARE @codes int=0,@activities int=0,@guids int=0;
UPDATE a SET NACE_CODE_TARIFF=LTRIM(RTRIM(a.NACE_CODE_REG))
FROM dbo.ARBK_LIST a
WHERE a.NACE_CODE_TARIFF IS NULL AND EXISTS(
 SELECT 1 FROM dbo.NACE_LIST n WHERE LTRIM(RTRIM(n.NACE_CODE))=LTRIM(RTRIM(a.NACE_CODE_REG))
);
SET @codes=@@ROWCOUNT;
UPDATE a SET nace_veprimtaria_tariff=n.Veprimtaria
FROM dbo.ARBK_LIST a
CROSS APPLY (
 SELECT MIN(source.Veprimtaria) Veprimtaria,COUNT(*) matches FROM dbo.NACE_LIST source
 WHERE LTRIM(RTRIM(source.Veprimtaria))=LTRIM(RTRIM(a.NACEPERSHKRIMI))
) n
WHERE a.nace_veprimtaria_tariff IS NULL AND n.matches=1;
SET @activities=@@ROWCOUNT;
UPDATE a SET NaceRowGuid=CONVERT(uniqueidentifier,n.NACErowGUID)
FROM dbo.ARBK_LIST a
CROSS APPLY (
 SELECT MIN(CONVERT(char(36),source.NACErowGUID)) NACErowGUID,COUNT(*) matches FROM dbo.NACE_LIST source
 WHERE LTRIM(RTRIM(source.NACE_CODE))=LTRIM(RTRIM(a.NACE_CODE_REG))
   AND LTRIM(RTRIM(source.Veprimtaria))=LTRIM(RTRIM(a.NACEPERSHKRIMI))
) n
WHERE a.NaceRowGuid IS NULL AND n.matches=1;
SET @guids=@@ROWCOUNT;
SELECT @codes codes,@activities activities,@guids guids;
SQL;
        $this->pdo->beginTransaction();
        try {
            $result=$this->pdo->query($sql)->fetch() ?: ['codes'=>0,'activities'=>0,'guids'=>0];
            $normalized=['codes'=>(int)$result['codes'],'activities'=>(int)$result['activities'],'guids'=>(int)$result['guids']];
            $this->audit->record(null,'TARIFF_MASTER_RELATION_SYNC','BATCH',gmdate('YmdHis'),null,$normalized,['policy'=>'fill NULL targets only; never overwrite manual values']);
            $this->pdo->commit(); return $normalized;
        } catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
    }
}
