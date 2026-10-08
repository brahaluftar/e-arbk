<?php
declare(strict_types=1);
namespace App\Billing;
use App\Document\DocumentStore;
use App\Support\Config;
use PDO;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
final class InvoiceDocumentService
{
    public function __construct(private PDO $pdo,private Config $config,private DocumentStore $store){}
    public function ensure(int $invoiceId):string
    {
        $invoice=$this->invoice($invoiceId);$existing=(string)($invoice['pdf_storage_key']??'');if($existing!==''){try{$this->store->path($existing);return $existing;}catch(RuntimeException){}}
        if(empty($invoice['uniref']))throw new RuntimeException('Fatura e vjetër nuk ka UNIREF dhe nuk mund të gjenerohet në formatin e ri.');
        $key=$this->store->put('invoices',(new InvoicePdfRenderer($this->config))->render($invoice));$statement=$this->pdo->prepare('UPDATE dbo.business_invoices SET pdf_storage_key=:key WHERE id=:id AND pdf_storage_key IS NULL');$statement->execute(['key'=>$key,'id'=>$invoiceId]);return $key;
    }
    /** @param list<int> $invoiceIds */
    public function bulk(array $invoiceIds):string
    {
        if($invoiceIds===[]||count($invoiceIds)>1000)throw new RuntimeException('Zgjidhni 1–1000 fatura për PDF-në masive.');$combined=new Fpdi();
        foreach(array_values(array_unique($invoiceIds)) as $id){$pdf=file_get_contents($this->store->path($this->ensure((int)$id)));if(!is_string($pdf))throw new RuntimeException('Fatura PDF nuk mund të lexohej.');$pages=$combined->setSourceFile(StreamReader::createByString($pdf));for($page=1;$page<=$pages;$page++){$template=$combined->importPage($page);$size=$combined->getTemplateSize($template);$combined->AddPage($size['orientation'],[$size['width'],$size['height']]);$combined->useTemplate($template);}}
        $output=$combined->Output('S');if(!is_string($output)||!str_starts_with($output,'%PDF-'))throw new RuntimeException('PDF-ja masive nuk mund të krijohej.');return $output;
    }
    /** @return array<string,mixed> */
    private function invoice(int $id):array{$statement=$this->pdo->prepare('SELECT i.*,a.EMAIL business_email,COALESCE(p.paid_amount,0) paid_amount FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id OUTER APPLY(SELECT SUM(bp.amount) paid_amount FROM dbo.business_invoice_payments bp WHERE bp.invoice_id=i.id)p WHERE i.id=:id');$statement->execute(['id'=>$id]);$row=$statement->fetch();if(!is_array($row))throw new RuntimeException('Fatura nuk u gjet.');return $row;}
}
