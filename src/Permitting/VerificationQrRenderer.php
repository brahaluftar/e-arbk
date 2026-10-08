<?php
declare(strict_types=1);
namespace App\Permitting;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
final class VerificationQrRenderer
{
    public function render(string $url):string{$qr=new QrCode(data:$url,encoding:new Encoding('ISO-8859-1'),errorCorrectionLevel:ErrorCorrectionLevel::Medium,size:320,margin:16,roundBlockSizeMode:RoundBlockSizeMode::Margin);return(new PngWriter())->write($qr)->getString();}
}
