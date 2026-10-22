<?php
declare(strict_types=1);
namespace App\Billing;
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
    private const BLUE=[19,82,133]; private const PALE=[242,246,249]; private const LINE=[215,224,231]; private const INK=[24,43,63];
    private const CODE39=['0'=>'101001101101','1'=>'110100101011','2'=>'101100101011','3'=>'110110010101','4'=>'101001101011','5'=>'110100110101','6'=>'101100110101','7'=>'101001011011','8'=>'110100101101','9'=>'101100101101','A'=>'110101001011','B'=>'101101001011','C'=>'110110100101','D'=>'101011001011','E'=>'110101100101','F'=>'101101100101','G'=>'101010011011','H'=>'110101001101','I'=>'101101001101','J'=>'101011001101','K'=>'110101010011','L'=>'101101010011','M'=>'110110101001','N'=>'101011010011','O'=>'110101101001','P'=>'101101101001','Q'=>'101010110011','R'=>'110101011001','S'=>'101101011001','T'=>'101011011001','U'=>'110010101011','V'=>'100110101011','W'=>'110011010101','X'=>'100101101011','Y'=>'110010110101','Z'=>'100110110101','-'=>'100101011011','.'=>'110010101101',' '=>'100110101101','*'=>'100101101101'];

    public function __construct(private Config $config){}

    /** @param array<string,mixed> $invoice */
    public function render(array $invoice,string $language='sq'):string
    {
        $pdf=new \FPDF('P','mm','A4');$pdf->SetCompression(true);$pdf->SetAutoPageBreak(false);
        $this->append($pdf,$invoice,$language);
        $out=$pdf->Output('S');if(!is_string($out)||!str_starts_with($out,'%PDF-'))throw new RuntimeException('Gjenerimi i faturës PDF dështoi.');return $out;
    }

    /** @param array<string,mixed> $i */
    public function append(\FPDF $pdf,array $i,string $language='sq'):void
    {
        $tr=new InvoiceTranslations($language,$this->config);$font=self::PDF_FONT;
        $this->registerFonts($pdf);$pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(22,18,22);$pdf->AddPage();$pdf->SetDrawColor(...self::LINE);$pdf->SetTextColor(...self::INK);

        // Header band with logo, system name and invoice number.
        $pdf->SetFillColor(...self::BLUE);$pdf->Rect(22,18,166,34,'F');$logo=dirname(__DIR__,2).'/public/logo_prishtina.png';
        if(is_file($logo))$pdf->Image($logo,27,20,15.6,30);
        $pdf->SetTextColor(255,255,255);$pdf->SetXY(47,26);$pdf->SetFont($font,'B',13);$pdf->Cell(93,7,$this->t($tr->t('system')));
        $pdf->SetXY(47,34);$pdf->SetFont($font,'',8);$pdf->Cell(93,5,$this->t($tr->municipality()));
        $pdf->SetXY(130,24);$pdf->SetFont($font,'B',14);$pdf->Cell(52,7,$this->t($tr->t('invoice').':'),0,1,'R');
        $number=(string)(preg_replace('/\AFAT-/','',(string)$i['invoice_number'])??$i['invoice_number']);
        $pdf->SetXY(110,34);$pdf->SetFont($font,'B',14);$pdf->Cell(72,8,$this->t($number),0,1,'R');
        $pdf->SetTextColor(...self::INK);

        // Taxpayer and invoice details.
        $this->card($pdf,22,57,77,56,$tr->t('subject'));
        $pdf->SetXY(26,70);$pdf->SetFont($font,'B',9);$pdf->MultiCell(69,5,$this->t((string)$i['business_name_snapshot']));
        $pdf->SetFont($font,'',8);
        foreach([(string)($i['business_address_snapshot']??''),(string)($i['business_email']??'')] as $line)if($line!==''){$pdf->SetX(26);$pdf->MultiCell(69,4.6,$this->t($line));}
        $pdf->SetX(26);$pdf->Cell(69,5,$this->t($tr->t('registration').': '.(string)($i['registration_number_snapshot']??'')));
        $status=(string)($i['balance_status']??$i['status_code']??'ISSUED');
        $this->card($pdf,109,57,79,56,$tr->t('details'));
        $details=[[$tr->t('date'),$this->date($i['issued_on'])],[$tr->t('period'),$this->date($i['period_start']).' - '.$this->date($i['period_end'])],[$tr->t('uniref'),(string)$i['uniref']],[$tr->t('status'),$tr->status($status)],[$tr->t('nace'),(string)$i['registered_nace_code_snapshot']],[$tr->t('due'),$this->date($i['due_on']??null)]];
        $y=69;foreach($details as [$label,$value]){$pdf->SetXY(113,$y);$pdf->SetFont($font,'B',7.7);$pdf->Cell(24,5,$this->t($label.':'));$pdf->SetFont($font,'',7.7);$pdf->Cell(47,5,$this->t(mb_substr($value,0,34)));$y+=6.8;}

        // Line item.
        $pdf->SetFillColor(235,241,246);$pdf->Rect(22,118,166,10,'F');$pdf->SetXY(25,121);$pdf->SetFont($font,'B',8);
        $pdf->Cell(108,5,$this->t($tr->t('description')));$pdf->Cell(25,5,$this->t($tr->t('tariff')),0,0,'R');$pdf->Cell(25,5,$this->t($tr->t('total')),0,0,'R');
        $pdf->SetXY(25,131);$pdf->SetFont($font,'',8);
        $pdf->MultiCell(108,4.5,$this->t(mb_substr($tr->t('fee_prefix').(string)($i['nace_description_snapshot']??''),0,150)));
        $pdf->SetXY(133,131);$pdf->Cell(25,5,$this->money($i['annual_tariff']??$i['amount']),0,0,'R');$pdf->Cell(25,5,$this->money($i['amount']),0,0,'R');
        $pdf->Line(22,142,188,142);

        // Payment barcode with a readable UNIREF below it.
        $this->barcode($pdf,26,149,91,15,(string)$i['uniref']);
        $pdf->SetXY(26,165.5);$pdf->SetFont($font,'B',10);$pdf->Cell(91,5.5,$this->t(implode(' ',str_split((string)$i['uniref']))),0,1,'C');
        $pdf->SetX(26);$pdf->SetFont($font,'',7);$pdf->Cell(91,4,$this->t($tr->t('barcode_note')),0,0,'C');

        // Totals: invoice total, paid, previous debt and total obligations.
        $amount=(float)$i['amount'];$paid=(float)($i['paid_amount']??0);$previous=(float)($i['previous_debt']??0);
        $open=$status==='CANCELLED'?0.0:max(0,$amount-$paid);$obligations=$open+$previous;
        $pdf->SetFillColor(...self::PALE);$pdf->Rect(119,147,69,41,'F');
        $rows=[[$tr->t('invoice_total'),$status==='CANCELLED'?0.0:$amount],[$tr->t('paid'),$paid],[$tr->t('previous_debt'),$previous]];
        $y=150;foreach($rows as [$label,$value]){$pdf->SetXY(123,$y);$pdf->SetFont($font,'',8);$pdf->Cell(36,6,$this->t($label));$pdf->SetFont($font,'B',8);$pdf->Cell(25,6,$this->money($value),0,0,'R');$y+=7;}
        $pdf->Line(123,172,184,172);
        $pdf->SetXY(123,175);$pdf->SetFont($font,'B',9.5);$pdf->SetTextColor(...self::BLUE);$pdf->Cell(33,8,$this->t($tr->t('total_obligations')));$pdf->Cell(28,8,$this->money($obligations),0,0,'R');$pdf->SetTextColor(...self::INK);

        // Municipal contact strip.
        $pdf->SetFillColor(235,241,246);$pdf->Rect(22,194,166,10,'F');$widths=[42,52,72];$labels=[$tr->t('address'),$tr->t('email'),$tr->t('iban')];$x=22;
        foreach($labels as $k=>$label){$pdf->SetXY($x+3,197);$pdf->SetFont($font,'B',7);$pdf->Cell($widths[$k]-6,4,$this->t($label.':'));$x+=$widths[$k];}
        $pdf->SetFont($font,'',7);$pdf->SetXY(25,207);$pdf->MultiCell(38,4,$this->t($this->config->string('MUNICIPALITY_ADDRESS')));$pdf->SetXY(67,207);$pdf->MultiCell(48,4,$this->t($this->config->string('MUNICIPALITY_CONTACT')));$pdf->SetXY(119,207);$pdf->MultiCell(66,4,$this->t($this->config->string('MUNICIPAL_BANK_ACCOUNT')));

        $pdf->SetTextColor(90,105,120);$pdf->SetXY(22,232);$pdf->SetFont($font,'',7);$pdf->MultiCell(166,4,$this->t($tr->legal()));
        $pdf->SetX(22);$pdf->SetFont($font,'B',7);$pdf->Cell(166,5,$this->t($tr->t('due_line').': '.$this->date($i['due_on']??null).'  |  '.$tr->t('digital')));
        $pdf->SetTextColor(...self::INK);
    }

    private function card(\FPDF $pdf,float $x,float $y,float $w,float $h,string $title):void{$pdf->SetFillColor(...self::PALE);$pdf->Rect($x,$y,$w,$h,'DF');$pdf->SetTextColor(...self::BLUE);$pdf->SetXY($x+4,$y+5);$pdf->SetFont(self::PDF_FONT,'B',9);$pdf->Cell($w-8,5,$this->t($title));$pdf->SetTextColor(...self::INK);}
    private function barcode(\FPDF $pdf,float $x,float $y,float $w,float $h,string $value):void{$value='*'.strtoupper($value).'*';$bits='';foreach(str_split($value) as $char)$bits.=(self::CODE39[$char]??self::CODE39['-']).'0';$unit=$w/max(1,strlen($bits));$pdf->SetFillColor(0,0,0);foreach(str_split($bits) as $index=>$bit)if($bit==='1')$pdf->Rect($x+$index*$unit,$y,$unit+.03,$h,'F');}
    private function money(mixed $amount):string{return $this->t(number_format((float)$amount,2,',','.').' €');}
    private function date(mixed $value):string{if($value===null||$value==='')return '—';$time=strtotime(substr((string)$value,0,10));return $time===false?(string)$value:date('d.m.Y',$time);}
    private function t(string $value):string{return $this->pdfText($value);}
}
