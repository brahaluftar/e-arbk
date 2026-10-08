<?php
declare(strict_types=1);
namespace App\Billing;
use App\Service\AuditLogger;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

final class AnnualInvoiceJobService
{
    public function __construct(private PDO $pdo,private AuditLogger $audit,private ?UnirefSequence $sequences=null){$this->sequences??=new UnirefSequence($pdo);}

    public function enqueue(string $fromDate,int $actorId,int $dueDays=30): int
    {
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$fromDate);if(!$start||$start->format('Y-m-d')!==$fromDate)throw new DomainException('Data fillestare nuk është valide.');$end=$start->modify('+1 year -1 day');$dueDays=max(0,min(365,$dueDays));
        $this->pdo->beginTransaction();try{$q=$this->pdo->prepare("SELECT 1 FROM dbo.annual_invoice_jobs WITH(UPDLOCK,HOLDLOCK) WHERE fiscal_year=:year AND status_code IN('QUEUED','PROCESSING')");$q->execute(['year'=>$start->format('Y')]);if($q->fetchColumn()!==false)throw new DomainException('Ekziston një gjenerim aktiv për këtë vit.');$q->closeCursor();
            $q=$this->pdo->prepare('INSERT dbo.annual_invoice_jobs(period_start,period_end,fiscal_year,due_days,requested_by_user_id) OUTPUT inserted.id VALUES(:start,:end,:year,:due,:user)');$q->execute(['start'=>$fromDate,'end'=>$end->format('Y-m-d'),'year'=>$start->format('Y'),'due'=>$dueDays,'user'=>$actorId]);$id=(int)$q->fetchColumn();$q->closeCursor();
            $q=$this->pdo->prepare("INSERT dbo.annual_invoice_job_items(job_id,business_id) SELECT :job,a.REGULATION_ID FROM dbo.ARBK_LIST a WHERE ISNULL(a.ATK_MBYLLUR,0)=0 AND TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)>0");$q->execute(['job'=>$id]);$q->closeCursor();$q=$this->pdo->prepare('SELECT COUNT_BIG(*) FROM dbo.annual_invoice_job_items WHERE job_id=:job');$q->execute(['job'=>$id]);$total=(int)$q->fetchColumn();$q->closeCursor();
            $q=$this->pdo->prepare('UPDATE dbo.annual_invoice_jobs SET total_items=:total WHERE id=:job');$q->execute(['total'=>$total,'job'=>$id]);$this->audit->record($actorId,'ANNUAL_INVOICE_JOB_QUEUED','ANNUAL_INVOICE_JOB',(string)$id,null,['period_start'=>$fromDate,'period_end'=>$end->format('Y-m-d'),'total_items'=>$total]);$this->pdo->commit();return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function processNextBatch(int $batchSize=200): bool
    {
        $batchSize=max(1,min(1000,$batchSize));$job=$this->pdo->query("SELECT TOP(1) * FROM dbo.annual_invoice_jobs WHERE status_code IN('QUEUED','PROCESSING') ORDER BY CASE status_code WHEN 'PROCESSING' THEN 0 ELSE 1 END,created_at,id")->fetch();if(!is_array($job))return false;$jobId=(int)$job['id'];
        $this->pdo->prepare("UPDATE dbo.annual_invoice_jobs SET status_code='PROCESSING',started_at=COALESCE(started_at,SYSUTCDATETIME()),heartbeat_at=SYSUTCDATETIME(),error_message=NULL WHERE id=:id")->execute(['id'=>$jobId]);
        $items=$this->pdo->query("SELECT TOP ($batchSize) business_id FROM dbo.annual_invoice_job_items WHERE job_id=$jobId AND status_code='PENDING' ORDER BY business_id")->fetchAll(PDO::FETCH_COLUMN);
        if($items===[]){$this->refresh($jobId);return false;}
        foreach($items as $businessId)$this->processItem($job,(int)$businessId);
        $this->refresh($jobId);return true;
    }

    public function retryFailed(int $jobId): int
    {
        if($jobId<1)throw new DomainException('Job-i nuk është valid.');
        $this->pdo->beginTransaction();
        try{$q=$this->pdo->prepare("DELETE ji FROM dbo.annual_invoice_job_items ji WHERE ji.job_id=:job AND ji.status_code IN('PENDING','FAILED') AND NOT EXISTS(SELECT 1 FROM dbo.ARBK_LIST a WHERE a.REGULATION_ID=ji.business_id AND ISNULL(a.ATK_MBYLLUR,0)=0 AND TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)>0)");$q->execute(['job'=>$jobId]);$q=$this->pdo->prepare("UPDATE dbo.annual_invoice_job_items SET status_code='PENDING',error_message=NULL,updated_at=SYSUTCDATETIME() WHERE job_id=:job AND status_code='FAILED'");$q->execute(['job'=>$jobId]);$count=$q->rowCount();$q=$this->pdo->prepare("UPDATE dbo.annual_invoice_jobs SET status_code=CASE WHEN status_code='PROCESSING' THEN 'PROCESSING' ELSE 'QUEUED' END,completed_at=NULL,error_message=NULL WHERE id=:job AND status_code IN('PROCESSING','COMPLETED_WITH_ERRORS','FAILED')");$q->execute(['job'=>$jobId]);if($q->rowCount()!==1)throw new DomainException('Job-i nuk mund të riprovohet në statusin aktual.');$this->pdo->commit();$this->refresh($jobId);return $count;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function processItem(array $job,int $businessId): void
    {
        try{$this->pdo->beginTransaction();$claim=$this->pdo->prepare("UPDATE dbo.annual_invoice_job_items WITH(UPDLOCK,ROWLOCK) SET status_code='PROCESSING',updated_at=SYSUTCDATETIME() WHERE job_id=:job AND business_id=:business AND status_code='PENDING'");$claim->execute(['job'=>$job['id'],'business'=>$businessId]);if($claim->rowCount()!==1){$this->pdo->rollBack();return;}
            $q=$this->pdo->prepare("SELECT a.REGULATION_ID,a.Emri,a.ADRESA,a.NUMRI_FISKAL,a.NRBIZ,LTRIM(RTRIM(a.NACE_CODE_REG)) registered_nace,COALESCE(LTRIM(RTRIM(a.NACE_CODE_TARIFF)),LTRIM(RTRIM(n.NACE_CODE)),LTRIM(RTRIM(a.NACE_CODE_REG))) tariff_nace,COALESCE(NULLIF(LTRIM(RTRIM(a.nace_veprimtaria_tariff)),''),n.Veprimtaria,a.NACEPERSHKRIMI) description,COALESCE(TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim),TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)) tariff,(SELECT TOP(1) id FROM dbo.business_invoices WHERE business_id=a.REGULATION_ID AND fiscal_year=:year) existing_id FROM dbo.ARBK_LIST a LEFT JOIN dbo.NACE_LIST n ON n.NACErowGUID=a.NaceRowGuid WHERE a.REGULATION_ID=:business AND ISNULL(a.ATK_MBYLLUR,0)=0 AND TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)>0");$q->execute(['year'=>$job['fiscal_year'],'business'=>$businessId]);$b=$q->fetch();$q->closeCursor();if(!$b)throw new DomainException('Biznesi nuk ka tarifë të regjistruar aktive.');
            if($b['existing_id']!==null){$this->finishItem((int)$job['id'],$businessId,'SKIPPED_EXISTING',(int)$b['existing_id']);$this->pdo->commit();return;}
            if($b['tariff']===null||(float)$b['tariff']<=0){$this->finishItem((int)$job['id'],$businessId,'SKIPPED_MISSING_TARIFF');$this->pdo->commit();return;}
            $nace=strtoupper(trim((string)$b['registered_nace']));if(preg_match('/\A[0-9A-Z]{4}\z/',$nace)!==1){$this->finishItem((int)$job['id'],$businessId,'SKIPPED_INVALID_NACE');$this->pdo->commit();return;}
            $amount=round((float)$b['tariff'],2);$uniref=$this->sequences->next($nace);$q=$this->pdo->prepare("INSERT dbo.business_invoices(business_id,fiscal_year,invoice_number,uniref,amount,issued_on,due_on,notes,created_by_user_id,status_code,period_start,period_end,billed_months,annual_tariff,currency,business_name_snapshot,business_address_snapshot,fiscal_number_snapshot,registration_number_snapshot,registered_nace_code_snapshot,tariff_nace_code_snapshot,nace_description_snapshot) OUTPUT inserted.id VALUES(:business,:year,:number,:uniref,:amount,CONVERT(date,:issued_on),DATEADD(day,CONVERT(int,:due),CONVERT(date,:due_base)),:notes,:actor,'ISSUED',CONVERT(date,:start),CONVERT(date,:end),12,:annual,'EUR',:name,:address,:fiscal,:registration,:registered_nace,:tariff_nace,:description)");$q->execute(['business'=>$businessId,'year'=>$job['fiscal_year'],'number'=>'FAT-'.$uniref,'uniref'=>$uniref,'amount'=>$amount,'issued_on'=>$job['period_start'],'due'=>$job['due_days'],'due_base'=>$job['period_start'],'notes'=>'Faturë për periudhë të plotë njëvjeçare.','actor'=>$job['requested_by_user_id'],'start'=>$job['period_start'],'end'=>$job['period_end'],'annual'=>$amount,'name'=>$b['Emri'],'address'=>$b['ADRESA'],'fiscal'=>$b['NUMRI_FISKAL'],'registration'=>$b['NRBIZ'],'registered_nace'=>$nace,'tariff_nace'=>$b['tariff_nace'],'description'=>$b['description']]);$invoiceId=(int)$q->fetchColumn();$q->closeCursor();$this->finishItem((int)$job['id'],$businessId,'CREATED',$invoiceId,$amount);$this->audit->record((int)$job['requested_by_user_id'],'ANNUAL_INVOICE_CREATED','BUSINESS_INVOICE',(string)$invoiceId,null,['job_id'=>$job['id'],'business_id'=>$businessId,'uniref'=>$uniref,'amount'=>$amount]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();$this->pdo->prepare("UPDATE dbo.annual_invoice_job_items SET status_code='FAILED',error_message=:error,updated_at=SYSUTCDATETIME() WHERE job_id=:job AND business_id=:business")->execute(['error'=>mb_substr($e->getMessage(),0,1000),'job'=>$job['id'],'business'=>$businessId]);error_log('Annual invoice job '.$job['id'].' business '.$businessId."\n".$e->__toString());}
    }
    private function finishItem(int $jobId,int $businessId,string $status,?int $invoiceId=null,?float $amount=null): void{if($invoiceId!==null)$this->pdo->prepare("UPDATE dbo.business_invoices SET invoice_number=uniref WHERE id=:invoice AND uniref IS NOT NULL AND invoice_number<>uniref")->execute(['invoice'=>$invoiceId]);$this->pdo->prepare('UPDATE dbo.annual_invoice_job_items SET status_code=:status,invoice_id=:invoice,amount=:amount,error_message=NULL,updated_at=SYSUTCDATETIME() WHERE job_id=:job AND business_id=:business')->execute(['status'=>$status,'invoice'=>$invoiceId,'amount'=>$amount,'job'=>$jobId,'business'=>$businessId]);}
    private function refresh(int $jobId): void{$q=$this->pdo->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status_code NOT IN('PENDING','PROCESSING') THEN 1 ELSE 0 END) processed,SUM(CASE WHEN status_code='CREATED' THEN 1 ELSE 0 END) created,SUM(CASE WHEN status_code LIKE 'SKIPPED_%' THEN 1 ELSE 0 END) skipped,SUM(CASE WHEN status_code='FAILED' THEN 1 ELSE 0 END) failed,COALESCE(SUM(amount),0) amount,SUM(CASE WHEN status_code IN('PENDING','PROCESSING') THEN 1 ELSE 0 END) remaining FROM dbo.annual_invoice_job_items WHERE job_id=:id");$q->execute(['id'=>$jobId]);$s=$q->fetch();$status=(int)$s['remaining']>0?'PROCESSING':((int)$s['failed']>0?'COMPLETED_WITH_ERRORS':'COMPLETED');$this->pdo->prepare("UPDATE dbo.annual_invoice_jobs SET status_code=:status,total_items=:total,processed_items=:processed,created_items=:created,skipped_items=:skipped,failed_items=:failed,total_amount=:amount,heartbeat_at=SYSUTCDATETIME(),completed_at=CASE WHEN :done=1 THEN SYSUTCDATETIME() ELSE NULL END WHERE id=:id")->execute(['status'=>$status,'total'=>$s['total'],'processed'=>$s['processed'],'created'=>$s['created'],'skipped'=>$s['skipped'],'failed'=>$s['failed'],'amount'=>$s['amount'],'done'=>$status==='PROCESSING'?0:1,'id'=>$jobId]);}
}
