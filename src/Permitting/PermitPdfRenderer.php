<?php
declare(strict_types=1);
namespace App\Permitting;
use App\Document\PdfText;
use App\Support\Config;
use RuntimeException;
final class PermitPdfRenderer
{
    use PdfText;
    public function __construct(private Config $config){}
    /** @param array<string,mixed> $permit */
    public function render(array $permit,string $qrPng):string
    {
        $temporary=tempnam(sys_get_temp_dir(),'arbk-qr-');if(!is_string($temporary)||file_put_contents($temporary,$qrPng,LOCK_EX)!==strlen($qrPng))throw new RuntimeException('QR nuk mund të përgatitej.');
        try{$pdf=new \FPDF('P','mm','A4');$pdf->SetCompression(false);$pdf->SetMargins(18,15,18);$pdf->AddPage();$pdf->SetFillColor(8,46,81);$pdf->Rect(18,15,174,38,'F');$pdf->SetFillColor(255,255,255);$pdf->Rect(23,19,18,28,'F');$pdf->SetTextColor(8,46,81);$pdf->SetFont('Arial','B',20);$pdf->SetXY(27,29);$pdf->Cell(10,8,'P',0,0,'C');$pdf->SetTextColor(255,255,255);$pdf->SetXY(47,23);$pdf->SetFont('Arial','B',15);$pdf->Cell(0,8,$this->pdfText($this->config->string('MUNICIPALITY_NAME')),0,1);$pdf->SetX(47);$pdf->SetFont('Arial','B',13);$pdf->Cell(0,8,$this->pdfText('LEJE E PUNËS'),0,1);
            $pdf->SetTextColor(20,35,60);$pdf->SetY(61);$pdf->SetFont('Arial','B',16);$pdf->Cell(0,9,$this->pdfText((string)$permit['serial_number']),0,1,'C');$pdf->Ln(3);
            foreach(['Emri i biznesit'=>'business_name_snapshot','Adresa'=>'business_address_snapshot','Kodi NACE'=>'registered_nace_code_snapshot','Veprimtaria'=>'registered_nace_activity_snapshot','Kodi sipas NACE_LIST'=>'tariff_nace_code_snapshot','Përshkrimi NACE_LIST'=>'nace_description_snapshot','Data e lëshimit'=>'issued_on'] as $label=>$key)$this->line($pdf,$label,(string)($permit[$key]??''));$this->line($pdf,'Vlefshmëria',(string)$permit['valid_from'].' – '.(string)$permit['valid_until']);
            $pdf->Ln(5);$y=$pdf->GetY();$pdf->SetFillColor(237,245,251);$pdf->Rect(60,$y,90,70,'DF');$pdf->Image($temporary,80,$y+3,50,50,'PNG');$pdf->SetY($y+54);$pdf->SetFont('Arial','B',9);$pdf->Cell(0,5,$this->pdfText('Verifikimi publik me QR Kod'),0,1,'C');$pdf->SetFont('Arial','',7);$pdf->MultiCell(0,4,$this->pdfText((string)$permit['verification_url']),0,'C');
            $pdf->Ln(5);$pdf->SetFont('Arial','',8);$pdf->MultiCell(0,5,$this->pdfText('Kjo leje është gjeneruar dhe nënshkruar në mënyrë digjitale nga sistemi i Komunës së Prishtinës.'),0,'C');$pdf->Ln(3);$pdf->Cell(75,18,$this->pdfText('Vendi për nënshkrim elektronik'),1,0,'C');$pdf->Cell(24,18,'',0,0);$pdf->Cell(75,18,$this->pdfText('Vendi për vulën'),1,1,'C');
            $out=$pdf->Output('S');if(!is_string($out)||!str_starts_with($out,'%PDF-'))throw new RuntimeException('Gjenerimi i lejes PDF dështoi.');return $out;
        }finally{if(is_file($temporary))unlink($temporary);}
    }
    private function line(\FPDF $pdf,string $label,string $value):void{$pdf->SetFillColor(247,249,251);$pdf->SetFont('Arial','B',8);$pdf->Cell(48,8,$this->pdfText($label),1,0,'L',true);$pdf->SetFont('Arial','',8);$pdf->Cell(126,8,$this->pdfText(mb_substr($value,0,100)),1,1);}
}
