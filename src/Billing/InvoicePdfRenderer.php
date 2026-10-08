<?php
declare(strict_types=1);
namespace App\Billing;
use App\Document\PdfText;
use App\Support\Config;
use RuntimeException;
final class InvoicePdfRenderer
{
    use PdfText;
    private string $naceDescription='';
    private const BLUE=[19,82,133]; private const PALE=[242,246,249]; private const LINE=[215,224,231];
    private const CODE39=['0'=>'101001101101','1'=>'110100101011','2'=>'101100101011','3'=>'110110010101','4'=>'101001101011','5'=>'110100110101','6'=>'101100110101','7'=>'101001011011','8'=>'110100101101','9'=>'101100101101','A'=>'110101001011','B'=>'101101001011','C'=>'110110100101','D'=>'101011001011','E'=>'110101100101','F'=>'101101100101','G'=>'101010011011','H'=>'110101001101','I'=>'101101001101','J'=>'101011001101','K'=>'110101010011','L'=>'101101010011','M'=>'110110101001','N'=>'101011010011','O'=>'110101101001','P'=>'101101101001','Q'=>'101010110011','R'=>'110101011001','S'=>'101101011001','T'=>'101011011001','U'=>'110010101011','V'=>'100110101011','W'=>'110011010101','X'=>'100101101011','Y'=>'110010110101','Z'=>'100110110101','-'=>'100101011011','.'=>'110010101101',' '=>'100110101101','*'=>'100101101101'];
    public function __construct(private Config $config){}
    public function render(array $invoice):string{$pdf=new \FPDF('P','mm','A4');$pdf->SetCompression(true);$this->append($pdf,$invoice);$out=$pdf->Output('S');if(!is_string($out)||!str_starts_with($out,'%PDF-'))throw new RuntimeException('Gjenerimi i faturës PDF dështoi.');return $out;}
    public function append(\FPDF $pdf,array $i):void
    {
        $this->naceDescription=(string)($i['nace_description_snapshot']??'');
        $pdf->SetMargins(22,18,22);$pdf->AddPage();$pdf->SetDrawColor(...self::LINE);$pdf->SetTextColor(24,43,63);
        $pdf->SetFillColor(...self::BLUE);$pdf->Rect(22,18,166,34,'F');$logo=dirname(__DIR__,2).'/public/logo_prishtina.png';if(is_file($logo))$pdf->Image($logo,27,20,15.6,30);else{$pdf->SetFillColor(255,255,255);$pdf->Rect(27,20,15.6,30,'F');$pdf->SetTextColor(...self::BLUE);$pdf->SetFont('Arial','B',16);$pdf->SetXY(28,30);$pdf->Cell(13,8,'P',0,0,'C');}
        $pdf->SetTextColor(255,255,255);$pdf->SetXY(47,26);$pdf->SetFont('Arial','B',13);$pdf->Cell(93,7,$this->t('Sistemi i Faturimit Komunal'));$pdf->SetXY(47,34);$pdf->SetFont('Arial','',8);$pdf->Cell(93,5,$this->t($this->config->string('MUNICIPALITY_NAME')));$pdf->SetXY(142,24);$pdf->SetFont('Arial','B',15);$pdf->Cell(38,7,$this->t('Fatura:'),0,1,'R');$pdf->SetXY(142,34);$pdf->SetFont('Arial','B',16);$pdf->Cell(38,8,$this->t(preg_replace('/\AFAT-/','',(string)$i['invoice_number'])??(string)$i['invoice_number']),0,1,'R');
        $this->card($pdf,22,57,77,56,'Klienti');$pdf->SetXY(26,70);$pdf->SetFont('Arial','B',9);$pdf->MultiCell(69,5,$this->t((string)$i['business_name_snapshot']));$pdf->SetX(26);$pdf->SetFont('Arial','',8);$pdf->MultiCell(69,5,$this->t((string)($i['business_address_snapshot']??'')));$pdf->SetX(26);$pdf->MultiCell(69,5,$this->t((string)($i['business_email']??'')));$pdf->SetX(26);$pdf->Cell(69,5,$this->t('NRB: '.(string)($i['registration_number_snapshot']??'')));
        $this->card($pdf,109,57,79,56,'Detajet e faturës');$details=[['Data:',(string)$i['issued_on']],['Periudha:',(string)$i['period_start'].' - '.(string)$i['period_end']],['UNIREF:',(string)$i['uniref']],['Statusi:',$this->status($i)],['NACE:',(string)$i['registered_nace_code_snapshot']]];$y=70;foreach($details as [$label,$value]){$pdf->SetXY(113,$y);$pdf->SetFont('Arial','B',7.7);$pdf->Cell(21,5,$this->t($label));$pdf->SetFont('Arial','',7.7);$pdf->Cell(50,5,$this->t(mb_substr($value,0,34)));$y+=7;}
        $pdf->SetFillColor(235,241,246);$pdf->Rect(22,118,166,10,'F');$pdf->SetXY(25,121);$pdf->SetFont('Arial','B',8);$pdf->Cell(108,5,$this->t('Përshkrimi'));$pdf->Cell(25,5,$this->t('Çmimi'),0,0,'R');$pdf->Cell(25,5,$this->t('Totali'),0,0,'R');$pdf->SetXY(25,132);$pdf->SetFont('Arial','',8);$pdf->Cell(108,8,$this->t(mb_substr((string)$i['nace_description_snapshot'],0,72)));$pdf->Cell(25,8,$this->money($i['annual_tariff']).' EUR',0,0,'R');$pdf->Cell(25,8,$this->money($i['amount']).' EUR',0,0,'R');$pdf->Line(22,142,188,142);
        $this->barcode($pdf,26,150,91,14,(string)$i['uniref']);$pdf->SetXY(26,165);$pdf->SetFont('Arial','',6);$pdf->Cell(91,4,(string)$i['uniref'],0,0,'C');
        $paid=(float)($i['paid_amount']??0);$amount=(float)$i['amount'];$balance=max(0,$amount-$paid);$pdf->SetFillColor(...self::PALE);$pdf->Rect(119,147,69,31,'F');$summary=[['Totali',$amount],['Paguar',$paid],['Bilanci',$balance]];$y=151;foreach($summary as $idx=>[$label,$value]){$pdf->SetXY(123,$y);$pdf->SetFont('Arial',$idx===2?'B':'',8);$pdf->Cell(34,6,$this->t($label));$pdf->SetFont('Arial','B',8);$pdf->Cell(27,6,$this->money($value).' EUR',0,0,'R');if($idx===1)$pdf->Line(123,$y+7,184,$y+7);$y+=8;}
        $pdf->SetFillColor(235,241,246);$pdf->Rect(22,185,166,10,'F');$widths=[42,52,72];$labels=['Adresa:','Email:','IBAN:'];$x=22;foreach($labels as $k=>$label){$pdf->SetXY($x+3,188);$pdf->SetFont('Arial','B',7);$pdf->Cell($widths[$k]-6,4,$this->t($label));$x+=$widths[$k];}$pdf->SetXY(25,199);$pdf->SetFont('Arial','',7);$pdf->MultiCell(38,4,$this->t($this->config->string('MUNICIPALITY_ADDRESS')));$pdf->SetXY(67,199);$pdf->MultiCell(48,4,$this->t($this->config->string('MUNICIPALITY_CONTACT')));$pdf->SetXY(119,199);$pdf->MultiCell(66,4,$this->t($this->config->string('MUNICIPAL_BANK_ACCOUNT')));
        $pdf->SetTextColor(90,105,120);$pdf->SetXY(22,226);$pdf->SetFont('Arial','',7);$pdf->MultiCell(166,4,$this->t('Kjo faturë është lëshuar nga sistemi komunal për tarifën vjetore të ushtrimit të veprimtarisë. Pagesa realizohet duke përdorur UNIREF-in e paraqitur në faturë.'));$pdf->SetX(22);$pdf->SetFont('Arial','B',7);$pdf->Cell(166,5,$this->t('AFATI: '.($i['due_on']??'').'  |  Dokument i gjeneruar dhe nënshkruar në mënyrë digjitale.'));
    }
    private function card(\FPDF $pdf,float $x,float $y,float $w,float $h,string $title):void{$pdf->SetFillColor(...self::PALE);$pdf->Rect($x,$y,$w,$h,'DF');$pdf->SetTextColor(...self::BLUE);$pdf->SetXY($x+4,$y+5);$pdf->SetFont('Arial','B',9);$pdf->Cell($w-8,5,$this->t($title));$pdf->SetTextColor(24,43,63);}
    private function barcode(\FPDF $pdf,float $x,float $y,float $w,float $h,string $value):void{$value='*'.strtoupper($value).'*';$bits='';foreach(str_split($value) as $char)$bits.=(self::CODE39[$char]??self::CODE39['-']).'0';$unit=$w/max(1,strlen($bits));$pdf->SetFillColor(0,0,0);foreach(str_split($bits) as $index=>$bit)if($bit==='1')$pdf->Rect($x+$index*$unit,$y,$unit+.03,$h,'F');}
    private function status(array $i):string{$paid=(float)($i['paid_amount']??0);$amount=(float)($i['amount']??0);return $paid>=$amount&&$amount>0?'PAGUAR':($paid>0?'PJESËRISHT E PAGUAR':'PAPAGUAR');}
    private function money(mixed $amount):string{return number_format((float)$amount,2,'.',',');}
    private function t(string $value):string
    {
        if($value==='Klienti')$value='Subjekti';
        if($value==='Çmimi')$value='Tarifa';
        if($this->naceDescription!==''&&$value===mb_substr($this->naceDescription,0,72))$value=mb_substr('Taksa për ushtrimin e veprimtarisë: '.$this->naceDescription,0,100);
        if(str_starts_with($value,'Kjo faturë është lëshuar nga sistemi komunal'))$value=$this->config->string('INVOICE_LEGAL_TEXT');
        return $this->pdfText($value);
    }
}
