<?php
declare(strict_types=1);
namespace App\Document;
trait PdfText
{
    private function pdfText(string $value): string
    {
        $encoded=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$value);
        return is_string($encoded)?$encoded:'';
    }
}
