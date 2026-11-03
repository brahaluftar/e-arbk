<?php
declare(strict_types=1);
namespace App\Document;

/**
 * PDF text in Windows-1250 with an embedded Liberation Sans font: covers Albanian (ë, ç),
 * Serbian Latin (č, ć, š, ž, đ), English and the euro sign.
 */
trait PdfText
{
    private function pdfText(string $value): string
    {
        $encoded=iconv('UTF-8','Windows-1250//TRANSLIT//IGNORE',$value);
        return is_string($encoded)?$encoded:'';
    }

    private function registerFonts(\FPDF $pdf): void
    {
        $directory=dirname(__DIR__,2).'/resources/fonts/';
        $pdf->AddFont(PdfFont::NAME,'','LiberationSans-Regular.json',$directory);
        $pdf->AddFont(PdfFont::NAME,'B','LiberationSans-Bold.json',$directory);
    }
}
