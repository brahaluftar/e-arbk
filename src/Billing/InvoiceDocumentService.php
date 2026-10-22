<?php
declare(strict_types=1);
namespace App\Billing;
use App\Repository\InvoiceRepository;
use App\Support\Config;
use DomainException;
use PDO;
use RuntimeException;

/**
 * Invoice PDFs are rendered on demand so that status, paid amount and previous debt are always current.
 */
final class InvoiceDocumentService
{
    public const BULK_PAGE_SIZE = 500;

    public function __construct(private PDO $pdo,private Config $config){}

    public function render(int $invoiceId,string $language='sq'):string
    {
        $invoice=(new InvoiceRepository($this->pdo))->document($invoiceId);
        if($invoice===null)throw new DomainException('Fatura nuk u gjet.');
        if(empty($invoice['uniref']))throw new DomainException('Kjo faturë e vjetër nuk ka UNIREF; PDF-ja nuk mund të krijohet.');
        return (new InvoicePdfRenderer($this->config))->render($invoice,$language);
    }

    /** Number of bulk PDF parts for a fiscal year. */
    public function bulkParts(int $year):int
    {
        $statement=$this->pdo->prepare('SELECT COUNT_BIG(*) FROM dbo.business_invoices WHERE fiscal_year=:year AND uniref IS NOT NULL');$statement->execute(['year'=>$year]);
        return (int)ceil((int)$statement->fetchColumn()/self::BULK_PAGE_SIZE);
    }

    /** One PDF with up to BULK_PAGE_SIZE invoices of the year (part 1, 2, …). */
    public function bulk(int $year,int $part,string $language='sq'):string
    {
        $part=max(1,$part);$offset=($part-1)*self::BULK_PAGE_SIZE;$size=self::BULK_PAGE_SIZE;
        $statement=$this->pdo->prepare(InvoiceRepository::documentSelect()." WHERE i.fiscal_year=:year AND i.uniref IS NOT NULL ORDER BY i.id OFFSET $offset ROWS FETCH NEXT $size ROWS ONLY");
        $statement->execute(['year'=>$year]);
        $pdf=new \FPDF('P','mm','A4');$pdf->SetCompression(true);$renderer=new InvoicePdfRenderer($this->config);$count=0;
        while($invoice=$statement->fetch(PDO::FETCH_ASSOC)){$renderer->append($pdf,$invoice,$language);$count++;}
        $statement->closeCursor();
        if($count===0)throw new DomainException('Nuk ka fatura me UNIREF për këtë pjesë.');
        $output=$pdf->Output('S');if(!is_string($output)||!str_starts_with($output,'%PDF-'))throw new RuntimeException('PDF-ja masive nuk mund të krijohej.');return $output;
    }
}
