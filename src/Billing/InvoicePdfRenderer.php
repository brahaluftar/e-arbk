<?php
declare(strict_types=1);
namespace App\Billing;
use App\Document\PdfFont;
use App\Document\PdfText;
use App\Support\Config;
use RuntimeException;

/**
 * Municipal invoice, one A4 page per invoice, in Albanian, English or Serbian.
 * Expects the columns of InvoiceRepository::documentSelect() (paid_amount, previous_debt, balance_status, business_email).
 */
final class InvoicePdfRenderer
{
    use PdfText;
    private const BLUE=[19,82,133]; private const PALE=[242,246,249]; private const LINE=[215,224,231]; private const INK=[24,43,63]; private const RED=[158,36,48];
    private const CODE39=['0'=>'101001101101','1'=>'110100101011','2'=>'101100101011','3'=>'110110010101','4'=>'101001101011','5'=>'110100110101','6'=>'101100110101','7'=>'101001011011','8'=>'110100101101','9'=>'101100101101','A'=>'110101001011','B'=>'101101001011','C'=>'110110100101','D'=>'101011001011','E'=>'110101100101','F'=>'101101100101','G'=>'101010011011','H'=>'110101001101','I'=>'101101001101','J'=>'101011001101','K'=>'110101010011','L'=>'101101010011','M'=>'110110101001','N'=>'101011010011','O'=>'110101101001','P'=>'101101101001','Q'=>'101010110011','R'=>'110101011001','S'=>'101101011001','T'=>'101011011001','U'=>'110010101011','V'=>'100110101011','W'=>'110011010101','X'=>'100101101011','Y'=>'110010110101','Z'=>'100110110101','-'=>'100101011011','.'=>'110010101101',' '=>'100110101101','*'=>'100101101101'];

    public function __construct(private Config $config){}

    /** @param array<string,mixed> $invoice */
    public function render(array $invoice,string $language='sq'):string
    {
        $pdf=new \FPDF('P','mm','A4');$pdf->SetCompression(true);$pdf->SetAutoPageBreak(false);
        $this->append($pdf,$invoice,$language);
        $out=$pdf->Output('S');if(!is_string($out)||!str_starts_with($out,'%PDF-'))throw new RuntimeException('Gjenerimi i faturës PDF dështoi.');return $out;
    }

    /**
     * Layout follows the five parts of the municipal invoice: 1 header (no UNIREF), 2 taxpayer with client number,
     * 3 invoice details with the invoice number, 4 tax line, payment reference and totals (tax of the year, previous debt,
     * total), 5 municipal contacts, legal basis, payment terms and the enforcement warning.
     * @param array<string,mixed> $i
     */
    public function append(\FPDF $pdf,array $i,string $language='sq'):void
    {
        $tr=new InvoiceTranslations($language,$this->config);$font=PdfFont::NAME;
        $this->registerFonts($pdf);$pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(22,16,22);$pdf->AddPage();$pdf->SetDrawColor(...self::LINE);$pdf->SetTextColor(...self::INK);
        $status=(string)($i['balance_status']??$i['status_code']??'ISSUED');$year=(int)($i['fiscal_year']??substr((string)$i['issued_on'],0,4));

        // 1. Header: logo, system, municipality and issuing directorate; document title and fiscal year.
        $pdf->SetFillColor(...self::BLUE);$pdf->Rect(22,16,166,32,'F');$logo=dirname(__DIR__,2).'/public/logo_prishtina.png';
        if(is_file($logo))$pdf->Image($logo,27,17.5,15,29);
        $pdf->SetTextColor(255,255,255);$pdf->SetXY(47,21);$pdf->SetFont($font,'B',13);$pdf->Cell(95,7,$this->t($tr->t('system')));
        $pdf->SetXY(47,28.5);$pdf->SetFont($font,'B',8.5);$pdf->Cell(95,5,$this->t($tr->municipality()));
        $pdf->SetXY(47,34);$pdf->SetFont($font,'',8);$pdf->MultiCell(92,4,$this->t($tr->t('issuer')),0,'L');
        $pdf->SetXY(130,22);$pdf->SetFont($font,'B',19);$pdf->Cell(53,9,$this->t($tr->t('invoice')),0,1,'R');
        $pdf->SetXY(130,33);$pdf->SetFont($font,'',9);$pdf->Cell(53,5,$this->t($tr->t('fiscal_year').' '.$year),0,1,'R');
        $pdf->SetTextColor(...self::INK);

        // 2. Taxpayer, with the municipal client number.
        $this->card($pdf,22,53,80,50,$tr->t('subject'));
        $pdf->SetXY(26,65);$pdf->SetFont($font,'B',9.5);$pdf->MultiCell(72,4.8,$this->t(mb_substr((string)$i['business_name_snapshot'],0,110)),0,'L');
        $pdf->SetFont($font,'',8);
        foreach([(string)($i['business_address_snapshot']??''),(string)($i['business_email']??'')] as $line)if($line!==''){$pdf->SetX(26);$pdf->MultiCell(72,4.2,$this->t(mb_substr($line,0,90)),0,'L');}
        $pdf->SetXY(26,89);$pdf->SetFont($font,'B',8.5);$pdf->Cell(26,5.5,$this->t($tr->t('client_number').':'));$pdf->SetFont($font,'B',10);$pdf->Cell(46,5.5,$this->t((string)($i['client_number']??'—')));
        $pdf->SetXY(26,95.5);$pdf->SetFont($font,'',8);$pdf->Cell(26,5,$this->t($tr->t('registration').':'));$pdf->Cell(46,5,$this->t((string)($i['registration_number_snapshot']??'')));

        // 3. Invoice details: invoice number instead of the UNIREF.
        $this->card($pdf,108,53,80,50,$tr->t('details'));
        $pdf->SetXY(112,65);$pdf->SetFont($font,'B',8.5);$pdf->Cell(26,6,$this->t($tr->t('invoice_number').':'));$pdf->SetFont($font,'B',10);$pdf->Cell(46,6,$this->t((string)$i['invoice_number']));
        $details=[[$tr->t('date'),$this->date($i['issued_on'])],[$tr->t('period'),$this->date($i['period_start']??null).' - '.$this->date($i['period_end']??null)],[$tr->t('status'),$tr->status($status)],[$tr->t('nace'),(string)($i['registered_nace_code_snapshot']??'')]];
        $y=72.5;foreach($details as [$label,$value]){$pdf->SetXY(112,$y);$pdf->SetFont($font,'B',8);$pdf->Cell(26,5,$this->t($label.':'));$pdf->SetFont($font,'',8);$pdf->Cell(46,5,$this->t(mb_substr($value,0,34)));$y+=6.8;}

        // 4. Tax line: activity tax or professional-services tax, by NACE code.
        $pdf->SetFillColor(235,241,246);$pdf->Rect(22,108,166,9,'F');$pdf->SetXY(25,110.5);$pdf->SetFont($font,'B',8);
        $pdf->Cell(108,5,$this->t($tr->t('description')));$pdf->Cell(25,5,$this->t($tr->t('tariff')),0,0,'R');$pdf->Cell(25,5,$this->t($tr->t('total')),0,0,'R');
        $nace=(string)($i['tariff_nace_code_snapshot']??'')!==''?(string)$i['tariff_nace_code_snapshot']:(string)($i['registered_nace_code_snapshot']??'');
        $activity=trim((string)($i['nace_description_snapshot']??''));
        $pdf->SetXY(25,120);$pdf->SetFont($font,'B',8.5);$pdf->Cell(108,4.5,$this->t($tr->taxLabel($nace)));
        if($activity!==''){$pdf->SetXY(25,124.5);$pdf->SetFont($font,'',8);$pdf->MultiCell(105,4,$this->t(mb_substr($activity,0,140)),0,'L');}
        $pdf->SetXY(133,120);$pdf->SetFont($font,'',8.5);$pdf->Cell(25,4.5,$this->money($i['annual_tariff']??$i['amount']),0,0,'R');$pdf->Cell(25,4.5,$this->money($i['amount']),0,0,'R');
        $pdf->Line(22,134,188,134);

        // Payment reference: framed UNIREF in large type, with the barcode below it.
        $uniref=(string)$i['uniref'];
        $pdf->SetDrawColor(...self::BLUE);$pdf->SetLineWidth(0.5);$pdf->Rect(22,139,96,23,'D');$pdf->SetLineWidth(0.2);$pdf->SetDrawColor(...self::LINE);
        $pdf->SetXY(22,141);$pdf->SetFont($font,'B',8);$pdf->SetTextColor(...self::BLUE);$pdf->Cell(96,4.5,$this->t($tr->t('uniref_label')),0,1,'C');
        $pdf->SetX(22);$pdf->SetFont($font,'B',19);$pdf->SetTextColor(0,0,0);$pdf->Cell(96,10,$this->t($uniref),0,1,'C');
        $pdf->SetX(22);$pdf->SetFont($font,'',7);$pdf->SetTextColor(...self::INK);$pdf->Cell(96,4,$this->t($tr->t('barcode_note')),0,0,'C');
        $this->barcode($pdf,26,166,88,14,$uniref);

        // Amounts: tax of the year, previous debt, (paid when any) and the total to pay.
        $amount=$status==='CANCELLED'?0.0:(float)$i['amount'];$paid=(float)($i['paid_amount']??0);$previous=(float)($i['previous_debt']??0);
        $total=max(0,$amount-$paid)+$previous;
        $rows=[[sprintf($tr->t('tax_for_year'),$year),$amount],[$tr->t('previous_debt'),$previous]];
        if($paid>0.004)$rows[]=[$tr->t('paid'),-$paid];
        $pdf->SetFillColor(...self::PALE);$pdf->Rect(124,139,64,41,'F');
        $y=142;foreach($rows as [$label,$value]){$pdf->SetXY(127,$y);$pdf->SetFont($font,'',8.5);$pdf->Cell(33,6,$this->t($label));$pdf->SetFont($font,'B',8.5);$pdf->Cell(25,6,$this->money($value),0,0,'R');$y+=7;}
        $pdf->SetDrawColor(...self::BLUE);$pdf->Line(127,166,185,166);$pdf->SetDrawColor(...self::LINE);
        $pdf->SetXY(127,169);$pdf->SetFont($font,'B',11);$pdf->SetTextColor(...self::BLUE);$pdf->Cell(28,8,$this->t($tr->t('grand_total')));$pdf->Cell(30,8,$this->money($total),0,0,'R');$pdf->SetTextColor(...self::INK);

        // 5. Municipal contacts, legal basis, payment terms and warning.
        $pdf->SetFillColor(235,241,246);$pdf->Rect(22,186,166,8,'F');$widths=[42,52,72];$labels=[$tr->t('address'),$tr->t('email'),$tr->t('iban')];$x=22;
        foreach($labels as $k=>$label){$pdf->SetXY($x+3,188);$pdf->SetFont($font,'B',7.5);$pdf->Cell($widths[$k]-6,4,$this->t($label.':'));$x+=$widths[$k];}
        $pdf->SetFont($font,'',7.5);$pdf->SetXY(25,196);$pdf->MultiCell(38,3.8,$this->t($this->config->string('MUNICIPALITY_ADDRESS')),0,'L');$pdf->SetXY(67,196);$pdf->MultiCell(48,3.8,$this->t($this->config->string('MUNICIPALITY_CONTACT')),0,'L');$pdf->SetXY(119,196);$pdf->SetFont($font,'B',8);$pdf->MultiCell(66,3.8,$this->t($this->config->string('MUNICIPAL_BANK_ACCOUNT')),0,'L');

        $pdf->SetXY(22,208);$pdf->SetFont($font,'B',8);$pdf->SetTextColor(...self::BLUE);$pdf->Cell(166,5,$this->t($tr->t('legal_basis_title')),0,1);$pdf->SetTextColor(...self::INK);
        $pdf->SetX(22);$pdf->SetFont($font,'',7.5);$pdf->MultiCell(166,3.7,$this->t($tr->t('legal_basis')),0,'J');
        $pdf->SetXY(22,max($pdf->GetY()+3,233));$pdf->SetFont($font,'B',9);$pdf->MultiCell(166,5,$this->t($tr->t('payment_terms')),0,'L');
        $pdf->Ln(1);$pdf->SetX(22);$pdf->SetFont($font,'B',9);$pdf->SetTextColor(...self::RED);$pdf->MultiCell(166,5,$this->t($tr->t('warning')),0,'L');$pdf->SetTextColor(...self::INK);
        $pdf->SetXY(22,max($pdf->GetY()+4,258));$pdf->SetTextColor(90,105,120);$pdf->SetFont($font,'',7);$pdf->MultiCell(166,3.6,$this->t($tr->t('legal')),0,'L');
        $pdf->SetX(22);$pdf->SetFont($font,'B',7);$pdf->Cell(166,4.5,$this->t($tr->t('digital')));
        $pdf->SetTextColor(...self::INK);
    }

    private function card(\FPDF $pdf,float $x,float $y,float $w,float $h,string $title):void{$pdf->SetFillColor(...self::PALE);$pdf->Rect($x,$y,$w,$h,'DF');$pdf->SetTextColor(...self::BLUE);$pdf->SetXY($x+4,$y+5);$pdf->SetFont(PdfFont::NAME,'B',9);$pdf->Cell($w-8,5,$this->t($title));$pdf->SetTextColor(...self::INK);}
    private function barcode(\FPDF $pdf,float $x,float $y,float $w,float $h,string $value):void{$value='*'.strtoupper($value).'*';$bits='';foreach(str_split($value) as $char)$bits.=(self::CODE39[$char]??self::CODE39['-']).'0';$unit=$w/max(1,strlen($bits));$pdf->SetFillColor(0,0,0);foreach(str_split($bits) as $index=>$bit)if($bit==='1')$pdf->Rect($x+$index*$unit,$y,$unit+.03,$h,'F');}
    private function money(mixed $amount):string{return $this->t(number_format((float)$amount,2,',','.').' €');}
    private function date(mixed $value):string{if($value===null||$value==='')return '—';$time=strtotime(substr((string)$value,0,10));return $time===false?(string)$value:date('d.m.Y',$time);}
    private function t(string $value):string{return $this->pdfText($value);}
}
