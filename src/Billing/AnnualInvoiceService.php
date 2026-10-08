<?php
declare(strict_types=1);

namespace App\Billing;

use App\Service\AuditLogger;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

final class AnnualInvoiceService
{
    private UnirefSequence $sequences;
    public function __construct(private PDO $pdo,private AuditLogger $audit,?UnirefSequence $sequences=null) { $this->sequences=$sequences??new UnirefSequence($pdo); }

    /** @return array{created:int,skipped_existing:int,skipped_missing_tariff:int,skipped_invalid_nace:int,total:float} */
    public function generate(string $fromDate,int $actorId,int $dueDays=30): array
    {
        $start=DateTimeImmutable::createFromFormat('!Y-m-d',$fromDate);
        if($start===false||$start->format('Y-m-d')!==$fromDate)throw new DomainException('Data fillestare nuk është valide.');
        $year=(int)$start->format('Y');$end=$start->modify('+1 year -1 day');
        $months=12;$dueDays=max(0,min(365,$dueDays));
        $statement=$this->pdo->prepare("SELECT a.REGULATION_ID business_id,a.Emri business_name,a.ADRESA business_address,a.NUMRI_FISKAL fiscal_number,a.NRBIZ registration_number,
                LTRIM(RTRIM(a.NACE_CODE_REG)) registered_nace_code,n.NACE_CODE tariff_nace_code,n.Veprimtaria nace_description,
                COALESCE(TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim),TRY_CONVERT(decimal(18,2),n.Tarifa)) annual_tariff,
                CASE WHEN i.id IS NULL THEN 0 ELSE 1 END has_invoice
            FROM dbo.ARBK_LIST a LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
            OUTER APPLY(SELECT TOP (1) LTRIM(RTRIM(x.NACE_CODE)) NACE_CODE,x.Veprimtaria,x.Tarifa FROM dbo.NACE_LIST x
                WHERE LTRIM(RTRIM(x.NACE_CODE))=LTRIM(RTRIM(a.NACE_CODE_REG)) AND TRY_CONVERT(decimal(18,2),x.Tarifa) IS NOT NULL
                ORDER BY TRY_CONVERT(decimal(18,2),x.Tarifa),x.NACErowGUID) n
            LEFT JOIN dbo.business_invoices i ON i.business_id=a.REGULATION_ID AND i.fiscal_year=:year
            WHERE COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END)='ACTIVE'
            ORDER BY a.REGULATION_ID");
        $statement->execute(['year'=>$year]);
        // SQL Server/pdo_sqlsrv cannot reliably start the per-invoice transaction
        // while this SELECT still has an active server-side result set.
        $businesses=$statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        $result=['created'=>0,'skipped_existing'=>0,'skipped_missing_tariff'=>0,'skipped_invalid_nace'=>0,'total'=>0.0];
        foreach($businesses as $business){
            if((int)$business['has_invoice']===1){$result['skipped_existing']++;continue;}
            if($business['annual_tariff']===null||(float)$business['annual_tariff']<=0){$result['skipped_missing_tariff']++;continue;}
            $nace=strtoupper(trim((string)$business['registered_nace_code']));
            if(preg_match('/\A[0-9A-Z]{4}\z/',$nace)!==1){$result['skipped_invalid_nace']++;continue;}
            $annual=round((float)$business['annual_tariff'],2);$amount=$annual;
            $this->pdo->beginTransaction();
            try{
                $uniref=$this->sequences->next($nace);$number=$uniref;
                $insert=$this->pdo->prepare("INSERT dbo.business_invoices(business_id,fiscal_year,invoice_number,uniref,amount,issued_on,due_on,notes,created_by_user_id,status_code,period_start,period_end,billed_months,annual_tariff,currency,business_name_snapshot,business_address_snapshot,fiscal_number_snapshot,registration_number_snapshot,registered_nace_code_snapshot,tariff_nace_code_snapshot,nace_description_snapshot)
                    OUTPUT inserted.id VALUES(:business,:year,:number,:uniref,:amount,:issued,DATEADD(day,:due_days,:issued),:notes,:actor,'ISSUED',:period_start,:period_end,:months,:annual,'EUR',:name,:address,:fiscal,:registration,:registered_nace,:tariff_nace,:description)");
                $insert->execute(['business'=>$business['business_id'],'year'=>$year,'number'=>$number,'uniref'=>$uniref,'amount'=>$amount,'issued'=>$fromDate,'due_days'=>$dueDays,'notes'=>'Faturë për periudhë të plotë njëvjeçare.','actor'=>$actorId,'period_start'=>$fromDate,'period_end'=>$end->format('Y-m-d'),'months'=>$months,'annual'=>$annual,'name'=>$business['business_name'],'address'=>$business['business_address'],'fiscal'=>$business['fiscal_number'],'registration'=>$business['registration_number'],'registered_nace'=>$nace,'tariff_nace'=>$business['tariff_nace_code'],'description'=>$business['nace_description']]);
                $invoiceId=(int)$insert->fetchColumn();$insert->closeCursor();$this->audit->record($actorId,'ANNUAL_INVOICE_CREATED','BUSINESS_INVOICE',(string)$invoiceId,null,['business_id'=>$business['business_id'],'uniref'=>$uniref,'amount'=>$amount,'months'=>$months]);
                $this->pdo->commit();$result['created']++;$result['total']+=$amount;
            }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        }
        $result['total']=round($result['total'],2);return $result;
    }
}
