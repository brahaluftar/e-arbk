<?php
declare(strict_types=1);
namespace App\Billing;
use App\Service\AuditLogger;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

/**
 * Background annual invoicing. A job snapshots every billable business (dbo.v_business_billing + BillingPolicy)
 * and every active unit marked for automatic invoicing; the CLI worker invoices them in batches.
 */
final class AnnualInvoiceJobService
{
    private BillingPolicy $policy;
    private InvoiceFactory $factory;

    public function __construct(private PDO $pdo,private AuditLogger $audit,?BillingPolicy $policy=null,?InvoiceFactory $factory=null)
    {
        $this->policy=$policy??new BillingPolicy();
        $this->factory=$factory??new InvoiceFactory($pdo,$audit);
    }

    public function enqueue(string $fromDate,int $actorId,int $dueDays=30): int
    {
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$fromDate);if(!$start||$start->format('Y-m-d')!==$fromDate)throw new DomainException('Data fillestare nuk është valide.');$end=$start->modify('+1 year -1 day');$dueDays=max(0,min(365,$dueDays));$year=(int)$start->format('Y');
        if($year<2000||$year>2100)throw new DomainException('Viti fiskal nuk është valid.');
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare("SELECT 1 FROM dbo.annual_invoice_jobs WITH(UPDLOCK,HOLDLOCK) WHERE fiscal_year=:year AND status_code IN('QUEUED','PROCESSING')");$q->execute(['year'=>$year]);if($q->fetchColumn()!==false)throw new DomainException('Ekziston një gjenerim aktiv për këtë vit.');$q->closeCursor();
            $q=$this->pdo->prepare('INSERT dbo.annual_invoice_jobs(period_start,period_end,fiscal_year,due_days,requested_by_user_id) OUTPUT inserted.id VALUES(:start,:end,:year,:due,:user)');$q->execute(['start'=>$fromDate,'end'=>$end->format('Y-m-d'),'year'=>$year,'due'=>$dueDays,'user'=>$actorId]);$id=(int)$q->fetchColumn();$q->closeCursor();
            $q=$this->pdo->prepare('INSERT dbo.annual_invoice_job_items(job_id,business_id) SELECT :job,b.business_id FROM dbo.v_business_billing b WHERE '.$this->policy->billableSql('b').' AND '.$this->policy->registeredBySql('b',':year'));$q->execute(['job'=>$id,'year'=>$year]);$q->closeCursor();
            $q=$this->pdo->prepare("INSERT dbo.annual_invoice_job_items(job_id,business_id,business_unit_id) SELECT :job,u.business_id,u.id FROM dbo.business_units u JOIN dbo.v_business_billing b ON b.business_id=u.business_id WHERE u.is_active=1 AND u.auto_invoice=1 AND u.annual_tariff>0 AND b.status_code='ACTIVE'");$q->execute(['job'=>$id]);$q->closeCursor();
            $q=$this->pdo->prepare('SELECT COUNT_BIG(*) FROM dbo.annual_invoice_job_items WHERE job_id=:job');$q->execute(['job'=>$id]);$total=(int)$q->fetchColumn();$q->closeCursor();
            $q=$this->pdo->prepare('UPDATE dbo.annual_invoice_jobs SET total_items=:total WHERE id=:job');$q->execute(['total'=>$total,'job'=>$id]);
            $this->audit->record($actorId,'ANNUAL_INVOICE_JOB_QUEUED','ANNUAL_INVOICE_JOB',(string)$id,null,['period_start'=>$fromDate,'period_end'=>$end->format('Y-m-d'),'total_items'=>$total,'tariff_sources'=>$this->policy->sources()]);
            $this->pdo->commit();return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function processNextBatch(int $batchSize=200): bool
    {
        $batchSize=max(1,min(1000,$batchSize));$job=$this->pdo->query("SELECT TOP(1) * FROM dbo.annual_invoice_jobs WHERE status_code IN('QUEUED','PROCESSING') ORDER BY CASE status_code WHEN 'PROCESSING' THEN 0 ELSE 1 END,created_at,id")->fetch(PDO::FETCH_ASSOC);if(!is_array($job))return false;$jobId=(int)$job['id'];
        $this->pdo->prepare("UPDATE dbo.annual_invoice_jobs SET status_code='PROCESSING',started_at=COALESCE(started_at,SYSUTCDATETIME()),heartbeat_at=SYSUTCDATETIME(),error_message=NULL WHERE id=:id")->execute(['id'=>$jobId]);
        $items=$this->pdo->query("SELECT TOP ($batchSize) id FROM dbo.annual_invoice_job_items WHERE job_id=$jobId AND status_code='PENDING' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        if($items===[]){$this->refresh($jobId);return false;}
        foreach($items as $itemId)$this->processItem($job,(int)$itemId);
        $this->refresh($jobId);return true;
    }

    public function retryFailed(int $jobId): int
    {
        if($jobId<1)throw new DomainException('Job-i nuk është valid.');
        $this->pdo->beginTransaction();
        try{
            // Items are re-checked when processed again; businesses that stopped being billable are skipped then.
            $q=$this->pdo->prepare("UPDATE dbo.annual_invoice_job_items SET status_code='PENDING',error_message=NULL,updated_at=SYSUTCDATETIME() WHERE job_id=:job AND status_code='FAILED'");$q->execute(['job'=>$jobId]);$count=$q->rowCount();
            $q=$this->pdo->prepare("UPDATE dbo.annual_invoice_jobs SET status_code=CASE WHEN status_code='PROCESSING' THEN 'PROCESSING' ELSE 'QUEUED' END,completed_at=NULL,error_message=NULL WHERE id=:job AND status_code IN('PROCESSING','COMPLETED_WITH_ERRORS','FAILED')");$q->execute(['job'=>$jobId]);if($q->rowCount()!==1)throw new DomainException('Job-i nuk mund të riprovohet në statusin aktual.');
            $this->pdo->commit();$this->refresh($jobId);return $count;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @param array<string,mixed> $job */
    private function processItem(array $job,int $itemId): void
    {
        $jobId=(int)$job['id'];$year=(int)$job['fiscal_year'];
        try{
            $this->pdo->beginTransaction();
            $claim=$this->pdo->prepare("UPDATE dbo.annual_invoice_job_items WITH(UPDLOCK,ROWLOCK) SET status_code='PROCESSING',updated_at=SYSUTCDATETIME() OUTPUT inserted.business_id,inserted.business_unit_id WHERE id=:id AND status_code='PENDING'");$claim->execute(['id'=>$itemId]);$item=$claim->fetch(PDO::FETCH_ASSOC);$claim->closeCursor();
            if(!is_array($item)){$this->pdo->rollBack();return;}
            $draft=$item['business_unit_id']===null?$this->mainDraft((int)$item['business_id'],$year):$this->unitDraft((int)$item['business_unit_id'],$year);
            if(is_string($draft)){$this->finishItem($itemId,$draft);$this->pdo->commit();return;}
            $start=(string)$job['period_start'];
            $due=(new DateTimeImmutable($start))->modify('+'.(int)$job['due_days'].' days')->format('Y-m-d');
            $invoice=$this->factory->issue($draft+['fiscal_year'=>$year,'issued_on'=>$start,'due_on'=>$due,'period_start'=>$start,'period_end'=>(string)$job['period_end'],'billed_months'=>12,'notes'=>'Faturë për periudhë të plotë njëvjeçare.'],(int)$job['requested_by_user_id'],'ANNUAL_INVOICE_CREATED',['job_id'=>$jobId]);
            $this->finishItem($itemId,'CREATED',$invoice['id'],(float)$draft['amount']);
            $this->pdo->commit();
        }catch(Throwable $e){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            $this->pdo->prepare("UPDATE dbo.annual_invoice_job_items SET status_code='FAILED',error_message=:error,updated_at=SYSUTCDATETIME() WHERE id=:id")->execute(['error'=>mb_substr($e->getMessage(),0,1000),'id'=>$itemId]);
            error_log('Annual invoice job '.$jobId.' item '.$itemId."\n".$e->__toString());
        }
    }

    /** @return array<string,mixed>|string invoice draft, or the skip status */
    private function mainDraft(int $businessId,int $year): array|string
    {
        $q=$this->pdo->prepare("SELECT a.REGULATION_ID,a.Emri,a.ADRESA,a.NUMRI_FISKAL,a.NRBIZ,LTRIM(RTRIM(a.NACE_CODE_REG)) registered_nace,COALESCE(LTRIM(RTRIM(a.NACE_CODE_TARIFF)),LTRIM(RTRIM(n.NACE_CODE)),LTRIM(RTRIM(a.NACE_CODE_REG))) tariff_nace,COALESCE(NULLIF(LTRIM(RTRIM(a.nace_veprimtaria_tariff)),''),n.Veprimtaria,a.NACEPERSHKRIMI) description,b.effective_tariff,CASE WHEN ".$this->policy->billableSql('b').' AND '.$this->policy->registeredBySql('b',':year')." THEN 1 ELSE 0 END billable,(SELECT TOP(1) id FROM dbo.business_invoices WHERE business_id=a.REGULATION_ID AND business_unit_id IS NULL AND fiscal_year=:invoice_year) existing_id FROM dbo.ARBK_LIST a JOIN dbo.v_business_billing b ON b.business_id=a.REGULATION_ID LEFT JOIN dbo.NACE_LIST n ON n.NACErowGUID=a.NaceRowGuid WHERE a.REGULATION_ID=:business");
        $q->execute(['year'=>$year,'invoice_year'=>$year,'business'=>$businessId]);$b=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
        if(!is_array($b)||(int)$b['billable']!==1)return 'SKIPPED_NOT_BILLABLE';
        if($b['existing_id']!==null)return 'SKIPPED_EXISTING';
        if($b['effective_tariff']===null||(float)$b['effective_tariff']<=0)return 'SKIPPED_MISSING_TARIFF';
        $nace=strtoupper(trim((string)$b['registered_nace']));if(preg_match('/\A[0-9A-Z]{4}\z/',$nace)!==1)return 'SKIPPED_INVALID_NACE';
        return ['business_id'=>$businessId,'business_unit_id'=>null,'nace'=>$nace,'amount'=>round((float)$b['effective_tariff'],2),'annual_tariff'=>round((float)$b['effective_tariff'],2),'business_name'=>(string)$b['Emri'],'business_address'=>$b['ADRESA'],'fiscal_number'=>$b['NUMRI_FISKAL'],'registration_number'=>$b['NRBIZ'],'registered_nace'=>$nace,'tariff_nace'=>$b['tariff_nace'],'nace_description'=>$b['description']];
    }

    /** @return array<string,mixed>|string invoice draft, or the skip status */
    private function unitDraft(int $unitId,int $year): array|string
    {
        $q=$this->pdo->prepare("SELECT u.*,a.Emri,a.NUMRI_FISKAL,a.NRBIZ,b.status_code business_status,(SELECT TOP(1) id FROM dbo.business_invoices WHERE business_unit_id=u.id AND fiscal_year=:year) existing_id FROM dbo.business_units u JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=u.business_id JOIN dbo.v_business_billing b ON b.business_id=u.business_id WHERE u.id=:unit");
        $q->execute(['year'=>$year,'unit'=>$unitId]);$u=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
        if(!is_array($u)||(int)$u['is_active']!==1||$u['business_status']!=='ACTIVE')return 'SKIPPED_NOT_BILLABLE';
        if($u['existing_id']!==null)return 'SKIPPED_EXISTING';
        if($u['annual_tariff']===null||(float)$u['annual_tariff']<=0)return 'SKIPPED_MISSING_TARIFF';
        $nace=strtoupper(trim((string)$u['nace_code']));if(preg_match('/\A[0-9A-Z]{4}\z/',$nace)!==1)return 'SKIPPED_INVALID_NACE';
        return ['business_id'=>(int)$u['business_id'],'business_unit_id'=>$unitId,'nace'=>$nace,'amount'=>round((float)$u['annual_tariff'],2),'annual_tariff'=>round((float)$u['annual_tariff'],2),'business_name'=>$u['Emri'].' — '.$u['unit_name'],'business_address'=>$u['address'],'fiscal_number'=>$u['NUMRI_FISKAL'],'registration_number'=>$u['NRBIZ'],'registered_nace'=>$nace,'tariff_nace'=>$nace,'nace_description'=>$u['nace_description']];
    }

    private function finishItem(int $itemId,string $status,?int $invoiceId=null,?float $amount=null): void
    {
        $this->pdo->prepare('UPDATE dbo.annual_invoice_job_items SET status_code=:status,invoice_id=:invoice,amount=:amount,error_message=NULL,updated_at=SYSUTCDATETIME() WHERE id=:id')->execute(['status'=>$status,'invoice'=>$invoiceId,'amount'=>$amount,'id'=>$itemId]);
    }

    private function refresh(int $jobId): void{$q=$this->pdo->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status_code NOT IN('PENDING','PROCESSING') THEN 1 ELSE 0 END) processed,SUM(CASE WHEN status_code='CREATED' THEN 1 ELSE 0 END) created,SUM(CASE WHEN status_code LIKE 'SKIPPED_%' THEN 1 ELSE 0 END) skipped,SUM(CASE WHEN status_code='FAILED' THEN 1 ELSE 0 END) failed,COALESCE(SUM(amount),0) amount,SUM(CASE WHEN status_code IN('PENDING','PROCESSING') THEN 1 ELSE 0 END) remaining FROM dbo.annual_invoice_job_items WHERE job_id=:id");$q->execute(['id'=>$jobId]);$s=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();$status=(int)$s['remaining']>0?'PROCESSING':((int)$s['failed']>0?'COMPLETED_WITH_ERRORS':'COMPLETED');$this->pdo->prepare("UPDATE dbo.annual_invoice_jobs SET status_code=:status,total_items=:total,processed_items=:processed,created_items=:created,skipped_items=:skipped,failed_items=:failed,total_amount=:amount,heartbeat_at=SYSUTCDATETIME(),completed_at=CASE WHEN :done=1 THEN SYSUTCDATETIME() ELSE NULL END WHERE id=:id")->execute(['status'=>$status,'total'=>(int)$s['total'],'processed'=>(int)$s['processed'],'created'=>(int)$s['created'],'skipped'=>(int)$s['skipped'],'failed'=>(int)$s['failed'],'amount'=>$s['amount'],'done'=>$status==='PROCESSING'?0:1,'id'=>$jobId]);}
}
