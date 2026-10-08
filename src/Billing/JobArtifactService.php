<?php
declare(strict_types=1);
namespace App\Billing;
use App\Document\JobArtifactStore;
use App\Export\XlsxWriter;
use App\Support\Config;
use PDO;
use RuntimeException;
use Throwable;
final class JobArtifactService
{
    public function __construct(private PDO $pdo,private Config $config,private JobArtifactStore $store){}
    public function processNext():bool
    {
        $job=$this->pdo->query("UPDATE target WITH(UPDLOCK,READPAST,ROWLOCK) SET artifact_status='BUILDING',artifact_error=NULL OUTPUT inserted.* FROM(SELECT TOP(1)* FROM dbo.annual_invoice_jobs WHERE status_code IN('COMPLETED','COMPLETED_WITH_ERRORS') AND artifact_status='PENDING' ORDER BY id)target")->fetch();if(!is_array($job))return false;
        $id=(int)$job['id'];$date=(string)$job['period_start'];[$pdfKey,$pdfPath]=$this->store->destination($id,$date,'pdf');[$xlsxKey,$xlsxPath]=$this->store->destination($id,$date,'xlsx');$pdfTemp=$pdfPath.'.tmp';$xlsxTemp=$xlsxPath.'.tmp';
        try{$count=$this->pdf($id,$pdfTemp);if($count<1)throw new RuntimeException('Job-i nuk ka fatura të krijuara për eksport.');$this->xlsx($id,$xlsxTemp);if(!rename($pdfTemp,$pdfPath)||!rename($xlsxTemp,$xlsxPath))throw new RuntimeException('Eksportet nuk mund të aktivizoheshin.');@chmod($pdfPath,0660);@chmod($xlsxPath,0660);$q=$this->pdo->prepare("UPDATE dbo.annual_invoice_jobs SET artifact_status='READY',pdf_storage_key=:pdf,xlsx_storage_key=:xlsx,artifacts_generated_at=SYSUTCDATETIME() WHERE id=:id");$q->execute(['pdf'=>$pdfKey,'xlsx'=>$xlsxKey,'id'=>$id]);return true;
        }catch(Throwable $e){@unlink($pdfTemp);@unlink($xlsxTemp);$this->pdo->prepare("UPDATE dbo.annual_invoice_jobs SET artifact_status='FAILED',artifact_error=:error WHERE id=:id")->execute(['error'=>mb_substr($e->getMessage(),0,1900),'id'=>$id]);error_log('Invoice job artifacts '.$id."\n".$e->__toString());return true;}
    }
    private function pdf(int $jobId,string $path):int
    {
        $q=$this->pdo->prepare("SELECT i.*,a.EMAIL business_email,COALESCE(p.paid_amount,0) paid_amount FROM dbo.annual_invoice_job_items ji JOIN dbo.business_invoices i ON i.id=ji.invoice_id JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id OUTER APPLY(SELECT SUM(bp.amount) paid_amount FROM dbo.business_invoice_payments bp WHERE bp.invoice_id=i.id)p WHERE ji.job_id=:job AND ji.status_code='CREATED' ORDER BY i.id");$q->execute(['job'=>$jobId]);$pdf=new \FPDF('P','mm','A4');$pdf->SetCompression(true);$renderer=new InvoicePdfRenderer($this->config);$count=0;while($invoice=$q->fetch(PDO::FETCH_ASSOC)){$renderer->append($pdf,$invoice);$count++;}$q->closeCursor();if($count>0)$pdf->Output('F',$path);return $count;
    }
    private function xlsx(int $jobId,string $path):void
    {
        $q=$this->pdo->prepare("SELECT i.invoice_number,i.uniref,i.issued_on,i.due_on,i.period_start,i.period_end,i.business_name_snapshot,i.business_address_snapshot,i.fiscal_number_snapshot,i.registration_number_snapshot,i.registered_nace_code_snapshot,i.tariff_nace_code_snapshot,i.nace_description_snapshot,i.annual_tariff,i.amount,i.currency,i.status_code FROM dbo.annual_invoice_job_items ji JOIN dbo.business_invoices i ON i.id=ji.invoice_id WHERE ji.job_id=:job AND ji.status_code='CREATED' ORDER BY i.id");$q->execute(['job'=>$jobId]);$rows=(function()use($q){try{while($row=$q->fetch(PDO::FETCH_ASSOC))yield $row;}finally{$q->closeCursor();}})();(new XlsxWriter())->write($path,['invoice_number'=>'Numri i faturës','uniref'=>'UNIREF','issued_on'=>'Data e lëshimit','due_on'=>'Afati i pagesës','period_start'=>'Vlen nga','period_end'=>'Vlen deri','business_name_snapshot'=>'Biznesi','business_address_snapshot'=>'Adresa','fiscal_number_snapshot'=>'Numri fiskal','registration_number_snapshot'=>'Numri i biznesit','registered_nace_code_snapshot'=>'NACE i regjistruar','tariff_nace_code_snapshot'=>'NACE_LIST','nace_description_snapshot'=>'Përshkrimi','annual_tariff'=>'Tarifa vjetore','amount'=>'Totali','currency'=>'Valuta','status_code'=>'Statusi'],$rows,'Faturat job '.$jobId);
    }
}
