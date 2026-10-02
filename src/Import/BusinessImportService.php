<?php
declare(strict_types=1);
namespace App\Import;

use App\Service\AuditLogger;
use PDO;
use RuntimeException;
use Throwable;

final class BusinessImportService
{
    private const FIELDS=['business_number','legal_name','trade_name','business_type','nace_raw','nace_code','nace_description','sector_raw','sector_clean','employee_count','business_size','total_m','total_f','city','business_status','business_year','business_month','closed_date','validation_error'];
    public function __construct(private PDO $pdo,private AuditLogger $audit,private XlsxRowReader $reader=new XlsxRowReader(),private BusinessImportNormalizer $normalizer=new BusinessImportNormalizer()){}

    public function processNext(): bool
    {
        $claim=$this->pdo->query("UPDATE target WITH(UPDLOCK,READPAST,ROWLOCK) SET status='PROCESSING',started_at=SYSUTCDATETIME(),error_message=NULL OUTPUT inserted.* FROM (SELECT TOP(1)* FROM dbo.business_import_runs WHERE status='QUEUED' ORDER BY created_at,id) target")->fetch();
        if(!is_array($claim))return false;$runId=(int)$claim['id'];
        try{$this->process($claim);return true;}catch(Throwable $e){$message=mb_substr($e->getMessage(),0,1900);$this->pdo->prepare("UPDATE dbo.business_import_runs SET status='FAILED',error_message=:error,completed_at=SYSUTCDATETIME() WHERE id=:id")->execute(['error'=>$message,'id'=>$runId]);$this->audit->record(null,'BUSINESS_IMPORT_FAILED','IMPORT_RUN',(string)$runId,null,['error'=>$message]);throw $e;}
    }

    /** @param array<string,mixed> $run */
    private function process(array $run): void
    {
        $path=(string)$run['storage_path'];if(!preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/',$path))$path=dirname(__DIR__,2).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);if(!is_file($path))throw new RuntimeException('Import file is missing.');$runId=(int)$run['id'];$sourceType=(string)$run['source_type'];
        $this->pdo->prepare('DELETE dbo.business_import_staging WHERE import_run_id=:id')->execute(['id'=>$runId]);
        $headers=[];$rowNumber=0;$batch=[];
        foreach($this->reader->rows($path) as $row){$rowNumber++;if($rowNumber===1){foreach($row as $index=>$header)$headers[trim((string)$header)]=$index;$this->validateHeaders($headers);continue;}$normalized=$this->normalizer->normalize($row,$headers,$sourceType);if(array_filter($normalized,static fn($v)=>$v!==null&&$v!=='')===[])continue;$batch[]=['source_row_number'=>$rowNumber,'data'=>$normalized];if(count($batch)>=2000){$this->insertBatch($runId,$batch);$batch=[];}}
        if($batch!==[])$this->insertBatch($runId,$batch);
        $this->merge($runId,$sourceType,max(0,$rowNumber-1));
    }

    /** @param array<string,int> $headers */
    private function validateHeaders(array $headers): void
    {
        foreach(['Nr. i biznesit','Emri i biznesit','Aktiv. Nace2 - Përshkrimi','Sektori','Qyteti'] as $required)if(!array_key_exists($required,$headers))throw new RuntimeException("Missing required Excel column: $required");
    }

    /** @param list<array{source_row_number:int,data:array<string,mixed>}> $rows */
    private function insertBatch(int $runId,array $rows): void
    {
        $payload=[];
        foreach($rows as $entry)$payload[]=['source_row_number'=>$entry['source_row_number']]+array_intersect_key($entry['data'],array_flip(self::FIELDS));
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
        $sql=<<<'SQL'
INSERT dbo.business_import_staging(import_run_id,source_row_number,business_number,legal_name,trade_name,business_type,nace_raw,nace_code,nace_description,sector_raw,sector_clean,employee_count,business_size,total_m,total_f,city,business_status,business_year,business_month,closed_date,validation_error)
SELECT :run_id,j.source_row_number,j.business_number,j.legal_name,j.trade_name,j.business_type,j.nace_raw,j.nace_code,j.nace_description,j.sector_raw,j.sector_clean,j.employee_count,j.business_size,j.total_m,j.total_f,j.city,j.business_status,j.business_year,j.business_month,j.closed_date,j.validation_error
FROM OPENJSON(:payload) WITH(
 source_row_number int '$.source_row_number',business_number varchar(20) '$.business_number',legal_name nvarchar(510) '$.legal_name',trade_name nvarchar(510) '$.trade_name',business_type nvarchar(100) '$.business_type',nace_raw nvarchar(700) '$.nace_raw',nace_code varchar(20) '$.nace_code',nace_description nvarchar(510) '$.nace_description',sector_raw nvarchar(700) '$.sector_raw',sector_clean nvarchar(510) '$.sector_clean',employee_count int '$.employee_count',business_size nvarchar(40) '$.business_size',total_m int '$.total_m',total_f int '$.total_f',city nvarchar(200) '$.city',business_status nvarchar(40) '$.business_status',business_year varchar(4) '$.business_year',business_month varchar(20) '$.business_month',closed_date date '$.closed_date',validation_error nvarchar(500) '$.validation_error'
)j;
SQL;
        $statement=$this->pdo->prepare($sql);$statement->bindValue('run_id',$runId,PDO::PARAM_INT);$statement->bindValue('payload',$json,PDO::PARAM_STR);$statement->execute();
    }

    private function merge(int $runId,string $sourceType,int $totalRows): void
    {
        $women=$sourceType==='WOMEN'?1:0;
        $sql=<<<SQL
SET NOCOUNT ON;SET XACT_ABORT ON;
DECLARE @actions TABLE(action_name varchar(10));
WITH ranked AS(
 SELECT s.*,ROW_NUMBER() OVER(PARTITION BY business_number ORDER BY source_row_number DESC) rn
 FROM dbo.business_import_staging s WHERE import_run_id=:run_id
), source_rows AS(SELECT * FROM ranked WHERE rn=1 AND validation_error IS NULL AND business_number IS NOT NULL)
MERGE dbo.ARBK_LIST WITH(HOLDLOCK) target USING source_rows source ON target.NRBIZ=source.business_number
WHEN MATCHED THEN UPDATE SET
 target.NACE_CODE_TARIFF=CASE WHEN ISNULL(target.NACE_CODE_REG,'')<>ISNULL(source.nace_code,'') THEN NULL ELSE target.NACE_CODE_TARIFF END,
 target.nace_veprimtaria_tariff=CASE WHEN ISNULL(target.NACE_CODE_REG,'')<>ISNULL(source.nace_code,'') THEN NULL ELSE target.nace_veprimtaria_tariff END,
 target.NaceRowGuid=CASE WHEN ISNULL(target.NACE_CODE_REG,'')<>ISNULL(source.nace_code,'') THEN NULL ELSE target.NaceRowGuid END,
 target.NACE_REG_TARIFF=CASE WHEN ISNULL(target.NACE_CODE_REG,'')<>ISNULL(source.nace_code,'') THEN NULL ELSE target.NACE_REG_TARIFF END,
 target.tarifa_me_lirim=CASE WHEN ISNULL(target.NACE_CODE_REG,'')<>ISNULL(source.nace_code,'') THEN NULL ELSE target.tarifa_me_lirim END,
 target.Emri=COALESCE(source.legal_name,target.Emri),target.EMRI_TREGTAR=source.trade_name,target.Lloji=COALESCE(source.business_type,target.Lloji),
 target.NACE_CODE_REG=COALESCE(source.nace_code,target.NACE_CODE_REG),target.NACEPERSHKRIMI=COALESCE(source.nace_description,target.NACEPERSHKRIMI),target.SEKTORI=source.sector_clean,
 target.NR_PUNETOREVE=source.employee_count,target.MADHESIA=source.business_size,target.TOTAL_M=source.total_m,target.TOTAL_F=source.total_f,
 target.Qyteti=COALESCE(source.city,target.Qyteti),target.Statusi=COALESCE(source.business_status,target.Statusi),target.Viti=source.business_year,target.MUAJI=source.business_month,
 target.DATA_SHUARJES=COALESCE(source.closed_date,target.DATA_SHUARJES),target.pronare_grua=CASE WHEN $women=1 THEN 1 ELSE target.pronare_grua END,target.import_updated_at=SYSUTCDATETIME()
WHEN NOT MATCHED THEN INSERT(ARBKrowGUID,NRBIZ,Emri,EMRI_TREGTAR,Lloji,NACE_CODE_REG,NACEPERSHKRIMI,SEKTORI,NR_PUNETOREVE,MADHESIA,TOTAL_M,TOTAL_F,Qyteti,Statusi,Viti,MUAJI,DATA_SHUARJES,pronare_grua,ATK_MBYLLUR,import_updated_at)
 VALUES(NEWID(),source.business_number,source.legal_name,source.trade_name,source.business_type,source.nace_code,source.nace_description,source.sector_clean,source.employee_count,source.business_size,source.total_m,source.total_f,source.city,source.business_status,source.business_year,source.business_month,source.closed_date,CASE WHEN $women=1 THEN 1 ELSE NULL END,0,SYSUTCDATETIME())
OUTPUT \$action INTO @actions;
UPDATE x SET ended_at=SYSUTCDATETIME(),end_reason=N'Registered NACE code changed by Excel import'
FROM dbo.business_nace_assignments x JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=x.business_id JOIN dbo.business_import_staging s ON s.import_run_id=:run_id2 AND s.business_number=a.NRBIZ
WHERE x.ended_at IS NULL AND ISNULL(x.original_nace_code,'')<>ISNULL(a.NACE_CODE_REG,'');
DECLARE @valid int=(SELECT COUNT(*) FROM(SELECT business_number FROM dbo.business_import_staging WHERE import_run_id=:run_id3 AND validation_error IS NULL AND business_number IS NOT NULL GROUP BY business_number)q);
DECLARE @errors int=(SELECT COUNT(*) FROM dbo.business_import_staging WHERE import_run_id=:run_id4 AND validation_error IS NOT NULL);
UPDATE dbo.business_import_runs SET status='COMPLETED',total_rows=:total_rows,valid_rows=@valid,inserted_rows=(SELECT COUNT(*) FROM @actions WHERE action_name='INSERT'),updated_rows=(SELECT COUNT(*) FROM @actions WHERE action_name='UPDATE'),error_rows=@errors,skipped_rows=:total_rows2-@valid,completed_at=SYSUTCDATETIME() WHERE id=:run_id5;
SELECT SUM(CASE WHEN action_name='INSERT' THEN 1 ELSE 0 END) inserted,SUM(CASE WHEN action_name='UPDATE' THEN 1 ELSE 0 END) updated FROM @actions;
SQL;
        $this->pdo->beginTransaction();try{$statement=$this->pdo->prepare($sql);$statement->execute(['run_id'=>$runId,'run_id2'=>$runId,'run_id3'=>$runId,'run_id4'=>$runId,'total_rows'=>$totalRows,'total_rows2'=>$totalRows,'run_id5'=>$runId]);$counts=$statement->fetch()?:[];$this->audit->record(null,'BUSINESS_IMPORT_COMPLETED','IMPORT_RUN',(string)$runId,null,['source_type'=>$sourceType,'total'=>$totalRows,'inserted'=>(int)($counts['inserted']??0),'updated'=>(int)($counts['updated']??0)]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
